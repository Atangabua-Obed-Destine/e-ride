{{--
    The lines editor (paxhitestbed create / edit scripts): add and remove lines,
    debit clears credit and the reverse, totals and the "not balanced" row, the
    contra-entry confirmation, select2 account search.
    Needs $accounts (postable, by code) and $initialLines ([account_id, description, debit, credit]).
--}}
@php
    $jeAccounts = $accounts->map(fn ($a) => [
        'id' => $a->id,
        'text' => $a->account_code.' - '.\App\Ledger\Services\Preview::accountName($a).' ['.($a->normal_balance === 'credit' ? __('ledger_je.nb_credit') : __('ledger_je.nb_debit')).']',
        'name' => $a->account_code.' - '.\App\Ledger\Services\Preview::accountName($a),
        'nb' => $a->normal_balance === 'credit' ? 'credit' : 'debit',
    ])->values();
    $jeT = [
        'select_account' => __('ledger_je.select_account'),
        'line_description' => __('ledger_je.line_description'),
        'minimum_two_lines_required' => __('ledger_je.minimum_two_lines_required'),
        'cannot_post_unbalanced_entry' => __('ledger_je.cannot_post_unbalanced_entry'),
        'please_select_account_for_all_lines' => __('ledger_je.please_select_account_for_all_lines'),
        'saving' => __('ledger_je.saving'),
        'contra_title' => __('ledger_je.contra_title'),
        'contra_crediting_debit' => __('ledger_je.contra_crediting_debit'),
        'contra_debiting_credit' => __('ledger_je.contra_debiting_credit'),
        'contra_reduces' => __('ledger_je.contra_reduces'),
        'common_examples' => __('ledger_je.common_examples'),
        'ex_cd' => [__('ledger_je.ex_cd1'), __('ledger_je.ex_cd2'), __('ledger_je.ex_cd3')],
        'ex_dc' => [__('ledger_je.ex_dc1'), __('ledger_je.ex_dc2'), __('ledger_je.ex_dc3')],
        'is_this_correct' => __('ledger_je.is_this_correct'),
        'tip_debit' => __('ledger_je.contra_tip_debit'),
        'tip_credit' => __('ledger_je.contra_tip_credit'),
        'delete' => __('ledger_je.delete'),
    ];
