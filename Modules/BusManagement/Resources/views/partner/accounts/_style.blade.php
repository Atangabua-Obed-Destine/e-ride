{{-- Accounts pages: tables of figures, square-ish corners, numbers aligned. --}}
<style>
    .acc-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 14px 16px; }
    .acc-card + .acc-card { margin-top: 12px; }
    .acc-h { font-weight: 800; font-size: 15px; margin: 0 0 8px; }
    .acc-sub { display: flex; gap: 4px; flex-wrap: wrap; margin: -4px 0 16px; border-bottom: 1px solid var(--line); }
    .acc-sub a { padding: 8px 12px; font-size: 13.5px; font-weight: 700; color: var(--muted); text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; }
    .acc-sub a.active { color: var(--ink); border-bottom-color: var(--ink); }
    .acc-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); }
    .acc-fig { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 12px 14px; }
    .acc-fig .k { font-size: 12.5px; color: var(--muted); font-weight: 600; }
    .acc-fig .v { font-size: 20px; font-weight: 800; font-variant-numeric: tabular-nums; margin-top: 2px; }
    .acc-fig .v.neg { color: #b91c1c; }
    .acc-fig a { color: inherit; text-decoration: none; }
    table.acc-t { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    table.acc-t th { font-size: 12px; text-transform: none; color: var(--muted); font-weight: 700; border-bottom: 1px solid var(--line); padding: 6px 8px; text-align: left; white-space: nowrap; }
    table.acc-t td { border-bottom: 1px solid #f0f2f2; padding: 7px 8px; vertical-align: top; }
    table.acc-t .n, table.acc-t th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    table.acc-t tr.tot td { font-weight: 800; border-top: 2px solid var(--ink); border-bottom: 0; }
    table.acc-t tr.sub td { font-weight: 700; background: #f7f9f9; }
    table.acc-t tr.void td { color: #9aa5a4; text-decoration: line-through; }
    table.acc-t a { color: inherit; }
    .acc-scroll { overflow-x: auto; }
    .neg { color: #b91c1c; }
    .acc-tag { font-size: 11.5px; color: var(--muted); }
    .acc-card .form-control, .acc-card .form-select, .modal .form-control, .modal .form-select { border-radius: 6px; }
    .acc-card .btn, .modal .btn { border-radius: 6px; }
    .acc-form .form-label { font-size: 12.5px; font-weight: 700; margin-bottom: 3px; color: var(--muted); }
    .rv-block { margin-top: 12px; }
    .rv-block h3 { font-size: 13px; font-weight: 800; margin: 0 0 6px; }
    .rv-problem { background: #fdecec; color: #991b1b; border-radius: 6px; padding: 8px 12px; font-size: 13.5px; margin-bottom: 6px; }
    .rv-warn { background: #fff4e5; color: #7c3a06; border-radius: 6px; padding: 8px 12px; font-size: 13.5px; margin-bottom: 6px; }
    .rv-note { color: var(--muted); font-size: 12.5px; margin-bottom: 4px; }
    .rv-entry { border: 1px solid var(--line); border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; }
    .rv-tick { display: flex; gap: 10px; align-items: flex-start; border: 2px solid var(--line); border-radius: 6px; padding: 10px 12px; margin-top: 14px; cursor: pointer; font-weight: 600; }
    .rv-tick.on { border-color: var(--ok); background: #f3fbf6; }
    .rv-tick input { margin-top: 3px; flex: none; }
    .acc-empty { color: var(--muted); font-size: 13.5px; padding: 14px 0; text-align: center; }
    @media (max-width: 575px) { .acc-fig .v { font-size: 17px; } table.acc-t { font-size: 12.5px; } }
</style>
