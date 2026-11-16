<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\MailAccount;
use App\Models\Sector;
use App\Services\Mail\TemplateRenderer;
use App\Support\Playbooks;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaybookTest extends TestCase
{
    use RefreshDatabase;

    public function test_hundred_complete_templates_across_ten_sectors(): void
    {
        $all = Playbooks::all();
        $this->assertCount(10, $all);
        $count = 0;
        foreach ($all as $p) {
            foreach (['name', 'icon', 'pitch', 'decider', 'need', 'offer', 'gain', 'deal', 'questions', 'objections', 'sectors'] as $key) {
                $this->assertNotEmpty($p[$key], $p['slug'].' : '.$key);
            }
            $this->assertCount(6, $p['questions'], $p['slug']);
            $this->assertFileExists(resource_path('icons/'.$p['icon'].'.svg'));
            $this->assertCount(10, $p['emails'], $p['slug']);
            foreach ($p['emails'] as $email) {
                $count++;
                $this->assertNotEmpty($email['subject']);
                $this->assertNotEmpty($email['why']);
                $this->assertLessThanOrEqual(190, mb_strlen($email['subject']));
                $this->assertStringStartsWith('Bonjour {{prenom}},', $email['body']);
                $this->assertStringNotContainsString('{{signature}}', $email['body']);
                // Les e-mails envoyés à plusieurs ne doivent rien contenir à compléter à la main.
                if ($email['mass']) {
                    $this->assertFalse(Playbooks::hasPlaceholder($email['subject'], $email['body']), $email['key']);
                }
                // Seules les variables connues sont utilisées.
                preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $email['subject'].$email['body'], $m);
                $this->assertEmpty(array_diff($m[1], ['prenom', 'entreprise', 'ville']), $email['key']);
            }
        }
        $this->assertSame(100, $count);
    }

    public function test_every_playbook_maps_to_a_seeded_sector(): void
    {
        $this->seed(SectorSeeder::class);
        foreach (Playbooks::all() as $p) {
            foreach ($p['sectors'] as $name) {
                $sector = Sector::where('slug', Str::slug($name))->first();
                $this->assertNotNull($sector, $name);
                $this->assertSame($p['slug'], Playbooks::forSector($sector)['slug']);
            }
        }
    }

    public function test_pages_render_for_every_sector(): void
    {
        $user = $this->userWithRole('commercial', ['simple_mode' => true]);
        $this->actingAs($user)->get(route('pm.playbooks.index'))->assertOk()->assertSee('Concessionnaires automobiles')->assertSee('Secteurs et modèles');
        foreach (array_keys(Playbooks::all()) as $slug) {
            $this->actingAs($user)->get(route('pm.playbooks.show', $slug))->assertOk()->assertSee('Les 10 e-mails');
        }
        $this->actingAs($user)->get(route('pm.playbooks.show', 'inconnu'))->assertNotFound();
    }

    public function test_empty_first_name_leaves_no_orphan_space(): void
    {
        $this->assertSame('Bonjour,', TemplateRenderer::fill('Bonjour {{prenom}},', ['prenom' => null]));
        $this->assertSame('Bonjour Léa,', TemplateRenderer::fill('Bonjour {{prenom}},', ['prenom' => 'Léa']));
        $this->assertSame('chez Garage, à Lyon', TemplateRenderer::fill('chez {{entreprise}}, à {{ville}}', ['entreprise' => 'Garage', 'ville' => 'Lyon']));
    }

    public function test_individual_email_is_prefilled_and_placeholders_block_sending(): void
    {
        $this->seed(SectorSeeder::class);
        $user = $this->userWithRole('commercial');
        $company = $this->companyFor($user, ['name' => 'Garage du Centre', 'city' => 'Lyon', 'sector_id' => Sector::where('slug', 'concessionnaires-automobiles')->value('id')]);
        $contact = new Contact(['first_name' => 'Paul', 'last_name' => 'Morel', 'email' => 'paul@garage.fr', 'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime']);
        $contact->company_id = $company->id;
        $contact->owner_id = $user->id;
        $contact->save();

        $account = MailAccount::create(['name' => 'Test', 'driver' => 'log', 'from_email' => 'contact@entreprise-ar.test', 'from_name' => 'Entreprise AR', 'hourly_limit' => 100, 'daily_limit' => 100, 'is_active' => true]);
        $account->forceFill(['last_tested_at' => now(), 'last_test_result' => 'ok'])->save();
        $this->actingAs($user)->get(route('pm.contacts.email.create', ['contact' => $contact, 'modele' => 'concessionnaires-1']))
            ->assertOk()->assertSee('Bonjour Paul,')->assertSee('Garage du Centre')->assertSee('Modèles « Concessionnaires automobiles »', false);

        $this->actingAs($user)->post(route('pm.contacts.email.store', $contact), [
            'mail_account_id' => $account->id,
            'subject' => 'Notre rendez-vous',
            'body' => "Bonjour Paul,\n\nLe [à compléter : date].",
        ])->assertSessionHasErrors('body');
    }

    public function test_simple_wizard_loads_a_sector_model(): void
    {
        $user = $this->userWithRole('marketing', ['simple_mode' => true]);
        $this->actingAs($user)->get(route('pm.simple-email.create', ['modele' => 'syndics-1']))->assertOk();
        $this->actingAs($user)->withSession(['simple_email.filters' => ['lifecycle' => 'prospect']])
            ->get(route('pm.simple-email.message', ['modele' => 'syndics-1']))
            ->assertOk()->assertSee('Demandes de bornes dans vos copropriétés');
    }
}
