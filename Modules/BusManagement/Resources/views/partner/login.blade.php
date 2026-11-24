{{-- Bus agency (branch) sign-in; the head office uses it too, with its own words (CompanyAuthController). --}}
@include('partner-auth.login', [
    'portal' => $portal ?? 'bus',
    'action' => $action ?? route('bus-partner.login.submit'),
    'field' => 'login',
    'fieldLabel' => translate('Email or username'),
    'heading' => $heading ?? translate('E-Ride for bus agencies'),
    'sub' => $sub ?? translate('Seat requests, counter sales, boarding and your departures, in one place.'),
    'features' => $features ?? [
        translate('Seat requests from the app and counter sales on one live seat map'),
        translate('Boarding with the ticket QR code, and the passenger list'),
        translate('Your accounts: cash per agent, spending, profit per bus'),
    ],
    'help' => $help ?? translate('Lost your password: your manager can set a new one in Staff. Managers: ask the E-Ride team.'),
])
