@extends('layouts.app')
@section('title', __('ledger_map.title_settings'))

@section('content')
@include('ledger.partials.style')
<style>
    .mapping-card { border-left: 4px solid #3c8dbc; margin-bottom: 20px; }
    .mapping-card > .panel-heading h4 { margin: 0; font-weight: 600; }
    .mapping-row { padding: 10px 15px; border-bottom: 1px solid #e9ecef; }
    .mapping-row:last-child { border-bottom: none; }
    .mapping-row:hover { background-color: #f8f9fa; }
    .mapping-row .row { display: flex; flex-wrap: wrap; align-items: center; }
    .category-name { font-weight: 500; }
    .category-name .badge { margin-right: 6px; }
    .mapping-row label { font-weight: normal; color: #64748b; font-size: 0.8rem; margin-bottom: 2px; }
    .mapping-status { display: block; margin-top: 4px; font-size: 0.8rem; }
</style>
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_map.title_settings')
        <small>@lang('ledger_map.sub_settings')</small></h1>
</section>
<section class="content">
    <div class="ap-card">
        <div class="ap-toolbar" style="justify-content: flex-end; margin-bottom: 12px;">
            @can('transaction-mapping-view')
            <a href="{{ route('ledger.mappings.transactions') }}" class="btn btn-info btn-sm"><i class="fa fa-list"></i> @lang('ledger_map.view_transactions')</a>
            @endcan
            <a href="{{ route('ledger.cash-accounts') }}" class="btn btn-default btn-sm"><i class="fa fa-university"></i> @lang('ledger_map.cash_and_bank')</a>
            <button type="submit" form="mappingsForm" class="btn btn-primary btn-sm" id="saveAllMappings"><i class="fa fa-save"></i> @lang('ledger_map.save_all')</button>
        </div>

        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> <strong>@lang('ledger_map.instructions'):</strong>
            @lang('ledger_map.instructions_text')
        </div>

        <form id="mappingsForm" method="POST" action="{{ route('ledger.mappings.save-default') }}">
            @csrf
            @php($i = 0)
            @foreach($groups as $gkey => $group)
            <div class="panel panel-{{ $group['color'] }} mapping-card" id="group-{{ $gkey }}">
                <div class="panel-heading"><h4><i class="fa {{ $group['icon'] }}"></i> {{ $group['title'] }}</h4></div>
                <div class="panel-body" style="padding: 0;">
                    @if($gkey === 'expenses' && count($group['rows']) <= 2)
                    <div class="mapping-row text-muted text-center">@lang('ledger_map.no_expense_categories')</div>
                    @endif
                    @foreach($group['rows'] as $row)
                    @php($rid = $row['type'].'_'.($row['key'] === '' ? 'default' : $row['key']))
                    <div class="mapping-row" data-row-id="{{ $rid }}">
                        <input type="hidden" name="mappings[{{ $i }}][mapping_type]" value="{{ $row['type'] }}" data-field="type">
                        <input type="hidden" name="mappings[{{ $i }}][category_key]" value="{{ $row['key'] }}" data-field="key">
                        <div class="row">
                            <div class="col-md-3 col-sm-12">
                                <div class="category-name">
                                    <span class="badge bg-{{ ['primary' => 'blue', 'success' => 'green', 'danger' => 'red', 'info' => 'aqua', 'warning' => 'yellow'][$group['color']] }}">{{ $loop->iteration }}</span>
                                    {{ $row['label'] }}
                                </div>
                                @if($row['note'])<small class="ap-muted">{{ $row['note'] }}</small>@endif
                            </div>
                            <div class="col-md-5 col-sm-8">
                                <label for="acc_{{ $rid }}">@lang('ledger_map.account')</label>
                                <select class="form-control input-sm account-select" id="acc_{{ $rid }}" name="mappings[{{ $i }}][account_id]"
                                        data-value="{{ $row['account'] }}" style="width: 100%;">
                                    <option value="">@lang('ledger_map.select_account')</option>
                                    @if($row['account'] && ($a = $accounts->firstWhere('id', $row['account'])))
                                    <option value="{{ $a->id }}" selected>{{ $a->account_code }} - {{ \App\Ledger\Services\Preview::accountName($a) }}</option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-4">
                                <label>@lang('ledger_map.posts_as')</label>
                                <div class="ap-muted">{{ $row['hint'] }}</div>
                            </div>
                            <div class="col-md-2 col-sm-12 text-center">
                                @can('transaction-mapping-settings')
                                <button type="button" class="btn btn-sm btn-success save-single-mapping" data-row-id="{{ $rid }}">
                                    <i class="fa fa-save"></i> @lang('ledger_map.save')
                                </button>
                                @endcan
                                <span class="mapping-status">
                                    @if($row['off'])
                                    <i class="fa fa-ban text-danger"></i> <span class="text-danger">{{ __('ledger_map.switched_off') }}</span>
                                    @elseif($row['configured'])
                                    <i class="fa fa-check-circle text-success"></i> <span class="ap-muted">@lang('ledger_map.configured')</span>
                                    @else
                                    <i class="fa fa-exclamation-circle text-warning"></i> <span class="ap-muted">@lang('ledger_map.not_configured')</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                    @php($i++)
                    @endforeach
                    @if(!empty($group['help']))
                    <div style="padding: 10px 15px; background: #f8fafc;">
                        <small class="ap-muted"><i class="fa fa-info-circle"></i> {{ $group['help'] }}
                            <a href="{{ route('ledger.cash-accounts') }}">@lang('ledger_map.cash_and_bank')</a></small>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach

            @can('transaction-mapping-settings')
            <div class="text-center" style="margin-top: 15px;">
                <button type="submit" class="btn btn-primary btn-lg" id="saveAllMappingsBottom"><i class="fa fa-save"></i> @lang('ledger_map.save_all')</button>
            </div>
            @endcan
        </form>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(function () {
    // One list of accounts for every picker (sending it once per row made the page heavy).
    var accounts = {!! json_encode($accounts->map(fn ($a) => ['id' => $a->id, 'text' => $a->account_code.' - '.\App\Ledger\Services\Preview::accountName($a)])->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var T = {!! json_encode(['select' => __('ledger_map.select_account'), 'none' => __('ledger_map.no_accounts_found'), 'saving' => __('ledger_map.saving'),
        'saved' => __('ledger_map.mapping_saved'), 'pick' => __('ledger_map.please_select_account'), 'configured' => __('ledger_map.configured'),
        'error' => __('ledger_map.error')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var html = accounts.map(function (a) { return $('<option>').val(a.id).text(a.text)[0].outerHTML; }).join('');

    $('.account-select').each(function () {
        var $s = $(this), v = String($s.data('value') || '');
        $s.find('option[value!=""]').remove();
        $s.append(html).val(v);
        $s.select2({ width: '100%', placeholder: T.select, allowClear: true, language: { noResults: function () { return T.none; } } });
    });

    // Save one row (paxhitestbed's per-row Save): posts just that mapping.
    $('.save-single-mapping').on('click', function () {
        var btn = $(this), row = $('[data-row-id="' + btn.data('row-id') + '"]').first();
        var sel = row.find('.account-select');
        if (!sel.val()) {
            toastr.warning(T.pick);
            return;
        }
        var original = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + T.saving + '…');
        $.ajax({
            url: @json(route('ledger.mappings.save-default')),
            type: 'POST',
            dataType: 'json',
            data: { _token: @json(csrf_token()), mappings: [{
                mapping_type: row.find('[data-field=type]').val(),
                category_key: row.find('[data-field=key]').val(),
                account_id: sel.val()
            }] },
            success: function (r) {
                if (r.success) {
                    toastr.success(T.saved);
                    row.find('.mapping-status').html('<i class="fa fa-check-circle text-success"></i> <span class="ap-muted"></span>')
                        .find('span').text(T.configured);
                } else {
                    toastr.error(r.msg || T.error);
                }
            },
            error: function (xhr) { toastr.error((xhr.responseJSON && (xhr.responseJSON.msg || xhr.responseJSON.message)) || T.error); },
            complete: function () { btn.prop('disabled', false).html(original); }
        });
    });
});
</script>
@endsection
