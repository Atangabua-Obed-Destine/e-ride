{{-- General ledger screens: what the Print button leaves on paper (paxhitestbed hides the filters and buttons). --}}
<style>
    .gl-thead-dark th { background: #343a40 !important; color: #fff !important; }
    @media print {
        .no-print, .main-sidebar, .main-header, .main-footer, .box-tools { display: none !important; }
        .content-wrapper { margin-left: 0 !important; }
        .box { box-shadow: none !important; border: none !important; }
        body { font-size: 11px; }
        .table-condensed td, .table-condensed th { padding: 2px 4px !important; }
        a[href]:after { content: none !important; }
    }
</style>
