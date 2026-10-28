<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/prospection')->assertRedirect(route('login'));
        $this->get('/prospection/prospects')->assertRedirect(route('login'));
    }

    public function test_user_can_log_in_and_login_is_audited(): void
    {
        $user = $this->userWithRole('commercial', ['email' => 'commercial@exemple.fr']);

        $this->post('/connexion', ['email' => 'Commercial@Exemple.fr', 'password' => 'password'])
            ->assertRedirect(route('pm.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(AuditLog::where('action', 'auth.login')->where('user_id', $user->id)->exists());
    }

    public function test_wrong_password_gives_generic_error(): void
    {
        $this->userWithRole('commercial', ['email' => 'commercial@exemple.fr']);

        $this->from('/connexion')->post('/connexion', ['email' => 'commercial@exemple.fr', 'password' => 'mauvais'])
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects ou compte désactivé.']);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $this->userWithRole('commercial', ['email' => 'inactif@exemple.fr', 'is_active' => false]);

        $this->post('/connexion', ['email' => 'inactif@exemple.fr', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $this->userWithRole('commercial', ['email' => 'commercial@exemple.fr']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => 'commercial@exemple.fr', 'password' => 'mauvais']);
        }

        $this->post('/connexion', ['email' => 'commercial@exemple.fr', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_deactivated_during_session_is_logged_out(): void
    {
        $user = $this->userWithRole('commercial');
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/prospection')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/connexion')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
