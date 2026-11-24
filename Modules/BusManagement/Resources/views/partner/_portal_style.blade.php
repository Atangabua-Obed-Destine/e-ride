{{-- The look shared by the bus agency (branch) portal and the bus company (head office) portal. --}}
    <style>
        :root { --red: #e02020; --ink: #1d2d2b; --muted: #5f6b6a; --line: #e5e9e8; --bg: #f4f6f6; --ok: #15803d; --warn: #9a4a06; }
        * { -webkit-tap-highlight-color: transparent; }
        [hidden] { display: none !important; }
        html, body { background: var(--bg); color: var(--ink); }
        body { font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .b-top { background: #111114; color: #fff; position: sticky; top: 0; z-index: 30; }
        .b-top__in { max-width: 1280px; margin: 0 auto; padding: 0 16px; }
        .b-bar { display: flex; align-items: center; gap: 12px; min-height: 58px; padding-top: env(safe-area-inset-top); }
        .b-logo { width: 34px; height: 34px; border-radius: 9px; background: #1e3a8a; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex: none; overflow: hidden; }
        .b-logo img { width: 100%; height: 100%; object-fit: cover; }
        .b-name { font-weight: 800; font-size: 15px; line-height: 1.15; min-width: 0; }
        .b-name small { display: block; font-weight: 500; font-size: 12px; color: #aeb9b8; }
        .b-pill { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 13px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .b-pill.is-live { background: rgba(21, 128, 61, .22); color: #86efac; }
        .b-pill.is-setup { background: rgba(245, 158, 11, .2); color: #fcd34d; }
        .b-pill.is-paused { background: rgba(224, 32, 32, .22); color: #fca5a5; }
        .b-nav { display: flex; gap: 22px; overflow-x: auto; scrollbar-width: none; }
        .b-nav::-webkit-scrollbar { display: none; }
        .b-nav a { color: #c9d1d0; font-size: 13.5px; font-weight: 600; padding: 12px 2px; border-bottom: 3px solid transparent; white-space: nowrap; text-decoration: none; display: inline-flex; gap: 7px; align-items: center; position: relative; }
        .b-nav a.active { color: #fff; border-bottom-color: var(--red); }
        .b-nav .b-badge { background: var(--red); color: #fff; border-radius: 999px; font-size: 11px; min-width: 18px; height: 18px; padding: 0 5px; display: none; align-items: center; justify-content: center; font-weight: 800; }
        .b-main { max-width: 1280px; margin: 0 auto; padding: 18px 16px 40px; }
        .b-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; }
        .b-h { font-weight: 800; font-size: 16px; margin: 0; }
        .b-hint { font-size: 12.5px; color: var(--muted); line-height: 1.45; }
        .btn { border-radius: 10px; font-weight: 700; }
        .btn-primary { background: var(--red); border-color: var(--red); }
        .btn-primary:hover, .btn-primary:focus { background: #c41a1a; border-color: #c41a1a; }
        .btn-primary:disabled, .btn-primary.disabled { background: var(--red); border-color: var(--red); opacity: .45; }
        .btn-dark { background: #1d2d2b; border-color: #1d2d2b; }
        .btn-soft { background: #fff; border-color: #dde3e2; color: var(--ink); }
        .b-card .btn-soft { background: #f4f6f6; border-color: #f4f6f6; }
        .form-control, .form-select { border-radius: 10px; min-height: 42px; }
        .b-tag { display: inline-flex; align-items: center; gap: 5px; height: 22px; padding: 0 8px; border-radius: 11px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .b-tag.ok { background: #e9f7ef; color: var(--ok); }
        .b-tag.warn { background: #fff4e5; color: var(--warn); }
        .b-tag.bad { background: #fdecec; color: #b91c1c; }
        .b-tag.mute { background: #f1f3f3; color: #48615e; }
        .b-tag.blue { background: #e8eefc; color: #1d4ed8; }
        .b-toast { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); background: #111114; color: #fff; padding: 10px 16px; border-radius: 12px; font-weight: 600; z-index: 2000; max-width: calc(100% - 32px); box-shadow: 0 10px 30px rgba(0,0,0,.25); }
        .seg { display: inline-flex; border: 1px solid #dde3e2; border-radius: 10px; overflow: hidden; flex-wrap: wrap; }
        .seg input { position: absolute; opacity: 0; pointer-events: none; }
        .seg label { padding: 0 12px; height: 34px; display: flex; align-items: center; font-size: 13px; font-weight: 600; cursor: pointer; margin: 0; background: #fff; }
        .seg input:checked + label { background: #1d2d2b; color: #fff; }
        .seg input:focus-visible + label { outline: 2px solid var(--red); outline-offset: -2px; }
        /* Seat plans (shared by Fleet, Quick sell and Boarding) */
        .sp { display: inline-flex; flex-direction: column; gap: 4px; border: 2px solid #e3e8e7; border-radius: 26px 26px 12px 12px; padding: 10px; background: #fbfcfc; }
        .sp-row { display: flex; gap: 4px; justify-content: center; }
        .sp { --sp-w: 34px; }
        .sp-cell { width: var(--sp-w); height: 32px; border-radius: 7px 7px 5px 5px; display: flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 700; flex: none; border: 0; padding: 0; }
        .sp-seat { background: #22c55e; color: #fff; }
        .sp-aisle { width: 14px; }
        .sp-driver { background: #48615e; color: #fff; border-radius: 50%; font-size: 9.5px; }
        .sp-door { background: #dbeafe; color: #1d4ed8; font-size: 10px; }
        .sp-cabin { display: flex; justify-content: space-between; background: #eef1f0; border-radius: 10px; padding: 6px 8px; margin-bottom: 4px; font-size: 11.5px; font-weight: 700; color: #48615e; }
        .sp-cabin b { color: #1d4ed8; }
        .sp-deck { font-size: 11.5px; font-weight: 700; color: var(--muted); text-align: center; margin: 4px 0; }
        .sp-seat.st-held { background: #f59e0b; }
        .sp-seat.st-app { background: #2563eb; }
        .sp-seat.st-counter { background: #1d2d2b; }
        .sp-seat.st-boarded { background: #9ca3af; }
        .sp-seat.st-kept { box-shadow: inset 0 0 0 3px #1d2d2b; }
        .sp-seat.st-picked { background: var(--red); transform: scale(1.08); }
        button.sp-seat:disabled { cursor: default; opacity: 1; }
        button.sp-seat:not(:disabled) { cursor: pointer; }
        button.sp-seat:focus-visible { outline: 3px solid #111; outline-offset: 1px; }
        .sp-legend { display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; color: var(--muted); }
        .sp-legend i { display: inline-block; width: 14px; height: 14px; border-radius: 4px; vertical-align: -2px; margin-right: 4px; }
        @media (max-width: 575px) { .sp { --sp-w: 30px; } .sp-cell { height: 28px; } }
    </style>
