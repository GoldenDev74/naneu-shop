<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Comptes et accès.
 *
 * L'administrateur gère tous les comptes ; la gérance crée et gère ceux
 * de ses employés (profils Vente et Gestion de stock), sans pouvoir
 * toucher aux comptes gérants ou administrateurs (User::manages).
 *
 * Garde-fous :
 *   — personne ne peut modifier son propre rôle ;
 *   — personne ne peut se supprimer ni se désactiver soi-même ;
 *   — le dernier administrateur actif ne peut être ni rétrogradé
 *     ni désactivé, sous peine de verrouiller l'application.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccess('utilisateurs');
    }

    public function view(User $user, User $target): bool
    {
        return $user->is($target) || $user->manages($target);
    }

    public function create(User $user): bool
    {
        return $user->canAccess('utilisateurs') && $user->role->assignableRoles() !== [];
    }

    public function update(User $user, User $target): bool
    {
        return $user->manages($target);
    }

    public function updateProfile(User $user, User $target): bool
    {
        return $user->is($target) || $user->manages($target);
    }

    /** Attribuer ce rôle-là (création ou changement) : seulement un rôle que l'on peut donner. */
    public function assignRole(User $user, UserRole $role): bool
    {
        return in_array($role, $user->role->assignableRoles(), true);
    }

    public function changeRole(User $user, User $target): bool
    {
        if (! $user->manages($target) || $user->is($target)) {
            return false;
        }

        return ! $this->isLastActiveAdmin($target);
    }

    public function toggleActive(User $user, User $target): bool
    {
        if (! $user->manages($target) || $user->is($target)) {
            return false;
        }

        return ! $this->isLastActiveAdmin($target);
    }

    /** Changer ou régénérer le mot de passe d'un employé qui l'a oublié. */
    public function resetPassword(User $user, User $target): bool
    {
        return ! $user->is($target) && $user->manages($target);
    }

    public function delete(User $user, User $target): bool
    {
        if (! $user->manages($target) || $user->is($target)) {
            return false;
        }

        return ! $this->isLastActiveAdmin($target);
    }

    /**
     * Vrai si retirer ce compte des administrateurs actifs ne laisserait
     * plus personne pour administrer la boutique.
     */
    private function isLastActiveAdmin(User $target): bool
    {
        if (! $target->isAdmin() || ! $target->is_active) {
            return false;
        }

        return User::query()
            ->active()
            ->role(UserRole::Admin)
            ->whereKeyNot($target->id)
            ->doesntExist();
    }
}
