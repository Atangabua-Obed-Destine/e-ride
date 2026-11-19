@extends('adminmodule::layouts.master')

@section('title', translate('Home_page_look'))

@section('content')
    <form action="{{ route('admin.business.pages-media.landing-page.look.update') }}" method="POST">
        @csrf
        <div class="main-content">
            <div class="container-fluid">
                <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{ translate('landing_page_setup') }}</h2>
                <div class="mb-3">
                    @include('businessmanagement::admin.pages.partials._landing_page_inline_menu')
                </div>

                <div class="card card-body mb-3">
                    <h4 class="mb-1">{{ translate('Home page look') }}</h4>
                    <p class="fs-14 mb-3">{{ translate('How the home page looks to every visitor. Its words are written in English and French and follow the visitor\'s language; the contact details come from Business Setup.') }}</p>
                    <div class="row g-3">
                        @foreach([
                            ['dark', 'Dark', 'Dark background, like the app\'s welcome screens.', '#141416', '#FFFFFF'],
                            ['light', 'Light', 'Warm off-white background with white cards.', '#FAF7F4', '#141416'],
                            ['auto', 'Follow the visitor\'s phone', 'Dark or light, the way each person set their phone.', 'linear-gradient(135deg,#141416 50%,#FAF7F4 50%)', '#E02020'],
                        ] as [$value, $label, $hint, $swatch, $ink])
                            <div class="col-md-4">
                                <label class="card h-100 p-3 cursor-pointer {{ $theme === $value ? 'border-primary' : '' }}" for="theme_{{ $value }}" style="border-width:2px">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <input type="radio" class="form-check-input mt-0" name="theme" id="theme_{{ $value }}" value="{{ $value }}" @checked($theme === $value)>
                                        <span class="fw-semibold">{{ translate($label) }}</span>
                                    </div>
                                    <div style="height:90px;border-radius:6px;background:{{ $swatch }};display:flex;align-items:center;justify-content:center;color:{{ $ink }};font-weight:800;border:1px solid #E5E5E5">E-Ride</div>
                                    <span class="fs-12 text-muted mt-2 d-block">{{ translate($hint) }}</span>
                                    <a class="fs-12 mt-1 d-inline-block" href="{{ url('/?theme=' . $value) }}" target="_blank" rel="noopener">{{ translate('Preview') }}</a>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
