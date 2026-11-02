<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function enable(User $user): array
    {
        $this->actingAs($user)->get(route('pm.security.show'))->assertOk()->assertSee('data-qr="otpauth://totp/', false);
        $secret = session('two_factor.setup');
        $this->actingAs($user)->post(route('pm.security.enable'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($user)->post(route('pm.security.enable'), ['code' => Totp::code($secret, intdiv(time(), 30))])->assertRedirect(route('pm.security.show'));
        $codes = session('two_factor.recovery_codes');

        return [$secret, $codes];
    }

    public function test_rfc6238_reference_value(): void
    {
        // Vecteur de test de la RFC 6238 (secret « 12345678901234567890 », T = 59 s), sur 6 chiffres.
        $this->assertSame('287082', Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 1));
        $this->assertNotNull(Totp::verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59));
        $this->assertNull(Totp::verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59 + 120));
    }

    public function test_login_requires_code_and_rejects_replay(): void
    {
        $user = $this->userWithRole('commercial');
        [$secret, $codes] = $this->enable($user);
        $this->assertCount(8, $codes);
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->assertStringNotContainsString($secret, (string) \DB::table('users')->where('id', $user->id)->value('two_factor_secret'), 'secret chiffré');
        auth()->logout();

        $this->post('/connexion', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->post(route('two-factor.challenge'), ['code' => '111111'])->assertSessionHasErrors('code');
        $code = Totp::code($secret, intdiv(time(), 30));
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertRedirect(route('pm.dashboard'));
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post('/connexion', ['email' => $user->email, 'password' => 'password']);
        $this->post(route('two-factor.challenge'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();

        // Code de secours : une seule utilisation.
        $this->post(route('two-factor.challenge'), ['code' => strtolower($codes[0])])->assertRedirect(route('pm.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertCount(7, (array) $user->fresh()->two_factor_recovery_codes);
    }

    public function test_admins_must_enroll_unless_demo(): void
    {
        config(['prospection.security.require_2fa_roles' => ['administrateur']]);
        $admin = $this->userWithRole('administrateur');
        $this->actingAs($admin)->get(route('pm.dashboard'))->assertRedirect(route('pm.security.show'));
        $this->actingAs($admin)->get(route('pm.security.show'))->assertOk();
        $this->actingAs($this->userWithRole('commercial'))->get(route('pm.dashboard'))->assertOk();

        config(['prospection.demo' => true]);
        $this->actingAs($admin)->get(route('pm.dashboard'))->assertOk();
        config(['prospection.demo' => false]);

        $this->enable($admin);
        $this->actingAs($admin->fresh())->get(route('pm.dashboard'))->assertOk();
        $this->actingAs($admin->fresh())->post(route('pm.security.disable'), ['password' => 'password'])->assertSessionHasErrors('password');
    }
}
