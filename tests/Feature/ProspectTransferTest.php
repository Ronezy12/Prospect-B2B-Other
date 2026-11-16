<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Services\SuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProspectTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('prospection.import.disk'));
    }

    public function test_simple_create_form_saves_postal_address(): void
    {
        $user = $this->userWithRole('commercial', ['simple_mode' => true]);
        $this->actingAs($user)->get(route('pm.companies.create'))
            ->assertOk()->assertSee('Adresse postale')->assertSee('Code postal');

        $this->actingAs($user)->post(route('pm.companies.store'), [
            'name' => 'Agence des Lilas', 'status' => 'nouveau',
            'address' => '4 rue des Lilas', 'postal_code' => '33000', 'city' => 'Bordeaux',
            'contact' => ['first_name' => 'Léa', 'last_name' => 'Roux'],
        ])->assertRedirect();

        $company = Company::where('name', 'Agence des Lilas')->firstOrFail();
        $this->assertSame(['4 rue des Lilas', '33000', 'Bordeaux'], [$company->address, $company->postal_code, $company->city]);
        $this->actingAs($user)->get(route('pm.companies.index'))->assertSee('33000 Bordeaux');
    }

    public function test_transfer_page_and_template(): void
    {
        $user = $this->userWithRole('commercial', ['simple_mode' => true]);
        $this->actingAs($user)->get(route('pm.companies.index'))->assertSee('Importer / exporter');
        $this->actingAs($user)->get(route('pm.companies.transfer'))->assertOk()->assertSee('Importer un fichier');

        $csv = $this->actingAs($user)->get(route('pm.companies.template'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Entreprise;Adresse;"Code postal";Ville', $csv);
    }

    public function test_quick_import_starts_automatically_and_respects_opposition(): void
    {
        $user = $this->userWithRole('commercial', ['simple_mode' => true]);
        app(SuppressionService::class)->suppress('stop@exemple.fr', 'all', 'opposition', 'test');

        $content = "\xEF\xBB\xBFEntreprise;Adresse;Code postal;Ville;Prénom;Nom;Téléphone;E-mail\n"
            ."Agence Alpha;1 rue A;75001;Paris;Ana;Durand;0612345678;ana@alpha.fr\n"
            ."Agence Beta;2 rue B;69001;Lyon;Bob;Stop;0698765432;stop@exemple.fr\n";

        $this->actingAs($user)->post(route('pm.companies.import'), [
            'file' => UploadedFile::fake()->createWithContent('prospects.csv', $content),
            'duplicates' => 'fill_empty',
            'confirm' => '1',
        ])->assertRedirect(route('pm.companies.transfer'))->assertSessionHas('success');

        $alpha = Company::where('name', 'Agence Alpha')->firstOrFail();
        $this->assertSame(['1 rue A', '75001', 'Paris'], [$alpha->address, $alpha->postal_code, $alpha->city]);
        $this->assertTrue(Contact::where('email', 'ana@alpha.fr')->exists());
        $this->assertFalse(Contact::where('email_normalized', 'stop@exemple.fr')->exists());
    }

    public function test_quick_import_requires_confirmation_and_falls_back_to_mapping(): void
    {
        $user = $this->userWithRole('commercial', ['simple_mode' => true]);
        $this->actingAs($user)->post(route('pm.companies.import'), [
            'file' => UploadedFile::fake()->createWithContent('a.csv', "Entreprise\nX\n"), 'duplicates' => 'skip',
        ])->assertSessionHasErrors('confirm');

        $this->actingAs($user)->post(route('pm.companies.import'), [
            'file' => UploadedFile::fake()->createWithContent('b.csv', "Raison\nX\n"), 'duplicates' => 'skip', 'confirm' => '1',
        ])->assertRedirectContains('/correspondance');
    }

    public function test_user_without_rights_cannot_import(): void
    {
        $user = $this->userWithRole('lecture_seule', ['simple_mode' => true]);
        $this->actingAs($user)->post(route('pm.companies.import'), [
            'file' => UploadedFile::fake()->createWithContent('a.csv', "Entreprise\nX\n"), 'duplicates' => 'skip', 'confirm' => '1',
        ])->assertForbidden();
    }
}
