@include('errors.page', [
    'code'  => 419,
    'titre' => __('Session expirée'),
    'texte' => __('Votre session a expiré, souvent après une période d\'inactivité. Reconnectez-vous pour reprendre là où vous en étiez.'),
    'icone' => 'clock',
    'ton'   => 'ambre',
])
