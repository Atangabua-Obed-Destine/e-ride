{{--
  One income or expense, read back (paxhitestbed admin/account/partials/record_modal): the
  amount first, then a label / value list. Rendered inside the list's loop ($row, $title, $route, $access).
--}}
@php $fileUrl = \App\Http\Controllers\Ledger\IncomeExpenseController::fileUrl($row->attach); @endphp
<div id="showModal-{{ $row->id }}" class="modal fade acct-record" tabindex="-1" aria-labelledby="showModalLabel-{{ $row->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-start">
            <div class="modal-header">
                <div>
                    <div class="acct-label">{{ $title }}</div>
                    <h5 class="modal-title mt-1" id="showModalLabel-{{ $row->id }}" style="font-size:.95rem;font-weight:700;">
                        {{ $row->date->format('d/m/Y') }}
                        @if($row->invoice_id)<span class="acct-ref ms-2">{{ $row->invoice_id }}</span>@endif
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('ledger_accounts.btn_close')"></button>
            </div>
            <div class="modal-body">
                <div class="acct-record-head">
                    <div class="acct-label">@lang('ledger_accounts.field_amount')</div>
                    <div class="v acct-num mt-1">{{ \App\Ledger\Ledger::money($row->amount) }}<span class="acct-cur" style="font-size:.9rem;margin-left:.25rem;">FCFA</span></div>
                    <div class="t">{{ $row->title }}</div>
                </div>
                <dl class="acct-rows mb-0 pt-2 pb-2">
                    <div class="acct-row"><dt>@lang('ledger_accounts.field_category')</dt><dd><span class="acct-tag acct-tag-flow">{{ $row->category->name ?? '-' }}</span></dd></div>
                    <div class="acct-row"><dt>@lang('ledger_accounts.field_date')</dt><dd>{{ $row->date->translatedFormat('j F Y') }}</dd></div>
                    <div class="acct-row"><dt>@lang('ledger_accounts.field_payment_method')</dt><dd>{{ \App\Ledger\Services\PaymentBook::methodLabel($row->payment_method) }}</dd></div>
                    @if($row->paymentAccount)
                    <div class="acct-row"><dt>@lang('ledger_accounts.field_payment_account')</dt><dd>{{ $row->paymentAccount->title }}</dd></div>
                    @endif
                    @if($row->invoice_id)
                    <div class="acct-row"><dt>@lang('ledger_accounts.field_invoice_id')</dt><dd class="acct-num">{{ $row->invoice_id }}</dd></div>
                    @endif
                    @if($row->reference)
                    <div class="acct-row"><dt>@lang('ledger_accounts.field_reference')</dt><dd>{{ $row->reference }}</dd></div>
                    @endif
                    <div class="acct-row"><dt>@lang('ledger_accounts.field_recorded_by')</dt><dd>{{ \App\Ledger\Services\PaymentBook::userName($row->recordedBy) }}</dd></div>
                    <div class="acct-row"><dt>@lang('ledger_accounts.field_attach')</dt><dd>
                        @if($fileUrl)<a href="{{ $fileUrl }}" target="_blank" rel="noopener"><i class="fas fa-paperclip me-1"></i>{{ basename($row->attach) }}</a>
                        @else<span style="font-weight:500;color:var(--muted);">@lang('ledger_accounts.no_attachment')</span>@endif
                    </dd></div>
                </dl>
                @if($row->note)
                <div class="acct-note"><div class="acct-label mb-1">@lang('ledger_accounts.field_note')</div>{{ $row->note }}</div>
                @endif
            </div>
            <div class="modal-footer">
                <a href="{{ route($route.'.show', $row->id) }}" class="acct-btn"><i class="fas fa-book"></i> @lang('ledger_accounts.open_with_entry')</a>
                @can($access.'-edit')
                <a href="{{ route($route.'.edit', $row->id) }}" class="acct-btn"><i class="far fa-edit"></i> @lang('ledger_accounts.btn_edit')</a>
                @endcan
                <button type="button" class="acct-btn acct-btn-main" data-bs-dismiss="modal">@lang('ledger_accounts.btn_close')</button>
            </div>
        </div>
    </div>
</div>
