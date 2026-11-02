<?php

namespace Tests\Feature;

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
}
