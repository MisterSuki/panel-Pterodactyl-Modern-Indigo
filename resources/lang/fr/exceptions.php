<?php

return [
    'daemon_connection_failed' => 'Une exception s\'est produite pendant la communication avec le daemon (réponse HTTP/:code). Elle a été enregistrée.',
    'node' => [
        'servers_attached' => 'Un node ne peut être supprimé que s\'il n\'a aucun serveur.',
        'daemon_off_config_updated' => 'La configuration du daemon a été mise à jour, mais une erreur est survenue en essayant de mettre à jour automatiquement son fichier de configuration. Il faut modifier le fichier de configuration (config.yml) du daemon à la main pour appliquer ces changements.',
    ],
    'allocations' => [
        'server_using' => 'Un serveur utilise actuellement cette allocation. Une allocation ne peut être supprimée que si aucun serveur ne l\'utilise.',
        'too_many_ports' => 'Ajouter plus de 1000 ports d\'un coup dans une même plage n\'est pas possible.',
        'invalid_mapping' => 'Le mappage fourni pour :port n\'est pas valide et n\'a pas pu être traité.',
        'cidr_out_of_range' => 'La notation CIDR n\'accepte que des masques entre /25 et /32.',
        'port_out_of_range' => 'Les ports d\'une allocation doivent être supérieurs à 1024 et inférieurs ou égaux à 65535.',
    ],
    'nest' => [
        'delete_has_servers' => 'Un nest qui a des serveurs actifs ne peut pas être supprimé du panel.',
        'egg' => [
            'delete_has_servers' => 'Un egg qui a des serveurs actifs ne peut pas être supprimé du panel.',
            'invalid_copy_id' => 'L\'egg choisi pour copier un script n\'existe pas, ou copie lui-même un script.',
            'must_be_child' => 'La directive « Copier les réglages de » de cet egg doit désigner une option enfant du nest choisi.',
            'has_children' => 'Cet egg est le parent d\'un ou plusieurs autres eggs. Supprimez d\'abord ces eggs avant de supprimer celui-ci.',
        ],
        'variables' => [
            'env_not_unique' => 'La variable d\'environnement :name doit être unique pour cet egg.',
            'reserved_name' => 'La variable d\'environnement :name est protégée et ne peut pas être assignée à une variable.',
            'bad_validation_rule' => 'La règle de validation « :rule » n\'est pas une règle valide pour cette application.',
        ],
        'importer' => [
            'json_error' => 'Une erreur est survenue en lisant le fichier JSON : :error.',
            'file_error' => 'Le fichier JSON fourni n\'est pas valide.',
            'invalid_json_provided' => 'Le fichier JSON fourni n\'a pas un format reconnu.',
        ],
    ],
    'subusers' => [
        'editing_self' => 'Modifier votre propre compte de sous-utilisateur n\'est pas permis.',
        'user_is_owner' => 'Vous ne pouvez pas ajouter le propriétaire du serveur comme sous-utilisateur.',
        'subuser_exists' => 'Un utilisateur avec cette adresse email est déjà sous-utilisateur de ce serveur.',
    ],
    'databases' => [
        'delete_has_databases' => 'Impossible de supprimer un serveur de bases de données qui a des bases actives.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'L\'intervalle maximal pour une tâche enchaînée est de 15 minutes.',
    ],
    'locations' => [
        'has_nodes' => 'Impossible de supprimer une location qui a des nodes actifs.',
    ],
    'users' => [
        'node_revocation_failed' => 'Impossible de révoquer les clés sur <a href=":link">le node n°:node</a>. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Aucun node ne répond aux critères demandés pour le déploiement automatique.',
        'no_viable_allocations' => 'Aucune allocation ne répond aux critères demandés pour le déploiement automatique.',
    ],
    'api' => [
        'resource_not_found' => 'La ressource demandée n\'existe pas sur ce serveur.',
    ],
];
