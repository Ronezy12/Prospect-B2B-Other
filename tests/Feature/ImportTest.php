<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\Import\ImportProcessor;
use App\Services\SuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function upload(User $user, string $content, string $name = 'prospects.csv'): Import
    {
        $this->actingAs($user)
            ->post(route('pm.imports.store'), ['file' => UploadedFile::fake()->createWithContent($name, $content)])
            ->assertRedirect();

        return Import::latest('id')->firstOrFail();
    }

    private function start(User $user, Import $import, array $overrides = []): void
    {
        $this->actingAs($user)->post(route('pm.imports.start', $import), $overrides + [
            'mapping' => $import->mapping,
            'duplicate_strategy' => 'skip',
            'default_audience_type' => 'b2b',
            'default_legal_basis' => 'interet_legitime',
            'confirm_information' => '1',
        ])->assertRedirect(route('pm.imports.show', $import));
    }

    public function test_csv_is_previewed_mapped_and_imported_with_row_report(): void
    {
        $user = $this->userWithRole('commercial');
        $csv = "Raison sociale;SIREN;Secteur;Code postal;Ville;Prénom;Nom;Fonction;E-mail\n"
            ."Agence Soleil;552100554;Agences immobilières;69003;Lyon;Paul;Morel;Gérant;paul@soleil.fr\n"
            .";;;;;;;;\n"
            .";;;75001;Paris;Anne;Roy;;anne@exemple.fr\n"
            ."Garage Nord;;Inconnu;59000;Lille;Luc;Blanc;;email-invalide\n"
            ."Agence Soleil;552 100 554;;69003;Lyon;Julie;Morel;Assistante;julie@soleil.fr\n";

        $import = $this->upload($user, $csv);
        $this->assertSame(4, $import->total_rows, 'la ligne vide est ignorée');
        $this->assertSame(0, $import->mapping['company_name']);
        $this->assertSame(8, $import->mapping['contact_email']);

        $this->actingAs($user)->get(route('pm.imports.mapping', $import))->assertOk()->assertSee('Agence Soleil');

        $this->start($user, $import);
        $import->refresh();

        $this->assertSame('completed', $import->status);
        $this->assertSame(1, $import->created_count);
        $this->assertSame(2, $import->error_count);
        $this->assertSame(1, $import->updated_count, 'doublon par SIREN : contact ajouté à la fiche existante');

        $company = Company::where('siren', '552100554')->firstOrFail();
        $this->assertSame(2, $company->contacts()->count());
        $this->assertSame('Agences immobilières', $company->sector->name);
        $this->assertSame($user->id, $company->owner_id);

        $errors = ImportRow::where('import_id', $import->id)->where('result', 'error')->orderBy('row_number')->get();
        $this->assertSame([4, 5], $errors->pluck('row_number')->all());
        $this->assertStringContainsString('Raison sociale manquante', implode(' ', $errors[0]->messages));
        $this->assertStringContainsString('E-mail du contact invalide', implode(' ', $errors[1]->messages));

        $this->actingAs($user)->get(route('pm.imports.show', $import))->assertOk()->assertSee('Raison sociale manquante');
        $this->assertStringContainsString('Raison sociale manquante', $this->actingAs($user)->get(route('pm.imports.report', $import))->streamedContent());
    }

    public function test_windows_1252_csv_with_comma_delimiter_is_read(): void
    {
        $user = $this->userWithRole('commercial');
        $csv = mb_convert_encoding("Entreprise,Ville\nCafé Équinoxe,Nîmes\n", 'Windows-1252', 'UTF-8');

        $import = $this->upload($user, $csv);
        $this->start($user, $import);

        $this->assertDatabaseHas('pm_companies', ['name' => 'Café Équinoxe', 'city' => 'Nîmes']);
    }

    public function test_xlsx_file_is_imported(): void
    {
        $user = $this->userWithRole('commercial');
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Société', 'Code postal', 'E-mail'],
            ['Syndic Horizon', '06000', 'contact@horizon.fr'],
            ['Concession Azur', '6400', 'info@azur.fr'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($path);

        $import = $this->upload($user, file_get_contents($path), 'fichier.xlsx');
        unlink($path);
        $this->assertSame('xlsx', $import->file_type);
        $this->start($user, $import);

        $this->assertSame(2, $import->fresh()->created_count);
        $this->assertDatabaseHas('pm_companies', ['name' => 'Concession Azur', 'postal_code' => '06400']);
        $this->assertDatabaseHas('pm_contacts', ['email' => 'contact@horizon.fr']);
    }

    public function test_suppressed_addresses_are_never_reintroduced(): void
    {
        $user = $this->userWithRole('commercial');
        app(SuppressionService::class)->suppress('refus@exemple.fr', 'all', 'opposition', 'test');

        $import = $this->upload($user, "Entreprise,Nom,Email\nSociété A,Dupont,REFUS@exemple.fr\n");
        $this->start($user, $import);

        $this->assertSame(0, Contact::count());
        $this->assertDatabaseHas('pm_import_rows', ['import_id' => $import->id, 'result' => 'skipped_suppressed']);
    }

    public function test_duplicate_strategies(): void
    {
        $user = $this->userWithRole('commercial');
        $existing = $this->companyFor($user, ['name' => 'Agence Rivage', 'postal_code' => '33000', 'city' => 'Bordeaux']);

        $csv = "Entreprise,Code postal,Ville,Téléphone entreprise\nAgence Rivage,33000,Mérignac,05 56 00 00 00\n";

        $import = $this->upload($user, $csv);
        $this->start($user, $import, ['duplicate_strategy' => 'skip']);
        $this->assertSame('Bordeaux', $existing->fresh()->city);
        $this->assertSame(1, $import->fresh()->skipped_count);

        $import = $this->upload($user, $csv);
        $this->start($user, $import, ['duplicate_strategy' => 'fill_empty']);
        $this->assertSame('Bordeaux', $existing->fresh()->city, 'un champ rempli n\'est pas écrasé');
        $this->assertSame('+33556000000', $existing->fresh()->phone, 'un champ vide est complété');

        $import = $this->upload($user, $csv);
        $this->start($user, $import, ['duplicate_strategy' => 'overwrite']);
        $this->assertSame('Mérignac', $existing->fresh()->city);
        $this->assertSame(1, Company::count());
    }

    public function test_existing_clients_and_foreign_records_are_never_modified(): void
    {
        $user = $this->userWithRole('commercial');
        $client = $this->companyFor($user, ['name' => 'Client Fidèle', 'postal_code' => '13001', 'city' => 'Marseille']);
        $client->forceFill(['lifecycle' => 'client'])->save();
        $foreign = $this->companyFor($this->userWithRole('commercial'), ['name' => 'Fiche Collègue', 'postal_code' => '13002', 'city' => 'Marseille']);

        $import = $this->upload($user, "Entreprise,Code postal,Ville\nClient Fidèle,13001,Aix\nFiche Collègue,13002,Aix\n");
        $this->start($user, $import, ['duplicate_strategy' => 'overwrite']);

        $this->assertSame('Marseille', $client->fresh()->city);
        $this->assertSame('Marseille', $foreign->fresh()->city);
        $this->assertSame(2, Company::count());
    }

    public function test_reprocessing_an_import_is_idempotent(): void
    {
        $user = $this->userWithRole('commercial');
        $import = $this->upload($user, "Entreprise,Email\nSociété Une,a@une.fr\nSociété Deux,b@deux.fr\n");
        $this->start($user, $import);

        // Simule une reprise après interruption : les lignes déjà traitées sont ignorées.
        $import->update(['status' => 'processing']);
        app(ImportProcessor::class)->process($import->fresh());

        $this->assertSame(2, Company::count());
        $this->assertSame(2, ImportRow::where('import_id', $import->id)->count());
    }

    public function test_information_confirmation_and_company_column_are_required(): void
    {
        $user = $this->userWithRole('commercial');
        $import = $this->upload($user, "Entreprise,Email\nSociété,a@b.fr\n");

        $this->actingAs($user)->post(route('pm.imports.start', $import), [
            'mapping' => ['contact_email' => 1],
            'duplicate_strategy' => 'skip',
            'default_audience_type' => 'b2b',
            'default_legal_basis' => 'interet_legitime',
        ])->assertSessionHasErrors('confirm_information');

        $this->actingAs($user)->post(route('pm.imports.start', $import), [
            'mapping' => ['contact_email' => 1],
            'duplicate_strategy' => 'skip',
            'default_audience_type' => 'b2b',
            'default_legal_basis' => 'interet_legitime',
            'confirm_information' => '1',
        ])->assertSessionHasErrors('mapping');

        $this->assertSame('uploaded', $import->fresh()->status);
    }

    public function test_rejected_files(): void
    {
        $user = $this->userWithRole('commercial');

        $this->actingAs($user)->post(route('pm.imports.store'), ['file' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php')])
            ->assertSessionHasErrors('file');
        $this->actingAs($user)->post(route('pm.imports.store'), ['file' => UploadedFile::fake()->createWithContent('vide.csv', "Entreprise\n")])
            ->assertSessionHasErrors('file');
        $this->assertSame(0, Import::count());
    }

    public function test_import_permissions(): void
    {
        $this->actingAs($this->userWithRole('lecture_seule'))->get(route('pm.imports.create'))->assertForbidden();

        $owner = $this->userWithRole('commercial');
        $import = $this->upload($owner, "Entreprise\nSociété\n");
        $other = $this->userWithRole('commercial');

        $this->actingAs($other)->get(route('pm.imports.show', $import))->assertForbidden();
        $this->actingAs($other)->get(route('pm.imports.mapping', $import))->assertForbidden();
        $this->actingAs($other)->post(route('pm.imports.cancel', $import))->assertForbidden();
    }

    public function test_commercial_cannot_assign_owner_through_import(): void
    {
        $commercial = $this->userWithRole('commercial');
        $colleague = $this->userWithRole('commercial', ['email' => 'collegue@exemple.fr']);

        $import = $this->upload($commercial, "Entreprise,Responsable\nSociété X,collegue@exemple.fr\n");
        $this->start($commercial, $import, ['default_owner_id' => $colleague->id]);

        $this->assertSame($commercial->id, Company::firstOrFail()->owner_id);
    }

    public function test_old_import_files_are_purged(): void
    {
        $user = $this->userWithRole('commercial');
        $import = $this->upload($user, "Entreprise\nSociété\n");
        $this->start($user, $import);
        $path = $import->fresh()->stored_path;
        Storage::disk('local')->assertExists($path);

        $this->travel(config('prospection.import.retention_days') + 1)->days();
        $this->artisan('pm:purge-import-files')->assertSuccessful();

        Storage::disk('local')->assertMissing($path);
        $this->assertNull($import->fresh()->stored_path);
        $this->assertSame(1, $import->rows()->count(), 'le rapport est conservé');
    }
}
