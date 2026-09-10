<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_request_screen_is_reachable_without_a_session(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_an_active_user_receives_a_single_use_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'producao@padraord.com.br', 'is_active' => true]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password.reset_requested', 'subject_id' => $user->id]);
    }

    public function test_the_answer_does_not_reveal_whether_the_account_exists(): void
    {
        Notification::fake();
        $known = User::factory()->create(['email' => 'conhecido@padraord.com.br']);

        $unknown = $this->post('/forgot-password', ['email' => 'ninguem@padraord.com.br']);
        $existing = $this->post('/forgot-password', ['email' => $known->email]);

        $this->assertSame($unknown->getSession()->get('success'), $existing->getSession()->get('success'));
        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    }

    public function test_an_inactive_user_gets_no_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success');

        Notification::assertNothingSent();
    }

    public function test_a_valid_token_sets_the_password_and_revokes_open_sessions(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-antiga-1234')]);
        $token = Password::broker()->createToken($user);
        DB::table('sessions')->insert([
            'id' => 'sessao-aberta', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'teste', 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'Producao2026Segura', 'password_confirmation' => 'Producao2026Segura',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Producao2026Segura', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'sessao-aberta']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password.reset_completed', 'subject_id' => $user->id]);
        $this->assertGuest();
    }

    public function test_the_same_token_cannot_be_used_twice(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $payload = [
            'token' => $token, 'email' => $user->email,
            'password' => 'Producao2026Segura', 'password_confirmation' => 'Producao2026Segura',
        ];

        $this->post('/reset-password', $payload)->assertRedirect(route('login'));
        $this->post('/reset-password', [...$payload, 'password' => 'Outra2026Diferente', 'password_confirmation' => 'Outra2026Diferente'])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Producao2026Segura', $user->fresh()->password));
    }

    public function test_a_weak_or_mismatched_password_is_refused(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-antiga-1234')]);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'curta1', 'password_confirmation' => 'curta1'])
            ->assertSessionHasErrors('password');
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Producao2026Segura', 'password_confirmation' => 'Divergente2026'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('senha-antiga-1234', $user->fresh()->password));
    }

    public function test_the_link_carries_the_project_notification_and_not_the_framework_default(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertNotSentTo($user, ResetPassword::class);
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_contains((string) $mail->actionUrl, '/reset-password/'.$notification->token)
                && str_contains((string) $mail->actionUrl, urlencode($user->email));
        });
    }
}
