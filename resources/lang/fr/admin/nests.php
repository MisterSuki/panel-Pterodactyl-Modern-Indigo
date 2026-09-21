<?php

return [
    'notices' => [
        'created' => 'Un nouveau nest, :name, a été créé.',
        'deleted' => 'Le nest demandé a été supprimé du panel.',
        'updated' => 'Les options du nest ont été mises à jour.',
    ],
    'eggs' => [
        'notices' => [
            'imported' => 'Cet egg et ses variables ont été importés.',
            'updated_via_import' => 'Cet egg a été mis à jour avec le fichier fourni.',
            'deleted' => 'L\'egg demandé a été supprimé du panel.',
            'updated' => 'La configuration de l\'egg a été mise à jour.',
            'script_updated' => 'Le script d\'installation de l\'egg a été mis à jour et sera lancé à chaque installation de serveur.',
            'egg_created' => 'Un nouvel egg a été pondu. Il faut redémarrer les daemons en cours pour appliquer ce nouvel egg.',
        ],
    ],
    'variables' => [
        'notices' => [
            'variable_deleted' => 'La variable « :variable » a été supprimée et ne sera plus disponible pour les serveurs une fois reconstruits.',
            'variable_updated' => 'La variable « :variable » a été mise à jour. Il faut reconstruire les serveurs qui l\'utilisent pour appliquer les changements.',
            'variable_created' => 'La nouvelle variable a été créée et assignée à cet egg.',
        ],
    ],
];
