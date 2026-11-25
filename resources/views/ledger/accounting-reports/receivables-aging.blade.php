@extends('layouts.app')
@section('title', __('ledger_reports.receivables_aging_report'))

@section('content')
@include('ledger.partials.style')
@include('ledger.accounting-reports._aging', ['kind' => 'receivables'])
@endsection
