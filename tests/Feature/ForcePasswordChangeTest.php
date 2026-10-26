<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Auth\ForcePasswordChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function compteProvisoire(): User
    {
        return User::create([
            'name'                 => 'Nouvelle recrue',
            'email'                => 'recrue@naneushop.tg',
            'password'             => Hash::make('ProvisoireInitial2026'),
            'role'                 => UserRole::Caissier,
            'is_active'            => true,
            'must_change_password' => true,
        ])->fresh();
    }

    public function test_un_mot_de_passe_provisoire_verrouille_lapplication(): void
    {
        $this->actingAs($this->compteProvisoire());

        $this->get(route('tableau-de-bord'))->assertRedirect(route('mot-de-passe.changer'));
        $this->get(route('produits.index'))->assertRedirect(route('mot-de-passe.changer'));

        // L'écran de changement, lui, reste accessible : sinon, boucle.
        $this->get(route('mot-de-passe.changer'))->assertOk();
    }

    public function test_changer_son_mot_de_passe_leve_le_verrou(): void
    {
        $utilisateur = $this->compteProvisoire();

        $this->actingAs($utilisateur);

        Livewire::test(ForcePasswordChange::class)
            ->set('current_password', 'ProvisoireInitial2026')
            ->set('password', 'MonVraiMotDePasse2026')
            ->set('password_confirmation', 'MonVraiMotDePasse2026')
            ->call('changer')
            ->assertHasNoErrors()
            ->assertRedirect(route('tableau-de-bord'));

        $utilisateur->refresh();

        $this->assertFalse($utilisateur->must_change_password);
        $this->assertTrue(Hash::check('MonVraiMotDePasse2026', $utilisateur->password));

        $this->get(route('caisse'))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'mot_de_passe.change']);
    }

    public function test_le_mot_de_passe_provisoire_doit_etre_exact(): void
    {
        $this->actingAs($this->compteProvisoire());

        Livewire::test(ForcePasswordChange::class)
            ->set('current_password', 'mauvais')
            ->set('password', 'MonVraiMotDePasse2026')
            ->set('password_confirmation', 'MonVraiMotDePasse2026')
            ->call('changer')
            ->assertHasErrors('current_password');
    }

    public function test_on_ne_peut_pas_reconduire_le_meme_mot_de_passe(): void
    {
        $this->actingAs($this->compteProvisoire());

        Livewire::test(ForcePasswordChange::class)
            ->set('current_password', 'ProvisoireInitial2026')
            ->set('password', 'ProvisoireInitial2026')
            ->set('password_confirmation', 'ProvisoireInitial2026')
            ->call('changer')
            ->assertHasErrors('password');
    }

    public function test_un_compte_normal_nest_pas_verrouille(): void
    {
        $utilisateur = User::create([
            'name' => 'Habitué', 'email' => 'habitue@naneushop.tg',
            'password' => Hash::make('MotDePasseSolide2026'),
            'role' => UserRole::Caissier, 'is_active' => true,
            'must_change_password' => false,
        ])->fresh();

        $this->actingAs($utilisateur);

        $this->get(route('caisse'))->assertOk();
    }
}
