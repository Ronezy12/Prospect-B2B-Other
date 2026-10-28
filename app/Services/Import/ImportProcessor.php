<?php

namespace App\Services\Import;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\AuditLogger;
use App\Services\DuplicateFinder;
use App\Services\SuppressionService;
use App\Support\Normalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Traite un import ligne par ligne. Chaque ligne est enregistrée dans
 * pm_import_rows dans la même transaction que ses effets : en cas de
 * redémarrage, les lignes déjà traitées sont ignorées (reprise idempotente).
 */
class ImportProcessor
{
    /** Stratégies face à un doublon. */
    public const STRATEGIES = [
        'skip' => 'Ignorer la ligne (ne rien modifier)',
        'fill_empty' => 'Compléter uniquement les champs vides de la fiche existante',
        'overwrite' => 'Mettre à jour la fiche existante avec les valeurs du fichier',
    ];

    private User $user;

    private Import $import;

    /** @var array<string, int|null> */
    private array $sectorCache = [];

    /** @var array<string, int> */
    private array $sourceCache = [];

    /** @var array<string, int|null> */
    private array $ownerCache = [];

    public function __construct(
        private readonly DuplicateFinder $duplicates,
        private readonly SuppressionService $suppressions,
        private readonly ActivityRecorder $activities,
        private readonly AuditLogger $audit,
    ) {}

    public function process(Import $import): void
    {
        $this->import = $import;
        $this->user = $import->user ?? throw new \RuntimeException('Auteur de l\'import introuvable.');

        $import->update(['status' => 'processing', 'started_at' => $import->started_at ?? now()]);

        $done = ImportRow::where('import_id', $import->id)->pluck('row_number')->flip();
        $reader = new ImportReader(Storage::disk(config('prospection.import.disk'))->path($import->stored_path), $import->file_type);

        $counter = 0;
        foreach ($reader->rows() as $number => $cells) {
            if ($done->has($number)) {
                continue;
            }
            if (++$counter % 100 === 0) {
                $import->refresh();
                if ($import->status === 'cancelled') {
                    return;
                }
                $this->refreshCounters();
            }
            $this->processRow($number, $cells);
        }

        $this->refreshCounters();
        $import->update(['status' => 'completed', 'finished_at' => now()]);
        $this->audit->log('import.completed', $import, [
            'créés' => $import->created_count,
            'mis_à_jour' => $import->updated_count,
            'ignorés' => $import->skipped_count,
            'erreurs' => $import->error_count,
        ], $this->user->id);
    }

    private function refreshCounters(): void
    {
        $counts = ImportRow::where('import_id', $this->import->id)
            ->selectRaw('result, count(*) as total')->groupBy('result')->pluck('total', 'result');

        $this->import->update([
            'processed_rows' => $counts->sum(),
            'created_count' => (int) ($counts['created'] ?? 0),
            'updated_count' => (int) ($counts['updated'] ?? 0),
            'skipped_count' => (int) ($counts['skipped_duplicate'] ?? 0) + (int) ($counts['skipped_suppressed'] ?? 0),
            'error_count' => (int) ($counts['error'] ?? 0),
        ]);
    }

