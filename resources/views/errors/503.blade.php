@include('errors.page', [
    'code'  => 503,
    'titre' => __('Maintenance en cours'),
    'texte' => __('L\'application est momentanément indisponible pour maintenance. Elle revient dans quelques minutes.'),
    'icone' => 'wrench',
    'ton'   => 'ambre',
])
