<div class="space-y-4">

    <div class="flex items-center gap-4">
        <x-illustration name="journal" />
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Journal de sécurité') }}</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Connexions, créations de comptes, mouvements de stock. Table en ajout seul : aucune entrée n\'est modifiable depuis l\'application, et aucun mot de passe n\'y figure.') }}
            </p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux p-3 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap gap-2">
            <input type="search" wire:model.live.debounce.300ms="recherche"
                   placeholder="{{ __('E-mail, évènement ou adresse IP…') }}"
                   class="champ min-w-[14rem] flex-1">

            <select wire:model.live="evenement"
                    class="champ">
                <option value="">{{ __('Tous les évènements') }}</option>
                @foreach ($evenements as $ev)
                    <option value="{{ $ev }}">{{ $ev }}</option>
                @endforeach
            </select>

            <input type="date" wire:model.live="depuis"
                   class="champ">
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                <thead class="bg-entete">
                    <tr>
                        <x-table.th filter="date" filter-type="date">{{ __('Horodatage') }}</x-table.th>
                        <x-table.th filter="evenement" filter-type="select" :options="$evenements->combine($evenements)->all()">{{ __('Évènement') }}</x-table.th>
                        <x-table.th filter="compte">{{ __('Compte') }}</x-table.th>
                        <x-table.th>{{ __('Détails') }}</x-table.th>
                        <x-table.th filter="origine">{{ __('Origine') }}</x-table.th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($entrees as $entree)
                        @php
                            $estEchec = str_contains($entree->event, 'echouee') || str_contains($entree->event, 'bloquee');
                            $estSensible = str_starts_with($entree->event, 'utilisateur.')
                                || str_starts_with($entree->event, 'mot_de_passe.')
                                || str_starts_with($entree->event, 'parametres.');
                        @endphp

                        <tr wire:key="audit-{{ $entree->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">

                            <td class="whitespace-nowrap px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                                {{ $entree->created_at?->format('d/m/Y H:i:s') }}
                            </td>

                            <td class="px-4 py-2.5">
                                <span @class([
                                    'rounded px-2 py-0.5 font-mono text-xs',
                                    'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' => $estEchec,
                                    'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $estSensible && ! $estEchec,
                                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! $estEchec && ! $estSensible,
                                ])>
                                    {{ $entree->event }}
                                </span>
                            </td>

                            <td class="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300">
                                {{ $entree->user?->name ?? $entree->user_email ?? 'anonyme' }}
                            </td>

                            <td class="px-4 py-2.5">
                                @if ($entree->properties)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($entree->properties as $cle => $valeur)
                                            <span class="rounded bg-slate-50 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                {{ $cle }}:
                                                <span class="font-medium">{{ is_scalar($valeur) ? $valeur : json_encode($valeur) }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-4 py-2.5 font-mono text-xs text-slate-500 dark:text-slate-400">
                                {{ $entree->ip_address ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">
                                {{ __('Aucune entrée dans le journal.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($entrees->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $entrees->links() }}</div>
        @endif
    </div>
</div>
