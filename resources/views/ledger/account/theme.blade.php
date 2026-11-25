{{--
  The Income & Expense section's visual language (paxhitestbed admin/account/partials/theme
  and common/inc/ledger_tokens), in one place: hairline rules, one accent per side (income
  cool, expense warm), money in tabular figures. Include inside @section('css') with $kind
  ('income' or 'expense').
--}}
@php
    $ledgerAccents = ['income' => ['#2a78d6', '#1856a4', '#eef4fc'], 'expense' => ['#eb6834', '#9a3b12', '#fdf1ec']];
    [$ledgerFlow, $ledgerFlowInk, $ledgerFlowWash] = $ledgerAccents[str_contains($kind ?? 'income', 'expense') ? 'expense' : 'income'];
@endphp
<style>
    .acct, .acct-form, .acct-record, .acct-cat {
        --ink: #15233b; --body: #4a5870; --muted: #64748b; --faint: #8794ab;
        --rule: #e4e8f0; --rule-soft: #eef1f6; --surface: #ffffff; --ground: #f6f8fb;
        --flow: {{ $ledgerFlow }}; --flow-ink: {{ $ledgerFlowInk }}; --flow-wash: {{ $ledgerFlowWash }};
        --good: #0f7a3d; --warn: #9a6a06; --bad: #b3261e;
    }

    .acct-panel {
        background: var(--surface);
        border: 1px solid var(--rule);
        border-radius: 8px;
        margin-bottom: .75rem;
    }

    /* The micro-label that names a block. Small, spaced, and quiet: it is a
       signpost, not a heading. */
    .acct-label {
        font-size: .65rem; font-weight: 800; letter-spacing: .09em;
        text-transform: uppercase; color: var(--muted);
    }

    .acct-rule { border: 0; border-top: 1px solid var(--rule-soft); margin: 0; }

    /* Money, everywhere it appears. */
    .acct-num {
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum" 1, "lnum" 1;
        white-space: nowrap;
    }

    .acct-cur { color: var(--muted); font-weight: 600; }

    /* ---- the band ------------------------------------------------------ */

    .acct-band {
        background: var(--surface);
        border: 1px solid var(--rule);
        border-left: 3px solid var(--flow);
        border-radius: 8px;
        padding: 1.15rem 1.35rem;
        margin-bottom: .75rem;
    }
    .acct-band-top {
        display: flex; align-items: flex-end; justify-content: space-between;
        gap: 1.5rem; flex-wrap: wrap;
    }
    .acct-band-who { min-width: 0; }
    .acct-band-who h4 {
        margin: .15rem 0 0; font-size: 1.1rem; font-weight: 700;
        color: var(--ink); letter-spacing: -.01em;
    }
    .acct-band-sub { font-size: .78rem; color: var(--muted); margin-top: .15rem; }

    /* The figure is why the band exists, so it is the largest thing on the
       page and nothing competes with it. */
    .acct-band-figure { text-align: right; }
    .acct-band-figure .v {
        font-size: 1.85rem; font-weight: 800; color: var(--ink);
        line-height: 1; letter-spacing: -.02em;
    }
    .acct-band-figure .c { font-size: .85rem; margin-left: .2rem; }

    /* A split bar rather than a row of chips: proportion is the thing being
       shown, and a bar shows it without anyone reading a number. */
    .acct-split { margin-top: 1.1rem; }
    .acct-split-bar {
        display: flex; height: 6px; border-radius: 3px; overflow: hidden;
        background: var(--rule-soft);
    }
    .acct-split-bar span { display: block; height: 100%; }
    .acct-split-key {
        display: flex; flex-wrap: wrap; gap: .25rem 1.4rem; margin-top: .6rem;
    }
    .acct-split-item { font-size: .78rem; color: var(--body); white-space: nowrap; }
    .acct-split-item .dot {
        display: inline-block; width: 8px; height: 8px; border-radius: 2px;
        margin-right: .4rem; vertical-align: baseline;
    }
    .acct-split-item b { color: var(--ink); font-weight: 700; margin-left: .35rem; }
    .acct-split-item i {
        font-style: normal; color: var(--muted); margin-left: .3rem; font-size: .72rem;
    }

    /* ---- the control row ----------------------------------------------- */

    .acct-bar {
        display: flex; align-items: center; gap: .3rem;
        flex-wrap: wrap; margin-bottom: .75rem;
    }
    .acct-tab {
        display: inline-block; padding: .3rem .7rem; border-radius: 6px;
        font-size: .79rem; font-weight: 600; color: var(--body);
        border: 1px solid transparent; text-decoration: none;
    }
    .acct-tab:hover { background: var(--ground); color: var(--ink); }
    .acct-tab.is-on {
        background: var(--flow-wash); color: var(--flow-ink);
        border-color: var(--flow); font-weight: 700;
    }
    .acct-bar-end { margin-left: auto; display: flex; gap: .35rem; }

    .acct-btn {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .35rem .8rem; border-radius: 6px; font-size: .8rem;
        font-weight: 600; text-decoration: none; border: 1px solid var(--rule);
        background: var(--surface); color: var(--body);
    }
    .acct-btn:hover { background: var(--ground); color: var(--ink); }
    .acct-btn-main {
        background: var(--ink); border-color: var(--ink); color: #fff;
    }
    .acct-btn-main:hover { background: #0e1a2e; border-color: #0e1a2e; color: #fff; }

    /* ---- filters -------------------------------------------------------- */

    .acct-filters { padding: .9rem 1.1rem; }
    .acct-filters label {
        font-size: .65rem; font-weight: 800; letter-spacing: .09em;
        text-transform: uppercase; color: var(--muted); margin-bottom: .25rem;
    }
    .acct-filters .form-control,
    .acct-form .form-control,
    .acct-cat .form-control {
        border-color: var(--rule); border-radius: 6px;
        font-size: .86rem; color: var(--ink);
    }
    .acct-filters .form-control:focus,
    .acct-form .form-control:focus,
    .acct-cat .form-control:focus {
        border-color: var(--flow);
        box-shadow: 0 0 0 3px var(--flow-wash);
    }

    /* ---- the table ------------------------------------------------------ */

    /* No striping. Stripes are noise on a ledger; hairlines between rows and
       a hover tint read as calmer and group nothing falsely.

       EVERY colour below carries !important, and that is not stylistic.
       The theme forces its own with it:

         .table td, .table th  { color: #888 !important }
         .table thead th       { background: #3ea1e4; color: #fff !important }

       Declaring the background with !important and the colour without: which
       is what this did at first: takes their blue header away and leaves
       their white text behind, so the headers rendered white on white. The
       body had the quieter half of the same fault: every cell was flattened to
       #888, which is 3.54:1 and below the text bar, losing the whole ink
       hierarchy these rules exist to set.

       The !important lives here rather than on .table being dropped from the
       table's classes, because DataTables and Bootstrap both style through it. */
    table.acct-table { border-collapse: collapse; }
    table.acct-table thead th {
        font-size: .64rem !important; font-weight: 800; letter-spacing: .09em;
        text-transform: uppercase; color: var(--muted) !important;
        border-bottom: 1px solid var(--rule) !important;
        border-top: 0 !important; padding: .6rem .75rem !important;
        background: transparent !important;
    }
    table.acct-table tbody td {
        border-top: 1px solid var(--rule-soft) !important;
        padding: .6rem .75rem !important; vertical-align: middle;
        font-size: .85rem; color: var(--body) !important;
        background: transparent !important;
    }
    table.acct-table tbody tr:hover td { background: var(--ground) !important; }
    table.acct-table tfoot th {
        border-top: 2px solid var(--rule) !important;
        padding: .7rem .75rem !important; font-size: .8rem;
        color: var(--ink) !important; background: transparent !important;
    }

    table.acct-table .n { text-align: right; }
    table.acct-table td.n { color: var(--ink) !important; font-weight: 600; }
    table.acct-table tfoot .n { font-weight: 800; }

    /* The record's own line, led by its date: a ledger is read by date.

       Scoped inside the table as well as standing alone, because these sit on
       the <td> itself where the theme's "color: #888 !important" applies to
       the same element. A span inside a cell keeps its own colour without
       this; a cell does not. */
    .acct-when { color: var(--ink); font-weight: 600; white-space: nowrap; }
    .acct-what { color: var(--ink); }
    .acct-meta { font-size: .75rem; color: var(--muted); }
    .acct-ref { font-size: .75rem; color: var(--muted); font-variant-numeric: tabular-nums; }

    table.acct-table td.acct-when { color: var(--ink) !important; }
    table.acct-table td.acct-what { color: var(--ink) !important; }
    table.acct-table td.acct-meta { color: var(--muted) !important; }
    table.acct-table td.acct-ref  { color: var(--muted) !important; }

    .acct-tag {
        display: inline-block; padding: .08rem .45rem; border-radius: 4px;
        font-size: .72rem; font-weight: 600;
        background: var(--ground); color: var(--body); border: 1px solid var(--rule);
    }
    .acct-tag-flow {
        background: var(--flow-wash); color: var(--flow-ink); border-color: transparent;
    }

    .acct-clip { color: var(--faint); font-size: .72rem; margin-left: .35rem; }

    .acct-acts { white-space: nowrap; text-align: right; }
    .acct-acts .btn { padding: .2rem .45rem; margin-left: .15rem; }

    /* ---- empty state ---------------------------------------------------- */

    .acct-empty { text-align: center; padding: 3.5rem 1rem; }
    .acct-empty i { font-size: 1.8rem; color: var(--faint); }
    .acct-empty p { margin: .9rem 0 .25rem; font-weight: 600; color: var(--body); }
    .acct-empty small { color: var(--muted); font-size: .8rem; }

    /* ---- the record card (the view modal) -------------------------------- */

    .acct-record .modal-content { border: 0; border-radius: 10px; overflow: hidden; }
    .acct-record .modal-header {
        border-bottom: 1px solid var(--rule); padding: 1rem 1.35rem;
        align-items: flex-start;
    }
    .acct-record .modal-body { padding: 0; }
    .acct-record .modal-footer { border-top: 1px solid var(--rule); padding: .75rem 1.35rem; }

    .acct-record-head { padding: 1.35rem; border-bottom: 1px solid var(--rule); }
    .acct-record-head .v {
        font-size: 2rem; font-weight: 800; color: var(--ink);
        line-height: 1; letter-spacing: -.02em;
    }
    .acct-record-head .t {
        font-size: .95rem; font-weight: 600; color: var(--ink); margin-top: .5rem;
    }

    /* Label left, value right, hairline between. The <mark> tags this
       replaces rendered as blue text on a yellow highlight with an <hr/>
       after every line. */
    .acct-rows { padding: 0 1.35rem; }
    .acct-row {
        display: flex; align-items: baseline; gap: 1rem;
        padding: .6rem 0; border-top: 1px solid var(--rule-soft);
    }
    .acct-row:first-child { border-top: 0; }
    .acct-row dt {
        flex: 0 0 40%; font-size: .78rem; font-weight: 500; color: var(--muted);
        margin: 0;
    }
    .acct-row dd {
        flex: 1 1 auto; margin: 0; font-size: .86rem; font-weight: 600;
        color: var(--ink); min-width: 0; word-break: break-word;
    }
    .acct-note {
        padding: .9rem 1.35rem 1.35rem; font-size: .84rem; color: var(--body);
        border-top: 1px solid var(--rule-soft); margin-top: .3rem;
    }

    /* ---- the forms ------------------------------------------------------ */

    .acct-form .acct-panel { margin-bottom: .75rem; }
    .acct-sec { padding: 1.15rem 1.35rem; }
    .acct-sec + .acct-sec { border-top: 1px solid var(--rule-soft); }
    .acct-sec-head {
        font-size: .65rem; font-weight: 800; letter-spacing: .09em;
        text-transform: uppercase; color: var(--muted); margin-bottom: .9rem;
        display: flex; align-items: center; gap: .5rem;
    }
    .acct-sec-head::after {
        content: ''; flex: 1 1 auto; height: 1px; background: var(--rule-soft);
    }

    .acct-form label {
        font-size: .76rem; font-weight: 600; color: var(--body); margin-bottom: .3rem;
    }
    /* The theme marks a required field with a bare asterisk that inherits body
       colour and reads as punctuation. */
    .acct-form label > span { color: var(--bad); font-weight: 700; }
    .acct-form .form-text { font-size: .74rem; color: var(--muted); }

    .acct-file {
        display: flex; align-items: center; gap: .45rem; margin-top: .45rem;
        font-size: .78rem; color: var(--body);
    }
    .acct-file.is-empty { color: var(--muted); }

    .acct-foot {
        display: flex; align-items: center; gap: .5rem;
        padding: .9rem 1.35rem; border-top: 1px solid var(--rule-soft);
    }
    .acct-foot .acct-cancel { margin-left: auto; }

    /* ---- the confirmation ------------------------------------------------ */

    .acct-confirm .modal-content { border: 0; border-radius: 10px; }
    .acct-confirm .modal-header { border-bottom: 1px solid var(--rule); }
    .acct-confirm .modal-footer { border-top: 1px solid var(--rule); }
    .acct-confirm-amount {
        font-size: 2rem; line-height: 1; font-weight: 800; color: var(--ink);
        font-variant-numeric: tabular-nums; letter-spacing: -.02em;
    }
    .acct-confirm-note {
        background: var(--flow-wash); border-radius: 6px; padding: .75rem .9rem;
        font-size: .82rem; color: var(--flow-ink); margin-bottom: 1rem;
    }
    .acct-confirm-changes-head {
        font-size: .65rem; font-weight: 800; letter-spacing: .09em;
        text-transform: uppercase; color: var(--muted); margin-bottom: .45rem;
    }
    .acct-confirm-changes-list { list-style: none; padding: 0; margin: 0; font-size: .83rem; }
    .acct-confirm-changes-list li {
        padding: .35rem 0; border-top: 1px solid var(--rule-soft); color: var(--body);
    }
    .acct-confirm-changes-list li:first-child { border-top: 0; }
    .acct-confirm-changes-list b { color: var(--ink); }
    .acct-confirm-changes-list .was { text-decoration: line-through; color: var(--muted); }
    .acct-cat .acct-sec { padding: 1.15rem 1.35rem; }
    .acct-cat .acct-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: .5rem; vertical-align: middle; }
    .acct-cat .is-on { background: var(--good); }
    .acct-cat .is-off { background: var(--faint); }
    .acct-state { font-size: .8rem; color: var(--body); }
    .acct-posted { font-size: .72rem; }
    @media (max-width: 767px) {
        .acct-band-figure { text-align: left; }
        .acct-bar-end { margin-left: 0; width: 100%; }
        .acct-row { display: block; }
        .acct-row dt { margin-bottom: .1rem; }
    }
</style>
