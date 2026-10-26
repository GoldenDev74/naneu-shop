{{--
    Le wire:poll de 60 s est un filet : Reverb pousse normalement les
    notifications en temps réel, mais si le WebSocket tombe, la cloche
    continue de se mettre à jour au lieu de rester silencieuse.
--}}
<div class="relative" wire:poll.60s x-data="{ ouvert: @entangle('ouvert') }">

    <button @click="ouvert = !ouvert" @click.outside="ouvert = false"
            class="relative rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
            aria-label="{{ __('Notifications') }}">
        <x-icon name="bell" class="h-5 w-5" />

        @if ($nonLues > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white">
                {{ $nonLues > 9 ? '9+' : $nonLues }}
            </span>
        @endif
    </button>

    <div x-show="ouvert" x-transition style="display:none"
         class="absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800">

        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5 dark:border-slate-700">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Notifications') }}</p>

            @if ($nonLues > 0)
                <button wire:click="toutMarquerLu"
                        class="text-xs font-medium text-marque-600 hover:underline dark:text-marque-400">
                    {{ __('Tout marquer comme lu') }}
                </button>
            @endif
        </div>

        <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
            @forelse ($notifications as $notification)
                @php
                    $donnees = $notification->data;
                    $gravite = $donnees['gravite'] ?? 'info';
                @endphp

                <div wire:key="notif-{{ $notification->id }}"
                     class="flex gap-3 px-4 py-3 {{ $notification->read_at ? '' : 'bg-marque-50/50 dark:bg-marque-900/10' }}">

                    <span @class([
                        'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                        'bg-rose-500'   => $gravite === 'critique',
                        'bg-amber-500'  => $gravite === 'avertissement',
                        'bg-slate-400'  => ! in_array($gravite, ['critique', 'avertissement']),
                    ])></span>

                    <div class="min-w-0 flex-1">
                        @if (! empty($donnees['type_label']))
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {{ $donnees['type_label'] }}
                            </p>
                        @endif

                        <p class="text-sm text-slate-700 dark:text-slate-200">
                            {{ $donnees['message'] ?? __('Notification') }}
                        </p>

                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-xs text-slate-400">
                                {{ $notification->created_at->diffForHumans() }}
                            </span>

                            @php $lien = $this->lien($donnees['url'] ?? null); @endphp

                            @if ($lien)
                                <a href="{{ $lien }}" wire:navigate
                                   class="text-xs font-medium text-marque-600 hover:underline dark:text-marque-400">
                                    {{ __('Ouvrir') }}
                                </a>
                            @endif

                            @if (! $notification->read_at)
                                <button wire:click="marquerLue('{{ $notification->id }}')"
                                        class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                    {{ __('Marquer lue') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-10 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Aucune notification.') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Les alertes de stock apparaîtront ici.') }}</p>
                </div>
            @endforelse
        </div>

        @if (auth()->user()->canAccess('alertes'))
        {{-- Chemin relatif, comme les liens de notification : la cloche ne
             doit jamais faire changer d'origine au navigateur. --}}
        <a href="{{ route('alertes.index', absolute: false) }}" wire:navigate
           class="block border-t border-slate-100 px-4 py-2.5 text-center text-sm font-medium text-marque-600 hover:bg-slate-50 dark:border-slate-700 dark:text-marque-400 dark:hover:bg-slate-700/50">
            {{ __('Voir toutes les alertes') }}
        </a>
        @endif
    </div>
</div>
