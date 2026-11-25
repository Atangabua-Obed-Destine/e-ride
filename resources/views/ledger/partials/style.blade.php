{{-- Accounting screens: paxhitestbed's ap- styles (supplier-bill/partials/style), shared by every ledger view. --}}
<style>
    .ap-table th, .ap-table td { font-size: 0.85rem; vertical-align: middle !important; }
    .ap-table thead th { background: #f1f5f9 !important; color: #334155 !important; white-space: nowrap; border-bottom: 1px solid #cbd5e1; }
    .ap-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ap-muted { color: #64748b; font-size: 0.8rem; }
    .ap-toolbar { display: flex; flex-wrap: wrap; gap: 8px; }
    .ap-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
    .ap-filters .form-group { margin-bottom: 0; min-width: 160px; }
    .ap-dl { display: grid; grid-template-columns: 170px 1fr; gap: 6px 16px; margin: 0; }
    .ap-dl dt { color: #64748b; font-weight: 500; text-align: left !important; }
    .ap-preview .ap-table td { white-space: normal; }
    .ap-preview .ap-table td.ap-num { white-space: nowrap; }
    .ap-dl dd { margin: 0; color: #0f172a; }
    .ap-total-row td { font-weight: 600; border-top: 2px solid #cbd5e1 !important; }
    .ap-note { background: #f1f5f9; border-left: 4px solid #1d4ed8; padding: 10px 14px; border-radius: 4px; color: #334155; font-size: 0.85rem; }
    .ap-note.is-warn { border-left-color: #b45309; background: #fffbeb; }
    .ap-note.is-bad { border-left-color: #b91c1c; background: #fef2f2; }
    .ap-confirm-check { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; color: #94a3b8; }
    .ap-confirm-check.is-ready { border-color: #93c5fd; background: #eff6ff; color: #0f172a; }
    .ap-confirm-check input { width: 18px; height: 18px; margin-top: 2px; flex: 0 0 auto; cursor: pointer; }
    .ap-confirm-check input:disabled { cursor: not-allowed; }
    .ap-confirm-check label { margin: 0; font-weight: 500; cursor: pointer; }
    /* State is never colour alone: every chip says it in words. */
    .ap-chip { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; border: 1px solid transparent; }
    .ap-chip.draft { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
    .ap-chip.unpaid { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .ap-chip.part_paid { background: #fffbeb; color: #92400e; border-color: #fde68a; }
    .ap-chip.paid { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
    .ap-chip.void { background: #f8fafc; color: #64748b; border-color: #e2e8f0; text-decoration: line-through; }
    .ap-chip.overdue { background: #b91c1c; color: #fff; }
    .ap-chip.posted { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .ap-chip.closed { background: #f1f5f9; color: #334155; border-color: #94a3b8; }
    .ap-chip.open { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
    .ap-chip.failed { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    #reviewModal .modal-dialog { width: 92%; max-width: 1100px; }
    .ap-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
</style>
