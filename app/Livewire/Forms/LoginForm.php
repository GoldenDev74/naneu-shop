<?php

namespace App\Livewire\Forms;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountLockedNotification;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    /** Adresse e-mail ou nom d'utilisateur. */
    #[Validate('required|string|max:150')]
    public string $login = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $compte = User::where($this->loginField(), $this->loginValue())->first();

        // Compte verrouillé : refusé même avec le bon mot de passe, pour
        // qu'une attaque par essais successifs ne puisse pas aboutir.
        if ($compte?->isLocked()) {
            throw ValidationException::withMessages([
                'form.login' => __('Compte verrouillé après plusieurs échecs de connexion. Réessayez après :heure ou demandez à la gérance de le déverrouiller.', [
                    'heure' => $compte->locked_until->format('H:i'),
                ]),
            ]);
        }

        if (! Auth::attempt([$this->loginField() => $this->loginValue(), 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            if ($compte) {
                $this->compterEchec($compte);
            }

            throw ValidationException::withMessages([
                'form.login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if ($compte && ($compte->failed_login_attempts > 0 || $compte->locked_until)) {
            $compte->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
        }
    }

    /**
     * Au-delà du nombre d'échecs autorisé, le compte est verrouillé
     * quelques minutes et la gérance est prévenue (cloche + journal).
     */
    private function compterEchec(User $compte): void
    {
        $echecs = $compte->failed_login_attempts + 1;
        $maximum = max(1, (int) config('naneu.security.max_failed_logins'));

        if ($echecs < $maximum) {
            $compte->forceFill(['failed_login_attempts' => $echecs])->save();

            return;
        }

        $compte->forceFill([
            'failed_login_attempts' => 0,
            'locked_until'          => now()->addMinutes(max(1, (int) config('naneu.security.lockout_minutes'))),
        ])->save();

        AuditLog::record('compte.verrouille', null, $compte, [
            'identifiant' => $compte->username,
            'echecs'      => $echecs,
            'jusqua'      => $compte->locked_until->toDateTimeString(),
        ], request());

        Notification::send(
            User::query()->alertRecipients()->whereKeyNot($compte->id)->get(),
            new AccountLockedNotification($compte),
        );
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /** Une arobase désigne une adresse e-mail, sinon un nom d'utilisateur. */
    public function loginField(): string
    {
        return str_contains($this->login, '@') ? 'email' : 'username';
    }

    private function loginValue(): string
    {
        $valeur = trim($this->login);

        // Les noms d'utilisateur sont enregistrés en minuscules.
        return $this->loginField() === 'username' ? Str::lower($valeur) : $valeur;
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower(trim($this->login)).'|'.request()->ip());
    }
}
