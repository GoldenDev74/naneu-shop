@props(['href'])

{{-- Bouton de retour vers une liste : même forme partout dans l'application. --}}
<a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => 'btn-secondaire']) }}>
    <x-icon name="arrow-left" class="h-4 w-4" />
    {{ $slot }}
</a>
