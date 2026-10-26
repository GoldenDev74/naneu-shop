<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'naneu:creer-admin
                            {--name= : Nom complet}
                            {--username= : Nom d\'utilisateur (identifiant de connexion)}
                            {--email= : Adresse e-mail}
                            {--generate : Générer un mot de passe aléatoire et l\'afficher}';

    protected $description = 'Crée un compte administrateur';

    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Nom complet',
            required: true,
        );

        $email = $this->option('email') ?: text(
            label: 'Adresse e-mail',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL)
                ? null
                : 'Adresse e-mail invalide.',
        );

        $username = Str::lower($this->option('username') ?: text(
            label: "Nom d'utilisateur",
            default: Str::lower(Str::before($email, '@')),
            required: true,
            validate: fn (string $value) => preg_match('/^[a-z0-9._-]{3,30}$/', Str::lower($value))
                ? null
                : 'De 3 à 30 caractères : minuscules, chiffres, point, tiret ou soulignement.',
        ));

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->components->error("Un compte existe déjà avec l'adresse {$email}.");

            return self::FAILURE;
        }

        if (User::withTrashed()->where('username', $username)->exists()) {
            $this->components->error("Le nom d'utilisateur {$username} est déjà pris.");

            return self::FAILURE;
        }

        $generated = null;

        if ($this->option('generate')) {
            $generated = Str::password(16);
            $plain = $generated;
        } else {
            $plain = password(
                label: 'Mot de passe',
                required: true,
                validate: function (string $value) {
                    try {
                        validator(
                            ['password' => $value],
                            ['password' => ['required', Password::defaults()]]
                        )->validate();
                    } catch (ValidationException $e) {
                        return $e->validator->errors()->first('password');
                    }

                    return null;
                },
            );
        }

        $user = User::create([
            'name'              => $name,
            'username'          => $username,
            'email'             => $email,
            'password'          => Hash::make($plain),
            'role'              => UserRole::Admin,
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        AuditLog::record('utilisateur.cree', null, $user, [
            'email'  => $user->email,
            'role'   => UserRole::Admin->value,
            'source' => 'console',
        ]);

        $this->newLine();
        $this->components->info("Compte administrateur créé : {$user->username} ({$user->email})");

        if ($generated) {
            $this->components->warn('Mot de passe généré (notez-le, il ne sera plus affiché) :');
            $this->line('    '.$generated);
        }

        return self::SUCCESS;
    }
}
