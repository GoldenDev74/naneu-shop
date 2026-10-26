<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Trace les évènements d'authentification. Volontairement synchrone :
 * un journal de sécurité qui arrive avec du retard, ou qui se perd si la
 * file d'attente tombe, ne vaut pas grand-chose.
 */
class RecordAuthenticationAudit
{
    public function handleLogin(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        AuditLog::record('connexion.reussie', $user, $user);
    }

    public function handleLogout(Logout $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        AuditLog::record('deconnexion', $user, $user);
    }

    public function handleFailed(Failed $event): void
    {
        // On journalise l'identifiant saisi, jamais le mot de passe essayé.
        AuditLog::record('connexion.echouee', null, null, [
            'identifiant' => $event->credentials['email'] ?? $event->credentials['username'] ?? null,
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        AuditLog::record('connexion.bloquee', null, null, [
            'email' => $event->request->input('email'),
        ]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        /** @var User $user */
        $user = $event->user;

        AuditLog::record('mot_de_passe.reinitialise', $user, $user);
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            Login::class         => 'handleLogin',
            Logout::class        => 'handleLogout',
            Failed::class        => 'handleFailed',
            Lockout::class       => 'handleLockout',
            PasswordReset::class => 'handlePasswordReset',
        ];
    }
}