@endphp
<script>
$(function () {
    var T = {!! json_encode($jeT, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var accounts = {!! json_encode($jeAccounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var initial = {!! json_encode(array_values($initialLines), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var byId = {};
    accounts.forEach(function (a) { byId[a.id] = a; });
    var lineCounter = 0;

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function num(v) { return parseFloat(v) || 0; }
    function fmt(n) {
        if (typeof __number_f === 'function') { return __number_f(n); }
        return n.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 2});
    }

    function addLine(data) {
        data = data || {};
        lineCounter++;
        var n = lineCounter;
        var options = '<option value="">' + esc(T.select_account) + '</option>';
        accounts.forEach(function (a) {
            options += '<option value="' + a.id + '" data-nb="' + a.nb + '"' + (String(data.account_id) === String(a.id) ? ' selected' : '') + '>' + esc(a.text) + '</option>';
        });
        var row = '<tr class="entry-line" data-line="' + n + '">'
            + '<td class="text-center line-no"></td>'
            + '<td><select class="form-control input-sm account-select" name="lines[' + n + '][account_id]" required>' + options + '</select></td>'
            + '<td><input type="text" class="form-control input-sm" name="lines[' + n + '][description]" value="' + esc(data.description) + '" placeholder="' + esc(T.line_description) + '"></td>'
            + '<td><input type="number" step="0.01" min="0" class="form-control input-sm debit-input" name="lines[' + n + '][debit]" value="' + esc(data.debit != null ? data.debit : 0) + '"></td>'
            + '<td><input type="number" step="0.01" min="0" class="form-control input-sm credit-input" name="lines[' + n + '][credit]" value="' + esc(data.credit != null ? data.credit : 0) + '"></td>'
            + '<td class="text-center"><button type="button" class="btn btn-danger btn-xs remove-line-btn" title="' + esc(T['delete']) + '"><i class="fa fa-trash"></i></button></td>'
            + '</tr>';
        $('#entry-lines-body').append(row);
        var $row = $('#entry-lines-body tr.entry-line').last();
        if ($.fn.select2) {
            $row.find('.account-select').select2({placeholder: T.select_account, width: '100%'});
        }
        renumberLines();
    }

    function renumberLines() {
        $('#entry-lines-body .entry-line').each(function (i) { $(this).find('.line-no').text(i + 1); });
    }

    function calculateTotals() {
        var dr = 0, cr = 0;
        $('#entry-lines-body .debit-input').each(function () { dr += num($(this).val()); });
        $('#entry-lines-body .credit-input').each(function () { cr += num($(this).val()); });
        $('#total-debit').text(fmt(dr));
        $('#total-credit').text(fmt(cr));
        var diff = Math.abs(dr - cr);
        $('#difference').text(fmt(diff));
        $('#balance-row').toggle(diff > 0.01);
        $('#save-post-btn').prop('disabled', diff > 0.01);
        return diff;
    }

    // Contra entry: crediting a debit account or debiting a credit account asks first.
    function checkAccountBalance($row) {
        var $sel = $row.find('.account-select');
        var acc = byId[$sel.val()];
        if (!acc) { return; }
        var $dr = $row.find('.debit-input'), $cr = $row.find('.credit-input');
        var d = num($dr.val()), c = num($cr.val());
        if (acc.nb === 'debit' && c > 0) {
            $cr.addClass('border-warning').attr('title', T.tip_debit);
            if (!confirm('⚠️ ' + T.contra_title + '\n\n' + T.contra_crediting_debit + '\n"' + acc.name + '"\n\n' + T.contra_reduces
                + '\n\n' + T.common_examples + '\n• ' + T.ex_cd.join('\n• ') + '\n\n' + T.is_this_correct)) {
                $cr.val(0).removeClass('border-warning');
                calculateTotals();
            }
        } else if (acc.nb === 'credit' && d > 0) {
            $dr.addClass('border-warning').attr('title', T.tip_credit);
            if (!confirm('⚠️ ' + T.contra_title + '\n\n' + T.contra_debiting_credit + '\n"' + acc.name + '"\n\n' + T.contra_reduces
                + '\n\n' + T.common_examples + '\n• ' + T.ex_dc.join('\n• ') + '\n\n' + T.is_this_correct)) {
                $dr.val(0).removeClass('border-warning');
                calculateTotals();
            }
        } else {
            $dr.removeClass('border-warning').attr('title', '');
            $cr.removeClass('border-warning').attr('title', '');
        }
    }

    var $body = $('#entry-lines-body');
    $body.on('input', '.debit-input, .credit-input', function () {
        var $row = $(this).closest('tr');
        var isDebit = $(this).hasClass('debit-input');
        var $other = $row.find(isDebit ? '.credit-input' : '.debit-input');
        if (num($(this).val()) > 0) {
            $other.val(0).removeClass('border-success border-warning');
            $(this).removeClass('border-warning').addClass('border-success');
        }
        calculateTotals();
        checkAccountBalance($row);
    });
    $body.on('change', '.account-select', function () { checkAccountBalance($(this).closest('tr')); });
    $body.on('click', '.remove-line-btn', function () {
        if ($('#entry-lines-body .entry-line').length > 2) {
            $(this).closest('tr').remove();
            renumberLines();
            calculateTotals();
        } else {
            alert(T.minimum_two_lines_required);
        }
    });
    $('#add-line-btn').on('click', function (e) { e.preventDefault(); addLine(); });

    // what the form checks before saving (and before the review of "Save & Post")
    window.jeCheckForm = function (posting) {
        if ($('#entry-lines-body .entry-line').length < 2) { alert(T.minimum_two_lines_required); return false; }
        if (posting && calculateTotals() > 0.01) { alert(T.cannot_post_unbalanced_entry); return false; }
        var empty = false;
        $('#entry-lines-body .account-select').each(function () { if (!$(this).val()) { empty = true; } });
        if (empty) { alert(T.please_select_account_for_all_lines); return false; }
        return true;
    };
    $('#journalEntryForm').on('submit', function () {
        if (!window.jeCheckForm(false)) { return false; }
        $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + esc(T.saving));
        return true;
    });

    initial.forEach(function (l) { addLine(l); });
    while ($('#entry-lines-body .entry-line').length < 2) { addLine(); }
    calculateTotals();
});
</script>