    /** @param array<int, string> $cells */
    public function processRow(int $number, array $cells): void
    {
        $data = $this->extract($cells);
        [$errors, $warnings] = $this->validate($data);

        if ($errors) {
            ImportRow::create(['import_id' => $this->import->id, 'row_number' => $number, 'result' => 'error', 'messages' => array_merge($errors, $warnings)]);

            return;
        }

        try {
            DB::transaction(function () use ($number, $data, $warnings) {
                $messages = $warnings;
                [$company, $companyResult] = $this->upsertCompany($data, $messages);
                [$contact, $contactResult] = $company ? $this->upsertContact($company, $data, $messages) : [null, null];

                $result = $companyResult;
                if ($contactResult === 'skipped_suppressed') {
                    $result = 'skipped_suppressed';
                } elseif ($contactResult === 'created' && $companyResult === 'skipped_duplicate') {
                    $result = 'updated';
                }

                ImportRow::create([
                    'import_id' => $this->import->id,
                    'row_number' => $number,
                    'result' => $result,
                    'messages' => $messages ?: null,
                    'company_id' => $company?->id,
                    'contact_id' => $contact?->id,
                ]);
            });
        } catch (Throwable $e) {
            report($e);
            ImportRow::updateOrCreate(
                ['import_id' => $this->import->id, 'row_number' => $number],
                ['result' => 'error', 'messages' => ['Erreur technique lors de l\'enregistrement de la ligne.']],
            );
        }
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<string, string|null>
     */
    private function extract(array $cells): array
    {
        $data = [];
        foreach ((array) $this->import->mapping as $field => $index) {
            if ($index === null || $index === '' || ! array_key_exists($field, ImportFields::FIELDS)) {
                continue;
            }
            $value = $cells[(int) $index] ?? '';
            $data[$field] = $value === '' ? null : mb_substr($value, 0, 2000);
        }

        return $data;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function validate(array &$data): array
    {
        $errors = [];
        $warnings = [];

        if (blank($data['company_name'] ?? null)) {
            $errors[] = 'Raison sociale manquante.';
        } elseif (mb_strlen($data['company_name']) > 190) {
            $errors[] = 'Raison sociale trop longue (190 caractères maximum).';
        }

        foreach (['contact_email' => 'E-mail du contact', 'company_email' => 'E-mail de l\'entreprise'] as $field => $label) {
            if (filled($data[$field] ?? null) && ! filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "{$label} invalide : « {$data[$field]} ».";
            }
        }

        if (filled($data['registration_number'] ?? null) && ! Normalizer::registrationNumber($data['registration_number'])) {
            $warnings[] = 'SIREN/SIRET ignoré : 9 ou 14 chiffres attendus.';
            $data['registration_number'] = null;
        }

        if (filled($data['website'] ?? null) && ! Normalizer::domain($data['website'])) {
            $warnings[] = 'Site web ignoré : format invalide.';
            $data['website'] = null;
        }

        foreach (['acquired_at', 'collected_at'] as $field) {
            if (filled($data[$field] ?? null)) {
                $date = $this->parseDate($data[$field]);
                if (! $date) {
                    $warnings[] = "Date « {$data[$field]} » non reconnue : ignorée.";
                }
                $data[$field] = $date;
            }
        }

        if (filled($data['status'] ?? null)) {
            $status = $this->matchOption($data['status'], config('prospection.company_statuses'));
            if (! $status) {
                $warnings[] = "Statut « {$data['status']} » inconnu : statut par défaut appliqué.";
            }
            $data['status'] = $status;
        }

        if (filled($data['audience_type'] ?? null)) {
            $type = Str::lower(trim($data['audience_type']));
            $data['audience_type'] = in_array($type, ['b2b', 'b2c'], true) ? $type : null;
            if (! $data['audience_type']) {
                $warnings[] = 'Type de contact non reconnu (b2b ou b2c attendu) : valeur par défaut appliquée.';
            }
        }

        if (filled($data['legal_basis'] ?? null)) {
            $basis = $this->matchOption($data['legal_basis'], config('prospection.legal_bases'));
            if (! $basis) {
                $warnings[] = 'Base légale non reconnue : valeur par défaut appliquée.';
            }
            $data['legal_basis'] = $basis;
        }

        if (filled($data['country'] ?? null)) {
            $country = Str::upper(trim($data['country']));
            $data['country'] = match (true) {
                in_array($country, ['FRANCE', 'FR'], true) => 'FR',
                (bool) preg_match('/^[A-Z]{2}$/', $country) => $country,
                default => null,
            };
        }

        $country = $data['country'] ?? 'FR';
        if (filled($data['postal_code'] ?? null) && $country === 'FR') {
            $postal = preg_replace('/\s+/', '', $data['postal_code']);
            if (preg_match('/^\d{4}$/', $postal)) {
                $postal = '0'.$postal; // zéro initial perdu par Excel
            }
            if (! preg_match('/^\d{5}$/', $postal)) {
                $warnings[] = "Code postal « {$data['postal_code']} » non conforme au format français.";
            }
            $data['postal_code'] = mb_substr($postal, 0, 12);
        }

        return [$errors, $warnings];
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        foreach (['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /** @param array<string, string> $options */
    private function matchOption(string $value, array $options): ?string
    {
        $needle = Str::lower(Str::ascii(trim($value)));
        foreach ($options as $key => $label) {
            if ($needle === $key || $needle === Str::lower(Str::ascii($label))) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $data
     * @param  array<int, string>  $messages
     * @return array{0: ?Company, 1: string}
     */
    private function upsertCompany(array $data, array &$messages): array
    {
        $options = (array) $this->import->options;
        $strategy = $options['duplicate_strategy'] ?? 'skip';

        $attributes = array_filter([
            'name' => $data['company_name'],
            'registration_number' => $data['registration_number'] ?? null,
            'sector_id' => $this->sectorId($data['sector'] ?? null, null) ?? ($options['default_sector_id'] ?? null),
            'subsector_id' => $this->sectorId($data['subsector'] ?? null, $this->sectorId($data['sector'] ?? null, null) ?? ($options['default_sector_id'] ?? null)),
            'website' => $data['website'] ?? null,
            'email' => $data['company_email'] ?? null,
            'phone' => $data['company_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'source_id' => $this->sourceId($data['source'] ?? null) ?? ($options['default_source_id'] ?? null),
            'acquired_at' => $data['acquired_at'] ?? null,
            'status' => $data['status'] ?? null,
            'notes' => $data['company_notes'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (filled($data['sector'] ?? null) && ! $this->sectorId($data['sector'], null)) {
            $messages[] = "Secteur « {$data['sector']} » inconnu : secteur par défaut appliqué.";
        }

        $existing = $this->duplicates->findCompany(['name' => $data['company_name']] + $data);

        if ($existing) {
            if ($existing->trashed()) {
                $messages[] = 'Doublon d\'une fiche supprimée : ligne ignorée (restauration manuelle possible).';

                return [null, 'skipped_duplicate'];
            }
            if ($existing->isClient()) {
                $messages[] = 'Cette entreprise existe déjà comme client : aucune modification (prospects et clients restent séparés).';

                return [null, 'skipped_duplicate'];
            }
            if (! $existing->isVisibleTo($this->user)) {
                $messages[] = 'Doublon d\'une fiche hors de votre périmètre : ligne ignorée.';

                return [null, 'skipped_duplicate'];
            }

            $messages[] = "Doublon détecté : fiche #{$existing->id} « {$existing->name} ».";
            if ($strategy === 'skip') {
                return [$existing, 'skipped_duplicate'];
            }

            unset($attributes['name']);
            if ($strategy === 'fill_empty') {
                $attributes = array_filter($attributes, fn ($v, $k) => blank($existing->getAttribute($k)), ARRAY_FILTER_USE_BOTH);
            }
            $existing->fill($attributes);
            if (! $existing->isDirty()) {
                $this->syncTags($existing, $data);

                return [$existing, 'skipped_duplicate'];
            }
            $existing->save();
            $changes = $this->audit->diff($existing);
            $this->syncTags($existing, $data);
            $this->activities->record($existing, 'systeme', "Fiche mise à jour par l'import #{$this->import->id}.", ['import_id' => $this->import->id, 'champs' => array_keys($changes)], $this->user->id);

            return [$existing, 'updated'];
        }

        $company = new Company($attributes + ['status' => 'nouveau', 'acquired_at' => now()->toDateString()]);
        $company->owner_id = $this->ownerId($data['owner_email'] ?? null);
        $company->created_by = $this->user->id;
        $company->save();
        $this->syncTags($company, $data);
        $this->activities->record($company, 'systeme', "Fiche créée par l'import #{$this->import->id} ({$this->import->original_name}).", ['import_id' => $this->import->id], $this->user->id);

        return [$company, 'created'];
    }

    /**
     * @param  array<string, string|null>  $data
     * @param  array<int, string>  $messages
     * @return array{0: ?Contact, 1: ?string}
     */
    private function upsertContact(Company $company, array $data, array &$messages): array
    {
        $hasContact = filled($data['contact_email'] ?? null) || filled($data['last_name'] ?? null) || filled($data['first_name'] ?? null);
        if (! $hasContact) {
            return [null, null];
        }

        $email = Normalizer::email($data['contact_email'] ?? null);
        if ($email && $this->suppressions->isSuppressed($email)) {
            $messages[] = 'Contact non importé : adresse présente dans la liste d\'opposition.';

            return [null, 'skipped_suppressed'];
        }

        $options = (array) $this->import->options;
        $attributes = array_filter([
            'civility' => $data['civility'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'email' => $email,
            'phone' => $data['contact_phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'audience_type' => $data['audience_type'] ?? null,
            'legal_basis' => $data['legal_basis'] ?? null,
            'collected_at' => $data['collected_at'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $existing = $email
            ? $this->duplicates->findContactByEmail($email)
            : $company->contacts()->withTrashed()
                ->where('first_name', $data['first_name'] ?? null)
                ->where('last_name', $data['last_name'] ?? null)->first();

        if ($existing) {
            if ($existing->trashed() || $existing->company_id !== $company->id) {
                $messages[] = 'Contact déjà présent sur une autre fiche ou supprimé : non modifié.';

                return [$existing->trashed() ? null : $existing, 'skipped_duplicate'];
            }
            $strategy = $options['duplicate_strategy'] ?? 'skip';
            if ($strategy !== 'skip') {
                if ($strategy === 'fill_empty') {
                    $attributes = array_filter($attributes, fn ($v, $k) => blank($existing->getAttribute($k)), ARRAY_FILTER_USE_BOTH);
                }
                $existing->fill($attributes)->save();
            }

            return [$existing, 'skipped_duplicate'];
        }

        if (($attributes['audience_type'] ?? $options['default_audience_type'] ?? 'b2b') === 'b2c'
            && ($attributes['legal_basis'] ?? $options['default_legal_basis'] ?? null) !== 'consentement') {
            $messages[] = 'Contact B2C sans consentement : il sera exclu des campagnes de prospection.';
        }

        $contact = new Contact($attributes + [
            'audience_type' => $options['default_audience_type'] ?? 'b2b',
            'legal_basis' => $options['default_legal_basis'] ?? 'a_determiner',
            'collected_at' => $options['default_collected_at'] ?? now()->toDateString(),
            'collection_note' => $options['collection_note'] ?? "Import #{$this->import->id}",
        ]);
        $contact->company_id = $company->id;
        $contact->source_id = $company->source_id;
        $contact->owner_id = $company->owner_id;
        $contact->created_by = $this->user->id;
        $contact->is_primary = ! $company->contacts()->exists();
        $contact->save();

        return [$contact, 'created'];
    }

    /** @param array<string, string|null> $data */
    private function syncTags(Company $company, array $data): void
    {
        $ids = Tag::idsFromString($data['tags'] ?? null);
        foreach ((array) (($this->import->options['tag_ids'] ?? [])) as $id) {
            $ids[] = (int) $id;
        }
        if ($ids) {
            $company->tags()->syncWithoutDetaching(array_unique($ids));
        }
    }

    private function sectorId(?string $name, ?int $parentId): ?int
    {
        if (blank($name)) {
            return null;
        }
        $key = ($parentId ?? 0).'|'.Str::lower(Str::ascii(trim($name)));
        if (! array_key_exists($key, $this->sectorCache)) {
            $this->sectorCache[$key] = Sector::query()
                ->where('parent_id', $parentId)
                ->where(fn ($q) => $q->where('name', trim($name))->orWhere('slug', Str::slug($name)))
                ->value('id');
        }

        return $this->sectorCache[$key];
    }

    private function sourceId(?string $name): ?int
    {
        if (blank($name)) {
            return null;
        }
        $name = mb_substr(trim($name), 0, 120);
        $key = Str::lower($name);

        return $this->sourceCache[$key] ??= Source::firstOrCreate(['name' => $name])->id;
    }

    /**
     * Le responsable désigné dans le fichier n'est retenu que si l'auteur de
     * l'import a le droit d'assigner des fiches ; sinon l'auteur est responsable.
     */
    private function ownerId(?string $email): ?int
    {
        $options = (array) $this->import->options;
        if (! $this->user->can('prospects.assign')) {
            return $this->user->id;
        }
        $email = Normalizer::email($email);
        if ($email) {
            $this->ownerCache[$email] ??= User::where('email', $email)->where('is_active', true)->value('id');
            if ($this->ownerCache[$email]) {
                return $this->ownerCache[$email];
            }
        }

        return $options['default_owner_id'] ?? $this->user->id;
    }
}
