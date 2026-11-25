@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@include('ledger.account.theme', ['kind' => $kind])
@endsection

{{--
  Recording or correcting one income or one expense (paxhitestbed admin/account/partials/transaction_form):
  the same fields, grouped into the three questions being answered: what it is, how it was paid,
  what proves it. Saving goes through the review dialog, which shows the ledger entry it posts
  (paxhitestbed's confirm modal read the figures back; here the real entry is shown).
  E-Ride: the payment account can be changed on an edit too (its book follows).
--}}
@section('content')
@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $row = $row ?? null;
    $val = fn ($field, $default = null) => old($field, $row->{$field} ?? $default);
    $fileUrl = $isEdit ? \App\Http\Controllers\Ledger\IncomeExpenseController::fileUrl($row->attach) : null;
@endphp
<div class="main-body acct-form">
    <div class="page-wrapper">
        <div class="row">
            <div class="col-lg-10 col-xl-9">
                @include('ledger.account.errors')
                <div class="acct-panel">
                    <div class="acct-sec" style="padding-bottom:0;">
                        <div class="acct-label">{{ $title }}</div>
                        <h4 style="margin:.2rem 0 0;font-size:1.1rem;font-weight:700;color:var(--ink);">{{ $isEdit ? __('ledger_accounts.form_head_edit') : __('ledger_accounts.form_head_create') }}</h4>
                        @if($isEdit)
                        <div style="font-size:.78rem;color:var(--muted);margin-top:.2rem;">
                            @lang('ledger_accounts.form_recorded') {{ $row->created_at?->format('d/m/Y') }} · {{ \App\Ledger\Services\PaymentBook::userName($row->recordedBy) }}
                        </div>
                        @endif
                    </div>

                    <form method="post" enctype="multipart/form-data" id="acct-form"
                          action="{{ $isEdit ? route($route.'.update', [$row->id]) : route($route.'.store') }}"
                          data-review="@lang($isEdit ? 'ledger_accounts.check_record_edit' : 'ledger_accounts.check_record')" data-review-label="@lang('ledger_accounts.save_and_post')">
                        @csrf
                        @if($isEdit) @method('PUT') @endif

                        <div class="acct-sec">
                            <div class="acct-sec-head">@lang('ledger_accounts.section_what')</div>
                            <div class="row">
                                <div class="form-group col-md-4 mb-3">
                                    <label for="category">@lang('ledger_accounts.field_category') <span>*</span></label>
                                    <select class="form-control" name="category" id="category" required>
                                        <option value="">@lang('ledger_accounts.select')</option>
                                        @foreach($categories as $c)
                                        <option value="{{ $c->id }}" @selected((string) old('category', $row->category_id ?? '') === (string) $c->id)>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                    @if($categories->isEmpty())
                                    <small class="form-text">@lang('ledger_accounts.no_categories_yet') <a href="{{ route('ledger.'.$kind.'-category.index') }}">@lang('ledger_accounts.'.$kind.'_category')</a></small>
                                    @endif
                                </div>
                                <div class="form-group col-md-4 mb-3">
                                    <label for="title">@lang('ledger_accounts.field_title') <span>*</span></label>
                                    <input type="text" class="form-control" name="title" id="title" value="{{ $val('title') }}" required maxlength="191">
                                </div>
                                <div class="form-group col-md-4 mb-3">
                                    <label for="amount">@lang('ledger_accounts.field_amount') (FCFA) <span>*</span></label>
                                    <input type="number" step="1" min="1" class="form-control" name="amount" id="amount"
                                           value="{{ old('amount', $row ? (int) round($row->amount) : '') }}" required inputmode="numeric">
                                </div>
                                <div class="form-group col-md-4 mb-3">
                                    <label for="date">@lang('ledger_accounts.field_date') <span>*</span></label>
                                    <input type="date" class="form-control" name="date" id="date" max="{{ now()->toDateString() }}"
                                           value="{{ old('date', $row ? $row->date->format('Y-m-d') : date('Y-m-d')) }}" required>
                                </div>
                                {{-- E-Ride: the department an expense is spent for, for departmental budgets --}}
                                @if(! empty($departments) && count($departments))
                                <div class="form-group col-md-4 mb-3">
                                    <label for="department_id">@lang('ledger_budget.department')</label>
                                    <select class="form-control" name="department_id" id="department_id">
                                        <option value="">@lang('ledger_accounts.select')</option>
                                        @foreach($departments as $d)
                                        <option value="{{ $d->id }}" @selected((string) old('department_id', $row->department_id ?? '') === (string) $d->id)>{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="acct-sec">
                            <div class="acct-sec-head">@lang('ledger_accounts.section_how')</div>
                            <div class="row">
                                <div class="form-group col-md-4 mb-3">
                                    <label for="payment_method">@lang('ledger_accounts.field_payment_method') <span>*</span></label>
                                    <select class="form-control" name="payment_method" id="payment_method" required>
                                        <option value="">@lang('ledger_accounts.select')</option>
                                        @foreach($methods as $k => $label)
                                        <option value="{{ $k }}" @selected($val('payment_method') === $k)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4 mb-3">
                                    <label for="payment_account_id">@lang('ledger_accounts.field_payment_account')</label>
                                    <select class="form-control" name="payment_account_id" id="payment_account_id">
                                        <option value="">@lang('ledger_accounts.select_payment_account_optional')</option>
                                        @foreach($payment_accounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) $val('payment_account_id') === (string) $account->id)>
                                            {{ $account->title }} - @lang('ledger_accounts.field_balance'): {{ \App\Ledger\Ledger::money($account->current_balance) }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text">@lang('ledger_accounts.'.$account_help)</small>
                                </div>
                                <div class="form-group col-md-4 mb-3">
                                    <label for="invoice_id">@lang('ledger_accounts.field_invoice_id')</label>
                                    <input type="text" class="form-control" name="invoice_id" id="invoice_id" value="{{ $val('invoice_id') }}" maxlength="191">
                                </div>
                                <div class="form-group col-md-4 mb-3">
                                    <label for="reference">@lang('ledger_accounts.field_reference')</label>
                                    <input type="text" class="form-control" name="reference" id="reference" value="{{ $val('reference') }}" maxlength="191">
                                </div>
                            </div>
                        </div>

                        <div class="acct-sec">
                            <div class="acct-sec-head">@lang('ledger_accounts.section_evidence')</div>
                            <div class="row">
                                <div class="form-group col-md-4 mb-3">
                                    <label for="attach">@lang('ledger_accounts.field_attach')</label>
                                    <input type="file" class="form-control" name="attach" id="attach">
                                    @if($isEdit)
                                        @if($fileUrl)
                                        <div class="acct-file"><i class="fas fa-paperclip"></i> <span>@lang('ledger_accounts.attached_file'):</span> <a href="{{ $fileUrl }}" target="_blank">{{ basename($row->attach) }}</a></div>
                                        <small class="form-text">@lang('ledger_accounts.replace_file')</small>
                                        @else
                                        <div class="acct-file is-empty"><i class="fas fa-paperclip"></i> <span>@lang('ledger_accounts.no_attachment')</span></div>
                                        @endif
                                    @endif
                                </div>
                                <div class="form-group col-md-8 mb-3">
                                    <label for="note">@lang('ledger_accounts.field_note')</label>
                                    <textarea class="form-control" name="note" id="note" rows="3">{{ $val('note') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="acct-foot">
                            <button type="submit" class="acct-btn acct-btn-main" id="acct-review"><i class="fas fa-check"></i> @lang('ledger_accounts.btn_save')</button>
                            <span class="ap-muted">@lang($isEdit ? 'ledger_accounts.confirm_warning_edit' : 'ledger_accounts.confirm_warning_create')</span>
                            <a href="{{ route($route.'.index') }}" class="acct-btn acct-cancel"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
