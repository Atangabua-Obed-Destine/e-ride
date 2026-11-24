{{-- Food & Shops partner sign-in. --}}
@include('partner-auth.login', [
    'portal' => 'store',
    'action' => route('partner.login.submit'),
    'field' => 'email',
    'fieldLabel' => translate('Email'),
    'heading' => translate('E-Ride Partner'),
    'sub' => translate('Receive orders, manage your menu and see your earnings.'),
    'features' => [
        translate('Orders arrive with a ring: accept, prepare, hand over'),
        translate('Your menu, prices, branches and opening hours'),
        translate('Your sales and earnings, day by day'),
    ],
    'help' => translate('Forgot your password? Ask the E-Ride team to reset it.'),
])
