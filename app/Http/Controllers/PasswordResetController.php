<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function request(): Response
    {
        return Inertia::render('ForgotPassword');
    }

    /**
     * A resposta é sempre a mesma, exista ou não a conta: descobrir quais
     * e-mails estão cadastrados não pode ser um efeito colateral desta tela.
     */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $status = Password::sendResetLink(['email' => $data['email'], 'is_active' => true]);
        if ($status === Password::RESET_LINK_SENT) {
            AuditLog::create([
                'action' => 'password.reset_requested',
                'subject_type' => User::class,
                'subject_id' => User::where('email', $data['email'])->value('id'),
                'metadata' => ['ip' => $request->ip()],
            ]);
        }

        return back()->with('success', 'Se houver uma conta ativa com esse e-mail, o link de redefinição foi enviado. O link vale por 60 minutos.');
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()->uncompromised()],
        ]);

        $status = Password::reset(
            [...$data, 'password_confirmation' => $request->input('password_confirmation'), 'is_active' => true],
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                // Uma senha trocada tem que encerrar o que já estava aberto.
                DB::table('sessions')->where('user_id', $user->id)->delete();
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'password.reset_completed',
                    'subject_type' => User::class,
                    'subject_id' => $user->id,
                    'metadata' => ['sessions_revoked' => true],
                ]);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => match ($status) {
                Password::INVALID_TOKEN => 'Este link já foi usado ou expirou. Peça um novo.',
                Password::INVALID_USER => 'Não foi possível redefinir com esses dados.',
                Password::RESET_THROTTLED => 'Aguarde antes de tentar novamente.',
                default => 'Não foi possível redefinir a senha.',
            }]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('success', 'Senha redefinida. Entre com a nova senha.');
    }
}
