@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/pay-slip-setting/index: what the printed payslip carries. --}}
@section('content')
<section class="content">
    @include('ledger.account.errors')
    <form action="{{ route('ledger.pay-slip-setting.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ $title }}</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="title">@lang('ledger_payroll.field_title')</label>
                        <input type="text" class="form-control" name="title" id="title" value="{{ old('title', $row->title) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="prefix">@lang('ledger_payroll.field_prefix')</label>
                        <input type="text" class="form-control" name="prefix" id="prefix" value="{{ old('prefix', $row->prefix) }}" maxlength="20">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="width">@lang('ledger_payroll.field_width')</label>
                        <input type="text" class="form-control" name="width" id="width" value="{{ old('width', $row->width) }}" maxlength="20" placeholder="800px">
                    </div>
                    @foreach(['header_left', 'header_center', 'header_right'] as $f)
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="{{ $f }}">@lang('ledger_payroll.field_'.$f)</label>
                        <textarea class="form-control" name="{{ $f }}" id="{{ $f }}" rows="3">{{ old($f, $row->{$f}) }}</textarea>
                    </div>
                    @endforeach
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="body">@lang('ledger_payroll.field_body')</label>
                        <textarea class="form-control" name="body" id="body" rows="3">{{ old('body', $row->body) }}</textarea>
                    </div>
                    @foreach(['footer_left', 'footer_center', 'footer_right'] as $f)
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="{{ $f }}">@lang('ledger_payroll.field_'.$f)</label>
                        <textarea class="form-control" name="{{ $f }}" id="{{ $f }}" rows="3">{{ old($f, $row->{$f}) }}</textarea>
                    </div>
                    @endforeach
                    @foreach(['logo_left', 'logo_right', 'background'] as $f)
                    <div class="col-md-4 mb-3">
                        <label class="form-label" for="{{ $f }}">@lang('ledger_payroll.field_'.$f)</label>
                        <input type="file" class="form-control" name="{{ $f }}" id="{{ $f }}" accept="image/*">
                        @if($row->{$f})<img src="{{ \App\Ledger\Models\PrintSetting::fileUrl($row->{$f}) }}" class="mt-2" style="max-height:60px" alt="">@endif
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer"><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_update')</button></div>
        </div>
    </form>
</section>
@endsection
