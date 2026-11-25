<?php

// The desktop workspace of the bus portals and the accounting frame
// (public/assets/desk/desktop.js, posinnova's, shared by all our systems): the
// words of the title bar, the menus, the tabs and the status bar on a wide
// screen, and of the "Go to…" box on every screen. Kept as posinnova's file;
// E-Ride's own menus are at the end.
return [
    'menu' => 'Menu',
    'home' => 'Home',
    'open_screens' => 'Open screens',
    'toolbar' => 'Toolbar',

    // Menus, for a menu that has no area headings of its own.
    'menu_go' => 'Go',
    'menu_sales' => 'Sales',
    'menu_stock' => 'Stock',
    'menu_contacts' => 'Contacts',
    'menu_money' => 'Money',
    'menu_reports' => 'Reports',
    'menu_people' => 'People',
    'menu_settings' => 'Settings',
    'menu_superadmin' => 'Superadmin',
    'menu_more' => 'More',

    // Toolbar
    'back' => 'Back',
    'forward' => 'Forward',
    'refresh' => 'Refresh',
    'print' => 'Print',
    'go_to' => 'Go to…',
    'go_to_title' => 'Go to any screen',
    'sidebar' => 'Show or hide the full menu',

    // "Go to…" box
    'palette_placeholder' => 'Go to a screen…',
    'palette_hint' => '↑ ↓ to choose · Enter to open · Esc to close',
    'palette_empty' => 'Nothing matches.',

    // Tabs
    'tab_close' => 'Close this tab',
    'opening' => 'Opening…',
    'tabs_full_title' => 'Two screens are already open',
    'tabs_full' => 'Besides Home, two screens stay open at a time. Opening “:new” will close “:old”, the one you used longest ago. Anything not saved on “:old” will be lost.',
    'tabs_full_ok' => 'Close “:old” and open',
    'tab_dropped' => '“:old” was closed: besides Home, two screens stay open at a time.',
    'cancel' => 'Cancel',

    // Status bar
    'conn_online' => 'Connected',
    'conn_offline' => 'No network',
    'conn_unreachable' => 'Server not answering',
    'conn_title' => 'Connection to the server',
    'key_tabs' => 'Alt+1…3 tabs',

    // E-Ride: the menus of the bus portals (Modules/BusManagement/Lib/PortalMenu.php).
    'menu_counter' => 'Counter',
    'menu_trips' => 'Trips',
    'menu_team' => 'Team',
    'menu_watch' => 'Watch',
    'menu_run' => 'Run',
    'menu_portal' => 'Portal',
    'search' => 'Search',
];
