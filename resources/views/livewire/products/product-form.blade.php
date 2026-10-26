<div class="mx-auto max-w-5xl space-y-5">

    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="produits" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $titre }}</h1>
                @if ($product)
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        {{ $product->sku }} — {{ __('stock actuel') }}
                        {{ $product->unit?->formatQuantity((float) $product->quantity_on_hand)
                           ?? rtrim(rtrim(\App\Support\Money::number((float) $product->quantity_on_hand, 3), '0'), ',.') }}
                    </p>
                @endif
            </div>
        </div>

        <x-back-button :href="route('produits.index')">{{ __('Retour à la liste') }}</x-back-button>
    </div>

    <form wire:submit="enregistrer" class="space-y-5">

        {{-- ============ IDENTIFICATION ============ --}}
        <x-form.card icon="tag" :title="__('Identification')"
                     :description="__('La référence sert en interne. Le code-barres est celui imprimé par le fabricant : scannez-le simplement.')">

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Nom du produit')" name="name" required>
                    <input type="text" wire:model="name" x-autofocus
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Référence interne')" name="sku" required>
                    <input type="text" wire:model="sku"
                           class="champ w-full font-mono">
                </x-form.field>

                <x-form.field :label="__('Code-barres (EAN)')" name="barcode"
                              :hint="__('Scannez le code imprimé sur l\'emballage. Laissez vide pour un produit vendu en vrac.')">
                    <div class="relative">
                        <x-icon name="barcode" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input type="text" wire:model="barcode" inputmode="numeric" placeholder="3701234567890"
                               class="champ w-full pl-9 font-mono">
                    </div>
                </x-form.field>

                <x-form.field :label="__('Code interne')" name="internal_code"
                              :hint="__('Pour les produits sans code fabricant (odika, arachides, huile en vrac…).')">
                    <div class="flex gap-2">
                        <input type="text" wire:model="internal_code"
                               class="champ w-full font-mono">
                        <button type="button" wire:click="genererCodeInterne"
                                class="btn-secondaire shrink-0">
                            {{ __('Générer') }}
                        </button>
                    </div>
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.field :label="__('Description')" name="description">
                        <textarea wire:model="description" rows="2"
                                  class="champ w-full"></textarea>
                    </x-form.field>
                </div>
            </div>
        </x-form.card>

        {{-- ============ CLASSIFICATION ============ --}}
        <x-form.card icon="folder-tree" :title="__('Classement')">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.field :label="__('Catégorie')" name="category_id">
                    <select wire:model.live="category_id"
                            class="champ w-full">
                        <option value="">—</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->optionLabel() }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field :label="__('Unité de mesure')" name="unit_id" required>
                    <select wire:model="unit_id"
                            class="champ w-full">
                        <option value="">{{ __('Choisir…') }}</option>
                        @foreach ($unites as $unite)
                            <option value="{{ $unite->id }}">{{ $unite->name }} ({{ $unite->symbol }})</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field :label="__('Fournisseur habituel')" name="default_supplier_id">
                    <select wire:model="default_supplier_id"
                            class="champ w-full">
                        <option value="">—</option>
                        @foreach ($fournisseurs as $fournisseur)
                            <option value="{{ $fournisseur->id }}">{{ $fournisseur->name }}</option>
                        @endforeach
                    </select>
                </x-form.field>
            </div>
        </x-form.card>

        {{-- ============ PRIX ============ --}}
        @if ($peutFixerPrix)
            <x-form.card icon="coins" :title="__('Prix et TVA')"
                         :description="__('Le prix d\'achat n\'est pas saisi ici : il est enregistré lot par lot à chaque réception, ce qui permet de calculer la marge réelle en FIFO.')">

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.field :label="__('Prix de vente TTC')" name="sale_price" required
                                  :hint="__('Prix affiché en rayon, payé par le client.')">
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.400ms="sale_price" inputmode="numeric"
                                   class="champ w-full pr-14 text-right font-medium tabular-nums">
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">{{ \App\Support\Money::currency()['symbol'] }}</span>
                        </div>
                    </x-form.field>

                    @if ($tvaActive)
                        <x-form.field :label="__('Taux de TVA (%)')" name="vat_rate">
                            <input type="number" step="0.01" wire:model.live.debounce.400ms="vat_rate" :disabled="$is_vat_exempt"
                                   class="champ w-full text-right tabular-nums">
                        </x-form.field>

                        <div class="flex items-end pb-2">
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" wire:model.live="is_vat_exempt"
                                       class="case">
                                {{ __('Produit exonéré de TVA') }}
                            </label>
                        </div>
                    @endif
                </div>

                <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
                    @if ($decomposition['tva'] > 0)
                        {{ __('Dont TVA :tva — prix hors taxe :ht.', ['tva' => \App\Support\Money::format($decomposition['tva']), 'ht' => \App\Support\Money::format($decomposition['ht'])]) }}
                    @elseif (! $tvaActive)
                        {{ __('TVA désactivée dans les paramètres : le prix de vente est aussi le prix hors taxe.') }}
                    @else
                        {{ __('Produit exonéré : aucune TVA sur ce prix.') }}
                    @endif
                </p>
            </x-form.card>
        @endif

        {{-- ============ STOCK ET PÉREMPTION ============ --}}
        <x-form.card icon="calendar-clock" :title="__('Seuils et péremption')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field :label="__('Seuil d\'alerte')" name="alert_quantity" required
                              :hint="__('Sous cette quantité, une alerte « stock bas » s\'ouvre automatiquement.')">
                    <input type="text" wire:model="alert_quantity" inputmode="decimal"
                           class="champ w-full text-right tabular-nums">
                </x-form.field>

                <x-form.field :label="__('Quantité à recommander')" name="reorder_quantity"
                              :hint="__('Quantité conseillée dans la liste de réapprovisionnement.')">
                    <input type="text" wire:model="reorder_quantity" inputmode="decimal"
                           class="champ w-full text-right tabular-nums">
                </x-form.field>

                <div class="sm:col-span-2 space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" wire:model.live="is_perishable"
                               class="case">
                        {{ __('Produit périssable (suivi des dates limites)') }}
                    </label>

                    @if ($is_perishable)
                        <x-form.field :label="__('Se conserve combien de jours ?')" name="shelf_life_days"
                                      :hint="__('Pour les produits frais sans date imprimée (oignons, piment, pain…) : la date limite est calculée à la réception. Vaut aussi pour les lots déjà en stock sans date.')">
                            <div class="flex items-center gap-2">
                                <input type="number" wire:model="shelf_life_days" min="1" max="730"
                                       class="champ w-28 text-right tabular-nums">
                                <span class="text-sm text-slate-500">{{ __('jours après réception') }}</span>
                            </div>
                        </x-form.field>

                        <x-form.field :label="__('Alerter combien de jours avant la péremption ?')" name="expiry_alert_days"
                                      :hint="__('Laissez vide pour utiliser le réglage général (:n jours).', ['n' => config('naneu.stock.expiry_alert_days')])">
                            <input type="number" wire:model="expiry_alert_days" min="1" max="365"
                                   placeholder="{{ config('naneu.stock.expiry_alert_days') }}"
                                   class="champ w-40 text-right tabular-nums">
                        </x-form.field>
                    @endif

                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" wire:model="is_quick_item"
                               class="case">
                        {{ __('Afficher en vignette rapide à la caisse (produit sans code-barres)') }}
                    </label>

                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" wire:model="is_active"
                               class="case">
                        {{ __('Produit actif') }}
                    </label>
                </div>
            </div>
        </x-form.card>

        {{-- ============ IMAGE ============ --}}
        <x-form.card icon="image" :title="__('Photo du produit')"
                     :description="__('Stockée sur MinIO. Utile pour les vignettes de caisse.')">

            <div class="flex flex-wrap items-center gap-4">
                @if ($image)
                    <img src="{{ $image->temporaryUrl() }}" alt=""
                         class="h-24 w-24 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                @elseif ($product?->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}"
                         class="h-24 w-24 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                @else
                    <div class="flex h-24 w-24 items-center justify-center rounded-lg border border-dashed border-slate-300 text-slate-300 dark:border-slate-700">
                        <x-icon name="cube" class="h-8 w-8" />
                    </div>
                @endif

                <div class="flex-1">
                    <input type="file" wire:model="image" accept="image/*"
                           class="champ-fichier">

                    <p class="mt-1 text-xs text-slate-400">
                        {{ __('JPG, PNG ou WebP — :n Ko maximum.', ['n' => config('naneu.storage.max_image_kb')]) }}
                    </p>

                    @error('image')
                        <p class="mt-1 text-xs text-rose-600">{{ __($message) }}</p>
                    @enderror

                    <div wire:loading wire:target="image" class="mt-1 text-xs text-marque-600">{{ __('Envoi en cours…') }}</div>
                </div>
            </div>
        </x-form.card>

        <div class="barre-actions justify-end">
            <a href="{{ route('produits.index') }}" wire:navigate
               class="btn-secondaire">
                {{ __('Annuler') }}
            </a>

            <button type="submit" wire:loading.attr="disabled"
                    class="btn-primaire">
                <span wire:loading.remove wire:target="enregistrer">{{ __('Enregistrer') }}</span>
                <span wire:loading wire:target="enregistrer">{{ __('Enregistrement…') }}</span>
            </button>
        </div>
    </form>
</div>
