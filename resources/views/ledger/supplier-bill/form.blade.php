@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  Entering or correcting a draft supplier bill (paxhitestbed admin/supplier-bill/form): a bill
  with lines filed under expense categories, or an opening balance set against an equity account.
  Saving posts nothing; approval is a separate, reviewed step. E-Ride: recoverable VAT on the
  bill, and a supplier can be added here (paxhitestbed sends to its inventory's supplier screen).
--}}
@section('content')
@php
    $kind = old('kind', $row->kind ?? 'bill');
    $lines = old('lines', $row && ! $row->isOpeningBalance()
        ? $row->lines->map(fn ($l) => ['description' => $l->description, 'expense_category_id' => $l->expense_category_id, 'amount' => (int) round($l->amount), 'vehicle_id' => $l->vehicle_id])->all() : []);
    if (! $lines) { $lines = [['description' => '', 'expense_category_id' => '', 'amount' => '', 'vehicle_id' => '']]; }
    $fleet = $fleet ?? [];
    $openingLine = $row && $row->isOpeningBalance() ? $row->lines->first() : null;
    $supplierId = old('supplier_id', $row->supplier_id ?? $selected_supplier);
@endphp
@include('ledger.account.errors')
<div class="card">
    <div class="card-header"><h5 class="mb-0">{{ $title }}</h5></div>
    <div class="card-body">
        <a href="{{ $row ? route($route.'.show', $row->id) : route($route.'.index') }}" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
    </div>
    <form method="post" enctype="multipart/form-data" id="bill-form" action="{{ $row ? route($route.'.update', $row->id) : route($route.'.store') }}">
        @csrf
        @if($row) @method('PUT') @endif
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-12 mb-3">
                    <label class="d-block">@lang('ledger_accounts.what_is_this')</label>
                    <div class="form-check form-check-inline">
                        <input type="radio" id="kind_bill" name="kind" value="bill" class="form-check-input" @checked($kind === 'bill')>
                        <label class="form-check-label" for="kind_bill">@lang('ledger_accounts.kind_bill')</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" id="kind_opening" name="kind" value="opening_balance" class="form-check-input" @checked($kind === 'opening_balance')>
                        <label class="form-check-label" for="kind_opening">@lang('ledger_accounts.kind_opening')</label>
                    </div>
                </div>
                <div class="form-group col-md-4 mb-3">
                    <label for="supplier_id">@lang('ledger_accounts.supplier') <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="supplier_id" class="form-control" required>
                        <option value="">@lang('ledger_accounts.select')</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" data-terms="{{ $s->payment_terms_days }}" @selected($supplierId == $s->id)>{{ $s->title }}</option>
                        @endforeach
                    </select>
                    <div class="ap-muted mt-1"><a href="#" data-bs-toggle="modal" data-bs-target="#supplierModal">@lang('ledger_accounts.add_a_supplier')</a></div>
                </div>
                <div class="form-group col-md-4 mb-3">
                    <label for="supplier_reference">@lang('ledger_accounts.their_invoice_number')</label>
                    <input type="text" class="form-control" name="supplier_reference" id="supplier_reference" maxlength="100" value="{{ old('supplier_reference', $row->supplier_reference ?? '') }}">
                    <div class="ap-muted mt-1">@lang('ledger_accounts.their_invoice_hint')</div>
                </div>
                <div class="form-group col-md-2 mb-3">
                    <label for="bill_date">@lang('ledger_accounts.bill_date') <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="bill_date" id="bill_date" required max="{{ now()->toDateString() }}"
                           value="{{ old('bill_date', $row ? $row->bill_date->toDateString() : now()->toDateString()) }}">
                </div>
                <div class="form-group col-md-2 mb-3">
                    <label for="due_date">@lang('ledger_accounts.due_date')</label>
                    <input type="date" class="form-control" name="due_date" id="due_date" value="{{ old('due_date', $row ? $row->due_date->toDateString() : '') }}">
                    <div class="ap-muted mt-1" id="due-hint">@lang('ledger_accounts.due_from_terms')</div>
                </div>
                <div class="form-group col-md-8 mb-3">
                    <label for="description">@lang('ledger_accounts.description')</label>
                    <input type="text" class="form-control" name="description" id="description" maxlength="255" value="{{ old('description', $row->description ?? '') }}">
                </div>
                <div class="form-group col-md-4 mb-3">
                    <label for="attach">@lang('ledger_accounts.scanned_invoice')</label>
                    <input type="file" class="form-control" name="attach" id="attach" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                    @if($row && $row->attach)
                    <div class="ap-muted mt-1"><a href="{{ \App\Http\Controllers\Ledger\IncomeExpenseController::fileUrl($row->attach) }}" target="_blank">@lang('ledger_accounts.current_file')</a></div>
                    @endif
                </div>
            </div>

            <div id="bill-lines" @if($kind === 'opening_balance') style="display:none" @endif>
                <h6 class="mt-2">@lang('ledger_accounts.lines')</h6>
                <p class="ap-muted">@lang('ledger_accounts.lines_hint')</p>
                <div class="table-responsive">
                    <table class="table ap-table" id="lines-table">
                        <thead>
                            <tr>
                                <th style="width:38%">@lang('ledger_accounts.expense_category_one') <span class="text-danger">*</span></th>
                                <th>@lang('ledger_accounts.description')</th>
                                @if($fleet)<th style="width:20%">@lang('ledger_accounts.field_bus')</th>@endif
                                <th class="ap-num" style="width:180px">@lang('ledger_accounts.amount') <span class="text-danger">*</span></th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lines as $i => $line)
                            <tr class="bill-line">
                                <td>
                                    <select name="lines[{{ $i }}][expense_category_id]" class="form-control line-category">
                                        <option value="">@lang('ledger_accounts.select')</option>
                                        @foreach($categories as $c)
                                        <option value="{{ $c->id }}" @selected(($line['expense_category_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                                        @endforeach
                                        @foreach($kept_categories as $c)
                                        <option value="{{ $c->id }}" @selected(($line['expense_category_id'] ?? '') == $c->id)>{{ $c->name }} - @lang('ledger_accounts.no_longer_in_use')</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="lines[{{ $i }}][description]" class="form-control" maxlength="255" value="{{ $line['description'] ?? '' }}"></td>
                                @if($fleet)
                                <td><select name="lines[{{ $i }}][vehicle_id]" class="form-control"><option value="">@lang('ledger_accounts.no_bus')</option>
                                    @foreach($fleet as $vid => $vlabel)<option value="{{ $vid }}" @selected((string) ($line['vehicle_id'] ?? '') === (string) $vid)>{{ $vlabel }}</option>@endforeach
                                </select></td>
                                @endif
                                <td><input type="number" step="1" min="0" name="lines[{{ $i }}][amount]" class="form-control ap-num line-amount" value="{{ $line['amount'] ?? '' }}"></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="@lang('ledger_accounts.remove')"><i class="fas fa-times"></i></button></td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><button type="button" class="btn btn-sm btn-outline-primary" id="add-line"><i class="fas fa-plus"></i> @lang('ledger_accounts.add_a_line')</button></td>
                                <td class="text-end" @if($fleet) colspan="2" @endif>@lang('ledger_accounts.vat_recoverable')</td>
                                <td><input type="number" step="1" min="0" name="vat_amount" id="vat_amount" class="form-control ap-num" value="{{ old('vat_amount', $row ? (int) round($row->vat_amount) : '') }}"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td class="text-end" @if($fleet) colspan="2" @endif><strong>@lang('ledger_accounts.total')</strong></td>
                                <td class="ap-num"><strong id="lines-total">0</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div id="opening-fields" class="row" @if($kind !== 'opening_balance') style="display:none" @endif>
                <div class="col-md-12"><div class="ap-note mb-3">@lang('ledger_accounts.opening_note')</div></div>
                <div class="form-group col-md-6 mb-3">
                    <label for="opening_account_id">@lang('ledger_accounts.set_against') <span class="text-danger">*</span></label>
                    <select name="opening_account_id" id="opening_account_id" class="form-control">
                        <option value="">@lang('ledger_accounts.select')</option>
                        @foreach($opening_accounts as $acc)
                        <option value="{{ $acc->id }}" @selected(old('opening_account_id', $openingLine->account_id ?? '') == $acc->id)>{{ \App\Ledger\Services\PaymentBook::accountLabel($acc) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3 mb-3">
                    <label for="opening_amount">@lang('ledger_accounts.amount_owed') <span class="text-danger">*</span></label>
                    <input type="number" step="1" min="0" class="form-control ap-num" name="opening_amount" id="opening_amount" value="{{ old('opening_amount', $openingLine ? (int) round($openingLine->amount) : '') }}">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap align-items-center" style="gap:8px">
            <button type="submit" name="after" value="draft" class="btn btn-outline-success"><i class="fas fa-save"></i> @lang('ledger_accounts.save_as_draft')</button>
            @can('supplier-bill-approve')
            <button type="submit" name="after" value="approve" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_accounts.save_and_review')</button>
            @endcan
            <span class="ap-muted">@lang('ledger_accounts.nothing_posted_until')</span>
        </div>
    </form>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post" action="{{ route('ledger.item-supplier.store') }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">@lang('ledger_accounts.add_a_supplier')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('ledger_accounts.btn_close')"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label for="s_title">@lang('ledger_accounts.supplier_name') <span class="text-danger">*</span></label><input type="text" name="title" id="s_title" class="form-control" required maxlength="191"></div>
                <div class="row">
                    <div class="col-md-6 mb-2"><label for="s_phone">@lang('ledger_accounts.phone')</label><input type="text" name="phone" id="s_phone" class="form-control" maxlength="40"></div>
                    <div class="col-md-6 mb-2"><label for="s_email">@lang('ledger_accounts.email')</label><input type="email" name="email" id="s_email" class="form-control"></div>
                    <div class="col-md-6 mb-2"><label for="s_contact">@lang('ledger_accounts.contact_person')</label><input type="text" name="contact_person" id="s_contact" class="form-control"></div>
                    <div class="col-md-6 mb-2"><label for="s_niu">@lang('ledger_accounts.niu')</label><input type="text" name="tax_number" id="s_niu" class="form-control" maxlength="40"></div>
                    <div class="col-md-8 mb-2"><label for="s_address">@lang('ledger_accounts.address')</label><input type="text" name="address" id="s_address" class="form-control"></div>
                    <div class="col-md-4 mb-2"><label for="s_terms">@lang('ledger_accounts.payment_terms_days')</label><input type="number" name="payment_terms_days" id="s_terms" class="form-control" min="0" max="365" value="0"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">@lang('ledger_accounts.btn_close')</button>
                <button type="submit" class="btn btn-primary">@lang('ledger_accounts.btn_save')</button>
            </div>
        </form>
    </div></div>
</div>

<template id="line-template">
    <tr class="bill-line">
        <td>
            <select name="lines[__i__][expense_category_id]" class="form-control line-category">
                <option value="">@lang('ledger_accounts.select')</option>
                @foreach($categories as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="text" name="lines[__i__][description]" class="form-control" maxlength="255"></td>
        @if($fleet)
        <td><select name="lines[__i__][vehicle_id]" class="form-control"><option value="">@lang('ledger_accounts.no_bus')</option>
            @foreach($fleet as $vid => $vlabel)<option value="{{ $vid }}">{{ $vlabel }}</option>@endforeach
        </select></td>
        @endif
        <td><input type="number" step="1" min="0" name="lines[__i__][amount]" class="form-control ap-num line-amount"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="@lang('ledger_accounts.remove')"><i class="fas fa-times"></i></button></td>
    </tr>
</template>
@endsection

@section('javascript')
<script>
(function () {
    var tbody = document.querySelector('#lines-table tbody');
    var next = tbody.querySelectorAll('tr').length;
    var fmt = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
    var vat = document.getElementById('vat_amount');
    function total() {
        var sum = parseFloat(vat.value) || 0;
        tbody.querySelectorAll('.line-amount').forEach(function (i) { sum += parseFloat(i.value) || 0; });
        document.getElementById('lines-total').textContent = fmt.format(Math.round(sum));
    }
    document.getElementById('add-line').addEventListener('click', function () {
        tbody.insertAdjacentHTML('beforeend', document.getElementById('line-template').innerHTML.replace(/__i__/g, next++));
    });
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.remove-line');
        if (!btn) { return; }
        if (tbody.querySelectorAll('tr').length > 1) { btn.closest('tr').remove(); } else {
            btn.closest('tr').querySelectorAll('input').forEach(function (i) { i.value = ''; });
            btn.closest('tr').querySelector('select').value = '';
        }
        total();
    });
    tbody.addEventListener('input', total);
    vat.addEventListener('input', total);
    total();
    // a half-filled line is caught here rather than after a round trip
    document.getElementById('bill-form').addEventListener('submit', function (e) {
        if (document.getElementById('kind_opening').checked) { return; }
        var bad = 0, filled = 0;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var cat = tr.querySelector('.line-category'), amt = tr.querySelector('.line-amount');
            var hasAmt = (parseFloat(amt.value) || 0) > 0, hasCat = cat.value !== '';
            cat.classList.toggle('is-invalid', hasAmt && !hasCat);
            amt.classList.toggle('is-invalid', hasCat && !hasAmt);
            if (hasAmt !== hasCat) { bad++; }
            if (hasAmt && hasCat) { filled++; }
        });
        if (bad || !filled) {
            e.preventDefault();
            alert(bad ? @json(__('ledger_accounts.err_line_both')) : @json(__('ledger_accounts.err_bill_no_lines')));
        }
    });
    document.querySelectorAll('input[name="kind"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var opening = document.getElementById('kind_opening').checked;
            document.getElementById('bill-lines').style.display = opening ? 'none' : '';
            document.getElementById('opening-fields').style.display = opening ? '' : 'none';
        });
    });
    function hint() {
        var opt = document.getElementById('supplier_id').selectedOptions[0];
        var days = opt ? parseInt(opt.getAttribute('data-terms'), 10) : 0;
        document.getElementById('due-hint').textContent = days > 0 ? @json(__('ledger_accounts.due_plus')).replace(':days', days) : @json(__('ledger_accounts.due_on_bill_date'));
    }
    document.getElementById('supplier_id').addEventListener('change', hint);
    hint();
})();
</script>
@endsection
