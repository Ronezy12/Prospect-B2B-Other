<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\MailAccount;
use App\Models\NewsletterList;
use App\Models\Segment;
use App\Models\Subscription;
use App\Models\Template;
use App\Services\Mail\BrevoProvider;
use App\Services\Mail\OutgoingMessage;
use App\Services\Mail\TemplateRenderer;
use App\Services\SuppressionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class EmailFeaturesTest extends CampaignTestCase
{
    private function sentRecipient(): CampaignRecipient
    {
        $this->contact('cible@pro.test');
        $this->launch($this->campaign());

        return CampaignRecipient::where('email', 'cible@pro.test')->firstOrFail();
    }

    public function test_unsubscribe_link_records_a_lasting_opposition(): void
    {
        $recipient = $this->sentRecipient();
        $url = URL::signedRoute('unsubscribe.show', ['recipient' => $recipient->id]);

        $this->get($url)->assertOk()->assertSee('Confirmer la désinscription');
        $this->post($url)->assertOk()->assertSee('Désinscription enregistrée');

        $this->assertTrue(app(SuppressionService::class)->isSuppressed('cible@pro.test', 'prospection'));
        $this->assertDatabaseHas('pm_email_events', ['recipient_id' => $recipient->id, 'type' => 'desabonnement']);

        // Une nouvelle campagne n'atteint plus cette adresse.
        $this->provider->sent = [];
        $this->launch($this->campaign());
        $this->assertSame([], $this->provider->sent);
    }

    public function test_unsubscribe_requires_a_valid_signature_and_supports_one_click(): void
    {
        $recipient = $this->sentRecipient();
        $this->get(route('unsubscribe.show', ['recipient' => $recipient->id]))->assertForbidden();
        $this->get(URL::signedRoute('unsubscribe.show', ['recipient' => $recipient->id]).'x')->assertForbidden();

        $this->post(URL::signedRoute('unsubscribe.store', ['recipient' => $recipient->id]), ['List-Unsubscribe' => 'One-Click'])
            ->assertOk()->assertContent('');
        $this->assertTrue(app(SuppressionService::class)->isSuppressed('cible@pro.test', 'prospection'));
    }

    public function test_newsletter_unsubscribe_updates_subscription(): void
    {
        $list = NewsletterList::create(['name' => 'Lettre']);
        $contact = $this->contact('lecteur@pro.test');
        Subscription::create(['list_id' => $list->id, 'contact_id' => $contact->id, 'consent_source' => 'Salon', 'consented_at' => now()]);
        $this->launch($this->campaign('newsletter', ['list_id' => $list->id]));
        $recipient = CampaignRecipient::firstOrFail();

        $this->post(URL::signedRoute('unsubscribe.store', ['recipient' => $recipient->id]));

        $this->assertSame('desabonne', Subscription::first()->status);
        $this->assertTrue(app(SuppressionService::class)->isSuppressed('lecteur@pro.test', 'newsletter'));
        $this->assertFalse(app(SuppressionService::class)->isSuppressed('lecteur@pro.test', 'prospection'), 'le périmètre reste limité aux newsletters');
    }

    public function test_subscription_requires_consent_and_refuses_opposed_addresses(): void
    {
        $list = NewsletterList::create(['name' => 'Lettre']);
        $this->contact('nouveau@pro.test');
        $this->contact('refus@pro.test');
        app(SuppressionService::class)->suppress('refus@pro.test', 'newsletter', 'desabonnement', 'test');

        $this->actingAs($this->marketer)->post(route('pm.newsletters.subscribe', $list), ['email' => 'nouveau@pro.test'])->assertSessionHasErrors(['consent_source', 'consented_at']);
        $this->actingAs($this->marketer)->post(route('pm.newsletters.subscribe', $list), ['email' => 'refus@pro.test', 'consent_source' => 'X', 'consented_at' => now()->subDay()->format('Y-m-d H:i')])->assertSessionHasErrors('email');
        $this->actingAs($this->marketer)->post(route('pm.newsletters.subscribe', $list), ['email' => 'nouveau@pro.test', 'consent_source' => 'Formulaire du site', 'consented_at' => now()->subDay()->format('Y-m-d H:i')])->assertSessionHas('success');

        $this->assertSame(1, Subscription::where('status', 'abonne')->count());
        $this->actingAs($this->marketer)->get(route('pm.newsletters.show', $list))->assertOk()->assertSee('Formulaire du site');
    }

    public function test_templates_are_versioned_and_sanitised(): void
    {
        $this->actingAs($this->marketer)->post(route('pm.templates.store'), ['name' => 'Agences', 'category' => 'prospection', 'starter' => 'prospection'])->assertRedirect();
        $template = Template::where('name', 'Agences')->firstOrFail();
        $this->assertSame(1, $template->current_version);

        $payload = ['name' => 'Agences', 'subject' => 'Objet v2', 'html' => '<p onclick="vol()">Texte</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>'];
        $this->actingAs($this->marketer)->put(route('pm.templates.update', $template), $payload)->assertRedirect();
        $this->actingAs($this->marketer)->put(route('pm.templates.update', $template), $payload)->assertSessionHas('success', 'Aucune modification à enregistrer.');

        $template->refresh();
        $this->assertSame(2, $template->current_version);
        $html = $template->latestVersion->html;
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame('Objet v2', $template->latestVersion->subject);
        $this->assertNotNull($template->versions()->where('version', 1)->first(), 'la version 1 est conservée');
    }

    public function test_template_pages_preview_isolation_and_permissions(): void
    {
        $template = $this->template();
        $this->actingAs($this->marketer)->get(route('pm.templates.show', $template))->assertOk();
        $this->actingAs($this->marketer)->get(route('pm.templates.edit', $template))->assertOk()
            ->assertHeader('Content-Security-Policy')->assertSee('grapes.min.js');

        $preview = $this->actingAs($this->marketer)->get(route('pm.templates.preview', $template));
        $preview->assertOk()->assertSee('Bonjour Claire de Agence Exemple');
        $this->assertStringContainsString('sandbox', $preview->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'none'", $preview->headers->get('Content-Security-Policy'));

        $this->actingAs($this->userWithRole('commercial'))->get(route('pm.templates.edit', $template))->assertForbidden();
        $this->actingAs($this->userWithRole('responsable_commercial'))->put(route('pm.templates.update', $template), ['name' => 'x', 'subject' => 'x', 'html' => 'x'])->assertForbidden();
    }

    public function test_test_email_is_prefixed_and_uses_sample_data(): void
    {
        $template = $this->template();
        $this->actingAs($this->marketer)->post(route('pm.templates.test', $template), ['mail_account_id' => $this->account->id, 'emails' => 'moi@entreprise-ar.test'])->assertSessionHas('success');

        $this->assertSame('[TEST] Bonjour Claire', $this->provider->sent[0]->subject);
        $this->actingAs($this->marketer)->post(route('pm.templates.test', $template), ['mail_account_id' => $this->account->id, 'emails' => 'a@x.fr,b@x.fr,c@x.fr,d@x.fr,e@x.fr,f@x.fr'])->assertSessionHasErrors('emails');
    }

    public function test_renderer_adds_footer_only_for_marketing_and_builds_text(): void
    {
        $renderer = new TemplateRenderer;
        $data = ['prenom' => 'Léa', 'lien_desinscription' => 'https://exemple.test/d/1'];

        $marketing = $renderer->render('Bonjour {{ prenom }}', '<p>Salut <a href="https://ar.test">site</a></p>', 'Aperçu', $data, true, 'Entreprise AR');
        $this->assertSame('Bonjour Léa', $marketing['subject']);
        $this->assertStringContainsString('https://exemple.test/d/1', $marketing['html']);
        $this->assertStringContainsString('site (https://ar.test)', $marketing['text']);

        $operational = $renderer->render('Info', '<p>RDV confirmé</p>', null, $data, false);
        $this->assertStringNotContainsString('désinscrire', $operational['html']);
    }

    public function test_mail_account_secrets_are_encrypted_and_never_displayed(): void
    {
        $admin = $this->userWithRole('administrateur');
        $this->actingAs($admin)->post(route('pm.settings.mail.store'), [
            'name' => 'OVH', 'driver' => 'smtp', 'from_email' => 'contact@ar.test', 'from_name' => 'AR',
            'hourly_limit' => 100, 'daily_limit' => 500, 'is_active' => '1',
            'host' => 'ssl0.ovh.net', 'port' => 587, 'encryption' => 'tls', 'username' => 'contact@ar.test', 'password' => 'S3cret-Pass!',
        ])->assertRedirect();

        $account = MailAccount::where('name', 'OVH')->firstOrFail();
        $raw = DB::table('pm_mail_accounts')->where('id', $account->id)->value('settings');
        $this->assertStringNotContainsString('S3cret-Pass!', $raw);
        $this->assertSame('S3cret-Pass!', $account->setting('password'));

        $this->actingAs($admin)->get(route('pm.settings.mail.edit', $account))->assertOk()->assertDontSee('S3cret-Pass!');
        $this->assertStringNotContainsString('S3cret-Pass!', $account->toJson());

        // Mot de passe laissé vide : conservé.
        $this->actingAs($admin)->put(route('pm.settings.mail.update', $account), [
            'name' => 'OVH', 'driver' => 'smtp', 'from_email' => 'contact@ar.test', 'from_name' => 'AR',
            'hourly_limit' => 100, 'daily_limit' => 500, 'is_active' => '1', 'host' => 'ssl0.ovh.net', 'port' => 587, 'encryption' => 'tls', 'username' => 'contact@ar.test', 'password' => '',
        ]);
        $this->assertSame('S3cret-Pass!', $account->fresh()->setting('password'));
        $this->assertStringNotContainsString('S3cret-Pass!', (string) AuditLog::latest('id')->first()?->toJson());

        $this->actingAs($this->marketer)->get(route('pm.settings.mail.index'))->assertForbidden();
    }

    public function test_account_test_marks_status(): void
    {
        $admin = $this->userWithRole('administrateur');
        $this->account->forceFill(['last_test_result' => null])->save();
        $this->actingAs($admin)->post(route('pm.settings.mail.test', $this->account), ['test_email' => 'moi@ar.test'])->assertSessionHas('success');
        $this->assertSame('ok', $this->account->fresh()->last_test_result);
    }

    public function test_brevo_provider_classifies_responses(): void
    {
        $account = new MailAccount(['driver' => 'brevo']);
        $account->settings = ['api_key' => 'xkeysib-test'];
        $message = new OutgoingMessage('a@b.test', 'A', 'from@ar.test', 'AR', null, 'Sujet', '<p>x</p>', 'x', 'cle');

        Http::fake([BrevoProvider::ENDPOINT => Http::sequence()
            ->push(['messageId' => '<abc@smtp-relay>'], 201)
            ->push(['message' => 'Too many requests'], 429)
            ->push(['message' => 'Invalid email'], 400)]);

        $provider = new BrevoProvider($account);
        $ok = $provider->send($message);
        $this->assertTrue($ok->isAccepted());
        $this->assertSame('<abc@smtp-relay>', $ok->messageId);
        $this->assertSame('temporary', $provider->send($message)->status);
        $this->assertSame('permanent', $provider->send($message)->status);
        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'xkeysib-test') && $request['subject'] === 'Sujet');

        $account->settings = [];
        $this->assertSame('permanent', (new BrevoProvider($account))->send($message)->status);
    }

    public function test_individual_email_from_contact_record(): void
    {
        $commercial = $this->userWithRole('commercial');
        $mine = $this->contact('client@pro.test');
        $mine->company->forceFill(['owner_id' => $commercial->id])->save();
        $mine->forceFill(['owner_id' => $commercial->id])->save();
        $other = $this->contact('autre@pro.test');

        $this->actingAs($commercial)->get(route('pm.contacts.email.create', $mine))->assertOk();
        $this->actingAs($commercial)->post(route('pm.contacts.email.store', $mine), ['mail_account_id' => $this->account->id, 'subject' => 'Suite à notre appel', 'body' => "Bonjour,\nVoici le devis."])
            ->assertRedirect(route('pm.companies.show', $mine->company_id));

        $this->assertSame(['client@pro.test'], $this->provider->recipients());
        $this->assertStringContainsString('Voici le devis.', $this->provider->sent[0]->html);
        $this->assertStringContainsString('Se désinscrire', $this->provider->sent[0]->html);
        $this->assertTrue($mine->company->activities()->where('type', 'email')->exists());
        $this->assertSame('individuel', Campaign::latest('id')->value('type'));

        $this->actingAs($commercial)->get(route('pm.contacts.email.create', $other))->assertForbidden();

        app(SuppressionService::class)->suppress('client@pro.test', 'all', 'opposition', 'test');
        $this->actingAs($commercial)->post(route('pm.contacts.email.store', $mine), ['mail_account_id' => $this->account->id, 'subject' => 'Relance', 'body' => 'x'])->assertSessionHasErrors('body');
        $this->assertCount(1, $this->provider->sent);
    }

    public function test_segment_estimate_and_pages(): void
    {
        $this->contact('a@pro.test');
        $this->contact('b@perso.test', ['audience_type' => 'b2c']);
        $this->actingAs($this->marketer)->post(route('pm.segments.store'), ['name' => 'Lyon', 'filters' => ['lifecycle' => 'prospect', 'city' => 'Lyon']])->assertRedirect();
        $segment = Segment::where('name', 'Lyon')->firstOrFail();

        $this->actingAs($this->marketer)->get(route('pm.segments.edit', $segment))->assertOk()
            ->assertSee('Contacts avec e-mail ciblés')->assertSee('Particulier sans consentement (1)');
        foreach ([route('pm.segments.index'), route('pm.newsletters.index'), route('pm.templates.index'), route('pm.templates.create')] as $url) {
            $this->actingAs($this->marketer)->get($url)->assertOk();
        }
    }
}
