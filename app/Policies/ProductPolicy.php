<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    // La caisse et les écrans de stock cherchent des produits sans que le
    // menu « Produits » soit ouvert : la consultation reste libre.
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->canWrite('produits');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->canWrite('produits');
    }

    /**
     * Le prix de vente engage la marge de la boutique : un magasinier
     * gère les quantités, pas la politique tarifaire.
     */
    public function updatePricing(User $user, Product $product): bool
    {
        return $user->hasAtLeast(UserRole::Gerant);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->isAdmin();
    }
}
