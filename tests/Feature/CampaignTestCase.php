<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\MailAccount;
use App\Models\Sector;
use App\Models\Segment;
use App\Models\Template;
use App\Models\User;
use App\Services\Mail\MailProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeMailProvider;
use Tests\TestCase;

abstract class CampaignTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $marketer;

    protected MailAccount $account;

    protected FakeMailProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marketer = $this->userWithRole('marketing');
        $this->account = MailAccount::create([
            'name' => 'Test', 'driver' => 'log', 'from_email' => 'contact@entreprise-ar.test', 'from_name' => 'Entreprise AR',
            'hourly_limit' => 1000, 'daily_limit' => 1000, 'is_active' => true,
        ]);
        $this->account->forceFill(['last_tested_at' => now(), 'last_test_result' => 'ok'])->save();
        $this->provider = new FakeMailProvider;
        app(MailProviderFactory::class)->fake($this->account, $this->provider);
    }

    protected function contact(string $email, array $attrs = [], array $companyAttrs = []): Contact
    {
        $company = new Company($companyAttrs + ['name' => 'Société '.$email, 'status' => 'nouveau', 'sector_id' => Sector::where('slug', 'agences-immobilieres')->value('id'), 'city' => 'Lyon']);
        $company->save();
        $contact = new Contact($attrs + ['first_name' => 'Jean', 'last_name' => 'Test', 'email' => $email, 'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime']);
        $contact->company_id = $company->id;
        $contact->save();

        return $contact;
    }

    protected function template(string $category = 'prospection', string $html = '<p>Bonjour {{prenom}} de {{entreprise}}</p>'): Template
    {
        $template = Template::create(['name' => 'Modèle '.$category, 'category' => $category]);
        $template->addVersion(['subject' => 'Bonjour {{prenom}}', 'html' => $html], $this->marketer->id);

        return $template;
    }

    protected function campaign(string $type = 'prospection', array $attrs = []): Campaign
    {
        $campaign = new Campaign($attrs + [
            'name' => 'Campagne '.$type,
            'type' => $type,
            'template_id' => $this->template($type === 'operationnel' ? 'operationnel' : $type)->id,
            'mail_account_id' => $this->account->id,
            'segment_id' => in_array($type, ['prospection', 'operationnel'], true) ? Segment::create(['name' => 'Tous', 'filters' => ['lifecycle' => 'all']])->id : null,
        ]);
        $campaign->created_by = $this->marketer->id;
        $campaign->last_test_at = now();
        $campaign->save();

        return $campaign;
    }

    protected function launch(Campaign $campaign, array $extra = []): TestResponse
    {
        return $this->actingAs($this->marketer)->post(route('pm.campaigns.schedule', $campaign), $extra + ['when' => 'now', 'confirm_compliance' => '1']);
    }
}
