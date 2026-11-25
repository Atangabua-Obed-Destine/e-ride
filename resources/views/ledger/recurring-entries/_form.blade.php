{{--
    The recurring entry form (paxhitestbed recurring-entries/create): template, schedule, lines.
    $recurringEntry is null on create. paxhitestbed's "fiscal year" and "notify before days"
    fields are left out: neither was stored (each run goes into the fiscal year of its own date).
--}}
@php
    $e = $recurringEntry ?? null;
    $freq = old('frequency', $e ? ($e->frequency === 'annually' ? 'yearly' : $e->frequency) : 'monthly');
    $oldLines = old('lines');
    $lines = is_array($oldLines) ? array_values($oldLines) : ($e ? $e->lines->map(fn ($l) => [
        'account_id' => $l->account_id, 'description' => $l->description, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit,
    ])->all() : []);
    $locked = $e && (int) $e->occurrences_completed > 0;
@endphp
@if ($errors->any())
<div class="alert alert-danger">
    <h4 style="margin-top: 0;"><i class="icon fa fa-ban"></i> @lang('ledger_periodend.validation_errors')</h4>
    <ul style="margin-bottom: 0;">
        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

{{-- Template --}}
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="template_name">@lang('ledger_periodend.template_name') <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="template_name" name="template_name" value="{{ old('template_name', $e->name ?? '') }}" required maxlength="255">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="journal_type">@lang('ledger_periodend.journal_type') <span class="text-danger">*</span></label>
            <select class="form-control" id="journal_type" name="journal_type" required>
                @foreach(['general', 'sales', 'purchase', 'cash', 'bank'] as $t)
                <option value="{{ $t }}" @selected(old('journal_type', $e->journal_type ?? 'general') == $t)>{{ \App\Http\Controllers\Ledger\JournalEntryController::typeLabel($t) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            <label for="description">@lang('ledger_periodend.description')</label>
            <textarea class="form-control" id="description" name="description" rows="2" maxlength="1000">{{ old('description', $e->description ?? '') }}</textarea>
        </div>
    </div>
</div>

{{-- Schedule --}}
<hr>
<h4>@lang('ledger_periodend.schedule')</h4>
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            <label for="frequency">@lang('ledger_periodend.frequency') <span class="text-danger">*</span></label>
            <select class="form-control" id="frequency" name="frequency" required>
                @foreach(['daily', 'weekly', 'monthly', 'quarterly', 'yearly'] as $f)
                <option value="{{ $f }}" @selected($freq == $f)>@lang('ledger_periodend.'.$f)</option>
                @endforeach
                @if(in_array($freq, ['biweekly', 'semiannually']))
                <option value="{{ $freq }}" selected>@lang('ledger_periodend.'.$freq)</option>
                @endif
            </select>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="day_of_month">@lang('ledger_periodend.day_of_month')</label>
            <input type="number" class="form-control" id="day_of_month" name="day_of_month" value="{{ old('day_of_month', $e ? $e->day_of_month : 1) }}" min="1" max="31">
            <small class="text-muted">@lang('ledger_periodend.for_monthly_entries')</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="start_date">@lang('ledger_periodend.start_date') <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ old('start_date', $e ? $e->start_date->toDateString() : date('Y-m-d')) }}" required @disabled($locked)>
            @if($locked)<small class="text-muted">@lang('ledger_periodend.start_locked')</small>@endif
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="end_date">@lang('ledger_periodend.end_date')</label>
            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ old('end_date', $e && $e->end_date ? $e->end_date->toDateString() : '') }}">
            <small class="text-muted">@lang('ledger_periodend.leave_blank_for_indefinite')</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="total_occurrences">@lang('ledger_periodend.total_occurrences')</label>
            <input type="number" class="form-control" id="total_occurrences" name="total_occurrences" value="{{ old('total_occurrences', $e->occurrences ?? '') }}" min="1">
            <small class="text-muted">@lang('ledger_periodend.leave_blank_for_unlimited')</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="auto_post">@lang('ledger_periodend.auto_post')</label>
            <div class="checkbox" style="margin-top: 4px;">
                <label><input type="checkbox" id="auto_post" name="auto_post" value="1" @checked(old('auto_post', $e->auto_post ?? false))> @lang('ledger_periodend.automatically_post_entries')</label>
            </div>
        </div>
    </div>
</div>

