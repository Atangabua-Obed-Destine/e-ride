<?php

// L'espace de travail bureau des portails bus et de la comptabilité
// (public/assets/desk/desktop.js, celui de posinnova, commun à nos systèmes) :
// les mots de la barre de titre, des menus, des onglets et de la barre d'état
// sur grand écran, et de la boîte « Aller à… » sur tous les écrans. Gardé comme
// le fichier de posinnova ; les menus propres à E-Ride sont à la fin.
return [
    'menu' => 'Menu',
    'home' => 'Accueil',
    'open_screens' => 'Écrans ouverts',
    'toolbar' => "Barre d'outils",

    // Menus, pour un menu sans titres de zones.
    'menu_go' => 'Aller',
    'menu_sales' => 'Ventes',
    'menu_stock' => 'Stock',
    'menu_contacts' => 'Contacts',
    'menu_money' => 'Finances',
    'menu_reports' => 'Rapports',
    'menu_people' => 'Personnel',
    'menu_settings' => 'Paramètres',
    'menu_superadmin' => 'Superadmin',
    'menu_more' => 'Plus',

    // Barre d'outils
    'back' => 'Précédent',
    'forward' => 'Suivant',
    'refresh' => 'Actualiser',
    'print' => 'Imprimer',
    'go_to' => 'Aller à…',
    'go_to_title' => "Aller à n'importe quel écran",
    'sidebar' => 'Afficher ou masquer le menu complet',

    // Boîte « Aller à… »
    'palette_placeholder' => 'Aller à un écran…',
    'palette_hint' => '↑ ↓ pour choisir · Entrée pour ouvrir · Échap pour fermer',
    'palette_empty' => 'Aucun résultat.',

    // Onglets
    'tab_close' => 'Fermer cet onglet',
    'opening' => 'Ouverture…',
    'tabs_full_title' => 'Deux écrans sont déjà ouverts',
    'tabs_full' => "En plus de l'accueil, deux écrans restent ouverts à la fois. Ouvrir « :new » fermera « :old », celui que vous avez utilisé il y a le plus longtemps. Ce qui n'est pas enregistré sur « :old » sera perdu.",
    'tabs_full_ok' => 'Fermer « :old » et ouvrir',
    'tab_dropped' => "« :old » a été fermé : en plus de l'accueil, deux écrans restent ouverts à la fois.",
    'cancel' => 'Annuler',

    // Barre d'état
    'conn_online' => 'Connecté',
    'conn_offline' => 'Pas de réseau',
    'conn_unreachable' => 'Le serveur ne répond pas',
    'conn_title' => 'Connexion au serveur',
    'key_tabs' => 'Alt+1…3 onglets',

    // E-Ride : les menus des portails bus (Modules/BusManagement/Lib/PortalMenu.php).
    'menu_counter' => 'Guichet',
    'menu_trips' => 'Voyages',
    'menu_team' => 'Équipe',
    'menu_watch' => 'Suivi',
    'menu_run' => 'Gestion',
    'menu_portal' => 'Portail',
    'search' => 'Rechercher',
];
