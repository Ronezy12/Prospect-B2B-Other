<?php

namespace Tests\Feature;

use App\Http\Controllers\TrackingController;
use App\Models\CampaignRecipient;
use App\Models\EmailEvent;
use App\Models\Inbox;
use App\Models\MailAccount;
use App\Models\Setting;
use App\Services\Mail\MailProviderFactory;
use App\Services\Replies\InboxReader;
use App\Services\Replies\IncomingMessage;
use App\Services\SuppressionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmailEventsTest extends CampaignTestCase
{
    private MailAccount $brevo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brevo = MailAccount::create(['name' => 'Brevo', 'driver' => 'brevo', 'from_email' => 'ar@ar.test', 'from_name' => 'AR', 'hourly_limit' => 1000, 'daily_limit' => 1000, 'is_active' => true]);
        $this->brevo->settings = ['api_key' => 'k', 'webhook_token' => 'jeton-secret-123'];
        $this->brevo->forceFill(['last_tested_at' => now(), 'last_test_result' => 'ok'])->save();
        app(MailProviderFactory::class)->fake($this->brevo, $this->provider);
    }

    private function sentVia(MailAccount $account, string $email = 'cible@pro.test', array $contactAttrs = [], array $campaignAttrs = []): CampaignRecipient
    {
        $this->contact($email, $contactAttrs);
        $this->launch($this->campaign('prospection', $campaignAttrs + ['mail_account_id' => $account->id]));

        return CampaignRecipient::where('email', $email)->firstOrFail();
    }

    private function hook(array $payload, string $token = 'jeton-secret-123')
    {
        return $this->postJson(route('webhooks.brevo', ['token' => $token]), $payload);
    }

    public function test_webhook_requires_the_account_token(): void
    {
        $this->hook(['event' => 'delivered'], 'mauvais')->assertStatus(401);
    }

    public function test_delivery_events_are_idempotent_and_never_downgraded(): void
    {
        $r = $this->sentVia($this->brevo);
        $bounce = ['event' => 'hard_bounce', 'X-Mailin-custom' => $r->idempotency_key, 'ts_event' => now()->timestamp, 'message-id' => $r->provider_message_id, 'reason' => 'mailbox unknown'];

        $this->hook($bounce)->assertOk()->assertJson(['traites' => 1]);
        $this->hook($bounce)->assertOk()->assertJson(['doublons' => 1]);
        // Un « livré » arrivé en retard n'efface pas le rebond.
        $this->hook(['event' => 'delivered', 'X-Mailin-custom' => $r->idempotency_key, 'ts_event' => now()->subMinute()->timestamp])->assertJson(['traites' => 1]);

        $r->refresh();
        $this->assertSame('rebond_definitif', $r->delivery_status);
        $this->assertNotNull($r->delivered_at);
        $this->assertSame(1, EmailEvent::where('type', 'rebond_definitif')->count());

        // L'adresse invalide est exclue des envois suivants : seule destinataire, la campagne est bloquée.
        $this->provider->sent = [];
        $this->launch($this->campaign('prospection', ['mail_account_id' => $this->brevo->id]))->assertSessionHasErrors('campaign');
        $this->assertSame([], $this->provider->sent);
        $this->assertTrue(app(SuppressionService::class)->isSuppressed('cible@pro.test', 'newsletter'), 'rebond définitif : bloqué pour toute finalité');
    }

    public function test_batch_payload_spam_and_unsubscribe(): void
    {
        $a = $this->sentVia($this->brevo, 'a@pro.test');
        $b = CampaignRecipient::firstWhere('email', 'a@pro.test');
        $this->contact('b@pro.test');
        $this->launch($this->campaign('prospection', ['mail_account_id' => $this->brevo->id]));
        $other = CampaignRecipient::where('email', 'b@pro.test')->firstOrFail();

        $this->hook([
            ['event' => 'spam', 'X-Mailin-custom' => $a->idempotency_key, 'ts_event' => now()->timestamp],
            ['event' => 'unsubscribed', 'X-Mailin-custom' => $other->idempotency_key, 'ts_event' => now()->timestamp],
            ['event' => 'inconnu'],
        ])->assertJson(['traites' => 2, 'ignores' => 1]);

        $this->assertTrue(app(SuppressionService::class)->isSuppressed('a@pro.test', 'prospection'));
        $this->assertTrue(app(SuppressionService::class)->isSuppressed('b@pro.test', 'prospection'));
        $this->assertNotNull($other->fresh()->unsubscribed_at);
    }

    public function test_provider_opens_are_dropped_without_consent(): void
    {
        $r = $this->sentVia($this->brevo);
        $this->hook(['event' => 'opened', 'X-Mailin-custom' => $r->idempotency_key, 'ts_event' => now()->timestamp])->assertJson(['ignores' => 1]);
        $this->assertNull($r->fresh()->opened_at);
        $this->assertSame(0, EmailEvent::where('type', 'ouverture')->count());
    }

    public function test_internal_tracking_only_with_global_setting_campaign_flag_and_consent(): void
    {
        Setting::put('tracking.opens', true);
        Setting::put('tracking.clicks', true);
        $template = $this->template('prospection', '<p>Bonjour <a href="https://www.entreprise-ar.test/offre">offre</a></p>');
        $this->contact('consent@pro.test')->forceFill(['tracking_consent_at' => now(), 'tracking_consent_source' => 'Formulaire'])->save();
        $this->contact('refus@pro.test');
        $campaign = $this->campaign('prospection', ['template_id' => $template->id, 'track_opens' => true, 'track_clicks' => true]);
        $this->launch($campaign);

        $consent = collect($this->provider->sent)->firstWhere('toEmail', 'consent@pro.test');
        $refus = collect($this->provider->sent)->firstWhere('toEmail', 'refus@pro.test');
        $this->assertStringContainsString('/t/o/', $consent->html);
        $this->assertStringContainsString('/t/c/', $consent->html);
        $this->assertStringNotContainsString('/t/o/', $refus->html);
        $this->assertStringContainsString('https://www.entreprise-ar.test/offre', $refus->html);
        $this->assertStringContainsString('/desinscription/', $consent->html, 'le lien de désinscription n\'est jamais réécrit');

        $r = CampaignRecipient::where('email', 'consent@pro.test')->firstOrFail();
        $this->get(route('tracking.open', ['recipient' => $r->id, 'signature' => TrackingController::sign($r->id, 'open')]))->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertSame(1, $r->fresh()->open_count);

        $url = 'https://www.entreprise-ar.test/offre';
        $this->get(route('tracking.click', ['recipient' => $r->id, 'signature' => TrackingController::sign($r->id, $url), 'u' => $url]))->assertRedirect($url);
        $this->assertNotNull($r->fresh()->clicked_at);
        $this->get(route('tracking.click', ['recipient' => $r->id, 'signature' => TrackingController::sign($r->id, $url), 'u' => 'https://pirate.test']))->assertNotFound();

        $other = CampaignRecipient::where('email', 'refus@pro.test')->firstOrFail();
        $this->get(route('tracking.open', ['recipient' => $other->id, 'signature' => TrackingController::sign($other->id, 'open')]))->assertOk();
        $this->assertNull($other->fresh()->opened_at, 'sans consentement, rien n\'est enregistré');
    }

    public function test_tracking_is_off_by_default(): void
    {
        $this->contact('x@pro.test')->forceFill(['tracking_consent_at' => now()])->save();
        $this->launch($this->campaign('prospection', ['track_opens' => true]));
        $this->assertStringNotContainsString('/t/o/', $this->provider->sent[0]->html);
    }

    public function test_replies_are_matched_by_message_id_then_sender(): void
    {
        $byId = $this->sentVia($this->account, 'repond@pro.test');
        $this->contact('expediteur@pro.test');
        $this->launch($this->campaign());
        $bySender = CampaignRecipient::where('email', 'expediteur@pro.test')->firstOrFail();

        $inbox = new Inbox(['name' => 'Commercial', 'folder' => 'INBOX', 'is_active' => true]);
        $inbox->settings = ['host' => 'imap.test', 'port' => 993, 'username' => 'u', 'password' => 'p'];
        $inbox->save();

        $reader = new class implements InboxReader
        {
            public array $messages = [];

            public function fetch(Inbox $inbox, int $afterUid, int $limit): iterable
            {
                return array_filter($this->messages, fn ($m) => $m->uid > $afterUid);
            }
        };
        $reader->messages = [
            new IncomingMessage(10, 'autre@ailleurs.test', 'Re: offre', '<'.$byId->idempotency_key.'@entreprise-ar.test>', [], Carbon::now()),
            new IncomingMessage(11, 'expediteur@pro.test', 'Re: bonjour', null, [], Carbon::now()),
            new IncomingMessage(12, 'inconnu@pro.test', 'Publicité', null, [], Carbon::now()),
        ];
        $this->app->instance(InboxReader::class, $reader);

        $this->artisan('pm:replies-fetch')->assertSuccessful();
        $this->artisan('pm:replies-fetch')->assertSuccessful(); // pas de double comptage

        $this->assertNotNull($byId->fresh()->replied_at);
        $this->assertNotNull($bySender->fresh()->replied_at);
        $this->assertSame(2, EmailEvent::where('type', 'reponse')->count());
        $this->assertSame(12, $inbox->fresh()->last_uid);
        $this->assertTrue($byId->contact->company->activities()->where('body', 'like', 'Réponse reçue%')->exists());
    }

    public function test_imap_failure_is_reported_without_crashing(): void
    {
        $inbox = new Inbox(['name' => 'KO', 'folder' => 'INBOX', 'is_active' => true]);
        $inbox->settings = ['host' => 'imap.test', 'password' => 'S3cret!'];
        $inbox->save();
        $this->app->instance(InboxReader::class, new class implements InboxReader
        {
            public function fetch(Inbox $inbox, int $afterUid, int $limit): iterable
            {
                throw new RuntimeException('connexion refusée');
            }
        });

        $this->artisan('pm:replies-fetch')->assertSuccessful();
        $this->assertStringStartsWith('Échec', $inbox->fresh()->last_result);
        $this->assertStringNotContainsString('S3cret!', DB::table('pm_inboxes')->value('settings'));
    }

    public function test_reports_and_settings_pages(): void
    {
        $admin = $this->userWithRole('administrateur');
        $this->sentVia($this->account);
        foreach ([route('pm.reports.index'), route('pm.reports.index', ['jours' => 365]), route('pm.settings.tracking.edit'), route('pm.settings.inboxes.index'), route('pm.settings.inboxes.create'), route('pm.notifications.index')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get(route('pm.reports.index'))->assertSee('Indisponible');

        $this->actingAs($admin)->put(route('pm.settings.tracking.update'), ['opens' => '1', 'clicks' => '0'])->assertSessionHas('success');
        $this->assertTrue(Setting::get('tracking.opens'));
        $this->assertFalse(Setting::get('tracking.clicks'));
        $this->actingAs($this->marketer)->put(route('pm.settings.tracking.update'), ['opens' => '1'])->assertForbidden();
        $this->actingAs($this->userWithRole('commercial'))->get(route('pm.reports.index'))->assertForbidden();
    }
}
