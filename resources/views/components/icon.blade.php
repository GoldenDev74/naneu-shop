@props(['name'])

@php
    // Icônes Lucide (mallardduck/blade-lucide-icons), servies en SVG inline :
    // aucun appel réseau, l'interface reste nette hors ligne.
    // Les noms historiques de l'application sont traduits ici ; tout autre
    // nom est pris tel quel comme nom Lucide (ex. « wallet », « store »).
    $alias = [
        'home'       => 'house',
        'cube'       => 'package',
        'arrow-down' => 'arrow-down-to-line',
        'arrow-up'   => 'arrow-up-from-line',
        'clock'      => 'calendar-clock',
        'clipboard'  => 'clipboard-list',
        'chart'      => 'chart-no-axes-column',
        'cog'        => 'settings',
        'shield'     => 'shield-check',
        'trash'      => 'trash-2',
        'warning'    => 'triangle-alert',
        'barcode'    => 'scan-barcode',
        'refresh'    => 'refresh-cw',
    ];

    $icone = $alias[$name] ?? $name;
@endphp

{{ svg('lucide-' . $icone, $attributes->merge(['class' => 'h-5 w-5'])->get('class'), array_merge(
    ['stroke-width' => '1.75', 'aria-hidden' => 'true'],
    $attributes->except('class')->getAttributes(),
)) }}
