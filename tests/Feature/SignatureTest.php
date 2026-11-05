<?php

namespace Tests\Feature;

use App\Services\Mail\MailProviderFactory;

class SignatureTest extends CampaignTestCase
{
    public function test_account_signature_is_added_to_every_email_and_escaped(): void
    {
        $this->account->forceFill(['signature' => "Yanis Moreau\nEntreprise AR — 06 12 34 56 78\nhttps://www.entreprise-ar.fr\n<script>x</script>"])->save();
        $this->contact('claire@agence.fr', ['first_name' => 'Claire']);

        $this->launch($this->campaign())->assertSessionHasNoErrors();
        $html = $this->provider->sent[0]->html;
        $this->assertStringContainsString('Yanis Moreau<br>', $html);
        $this->assertStringContainsString('<a href="https://www.entreprise-ar.fr"', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertLessThan(strpos($html, 'Se désinscrire'), strpos($html, 'Yanis Moreau'), 'signature avant le lien de désinscription');
    }

    public function test_placeholder_falls_back_to_sender_name(): void
    {
        $this->contact('paul@garage.fr');
        $campaign = $this->campaign('prospection');
        $campaign->template->addVersion(['subject' => 'Bonjour', 'html' => '<p>Texte</p><p>{{signature}}</p>'], $this->marketer->id);
        $this->launch($campaign);
        $this->assertStringContainsString('<p>Entreprise AR</p>', $this->provider->sent[0]->html);
    }

    public function test_html_signature_is_sanitized_and_used(): void
    {
        $admin = $this->userWithRole('administrateur');
        $this->actingAs($admin)->put(route('pm.settings.mail.update', $this->account), [
            'name' => 'Test', 'driver' => 'log', 'from_email' => 'contact@entreprise-ar.test', 'from_name' => 'Entreprise AR',
            'hourly_limit' => 1000, 'daily_limit' => 1000, 'is_active' => '1',
            'signature_html' => '<html><body><table style="color:#0a3f94" onmouseover="steal()"><tr><td><img src="https://cdn.test/logo.png" alt="AR"><b>Yanis</b> <a href="javascript:alert(1)">x</a> <a href="https://www.entreprise-ar.fr">Site</a><script>alert(1)</script></td></tr></table></body></html>',
        ])->assertRedirect();

        $stored = $this->account->fresh()->signature_html;
        $this->assertStringContainsString('<img src="https://cdn.test/logo.png"', $stored);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onmouseover', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringContainsString('color:#0a3f94', $stored);

        $this->account->forceFill(['last_tested_at' => now(), 'last_test_result' => 'ok'])->save();
        app(MailProviderFactory::class)->fake($this->account->fresh(), $this->provider);
        $this->contact('claire@agence.fr');
        $this->launch($this->campaign());
        $this->assertStringContainsString('<b>Yanis</b>', $this->provider->sent[0]->html);
    }

    public function test_signature_preview_is_isolated(): void
    {
        $admin = $this->userWithRole('administrateur');
        $response = $this->actingAs($admin)->post(route('pm.settings.mail.signature_preview'), ['signature_html' => '<p>Bonjour</p><script>x</script>']);
        $response->assertOk()->assertSee('<p>Bonjour</p>', false)->assertDontSee('<script>', false);
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->actingAs($this->marketer)->post(route('pm.settings.mail.signature_preview'), ['signature_html' => 'x'])->assertForbidden();
    }
}
