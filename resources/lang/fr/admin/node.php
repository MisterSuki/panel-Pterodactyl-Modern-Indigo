<?php

return [
    'validation' => [
        'fqdn_not_resolvable' => 'Le FQDN ou l\'adresse IP fournis ne correspondent à aucune adresse IP valide.',
        'fqdn_required_for_ssl' => 'Un nom de domaine complet qui pointe vers une adresse IP publique est nécessaire pour utiliser le SSL sur ce node.',
    ],
    'notices' => [
        'allocations_added' => 'Les allocations ont été ajoutées à ce node.',
        'node_deleted' => 'Le node a été retiré du panel.',
        'location_required' => 'Il faut au moins une location configurée avant d\'ajouter un node à ce panel.',
        'node_created' => 'Le node a été créé. Tu peux configurer automatiquement le daemon sur cette machine depuis l\'onglet « Configuration ». Avant d\'ajouter des serveurs, il faut d\'abord allouer au moins une adresse IP et un port.',
        'node_updated' => 'Les informations du node ont été mises à jour. Si des réglages du daemon ont changé, il faut le redémarrer pour qu\'ils s\'appliquent.',
        'unallocated_deleted' => 'Tous les ports non alloués de <code>:ip</code> ont été supprimés.',
    ],
];
