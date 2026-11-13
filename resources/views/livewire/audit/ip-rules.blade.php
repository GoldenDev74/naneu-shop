<div class="rounded-2xl border border-slate-200/70 bg-white shadow-doux dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Adresses IP') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Bloquez une adresse suspecte, ou autorisez celle d\'un membre de l\'équipe en déplacement hors du Togo. Votre adresse actuelle : :ip', ['ip' => $votreIp]) }}
            </p>
        </div>
        <x-secondary-button type="button" wire:click="$toggle('formulaireOuvert')">
            <x-icon name="plus" class="h-4 w-4" />
            {{ __('Ajouter une règle') }}
        </x-secondary-button>
    </div>

    @if ($formulaireOuvert)
        <form wire:submit="enregistrer" class="grid gap-3 border-t border-slate-200 px-4 py-3 sm:grid-cols-[1fr_auto_1fr_auto_auto] sm:items-start dark:border-slate-800">
            <div>
                <input type="text" wire:model="ip" placeholder="{{ __('ex. 102.64.12.8 ou 102.64.12.0/24') }}" class="champ w-full font-mono" aria-label="{{ __('Adresse IP') }}">
                <x-input-error :messages="$errors->get('ip')" class="mt-1" />
            </div>
            <select wire:model="type" class="champ" aria-label="{{ __('Type de règle') }}">
                <option value="bloquee">{{ __('Bloquer') }}</option>
                <option value="autorisee">{{ __('Autoriser (hors Togo)') }}</option>
            </select>
            <div>
                <input type="text" wire:model="note" placeholder="{{ __('Motif (facultatif)') }}" class="champ w-full" aria-label="{{ __('Motif') }}">
                <x-input-error :messages="$errors->get('note')" class="mt-1" />
            </div>
            <div>
                <input type="number" min="1" wire:model="duree" placeholder="{{ __('Jours (vide = toujours)') }}" class="champ w-full sm:w-44" aria-label="{{ __('Durée en jours') }}">
                <x-input-error :messages="$errors->get('duree')" class="mt-1" />
            </div>
            <x-primary-button type="submit">{{ __('Enregistrer') }}</x-primary-button>
        </form>
    @endif

    @if ($regles->isNotEmpty())
        <ul class="divide-y divide-slate-100 border-t border-slate-200 dark:divide-slate-800 dark:border-slate-800">
            @foreach ($regles as $regle)
                <li wire:key="ip-regle-{{ $regle->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-sm">
                    <span @class([
                        'rounded px-2 py-0.5 text-xs font-medium',
                        'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' => $regle->type === 'bloquee',
                        'bg-marque-100 text-marque-700 dark:bg-marque-900/40 dark:text-marque-300' => $regle->type === 'autorisee',
                    ])>{{ $regle->type === 'bloquee' ? __('Bloquée') : __('Autorisée') }}</span>
                    <span class="font-mono text-slate-800 dark:text-slate-100">{{ $regle->ip }}</span>
                    <span class="text-slate-500 dark:text-slate-400">{{ $regle->note }}</span>
                    <span class="ml-auto text-xs text-slate-400">
                        {{ $regle->expires_at ? __('jusqu\'au :date', ['date' => $regle->expires_at->format('d/m/Y H:i')]) : __('sans limite') }}
                        · {{ $regle->auteur?->name ?? '—' }}
                    </span>
                    <button type="button" wire:click="supprimer({{ $regle->id }})" wire:confirm="{{ __('Retirer cette règle ?') }}"
                            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800"
                            title="{{ __('Retirer') }}" aria-label="{{ __('Retirer') }}">
                        <x-icon name="trash-2" class="h-4 w-4" />
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="border-t border-slate-200 px-4 py-2 text-[11px] text-slate-400 dark:border-slate-800">
        {{ __('Géolocalisation des adresses :') }}
        <a href="https://db-ip.com" target="_blank" rel="noopener" class="underline hover:text-slate-600">IP Geolocation by DB-IP</a>
    </p>
</div>
