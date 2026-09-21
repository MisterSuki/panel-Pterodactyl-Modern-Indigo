<?php

return [
    'email' => [
        'title' => 'Modifier votre email',
        'updated' => 'Votre adresse email a été mise à jour.',
    ],
    'password' => [
        'title' => 'Changer votre mot de passe',
        'requirements' => 'Votre nouveau mot de passe doit contenir au moins 8 caractères.',
        'updated' => 'Votre mot de passe a été mis à jour.',
    ],
    'two_factor' => [
        'button' => 'Configurer l\'authentification à 2 facteurs',
        'disabled' => 'L\'authentification à deux facteurs a été désactivée sur votre compte. Un code ne vous sera plus demandé à la connexion.',
        'enabled' => 'L\'authentification à deux facteurs est activée sur votre compte ! Désormais, à chaque connexion, vous devrez saisir le code généré par votre appareil.',
        'invalid' => 'Le code fourni n\'est pas valide.',
        'setup' => [
            'title' => 'Configurer l\'authentification à deux facteurs',
            'help' => 'Impossible de scanner le code ? Saisissez le code ci-dessous dans votre application :',
            'field' => 'Saisissez le code',
        ],
        'disable' => [
            'title' => 'Désactiver l\'authentification à deux facteurs',
            'field' => 'Saisissez le code',
        ],
    ],
];
