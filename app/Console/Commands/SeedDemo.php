<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\MailAccount;
use App\Models\NewsletterList;
use App\Models\Opportunity;
use App\Models\Sector;
use App\Models\Segment;
use App\Models\Source;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Team;
use App\Models\Template;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\Mail\CampaignSender;
use App\Services\Mail\CampaignService;
use App\Services\PipelineService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Environnement de test : comptes de démonstration et entreprises fictives,
 * toutes marquées du tag « Exemple démo » et sur des domaines .test non routables.
 * Refuse de s'exécuter en production.
 */
class SeedDemo extends Command
{
    protected $signature = 'pm:demo {--force : autoriser hors environnement local/test}';

    protected $description = 'Crée des comptes et des données de démonstration (environnement de test uniquement)';

    public const PASSWORD = 'Demo-Prospection-2026';

    private const COMPANIES = [
        ['Agence Horizon Immobilier', 'agences-immobilieres', '69006', 'Lyon', 'qualifie', 'Claire', 'Morel', 'Directrice d\'agence'],
        ['Les Clés du Vieux-Port', 'agences-immobilieres', '13002', 'Marseille', 'nouveau', 'Julien', 'Ferrand', 'Gérant'],
        ['Immo Rive Gauche', 'agences-immobilieres', '33800', 'Bordeaux', 'a_qualifier', 'Sarah', 'Lemaire', 'Responsable transactions'],
        ['Cabinet Lumière Habitat', 'agences-immobilieres', '59000', 'Lille', 'nouveau', 'Thomas', 'Vasseur', 'Associé'],
        ['Atlantique Patrimoine', 'agences-immobilieres', '44000', 'Nantes', 'en_sommeil', 'Inès', 'Garnier', 'Négociatrice'],
        ['Garage des Alpes Automobiles', 'concessionnaires-automobiles', '38000', 'Grenoble', 'qualifie', 'Marc', 'Rolland', 'Directeur de concession'],
        ['Azur Motors', 'concessionnaires-automobiles', '06200', 'Nice', 'a_qualifier', 'Nadia', 'Benali', 'Responsable après-vente'],
        ['Centre Auto Loire', 'concessionnaires-automobiles', '37000', 'Tours', 'nouveau', 'Pierre', 'Chevalier', 'Gérant'],
        ['Occasions du Rhin', 'concessionnaires-automobiles', '67000', 'Strasbourg', 'non_pertinent', 'Lucas', 'Weber', 'Chef des ventes'],
        ['Brasserie du Marché', 'restaurants-et-commerces', '75011', 'Paris', 'qualifie', 'Émilie', 'Roux', 'Propriétaire'],
        ['Le Comptoir Gourmand', 'restaurants-et-commerces', '31000', 'Toulouse', 'nouveau', 'Antoine', 'Blanc', 'Gérant'],
        ['Fleurs & Saisons', 'restaurants-et-commerces', '35000', 'Rennes', 'a_qualifier', 'Camille', 'Perrin', 'Fondatrice'],
        ['La Table des Canuts', 'restaurants-et-commerces', '69004', 'Lyon', 'nouveau', 'Hugo', 'Dumas', 'Chef propriétaire'],
        ['Boulangerie Saint-Michel', 'restaurants-et-commerces', '13001', 'Marseille', 'en_sommeil', 'Léa', 'Fabre', 'Gérante'],
        ['Syndic Les Tilleuls', 'syndics-de-copropriete', '69003', 'Lyon', 'qualifie', 'Bernard', 'Giraud', 'Gestionnaire de copropriété'],
        ['Cabinet Gestion Lumière', 'syndics-de-copropriete', '75015', 'Paris', 'a_qualifier', 'Sophie', 'Marchand', 'Directrice'],
        ['Syndic Côte Basque', 'syndics-de-copropriete', '64100', 'Bayonne', 'nouveau', 'Iker', 'Etcheverry', 'Gestionnaire'],
        ['Résidences du Parc Gestion', 'syndics-de-copropriete', '21000', 'Dijon', 'nouveau', 'Mathilde', 'Noël', 'Comptable copropriété'],
        ['Atelier Bois & Design', 'professionnels-et-entreprises-tous-secteurs', '74000', 'Annecy', 'qualifie', 'Romain', 'Collet', 'Dirigeant'],
        ['Transports Garonne Express', 'professionnels-et-entreprises-tous-secteurs', '31200', 'Toulouse', 'a_qualifier', 'Karim', 'Haddad', 'Responsable logistique'],
        ['Clinique Vétérinaire des Pins', 'professionnels-et-entreprises-tous-secteurs', '40000', 'Mont-de-Marsan', 'nouveau', 'Pauline', 'Lacoste', 'Vétérinaire associée'],
        ['Cabinet Expertise Comptable Vauban', 'professionnels-et-entreprises-tous-secteurs', '59800', 'Lille', 'nouveau', 'Olivier', 'Delattre', 'Expert-comptable'],
        ['Studio Pixel Ouest', 'professionnels-et-entreprises-tous-secteurs', '29200', 'Brest', 'non_pertinent', 'Maëlle', 'Le Gall', 'Gérante'],
        ['Hôtel du Lac Bleu', 'autres', '74400', 'Chamonix', 'a_qualifier', 'François', 'Bonnet', 'Directeur'],
    ];

