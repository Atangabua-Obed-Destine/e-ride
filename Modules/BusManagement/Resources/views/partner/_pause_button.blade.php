{{-- Pause or resume sales in the app (the agent steps out, a cut is coming). Follows the paper feed. --}}
<span data-pause-state="{{ $busAgency->online_paused_at ? 'paused' : 'live' }}" class="pause-ctl">
    <button type="button" class="btn btn-dark btn-sm" data-pause-btn @if($busAgency->online_paused_at) hidden @endif><i class="bi bi-pause-circle"></i> {{ translate('Pause app sales') }}</button>
    <span class="b-tag bad" data-paused-tag @unless($busAgency->online_paused_at) hidden @endunless>{{ translate('App sales paused') }}</span>
</span>
@once
@push('script')
<script>
(function () {
    var ask = @json(translate('Passengers cannot ask for seats until you resume. Requests waiting now are declined. Your counter keeps working.'));
    function paint(paused) {
        document.querySelectorAll('.pause-ctl').forEach(function (c) {
            c.querySelector('[data-pause-btn]').hidden = paused;
            c.querySelector('[data-paused-tag]').hidden = !paused;
        });
    }
    window.addEventListener('bus-paper', function (e) { paint(!!e.detail.paused); });
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-pause-btn]');
        if (!b || !confirm(ask)) return;
        b.disabled = true;
        busPost(@json(route('bus-partner.paper.pause'))).then(function (d) { busToast(d.message); paint(true); if (window.busPaperRefresh) busPaperRefresh(); })
            .finally(function () { b.disabled = false; });
    });
})();
</script>
@endpush
@endonce
