<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Paramètres')]
class SettingsPanel extends Component
{
    use InteractsWithToasts;

    // --- Boutique ---
    public string $shop_name = '';
    public string $shop_phone = '';
    public string $shop_address = '';
    public string $shop_nif = '';

    // --- Stock ---
    public string $strategy = 'fifo';
    public int $expiry_alert_days = 30;
    public int $expiry_critical_days = 7;
    public int $low_stock_threshold = 5;

    // --- Fiscalité ---
    public bool $vat_enabled = true;
    public string $vat_default_rate = '18';

    // --- Devise ---
    public string $currency_code = 'XOF';

    /** Remise que le personnel peut accorder seul sur un prix de vente (%). */
    public string $max_discount_percent = '10';

    // Sécurité des connexions.
    public int $idle_minutes = 30;
    public int $max_failed_logins = 5;
    public int $lockout_minutes = 15;

    // --- Unité en cours de création ---
    public string $unite_nom = '';
    public string $unite_symbole = '';
    public bool $unite_decimal = false;

    public function mount(): void
    {
        $this->authorize('gerer-parametres');

        $this->shop_name    = Setting::get('shop_name', config('naneu.shop.name'));
        $this->shop_phone   = Setting::get('shop_phone', config('naneu.shop.phone'));
        $this->shop_address = Setting::get('shop_address', config('naneu.shop.address'));
        $this->shop_nif     = Setting::get('shop_nif', config('naneu.shop.nif'));

        $this->strategy             = Setting::get('stock_strategy', config('naneu.stock.strategy'));
        $this->expiry_alert_days    = (int) Setting::get('expiry_alert_days', config('naneu.stock.expiry_alert_days'));
        $this->expiry_critical_days = (int) Setting::get('expiry_critical_days', config('naneu.stock.expiry_critical_days'));
        $this->low_stock_threshold  = (int) Setting::get('low_stock_threshold', config('naneu.stock.low_stock_threshold'));

        $this->vat_enabled      = (bool) Setting::get('vat_enabled', config('naneu.vat.enabled'));
        $this->vat_default_rate = (string) Setting::get('vat_default_rate', config('naneu.vat.default_rate'));

        $this->currency_code = Money::currency()['code'];

        $this->max_discount_percent = (string) Setting::get('max_discount_percent', config('naneu.sales.max_discount_percent'));

        $this->idle_minutes      = (int) Setting::get('idle_minutes', config('naneu.security.idle_minutes'));
        $this->max_failed_logins = (int) Setting::get('max_failed_logins', config('naneu.security.max_failed_logins'));
        $this->lockout_minutes   = (int) Setting::get('lockout_minutes', config('naneu.security.lockout_minutes'));
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'shop_name'    => ['required', 'string', 'max:120'],
            'shop_phone'   => ['nullable', 'string', 'max:30'],
            'shop_address' => ['nullable', 'string', 'max:255'],
            'shop_nif'     => ['nullable', 'string', 'max:50'],

            'strategy'             => ['required', 'in:fifo,fefo'],
            'expiry_alert_days'    => ['required', 'integer', 'min:1', 'max:365'],
            'expiry_critical_days' => ['required', 'integer', 'min:1', 'lte:expiry_alert_days'],
            'low_stock_threshold'  => ['required', 'integer', 'min:0'],

            'vat_enabled'      => ['boolean'],
            'vat_default_rate' => ['required', 'numeric', 'min:0', 'max:100'],

            'currency_code' => ['required', Rule::in(array_keys(config('naneu.currencies')))],

            'max_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],

            'idle_minutes'      => ['required', 'integer', 'min:0', 'max:720'],
            'max_failed_logins' => ['required', 'integer', 'min:3', 'max:20'],
            'lockout_minutes'   => ['required', 'integer', 'min:1', 'max:1440'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'expiry_critical_days.lte' => 'Le seuil critique doit être inférieur ou égal au seuil d\'alerte.',
            'shop_name.required'       => 'Le nom de la boutique est obligatoire.',
        ];
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-parametres');

        $donnees = $this->validate();

        foreach ([
            'shop_name', 'shop_phone', 'shop_address', 'shop_nif',
            'expiry_alert_days', 'expiry_critical_days', 'low_stock_threshold',
            'vat_default_rate', 'max_discount_percent',
            'idle_minutes', 'max_failed_logins', 'lockout_minutes',
        ] as $cle) {
            Setting::put($cle, $donnees[$cle]);
        }

        Setting::put('stock_strategy', $donnees['strategy']);
        Setting::put('vat_enabled', $donnees['vat_enabled']);
        Setting::put('currency_code', $donnees['currency_code']);

        AuditLog::record('parametres.modifies', auth()->user(), null, [
            'strategie' => $donnees['strategy'],
            'devise'    => $donnees['currency_code'],
            'remise_max' => $donnees['max_discount_percent'],
        ]);

        $this->toastSucces('Paramètres enregistrés.');
    }

