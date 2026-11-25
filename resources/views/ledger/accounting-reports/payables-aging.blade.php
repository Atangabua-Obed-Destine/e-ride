@extends('layouts.app')
@section('title', __('ledger_reports.payables_aging_report'))

@section('content')
@include('ledger.partials.style')
@include('ledger.accounting-reports._aging', ['kind' => 'payables'])
@endsection
