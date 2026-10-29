<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

/** Champs cibles proposés dans l'écran de correspondance des colonnes. */
final class ImportFields
{
    public const FIELDS = [
        'company_name' => ['label' => 'Entreprise — raison sociale', 'group' => 'Entreprise', 'aliases' => ['entreprise', 'societe', 'raison sociale', 'nom entreprise', 'company', 'nom de l entreprise', 'denomination']],
        'registration_number' => ['label' => 'Entreprise — SIREN / SIRET', 'group' => 'Entreprise', 'aliases' => ['siren', 'siret', 'numero siren', 'numero siret']],
        'sector' => ['label' => 'Entreprise — secteur', 'group' => 'Entreprise', 'aliases' => ['secteur', 'activite', 'categorie', 'sector']],
        'subsector' => ['label' => 'Entreprise — sous-secteur', 'group' => 'Entreprise', 'aliases' => ['sous secteur', 'sous-secteur']],
        'website' => ['label' => 'Entreprise — site web', 'group' => 'Entreprise', 'aliases' => ['site', 'site web', 'site internet', 'website', 'url']],
        'company_email' => ['label' => 'Entreprise — e-mail générique', 'group' => 'Entreprise', 'aliases' => ['email entreprise', 'e-mail entreprise', 'email societe']],
        'company_phone' => ['label' => 'Entreprise — téléphone standard', 'group' => 'Entreprise', 'aliases' => ['telephone entreprise', 'standard', 'tel entreprise']],
        'address' => ['label' => 'Entreprise — adresse', 'group' => 'Entreprise', 'aliases' => ['adresse', 'rue', 'address']],
        'postal_code' => ['label' => 'Entreprise — code postal', 'group' => 'Entreprise', 'aliases' => ['code postal', 'cp', 'zip', 'postal code']],
        'city' => ['label' => 'Entreprise — ville', 'group' => 'Entreprise', 'aliases' => ['ville', 'commune', 'city']],
        'country' => ['label' => 'Entreprise — pays (code ISO)', 'group' => 'Entreprise', 'aliases' => ['pays', 'country']],
        'source' => ['label' => 'Source d\'acquisition', 'group' => 'Entreprise', 'aliases' => ['source', 'origine', 'provenance']],
        'acquired_at' => ['label' => 'Date d\'acquisition', 'group' => 'Entreprise', 'aliases' => ['date acquisition', 'date d acquisition']],
        'status' => ['label' => 'Statut commercial', 'group' => 'Entreprise', 'aliases' => ['statut', 'status']],
        'tags' => ['label' => 'Tags (séparés par des virgules)', 'group' => 'Entreprise', 'aliases' => ['tags', 'etiquettes', 'mots cles']],
        'company_notes' => ['label' => 'Notes entreprise', 'group' => 'Entreprise', 'aliases' => ['notes', 'commentaire', 'commentaires']],
        'company_linkedin' => ['label' => 'Entreprise — page LinkedIn', 'group' => 'Entreprise', 'aliases' => ['linkedin entreprise', 'page linkedin']],
        'company_facebook' => ['label' => 'Entreprise — page Facebook', 'group' => 'Entreprise', 'aliases' => ['facebook', 'page facebook']],
        'company_instagram' => ['label' => 'Entreprise — compte Instagram', 'group' => 'Entreprise', 'aliases' => ['instagram', 'compte instagram']],
        'owner_email' => ['label' => 'Responsable (e-mail utilisateur)', 'group' => 'Entreprise', 'aliases' => ['responsable', 'commercial', 'owner']],
        'civility' => ['label' => 'Contact — civilité', 'group' => 'Contact', 'aliases' => ['civilite', 'titre']],
        'first_name' => ['label' => 'Contact — prénom', 'group' => 'Contact', 'aliases' => ['prenom', 'first name', 'firstname']],
        'last_name' => ['label' => 'Contact — nom', 'group' => 'Contact', 'aliases' => ['nom', 'nom de famille', 'last name', 'lastname']],
        'job_title' => ['label' => 'Contact — fonction', 'group' => 'Contact', 'aliases' => ['fonction', 'poste', 'job title', 'titre du poste']],
        'contact_email' => ['label' => 'Contact — e-mail', 'group' => 'Contact', 'aliases' => ['email', 'e-mail', 'mail', 'courriel', 'email contact', 'adresse email']],
        'contact_phone' => ['label' => 'Contact — téléphone', 'group' => 'Contact', 'aliases' => ['telephone', 'tel', 'phone', 'telephone contact']],
        'mobile' => ['label' => 'Contact — mobile', 'group' => 'Contact', 'aliases' => ['mobile', 'portable', 'gsm']],
        'linkedin_url' => ['label' => 'Contact — profil LinkedIn', 'group' => 'Contact', 'aliases' => ['linkedin', 'profil linkedin']],
        'audience_type' => ['label' => 'Contact — type (b2b / b2c)', 'group' => 'Contact', 'aliases' => ['type contact', 'b2b b2c']],
        'legal_basis' => ['label' => 'Contact — base légale', 'group' => 'Contact', 'aliases' => ['base legale']],
        'collected_at' => ['label' => 'Contact — date de collecte', 'group' => 'Contact', 'aliases' => ['date de collecte', 'date collecte']],
    ];

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_map(fn ($f) => $f['label'], self::FIELDS);
    }

    /**
     * Propose une correspondance automatique à partir des en-têtes.
     *
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    public static function guess(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $index => $header) {
            $normalized = trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($header))));
            foreach (self::FIELDS as $field => $definition) {
                if (isset($mapping[$field])) {
                    continue;
                }
                $candidates = array_merge([$field, Str::lower(Str::ascii($definition['label']))], $definition['aliases']);
                foreach ($candidates as $candidate) {
                    $candidate = trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($candidate))));
                    if ($normalized === $candidate) {
                        $mapping[$field] = $index;

                        continue 3;
                    }
                }
            }
        }

        return $mapping;
    }
}
