<?php

return [
    'sign_in' => 'Se connecter',
    'go_to_login' => 'Aller à la connexion',
    'failed' => 'Aucun compte ne correspond à ces identifiants.',

    'forgot_password' => [
        'label' => 'Mot de passe oublié ?',
        'label_help' => 'Saisissez l\'adresse email de votre compte pour recevoir les instructions de réinitialisation de votre mot de passe.',
        'button' => 'Récupérer le compte',
    ],

    'reset_password' => [
        'button' => 'Réinitialiser et se connecter',
    ],

    'two_factor' => [
        'label' => 'Code à 2 facteurs',
        'label_help' => 'Ce compte demande une seconde étape d\'authentification pour continuer. Saisissez le code généré par votre appareil pour terminer la connexion.',
        'checkpoint_failed' => 'Le code d\'authentification à deux facteurs n\'est pas valide.',
    ],

    'throttle' => 'Trop de tentatives de connexion. Réessayez dans :seconds secondes.',
    'password_requirements' => 'Le mot de passe doit contenir au moins 8 caractères et être unique à ce site.',
    '2fa_must_be_enabled' => 'L\'administrateur exige que l\'authentification à 2 facteurs soit activée sur votre compte pour utiliser le panel.',
];
