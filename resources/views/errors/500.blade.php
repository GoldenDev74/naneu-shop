@include('errors.page', [
    'code'  => 500,
    'titre' => __('Une erreur est survenue'),
    'texte' => __('Le problème a été enregistré. Réessayez dans un instant ; s\'il persiste, prévenez l\'administrateur.'),
    'icone' => 'triangle-alert',
    'ton'   => 'rose',
])
