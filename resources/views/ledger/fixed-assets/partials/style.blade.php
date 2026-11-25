{{-- Fixed assets: paxhitestbed's statistic boxes and summary cards, on Bootstrap 3. --}}
<style>
    .fa-stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; margin-bottom: 12px; display: flex; align-items: center; gap: 12px; }
    .fa-stat .fa-stat-icon { font-size: 26px; color: #94a3b8; width: 34px; text-align: center; }
    .fa-stat .fa-stat-value { font-size: 1.35rem; font-weight: 600; color: #0f172a; font-variant-numeric: tabular-nums; line-height: 1.2; }
    .fa-stat .fa-stat-label { color: #64748b; font-size: .8rem; }
    .fa-stat.is-info { border-left: 4px solid #0ea5e9; }
    .fa-stat.is-warning { border-left: 4px solid #b45309; }
    .fa-stat.is-success { border-left: 4px solid #15803d; }
    .fa-stat.is-primary { border-left: 4px solid #1d4ed8; }
    .fa-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .fa-actions form { display: inline-block; margin: 0; }
    .fa-panel { border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 16px; background: #fff; }
    .fa-panel > .fa-panel-head { padding: 8px 14px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #0f172a; background: #f8fafc; border-radius: 6px 6px 0 0; }
    .fa-panel > .fa-panel-body { padding: 12px 14px; }
    .fa-panel.is-danger { border-color: #fecaca; }
    .fa-panel.is-danger > .fa-panel-head { background: #fef2f2; color: #b91c1c; }
    .fa-progress { height: 26px; margin-bottom: 0; }
    .fa-progress .progress-bar { line-height: 26px; }
    .fa-table-actions .btn, .fa-table-actions form { display: inline-block; margin: 0 2px 2px 0; }
</style>
