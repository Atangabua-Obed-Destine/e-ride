{{-- paxhitestbed admin/payroll/pay: the pay modal of one row, through the review dialog. --}}
<div id="payModal-{{ $row->id }}" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form action="{{ route($route.'.pay', $payroll_data->id) }}" method="post"
              data-review="@lang('ledger_payroll.review_pay')" data-review-label="@lang('ledger_payroll.pay_and_post')">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">@lang('ledger_payroll.btn_pay')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('ledger_payroll.btn_close')"></button>
            </div>
            <div class="modal-body text-start">
                <div class="row">
                    <div class="col-md-6">
                        <p><mark class="text-primary">@lang('ledger_payroll.field_staff_id'):</mark> #{{ $row->staff_no }}</p><hr/>
                        <p><mark class="text-primary">@lang('ledger_payroll.field_pay_to'):</mark> {{ $row->payTo() }}</p><hr/>
                    </div>
                    <div class="col-md-6">
                        <p><mark class="text-primary">@lang('ledger_payroll.field_name'):</mark> {{ $row->name }}</p><hr/>
                        <p><mark class="text-primary">@lang('ledger_payroll.field_basic_salary'):</mark> {{ \App\Ledger\Ledger::money($row->basic_salary) }} FCFA / {{ $row->isHourly() ? __('ledger_payroll.salary_type_hourly') : __('ledger_payroll.salary_type_fixed') }}</p><hr/>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">@lang('ledger_payroll.field_net_salary') (FCFA)</label>
                        <input type="text" class="form-control" value="{{ \App\Ledger\Ledger::money($payroll_data->net_salary) }}" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">@lang('ledger_payroll.field_salary_month')</label>
                        <input type="text" class="form-control" value="{{ $payroll_data->salary_month->translatedFormat('F Y') }}" readonly>
                    </div>
                </div>
                @include('ledger.payroll.payroll.pay_fields', ['suffix' => '-'.$row->id])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> @lang('ledger_payroll.btn_close')</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-money-check"></i> @lang('ledger_payroll.btn_pay')</button>
            </div>
        </form>
    </div></div>
</div>
