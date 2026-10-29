<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Import;
use App\Models\SocialAccount;
use App\Models\SocialConversation;
use App\Models\SocialInteraction;
use App\Models\SocialMessage;
use App\Models\SocialOpposition;
use App\Models\SocialProfile;
use App\Models\SocialTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\Social\SocialProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sales = $this->userWithRole('commercial');
        $this->company = new Company(['name' => 'Agence du Parc', 'status' => 'nouveau', 'city' => 'Lyon']);
        $this->company->owner_id = $this->sales->id;
        $this->company->save();
    }

    private function contact(): Contact
    {
        $contact = new Contact(['first_name' => 'Claire', 'last_name' => 'Martin', 'job_title' => 'Gérante', 'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime']);
        $contact->company_id = $this->company->id;
        $contact->owner_id = $this->sales->id;
        $contact->save();

        return $contact;
    }

    private function addProfile(string $url = 'https://fr.linkedin.com/in/Claire-Martin/?utm_source=share', string $platform = 'linkedin'): SocialProfile
    {
        $this->actingAs($this->sales)->post(route('pm.social.store', $this->company), ['platform' => $platform, 'url' => $url])->assertRedirect();

        return SocialProfile::latest('id')->firstOrFail();
    }

    public function test_profile_urls_are_validated_and_normalized(): void
    {
        $social = app(SocialProfileService::class);
        $this->assertSame('https://www.linkedin.com/in/claire-martin', $social->normalizeUrl('linkedin', 'fr.linkedin.com/in/Claire-Martin/?trk=abc#x'));
        $this->assertSame('https://www.facebook.com/profile.php?id=100012345678', $social->normalizeUrl('facebook', 'https://m.facebook.com/profile.php?id=100012345678&ref=x'));
        $this->assertSame('https://x.com/agenceparc', $social->normalizeUrl('x', 'https://twitter.com/AgenceParc'));
        $this->assertNull($social->normalizeUrl('linkedin', 'https://www.facebook.com/agence'), 'adresse d\'un autre réseau');
        $this->assertNull($social->normalizeUrl('linkedin', 'javascript:alert(1)'));
        $this->assertNull($social->normalizeUrl('linkedin', 'https://linkedin.com.evil.test/in/x'));
        $this->assertNull($social->normalizeUrl('linkedin', 'https://www.linkedin.com/'), 'profil manquant');
        $this->assertNull($social->normalizeUrl('autre', 'https://www.linkedin.com/in/x'), 'réseau connu déclaré comme « autre »');

        $this->actingAs($this->sales)->post(route('pm.social.store', $this->company), ['platform' => 'linkedin', 'url' => 'https://exemple.fr/claire'])
            ->assertSessionHasErrors('url');
        $this->assertSame(0, SocialProfile::count());
    }

    public function test_profile_is_added_once_and_logged_in_history(): void
    {
        $profile = $this->addProfile();
        $this->assertSame('https://www.linkedin.com/in/claire-martin', $profile->url);
        $this->assertSame('a_contacter', $profile->status);

        $this->addProfile('https://www.linkedin.com/in/claire-martin');
        $this->assertSame(1, SocialProfile::count(), 'même profil : pas de doublon');
        $this->assertTrue($this->company->activities()->where('type', 'reseau_social')->exists());
    }

    public function test_interaction_updates_status_and_creates_follow_up_task(): void
    {
        $profile = $this->addProfile();
        $this->actingAs($this->sales)->post(route('pm.social.interaction', $profile), [
            'type' => 'invitation_envoyee', 'body' => 'Note envoyée', 'next_action_at' => now()->addDays(3)->toDateString(), 'create_task' => '1',
        ])->assertRedirect(route('pm.social.show', $profile))->assertSessionHas('success');

        $profile->refresh();
        $this->assertSame('invitation_envoyee', $profile->status);
        $this->assertNotNull($profile->last_interaction_at);
        $task = Task::where('company_id', $this->company->id)->firstOrFail();
        $this->assertSame('reseau_social', $task->type);
        $this->assertSame($this->sales->id, $task->assignee_id);
        $this->assertSame(now()->addDays(3)->toDateString(), $task->due_at->toDateString());

        // Le statut « ne plus contacter » ne peut pas être posé par une simple saisie.
        $this->actingAs($this->sales)->post(route('pm.social.interaction', $profile), ['type' => 'autre', 'status' => 'ne_plus_contacter'])->assertSessionHasErrors('status');
    }

    public function test_opposition_is_durable_and_blocks_reintroduction(): void
    {
        $profile = $this->addProfile();
        $this->actingAs($this->sales)->post(route('pm.social.interaction', $profile), ['type' => 'message_envoye', 'next_action_at' => now()->addDay()->toDateString(), 'create_task' => '1']);
        $this->actingAs($this->sales)->post(route('pm.social.oppose', $profile), ['note' => 'Demande par message', 'confirm' => '1'])->assertSessionHas('success');

        $profile->refresh();
        $this->assertTrue($profile->isOpposed());
        $this->assertNull($profile->next_action_at);
        $this->assertSame(1, SocialOpposition::count());
        $this->assertSame('annule', Task::where('company_id', $this->company->id)->value('status'), 'relances sociales annulées');
        $this->assertFalse(str_contains(SocialOpposition::first()->fingerprint, 'linkedin'), 'empreinte, pas l\'adresse en clair');

        // Plus de sollicitation possible, ni de levée par changement de statut.
        $this->actingAs($this->sales)->post(route('pm.social.interaction', $profile), ['type' => 'message_envoye'])->assertSessionHasErrors('type');
        $this->actingAs($this->sales)->put(route('pm.social.update', $profile), ['status' => 'a_contacter'])->assertSessionHasErrors('status');
        $this->assertTrue($profile->fresh()->isOpposed());

        // Retiré puis ressaisi (autre forme d'adresse) : refusé.
        $this->actingAs($this->sales)->delete(route('pm.social.destroy', $profile))->assertRedirect();
        $this->actingAs($this->sales)->post(route('pm.social.store', $this->company), ['platform' => 'linkedin', 'url' => 'linkedin.com/in/CLAIRE-MARTIN'])->assertSessionHasErrors('url');

        // Ni par la fiche contact…
        $contact = $this->contact();
        $this->actingAs($this->sales)->put(route('pm.contacts.update', $contact), [
            'company_id' => $this->company->id, 'first_name' => 'Claire', 'last_name' => 'Martin', 'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime',
            'linkedin_url' => 'https://www.linkedin.com/in/claire-martin',
        ])->assertSessionHasErrors('linkedin_url');

        // … ni par un import.
        Storage::fake('local');
        $csv = "Raison sociale;Prénom;Nom;LinkedIn\nAgence Nouvelle;Claire;Martin;https://www.linkedin.com/in/claire-martin/\n";
        $this->actingAs($this->sales)->post(route('pm.imports.store'), ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)]);
        $import = Import::latest('id')->firstOrFail();
        $this->actingAs($this->sales)->post(route('pm.imports.start', $import), ['mapping' => $import->mapping, 'duplicate_strategy' => 'skip', 'default_audience_type' => 'b2b', 'default_legal_basis' => 'interet_legitime', 'confirm_information' => '1']);
        $this->assertSame(0, SocialProfile::count());
        $this->assertNull(Contact::where('last_name', 'Martin')->whereHas('company', fn ($q) => $q->where('name', 'Agence Nouvelle'))->value('linkedin_url'));
        $this->assertStringContainsString('ne plus être contactée', json_encode($import->rows()->first()->messages, JSON_UNESCAPED_UNICODE));
    }

    public function test_import_and_contact_form_create_profiles(): void
    {
        Storage::fake('local');
        $csv = "Raison sociale;Page Facebook;Prénom;Nom;LinkedIn\nGarage Central;https://www.facebook.com/garagecentral;Luc;Blanc;https://www.linkedin.com/in/luc-blanc\n";
        $this->actingAs($this->sales)->post(route('pm.imports.store'), ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)]);
        $import = Import::latest('id')->firstOrFail();
        $this->assertSame(1, $import->mapping['company_facebook'] ?? null, 'colonne reconnue automatiquement');
        $this->actingAs($this->sales)->post(route('pm.imports.start', $import), ['mapping' => $import->mapping, 'duplicate_strategy' => 'skip', 'default_audience_type' => 'b2b', 'default_legal_basis' => 'interet_legitime', 'confirm_information' => '1']);

        $company = Company::where('name', 'Garage Central')->firstOrFail();
        $this->assertEqualsCanonicalizing(['facebook', 'linkedin'], $company->socialProfiles()->pluck('platform')->all());
        $this->assertNotNull($company->socialProfiles()->where('platform', 'linkedin')->value('contact_id'));

        $contact = $this->contact();
        $this->actingAs($this->sales)->put(route('pm.contacts.update', $contact), [
            'company_id' => $this->company->id, 'first_name' => 'Claire', 'last_name' => 'Martin', 'audience_type' => 'b2b', 'legal_basis' => 'interet_legitime',
            'linkedin_url' => 'https://www.linkedin.com/in/claire-m',
        ])->assertRedirect();
        $this->assertSame($contact->id, $this->company->socialProfiles()->value('contact_id'));
    }

    public function test_templates_render_variables_for_copy(): void
    {
        $contact = $this->contact();
        $manager = $this->userWithRole('responsable_commercial');
        $this->actingAs($manager)->post(route('pm.social.templates.store'), ['name' => 'Invitation', 'kind' => 'invitation', 'platform' => 'linkedin', 'body' => 'Bonjour {{prenom}}, je suis {{inconnue}}', 'is_active' => '1'])
            ->assertSessionHasErrors('body');
        $this->actingAs($manager)->post(route('pm.social.templates.store'), ['name' => 'Invitation', 'kind' => 'invitation', 'platform' => 'linkedin', 'body' => 'Bonjour {{prenom}}, j\'accompagne les agences de {{ville}} comme {{entreprise}}. {{expediteur}}', 'is_active' => '1'])
            ->assertRedirect();
        $this->actingAs($this->sales)->post(route('pm.social.templates.store'), ['name' => 'X', 'kind' => 'message', 'body' => 'x'])->assertForbidden();

        $this->actingAs($this->sales)->post(route('pm.social.store', $this->company), ['platform' => 'linkedin', 'url' => 'https://www.linkedin.com/in/claire', 'contact_id' => $contact->id]);
        $profile = SocialProfile::firstOrFail();
        $this->actingAs($this->sales)->get(route('pm.social.show', $profile))->assertOk()
            ->assertSee('Bonjour Claire, j&#039;accompagne les agences de Lyon comme Agence du Parc. '.$this->sales->name, false);
        $this->assertSame(1, SocialTemplate::count());
    }

    public function test_access_follows_company_scope_and_permissions(): void
    {
        $profile = $this->addProfile();
        $other = $this->userWithRole('commercial');
        $this->actingAs($other)->get(route('pm.social.show', $profile))->assertForbidden();
        $this->actingAs($other)->post(route('pm.social.interaction', $profile), ['type' => 'autre'])->assertForbidden();
        $this->actingAs($other)->get(route('pm.social.index'))->assertOk()->assertDontSee('Agence du Parc');
        $this->actingAs($this->sales)->get(route('pm.social.index'))->assertOk()->assertSee('Agence du Parc');

        $reader = $this->userWithRole('lecture_seule');
        $this->actingAs($reader)->get(route('pm.social.index'))->assertForbidden();
        $this->actingAs($reader)->get(route('pm.social.inbox.index'))->assertForbidden();
        $this->actingAs($this->sales)->get(route('pm.settings.social.index'))->assertForbidden();
    }

    public function test_social_reply_triggers_automation(): void
    {
        $marketer = $this->userWithRole('marketing');
        $this->actingAs($marketer)->post(route('pm.automations.store'), ['preset' => 'reponse_sociale']);
        $automation = Automation::latest('id')->firstOrFail();
        $this->actingAs($marketer)->post(route('pm.automations.activate', $automation))->assertSessionHas('success');

        $profile = $this->addProfile();
        $this->actingAs($this->sales)->post(route('pm.social.interaction', $profile), ['type' => 'message_recu', 'body' => 'Avec plaisir, appelez-moi.']);

        $this->assertSame('en_discussion', $profile->fresh()->status);
        $this->assertSame('Répondre au message reçu sur le réseau social', Task::where('company_id', $this->company->id)->value('title'));
    }

    private function metaAccount(): SocialAccount
    {
        $admin = $this->userWithRole('administrateur');
        $this->actingAs($admin)->post(route('pm.settings.social.store'), [
            'name' => 'Page AR', 'app_id' => '1234567890', 'app_secret' => 'secret-de-test', 'page_id' => '111222333',
            'page_access_token' => 'EAAB-jeton-page', 'verify_token' => 'jeton-verif-0123456789', 'is_active' => '1',
        ])->assertRedirect();

        return SocialAccount::firstOrFail();
    }

    private function postEvent(SocialAccount $account, array $payload, ?string $secret = 'secret-de-test')
    {
        $body = json_encode($payload);

        return $this->call('POST', route('webhooks.meta', $account->webhook_token), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    }

    private function inbound(string $mid, string $text, ?int $ms = null): array
    {
        return ['object' => 'page', 'entry' => [['id' => '111222333', 'time' => 1, 'messaging' => [[
            'sender' => ['id' => '987654321'], 'recipient' => ['id' => '111222333'], 'timestamp' => $ms ?? now()->getTimestampMs(),
            'message' => ['mid' => $mid, 'text' => $text],
        ]]]]];
    }

    public function test_meta_secrets_are_encrypted_and_never_displayed(): void
    {
        $account = $this->metaAccount();
        $raw = \DB::table('pm_social_accounts')->value('settings');
        $this->assertStringNotContainsString('EAAB-jeton-page', $raw);
        $this->assertStringNotContainsString('secret-de-test', $raw);
        $this->assertArrayNotHasKey('settings', $account->toArray());

        $admin = User::role('administrateur')->first();
        $this->actingAs($admin)->get(route('pm.settings.social.edit', $account))->assertOk()
            ->assertDontSee('EAAB-jeton-page')->assertDontSee('secret-de-test')->assertSee($account->webhook_token);

        // Champ vide : la valeur enregistrée est conservée.
        $this->actingAs($admin)->put(route('pm.settings.social.update', $account), ['name' => 'Page AR', 'app_id' => '1234567890', 'page_id' => '111222333', 'is_active' => '1'])->assertRedirect();
        $this->assertSame('EAAB-jeton-page', $account->fresh()->setting('page_access_token'));
    }

    public function test_meta_webhook_verification_and_signature(): void
    {
        $account = $this->metaAccount();
        $this->get(route('webhooks.meta.verify', $account->webhook_token).'?hub.mode=subscribe&hub.verify_token=jeton-verif-0123456789&hub.challenge=1158201444')
            ->assertOk()->assertSeeText('1158201444');
        $this->get(route('webhooks.meta.verify', $account->webhook_token).'?hub.mode=subscribe&hub.verify_token=mauvais&hub.challenge=1')->assertForbidden();
        $this->get(route('webhooks.meta.verify', Str::random(48)).'?hub.mode=subscribe&hub.verify_token=jeton-verif-0123456789&hub.challenge=1')->assertForbidden();

        $this->postEvent($account, $this->inbound('m_1', 'Bonjour'), 'faux-secret')->assertForbidden();
        $this->assertSame(0, SocialMessage::count());
    }

    public function test_inbound_messages_are_idempotent_and_linked_replies_feed_history(): void
    {
        $account = $this->metaAccount();
        $this->postEvent($account, $this->inbound('m_1', 'Bonjour, vous faites des devis ?'))->assertOk()->assertJson(['recus' => 1]);
        $this->postEvent($account, $this->inbound('m_1', 'Bonjour, vous faites des devis ?'))->assertOk()->assertJson(['doublons' => 1]);
        $this->postEvent($account, ['object' => 'page', 'entry' => [['messaging' => [['sender' => ['id' => '987654321'], 'recipient' => ['id' => '111222333'], 'read' => ['watermark' => 1]]]]]])->assertJson(['ignores' => 1]);

        $conversation = SocialConversation::firstOrFail();
        $this->assertSame(1, SocialMessage::count());
        $this->assertTrue($conversation->unread);
        $this->assertTrue($conversation->canReply());

        // Rattachement manuel au prospect et à son profil Facebook.
        $profile = $this->addProfile('https://www.facebook.com/agenceduparc', 'facebook');
        $this->actingAs($this->sales)->get(route('pm.social.inbox.show', $conversation))->assertOk();
        $this->actingAs($this->sales)->put(route('pm.social.inbox.link', $conversation), ['company_id' => $this->company->id, 'profile_id' => $profile->id, 'participant_label' => 'Claire'])->assertSessionHas('success');

        $this->postEvent($account, $this->inbound('m_2', 'Je suis disponible jeudi.'))->assertOk();
        $interaction = SocialInteraction::where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame('message_recu', $interaction->type);
        $this->assertSame('meta', $interaction->origin);
        $this->assertSame('en_discussion', $profile->fresh()->status);

        // Une conversation rattachée suit le périmètre de la fiche.
        $this->actingAs($this->userWithRole('commercial'))->get(route('pm.social.inbox.show', $conversation))->assertForbidden();
    }

    public function test_reply_only_inside_window_and_never_claimed_as_read(): void
    {
        $account = $this->metaAccount();
        Http::fake(['graph.facebook.com/*' => Http::response(['recipient_id' => '987654321', 'message_id' => 'm_out_1'])]);

        $this->postEvent($account, $this->inbound('m_old', 'Ancien message', now()->subHours(30)->getTimestampMs()))->assertOk();
        $conversation = SocialConversation::firstOrFail();
        $this->assertFalse($conversation->canReply());
        $this->actingAs($this->sales)->post(route('pm.social.inbox.reply', $conversation), ['body' => 'Bonjour'])->assertSessionHasErrors('body');
        Http::assertNothingSent();

        $this->postEvent($account, $this->inbound('m_new', 'Toujours intéressé'))->assertOk();
        $this->actingAs($this->sales)->post(route('pm.social.inbox.reply', $conversation), ['body' => 'Merci, je vous rappelle.'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Accepté par Meta') || str_contains($m, 'accepté par Meta'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v25.0/111222333/messages')
            && $request['messaging_type'] === 'RESPONSE'
            && $request['recipient']['id'] === '987654321'
            && $request->hasHeader('Authorization', 'Bearer EAAB-jeton-page'));
        $out = SocialMessage::where('direction', 'sortant')->firstOrFail();
        $this->assertSame('accepte', $out->status);
        $this->assertSame('m_out_1', $out->external_id);
    }

    public function test_meta_errors_are_reported_without_leaking_the_token(): void
    {
        $account = $this->metaAccount();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.', 'code' => 190]], 400)]);
        $admin = User::role('administrateur')->first();
        $this->actingAs($admin)->post(route('pm.settings.social.test', $account))->assertSessionHasErrors('account');
        $result = $account->fresh()->last_test_result;
        $this->assertStringContainsString('code 190', $result);
        $this->assertStringNotContainsString('EAAB-jeton-page', $result);
    }

    public function test_quick_buttons_log_and_schedule_next_reminder(): void
    {
        $profile = $this->addProfile();
        $this->actingAs($this->sales)->post(route('pm.social.quick', $profile), ['action' => 'invitation'])->assertSessionHas('success', fn ($m) => str_contains($m, 'rappel'));
        $profile->refresh();
        $this->assertSame('invitation_envoyee', $profile->status);
        $task = Task::where('company_id', $this->company->id)->where('status', 'a_faire')->firstOrFail();
        $this->assertFalse($task->due_at->isWeekend());

        $this->actingAs($this->sales)->post(route('pm.social.quick', $profile), ['action' => 'non']);
        $this->assertSame('pas_interesse', $profile->fresh()->status);
        $this->actingAs($this->sales)->post(route('pm.social.quick', $profile), ['action' => 'pirate'])->assertSessionHasErrors('action');
    }
}
