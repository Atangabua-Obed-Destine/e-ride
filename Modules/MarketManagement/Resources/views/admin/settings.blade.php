@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride Market settings'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 900px">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride Market') }}</h2>
            @include('marketmanagement::admin._nav', ['active' => 'settings'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form action="{{ route('admin.market.settings.save') }}" method="POST">@csrf
                <div class="card mb-3"><div class="card-body d-flex flex-column gap-3">
                    <div class="form-check form-switch">
                        <input type="hidden" name="enabled" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="m-enabled" name="enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}>
                        <label class="form-check-label" for="m-enabled">{{ translate('E-Ride Market is on') }}</label>
                        <div class="fs-12 text-muted">{{ translate('Riders can open shops and post; shop and item links work. Off: the Market disappears from the app and links say it is not available.') }}</div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="digital_allowed" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="m-digital" name="digital_allowed" value="1" {{ $settings['digital_allowed'] ? 'checked' : '' }}>
                        <label class="form-check-label" for="m-digital">{{ translate('Digital products allowed') }}</label>
                        <div class="fs-12 text-muted">{{ translate('A free preview is required, and the seller confirms they own the product.') }}</div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="digital_warning" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="m-warning" name="digital_warning" value="1" {{ $settings['digital_warning'] ? 'checked' : '' }}>
                        <label class="form-check-label" for="m-warning">{{ translate('Red warning on digital products of unverified shops') }}</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="m-hide">{{ translate('Hide an item after this many people report it') }}</label>
                            <input type="number" id="m-hide" name="hide_after_reports" class="form-control" min="1" max="20" required value="{{ $settings['hide_after_reports'] }}">
                            <div class="fs-12 text-muted mt-1">{{ translate('One scam report is enough for a digital product of an unverified shop.') }}</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="m-items">{{ translate('Items per shop') }}</label>
                            <input type="number" id="m-items" name="items_per_shop" class="form-control" min="1" max="500" required value="{{ $settings['items_per_shop'] }}">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="m-payer">{{ translate('Rest of the delivery fare paid by') }}</label>
                            <select id="m-payer" name="delivery_payer" class="form-select">
                                @foreach(['buyer' => 'The buyer, on delivery', 'seller' => 'The seller', 'seller_chooses' => 'The seller chooses each time'] as $value => $label)
                                    <option value="{{ $value }}" {{ $settings['delivery_payer'] === $value ? 'selected' : '' }}>{{ translate($label) }}</option>
                                @endforeach
                            </select>
                            <div class="fs-12 text-muted mt-1">{{ translate('The seller books the delivery the buyer asked for in the chat, and pays the booking deposit.') }}</div>
                        </div>
                    </div>
                </div></div>

                <div class="card mb-3"><div class="card-body">
                    <label class="form-label" for="m-words">{{ translate('Banned words and phrases, one per line') }}</label>
                    <textarea id="m-words" name="banned_words" rows="10" class="form-control font-monospace fs-12">{{ $settings['banned_words'] }}</textarea>
                    <div class="fs-12 text-muted mt-1">{{ translate('A post with one of these (whole words, accents and capitals ignored) does not go live and waits in the moderation queue, where you can allow it.') }}</div>
                </div></div>

                <div class="card mb-3"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">{{ translate('Paid features') }}</h5><span class="badge bg-secondary">{{ translate('Later') }}</span>
                    </div>
                    <p class="fs-12 text-muted mb-0">{{ translate('Listing is free at launch. A listing fee, a fee for E-Ride Verified and priority placement will come here when E-Ride is ready to charge. E-Ride never holds money between buyers and sellers.') }}</p>
                </div></div>

                <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">{{ translate('Save') }}</button></div>
            </form>
        </div>
    </div>
@endsection
