{{-- paxhitestbed admin/daybook styles (db-, ms-), shared by the book, the summary and the analysis. --}}
<style>
    .db-toolbar {
        display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap;
        background: #fff; border: 1px solid #e3e7ee; border-radius: 10px;
        padding: 1rem 1.15rem; margin-bottom: 1rem;
    }
    .db-field label {
        display: block; font-size: .7rem; font-weight: 800; letter-spacing: .8px;
        text-transform: uppercase; color: #8a97ab; margin-bottom: .25rem;
    }
    .db-field select { min-width: 190px; }
    .db-actions { margin-left: auto; display: flex; gap: .35rem; flex-wrap: wrap; }

    .db-guide { border: 1px solid #cfe0f7; border-radius: 10px; background: #f5f9ff; margin-bottom: 1rem; }
    .db-guide-toggle {
        display: flex; align-items: center; width: 100%; background: transparent; border: 0;
        padding: .85rem 1.1rem; text-align: left; color: #14396e; cursor: pointer; font-weight: 700;
    }
    .db-guide-toggle .grow { flex: 1 1 auto; }
    .db-guide-body { padding: 0 1.15rem 1.15rem; }
    .db-guide-body p, .db-guide-body li { color: #33507d; font-size: .89rem; line-height: 1.6; }
    .db-guide-body p { margin-bottom: 0; }
    .db-guide-body h6 {
        font-size: .7rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase;
        color: #7189b0; margin: 1rem 0 .3rem;
    }

    .db-figures { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .db-figure {
        background: #fff; border: 1px solid #e3e7ee; border-radius: 10px;
        padding: .85rem 1.25rem; min-width: 170px;
    }
    .db-figure .label {
        display: block; padding: 0; text-align: left;
        font-size: .7rem; font-weight: 800; letter-spacing: .9px;
        text-transform: uppercase; color: #8a97ab;
    }
    .db-figure .value { font-size: 1.35rem; font-weight: 800; color: #0a2540; font-variant-numeric: tabular-nums; }
    .db-figure.in .value { color: #0f7a45; }
    .db-figure.out .value { color: #a02020; }

    .db-card { background: #fff; border: 1px solid #e3e7ee; border-radius: 10px; margin-bottom: 1rem; }
    .db-card-head { padding: .85rem 1.15rem; border-bottom: 1px solid #eef1f5; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
    .db-card-head h5 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #182b49; }
    .db-card-head small { color: #64748b; }

    .db-table { width: 100%; font-size: .86rem; margin-bottom: 0; }
    .db-table th {
        background: #f4f6f9; font-size: .7rem; text-transform: uppercase;
        letter-spacing: .7px; color: #55637a; padding: .5rem .6rem !important; white-space: nowrap;
    }
    .db-table td { padding: .45rem .6rem !important; border-top: 1px solid #eef1f5; vertical-align: top !important; }
    .db-table tbody tr:hover td { background: #fafbfd; }
    .db-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .db-in { color: #0f7a45; font-weight: 600; }
    .db-out { color: #a02020; font-weight: 600; }
    .db-ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .78rem; color: #55637a; }
    .db-line { font-size: .74rem; color: #5b7bab; white-space: nowrap; }
    .db-line-none { color: #b3701a; font-weight: 700; }
    .db-source {
        font-size: .62rem; text-transform: uppercase; letter-spacing: .5px;
        border: 1px solid #dde3ea; border-radius: 3px; padding: 0 .25rem; color: #7189b0; white-space: nowrap;
    }
    .db-source.rev { border-color: #f2dca7; color: #7a5312; background: #fdf8ea; }
    .db-foot td { border-top: 2px solid #34495e !important; font-weight: 800; background: #f7f9fb; }
    .db-open td { background: #f5f9ff; font-weight: 700; }

    .ms-table { width: 100%; font-size: .85rem; margin-bottom: 0; }
    .ms-table th {
        background: #f4f6f9; font-size: .68rem; text-transform: uppercase;
        letter-spacing: .7px; color: #55637a; padding: .5rem .55rem !important; white-space: nowrap;
    }
    .ms-table td { padding: .38rem .55rem !important; border-top: 1px solid #eef1f5; }
    .ms-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ms-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .78rem; color: #55637a; }
    .ms-section td {
        background: #212b36; color: #fff; font-weight: 700; text-transform: uppercase;
        letter-spacing: .5px; font-size: .74rem;
    }
    .ms-total td { border-top: 2px solid #34495e !important; font-weight: 800; background: #f7f9fb; }
    .ms-header td { background: #eef1f6; font-weight: 700; }
    .ms-header .ms-name { text-transform: uppercase; letter-spacing: .3px; }
    .ms-child .ms-name { padding-left: 1.4rem !important; }
    .ms-subtotal td {
        background: #f7f9fb; font-weight: 600; font-style: italic;
        border-top: 1px solid #c8d3e0; border-bottom: 1px solid #c8d3e0;
    }
    .ms-subtotal .ms-name { padding-left: 1.4rem !important; }
    .ms-good { color: #0f7a45; }
    .ms-bad { color: #a02020; }
    .ms-quiet { color: #a9b3c1; }
    .col-key { width: 100%; font-size: .86rem; margin-bottom: 0; }
    .col-key td { padding: .3rem .5rem; border-top: 1px solid #dbe7f8; color: #33507d; }
    .col-key td:first-child { font-weight: 700; white-space: nowrap; width: 130px; }

    @media print {
        .no-print, .main-sidebar, .main-header { display: none !important; }
    }
</style>
