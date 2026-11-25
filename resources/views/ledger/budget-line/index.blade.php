@extends('layouts.app')
@section('title', __('ledger_budget.budget_lines'))

@section('css')
<style>
    .bl-guide { border: 1px solid #cfe0f7; border-radius: 8px; background: #f5f9ff; margin-bottom: 16px; }
    .bl-guide-toggle { display: block; width: 100%; background: transparent; border: 0; padding: 12px 16px; text-align: left; color: #14396e; font-weight: 700; }
    .bl-guide-body { padding: 0 16px 16px; }
    .bl-guide-body h5 { font-size: 11px; font-weight: 800; letter-spacing: .9px; text-transform: uppercase; color: #7189b0; margin: 14px 0 6px; }
    .bl-guide-body p, .bl-guide-body li { color: #33507d; line-height: 1.6; }
    .bl-example { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; background: #fff; border: 1px solid #dbe7f8; border-radius: 6px; padding: 10px 14px; color: #2c4a7a; white-space: pre; overflow-x: auto; }
    .bl-nest, .bl-children { list-style: none; margin: 0; padding: 0; }
    .bl-children { padding-left: 28px; min-height: 8px; padding-bottom: 4px; }
    .bl-row { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #e3e7ee; border-radius: 6px; padding: 7px 10px; margin: 4px 0; }
    .bl-item.bl-is-header > .bl-row { background: #eef2f7; border-color: #cfd8e3; }
    .bl-row.bl-retired { opacity: .55; }
    .bl-grip { cursor: move; color: #9aa7ba; padding: 2px 5px; flex-shrink: 0; }
    .bl-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: #55637a; min-width: 48px; flex-shrink: 0; }
    .bl-main { flex-grow: 1; min-width: 0; }
    .bl-name { color: #212529; }
    .bl-item.bl-is-header > .bl-row .bl-name { font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
    .bl-account { font-size: 11px; color: #6c757d; display: block; }
    .bl-unmapped { font-size: 11px; color: #b3701a; display: block; }
    .bl-unmapped-btn, .bl-change-btn { background: transparent; border: 0; padding: 0; text-align: left; text-decoration: underline dotted; }
    .bl-change-btn { font-size: 11px; color: #5b7bab; }
    .bl-unmapped-cta { font-weight: 700; margin-left: 5px; text-decoration: none; }
    .bl-meta { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
    .bl-flag { font-size: 10px; padding: 1px 5px; border-radius: 3px; border: 1px solid; white-space: nowrap; }
    .bl-flag-local { color: #0b6b3a; border-color: #b7e0c6; background: #eefaf2; }
    .bl-flag-standard { color: #7a5c00; border-color: #f0e0a8; background: #fdf8e6; }
    .bl-actions { display: flex; gap: 3px; flex-shrink: 0; }
    .bl-section-head { display: flex; align-items: center; justify-content: space-between; background: #212b36; color: #fff; border-radius: 6px; padding: 8px 12px; margin-bottom: 8px; }
    .bl-empty { color: #8a97ab; padding: 6px 0; }
    .bl-placeholder { border: 2px dashed #9aa7ba; border-radius: 6px; height: 40px; margin: 4px 0; background: #f8fafc; }
    .bl-save-state { font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; display: none; margin-right: 8px; }
    .bl-save-state.is-saving { display: inline-block; background: #fff5e0; color: #8a5312; }
    .bl-save-state.is-saved { display: inline-block; background: #e6f7ee; color: #0f7a45; }
    .bl-save-state.is-error { display: inline-block; background: #fdecec; color: #a02020; }
</style>
@endsection

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_budget.budget_lines')</h1>
</section>
<section class="content">
    @include('ledger.budget.partials.messages')

    {{-- The screen explains itself rather than assuming the reader knows what a budget line is. --}}
    <div class="bl-guide">
        <button class="bl-guide-toggle" type="button" data-toggle="collapse" data-target="#blGuide" aria-expanded="{{ $lines->isEmpty() ? 'true' : 'false' }}">
            <i class="fa fa-lightbulb-o"></i> @lang('ledger_budget.guide_title') <i class="fa fa-chevron-down pull-right"></i>
        </button>
        <div class="collapse {{ $lines->isEmpty() ? 'in' : '' }}" id="blGuide">
            <div class="bl-guide-body">
                <h5>@lang('ledger_budget.guide_what_h')</h5>
                <p>@lang('ledger_budget.guide_what')</p>
                <h5>@lang('ledger_budget.guide_headings_h')</h5>
                <p>@lang('ledger_budget.guide_headings')</p>
                <div class="bl-example">60   {{ __('ledger_budget.guide_ex_heading') }}
  604  {{ __('ledger_budget.guide_ex_line') }}
  605  {{ __('ledger_budget.guide_ex_line2') }}
62   {{ __('ledger_budget.guide_ex_next') }}</div>
                <h5>@lang('ledger_budget.guide_order_h')</h5>
                <ul>
                    <li>@lang('ledger_budget.guide_order_1')</li>
                    <li>@lang('ledger_budget.guide_order_2')</li>
                    <li>@lang('ledger_budget.guide_order_3')</li>
                    <li>@lang('ledger_budget.guide_order_4')</li>
                </ul>
                <h5>@lang('ledger_budget.guide_money_h')</h5>
                <p>@lang('ledger_budget.guide_money')</p>
                <p><strong>@lang('ledger_budget.guide_once_h')</strong> @lang('ledger_budget.guide_once')</p>
                <h5>@lang('ledger_budget.guide_retire_h')</h5>
                <p style="margin-bottom: 0;">@lang('ledger_budget.guide_retire')</p>
            </div>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_budget.budget_lines')</h3>
            <div class="box-tools">
                <span class="bl-save-state" id="blSaveState"></span>
                @can('budget-line-create')
                    <form action="{{ route('ledger.budget-line.standard') }}" method="post" style="display: inline;"
                          onsubmit="return confirm(@json(__('ledger_budget.confirm_standard')))">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-sitemap"></i> @lang('ledger_budget.standard_lines')</button>
                    </form>
                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#lineModal" onclick="budgetLineForm(null)">
                        <i class="fa fa-plus"></i> @lang('ledger_budget.add_line')</button>
                @endcan
            </div>
        </div>
        <div class="box-body">
            @foreach(['income', 'expenditure', 'capital'] as $section)
                @php $tree = $grouped[$section] ?? collect(); @endphp
                <div style="margin-bottom: 20px;">
                    <div class="bl-section-head">
                        <span><strong>{{ \App\Ledger\Models\BudgetLine::sectionLabel($section) }}</strong>
                            <span class="badge" style="margin-left: 6px;">{{ $tree->sum(fn ($n) => 1 + $n['children']->count()) }}</span>
                            <small style="margin-left: 8px; opacity: .8;">{{ __('ledger_budget.syscohada_class', ['class' => \App\Ledger\Models\BudgetLine::SECTION_CLASS[$section]]) }}</small>
                        </span>
                        @can('budget-line-edit')
                            <form action="{{ route('ledger.budget-line.auto-sort') }}" method="post" style="display: inline;"
                                  onsubmit="return confirm(@json(__('ledger_budget.confirm_sort')))">
                                @csrf
                                <input type="hidden" name="section" value="{{ $section }}">
                                <button type="submit" class="btn btn-xs btn-default"><i class="fa fa-sort-numeric-asc"></i> @lang('ledger_budget.sort_by_code')</button>
                            </form>
                        @endcan
                    </div>
                    @if($tree->isEmpty())
                        <p class="bl-empty">@lang('ledger_budget.no_lines_in_section')</p>
                    @else
                        <ol class="bl-nest" data-section="{{ $section }}">
                            @foreach($tree as $node)
                                @include('ledger.budget-line.partials.node', ['line' => $node['line'], 'children' => $node['children']])
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- One dialog serves add and edit; budgetLineForm() repoints it. --}}
<div class="modal fade" id="lineModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" id="lineForm" action="{{ route('ledger.budget-line.store') }}">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('ledger_budget.cancel') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="lineModalTitle">@lang('ledger_budget.add_line')</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label for="bl_code">@lang('ledger_budget.code') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="code" id="bl_code" maxlength="20" required>
                            <p class="help-block">@lang('ledger_budget.code_help')</p>
                        </div>
                        <div class="col-md-9 form-group">
                            <label for="bl_name">@lang('ledger_budget.name') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="bl_name" maxlength="191" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="bl_name_fr">@lang('ledger_budget.name_fr')</label>
                            <input type="text" class="form-control" name="name_fr" id="bl_name_fr" maxlength="191">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="bl_section">@lang('ledger_budget.section') <span class="text-danger">*</span></label>
                            <select class="form-control" name="section" id="bl_section" required>
                                @foreach(['income', 'expenditure', 'capital'] as $s)
                                    <option value="{{ $s }}">{{ \App\Ledger\Models\BudgetLine::sectionLabel($s) }}</option>
                                @endforeach
                            </select>
                            <p class="help-block">@lang('ledger_budget.section_help')</p>
                        </div>
                        <div class="col-md-12 form-group">
                            <label for="bl_description">@lang('ledger_budget.description')</label>
                            <textarea class="form-control" name="description" id="bl_description" rows="2" maxlength="500" placeholder="{{ __('ledger_budget.description_placeholder') }}"></textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="bl_parent">@lang('ledger_budget.files_under')</label>
                            <select class="form-control" name="parent_id" id="bl_parent">
                                <option value="">@lang('ledger_budget.stands_alone')</option>
                                @foreach($headers as $header)
                                    <option value="{{ $header->id }}" data-section="{{ $header->section }}">{{ $header->code }} — {{ $header->display_name }} ({{ \App\Ledger\Models\BudgetLine::sectionLabel($header->section) }})</option>
                                @endforeach
                            </select>
                            <p class="help-block">@lang('ledger_budget.files_under_help')</p>
                        </div>
                        <div class="col-md-6 form-group bl-target">
                            <label for="bl_account">@lang('ledger_budget.ledger_account')</label>
                            <select class="form-control" name="account_id" id="bl_account"></select>
                            <p class="help-block">@lang('ledger_budget.ledger_account_help')</p>
                        </div>
                        <div class="col-md-6 form-group bl-target">
                            <label for="bl_category">@lang('ledger_budget.expense_category')</label>
                            <select class="form-control" name="expense_category_id" id="bl_category">
                                <option value="">@lang('ledger_budget.any_category')</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <p class="help-block">@lang('ledger_budget.expense_category_help')</p>
                        </div>
                        <div class="col-md-6 form-group bl-target">
                            <label for="bl_location">@lang('ledger_budget.location')</label>
                            <select class="form-control" name="location_id" id="bl_location">
                                <option value="">@lang('ledger_budget.all_locations')</option>
                                @foreach($locations as $l)
                                    <option value="{{ $l->id }}">{{ $l->name }}</option>
                                @endforeach
                            </select>
                            <p class="help-block">@lang('ledger_budget.location_help')</p>
                        </div>
                        <input type="hidden" name="sort_order" id="bl_sort_order" value="">
                        <div class="col-md-12">
                            <div class="checkbox">
                                <label><input type="checkbox" name="is_header" value="1" id="bl_is_header"> <strong>@lang('ledger_budget.is_heading')</strong>
                                    <span class="help-block" style="margin: 0;">@lang('ledger_budget.is_heading_help')</span></label>
                            </div>
                            <div class="checkbox">
                                <label><input type="checkbox" name="status" value="1" id="bl_status" checked> <strong>@lang('ledger_budget.on_the_sheet')</strong>
                                    <span class="help-block" style="margin: 0;">@lang('ledger_budget.on_the_sheet_help')</span></label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('ledger_budget.cancel')</button>
                    <button type="submit" class="btn btn-primary">@lang('ledger_budget.save')</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('javascript')
<script>
(function () {
    'use strict';
    var ACCOUNTS = {!! json_encode($accountOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var T = {!! json_encode([
        'add' => __('ledger_budget.add_line'), 'edit' => __('ledger_budget.edit_line'), 'none' => __('ledger_budget.no_account_option'),
        'saving' => __('ledger_budget.saving_order'), 'saved' => __('ledger_budget.order_saved'), 'error' => __('ledger_budget.order_not_saved'),
        'heading' => __('ledger_budget.heading_marker'),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var storeUrl = {!! json_encode(route('ledger.budget-line.store')) !!};
    var updateUrl = {!! json_encode(url('accounting/budget-line')) !!};

    function set(id, value) { var el = document.getElementById(id); if (el) { el.value = value === null || value === undefined ? '' : value; } }

    // Only accounts of the section's own class: 7 income, 6 expenditure, 2 capital.
    function fillAccounts(section, selected) {
        var select = document.getElementById('bl_account');
        select.innerHTML = '';
        var none = document.createElement('option');
        none.value = '';
        none.textContent = T.none;
        select.appendChild(none);
        (ACCOUNTS[section] || []).forEach(function (a) {
            var o = document.createElement('option');
            o.value = a.id;
            o.textContent = a.label + (a.heading ? ' ' + T.heading : '');
            if (String(a.id) === String(selected || '')) { o.selected = true; }
            select.appendChild(o);
        });
        // Headings of another section are not offered as parents.
        $('#bl_parent option[data-section]').each(function () {
            var other = this.getAttribute('data-section') !== section;
            this.disabled = other;
            if (other && this.selected) { $('#bl_parent').val(''); }
        });
    }
    function toggleHeaderFields() {
        var isHeader = document.getElementById('bl_is_header').checked;
        var parent = document.getElementById('bl_parent');
        parent.disabled = isHeader;
        if (isHeader) { parent.value = ''; }
        $('.bl-target').toggle(!isHeader);
    }
    window.budgetLineForm = function (line) {
        var isEdit = !!line;
        document.getElementById('lineModalTitle').textContent = isEdit ? T.edit : T.add;
        document.getElementById('lineForm').action = isEdit ? updateUrl + '/' + line.id + '/update' : storeUrl;
        set('bl_code', isEdit ? line.code : '');
        set('bl_name', isEdit ? line.name : '');
        set('bl_name_fr', isEdit ? line.name_fr : '');
        set('bl_description', isEdit ? line.description : '');
        set('bl_section', isEdit ? line.section : 'expenditure');
        set('bl_sort_order', isEdit ? line.sort_order : '');
        set('bl_parent', isEdit ? (line.parent_id || '') : '');
        set('bl_category', isEdit ? (line.expense_category_id || '') : '');
        set('bl_location', isEdit ? (line.location_id || '') : '');
        document.getElementById('bl_is_header').checked = isEdit ? !!line.is_header : false;
        document.getElementById('bl_status').checked = isEdit ? !!line.status : true;
        fillAccounts(isEdit ? line.section : 'expenditure', isEdit ? line.account_id : null);
        toggleHeaderFields();
    };

    $(function () {
        $('#bl_is_header').on('change', toggleHeaderFields);
        $('#bl_section').on('change', function () { fillAccounts(this.value, $('#bl_account').val()); });
        @if($errors->any() && old('code') !== null)
            // A refused save reopens the dialog with what was typed.
            budgetLineForm(null);
            set('bl_code', @json(old('code'))); set('bl_name', @json(old('name'))); set('bl_name_fr', @json(old('name_fr')));
            set('bl_section', @json(old('section', 'expenditure'))); set('bl_description', @json(old('description')));
            fillAccounts(@json(old('section', 'expenditure')), @json(old('account_id')));
            set('bl_parent', @json(old('parent_id'))); set('bl_category', @json(old('expense_category_id'))); set('bl_location', @json(old('location_id')));
            $('#lineModal').modal('show');
        @endif

        @can('budget-line-edit')
        if (!$.fn.sortable) { return; }
        var $state = $('#blSaveState');
        function showState(kind, text) {
            $state.removeClass('is-saving is-saved is-error').addClass(kind).text(text);
            if (kind === 'is-saved') { window.setTimeout(function () { $state.removeClass('is-saved').text(''); }, 2500); }
        }
        // Order and parentage are one fact (where a line sits is what it belongs to), sent together.
        function serialize($nest) {
            var out = [];
            $nest.children('li.bl-item').each(function () {
                var id = $(this).data('id');
                out.push({ id: id, parent_id: '' });
                $(this).children('ol.bl-children').children('li.bl-item').each(function () {
                    out.push({ id: $(this).data('id'), parent_id: id });
                });
            });
            return out;
        }
        function save($nest) {
            var order = serialize($nest);
            if (!order.length) { return; }
            showState('is-saving', T.saving);
            $.ajax({ url: {!! json_encode(route('ledger.budget-line.reorder')) !!}, method: 'POST',
                data: { _token: {!! json_encode(csrf_token()) !!}, section: $nest.data('section'), order: order } })
                .done(function (r) { showState(r && r.success ? 'is-saved' : 'is-error', r && r.success ? T.saved : T.error); })
                .fail(function () { showState('is-error', T.error); });
        }
        // One group per section, so a line cannot be dragged into another section's list:
        // moving between sections is an edit, made in the form.
        $('.bl-nest').each(function () {
            var $nest = $(this);
            var section = $nest.data('section');
            var $lists = $nest.add($nest.find('ol.bl-children'));
            var timer = null;
            $lists.sortable({
                items: '> li.bl-item',
                handle: '.bl-row',
                cancel: '.bl-nodrag, button, a, input, select, form',
                connectWith: '.bl-nest[data-section="' + section + '"], .bl-nest[data-section="' + section + '"] ol.bl-children',
                placeholder: 'bl-placeholder',
                tolerance: 'pointer',
                // Two levels: a heading never files under another heading.
                receive: function (e, ui) {
                    if (ui.item.hasClass('bl-is-header') && $(this).hasClass('bl-children')) { $(ui.sender).sortable('cancel'); }
                },
                stop: function (e, ui) {
                    if (ui.item.hasClass('bl-is-header') && ui.item.parent().hasClass('bl-children')) { $(this).sortable('cancel'); return; }
                    window.clearTimeout(timer);
                    timer = window.setTimeout(function () { save($nest); }, 150);
                }
            });
        });
        @endcan
    });
})();
</script>
@endsection
