<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.forgot-password');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
            $this->get(route('password.reset', ['token' => $notification->token]))
                ->assertOk()
                ->assertSeeVolt('pages.auth.reset-password');

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            // Le nouveau mot de passe doit satisfaire la politique définie
            // dans AppServiceProvider : 10 caractères, casse mixte, chiffres.
            Volt::test('pages.auth.reset-password', ['token' => $notification->token])
                ->set('email', $user->email)
                ->set('password', 'NouveauMotDePasse2026')
                ->set('password_confirmation', 'NouveauMotDePasse2026')
                ->call('resetPassword')
                ->assertHasNoErrors()
                ->assertRedirect(route('login', absolute: false));

            return true;
        });

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('NouveauMotDePasse2026', $user->fresh()->password)
        );
    }

    public function test_une_reinitialisation_est_inscrite_au_journal(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            Volt::test('pages.auth.reset-password', ['token' => $notification->token])
                ->set('email', $user->email)
                ->set('password', 'AutreMotDePasse2026')
                ->set('password_confirmation', 'AutreMotDePasse2026')
                ->call('resetPassword');

            return true;
        });

        $this->assertDatabaseHas('audit_logs', [
            'event'      => 'mot_de_passe.reinitialise',
            'user_email' => $user->email,
        ]);
    }
}
