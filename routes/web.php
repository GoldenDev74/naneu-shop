<?php

use App\Livewire\Accounting\AccountingBoard;
use App\Livewire\Alerts\AlertList;
use App\Livewire\Audit\AuditTrail;
use App\Livewire\Auth\ForcePasswordChange;
use App\Livewire\Categories\CategoryManager;
use App\Livewire\Dashboard;
use App\Livewire\Expiry\ExpiryBoard;
use App\Livewire\Inventory\InventoryForm;
use App\Livewire\Inventory\InventoryList;
use App\Livewire\Movements\MovementLedger;
use App\Http\Controllers\ReceiptController;
use App\Livewire\Pos\Checkout;
use App\Livewire\Products\BatchList;
use App\Livewire\Products\ProductForm;
use App\Livewire\Products\ProductList;
use App\Livewire\Reports\ReportCentre;
use App\Livewire\Settings\SettingsPanel;
use App\Livewire\Stock\EntryForm;
use App\Livewire\Stock\EntryList;
use App\Livewire\Stock\EntryShow;
use App\Livewire\Stock\ExitForm;
use App\Livewire\Stock\ExitList;
use App\Livewire\Stock\ExitShow;
use App\Livewire\Suppliers\SupplierManager;
use App\Livewire\Users\UserManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tableau-de-bord');

// Bascule de langue, aussi depuis la page de connexion.
Route::post('langue/{locale}', function (Request $request, string $locale) {
    abort_unless(array_key_exists($locale, config('naneu.locales')), 404);

    $request->session()->put('locale', $locale);
    $request->user()?->forceFill(['locale' => $locale])->save();

    return back();
})->name('langue');

// Accessible dès la connexion, avant même le changement de mot de passe
// provisoire — c'est la porte de sortie du verrou `mot-de-passe.actuel`.
Route::middleware(['auth', 'actif'])
    ->get('changer-mot-de-passe', ForcePasswordChange::class)
    ->name('mot-de-passe.changer');

Route::middleware(['auth', 'actif', 'mot-de-passe.actuel'])->group(function () {

    // Chaque menu est ouvert, en lecture ou en accès complet, selon les droits
    // de l'utilisateur (User::accessLevel) : la même règle construit le menu
    // latéral. `,ecriture` ferme les pages de saisie à la lecture seule.
    Route::get('tableau-de-bord', Dashboard::class)->middleware('section:tableau-de-bord')->name('tableau-de-bord');

    // --- Caisse -------------------------------------------------------------
    Route::middleware('section:caisse')->group(function () {
        Route::get('caisse', Checkout::class)->name('caisse');
        Route::get('caisse/ticket/{exit}', [ReceiptController::class, 'show'])->name('caisse.ticket');
        Route::get('caisse/ticket/{exit}/pdf', [ReceiptController::class, 'pdf'])->name('caisse.ticket.pdf');
    });

    // --- Catalogue ----------------------------------------------------------
    Route::middleware('section:produits')->group(function () {
        Route::get('produits', ProductList::class)->name('produits.index');
        Route::get('produits/nouveau', ProductForm::class)->middleware('section:produits,ecriture')->name('produits.create');
        Route::get('produits/{product}/modifier', ProductForm::class)->middleware('section:produits,ecriture')->name('produits.edit');
    });
    Route::get('produits/{product}/lots', BatchList::class)->middleware('section:lots')->name('produits.lots');

    Route::get('categories', CategoryManager::class)->middleware('section:categories')->name('categories.index');
    Route::get('fournisseurs', SupplierManager::class)->middleware('section:fournisseurs')->name('fournisseurs.index');

    // --- Mouvements de stock ---------------------------------------------
    Route::middleware('section:entrees')->group(function () {
        Route::get('entrees', EntryList::class)->name('entrees.index');
        Route::get('entrees/nouvelle', EntryForm::class)->middleware('section:entrees,ecriture')->name('entrees.create');
        Route::get('entrees/{entry}', EntryShow::class)->name('entrees.show');
    });

    Route::middleware('section:sorties')->group(function () {
        Route::get('sorties', ExitList::class)->name('sorties.index');
        Route::get('sorties/nouvelle', ExitForm::class)->middleware('section:sorties,ecriture')->name('sorties.create');
        Route::get('sorties/{exit}', ExitShow::class)->name('sorties.show');
    });

    Route::get('lots', BatchList::class)->middleware('section:lots')->name('lots.index');
    Route::get('mouvements', MovementLedger::class)->middleware('section:mouvements')->name('mouvements.index');

    // --- Surveillance ----------------------------------------------------
    Route::get('alertes', AlertList::class)->middleware('section:alertes')->name('alertes.index');
    Route::get('peremptions', ExpiryBoard::class)->middleware('section:peremptions')->name('peremptions.index');

    // --- Inventaire ------------------------------------------------------
    Route::middleware('section:inventaires')->group(function () {
        Route::get('inventaires', InventoryList::class)->name('inventaires.index');
        Route::get('inventaires/{count}', InventoryForm::class)->name('inventaires.show');
    });

    // --- Pilotage et administration (gérance) -----------------------------
    Route::get('rapports', ReportCentre::class)->middleware('section:rapports')->name('rapports.index');
    Route::get('comptabilite', AccountingBoard::class)->middleware('section:comptabilite')->name('comptabilite.index');
    Route::get('utilisateurs', UserManager::class)->middleware('section:utilisateurs')->name('utilisateurs.index');
    Route::get('parametres', SettingsPanel::class)->middleware('section:parametres')->name('parametres.index');
    Route::get('journal', AuditTrail::class)->middleware('section:journal')->name('journal.index');

    // --- Profil ------------------------------------------------------------
    Route::view('profil', 'profile')->name('profile');

    Route::post('deconnexion', function (Request $request) {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->input('raison') === 'inactivite') {
            $request->session()->flash('status', __('Session fermée après :n minutes d\'inactivité. Reconnectez-vous.', [
                'n' => (int) config('naneu.security.idle_minutes'),
            ]));
        }

        return redirect()->route('login');
    })->name('deconnexion');
});

require __DIR__.'/auth.php';
