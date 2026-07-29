@extends('adminmodule::layouts.master')

@section('title', translate('Edit_Zone_Setup'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <form action="{{ route('admin.zone.extra-fare.store') }}" enctype="multipart/form-data"
                  method="POST">
                @csrf
                <input type="hidden" name="id" id="zoneId" value="{{$zone->id}}">
                <div class="d-flex align-items-center gap-3 justify-content-between mb-4">
                    <h2 class="fs-22 text-capitalize">{{ $zone->name }}</h2>
                </div>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center p-md-30px">
                        <div class="w-0 flex-grow-1">
                            <h5 class="mb-2">{{translate("Zone Wise Extra Fare")}}</h5>
                            <div class="fs-12">
                                {{translate("To turn this feature on customer will pay the extra fare for each trip & parcel delivery service.")}}
                            </div>
                        </div>
                        <label class="switcher cmn_focus rounded">
                            <input class="switcher_input" name="extra_fare_status" tabindex="1"
                                   type="checkbox" {{$zone->extra_fare_status?'checked':''}}>
                            <span class="switcher_control"></span>
                        </label>
                    </div>
                    <div class="card-body p-md-30px">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="w-0 flex-grow-1">
                                    <h5 class="mb-2">{{translate("Extra Fare Setup")}}</h5>
                                    <div class="fs-12">
                                        {{translate("Hear you can setup why you charge extra fare & set how much price will be
                                        increased for the reason.")}}
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div>
                                    <label class="form-label">{{translate("Extra Fare")}} (%) <i
                                            class="bi bi-info-circle-fill text-primary tooltip-icon"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="{{translate("Set the percentage of extra fare to be added to the total fare")}}"></i></label>
                                    <input type="number" min="0" max="100" step="{{stepValue()}}" name="extra_fare_fee" value="{{$zone->extra_fare_fee ?? old("extra_fare_fee")}}"  class="form-control" placeholder="Ex : 100" required tabindex="2">
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <div>
                                    <label class="form-label">{{translate("Reasons for Extra Fare")}}</label>
                                    <input type="text" name="extra_fare_reason" value="{{$zone->extra_fare_reason ?? old("extra_fare_reason")}}" class="form-control" placeholder="Ex : Heavy Rain" required tabindex="3">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-3 mt-3 pt-sm-3">
                    <button class="btn btn-secondary cmn_reset" type="reset" tabindex="4">{{ translate('Reset') }}</button>
                    <button class="btn btn-primary cmn_focus" type="submit" tabindex="5">{{ translate('Update') }}</button>
                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')

@endpush
