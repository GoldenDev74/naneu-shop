<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Authentification
|--------------------------------------------------------------------------
|
| Pas de route d'inscription : Naneu Shop n'est pas un service ouvert.
| Les comptes sont créés exclusivement par un administrateur depuis
| l'écran « Utilisateurs », ou en console via `naneu:creer-admin`.
| Laisser /register accessible reviendrait à offrir un accès au stock
| à quiconque connaît l'adresse du site.
|
*/

Route::middleware('guest')->group(function () {
    Volt::route('connexion', 'pages.auth.login')
        ->name('login');

    Volt::route('mot-de-passe-oublie', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reinitialiser-mot-de-passe/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Volt::route('verifier-email', 'pages.auth.verify-email')
        ->name('verification.notice');

    Route::get('verifier-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirmer-mot-de-passe', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
