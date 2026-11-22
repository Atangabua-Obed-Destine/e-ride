@extends('storemanagement::partner.layout')

@section('title', translate('Team'))

@php
    $labels = [
        'orders' => [translate('Orders'), translate('See new orders, accept, prepare, hand over, call riders.')],
        'open_close' => [translate('Open and close'), translate('Open or close the store for the day.')],
        'availability' => [translate('Today\'s menu'), translate('Mark dishes sold out today, or back.')],
        'menu' => [translate('Menu'), translate('Edit dishes and sections, their photos and serving times (not prices).')],
        'settings' => [translate('Store settings'), translate('Hours, preparation time, delivery (not prices or payouts).')],
        'riders' => [translate('My riders'), translate('Choose the riders who deliver for you.')],
        'earnings' => [translate('Earnings'), translate('See what the store earned.')],
    ];
    $roleNames = ['manager' => translate('Manager'), 'sales' => translate('Sales')];
    $actions = [
        'orders.accept' => translate('accepted order'), 'orders.reject' => translate('refused order'), 'orders.ready' => translate('marked ready'),
        'orders.hand-over' => translate('handed over order'), 'orders.out' => translate('sent out order'), 'orders.use-rider' => translate('called a rider for order'),
        'orders.open-riders' => translate('called every rider for order'), 'orders.propose' => translate('proposed changes to order'),
        'menu.item.availability' => translate('changed today\'s availability of'), 'menu.item.save' => translate('edited'), 'menu.item.delete' => translate('deleted a dish'),
        'menu.category.save' => translate('edited a menu section'), 'menu.category.delete' => translate('deleted a menu section'),
        'store.update' => translate('changed the store settings'), 'store.open' => translate('opened or closed the store'),
        'riders.add' => translate('added a rider'), 'riders.remove' => translate('removed a rider'), 'riders.mode' => translate('changed who is called for deliveries'),
    ];
    // Read after a name ("Sali accepted order #1021"): the translation starts with a capital.
    $actions = array_map(fn($t) => mb_strtolower(mb_substr($t, 0, 1)) . mb_substr($t, 1), $actions);
@endphp

@push('head')
<style>
    .t-row { padding: 12px 0; }
    .t-row + .t-row { border-top: 1px solid var(--line); }
    .t-perm { display: flex; gap: 8px; align-items: flex-start; font-size: 14px; padding: 4px 0; }
    .t-perm small { display: block; color: var(--muted); font-size: 12px; }
    .t-log { font-size: 13px; padding: 6px 0; border-top: 1px solid var(--line); }
    .t-log time { color: var(--muted); font-size: 12px; }
</style>
@endpush

@section('content')
    <h1 class="fs-4 fw-bold mb-1">{{ translate('Team') }}</h1>
    <p class="text-muted">{{ translate('Give the people who run your store their own login. You choose what each one can do. Prices, payouts and the team stay with you.') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="p-card p-3 mb-3">
                <h2 class="fs-6 fw-bold mb-2">{{ translate('People') }}</h2>
                @forelse($members as $m)
                    <div class="t-row">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="flex-grow-1"><b>{{ $m->user?->first_name }}</b> <span class="badge bg-light text-dark">{{ $roleNames[$m->role] ?? $m->role }}</span>
                                <div class="fs-12 text-muted">{{ translate('Signs in with') }} {{ $m->user?->email }}</div></div>
                            <form action="{{ route('partner.team.remove', $m->id) }}" method="POST" onsubmit="return confirm('{{ translate('Remove this person? Their login stops working at once.') }}')">@csrf
                                <button class="btn btn-sm btn-outline-danger">{{ translate('Remove') }}</button></form>
                        </div>
                        <details>
                            <summary class="fs-13">{{ translate('What they can do') }} ({{ count((array)$m->permissions) }})</summary>
                            <form action="{{ route('partner.team.update', $m->id) }}" method="POST" class="mt-2">@csrf
                                <select name="role" class="form-select form-select-sm mb-2" style="max-width: 220px">
                                    @foreach($roleNames as $key => $name)<option value="{{ $key }}" {{ $m->role === $key ? 'selected' : '' }}>{{ $name }}</option>@endforeach
                                </select>
                                @foreach($permissions as $p)
                                    <label class="t-perm"><input type="checkbox" name="permissions[]" value="{{ $p }}" {{ in_array($p, (array)$m->permissions, true) ? 'checked' : '' }}>
                                        <span>{{ $labels[$p][0] }}<small>{{ $labels[$p][1] }}</small></span></label>
                                @endforeach
                                <button class="btn btn-sm btn-primary mt-2">{{ translate('Save') }}</button>
                            </form>
                            <form action="{{ route('partner.team.password', $m->id) }}" method="POST" class="d-flex gap-2 mt-3">@csrf
                                <input type="text" name="password" class="form-control form-control-sm" minlength="8" placeholder="{{ translate('New password (8 or more)') }}" required>
                                <button class="btn btn-sm btn-light text-nowrap">{{ translate('Set password') }}</button>
                            </form>
                        </details>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ translate('Nobody yet. Add the first person below.') }}</p>
                @endforelse
            </div>

            <div class="p-card p-3 mb-3">
                <h2 class="fs-6 fw-bold mb-2">{{ translate('Add someone') }}</h2>
                <form action="{{ route('partner.team.add') }}" method="POST" data-team-add>@csrf
                    <div class="row g-2 mb-2">
                        <div class="col-sm-6"><input type="text" name="name" class="form-control" placeholder="{{ translate('Their name') }}" value="{{ old('name') }}" required></div>
                        <div class="col-sm-6"><input type="email" name="email" class="form-control" placeholder="{{ translate('Email they sign in with') }}" value="{{ old('email') }}" required></div>
                        <div class="col-sm-6"><input type="text" name="password" class="form-control" minlength="8" placeholder="{{ translate('A password (8 or more)') }}" required></div>
                        <div class="col-sm-6">
                            <select name="role" class="form-select" data-role>
                                @foreach($roleNames as $key => $name)<option value="{{ $key }}" {{ old('role', 'sales') === $key ? 'selected' : '' }}>{{ $name }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    @foreach($permissions as $p)
                        <label class="t-perm"><input type="checkbox" name="permissions[]" value="{{ $p }}" data-perm {{ in_array($p, $roles['sales'], true) ? 'checked' : '' }}>
                            <span>{{ $labels[$p][0] }}<small>{{ $labels[$p][1] }}</small></span></label>
                    @endforeach
                    <p class="fs-12 text-muted mt-2">{{ translate('Give them the email and password in person. They sign in on this same page.') }}</p>
                    <button class="btn btn-primary w-100" style="min-height: 46px">{{ translate('Add to the team') }}</button>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="p-card p-3">
                <h2 class="fs-6 fw-bold mb-2">{{ translate('Who did what') }}</h2>
                @forelse($activity as $a)
                    <div class="t-log"><b>{{ $a->who }}</b> {{ $actions[$a->action] ?? $a->action }} {{ $a->subject }}<br><time>{{ $a->at->format('d M, H:i') }}</time></div>
                @empty
                    <p class="text-muted mb-0">{{ translate('Nothing yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        // A role ticks its usual permissions; you can change them after.
        (function () {
            var form = document.querySelector('[data-team-add]'), roles = @json($roles);
            form.querySelector('[data-role]').addEventListener('change', function () {
                var on = roles[this.value] || [];
                form.querySelectorAll('[data-perm]').forEach(function (c) { c.checked = on.indexOf(c.value) !== -1; });
            });
        })();
    </script>
@endsection
