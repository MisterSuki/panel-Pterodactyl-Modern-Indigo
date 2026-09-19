<?php

/**
 * Contains all of the translation strings for different activity log
 * events. These should be keyed by the value in front of the first
 * period (.) in the event name, with the first period being replaced
 * with an underscore (_), and all others with a dash.
 */
return [
    'auth' => [
        'fail' => 'Échec de connexion',
        'success' => 'Connexion réussie',
        'password-reset' => 'Mot de passe réinitialisé',
        'reset-password' => 'Réinitialisation du mot de passe demandée',
        'checkpoint' => 'Authentification à deux facteurs demandée',
        'recovery-token' => 'Code de secours à deux facteurs utilisé',
        'token' => 'Défi à deux facteurs réussi',
        'ip-blocked' => 'Requête bloquée depuis une adresse IP non autorisée pour :identifier',
        'sftp' => [
            'fail' => 'Échec de connexion SFTP',
        ],
    ],
    'user' => [
        'user' => [
            'create' => 'Nouvel utilisateur créé : :email',
        ],
        'account' => [
            'email-changed' => 'Email changé de :old à :new',
            'password-changed' => 'Mot de passe changé',
        ],
        'api-key' => [
            'create' => 'Nouvelle clé d\'API créée : :identifier',
            'delete' => 'Clé d\'API supprimée : :identifier',
        ],
        'ssh-key' => [
            'create' => 'Clé SSH :fingerprint ajoutée au compte',
            'delete' => 'Clé SSH :fingerprint retirée du compte',
        ],
        'two-factor' => [
            'create' => 'Authentification à deux facteurs activée',
            'delete' => 'Authentification à deux facteurs désactivée',
        ],
    ],
    'server' => [
        'reinstall' => 'Serveur réinstallé',
        'console' => [
            'command' => 'A exécuté « :command » sur le serveur',
        ],
        'power' => [
            'start' => 'Serveur démarré',
            'stop' => 'Serveur arrêté',
            'restart' => 'Serveur redémarré',
            'kill' => 'Processus du serveur tué',
        ],
        'backup' => [
            'download' => 'Sauvegarde :name téléchargée',
            'delete' => 'Sauvegarde :name supprimée',
            'restore' => 'Sauvegarde :name restaurée (fichiers supprimés : :truncate)',
            'restore-complete' => 'Restauration de la sauvegarde :name terminée',
            'restore-failed' => 'Échec de la restauration de la sauvegarde :name',
            'start' => 'Nouvelle sauvegarde démarrée : :name',
            'complete' => 'Sauvegarde :name marquée comme terminée',
            'fail' => 'Sauvegarde :name marquée comme échouée',
            'lock' => 'Sauvegarde :name verrouillée',
            'unlock' => 'Sauvegarde :name déverrouillée',
        ],
        'database' => [
            'create' => 'Nouvelle base de données créée : :name',
            'rotate-password' => 'Mot de passe changé pour la base de données :name',
            'phpmyadmin' => 'Base de données :name ouverte dans phpMyAdmin',
            'delete' => 'Base de données :name supprimée',
        ],
        'file' => [
            'compress_one' => ':directory:files.0 compressé',
            'compress_other' => ':count fichiers compressés dans :directory',
            'read' => 'Contenu de :file consulté',
            'copy' => 'Copie de :file créée',
            'create-directory' => 'Dossier :directory:name créé',
            'decompress' => ':files décompressé dans :directory',
            'delete_one' => ':directory:files.0 supprimé',
            'delete_other' => ':count fichiers supprimés dans :directory',
            'download' => ':file téléchargé',
            'pull' => 'Fichier distant téléchargé depuis :url vers :directory',
            'rename_one' => ':directory:files.0.from renommé en :directory:files.0.to',
            'rename_other' => ':count fichiers renommés dans :directory',
            'write' => 'Nouveau contenu écrit dans :file',
            'upload' => 'Envoi de fichier commencé',
            'uploaded' => ':directory:file envoyé',
        ],
        'sftp' => [
            'denied' => 'Accès SFTP bloqué à cause des permissions',
            'create_one' => ':files.0 créé',
            'create_other' => ':count nouveaux fichiers créés',
            'write_one' => 'Contenu de :files.0 modifié',
            'write_other' => 'Contenu de :count fichiers modifié',
            'delete_one' => ':files.0 supprimé',
            'delete_other' => ':count fichiers supprimés',
            'create-directory_one' => 'Dossier :files.0 créé',
            'create-directory_other' => ':count dossiers créés',
            'rename_one' => ':files.0.from renommé en :files.0.to',
            'rename_other' => ':count fichiers renommés ou déplacés',
        ],
        'allocation' => [
            'create' => ':allocation ajoutée au serveur',
            'notes' => 'Notes de :allocation changées de « :old » à « :new »',
            'primary' => ':allocation définie comme allocation principale du serveur',
            'delete' => 'Allocation :allocation supprimée',
        ],
        'schedule' => [
            'create' => 'Planning :name créé',
            'update' => 'Planning :name mis à jour',
            'execute' => 'Planning :name exécuté à la main',
            'delete' => 'Planning :name supprimé',
        ],
        'task' => [
            'create' => 'Nouvelle tâche « :action » créée pour le planning :name',
            'update' => 'Tâche « :action » mise à jour pour le planning :name',
            'delete' => 'Tâche supprimée pour le planning :name',
        ],
        'settings' => [
            'rename' => 'Serveur renommé de :old en :new',
            'description' => 'Description du serveur changée de :old à :new',
        ],
        'startup' => [
            'edit' => 'Variable :variable changée de « :old » à « :new »',
            'image' => 'Image Docker du serveur changée de :old à :new',
        ],
        'subuser' => [
            'create' => ':email ajouté comme sous-utilisateur',
            'update' => 'Permissions du sous-utilisateur :email mises à jour',
            'delete' => ':email retiré des sous-utilisateurs',
        ],
    ],
];
