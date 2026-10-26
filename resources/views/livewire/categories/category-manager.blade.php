<div class="space-y-4">

    <div class="flex items-center gap-4">
        <x-illustration name="categories" />
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Catégories') }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Organisation du catalogue. Deux niveaux au maximum pour rester lisible en rayon.') }}
            </p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">

        {{-- ============ FORMULAIRE ============ --}}
        @if ($peutModifier)
        <div class="lg:col-span-1">
            <form wire:submit="enregistrer"
                  class="space-y-4 rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux dark:border-slate-800 dark:bg-slate-900">

                <div class="-mx-5 -mt-5 mb-5 flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-marque-50 text-marque-700 ring-1 ring-inset ring-marque-100 dark:bg-marque-900/40 dark:text-marque-300 dark:ring-marque-800/60">
                        <x-icon name="tag" class="h-5 w-5" />
                    </span>
                    <h2 class="font-display text-base font-bold text-slate-900 dark:text-white">
                        {{ $enEdition ? __('Modifier la catégorie') : __('Nouvelle catégorie') }}
                    </h2>
                </div>

                <x-form.field :label="__('Nom')" name="name" required>
                    <input type="text" wire:model="name"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Catégorie parente')" name="parent_id">
                    <select wire:model="parent_id"
                            class="champ w-full">
                        <option value="">{{ __('Aucune (catégorie principale)') }}</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field :label="__('Couleur')" name="color">
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model="color"
                               class="h-9 w-12 cursor-pointer rounded border-slate-300 dark:border-slate-700">
                        <input type="text" wire:model="color"
                               class="champ w-28 font-mono">
                    </div>
                </x-form.field>

                <x-form.field :label="__('Description')" name="description">
                    <textarea wire:model="description" rows="2"
                              class="champ w-full"></textarea>
                </x-form.field>

                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" wire:model="is_active"
                           class="case">
                    {{ __('Catégorie active') }}
                </label>

                @if ($enEdition && $gereTva && $enCours)
                    @php
                        $ids = $enCours->selfAndDescendantIds();
                        $nb = \App\Models\Product::whereIn('category_id', $ids)->count();
                        $nbEx = \App\Models\Product::whereIn('category_id', $ids)->where('is_vat_exempt', true)->count();
                    @endphp
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ __('TVA des produits du rayon') }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ __(':ex exonéré(s) sur :n produit(s), sous-catégories comprises.', ['ex' => $nbEx, 'n' => $nb]) }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" wire:click="tvaRayon({{ $enEdition }}, true)" @disabled($nbEx === $nb)
                                    class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-marque-400 hover:text-marque-700 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200">
                                {{ __('Tout exonérer') }}
                            </button>
                            <button type="button" wire:click="tvaRayon({{ $enEdition }}, false)" @disabled($nbEx === 0)
                                    class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-marque-400 hover:text-marque-700 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200">
                                {{ __('Tout soumettre à la TVA') }}
                            </button>
                        </div>
                    </div>
                @endif

                <div class="flex gap-2 pt-1">
                    <button type="submit"
                            class="btn-primaire flex-1">
                        {{ $enEdition ? __('Mettre à jour') : __('Créer') }}
                    </button>

                    @if ($enEdition)
                        <button type="button" wire:click="annuler"
                                class="btn-secondaire">
                            {{ __('Annuler') }}
                        </button>
                    @endif
                </div>
            </form>
        </div>
        @endif

        {{-- ============ LISTE ============ --}}
        <div class="{{ $peutModifier ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-entete">
                        <tr>
                            <x-table.th filter="categorie">{{ __('Catégorie') }}</x-table.th>
                            <x-table.th filter="parent">{{ __('Rattachée à') }}</x-table.th>
                            <x-table.th align="right">{{ __('Produits') }}</x-table.th>
                            <x-table.th align="right">{{ __('Actions') }}</x-table.th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($categories as $categorie)
                            <tr wire:key="cat-{{ $categorie->id }}"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/40 {{ $categorie->is_active ? '' : 'opacity-50' }}">

                                <td class="px-4 py-2.5">
                                    <span class="inline-flex items-center gap-2" style="padding-left: {{ $categorie->profondeur * 1.5 }}rem">
                                        @if ($categorie->profondeur > 0)
                                            <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">└</span>
                                        @endif
                                        <span class="h-3 w-3 rounded-full" style="background: {{ $categorie->color }}"></span>
                                        <span class="{{ $categorie->profondeur > 0 ? 'text-slate-700 dark:text-slate-200' : 'font-medium text-slate-900 dark:text-white' }}">{{ $categorie->name }}</span>
                                    </span>
                                </td>

                                <td class="px-4 py-2.5 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $categorie->parent?->name ?? '—' }}
                                </td>

                                <td class="px-4 py-2.5 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ $categorie->products_count }}
                                    @if ($categorie->exoneres_count > 0)
                                        <p class="text-[11px] text-slate-400">{{ __(':n sans TVA', ['n' => $categorie->exoneres_count]) }}</p>
                                    @endif
                                </td>

                                <td class="px-4 py-2.5">
                                    <div class="flex justify-end gap-1">
                                        @if ($peutModifier)
                                            <button wire:click="editer({{ $categorie->id }})" title="{{ __('Modifier') }}"
                                                    class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                                <x-icon name="pencil" class="h-4 w-4" />
                                            </button>
                                        @else
                                            <span class="text-xs text-slate-400">{{ __('Lecture seule') }}</span>
                                        @endif

                                        @if ($peutSupprimer)
                                            <button wire:click="supprimer({{ $categorie->id }})"
                                                    wire:confirm="Supprimer la catégorie « {{ $categorie->name }} » ?"
                                                    class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30">
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                    <x-empty-state illustration="categories" :message="__('Aucune catégorie. Créez-en une pour commencer.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