{{-- Lines --}}
<hr>
<h4>@lang('ledger_periodend.entry_lines')</h4>
<div class="table-responsive">
    <table class="table table-bordered ap-table" id="lines-table">
        <thead>
            <tr>
                <th width="5%">@lang('ledger_periodend.line')</th>
                <th width="35%">@lang('ledger_periodend.account') <span class="text-danger">*</span></th>
                <th width="25%">@lang('ledger_periodend.description')</th>
                <th width="13%">@lang('ledger_periodend.debit')</th>
                <th width="13%">@lang('ledger_periodend.credit')</th>
                <th width="9%">@lang('ledger_periodend.action')</th>
            </tr>
        </thead>
        <tbody id="lines-body"></tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-right"><strong>@lang('ledger_periodend.total'):</strong></td>
                <td class="ap-num"><strong id="total-debit">0</strong></td>
                <td class="ap-num"><strong id="total-credit">0</strong></td>
                <td></td>
            </tr>
            <tr id="balance-row" style="display: none;">
                <td colspan="6" class="text-center"><span class="ap-chip unpaid"><i class="fa fa-exclamation-triangle"></i> @lang('ledger_periodend.entry_not_balanced')</span></td>
            </tr>
        </tfoot>
    </table>
</div>
<button type="button" class="btn btn-success btn-sm" id="add-line-btn"><i class="fa fa-plus"></i> @lang('ledger_periodend.add_line')</button>

@section('javascript')
<script>
$(function () {
    var lineCounter = 0;
    var accounts = {!! json_encode($accounts->map(fn ($a) => ['id' => $a->id, 'label' => $a->account_code.' - '.\App\Ledger\Services\Preview::accountName($a)])->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var initial = {!! json_encode($lines, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var T = {!! json_encode(['select' => __('ledger_periodend.select_account'), 'min' => __('ledger_periodend.minimum_two_lines_required'), 'balanced' => __('ledger_periodend.entry_must_be_balanced')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };

    function addLine(l) {
        l = l || {};
        lineCounter++;
        var options = '<option value="">' + esc(T.select) + '</option>';
        accounts.forEach(function (a) {
            options += '<option value="' + a.id + '"' + (String(l.account_id) === String(a.id) ? ' selected' : '') + '>' + esc(a.label) + '</option>';
        });
        var row = '<tr class="entry-line">'
            + '<td class="text-center line-no"></td>'
            + '<td><select class="form-control input-sm account-select" name="lines[' + lineCounter + '][account_id]" required>' + options + '</select></td>'
            + '<td><input type="text" class="form-control input-sm" name="lines[' + lineCounter + '][description]" value="' + esc(l.description) + '"></td>'
            + '<td><input type="number" step="0.01" min="0" class="form-control input-sm debit-input" name="lines[' + lineCounter + '][debit]" value="' + (parseFloat(l.debit) || 0) + '"></td>'
            + '<td><input type="number" step="0.01" min="0" class="form-control input-sm credit-input" name="lines[' + lineCounter + '][credit]" value="' + (parseFloat(l.credit) || 0) + '"></td>'
            + '<td class="text-center"><button type="button" class="btn btn-danger btn-xs remove-line-btn"><i class="fa fa-trash"></i></button></td>'
            + '</tr>';
        $('#lines-body').append(row);
        var sel = $('#lines-body tr:last .account-select');
        if ($.fn.select2) { sel.select2({ width: '100%' }); }
        renumber();
    }
    function renumber() { $('.entry-line').each(function (i) { $(this).find('.line-no').text(i + 1); }); }
    function totals() {
        var d = 0, c = 0;
        $('.debit-input').each(function () { d += parseFloat($(this).val()) || 0; });
        $('.credit-input').each(function () { c += parseFloat($(this).val()) || 0; });
        $('#total-debit').text(d.toLocaleString('fr-FR'));
        $('#total-credit').text(c.toLocaleString('fr-FR'));
        $('#balance-row').toggle(Math.abs(d - c) > 0.01);
        return Math.abs(d - c) <= 0.01;
    }

    $('#lines-body').on('click', '.remove-line-btn', function () {
        if ($('.entry-line').length > 2) { $(this).closest('tr').remove(); renumber(); totals(); } else { alert(T.min); }
    });
    // Debit / credit: one or the other
    $('#lines-body').on('input', '.debit-input', function () {
        if (parseFloat($(this).val()) > 0) { $(this).closest('tr').find('.credit-input').val(0); }
        totals();
    });
    $('#lines-body').on('input', '.credit-input', function () {
        if (parseFloat($(this).val()) > 0) { $(this).closest('tr').find('.debit-input').val(0); }
        totals();
    });
    $('#add-line-btn').on('click', function () { addLine(); });
    $('#recurringEntryForm').on('submit', function (e) {
        if (!totals()) { e.preventDefault(); alert(T.balanced); return false; }
    });

    (initial.length ? initial : [{}, {}]).forEach(function (l) { addLine(l); });
    if ($('.entry-line').length < 2) { addLine(); }
    totals();
});
</script>
@endsection
