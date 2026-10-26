@php
    // Motif précis du refus (« Accès en lecture seule à cette section. ») quand il y en a un.
    $motif = $exception?->getMessage();
@endphp
@include('errors.page', [
    'code'  => 403,
    'titre' => __('Accès refusé'),
    'texte' => filled($motif) && $motif !== 'This action is unauthorized.'
        ? __($motif)
        : __('Vous n\'avez pas les droits pour ouvrir cette page. Si vous en avez besoin, demandez à la gérance de vous ouvrir ce menu.'),
    'icone' => 'shield',
    'ton'   => 'ambre',
])
