<?php

return [
    'title' => 'System documentation',
    'subtitle' => 'Product overview for leadership, and a technical handoff guide for engineers resuming work on SPIMS.',
    'nav' => 'System docs',
    'tile' => 'System documentation',
    'tile_desc' => 'Client overview and technical handoff guides for the portal.',
    'audience_nav' => 'Documentation audience',
    'audience_all' => 'All guides',
    'audience_client' => 'For leadership & partners',
    'audience_technical' => 'For the technical team',
    'back_to_index' => 'Back to system documentation',
    'related' => 'Related guides',
    'guest_banner' => 'You are reading the public school overview. Sign in to open the full technical handoff set.',
    'locale_fallback' => 'This page is shown in English because a translation for your language is not available yet.',
    'empty_title' => 'No guides available',
    'empty_desc' => 'Ask a Super Admin to publish the client overview for guests, or sign in to read the full set.',
    'help_crosslink_prefix' => 'Looking for how-to tips? Open the',
    'publish_title' => 'Publish system documentation',
    'publish_desc' => 'Control whether guests without accounts can read the leadership overview inside the portal.',
    'publish_status_label' => 'Guest access',
    'publish_status_on' => 'Published to guests',
    'publish_status_off' => 'Signed-in users only',
    'publish_scope_note' => 'When published, guests can open leadership (client) guides only. Technical handoff pages always require a signed-in account.',
    'publish_checkbox' => 'Publish leadership system documentation to guests',
    'publish_save' => 'Save publish setting',
    'publish_enabled' => 'Leadership system documentation is now visible to guests.',
    'publish_disabled' => 'Guest access to system documentation is turned off.',
    'publish_client_note' => 'These pages become guest-readable when publish is on.',
    'publish_technical_note' => 'These pages stay behind sign-in even when guest publish is on.',
    'superadmin_tile' => 'System documentation',
    'superadmin_tile_desc' => 'Read the handoff set and publish the leadership overview to guests.',
    'superadmin_tile_hint' => 'Toggle guest publish. Technical pages remain authenticated.',

    'pages' => [
        'overview' => [
            'title' => 'What SPIMS is today',
            'summary' => 'Plain-language picture of the live Student Information System and Learning Management System.',
        ],
        'roles-guide' => [
            'title' => 'Roles at a glance',
            'summary' => 'Who uses the school portal and what each role owns day to day.',
        ],
        'student-journey' => [
            'title' => 'Student journeys',
            'summary' => 'From guest catalog browsing through enrollment, learning, exams, pay, and credentials.',
        ],
        'staff-journeys' => [
            'title' => 'Staff journeys',
            'summary' => 'Teach, academic admin, school admin, finance, and Super Admin workflows already in the product.',
        ],
        'portal-navigation' => [
            'title' => 'Navigation, hubs, and dashboard cards',
            'summary' => 'How the sidebar, hubs, and home widgets help people find their work.',
        ],
        'architecture' => [
            'title' => 'Architecture & non-negotiables',
            'summary' => 'Stack, environments, hard product rules, and where source of truth lives in the repo.',
        ],
        'frontend' => [
            'title' => 'Frontend handoff',
            'summary' => 'Blade shell, design system, components, hub tiles, and dashboard widgets.',
        ],
        'backend' => [
            'title' => 'Backend handoff',
            'summary' => 'Services, authorization, audit writes, route tracks, and API surface.',
        ],
        'database' => [
            'title' => 'Database map',
            'summary' => 'Major tables and models grouped by domain.',
        ],
        'navigation-map' => [
            'title' => 'Navigation & IA map',
            'summary' => 'NavigationHub destinations, hub tiles, and Super Admin sections.',
        ],
        'feature-flows' => [
            'title' => 'Feature flows',
            'summary' => 'End-to-end flows for admissions, enrollment, LMS, assessment, finance, live, and credentials.',
        ],
        'roles-permissions' => [
            'title' => 'Roles & permissions matrix',
            'summary' => 'Role types, permission keys, offering scopes, and Roles Hub overrides.',
        ],
    ],
];
