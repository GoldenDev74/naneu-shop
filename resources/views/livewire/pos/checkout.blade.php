@use('App\Support\Money')

<div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_24rem] xl:grid-cols-[minmax(0,1fr)_26rem]"
     x-data x-on:caisse-prete.window="$nextTick(() => $refs.recherche?.focus())">

    {{-- ================= PRODUITS ================= --}}
    <section class="min-w-0 space-y-4">
        <div class="flex items-center gap-4">
            <x-illustration name="sorties" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Caisse') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ __('Touchez un produit ou scannez son code-barres. Le stock est mis à jour à l\'encaissement.') }}</p>
            </div>
        </div>

        <div class="relative">
            <x-icon name="barcode" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input type="search" x-ref="recherche" autofocus
                   wire:model.live.debounce.250ms="recherche" wire:keydown.enter.prevent="scanner"
                   placeholder="{{ __('Scanner ou chercher un produit…') }}"
                   class="champ w-full py-3 pl-11 text-base" aria-label="{{ __('Scanner ou chercher un produit') }}">
        </div>

        <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
            <button type="button" wire:click="$set('categorie', null)"
                    @class(['shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition',
                            'border-marque-500 bg-marque-500 text-white' => ! $categorie,
                            'border-slate-200 bg-white text-slate-600 hover:border-marque-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300' => $categorie])>
                {{ __('Tout') }}
            </button>
            @foreach ($categories as $cat)
                <button type="button" wire:key="cat-{{ $cat->id }}" wire:click="$set('categorie', {{ $cat->id }})"
                        @class(['inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition',
                                'border-marque-500 bg-marque-500 text-white' => $categorie === $cat->id,
                                'border-slate-200 bg-white text-slate-600 hover:border-marque-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300' => $categorie !== $cat->id])>
                    <span class="h-2 w-2 rounded-full" style="background: {{ $cat->color }}"></span>{{ $cat->name }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
            @forelse ($produits as $produit)
                @php $vendable = $produit->vendable > 1e-6; $image = $produit->imageUrl(); @endphp
                <button type="button" wire:key="p-{{ $produit->id }}" wire:click="ajouter({{ $produit->id }})"
                        @class(['group flex flex-col overflow-hidden rounded-2xl border bg-white text-left shadow-doux transition dark:bg-slate-900',
                                'border-slate-200/70 hover:-translate-y-0.5 hover:border-marque-300 dark:border-slate-800' => $vendable,
                                'border-slate-200/70 opacity-50 grayscale dark:border-slate-800' => ! $vendable])>
                    <div class="relative aspect-[16/10] w-full bg-marque-50 dark:bg-slate-800">
                        @if ($image)
                            <img src="{{ $image }}" alt="" loading="lazy" class="h-full w-full object-cover">
                        @else
                            <span class="flex h-full items-center justify-center font-display text-3xl font-bold text-marque-300">{{ mb_strtoupper(mb_substr($produit->name, 0, 1)) }}</span>
                        @endif
                        @if (! $vendable)
                            <span class="absolute left-2 top-2 rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-rose-700">
                                {{ $produit->perime > 1e-6 ? __('Périmé') : __('Rupture') }}
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col gap-1 p-2.5">
                        <span class="line-clamp-2 text-sm font-medium leading-tight text-slate-800 dark:text-slate-100">{{ $produit->name }}</span>
                        <span class="mt-auto flex items-baseline justify-between gap-2">
                            <span class="font-display text-base font-bold tabular-nums text-marque-700 dark:text-marque-300">{{ Money::format($produit->sale_price) }}</span>
                            <span class="text-[11px] tabular-nums text-slate-400">{{ rtrim(rtrim(Money::number((float) $produit->vendable, 3), '0'), ',.') }} {{ $produit->unit?->symbol }}</span>
                        </span>
                    </div>
                </button>
            @empty
                <div class="col-span-full">
                    <x-empty-state illustration="produits" :message="__('Aucun produit ne correspond.')" />
                </div>
            @endforelse
        </div>
    </section>

    {{-- ================= TICKET EN COURS ================= --}}
    <aside class="lg:sticky lg:top-4 lg:self-start">
        <div class="flex max-h-[calc(100vh-2rem)] flex-col overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <h2 class="font-display text-lg font-bold text-slate-900 dark:text-white">{{ __('Ticket en cours') }}</h2>
                @if ($panier)
                    <button type="button" wire:click="vider" class="text-xs font-medium text-slate-400 hover:text-rose-600">{{ __('Vider') }}</button>
                @endif
            </div>

            <ul class="min-h-[8rem] flex-1 divide-y divide-slate-100 overflow-y-auto px-5 dark:divide-slate-800">
                @forelse ($panier as $i => $ligne)
                    <li wire:key="ligne-{{ $ligne['product_id'] }}" class="py-3">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium leading-tight text-slate-900 dark:text-white">{{ $ligne['nom'] }}</p>
                            <button type="button" wire:click="retirer({{ $i }})" class="-mr-1 rounded-full p-1 text-slate-300 hover:bg-rose-50 hover:text-rose-600"
                                    aria-label="{{ __('Retirer :nom', ['nom' => $ligne['nom']]) }}">
                                <x-icon name="x" class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="moins({{ $i }})" class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-600 hover:border-marque-300 dark:border-slate-700 dark:text-slate-300" aria-label="{{ __('Moins') }}">−</button>
                                <input type="text" inputmode="decimal" wire:model.blur="panier.{{ $i }}.quantite"
                                       class="champ-sm w-14 text-center tabular-nums" aria-label="{{ __('Quantité') }}">
                                <button type="button" wire:click="plus({{ $i }})" class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-600 hover:border-marque-300 dark:border-slate-700 dark:text-slate-300" aria-label="{{ __('Plus') }}">+</button>
                                <span class="ml-1 text-xs text-slate-400">{{ $ligne['unite'] }} × {{ Money::format($ligne['prix']) }}</span>
                            </div>
                            <span class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
                                {{ Money::format((int) round((float) str_replace(',', '.', $ligne['quantite']) * $ligne['prix'])) }}
                            </span>
                        </div>
                    </li>
                @empty
                    <li class="py-10 text-center text-sm text-slate-400">{{ __('Aucun article. Touchez un produit pour commencer.') }}</li>
                @endforelse
            </ul>

            <div class="space-y-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4 dark:border-slate-800 dark:bg-slate-800/30">
                <div class="flex items-baseline justify-between">
                    <span class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Total à payer') }}</span>
                    <span class="font-display text-3xl font-extrabold tabular-nums text-slate-900 dark:text-white">{{ Money::format($total) }}</span>
                </div>

                <div>
                    <label for="remis" class="sans-tuile mb-1 block text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Montant remis par le client') }}</label>
                    <input id="remis" type="text" inputmode="numeric" wire:model.live.debounce.200ms="remis" wire:keydown.enter="encaisser"
                           placeholder="0" @class(['champ w-full text-right font-display text-xl font-bold tabular-nums', 'champ-erreur' => $errors->has('remis')])>
                    @error('remis') <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p> @enderror

                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button type="button" wire:click="remettre('exact')" @disabled(! $total)
                                class="rounded-full border border-marque-300 bg-marque-50 px-2.5 py-1 text-xs font-semibold text-marque-800 disabled:opacity-40 dark:border-marque-800 dark:bg-marque-900/30 dark:text-marque-200">{{ __('Compte juste') }}</button>
                        @foreach ([500, 1000, 2000, 5000, 10000] as $billet)
                            <button type="button" wire:click="remettre({{ $billet }})"
                                    class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold tabular-nums text-slate-700 hover:border-marque-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">+{{ Money::format($billet, false) }}</button>
                        @endforeach
                        @if ($remisMontant)
                            <button type="button" wire:click="$set('remis', '')" class="px-1.5 text-xs text-slate-400 hover:text-slate-700">{{ __('Effacer') }}</button>
                        @endif
                    </div>
                </div>

                <div @class(['flex items-baseline justify-between rounded-2xl px-4 py-3',
                             'bg-marque-600 text-white' => $total && $remisMontant >= $total,
                             'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! ($total && $remisMontant >= $total)])>
                    <span class="text-sm font-medium">{{ $remisMontant && $remisMontant < $total ? __('Il manque') : __('Monnaie à rendre') }}</span>
                    <span class="font-display text-2xl font-extrabold tabular-nums">
                        {{ Money::format($remisMontant && $remisMontant < $total ? $total - $remisMontant : $monnaie) }}
                    </span>
                </div>

                <button type="button" wire:click="encaisser" wire:loading.attr="disabled" wire:target="encaisser"
                        @disabled(! $panier || $remisMontant < $total)
                        class="btn-primaire w-full justify-center py-3 text-base disabled:cursor-not-allowed disabled:opacity-40">
                    <x-icon name="check" class="h-5 w-5" />
                    <span wire:loading.remove wire:target="encaisser">{{ __('Encaisser') }}</span>
                    <span wire:loading wire:target="encaisser">{{ __('Encaissement…') }}</span>
                </button>
            </div>
        </div>
    </aside>

    {{-- ================= VENTE ENCAISSÉE ================= --}}
    @if ($vente)
        <x-popup close="nouvelleVente" :titre="__('Vente encaissée')" largeur="max-w-md">
            <div class="py-2 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-marque-100 text-marque-700 dark:bg-marque-900/40 dark:text-marque-300">
                    <x-icon name="check" class="h-7 w-7" />
                </span>
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ __('Ticket :ref — :total encaissés', ['ref' => $vente->reference, 'total' => Money::format($vente->total_sale)]) }}</p>
                <p class="mt-4 text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Monnaie à rendre') }}</p>
                <p class="font-display text-5xl font-extrabold tabular-nums text-marque-700 dark:text-marque-300">{{ Money::format($vente->change_given) }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('Remis : :montant', ['montant' => Money::format($vente->amount_tendered)]) }}</p>
            </div>

            <x-slot:pied>
                <a href="{{ route('caisse.ticket.pdf', $vente) }}" target="_blank" class="btn-secondaire">
                    <x-icon name="file-down" class="h-4 w-4" /> {{ __('PDF') }}
                </a>
                <a href="{{ route('caisse.ticket', $vente) }}" target="_blank" class="btn-secondaire">
                    <x-icon name="printer" class="h-4 w-4" /> {{ __('Imprimer') }}
                </a>
                <button type="button" wire:click="nouvelleVente" class="btn-primaire" autofocus>
                    {{ __('Nouvelle vente') }}
                </button>
            </x-slot:pied>
        </x-popup>
    @endif
</div>
