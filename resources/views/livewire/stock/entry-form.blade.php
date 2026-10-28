@use('App\Support\Money')

<div class="space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="entrees" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Nouvelle entrée de stock') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Bon') }} {{ $reference }} ({{ __('provisoire') }}) — {{ __('chaque ligne crée un lot distinct, base du calcul FIFO.') }}
                </p>
            </div>
        </div>

        <x-back-button :href="route('entrees.index')">{{ __('Retour aux entrées') }}</x-back-button>
    </div>

    <form wire:submit="enregistrer" class="space-y-5">

        {{-- ============ EN-TÊTE DU BON ============ --}}
        <x-form.card icon="file-text" :title="__('Informations du bon')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-form.field :label="__('Fournisseur')" name="supplier_id">
                    <select wire:model="supplier_id"
                            class="champ w-full">
                        <option value="">—</option>
                        @foreach ($fournisseurs as $fournisseur)
                            <option value="{{ $fournisseur->id }}">{{ $fournisseur->name }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field :label="__('Date de réception')" name="entry_date" required>
                    <input type="date" wire:model="entry_date" max="{{ now()->toDateString() }}"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('N° facture fournisseur')" name="invoice_number">
                    <input type="text" wire:model="invoice_number"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Bon de livraison scanné')" name="document"
                              :hint="__('PDF ou photo, 5 Mo max.')">
                    <input type="file" wire:model="document" accept=".pdf,image/*"
                           class="champ-fichier">
                </x-form.field>
            </div>
        </x-form.card>

        {{-- ============ AJOUT DE PRODUIT ============ --}}
        <x-form.card icon="package-plus" :title="__('Produits reçus')"
                     :description="__('Scannez le code-barres ou cherchez par nom. Le prix d\'achat est celui de CETTE livraison.')">

            <div class="relative" x-data="{ ouvert: false }" @click.outside="ouvert = false">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />

                <input type="text"
                       wire:model.live.debounce.250ms="recherche"
                       wire:keydown.enter.prevent="scanner"
                       @focus="ouvert = true"
                       @input="ouvert = true"
                       placeholder="{{ __('Scanner un code-barres ou taper un nom de produit…') }}"
                       autocomplete="off"
                       class="champ w-full pl-9 pr-3">

                @if ($resultats->isNotEmpty())
                    <div x-show="ouvert" x-transition
                         class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
                        @foreach ($resultats as $produit)
                            <button type="button"
                                    wire:key="res-{{ $produit->id }}"
                                    wire:click="ajouterProduit({{ $produit->id }})"
                                    @click="ouvert = false"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $produit->name }}
                                    </span>
                                    <span class="block text-xs text-slate-400">
                                        {{ $produit->sku }}@if ($produit->barcode) · {{ $produit->barcode }}@endif
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-slate-400">
                                    {{ __('stock') }} {{ rtrim(rtrim(\App\Support\Money::number((float) $produit->quantity_on_hand, 3), '0'), ',.') }}
                                    {{ $produit->unit?->symbol }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @error('lignes')
                <p class="mt-2 text-sm font-medium text-rose-600 dark:text-rose-400">{{ __($message) }}</p>
            @enderror

            {{-- ============ LIGNES ============ --}}
            @if ($lignes)
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-entete">
                            <tr class="text-xs font-bold uppercase tracking-wide text-entete-fonce [&>th:first-child]:pl-3 [&>th:last-child]:pr-3">
                                <th class="py-2 text-left">{{ __('Produit') }}</th>
                                <th class="py-2 text-right">{{ __('Quantité') }}</th>
                                <th class="py-2 text-right">{{ __('Prix d\'achat unitaire') }}</th>
                                <th class="py-2 text-left">{{ __('Péremption') }}</th>
                                <th class="py-2 text-left">{{ __('N° de lot') }}</th>
                                <th class="py-2 text-right">{{ __('Total') }}</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($lignes as $index => $ligne)
                                @php
                                    $quantite = (float) str_replace(',', '.', (string) $ligne['quantity']);
                                    $prix = Money::parse($ligne['purchase_price']);
                                @endphp

                                <tr wire:key="ligne-{{ $index }}-{{ $ligne['product_id'] }}">
                                    <td class="py-2 pr-3">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $ligne['nom'] }}</p>
                                        <p class="text-xs text-slate-400">{{ $ligne['sku'] }}</p>
                                    </td>

                                    <td class="py-2 pr-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <input type="text"
                                                   wire:model.live.debounce.400ms="lignes.{{ $index }}.quantity"
                                                   inputmode="decimal"
                                                   class="champ-sm w-24 text-right tabular-nums">
                                            <span class="w-8 text-xs text-slate-400">{{ $ligne['unite'] }}</span>
                                        </div>
                                        @error("lignes.{$index}.quantity")
                                            <p class="mt-0.5 text-right text-xs text-rose-600">{{ __($message) }}</p>
                                        @enderror
                                    </td>

                                    <td class="py-2 pr-3">
                                        <input type="text"
                                               wire:model.live.debounce.400ms="lignes.{{ $index }}.purchase_price"
                                               inputmode="numeric"
                                               placeholder="{{ __('Prix') }}"
                                               aria-label="{{ __('Prix d\'achat unitaire') }}"
                                               class="champ-sm w-28 text-right tabular-nums">
                                        @error("lignes.{$index}.purchase_price")
                                            <p class="mt-0.5 text-right text-xs text-rose-600">{{ __($message) }}</p>
                                        @enderror
                                    </td>

                                    <td class="py-2 pr-3">
                                        @if ($ligne['perissable'])
                                            <input type="date"
                                                   wire:model="lignes.{{ $index }}.expiry_date"
                                                   min="{{ \Illuminate\Support\Carbon::parse($entry_date ?: now())->addDay()->toDateString() }}"
                                                   class="champ-sm">
                                            @if (blank($ligne['expiry_date']) && ! empty($ligne['conservation']))
                                                <p class="mt-0.5 text-xs text-slate-400">
                                                    {{ __('Vide : :date (conservation :n j)', ['date' => \Illuminate\Support\Carbon::parse($entry_date ?: now())->addDays($ligne['conservation'])->format('d/m/Y'), 'n' => $ligne['conservation']]) }}
                                                </p>
                                            @endif
                                            @error("lignes.{$index}.expiry_date")
                                                <p class="mt-0.5 text-xs text-rose-600">{{ __($message) }}</p>
                                            @enderror
                                        @else
                                            <span class="text-xs text-slate-400">{{ __('non périssable') }}</span>
                                        @endif
                                    </td>

                                    <td class="py-2 pr-3">
                                        <input type="text"
                                               wire:model="lignes.{{ $index }}.batch_number"
                                               placeholder="{{ __('auto') }}"
                                               class="champ-sm w-28">
                                    </td>

                                    <td class="py-2 pr-3 text-right text-sm font-medium tabular-nums text-slate-900 dark:text-white">
                                        {{ Money::format((int) round($quantite * $prix)) }}
                                    </td>

                                    <td class="py-2 text-right">
                                        <button type="button" wire:click="retirerLigne({{ $index }})"
                                                class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr class="border-t-2 border-slate-200 dark:border-slate-700">
                                <td colspan="5" class="py-3 text-right text-sm font-medium text-slate-600 dark:text-slate-300">
                                    {{ __('Total du bon') }}
                                </td>
                                <td class="py-3 text-right text-lg font-semibold tabular-nums text-slate-900 dark:text-white">
                                    {{ Money::format($total) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <x-empty-state illustration="entrees" :message="__('Aucun produit dans ce bon.')"
                               :hint="__('Scannez un code-barres ou cherchez par nom ci-dessus.')"
                               class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 py-10 dark:border-slate-700 dark:bg-slate-800/30" />
            @endif
        </x-form.card>

        <x-form.card icon="truck" :title="__('Frais de transport')"
                     :description="__('Taxi, moto ou camion payé pour ramener cette marchandise. Facultatif : compté dans la Comptabilité, sans changer le prix d’achat des produits.')">
            <x-form.field name="transport">
                <div class="relative max-w-xs">
                    <input type="text" inputmode="numeric" wire:model.live.debounce.400ms="transport" id="transport" placeholder="0"
                           class="champ w-full pr-16 text-right tabular-nums">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400">{{ Money::currency()['symbol'] }}</span>
                </div>
            </x-form.field>
        </x-form.card>

        <x-form.card icon="notebook-pen" :title="__('Note interne')">
            <textarea wire:model="note" rows="2" placeholder="{{ __('Observation sur la livraison, état des cartons…') }}"
                      class="champ w-full"></textarea>
        </x-form.card>

        <div class="barre-actions">
            <div class="flex flex-wrap gap-8">
                <div>
                    <p class="libelle">{{ __('Lignes') }}</p>
                    <p class="valeur">{{ count($lignes) }}</p>
                </div>
                <div>
                    <p class="libelle">{{ __('Total du bon') }}</p>
                    <p class="valeur">{{ Money::format($total) }}</p>
                </div>
                @if (Money::parse($transport) > 0)
                    <div>
                        <p class="libelle">{{ __('+ Transport') }}</p>
                        <p class="valeur">{{ Money::format(Money::parse($transport)) }}</p>
                    </div>
                @endif
            </div>

            <div class="flex gap-3">
                <a href="{{ route('entrees.index') }}" wire:navigate
                   class="btn-secondaire">
                    {{ __('Annuler') }}
                </a>

                <button type="submit" wire:loading.attr="disabled" @disabled(! $lignes)
                        class="btn-primaire">
                    <span wire:loading.remove wire:target="enregistrer">{{ __('Valider l\'entrée') }}</span>
                    <span wire:loading wire:target="enregistrer">{{ __('Enregistrement…') }}</span>
                </button>
            </div>
        </div>
    </form>
</div>
