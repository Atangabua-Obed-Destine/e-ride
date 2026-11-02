@extends('storemanagement::partner.layout')

@section('title', translate('Store'))

@section('content')
    <h1 class="fs-4 fw-bold mb-3">{{ translate('Store') }}</h1>
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form action="{{ route('partner.store.update') }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="p-card p-3 mb-3">
                    <h2 class="fs-6 fw-bold mb-3">{{ translate('Opening_hours') }}</h2>
                    @include('storemanagement::shared._hours')
                </div>
                <div class="p-card p-3 mb-3">
                    <h2 class="fs-6 fw-bold mb-3">{{ translate('Orders') }}</h2>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">{{ translate('Usual_preparation_time_(minutes)') }}</label>
                            <input type="number" name="default_prep_minutes" class="form-control" required min="5" max="180" value="{{ old('default_prep_minutes', $store->default_prep_minutes) }}">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">{{ translate('Minimum_order_(FCFA)') }}</label>
                            <input type="number" name="min_order_amount" class="form-control" min="0" step="1" value="{{ old('min_order_amount', (int)$store->min_order_amount) }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="p-card p-3 mb-3">
                    <h2 class="fs-6 fw-bold mb-3">{{ translate('Contact_and_photos') }}</h2>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ translate('Phone_for_orders') }}</label>
                        <input type="tel" name="phone" class="form-control" required maxlength="25" value="{{ old('phone', $store->phone) }}">
                        <div class="fs-12 text-muted mt-1">{{ translate('New-order text messages go to this number.') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ translate('WhatsApp') }}</label>
                        <input type="tel" name="whatsapp" class="form-control" maxlength="25" value="{{ old('whatsapp', $store->whatsapp) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ translate('Short_description') }}</label>
                        <textarea name="description" class="form-control" rows="2" maxlength="1000">{{ old('description', $store->description) }}</textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ translate('Logo') }}</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ translate('Cover_photo') }}</label>
                            <input type="file" name="cover" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="p-card p-3 mb-3">
                    <h2 class="fs-6 fw-bold mb-1">{{ translate('Payouts') }}</h2>
                    <p class="fs-12 text-muted">{{ translate('Where E-Ride sends your money.') }}</p>
                    <div class="mb-3">
                        <select name="payout_method" class="form-select">
                            @foreach(['' => translate('Choose'), 'mtn_momo' => 'MTN MoMo', 'orange_money' => 'Orange Money', 'bank' => translate('Bank')] as $value => $label)
                                <option value="{{ $value }}" {{ old('payout_method', $store->payout_method) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <input type="text" name="payout_number" class="form-control" maxlength="40" placeholder="{{ translate('Number_or_account') }}" value="{{ old('payout_number', $store->payout_number) }}">
                    </div>
                    <input type="text" name="payout_name" class="form-control" maxlength="120" placeholder="{{ translate('Account_name') }}" value="{{ old('payout_name', $store->payout_name) }}">
                </div>
                <p class="fs-12 text-muted">{{ translate('To change your store name, address or map location, contact E-Ride.') }}</p>
                <button type="submit" class="btn btn-primary w-100" style="min-height: 50px">{{ translate('Save') }}</button>
            </div>
        </div>
    </form>
@endsection
