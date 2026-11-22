/*
 * E-Ride admin workspace (Modules/AdminModule/Resources/views/partials/_desk.blade.php).
 *
 * - "Go to…" (Ctrl/Cmd + K) everywhere: every page the admin may open, found by name,
 *   area or other words; recent pages first.
 * - Wide screens (html.ed-desk): menu bar, tabs that keep each page alive (iframes),
 *   back / forward / reload / print / new window, Alt + 1…9, Alt + W, status bar.
 *   The page that was loaded is the first tab; its links and forms move into a tab
 *   frame, so the other tabs are never lost. Tabs come back after a reload.
 * - Inside a tab (html.ed-framed): no header or sidebar, shortcuts go to the workspace.
 * - Phones (html.ed-phone): bottom bar; "Menu" opens the sidebar.
 * Plain JavaScript, no dependency.
 */
(function () {
    'use strict';

    var html = document.documentElement;
    var CFG = window.ED_DESK_CFG || {pages: [], text: {}};
    var T = CFG.text || {};
    var FRAMED = html.classList.contains('ed-framed');
    var DESK = html.classList.contains('ed-desk');
    var MAX_TABS = 8;
    var RECENT_KEY = 'ed.recent.' + (CFG.who || '');
    var TABS_KEY = 'ed.desk.tabs';

    // ------------------------------------------------------------------ helpers

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function store(kind) { try { return window[kind]; } catch (e) { return null; } }
    function readJson(kind, key, dflt) { try { var v = store(kind).getItem(key); return v ? JSON.parse(v) : dflt; } catch (e) { return dflt; } }
    function writeJson(kind, key, v) { try { store(kind).setItem(key, JSON.stringify(v)); } catch (e) {} }
    function sameOrigin(url) { try { return new URL(url, location.href).origin === location.origin; } catch (e) { return false; } }
    function keyOf(url) { try { var u = new URL(url, location.href); return u.pathname.replace(/\/+$/, '') + u.search; } catch (e) { return url; } }
    function fold(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    function pageFor(url) {
        var k = keyOf(url), best = null;
        (CFG.pages || []).forEach(function (p) { var pk = keyOf(p.u); if (k === pk || (k.indexOf(pk.split('?')[0]) === 0 && (!best || pk.length > keyOf(best.u).length))) best = p; });
        return best;
    }
    function toast(msg) {
        if (window.toastr) { window.toastr.info(msg); return; }
        var t = el('div', 'ed-toast', msg); document.body.appendChild(t); setTimeout(function () { t.remove(); }, 4000);
    }

    // A link the workspace should open in a tab: same site, a page of the admin, not a download.
    function isScreenLink(a, e) {
        if (!a || !a.getAttribute('href')) return false;
        var href = a.getAttribute('href');
        if (href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return false;
        if (a.target && a.target !== '_self') return false;
        if (a.hasAttribute('download') || a.hasAttribute('data-ed-skip') || a.hasAttribute('data-bs-toggle')) return false;
        if (e && (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0)) return false;
        if (!sameOrigin(a.href)) return false;
        var u = new URL(a.href);
        if (u.pathname.indexOf('/admin') === -1) return false;
        if (/\/(logout|login|export|download)(\/|$|\?)/i.test(u.pathname) || /\.(pdf|xlsx?|csv|zip|png|jpe?g)$/i.test(u.pathname)) return false;
        return true;
    }

    // ------------------------------------------------------------------ "Go to…"

    var palette = $('#ed-palette');
    var palInput = palette && $('input', palette);
    var palList = palette && $('#ed-pal-list');
    var palItems = [], palIndex = 0, palReturn = null;

    function recent() { return readJson('localStorage', RECENT_KEY, []); }
    function remember(url) {
        var p = pageFor(url);
        if (!p) return;
        var list = recent().filter(function (u) { return u !== p.u; });
        list.unshift(p.u);
        writeJson('localStorage', RECENT_KEY, list.slice(0, 8));
    }

    function score(p, words) {
        var name = fold(p.t), group = fold(p.g), more = fold(p.w), total = 0;
        for (var i = 0; i < words.length; i++) {
            var w = words[i], at = name.indexOf(w);
            if (at === 0 || (at > 0 && /[\s\-&›(]/.test(name.charAt(at - 1)))) total += 6;
            else if (at > -1) total += 4;
            else if (more.indexOf(w) > -1) total += 2;
            else if (group.indexOf(w) > -1) total += 1;
            else return 0;
        }
        return total;
    }

    function renderPalette() {
        var q = fold(palInput.value).trim(), words = q.split(/\s+/).filter(Boolean), pages = CFG.pages || [];
        var list;
        if (!words.length) {
            var rec = recent().map(function (u) { return pages.filter(function (p) { return p.u === u; })[0]; }).filter(Boolean);
            var rest = pages.filter(function (p) { return rec.indexOf(p) === -1; });
            list = rec.map(function (p) { return {p: p, recent: true}; }).concat(rest.slice(0, 12 - rec.length).map(function (p) { return {p: p}; }));
        } else {
            list = pages.map(function (p) { return {p: p, s: score(p, words)}; }).filter(function (x) { return x.s > 0; })
                .sort(function (a, b) { return b.s - a.s || a.p.t.length - b.p.t.length; }).slice(0, 12);
        }
        palItems = list;
        palIndex = 0;
        palList.replaceChildren();
        if (!list.length) { palList.appendChild(el('li', 'ed-pal-empty', T.noResult || 'Nothing found')); return; }
        var shownRecentHead = false;
        list.forEach(function (x, i) {
            if (x.recent && !shownRecentHead) { palList.appendChild(el('li', 'ed-pal-head', T.recent || 'Recent')); shownRecentHead = true; }
            var li = el('li', 'ed-pal-item');
            li.setAttribute('role', 'option');
            li.id = 'ed-pal-' + i;
            var ic = el('i', 'bi ' + (x.p.i || 'bi-dot'));
            var tx = el('div', 'ed-pal-text');
            tx.appendChild(el('b', null, x.p.t));
            tx.appendChild(el('small', null, x.p.g));
            li.appendChild(ic); li.appendChild(tx);
            if (x.p.s) li.appendChild(el('span', 'ed-pal-tag', T.settings || 'Setting'));
            li.addEventListener('mousemove', function () { select(i); });
            li.addEventListener('click', function (e) { go(i, e.shiftKey); });
            palList.appendChild(li);
        });
        select(0);
    }

    function select(i) {
        var items = $all('.ed-pal-item', palList);
        if (!items.length) return;
        palIndex = (i + items.length) % items.length;
        items.forEach(function (li, n) { li.classList.toggle('is-on', n === palIndex); li.setAttribute('aria-selected', n === palIndex ? 'true' : 'false'); });
        palInput.setAttribute('aria-activedescendant', 'ed-pal-' + palIndex);
        items[palIndex].scrollIntoView({block: 'nearest'});
    }

    function go(i, newTab) {
        var x = palItems[i];
        if (!x) return;
        closePalette();
        remember(x.p.u);
        if (DESK && window.ED_DESK && window.ED_DESK.open) window.ED_DESK.open(x.p.u, {fresh: !!newTab});
        else location.href = x.p.u;
    }

    function openPalette() {
        if (!palette) return;
        palReturn = document.activeElement;
        palette.hidden = false;
        html.classList.add('ed-pal-open');
        palInput.value = '';
        renderPalette();
        setTimeout(function () { palInput.focus(); }, 0);
    }
    function closePalette() {
        if (!palette || palette.hidden) return;
        palette.hidden = true;
        html.classList.remove('ed-pal-open');
        if (palReturn && palReturn.focus) try { palReturn.focus(); } catch (e) {}
    }

    if (palette) {
        palInput.addEventListener('input', renderPalette);
        palInput.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); select(palIndex + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); select(palIndex - 1); }
            else if (e.key === 'Enter') { e.preventDefault(); go(palIndex, e.shiftKey); }
            else if (e.key === 'Escape') { e.preventDefault(); closePalette(); }
        });
        palette.addEventListener('mousedown', function (e) { if (e.target === palette) closePalette(); });
    }

    // ------------------------------------------------------------------ inside a tab

    if (FRAMED) {
        var top = null;
        try { top = window.top.ED_DESK; } catch (e) {}
        // Shortcuts belong to the workspace.
        document.addEventListener('keydown', function (e) {
            if (top && top.key && top.key(e)) e.preventDefault();
        }, true);
        // Links to pages outside the admin (or a sign-in page) leave the workspace.
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || e.defaultPrevented) return;
            if ((e.ctrlKey || e.metaKey || e.button === 1) && top && top.open && isScreenLink(a)) {
                e.preventDefault(); top.open(a.href, {fresh: true}); return;
            }
            if (sameOrigin(a.href) && /\/(logout|login)(\/|$)/.test(new URL(a.href).pathname)) { e.preventDefault(); window.top.location.href = a.href; }
        });
        window.addEventListener('beforeunload', function () { if (top && top.busy) top.busy(window, true); });
        var tell = function () { if (top && top.moved) top.moved(window); };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tell); else tell();
        window.addEventListener('pageshow', tell);
        return;
    }

    // ------------------------------------------------------------------ everywhere (not framed)

    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-ed]');
        if (!b) return;
        var what = b.getAttribute('data-ed');
        if (what === 'palette') { e.preventDefault(); openPalette(); }
        else if (what === 'menu') { e.preventDefault(); var t = $('.header .aside-toggle') || $('.aside-toggle'); if (t) t.click(); }
        else if (what === 'desk-off') { e.preventDefault(); try { localStorage.setItem('ed.desk', 'off'); } catch (x) {} toast(T.deskOff || ''); setTimeout(function () { location.reload(); }, 600); }
        else if (what === 'desk-on') { e.preventDefault(); try { localStorage.removeItem('ed.desk'); } catch (x) {} location.reload(); }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); palette && palette.hidden ? openPalette() : closePalette(); }
    });

    remember(location.href);

    // Phones: "Attention" scrolls to the list on the overview.
    if (location.hash === '#attention') {
        window.addEventListener('load', function () { var a = $('#attention'); if (a) a.scrollIntoView({behavior: 'smooth', block: 'start'}); });
    }

    if (!DESK) return;

    // ================================================================== the desktop workspace

    var tabsBar = $('#ed-tabs'), host = $('#ed-frames'), busyBar = $('#ed-busy'), main = $('.main-area');
    var tabs = [], active = null, seq = 0;

    function save() {
        writeJson('sessionStorage', TABS_KEY, {who: CFG.who, active: active && active.id, tabs: tabs.map(function (t) {
            return {id: t.id, url: t.url, title: t.title, used: t.used, native: !!t.native};
        })});
    }

    function tabFrom(data) {
        var t = {id: data.id || ('t' + (++seq) + Date.now().toString(36)), url: data.url, title: data.title || '', used: data.used || Date.now(),
            native: !!data.native, frame: null, el: null, loading: false};
        var p = pageFor(t.url);
        t.icon = p ? p.i : 'bi-file-earmark';
        if (!t.title && p) t.title = p.t;
        return t;
    }

    function drawTab(t) {
        var b = el('div', 'ed-tab');
        b.setAttribute('role', 'tab');
        b.tabIndex = 0;
        var ic = el('i', 'bi ' + t.icon);
        var name = el('span', 'ed-tab-name', t.title || T.opening || '…');
        var spin = el('span', 'ed-tab-spin');
        var x = el('button', 'ed-tab-x');
        x.type = 'button';
        x.innerHTML = '&times;';
        x.title = T.close || 'Close';
        x.setAttribute('aria-label', (T.close || 'Close') + ' ' + (t.title || ''));
        b.appendChild(ic); b.appendChild(spin); b.appendChild(name); b.appendChild(x);
        b.addEventListener('click', function (e) { if (e.target === x) return; activate(t); });
        b.addEventListener('auxclick', function (e) { if (e.button === 1) { e.preventDefault(); close(t); } });
        b.addEventListener('keydown', function (e) { if (e.key === 'Enter') activate(t); });
        x.addEventListener('click', function (e) { e.stopPropagation(); close(t); });
        t.el = b;
        tabsBar.appendChild(b);
        paintTab(t);
    }

    function paintTab(t) {
        if (!t.el) return;
        $('.ed-tab-name', t.el).textContent = t.title || T.opening || '…';
        t.el.title = t.title || '';
        $('i', t.el).className = 'bi ' + (t.icon || 'bi-file-earmark');
        t.el.classList.toggle('is-on', t === active);
        t.el.classList.toggle('is-loading', !!t.loading);
        t.el.setAttribute('aria-selected', t === active ? 'true' : 'false');
        $('.ed-tab-x', t.el).hidden = tabs.length < 2;
    }

    function ensureFrame(t) {
        if (t.native || t.frame) return;
        var f = document.createElement('iframe');
        f.name = 'ed-' + t.id;
        f.className = 'ed-frame';
        f.setAttribute('title', t.title || '');
        t.loading = true;
        f.addEventListener('load', function () { framed(t); });
        f.src = t.url;
        host.appendChild(f);
        t.frame = f;
        busy(null, true);
    }

    // A frame finished loading: its title and address; a sign-in page takes the whole window.
    function framed(t) {
        t.loading = false;
        var w = t.frame && t.frame.contentWindow, d = null;
        try { d = w.document; } catch (e) {}
        if (d) {
            var inAdmin = d.documentElement.classList.contains('ed-framed');
            var href = w.location.href;
            if (!inAdmin && /\/(login|admin\/auth)/i.test(href)) { location.href = href; return; }
            t.url = href;
            var title = (d.title || '').trim();
            var p = pageFor(href);
            t.title = title || (p && p.t) || t.title;
            t.icon = p ? p.i : t.icon;
            t.frame.setAttribute('title', t.title);
        }
        paintTab(t);
        if (t === active) { address(t); busy(null, false); }
        save();
    }

    function address(t) {
        try { history.replaceState(history.state, '', t.url); } catch (e) {}
        document.title = t.title || document.title;
        markMenus(t.url);
    }

    // The sidebar follows the tab on screen: its link lit, its sub-menu and section open.
    function markSidebar(url) {
        var k = keyOf(url), best = null, bestLen = -1;
        $all('.aside #admin-nav a[href]').forEach(function (a) {
            if (a.getAttribute('href') === '#') return;
            var ak = keyOf(a.href);
            if (ak === k) { best = a; bestLen = 1e9; }
            else if (bestLen < 1e9 && k.indexOf(ak.split('?')[0]) === 0 && ak.length > bestLen) { best = a; bestLen = ak.length; }
        });
        if (!best) return;
        $all('.aside #admin-nav li.active').forEach(function (li) { li.classList.remove('active', 'open'); });
        var li = best.closest('li');
        li.classList.add('active', 'open');
        var parent = li.parentElement && li.parentElement.closest('li');
        if (parent) {
            parent.classList.add('active', 'sub-menu-opened');
            var ul = parent.querySelector('.sub-menu');
            if (ul) { ul.style.display = 'block'; ul.classList.add('open'); }
        }
        var top = parent || li, key = top.getAttribute('data-ed-in');
        var cat = key && $('.aside .ed-cat[data-ed-section="' + key + '"]');
        if (cat && !cat.classList.contains('ed-open')) cat.click();
    }

    function markMenus(url) {
        markSidebar(url);
        var k = keyOf(url);
        $all('#ed-menubar a[href]').forEach(function (a) { a.classList.toggle('is-here', keyOf(a.href) === k); });
        $all('#ed-menubar .ed-mb-item').forEach(function (it) { it.querySelector('.ed-mb-btn').classList.toggle('is-here', !!it.querySelector('a.is-here')); });
    }

    function activate(t) {
        if (!t) return;
        if (active && active !== t && active.el) active.el.classList.remove('is-on');
        active = t;
        t.used = Date.now();
        if (t.native) {
            html.classList.remove('ed-frame-on');
            if (main) main.removeAttribute('inert');
            $all('.ed-frame', host).forEach(function (f) { f.classList.remove('is-on'); });
        } else {
            ensureFrame(t);
            html.classList.add('ed-frame-on');
            if (main) main.setAttribute('inert', '');
            $all('.ed-frame', host).forEach(function (f) { f.classList.toggle('is-on', f === t.frame); });
            busy(null, t.loading);
        }
        tabs.forEach(paintTab);
        address(t);
        if (t.el) t.el.scrollIntoView({block: 'nearest', inline: 'nearest'});
        save();
    }

    function open(url, opts) {
        opts = opts || {};
        url = new URL(url, location.href).href;
        remember(url);
        var k = keyOf(url);
        if (!opts.fresh) {
            var found = tabs.filter(function (t) { return keyOf(t.url) === k; })[0];
            if (found) { activate(found); return found; }
        }
        if (tabs.length >= MAX_TABS) {
            var victim = tabs.filter(function (t) { return t !== active; }).sort(function (a, b) { return a.used - b.used; })[0];
            if (victim) { toast((T.closedTab || '') + ' ' + victim.title); close(victim, true); }
        }
        var t = tabFrom({url: url});
        tabs.push(t);
        drawTab(t);
        activate(t);
        return t;
    }

    function close(t, quiet) {
        var i = tabs.indexOf(t);
        if (i === -1 || tabs.length < 2) return;
        tabs.splice(i, 1);
        if (t.frame) t.frame.remove();
        if (t.el) t.el.remove();
        if (t.native && main) { html.classList.add('ed-native-gone'); }
        if (active === t) {
            var next = tabs.slice().sort(function (a, b) { return b.used - a.used; })[0];
            activate(next);
        } else {
            tabs.forEach(paintTab);
            save();
        }
    }

    // The loaded page leaves for another page: it becomes a frame in the same tab, so no tab is lost.
    function nativeTo(url, form) {
        var t = tabs.filter(function (x) { return x.native; })[0];
        if (!t) { open(url); return; }
        t.native = false;
        html.classList.add('ed-native-gone');
        t.url = url || t.url;
        t.title = T.opening || '…';
        if (form) {
            var f = document.createElement('iframe');
            f.name = 'ed-' + t.id;
            f.className = 'ed-frame';
            host.appendChild(f);
            t.frame = f;
            t.loading = true;
            f.addEventListener('load', function () { framed(t); });
            form.target = f.name;
        }
        activate(t);
        return t;
    }

    function busy(win, on) {
        if (win) {
            var t = tabs.filter(function (x) { return x.frame && x.frame.contentWindow === win; })[0];
            if (t) { t.loading = on; paintTab(t); if (t !== active) return; }
        }
        busyBar.classList.toggle('is-on', !!on);
    }

    function moved(win) {
        var t = tabs.filter(function (x) { return x.frame && x.frame.contentWindow === win; })[0];
        if (t) framed(t);
    }

    function step(dir) {
        var t = active;
        if (!t) return;
        if (t.native) { dir < 0 ? history.back() : history.forward(); return; }
        try { dir < 0 ? t.frame.contentWindow.history.back() : t.frame.contentWindow.history.forward(); } catch (e) {}
    }

    function reload() {
        var t = active;
        if (!t) return;
        if (t.native) { location.reload(); return; }
        try { t.loading = true; paintTab(t); busy(null, true); t.frame.contentWindow.location.reload(); } catch (e) { t.frame.src = t.url; }
    }

    function print() {
        var t = active;
        if (!t || t.native) { window.print(); return; }
        try { t.frame.contentWindow.focus(); t.frame.contentWindow.print(); } catch (e) { window.print(); }
    }

    // Shortcuts, from the workspace or from inside a tab. True when handled.
    function key(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { palette.hidden ? openPalette() : closePalette(); return true; }
        if (e.altKey && /^[1-9]$/.test(e.key)) { var t = tabs[parseInt(e.key, 10) - 1]; if (t) activate(t); return true; }
        if (e.altKey && (e.key === 'w' || e.key === 'W')) { if (active) close(active); return true; }
        if (e.altKey && e.key === 'ArrowLeft') { step(-1); return true; }
        if (e.altKey && e.key === 'ArrowRight') { step(1); return true; }
        if (e.key === 'F5' || ((e.ctrlKey || e.metaKey) && (e.key === 'r' || e.key === 'R') && !e.shiftKey)) { if (active && !active.native) { reload(); return true; } return false; }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) { if (active && !active.native) { print(); return true; } return false; }
        if (e.key === 'Escape') { closeMenus(); closePalette(); return false; }
        return false;
    }

    window.ED_DESK = {open: open, busy: busy, moved: moved, key: key, palette: openPalette};

    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey || e.metaKey) { if (e.key === 'k' || e.key === 'K') return; }
        if (key(e)) e.preventDefault();
    });

    // The toolbar.
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-ed]');
        if (!b) return;
        var what = b.getAttribute('data-ed');
        if (what === 'back') step(-1);
        else if (what === 'forward') step(1);
        else if (what === 'reload') reload();
        else if (what === 'print') print();
        else if (what === 'window') window.open(active ? active.url : location.href, '_blank');
    });

    // Links in the sidebar, menus and header open in a tab; links in the loaded page move it into a frame.
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || e.defaultPrevented || !isScreenLink(a, e)) return;
        if (a.closest('.aside, #ed-titlebar, #ed-status, .header, .ed-palette')) {
            e.preventDefault();
            closeMenus();
            open(a.href);
            return;
        }
        if (main && main.contains(a) && active && active.native) {
            e.preventDefault();
            nativeTo(a.href);
        }
    });
    // Forms in the loaded page post into its tab's frame (so a save never wipes the other tabs).
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!main || !main.contains(form) || form.target || !active || !active.native) return;
        if (form.method && form.method.toLowerCase() === 'get') {
            e.preventDefault();
            var u = new URL(form.action || location.href, location.href);
            new FormData(form).forEach(function (v, k) { if (typeof v === 'string') u.searchParams.set(k, v); });
            nativeTo(u.href);
            return;
        }
        nativeTo(form.action || location.href, form);
    }, true);

    // ---- the menu bar
    var menubar = $('#ed-menubar'), openItem = null;
    function closeMenus() { if (openItem) { openItem.classList.remove('is-open'); $('.ed-mb-btn', openItem).setAttribute('aria-expanded', 'false'); openItem = null; } }
    function openMenu(item) {
        if (openItem === item) return;
        closeMenus();
        openItem = item;
        item.classList.add('is-open');
        $('.ed-mb-btn', item).setAttribute('aria-expanded', 'true');
    }
    if (menubar) {
        $all('.ed-mb-item', menubar).forEach(function (item, i, all) {
            var btn = $('.ed-mb-btn', item);
            btn.addEventListener('click', function () { openItem === item ? closeMenus() : openMenu(item); });
            btn.addEventListener('mouseenter', function () { if (openItem && openItem !== item) openMenu(item); });
            btn.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); openMenu(item); var f = $('a', item); if (f) f.focus(); }
                if (e.key === 'ArrowRight') { e.preventDefault(); $('.ed-mb-btn', all[(i + 1) % all.length]).focus(); if (openItem) openMenu(all[(i + 1) % all.length]); }
                if (e.key === 'ArrowLeft') { e.preventDefault(); $('.ed-mb-btn', all[(i - 1 + all.length) % all.length]).focus(); if (openItem) openMenu(all[(i - 1 + all.length) % all.length]); }
            });
            $all('a', item).forEach(function (a, n, links) {
                a.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown') { e.preventDefault(); (links[n + 1] || links[0]).focus(); }
                    if (e.key === 'ArrowUp') { e.preventDefault(); (links[n - 1] || links[links.length - 1]).focus(); }
                    if (e.key === 'Escape') { e.preventDefault(); closeMenus(); btn.focus(); }
                });
            });
        });
        document.addEventListener('mousedown', function (e) { if (openItem && !openItem.contains(e.target)) closeMenus(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'F10') { e.preventDefault(); var b = $('.ed-mb-btn', menubar); if (b) b.focus(); } });
    }

    // ---- status bar: connection and clock
    var conn = $('#ed-conn');
    function setConn(on) {
        if (!conn) return;
        conn.dataset.state = on ? 'online' : 'offline';
        $('span', conn).textContent = on ? (T.online || 'Online') : (T.offline || 'Offline');
    }
    window.addEventListener('online', function () { setConn(true); });
    window.addEventListener('offline', function () { setConn(false); });
    setConn(navigator.onLine !== false);
    var clock = $('#ed-clock');
    function tick() { if (clock) clock.textContent = new Intl.DateTimeFormat(html.lang || undefined, {weekday: 'short', hour: '2-digit', minute: '2-digit'}).format(new Date()); }
    tick();
    setInterval(tick, 15000);

    // ---- start: the loaded page is a tab; the others come back (frames load when opened)
    var saved = readJson('sessionStorage', TABS_KEY, null);
    var here = keyOf(location.href);
    var restored = saved && saved.who === CFG.who ? saved.tabs || [] : [];
    var nativeTab = null;
    restored.forEach(function (d) {
        if (keyOf(d.url) === here && !nativeTab) { d.native = true; nativeTab = tabFrom(d); nativeTab.url = location.href; nativeTab.title = document.title || nativeTab.title; tabs.push(nativeTab); return; }
        d.native = false;
        tabs.push(tabFrom(d));
    });
    if (!nativeTab) {
        nativeTab = tabFrom({url: location.href, title: document.title, native: true});
        tabs.push(nativeTab);
    }
    while (tabs.length > MAX_TABS) {
        var drop = tabs.filter(function (t) { return !t.native; }).sort(function (a, b) { return a.used - b.used; })[0];
        tabs.splice(tabs.indexOf(drop), 1);
    }
    tabs.forEach(drawTab);
    activate(nativeTab);
})();
