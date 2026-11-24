{{-- Hotel and guest house sign-in. --}}
@include('partner-auth.login', [
    'portal' => 'hotel',
    'action' => route('hotel-partner.login.submit'),
    'field' => 'login',
    'fieldLabel' => translate('Email or username'),
    'heading' => translate('E-Ride for hotels'),
    'sub' => translate('Your rooms, guests asking for a room, and who arrives today, in one place.'),
    'features' => [
        translate('Guests ask for a room in the app: answer them by chat or call'),
        translate('Your rooms board: free, kept, booked, in house'),
        translate('Who arrives today, and room service on the room bill'),
    ],
    'help' => translate('Lost your password: your manager can set a new one in Staff. Managers: ask the E-Ride team.'),
])