    public function ajouterUnite(): void
    {
        $this->authorize('gerer-parametres');

        $this->validate([
            'unite_nom'     => ['required', 'string', 'max:60'],
            'unite_symbole' => ['required', 'string', 'max:10', 'unique:units,symbol'],
        ], [
            'unite_nom.required'     => 'Nom de l\'unité obligatoire.',
            'unite_symbole.required' => 'Symbole obligatoire.',
            'unite_symbole.unique'   => 'Ce symbole existe déjà.',
        ]);

        Unit::create([
            'name'           => $this->unite_nom,
            'symbol'         => $this->unite_symbole,
            'allows_decimal' => $this->unite_decimal,
        ]);

        $this->reset(['unite_nom', 'unite_symbole', 'unite_decimal']);

        $this->toastSucces('Unité ajoutée.');
    }

    public function supprimerUnite(int $id): void
    {
        $this->authorize('gerer-parametres');

        $unite = Unit::withCount('products')->findOrFail($id);

        if ($unite->products_count > 0) {
            $this->toastErreur(__("L'unité « :nom » est utilisée par :n produit(s).", ['nom' => $unite->name, 'n' => $unite->products_count]));

            return;
        }

        $unite->delete();

        $this->toastSucces('Unité supprimée.');
    }

    /**
     * Vérifie que MinIO répond. Sans ce test, un problème de configuration
     * ne se découvre qu'au moment d'envoyer une photo de produit.
     */
    public function testerMinio(): void
    {
        $disque = config('naneu.storage.disk');

        try {
            $sonde = 'diagnostic/'.uniqid('test_', true).'.txt';

            Storage::disk($disque)->put($sonde, 'Naneu Shop — test de connexion '.now()->toIso8601String());
            $contenu = Storage::disk($disque)->get($sonde);
            Storage::disk($disque)->delete($sonde);

            if (blank($contenu)) {
                throw new \RuntimeException('Le fichier écrit est revenu vide.');
            }

            $this->toastSucces(__('Connexion à MinIO opérationnelle (bucket « :bucket »).', [
                'bucket' => config('filesystems.disks.'.$disque.'.bucket'),
            ]));
        } catch (\Throwable $e) {
            $this->toastErreur(__('MinIO injoignable : :erreur', ['erreur' => str($e->getMessage())->limit(180)]));
        }
    }

    public function render(): View
    {
        return view('livewire.settings.settings-panel', [
            'unites' => Unit::query()->withCount('products')->orderBy('name')->get(),
            'disque' => config('naneu.storage.disk'),
            'apercuDevise' => $this->apercuDevise(),
        ]);
    }

    /** Montant d'exemple dans la devise sélectionnée, avant enregistrement. */
    private function apercuDevise(): string
    {
        ['prefix' => $prefixe, 'suffix' => $suffixe] = Money::affixes($this->currency_code);

        return $prefixe.Money::format(12500, false).$suffixe;
    }
}
