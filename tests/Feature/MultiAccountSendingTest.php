<?php

namespace Tests\Feature;

use App\Models\CampaignRecipient;
use App\Models\MailAccount;
use App\Services\Mail\MailProviderFactory;
use Tests\Support\FakeMailProvider;

class MultiAccountSendingTest extends CampaignTestCase
{
    private function gmail(string $email, int $daily): array
    {
        $account = MailAccount::create(['name' => $email, 'driver' => 'log', 'from_email' => $email, 'from_name' => 'AR', 'hourly_limit' => 1000, 'daily_limit' => $daily, 'is_active' => true]);
        $account->forceFill(['last_tested_at' => now(), 'last_test_result' => 'ok'])->save();
        $provider = new FakeMailProvider;
        app(MailProviderFactory::class)->fake($account, $provider);

        return [$account, $provider];
    }

    public function test_sending_is_spread_across_accounts_within_each_quota(): void
    {
        $this->account->forceFill(['daily_limit' => 2])->save();
        [$second, $secondProvider] = $this->gmail('commercial2@gmail.test', 2);
        [$third, $thirdProvider] = $this->gmail('commercial3@gmail.test', 1);
        foreach (range(1, 7) as $i) {
            $this->contact("client{$i}@exemple.fr");
        }

        $campaign = $this->campaign('prospection', ['account_ids' => [$second->id, $third->id]]);
        $this->launch($campaign)->assertSessionHasNoErrors();

        $this->assertSame(2, count($this->provider->sent));
        $this->assertSame(2, count($secondProvider->sent));
        $this->assertSame(1, count($thirdProvider->sent));
        $this->assertSame(5, CampaignRecipient::where('status', 'accepte')->count(), 'capacité totale = 2 + 2 + 1');
        $this->assertSame(2, CampaignRecipient::where('status', 'en_attente')->count(), 'le reste attend la levée des quotas');
        $this->assertSame(1, CampaignRecipient::where('mail_account_id', $third->id)->count());
        // L'adresse d'expédition est celle du compte qui envoie (exigence Gmail).
        $this->assertSame('commercial3@gmail.test', $thirdProvider->sent[0]->fromEmail);
    }

    public function test_same_person_keeps_the_same_sender(): void
    {
        [$second] = $this->gmail('b@gmail.test', 100);
        $this->contact('fidele@exemple.fr');
        $first = $this->campaign('prospection', ['account_ids' => [$second->id]]);
        $this->launch($first);
        $used = CampaignRecipient::where('email', 'fidele@exemple.fr')->value('mail_account_id');

        $again = $this->campaign('prospection', ['account_ids' => [$second->id]]);
        $this->launch($again);
        $this->assertSame([$used, $used], CampaignRecipient::where('email', 'fidele@exemple.fr')->orderBy('id')->pluck('mail_account_id')->all());
    }
}
