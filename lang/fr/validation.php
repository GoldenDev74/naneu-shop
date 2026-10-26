<?php

/*
|--------------------------------------------------------------------------
| Messages de validation
|--------------------------------------------------------------------------
|
| Seules les règles réellement employées par Naneu Shop sont traduites.
| Les composants Livewire définissent en outre leurs propres messages
| lorsqu'une formulation métier est plus claire.
|
*/

return [
    'accepted'             => 'Le champ :attribute doit être accepté.',
    'after'                => 'Le champ :attribute doit être une date postérieure au :date.',
    'before_or_equal'      => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'boolean'              => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed'            => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password'     => 'Ce mot de passe est incorrect.',
    'date'                 => 'Le champ :attribute doit être une date valide.',
    'different'            => 'Les champs :attribute et :other doivent être différents.',
    'email'                => 'Le champ :attribute doit être une adresse e-mail valide.',
    'exists'               => 'La valeur sélectionnée pour :attribute est invalide.',
    'file'                 => 'Le champ :attribute doit être un fichier.',
    'gt'                   => [
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
    ],
    'image'                => 'Le champ :attribute doit être une image.',
    'in'                   => 'La valeur sélectionnée pour :attribute est invalide.',
    'integer'              => 'Le champ :attribute doit être un entier.',
    'lte'                  => [
        'numeric' => 'Le champ :attribute doit être inférieur ou égal à :value.',
    ],
    'max'                  => [
        'array'   => 'Le champ :attribute ne peut contenir plus de :max éléments.',
        'file'    => 'Le champ :attribute ne peut dépasser :max Ko.',
        'numeric' => 'Le champ :attribute ne peut dépasser :max.',
        'string'  => 'Le champ :attribute ne peut dépasser :max caractères.',
    ],
    'mimes'                => 'Le champ :attribute doit être un fichier de type : :values.',
    'min'                  => [
        'array'   => 'Le champ :attribute doit contenir au moins :min éléments.',
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string'  => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric'              => 'Le champ :attribute doit être un nombre.',
    'regex'                => 'Le format du champ :attribute est invalide.',
    'required'             => 'Le champ :attribute est obligatoire.',
    'required_if'          => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'string'               => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique'               => 'Cette valeur de :attribute est déjà utilisée.',

    'password' => [
        'letters'       => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed'         => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers'       => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols'       => 'Le champ :attribute doit contenir au moins un caractère spécial.',
        'uncompromised' => 'Ce mot de passe est apparu dans une fuite de données connue. Choisissez-en un autre.',
    ],

    'attributes' => [
        'email'                 => 'adresse e-mail',
        'password'              => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'current_password'      => 'mot de passe actuel',
        'name'                  => 'nom',
        'phone'                 => 'téléphone',
        'role'                  => 'rôle',
        'quantity'              => 'quantité',
        'purchase_price'        => 'prix d\'achat',
        'sale_price'            => 'prix de vente',
        'expiry_date'           => 'date de péremption',
        'entry_date'            => 'date de réception',
        'exit_date'             => 'date de sortie',
    ],
];
