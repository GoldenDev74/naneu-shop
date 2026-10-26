{{--
    Pile de messages éphémères. Alimentée depuis PHP par
    $this->dispatch('toast', type: 'succes', message: '…') dans n'importe
    quel composant Livewire, et depuis JavaScript par les évènements
    poussés par Reverb.
--}}
<div x-data="{
        messages: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.messages.push({ id, ...detail });
            setTimeout(() => this.remove(id), detail.duree ?? 5000);
        },
        remove(id) {
            this.messages = this.messages.filter(m => m.id !== id);
        },
     }"
     @toast.window="add($event.detail)"
     class="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-full max-w-sm flex-col gap-2">

    <template x-for="message in messages" :key="message.id">
        <div x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-end="opacity-0"
             class="pointer-events-auto flex items-start gap-3 rounded-lg border bg-white p-3 shadow-lg dark:bg-slate-800"
             :class="{
                'border-emerald-200 dark:border-emerald-800': message.type === 'succes',
                'border-rose-200 dark:border-rose-800': message.type === 'erreur',
                'border-amber-200 dark:border-amber-800': message.type === 'alerte',
                'border-slate-200 dark:border-slate-700': !['succes','erreur','alerte'].includes(message.type),
             }">

            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                  :class="{
                    'bg-emerald-500': message.type === 'succes',
                    'bg-rose-500': message.type === 'erreur',
                    'bg-amber-500': message.type === 'alerte',
                    'bg-slate-400': !['succes','erreur','alerte'].includes(message.type),
                  }"></span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-900 dark:text-white" x-text="message.titre" x-show="message.titre"></p>
                <p class="text-sm text-slate-600 dark:text-slate-300" x-text="message.message"></p>
            </div>

            <button @click="remove(message.id)"
                    class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
    </template>
</div>
