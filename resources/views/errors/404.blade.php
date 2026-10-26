@include('errors.page', [
    'code'  => 404,
    'titre' => __('Page introuvable'),
    'texte' => __('Cette page n\'existe pas ou a été déplacée. Vérifiez l\'adresse, ou revenez à l\'accueil.'),
    'icone' => 'search-x',
    'ton'   => 'marque',
])
