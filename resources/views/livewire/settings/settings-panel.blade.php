<div class="mx-auto max-w-4xl space-y-5">

    <div class="flex items-center gap-4">
        <x-illustration name="parametres" />
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Paramètres') }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Réglages de la boutique, de la gestion du stock et de la fiscalité.') }}
            </p>
        </div>
    </div>

    <form wire:submit="enregistrer" class="space-y-5">

        <x-form.card icon="store" :title="__('Identité de la boutique')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Nom')" name="shop_name" required>
                    <input type="text" wire:model="shop_name"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Téléphone')" name="shop_phone">
                    <input type="tel" wire:model="shop_phone"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Adresse')" name="shop_address">
                    <input type="text" wire:model="shop_address"
                           class="champ w-full">
                </x-form.field>

                <x-form.field label="NIF" name="shop_nif" :hint="__('Numéro d\'identification fiscale, imprimé sur les documents.')">
                    <input type="text" wire:model="shop_nif"
                           class="champ w-full">
                </x-form.field>
            </div>
        </x-form.card>

        <x-form.card icon="boxes" :title="__('Gestion du stock')">
            <div class="space-y-4">
                <x-form.field :label="__('Ordre de consommation des lots')" name="strategy" required>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label @class([
                            'flex cursor-pointer gap-3 rounded-lg border p-3',
                            'border-marque-500 bg-marque-50 dark:border-marque-600 dark:bg-marque-900/20' => $strategy === 'fifo',
                            'border-slate-200 dark:border-slate-700' => $strategy !== 'fifo',
                        ])>
                            <input type="radio" wire:model.live="strategy" value="fifo"
                                   class="radio mt-0.5">
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">FIFO</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Premier entré, premier sorti. Ordre comptable strict, fondé sur la date de réception.') }}
                                </span>
                            </span>
                        </label>

                        <label @class([
                            'flex cursor-pointer gap-3 rounded-lg border p-3',
                            'border-marque-500 bg-marque-50 dark:border-marque-600 dark:bg-marque-900/20' => $strategy === 'fefo',
                            'border-slate-200 dark:border-slate-700' => $strategy !== 'fefo',
                        ])>
                            <input type="radio" wire:model.live="strategy" value="fefo"
                                   class="radio mt-0.5">
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">FEFO</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Premier périmé, premier sorti. Réduit les pertes en alimentaire.') }}
                                </span>
                            </span>
                        </label>
                    </div>
                </x-form.field>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.field :label="__('Alerte péremption (jours)')" name="expiry_alert_days" required>
                        <input type="number" wire:model="expiry_alert_days" min="1" max="365"
                               class="champ w-full text-right tabular-nums">
                    </x-form.field>

                    <x-form.field :label="__('Seuil critique (jours)')" name="expiry_critical_days" required>
                        <input type="number" wire:model="expiry_critical_days" min="1"
                               class="champ w-full text-right tabular-nums">
                    </x-form.field>

                    <x-form.field :label="__('Seuil de stock bas par défaut')" name="low_stock_threshold" required>
                        <input type="number" wire:model="low_stock_threshold" min="0"
                               class="champ w-full text-right tabular-nums">
                    </x-form.field>
                </div>
            </div>
        </x-form.card>

        <x-form.card icon="receipt" :title="__('Fiscalité')">
            <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" wire:model.live="vat_enabled"
                           class="case">
                    {{ __('Appliquer la TVA') }}
                </label>

                @if ($vat_enabled)
                    <x-form.field :label="__('Taux de TVA par défaut (%)')" name="vat_default_rate" required
                                  :hint="__('Taux normal au Togo : 18 %.')">
                        <input type="number" step="0.01" wire:model="vat_default_rate"
                               class="champ w-40 text-right tabular-nums">
                    </x-form.field>
                @endif
            </div>
        </x-form.card>

        <x-form.card icon="badge-percent" :title="__('Remises')"
                     :description="__('Prix de vente modifié à la main sur un bon de sortie.')">
            <x-form.field :label="__('Remise maximale sans validation de la gérance (%)')" name="max_discount_percent" required
                          :hint="__('Au-delà, seuls la gérance et l\'administrateur peuvent accorder la remise. 0 = toute remise passe par la gérance. Chaque prix modifié exige un motif et reste tracé au journal.')">
                <input type="number" step="0.5" min="0" max="100" wire:model="max_discount_percent"
                       class="champ w-40 text-right tabular-nums">
            </x-form.field>
        </x-form.card>

        <x-form.card icon="shield" :title="__('Sécurité des connexions')"
                     :description="__('Protège les comptes contre les essais de mots de passe et les postes laissés ouverts.')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.field :label="__('Déconnexion après inactivité (min)')" name="idle_minutes" required
                              :hint="__('0 = jamais. Un avertissement s\'affiche une minute avant.')">
                    <input type="number" min="0" max="720" wire:model="idle_minutes"
                           class="champ w-full text-right tabular-nums">
                </x-form.field>
                <x-form.field :label="__('Échecs avant verrouillage')" name="max_failed_logins" required
                              :hint="__('Mots de passe erronés successifs sur un même compte.')">
                    <input type="number" min="3" max="20" wire:model="max_failed_logins"
                           class="champ w-full text-right tabular-nums">
                </x-form.field>
                <x-form.field :label="__('Durée du verrouillage (min)')" name="lockout_minutes" required
                              :hint="__('La gérance est prévenue et peut déverrouiller plus tôt.')">
                    <input type="number" min="1" max="1440" wire:model="lockout_minutes"
                           class="champ w-full text-right tabular-nums">
                </x-form.field>
            </div>
        </x-form.card>

        <x-form.card icon="coins" :title="__('Devise')"
                     :description="__('Monnaie affichée sur les prix, les bons et les rapports.')">
            <x-form.field :label="__('Devise de l\'application')" name="currency_code" required
                          :hint="__('Changer de devise ne convertit pas les montants déjà saisis : les montants restent des nombres entiers, sans centimes.')">
                <select wire:model.live="currency_code"
                        class="champ w-full max-w-xs">
                    @foreach (config('naneu.currencies') as $code => $devise)
                        <option value="{{ $code }}">{{ $devise['name'] }}</option>
                    @endforeach
                </select>
            </x-form.field>

            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                {{ __('Aperçu :') }} <strong class="tabular-nums text-slate-900 dark:text-white">{{ $apercuDevise }}</strong>
            </p>
        </x-form.card>

        <div class="barre-actions justify-end">
            <button type="submit"
                    class="btn-primaire">
                {{ __('Enregistrer les paramètres') }}
            </button>
        </div>
    </form>

    {{-- ============ UNITÉS ============ --}}
    <x-form.card icon="ruler" :title="__('Unités de mesure')"
                 :description="__('Les unités décimales acceptent les quantités au gramme ou au litre près (vrac, pesée).')">

        <div class="mb-4 flex flex-wrap items-end gap-2">
            <x-form.field :label="__('Nom')" name="unite_nom" class="flex-1 min-w-[10rem]">
                <input type="text" wire:model="unite_nom" placeholder="{{ __('Kilogramme') }}"
                       class="champ w-full">
            </x-form.field>

            <x-form.field :label="__('Symbole')" name="unite_symbole" class="w-28">
                <input type="text" wire:model="unite_symbole" placeholder="kg"
                       class="champ w-full">
            </x-form.field>

            <label class="mb-2 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" wire:model="unite_decimal"
                       class="case">
                {{ __('Décimales') }}
            </label>

            <button type="button" wire:click="ajouterUnite"
                    class="btn-secondaire mb-1">
                {{ __('Ajouter') }}
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            @forelse ($unites as $unite)
                <span wire:key="unite-{{ $unite->id }}"
                      class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm dark:border-slate-700">
                    <span class="text-slate-800 dark:text-slate-100">
                        {{ $unite->name }} <span class="text-slate-400">({{ $unite->symbol }})</span>
                    </span>
                    <span class="text-xs text-slate-400">{{ $unite->products_count }} {{ __('produit(s)') }}</span>

                    @if ($unite->products_count === 0)
                        <button wire:click="supprimerUnite({{ $unite->id }})"
                                class="text-slate-400 hover:text-rose-600">
                            <x-icon name="x" class="h-3.5 w-3.5" />
                        </button>
                    @endif
                </span>
            @empty
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Aucune unité définie.') }}</p>
            @endforelse
        </div>
    </x-form.card>

    {{-- ============ STOCKAGE ============ --}}
    <x-form.card icon="hard-drive" :title="__('Stockage des fichiers (MinIO)')"
                 :description="__('Photos de produits et documents fournisseurs. Les objets sont privés : l\'application sert des liens signés de courte durée.')">

        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Disque') }}</dt>
                <dd class="font-mono text-slate-800 dark:text-slate-100">{{ $disque }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Point d\'accès') }}</dt>
                <dd class="font-mono break-all text-slate-800 dark:text-slate-100">
                    {{ config('filesystems.disks.'.$disque.'.endpoint') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-400">Bucket</dt>
                <dd class="font-mono text-slate-800 dark:text-slate-100">
                    {{ config('filesystems.disks.'.$disque.'.bucket') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-400">{{ __('Durée des liens signés') }}</dt>
                <dd class="text-slate-800 dark:text-slate-100">{{ config('naneu.storage.temp_url_ttl') }} {{ __('minutes') }}</dd>
            </div>
        </dl>

        <button type="button" wire:click="testerMinio" wire:loading.attr="disabled"
                class="btn-secondaire mt-4">
            <x-icon name="refresh" class="h-4 w-4" />
            <span wire:loading.remove wire:target="testerMinio">{{ __('Tester la connexion') }}</span>
            <span wire:loading wire:target="testerMinio">{{ __('Test en cours…') }}</span>
        </button>
    </x-form.card>
</div>
