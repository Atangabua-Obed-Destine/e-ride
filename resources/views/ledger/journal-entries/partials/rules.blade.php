{{-- Accounting Entry Rules help box (paxhitestbed create / edit). --}}
<div class="alert alert-info alert-dismissible je-rules" role="alert">
    <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('ledger.close') }}"><span aria-hidden="true">&times;</span></button>
    <h5 style="margin-top: 0; font-weight: 600;"><i class="fa fa-info-circle"></i> {{ __('ledger_je.rules_title') }}</h5>
    <ul class="small">
        <li><strong>{{ __('ledger_je.rule_debit_label') }}</strong> {{ __('ledger_je.rule_debit') }}</li>
        <li><strong>{{ __('ledger_je.rule_credit_label') }}</strong> {{ __('ledger_je.rule_credit') }}</li>
        <li><strong>{{ __('ledger_je.rule_expenses_label') }}</strong> {{ __('ledger_je.rule_expenses') }}</li>
        <li>{{ __('ledger_je.rule_one_side') }}</li>
        <li>{{ __('ledger_je.rule_balance') }}</li>
        <li><span class="label label-warning">{{ __('ledger_je.rule_yellow_label') }}</span> {{ __('ledger_je.rule_yellow') }} <strong>{{ __('ledger_je.rule_yellow_confirm') }}</strong></li>
        <li><span class="label label-success">{{ __('ledger_je.rule_green_label') }}</span> {{ __('ledger_je.rule_green') }}</li>
        <li><i class="fa fa-exclamation-triangle text-warning"></i> <strong>{{ __('ledger_je.rule_confirm_label') }}</strong> {{ __('ledger_je.rule_confirm') }}</li>
    </ul>
</div>
