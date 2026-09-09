<?php

return [
    'title' => 'Documentation système',
    'subtitle' => 'Vue produit pour la direction, et guide de reprise technique pour l’équipe qui continue SPIMS.',
    'nav' => 'Docs système',
    'tile' => 'Documentation système',
    'tile_desc' => 'Vue client et guides de reprise technique pour le portail.',
    'audience_nav' => 'Public de la documentation',
    'audience_all' => 'Tous les guides',
    'audience_client' => 'Pour la direction et les partenaires',
    'audience_technical' => 'Pour l’équipe technique',
    'back_to_index' => 'Retour à la documentation système',
    'related' => 'Guides associés',
    'guest_banner' => 'Vous lisez la vue publique de l’école. Connectez-vous pour ouvrir l’ensemble technique complet.',
    'locale_fallback' => 'Cette page est affichée en anglais car une traduction pour votre langue n’est pas encore disponible.',
    'empty_title' => 'Aucun guide disponible',
    'empty_desc' => 'Demandez à un Super Admin de publier la vue client pour les invités, ou connectez-vous pour lire l’ensemble complet.',
    'help_crosslink_prefix' => 'Besoin d’aide pratique ? Ouvrez le',
    'open_guide' => 'Ouvrir le guide',
    'publish_title' => 'Publier la documentation système',
    'publish_desc' => 'Contrôlez si les invités sans compte peuvent lire la vue direction dans le portail.',
    'publish_status_label' => 'Accès invité',
    'publish_status_on' => 'Publié pour les invités',
    'publish_status_off' => 'Utilisateurs connectés uniquement',
    'publish_scope_note' => 'Une fois publié, les invités n’ouvrent que les guides direction (client). Les pages techniques exigent toujours une connexion.',
    'publish_checkbox' => 'Publier la documentation direction pour les invités',
    'publish_save' => 'Enregistrer le paramètre de publication',
    'publish_enabled' => 'La documentation direction est désormais visible pour les invités.',
    'publish_disabled' => 'L’accès invité à la documentation système est désactivé.',
    'publish_client_note' => 'Ces pages deviennent lisibles par les invités lorsque la publication est activée.',
    'publish_technical_note' => 'Ces pages restent derrière la connexion même si la publication invité est active.',
    'superadmin_tile' => 'Documentation système',
    'superadmin_tile_desc' => 'Lire le kit de reprise et publier la vue direction pour les invités.',
    'superadmin_tile_hint' => 'Basculer la publication invité. Les pages techniques restent authentifiées.',

    'pages' => [
        'overview' => [
            'title' => 'Ce qu’est SPIMS aujourd’hui',
            'summary' => 'Vue claire du SIS et du LMS déjà en production.',
        ],
        'roles-guide' => [
            'title' => 'Rôles en un coup d’œil',
            'summary' => 'Qui utilise le portail scolaire et ce que chaque rôle possède au quotidien.',
        ],
        'student-journey' => [
            'title' => 'Parcours étudiants',
            'summary' => 'Du catalogue public à l’inscription, l’apprentissage, les examens, le paiement et les certificats.',
        ],
        'staff-journeys' => [
            'title' => 'Parcours du personnel',
            'summary' => 'Enseignement, admin académique, admin scolaire, finance et Super Admin.',
        ],
        'portal-navigation' => [
            'title' => 'Navigation, hubs et cartes du tableau de bord',
            'summary' => 'Comment la barre latérale, les hubs et les widgets aident chacun à trouver son travail.',
        ],
        'architecture' => [
            'title' => 'Architecture et règles non négociables',
            'summary' => 'Stack, environnements, règles produit et sources de vérité dans le dépôt.',
        ],
        'frontend' => [
            'title' => 'Reprise frontend',
            'summary' => 'Shell Blade, design system, composants, tuiles de hub et widgets du tableau de bord.',
        ],
        'backend' => [
            'title' => 'Reprise backend',
            'summary' => 'Services, autorisation, audit, pistes de routes et surface API.',
        ],
        'database' => [
            'title' => 'Carte de la base de données',
            'summary' => 'Tables et modèles majeurs regroupés par domaine.',
        ],
        'navigation-map' => [
            'title' => 'Carte de navigation et IA',
            'summary' => 'Destinations NavigationHub, tuiles de hub et sections Super Admin.',
        ],
        'feature-flows' => [
            'title' => 'Flux des fonctionnalités',
            'summary' => 'Flux de bout en bout pour admissions, inscription, LMS, évaluation, finance, live et certificats.',
        ],
        'roles-permissions' => [
            'title' => 'Matrice rôles et permissions',
            'summary' => 'Types de rôles, clés de permission, portées d’offering et overrides Roles Hub.',
        ],
    ],
];
