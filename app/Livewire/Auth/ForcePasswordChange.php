<?php

namespace App\Livewire\Auth;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Changer votre mot de passe')]
class ForcePasswordChange extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function changer()
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ], [
            'current_password.required'  => 'Saisissez votre mot de passe actuel.',
            'current_password.current_password' => 'Ce mot de passe ne correspond pas à celui enregistré.',
            'password.required'  => 'Choisissez un nouveau mot de passe.',
            'password.confirmed' => 'Les deux saisies ne correspondent pas.',
            'password.different' => 'Le nouveau mot de passe doit être différent de l\'ancien.',
        ]);

        $utilisateur = auth()->user();

        $utilisateur->forceFill([
            'password'             => Hash::make($this->password),
            'must_change_password' => false,
        ])->save();

        // Les autres appareils restés connectés avec l'ancien mot de passe sont déconnectés.
        $utilisateur->closeSessions(session()->getId());

        AuditLog::record('mot_de_passe.change', $utilisateur, $utilisateur);

        session()->flash('succes', 'Mot de passe mis à jour.');

        return $this->redirectRoute('tableau-de-bord', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.force-password-change');
    }
}
