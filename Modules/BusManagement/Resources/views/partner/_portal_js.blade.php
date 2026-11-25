{{-- busToast, busPost and busDesk, shared by the bus agency and bus company portals. --}}
<script>
    // The desktop workspace of wide screens (public/assets/desk/desktop.js) keeps each open
    // screen alive in a tab of its own, so a page's alarm (the paper sheet's) runs in every
    // tab: busDesk lets the copies agree. One sound for the whole window, from the copy on
    // screen, or from the Home tab when the screen on top has no copy (Accounting).
    window.busDesk = {
        // The workspace window this page is in (itself when it is the window), or null.
        top: function () { try { return window.top.PI_DESK ? window.top : null; } catch (e) { return null; } },
        // The document of the tab on screen (null while it opens).
        onScreen: function () {
            var t = busDesk.top();
            if (!t) return document;
            if (!t.document.documentElement.classList.contains('pi-frame-on')) return t.document;
            var f = t.document.querySelector('#pi-frames iframe.on');
            try { return f ? f.contentDocument : null; } catch (e) { return null; }
        },
        isHome: function () {
            var t = busDesk.top();
            if (!t) return true;
            try { return new URL(t.PI_DESK_CFG.home, location.href).pathname.replace(/\/+$/, '') === location.pathname.replace(/\/+$/, ''); } catch (e) { return false; }
        },
        // May this copy of an alarm (its element, with an id) sound now?
        maySound: function (el) {
            if (!el || !el.isConnected) return false; // a screen the window let go
            var d = busDesk.onScreen();
            if (d === document) return true;
            if (d && d.getElementById(el.id)) return false;
            return busDesk.isHome();
        },
        // The one sound of the window: made in the workspace window, which any tap in any tab
        // lets play (browsers allow sound only after a tap on the page).
        audio: function () {
            var w = busDesk.top() || window;
            try {
                if (!w.__busAudio) w.__busAudio = new (w.AudioContext || w.webkitAudioContext)();
                if (w.__busAudio.state === 'suspended') w.__busAudio.resume();
                return w.__busAudio;
            } catch (e) { return null; }
        },
        // A message where it is seen: in the workspace window when this tab is behind.
        toast: function (text) {
            var t = busDesk.top();
            if (t && t !== window && busDesk.onScreen() !== document) {
                if (t.busToast) { t.busToast(text); return; }
                if (t.toastr) { t.toastr.info(text); return; }
            }
            busToast(text);
        }
    };
    document.addEventListener('pointerdown', function () {
        var w = busDesk.top() || window;
        if (w.__busAudio && w.__busAudio.state === 'suspended') w.__busAudio.resume();
    }, {capture: true});
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
