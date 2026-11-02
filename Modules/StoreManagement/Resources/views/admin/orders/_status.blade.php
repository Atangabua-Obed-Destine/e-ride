@php($map = [
    'awaiting_payment' => ['bg-light text-dark', translate('Awaiting_payment')],
    'placed' => ['bg-danger', translate('New')],
    'accepted' => ['bg-warning text-dark', translate('Preparing')],
    'rider_requested' => ['bg-info text-dark', translate('Rider')],
    'picked_up' => ['bg-primary', translate('On_the_way')],
    'delivered' => ['bg-success', translate('Delivered')],
    'rejected' => ['bg-secondary', translate('Declined')],
    'cancelled' => ['bg-secondary', translate('Cancelled')],
    'expired' => ['bg-light text-muted', translate('Expired')],
    'delivery_failed' => ['bg-danger', translate('Not_delivered')],
])
<span class="badge {{ $map[$status][0] ?? 'bg-secondary' }}">{{ $map[$status][1] ?? $status }}</span>
