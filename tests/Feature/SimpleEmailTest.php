<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Segment;
use App\Services\SuppressionService;

class SimpleEmailTest extends CampaignTestCase
{
    public function test_three_steps_create_everything_and_send(): void
    {
        $lyon = $this->contact('claire@agence.fr', ['first_name' => 'Claire'], ['city' => 'Lyon']);
        $this->contact('paul@garage.fr', ['first_name' => 'Paul'], ['city' => 'Lille']);
        $this->contact('stop@agence.fr', [], ['city' => 'Lyon']);
        app(SuppressionService::class)->suppress('stop@agence.fr', 'all', 'opposition', 'test');

        $this->actingAs($this->marketer)->get(route('pm.simple-email.create', ['vu' => 1, 'city' => 'Lyon']))->assertOk()
            ->assertSee('personne(s) recevront')->assertSee('Retirées automatiquement');
        $this->actingAs($this->marketer)->post(route('pm.simple-email.who'), ['city' => 'Lyon'])->assertRedirect(route('pm.simple-email.message'));
        $this->actingAs($this->marketer)->get(route('pm.simple-email.message', ['exemple' => 'relance']))->assertOk()->assertSee('Suite à mon précédent message');
        $this->actingAs($this->marketer)->post(route('pm.simple-email.message.store'), [
            'subject' => 'Bonjour {{prenom}}', 'body' => "Bonjour {{prenom}},\n\n<b>Offre</b> pour {{entreprise}}.", 'mail_account_id' => $this->account->id,
        ])->assertRedirect(route('pm.simple-email.confirm'));
        $this->actingAs($this->marketer)->get(route('pm.simple-email.confirm'))->assertOk()->assertSee('Bonjour Claire')->assertSee('&lt;b&gt;Offre&lt;/b&gt;', false);

        $this->actingAs($this->marketer)->post(route('pm.simple-email.send'), ['when' => 'now'])->assertSessionHasErrors('confirm');
        $this->actingAs($this->marketer)->post(route('pm.simple-email.send'), ['when' => 'now', 'confirm' => '1'])->assertRedirect();

        $campaign = Campaign::latest('id')->firstOrFail();
        $this->assertSame('prospection', $campaign->type);
        $this->assertSame(['city' => 'Lyon', 'lifecycle' => 'prospect'], Segment::findOrFail($campaign->segment_id)->filters);
        $this->assertSame(30, $campaign->exclusions['contacted_within_days']);
        $this->assertNotSame('brouillon', $campaign->fresh()->status);
        $sent = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'accepte')->pluck('email')->all();
        $this->assertSame(['claire@agence.fr'], $sent, 'opposition et autre ville exclues');
        $this->assertStringContainsString('&lt;b&gt;Offre&lt;/b&gt;', $this->provider->sent[0]->html, 'texte saisi échappé');
        $this->assertStringContainsString('Claire', $this->provider->sent[0]->html);
    }

    public function test_access_and_empty_audience(): void
    {
        $this->actingAs($this->userWithRole('commercial'))->get(route('pm.simple-email.create'))->assertForbidden();
        $this->actingAs($this->marketer)->post(route('pm.simple-email.who'), ['city' => 'Nulle-part'])->assertSessionHasErrors('who');
        $this->actingAs($this->marketer)->get(route('pm.simple-email.confirm'))->assertRedirect(route('pm.simple-email.create'));
    }
}