    public function handle(ActivityRecorder $activities, PipelineService $pipelines): int
    {
        if (! app()->environment(['local', 'testing', 'demo']) && ! $this->option('force')) {
            $this->error('Refusé : les données de démonstration ne doivent pas être créées en production (utilisez --force en connaissance de cause).');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        DB::transaction(function () use ($activities, $pipelines) {
            $team = Team::firstOrCreate(['name' => 'Équipe Rhône-Alpes']);
            $users = [
                'admin' => $this->user('admin@demo.test', 'Alexandre Martin', 'administrateur', null),
                'manager' => $this->user('responsable@demo.test', 'Nora Lefèvre', 'responsable_commercial', $team->id),
                'sales' => $this->user('commercial@demo.test', 'Yanis Moreau', 'commercial', $team->id),
                'sales2' => $this->user('commercial2@demo.test', 'Julie Caron', 'commercial', null),
                'dpo' => $this->user('conformite@demo.test', 'Hélène Simon', 'conformite', null),
            ];

            $tag = Tag::firstOrCreate(['name' => 'Exemple démo']);
            $sources = Source::pluck('id')->all();
            $owners = [$users['sales']->id, $users['sales']->id, $users['manager']->id, $users['sales2']->id, null];

            foreach (self::COMPANIES as $i => [$name, $sectorSlug, $postal, $city, $status, $first, $last, $job]) {
                if (Company::where('name', $name)->exists()) {
                    continue;
                }
                $slug = Str::slug($name);
                $company = new Company([
                    'name' => $name,
                    'sector_id' => Sector::where('slug', $sectorSlug)->value('id'),
                    'website' => "https://www.{$slug}.test",
                    'phone' => '04 '.sprintf('%02d %02d %02d %02d', 10 + $i, 20 + $i, 30 + $i, 40 + $i),
                    'postal_code' => $postal,
                    'city' => $city,
                    'status' => $status,
                    'source_id' => $sources[$i % count($sources)] ?? null,
                    'acquired_at' => now()->subDays(3 * $i)->toDateString(),
                ]);
                $company->owner_id = $owners[$i % count($owners)];
                $company->created_by = $users['admin']->id;
                $company->save();
                $company->created_at = now()->subDays(2 * $i);
                $company->save();
                $company->tags()->sync([$tag->id]);

                $contact = new Contact([
                    'civility' => null,
                    'first_name' => $first,
                    'last_name' => $last,
                    'job_title' => $job,
                    'email' => Str::slug($first).'.'.Str::slug($last)."@{$slug}.test",
                    'audience_type' => 'b2b',
                    'legal_basis' => 'interet_legitime',
                    'collected_at' => now()->subDays(3 * $i)->toDateString(),
                    'collection_note' => 'Donnée fictive de démonstration',
                ]);
                $contact->company_id = $company->id;
                $contact->owner_id = $company->owner_id;
                $contact->is_primary = true;
                $contact->save();

                $activities->record($company, 'systeme', 'Fiche créée (données de démonstration).', [], $users['admin']->id);
                if ($i % 3 === 0) {
                    $activities->record($company, 'appel', "Premier appel avec {$first} {$last} : intéressé(e) par une présentation, rappeler la semaine prochaine.", [], $company->owner_id ?? $users['manager']->id);
                }
            }

            // Opportunités et tâches de démonstration, réparties sur les étapes.
            if (! Opportunity::exists()) {
                $steps = ['Nouveau', 'À contacter', 'Contact effectué', 'Intéressé', 'Rendez-vous', 'Proposition envoyée', 'Relance', 'Gagné', 'Perdu', 'À recontacter'];
                $actions = ['Appeler pour qualifier le besoin', 'Envoyer la plaquette', 'Confirmer le rendez-vous', 'Relancer sur la proposition', 'Préparer le devis'];
                $priorities = ['normale', 'haute', 'normale', 'urgente', 'basse'];
                foreach (Company::with(['sector.pipeline.stages', 'contacts'])->get() as $i => $company) {
                    $pipeline = $company->sector?->pipeline;
                    if (! $pipeline) {
                        continue;
                    }
                    $stage = $pipeline->stages->firstWhere('name', $steps[$i % count($steps)]) ?? $pipeline->stages->first();
                    $opportunity = new Opportunity([
                        'title' => 'Contrat de prestation — '.$company->name,
                        'contact_id' => $company->contacts->first()?->id,
                        'priority' => $priorities[$i % count($priorities)],
                        'amount' => 1500 + ($i * 750) % 12000,
                        'next_action' => $stage->isClosing() ? null : $actions[$i % count($actions)],
                        'follow_up_at' => $stage->isClosing() ? null : today()->addDays(($i % 9) - 3)->toDateString(),
                        'lost_reason' => $stage->type === 'lost' ? 'Budget reporté à l\'an prochain' : null,
                    ]);
                    $opportunity->company_id = $company->id;
                    $opportunity->owner_id = $company->owner_id;
                    $pipelines->create($opportunity, $pipeline, $stage, $users['admin']);

                    if (! $stage->isClosing()) {
                        $task = new Task([
                            'title' => $actions[$i % count($actions)],
                            'type' => $i % 2 ? 'appel' : 'relance',
                            'priority' => $opportunity->priority,
                            'due_at' => now()->startOfDay()->addDays(($i % 7) - 2)->setTime(9 + $i % 8, 0),
                        ]);
                        $task->company_id = $company->id;
                        $task->opportunity_id = $opportunity->id;
                        $task->assignee_id = $company->owner_id ?? $users['manager']->id;
                        $task->created_by = $users['admin']->id;
                        $task->save();
                    }
                }
            }
        });

        $this->seedEmailing();

        $this->newLine();
        $this->info('Données de démonstration prêtes. Comptes (mot de passe : '.self::PASSWORD.') :');
        $this->table(['Rôle', 'E-mail'], [
            ['Administrateur', 'admin@demo.test'],
            ['Responsable commercial', 'responsable@demo.test'],
            ['Commercial', 'commercial@demo.test'],
            ['Commercial (hors équipe)', 'commercial2@demo.test'],
            ['Conformité / DPO', 'conformite@demo.test'],
            ['Marketing', 'marketing@demo.test'],
        ]);

        return self::SUCCESS;
    }

    private function user(string $email, string $name, string $role, ?int $teamId): User
    {
        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => self::PASSWORD,
            'team_id' => $teamId,
            'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    /** Marketing de démonstration : compte « journal local » (aucun envoi réel). */
    private function seedEmailing(): void
    {
        if (MailAccount::exists()) {
            return;
        }
        $admin = User::where('email', 'admin@demo.test')->firstOrFail();
        $marketer = $this->user('marketing@demo.test', 'Inès Robert', 'marketing', null);

        $account = MailAccount::create([
            'name' => 'Démo — journal local', 'driver' => 'log', 'from_email' => 'contact@entreprise-ar.test',
            'from_name' => 'Entreprise AR', 'reply_to' => 'commercial@entreprise-ar.test', 'hourly_limit' => 500, 'daily_limit' => 2000, 'is_active' => true,
        ]);
        $account->forceFill(['last_tested_at' => now(), 'last_test_result' => 'ok'])->save();

        $prospection = Template::create(['name' => 'Prospection — présentation des services', 'category' => 'prospection']);
        $prospection->addVersion(['subject' => '{{prenom}}, une proposition pour {{entreprise}}', 'preheader' => 'Un échange de 15 minutes ?', 'html' => file_get_contents(resource_path('email-starters/prospection.html'))], $marketer->id);
        $newsletter = Template::create(['name' => 'Lettre mensuelle', 'category' => 'newsletter']);
        $newsletter->addVersion(['subject' => 'Les actualités d\'Entreprise AR — novembre', 'html' => file_get_contents(resource_path('email-starters/newsletter.html'))], $marketer->id);

        $immo = Segment::create(['name' => 'Agences immobilières (prospects)', 'filters' => ['lifecycle' => 'prospect', 'sector_id' => Sector::where('slug', 'agences-immobilieres')->value('id')]]);
        Segment::create(['name' => 'Syndics de copropriété', 'filters' => ['lifecycle' => 'prospect', 'sector_id' => Sector::where('slug', 'syndics-de-copropriete')->value('id')]]);

        $list = NewsletterList::create(['name' => 'Lettre mensuelle', 'description' => 'Actualités et conseils, un envoi par mois']);
        Contact::orderBy('id')->limit(8)->get()->each(fn (Contact $c) => Subscription::create([
            'list_id' => $list->id, 'contact_id' => $c->id, 'consent_source' => 'Démo — case cochée sur le formulaire du site',
            'consented_at' => now()->subDays(20), 'created_by' => $admin->id,
        ]));

        $sent = new Campaign(['name' => 'Présentation agences — octobre', 'type' => 'prospection', 'template_id' => $prospection->id, 'mail_account_id' => $account->id, 'segment_id' => $immo->id]);
        $sent->created_by = $marketer->id;
        $sent->last_test_at = now();
        $sent->save();
        app(CampaignService::class)->schedule($sent, $marketer, null);
        $sender = app(CampaignSender::class);
        while ($sender->processBatch($sent) === 0) {
            // envoi complet de la campagne de démonstration (journal local)
        }

        $draft = new Campaign(['name' => 'Newsletter de novembre', 'type' => 'newsletter', 'template_id' => $newsletter->id, 'mail_account_id' => $account->id, 'list_id' => $list->id]);
        $draft->created_by = $marketer->id;
        $draft->save();
    }
}
