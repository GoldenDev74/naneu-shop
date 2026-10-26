{{-- Bascule de langue : un formulaire par langue, la langue active est marquée. --}}
<div {{ $attributes->merge(['class' => 'inline-flex rounded-lg border border-slate-200 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-900']) }}
     role="group" aria-label="{{ __('Langue') }}">
    @foreach (config('naneu.locales') as $code => $nom)
        <form method="POST" action="{{ route('langue', $code) }}">
            @csrf
            <button type="submit" title="{{ $nom }}" lang="{{ $code }}"
                    @if (app()->getLocale() === $code) aria-current="true" @endif
                    @class([
                        'rounded-md px-2 py-1 text-xs font-bold uppercase tracking-wide transition',
                        'bg-marque-600 text-white' => app()->getLocale() === $code,
                        'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => app()->getLocale() !== $code,
                    ])>
                {{ $code }}
            </button>
        </form>
    @endforeach
</div>
