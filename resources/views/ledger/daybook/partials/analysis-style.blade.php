{{-- paxhitestbed admin/daybook/analysis styles: chart roles, tiles, the confidence panel, the viz cards. --}}
<style>
    /* Chart roles, not raw hex, so the palette swaps in one place (paxhitestbed's validated pair). */
    .viz {
        --surface:        #ffffff;
        --text-primary:   #0b0b0b;
        --text-secondary: #52514e;
        --text-muted:     #8a97ab;
        --gridline:       #e1e0d9;
        --axis:           #c3c2b7;
        --series-in:      #2a78d6;
        --series-out:     #eb6834;
        --good:           #0ca30c;
        --warning:        #fab219;
        --critical:       #d03b3b;
        --paid-full:      #0f7a3d;
        --paid-part:      #b8860b;
        --paid-none:      #b3261e;
    }
    .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(215px, 1fr)); gap: .85rem; margin-bottom: 1.1rem; }
    .tile { background: #fff; border: 1px solid #e3e7ee; border-radius: 10px; padding: 1rem 1.15rem; }
    .tile .label { display: block; padding: 0; text-align: left; font-size: .7rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase; color: var(--text-muted); margin-bottom: .2rem; }
    .tile .value { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.15; }
    .tile .note { font-size: .78rem; color: var(--text-secondary); margin-top: .35rem; line-height: 1.45; }
    .tile.in .value { color: var(--series-in); }
    .tile.out .value { color: var(--series-out); }
    .tile.deficit .value { color: var(--critical); }

    .status-chip { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 700; padding: .1rem .45rem; border-radius: 4px; border: 1px solid; margin-top: .3rem; }
    .chip-good { color: #056b05; border-color: #b5e3b5; background: #eefaee; }
    .chip-warning { color: #7a5312; border-color: #f2dca7; background: #fdf8ea; }
    .chip-critical { color: #8a1f1f; border-color: #eab8b8; background: #fdeeee; }
    .chip-none { color: #55637a; border-color: #d7dee7; background: #f4f6f9; }

    .conf { background: #fff; border: 1px solid #e3e7ee; border-left: 4px solid var(--good); border-radius: 10px; margin-bottom: 1.1rem; }
    .conf.has-warning { border-left-color: var(--warning); }
    .conf.has-critical { border-left-color: var(--critical); }
    .conf-head { display: flex; align-items: center; gap: .6rem; width: 100%; background: transparent; border: 0; text-align: left; padding: .9rem 1.15rem; font-weight: 700; color: #182b49; cursor: pointer; }
    .conf-head .count { margin-left: auto; font-size: .78rem; font-weight: 700; margin-top: 0; }
    .conf-list { border-top: 1px solid #eef1f5; }
    .conf-row { display: grid; grid-template-columns: 22px minmax(190px, 1.1fr) 150px 2fr; gap: .75rem; align-items: start; padding: .75rem 1.15rem; border-bottom: 1px solid #f3f5f8; }
    .conf-row:last-child { border-bottom: 0; }
    .conf-row .ico { font-size: .95rem; line-height: 1.5; }
    .conf-row .ico.good { color: var(--good); }
    .conf-row .ico.warning { color: #b07d10; }
    .conf-row .ico.critical { color: var(--critical); }
    .conf-row .name { font-weight: 700; color: #182b49; font-size: .88rem; }
    .conf-row .fig { font-variant-numeric: tabular-nums; font-weight: 800; font-size: .95rem; color: var(--text-primary); text-align: right; white-space: nowrap; }
    .conf-row .says { font-size: .83rem; color: var(--text-secondary); line-height: 1.55; }
    .conf-row .says .todo { display: block; margin-top: .2rem; color: #33507d; font-weight: 600; }
    @media (max-width: 900px) { .conf-row { grid-template-columns: 22px 1fr; } .conf-row .fig { text-align: left; } }

    .viz-card { background: #fff; border: 1px solid #e3e7ee; border-radius: 10px; margin-bottom: 1.1rem; }
    .viz-head { padding: 1rem 1.25rem .5rem; }
    .viz-head h5 { margin: 0 0 .2rem; font-size: 1.02rem; font-weight: 700; color: #182b49; }
    .viz-head .sub { font-size: .84rem; color: var(--text-secondary); margin: 0; line-height: 1.5; }
    .viz-body { padding: .5rem 1.25rem 1rem; }
    .viz-canvas { position: relative; height: 300px; }
    .viz-read { border-top: 1px solid #eef1f5; margin-top: .75rem; padding: .8rem 1.25rem 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
    .viz-read h6 { font-size: .68rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase; color: var(--text-muted); margin: 0 0 .3rem; }
    .viz-read p { font-size: .85rem; color: #33507d; margin: 0; line-height: 1.55; }
    .key { display: flex; gap: 1rem; flex-wrap: wrap; padding: 0 1.25rem .4rem; }
    .key span { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; color: var(--text-secondary); }
    .key i { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }

    .rank-table { width: 100%; font-size: .87rem; }
    .rank-table td, .rank-table th { padding: .35rem .5rem; border-top: 1px solid #eef1f5; }
    .rank-table thead th { font-size: .68rem; text-transform: uppercase; letter-spacing: .7px; color: var(--text-muted); font-weight: 800; border-top: 0; white-space: nowrap; }
    .rank-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .rank-bar { height: 8px; border-radius: 4px; background: var(--series-out); }
    .rank-bar.in { background: var(--series-in); }
    .rank-track { background: #f2f4f7; border-radius: 4px; }
    .rank-share { font-size: .78rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }

    .fc-split { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .85rem; margin-bottom: 1rem; }
    .fc-stat { border: 1px solid #e3e7ee; border-radius: 10px; padding: .85rem 1rem; border-left-width: 3px; }
    .fc-stat .label { display: block; padding: 0; text-align: left; font-size: .7rem; font-weight: 800; letter-spacing: .9px; text-transform: uppercase; color: var(--text-muted); margin-bottom: .2rem; }
    .fc-stat .value { font-size: 1.9rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
    .fc-stat .of { font-size: .78rem; color: var(--text-secondary); margin-top: .2rem; }
    .fc-full { border-left-color: var(--paid-full); } .fc-part { border-left-color: var(--paid-part); } .fc-none { border-left-color: var(--paid-none); }
    .fc-full .value { color: var(--paid-full); } .fc-part .value { color: var(--paid-part); } .fc-none .value { color: var(--paid-none); }
    .fc-bar { display: flex; height: 14px; border-radius: 7px; overflow: hidden; background: #f2f4f7; }
    .fc-bar span { display: block; height: 100%; box-shadow: 0 0 0 2px var(--surface) inset; }
    .fc-bar span:first-child { box-shadow: none; }
    .fc-bar .s-full { background: var(--paid-full); } .fc-bar .s-part { background: var(--paid-part); } .fc-bar .s-none { background: var(--paid-none); }
    .fc-tiles { padding: 0 1.25rem; margin-bottom: 1rem; }
    .fc-aside { font-size: .76rem; color: var(--text-muted); line-height: 1.5; }
    .fc-tag { display: inline-block; padding: .02rem .35rem; border-radius: 3px; font-size: .68rem; font-weight: 700; background: #f4f6f9; color: #55637a; border: 1px solid #d7dee7; }

    .numbers { border-top: 1px solid #eef1f5; }
    .numbers summary { padding: .6rem 1.25rem; cursor: pointer; font-size: .8rem; font-weight: 700; color: #33507d; list-style: none; }
    .numbers summary::-webkit-details-marker { display: none; }
    .numbers summary::before { content: '▸ '; }
    .numbers[open] summary::before { content: '▾ '; }
    .numbers table { width: 100%; font-size: .82rem; margin: 0 0 .6rem; }
    .numbers th, .numbers td { padding: .3rem .5rem; border-top: 1px solid #f1f3f7; }
    .numbers th { font-size: .68rem; text-transform: uppercase; letter-spacing: .7px; color: var(--text-muted); font-weight: 800; }
    .numbers td.n, .numbers th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .mv-up { color: var(--series-in); font-weight: 700; }
    .mv-down { color: var(--series-out); font-weight: 700; }
    .pack-note { font-size: .8rem; color: var(--text-muted); margin: .35rem 0 0; }
</style>
