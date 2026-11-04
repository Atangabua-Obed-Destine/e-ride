@php
    $depositBadge = [
        'awaiting' => ['warning', 'Waiting_for_payment'],
        'paid' => ['info', 'Paid_held_by_E-Ride'],
        'applied' => ['success', 'Counted_toward_the_fare'],
        'forfeited' => ['danger', 'Kept_customer_cancelled'],
        'refunded' => ['secondary', 'Refunded_to_wallet'],
        'expired' => ['light', 'Not_paid'],
    ][$status] ?? ['light', $status];
@endphp
<span class="badge badge-{{ $depositBadge[0] }} text-capitalize">{{ translate($depositBadge[1]) }}</span>
