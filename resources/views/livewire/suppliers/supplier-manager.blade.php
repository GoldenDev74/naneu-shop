<div class="space-y-4">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <x-illustration name="fournisseurs" />
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Fournisseurs') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                    {{ $fournisseurs->total() }} {{ __('fournisseur(s) — locaux et importateurs') }}
                </p>
            </div>
        </div>

        @if ($peutModifier)
            <button wire:click="nouveau"
                    class="btn-primaire">
                <x-icon name="plus" class="h-4 w-4" /> {{ __('Nouveau fournisseur') }}
            </button>
        @endif
    </div>

    {{-- ============ PANNEAU D'ÉDITION ============ --}}
    @if ($panneau && $peutModifier)
        <form wire:submit="enregistrer"
              class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-doux dark:border-slate-800 dark:bg-slate-900">

            <div class="-mx-5 -mt-5 mb-5 flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-marque-50 text-marque-700 ring-1 ring-inset ring-marque-100 dark:bg-marque-900/40 dark:text-marque-300 dark:ring-marque-800/60">
                    <x-icon name="truck" class="h-5 w-5" />
                </span>
                <h2 class="font-display text-base font-bold text-slate-900 dark:text-white">
                    {{ $enEdition ? __('Modifier le fournisseur') : __('Nouveau fournisseur') }}
                </h2>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-form.field :label="__('Nom')" name="name" required>
                    <input type="text" wire:model="name"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Personne à contacter')" name="contact_name">
                    <input type="text" wire:model="contact_name"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Téléphone')" name="phone">
                    <input type="tel" wire:model="phone" placeholder="+228 90 12 34 56"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('E-mail')" name="email">
                    <input type="email" wire:model="email"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Adresse')" name="address">
                    <input type="text" wire:model="address"
                           class="champ w-full">
                </x-form.field>

                <x-form.field :label="__('Ville')" name="city">
                    <input type="text" wire:model="city"
                           class="champ w-full">
                </x-form.field>

                <div class="sm:col-span-2 lg:col-span-3">
                    <x-form.field :label="__('Notes')" name="notes">
                        <textarea wire:model="notes" rows="2"
                                  placeholder="{{ __('Conditions de paiement, jours de livraison…') }}"
                                  class="champ w-full"></textarea>
                    </x-form.field>
                </div>

                <div class="flex items-center gap-4 sm:col-span-2 lg:col-span-3">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" wire:model="is_local"
                               class="case">
                        {{ __('Fournisseur local (Togo)') }}
                    </label>

                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" wire:model="is_active"
                               class="case">
                        {{ __('Fournisseur actif') }}
                    </label>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <button type="submit"
                        class="btn-primaire">
                    {{ $enEdition ? __('Mettre à jour') : __('Créer') }}
                </button>
                <button type="button" wire:click="$set('panneau', false)"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white dark:border-slate-700 dark:text-slate-200">
                    {{ __('Fermer') }}
                </button>
            </div>
        </form>
    @endif

    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <input type="search" wire:model.live.debounce.300ms="recherche"
               placeholder="{{ __('Nom, contact ou téléphone…') }}"
               class="champ w-full">
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th filter="fournisseur">{{ __('Fournisseur') }}</x-table.th>
                        <x-table.th filter="contact">{{ __('Contact') }}</x-table.th>
                        <x-table.th filter="ville">{{ __('Ville') }}</x-table.th>
                        <x-table.th align="right">{{ __('Livraisons') }}</x-table.th>
                        <x-table.th align="right">{{ __('Actions') }}</x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($fournisseurs as $fournisseur)
                        <tr wire:key="four-{{ $fournisseur->id }}"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/40 {{ $fournisseur->is_active ? '' : 'opacity-50' }}">

                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900 dark:text-white">{{ $fournisseur->name }}</span>
                                    @if ($fournisseur->is_local)
                                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            {{ __('local') }}
                                        </span>
                                    @endif
                                </div>
                                @if ($fournisseur->email)
                                    <p class="text-xs text-slate-400">{{ $fournisseur->email }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $fournisseur->contact_name ?: '—' }}
                                @if ($fournisseur->phone)
                                    <p class="text-xs text-slate-400">{{ $fournisseur->phone }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $fournisseur->city ?: '—' }}
                            </td>

                            <td class="px-4 py-3 text-right text-sm tabular-nums text-slate-600 dark:text-slate-300">
                                {{ $fournisseur->stock_entries_count }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    @unless ($peutModifier)
                                        <span class="text-xs text-slate-400">{{ __('Lecture seule') }}</span>
                                    @else
                                    <button wire:click="editer({{ $fournisseur->id }})"
                                            class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                        <x-icon name="pencil" class="h-4 w-4" />
                                    </button>
                                    <button wire:click="basculerActif({{ $fournisseur->id }})"
                                            title="{{ $fournisseur->is_active ? __('Désactiver') : __('Réactiver') }}"
                                            class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                        <x-icon :name="$fournisseur->is_active ? 'x' : 'check'" class="h-4 w-4" />
                                    </button>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                <x-empty-state illustration="fournisseurs" :message="__('Aucun fournisseur enregistré.')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($fournisseurs->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $fournisseurs->links() }}</div>
        @endif
    </div>
</div>
