<?php

return [
    'exceptions' => [
        'no_new_default_allocation' => 'Tu essaies de supprimer l\'allocation par défaut de ce serveur, mais il n\'y a pas d\'allocation de remplacement.',
        'marked_as_failed' => 'Ce serveur a été marqué comme ayant échoué lors d\'une installation précédente. Son statut ne peut pas être changé dans cet état.',
        'skipping_install_script' => 'Ce serveur est configuré pour ignorer le script d\'installation de son egg. La réinstallation n\'est pas possible tant que ce réglage n\'est pas désactivé.',
        'bad_variable' => 'La variable :name a provoqué une erreur de validation.',
        'daemon_exception' => 'Une exception s\'est produite pendant la communication avec le daemon (réponse HTTP/:code). Elle a été enregistrée. (identifiant de requête : :request_id)',
        'default_allocation_not_found' => 'L\'allocation par défaut demandée n\'a pas été trouvée parmi les allocations de ce serveur.',
    ],
    'alerts' => [
        'startup_changed' => 'La configuration de démarrage de ce serveur a été mise à jour. Si le nest ou l\'egg a changé, une réinstallation va avoir lieu maintenant.',
        'server_deleted' => 'Le serveur a été supprimé du système.',
        'server_created' => 'Le serveur a été créé sur le panel. Laissez quelques minutes au daemon pour l\'installer complètement.',
        'build_updated' => 'Les détails de configuration de ce serveur ont été mis à jour. Certains changements demandent un redémarrage.',
        'suspension_toggled' => 'Le statut de suspension du serveur est maintenant : :status.',
        'rebuild_on_boot' => 'Ce serveur doit reconstruire son conteneur Docker. Ce sera fait au prochain démarrage.',
        'install_toggled' => 'Le statut d\'installation de ce serveur a été inversé.',
        'server_reinstalled' => 'Ce serveur est en file d\'attente pour une réinstallation qui commence maintenant.',
        'details_updated' => 'Les détails du serveur ont été mis à jour.',
        'docker_image_updated' => 'L\'image Docker par défaut de ce serveur a été changée. Un redémarrage est nécessaire pour l\'appliquer.',
        'node_required' => 'Il faut au moins un node configuré avant d\'ajouter un serveur à ce panel.',
        'transfer_nodes_required' => 'Il faut au moins deux nodes configurés avant de pouvoir transférer des serveurs.',
        'transfer_started' => 'Le transfert du serveur a commencé.',
        'transfer_not_viable' => 'Le node choisi n\'a pas assez d\'espace disque ou de mémoire disponibles pour accueillir ce serveur.',
    ],
];
