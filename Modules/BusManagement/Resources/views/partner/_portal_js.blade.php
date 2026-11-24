{{-- busToast and busPost, shared by the bus agency and bus company portals. --}}
<script>
    window.busToast = function (text) {
        var el = document.createElement('div');
        el.className = 'b-toast'; el.setAttribute('role', 'status'); el.textContent = text;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 3600);
    };
    window.busPost = function (url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'Content-Type': 'application/json'},
            body: JSON.stringify(body || {})
        }).then(function (r) {
            if (r.status === 401 || r.status === 419) { window.location.reload(); throw new Error('session'); }
            return r.json().then(function (d) { d._status = r.status; return d; });
        });
    };
</script>
