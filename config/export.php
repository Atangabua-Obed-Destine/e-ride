<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Excel Font
    |--------------------------------------------------------------------------
    |
    | Font family used by the styled Excel export system. .SF Bangla is the
    | macOS system Bengali font, so Bengali text shapes closer to the system
    | UI while still supporting the regular Latin labels used in reports. Override
    | by setting EXPORT_FONT in .env or by saving an `export_font` key in
    | the business_settings table (settings_type = business_information).
    |
    */
    'font' => env('EXPORT_FONT', 'Calibri'),
];
