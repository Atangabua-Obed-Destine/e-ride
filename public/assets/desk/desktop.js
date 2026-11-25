/*
 * PosInnova desktop workspace (styles: public/pi-desktop.css, parts of the
 * window: resources/views/layouts/partials/desktop.blade.php).
 *
 * E-Ride's copy (public/assets/desk/desktop.js, styles pi-desktop.css beside
 * it): posinnova's file, kept as close to it as possible so fixes can be
 * copied across both ways. What E-Ride needs differently sits in the section
 * marked "E-Ride" below; the few lines that use it say "E-Ride". It runs the
 * bus branch portal, the head office portal and the accounting frame.
 *
 * On a wide screen (1024px and up) the back office opens like an installed
 * program: a title bar with menus, the header as a toolbar, the screens
 * opened as tabs, the sidebar as an icon rail, a status bar and a "Go to…"
 * box (Ctrl+K). The menus and the box are built from the sidebar, so they
 * offer exactly what the sidebar offers, permissions included.
 *
 * Tabs stay alive: each screen opens in a frame of its own laid over the
 * screen area, so going back to a tab shows it as it was left. Besides Home,
 * two screens stay open at a time; a third asks first. The POS screen and the
 * customer display keep the whole window, as before.
 *
 * Nothing on a screen changes: it scrolls, prints and saves as it always did.
 * Inside a tab this script only hands keys and links back to the window
 * (html.pi-framed). Phones and narrow windows never run the workspace.
 */
(function () {
    'use strict';

    var html = document.documentElement;
    var cfg = window.PI_DESK_CFG || {};
    var T = cfg.text || {};
    var tx = function (k, d) { return T[k] || d; };
    var fill = function (s, v) { return String(s || '').replace(/:(\w+)/g, function (m, k) { return v[k] != null ? v[k] : m; }); };
    var make = function (tag, cls, text) {
        var el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text != null) el.textContent = text;
        return el;
    };
    var textOf = function (el) { return el ? el.textContent.replace(/\s+/g, ' ').trim() : ''; };
    var toURL = function (href) { try { return new URL(href, location.href); } catch (e) { return null; } };
    var local = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } }
    };

    // The app's own address: /posinnova/public/ on XAMPP, / on a server.
    var BASE = (function () {
        var u = toURL((cfg.base || location.origin) + '/');
        return u ? u.pathname.replace(/\/+$/, '') + '/' : '/';
    })();
    // A page's path inside the app ("sells/create"), or null outside it.
    var relPath = function (u) {
        var p = u.pathname;
        if (p.indexOf(BASE) === 0) return p.slice(BASE.length).replace(/\/+$/, '');
        if (p + '/' === BASE) return '';
        return null;
    };
    // Pages that take the whole window, as layouts/app.blade.php decides
    // (the POS screen, the customer display), and the ways in and out.
    var isWindowPage = function (u) {
        var r = relPath(u);
        if (r === null) return false;
        var s = r.split('/');
        return ER.windowPage(s); // E-Ride
    };
    // ── E-Ride ─────────────────────────────────────────────────────────────
    // Set by resources/views/layouts/partials/desktop.blade.php:
    //  - the screens are the bus portals and the accounting, all under
    //    partner/ (the public bus site and the admin panel are not);
    //  - the whole-window pages are the sign-in pages (posinnova's are the
    //    POS screen, the customer display and its sign-in pages);
    //  - a CSV statement downloads, as an export does on posinnova;
    //  - cfg.alias: in a portal, every accounting screen belongs to the one
    //    "Accounting" tab (its menu entry opens the books, then the screens);
    //  - cfg.suffix: the end of the page titles (" · Agency"), cfg.strip: the
    //    ends of other titles shown here (a portal's, in the accounting);
    //    cfg.homeIcon;
    //  - the menu marks its screen with .active, messages are busToast's;
    //  - the portals ask the server with fetch, not jQuery: the connection
    //    in the status bar follows fetch too;
    //  - a screen let go (freeNative) stops asking the server and stops its
    //    camera: its timers would otherwise run on behind the new screen.
    var ER = {
        windowPage: function (s) { return s[0] === 'partner' && /^(login|logout)$/.test(s[2] || ''); },
        screen: function (r) { return /^partner\//.test(r) && !/(^|\/)csv$/i.test(r); },
        alias: function (u) {
            var r = relPath(u);
            if (r === null || !cfg.alias) return null;
            for (var from in cfg.alias) {
                if (r === from || r.indexOf(from + '/') === 0) return (BASE + cfg.alias[from]).replace(/\/+$/, '');
            }
            return null;
        },
        active: function (a) { return a.classList.contains('active'); },
        untitle: function (t) {
            (cfg.strip || []).forEach(function (end) { if (end && t.slice(-end.length) === end) t = t.slice(0, -end.length); });
            return t;
        },
        notice: function (text) { if (window.busToast) window.busToast(text); },
        watchFetch: function (report) {
            var f = window.fetch;
            if (!f || f.piWatched) return;
            window.fetch = function () {
                return f.apply(this, arguments).then(function (r) { report('online'); return r; }, function (err) {
                    if (!err || err.name !== 'AbortError') report(navigator.onLine === false ? 'offline' : 'unreachable');
                    throw err;
                });
            };
            window.fetch.piWatched = true;
        },
        letGo: function () {
            document.querySelectorAll('video').forEach(function (v) {
                try { if (v.srcObject) v.srcObject.getTracks().forEach(function (t) { t.stop(); }); } catch (e) { /* ignore */ }
            });
            window.fetch = function () { return new Promise(function () {}); };
            try { window.dispatchEvent(new Event('pi-desk-freed')); } catch (e) { /* ignore */ }
        }
    };

    // A screen of the back office that opens in a tab. Files, downloads and
    // exports leave the page where it is, so they are left to the browser.
    var isScreen = function (u) {
        if (!u || u.origin !== location.origin) return false;
        var r = relPath(u);
        if (r === null || isWindowPage(u) || !ER.screen(r)) return false; // E-Ride
        if (/\.[a-z0-9]{2,5}$/i.test(r.split('/').pop())) return false;
        if (/(^|[\/_-])(export|download)/i.test(r)) return false;
        return true;
    };
    // What a plain click on this link would open, if it is a screen.
    var linkTarget = function (a) {
        if (!a || a.hasAttribute('download') || a.hasAttribute('data-pi-skip')) return null;
        var tg = (a.getAttribute('target') || '').toLowerCase();
        if (tg && tg !== '_self') return null;
        var raw = (a.getAttribute('href') || '').trim();
        if (!raw || raw.charAt(0) === '#' || /^(javascript|mailto|tel|data|blob):/i.test(raw)) return null;
        return toURL(a.href);
    };
    var plainClick = function (e) { return !(e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey); };
    var crossDocument = function (e) {
        return e.cancelable && !e.hashChange && !e.downloadRequest && !(e.destination && e.destination.sameDocument);
    };

    // ── The sidebar's screens: for the menus and the "Go to…" box ─────────
    var readSidebar = function (side) {
        var items = []; // { heading } | { link } | { label, links }
        Array.prototype.forEach.call(side.children, function (el) {
            if (el.classList.contains('side-bar-area-heading')) { items.push({ heading: textOf(el) }); return; }
            if (el.tagName === 'A' && el.getAttribute('href') && el.getAttribute('href') !== '#') { items.push({ link: el }); return; }
            var drop = el.querySelector(':scope > a.drop_down');
            if (drop) {
                var links = Array.prototype.filter.call(el.querySelectorAll('.chiled a[href]'), function (a) { return a.getAttribute('href') && a.getAttribute('href') !== '#'; });
                if (links.length) items.push({ label: textOf(drop), links: links });
            }
        });
        // Every screen of the sidebar, with the area or group it is in.
        var screens = [];
        var seen = {};
        var group = '';
        items.forEach(function (it) {
            if (it.heading) { group = it.heading; return; }
            var add = function (a, g) {
                if (seen[a.href]) return;
                seen[a.href] = true;
                var name = textOf(a);
                if (name) screens.push({ name: name, detail: g, href: a.href, hay: (name + ' ' + g).toLowerCase() });
            };
            if (it.link) add(it.link, group);
            else it.links.forEach(function (a) { add(a, it.label); });
        });
        return { items: items, screens: screens };
    };

    // ── "Go to…" (Ctrl+K): a box to find any screen of the menu by typing ──
    var score = function (item, words) {
        var name = item.name.toLowerCase();
        var total = 0;
        for (var i = 0; i < words.length; i++) {
            var w = words[i];
            var at = name.indexOf(w);
            if (at >= 0) { total += (at === 0 || name.charAt(at - 1) === ' ') ? 4 : 3; continue; }
            if (item.hay.indexOf(w) < 0) return -1;
            total += 1;
        }
        return total;
    };
    // go(href) opens the chosen screen; closed() runs when the box is closed without a choice.
    var makePalette = function (screens, go, closed) {
        var palette = null;
        return function () {
            if (palette) { palette.input.focus(); return; }
            var back = make('div');
            back.id = 'pi-palette';
            back.setAttribute('role', 'dialog');
            back.setAttribute('aria-modal', 'true');
            back.setAttribute('aria-label', tx('palette_placeholder', 'Go to'));
            var box = make('div', 'pi-pal-box');
            var input = make('input');
            input.type = 'text';
            input.autocomplete = 'off';
            input.spellcheck = false;
            input.enterKeyHint = 'go';
            input.placeholder = tx('palette_placeholder', '');
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-expanded', 'true');
            input.setAttribute('aria-controls', 'pi-pal-list');
            var list = make('ul');
            list.id = 'pi-pal-list';
            list.setAttribute('role', 'listbox');
            var hint = make('div', 'pi-pal-hint', tx('palette_hint', ''));
            box.appendChild(input); box.appendChild(list); box.appendChild(hint);
            back.appendChild(box);
            document.body.appendChild(back);
            var items = [];
            var at = 0;
            var close = function () { back.remove(); palette = null; if (closed) closed(); };
            var pick = function (item) { if (!item) return; back.remove(); palette = null; go(item.href); };
            var draw = function () {
                var words = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
                items = words.length
                    ? screens.map(function (s) { return [s, score(s, words)]; }).filter(function (x) { return x[1] >= 0; })
                        .sort(function (a, b) { return b[1] - a[1]; }).map(function (x) { return x[0]; }).slice(0, 10)
                    : screens.slice(0, 10);
                if (at >= items.length) at = 0;
                list.replaceChildren();
                items.forEach(function (s, n) {
                    var li = make('li', n === at ? 'on' : '');
                    li.id = 'pi-pal-' + n;
                    li.setAttribute('role', 'option');
                    li.setAttribute('aria-selected', String(n === at));
                    li.appendChild(make('span', null, s.name));
                    li.appendChild(make('small', null, s.detail || ''));
                    li.addEventListener('mousedown', function (ev) { ev.preventDefault(); pick(s); });
                    list.appendChild(li);
                });
                if (!items.length) list.appendChild(make('li', 'pi-pal-empty', tx('palette_empty', '')));
                input.setAttribute('aria-activedescendant', items.length ? 'pi-pal-' + at : '');
                var on = list.querySelector('.on');
                if (on) on.scrollIntoView({ block: 'nearest' });
            };
            input.addEventListener('input', function () { at = 0; draw(); });
            input.addEventListener('keydown', function (ev) {
                if (ev.key === 'ArrowDown') { ev.preventDefault(); at = Math.min(items.length - 1, at + 1); draw(); }
                else if (ev.key === 'ArrowUp') { ev.preventDefault(); at = Math.max(0, at - 1); draw(); }
                else if (ev.key === 'Enter') { ev.preventDefault(); pick(items[at]); }
                else if (ev.key === 'Escape') { ev.preventDefault(); close(); }
            });
            back.addEventListener('mousedown', function (ev) { if (ev.target === back) close(); });
            palette = { input: input };
            draw();
            input.focus();
        };
    };

    // ── A screen shown inside a workspace tab ──────────────────────────────
    var desk = null;
    try { if (window.parent !== window && window.parent.PI_DESK) desk = window.parent.PI_DESK; } catch (e) { /* not ours */ }
    if (desk) {
        html.classList.add('pi-framed');
        // The window's header already counts new notifications.
        if (typeof window.getTotalUnreadNotifications === 'function') window.getTotalUnreadNotifications = function () {};
        document.addEventListener('keydown', function (e) {
            if (e.defaultPrevented) return;
            var k = (e.key || '').toLowerCase();
            var ctl = e.ctrlKey || e.metaKey;
            if (ctl && !e.shiftKey && !e.altKey && k === 'k') { e.preventDefault(); desk.palette(); }
            else if (e.key === 'F10' && !e.shiftKey && !ctl) { e.preventDefault(); desk.menus(); }
            else if (e.altKey && !ctl && /^[1-9]$/.test(e.key)) { e.preventDefault(); desk.tab(Number(e.key)); }
            else if (e.altKey && !ctl && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) { e.preventDefault(); desk.step(e.key === 'ArrowLeft' ? -1 : 1); }
            // Refresh and print this tab's screen, not the whole window.
            else if ((e.key === 'F5' && !ctl && !e.shiftKey) || (ctl && !e.shiftKey && !e.altKey && k === 'r')) { e.preventDefault(); desk.busy(window, true); location.reload(); }
            else if (ctl && !e.shiftKey && !e.altKey && k === 'p') { e.preventDefault(); window.print(); }
        });
        document.addEventListener('click', function (e) {
            if (!plainClick(e)) return;
            var a = e.target.closest && e.target.closest('a[href]');
            var u = linkTarget(a);
            if (!u) return;
            // The POS screen takes the whole window, as it always has.
            if (isWindowPage(u)) { e.preventDefault(); desk.leave(u.href); return; }
            // On Home a link opens a tab of its own.
            if (isScreen(u) && desk.link(window, u.href)) e.preventDefault();
        });
        // Pages that move on by script (window.location = ...).
        if (window.navigation && window.navigation.addEventListener) {
            window.navigation.addEventListener('navigate', function (e) {
                if (!crossDocument(e) || e.navigationType === 'traverse') return;
                var u = toURL(e.destination.url);
                if (u && isWindowPage(u) && !e.formData) { e.preventDefault(); desk.leave(u.href); return; }
                if (u && !e.formData && e.navigationType !== 'reload' && isScreen(u) && desk.link(window, u.href)) { e.preventDefault(); return; }
                desk.busy(window, true);
            });
            window.navigation.addEventListener('navigatesuccess', function () { desk.moved(window); });
        }
        ER.watchFetch(desk.conn); // E-Ride
        if (window.jQuery) {
            window.jQuery(document).ajaxSuccess(function () { desk.conn('online'); });
            window.jQuery(document).ajaxError(function (ev, xhr) { if (xhr && xhr.status === 0 && xhr.statusText !== 'abort') desk.conn('unreachable'); });
        }
        return;
    }

    // ── The window ─────────────────────────────────────────────────────────
    var side = document.getElementById('side-bar');
    var aside = document.querySelector('aside.side-bar');
    var header = document.getElementById('pi-header');
    var strip = document.getElementById('pi-tabs');
    var bar = document.getElementById('pi-menubar');
    var statusBar = document.getElementById('pi-status');
    var main = document.querySelector('.thetop > main');
    var container = document.getElementById('scrollable-container');
    if (!html.classList.contains('pi-desk')) {
        // A phone or a narrow window: no workspace, but the same "Go to…" box,
        // from the search button beside the menu button (or Ctrl+K). The
        // chosen screen opens as any link does.
        if (!side) return;
        var openGoTo = makePalette(readSidebar(side).screens, function (href) { location.href = href; });
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('[data-pi="go"]')) { e.preventDefault(); openGoTo(); }
        });
        document.addEventListener('keydown', function (e) {
            if (!e.defaultPrevented && (e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && (e.key || '').toLowerCase() === 'k') { e.preventDefault(); openGoTo(); }
        });
        return;
    }
    if (!side || !header || !strip || !bar || !main || !container || !cfg.home) return;
    var wide = window.matchMedia('(min-width: 1024px)');
    // The whole window going somewhere on purpose (sign in, the POS screen):
    // the address guard below lets it through.
    var leaving = false;
    var goWindow = function (href) { leaving = true; setTimeout(function () { leaving = false; }, 4000); location.href = href; };
    var CHROME = '#pi-titlebar, #pi-header, aside.side-bar, #pi-tabs, #pi-status, .posinnova-ticker, #pi-palette, .pi-dialog';

    // The header's height, for the tabs below it.
    var measure = function () { html.style.setProperty('--pi-head', Math.round(header.getBoundingClientRect().height) + 'px'); };
    measure();
    if (window.ResizeObserver) new ResizeObserver(measure).observe(header);

    // ── Read the sidebar ──
    var isActive = function (a) { return a.classList.contains('tw-text-primary-700') || ER.active(a); }; // E-Ride
    var homeU = toURL(cfg.home);
    var isHomeLink = function (a) { var u = toURL(a.href); return !!u && !!homeU && u.pathname.replace(/\/+$/, '') === homeU.pathname.replace(/\/+$/, ''); };
    var sidebar = readSidebar(side);
    var rawItems = sidebar.items;
    // Every screen of the sidebar, for the "Go to…" box and the tab names.
    var screens = sidebar.screens;

    // A screen's identity: its path, and the query words the menu itself
    // uses to tell screens apart (Suppliers and Customers share /contacts).
    var identity = {};
    screens.forEach(function (s) {
        var u = toURL(s.href);
        if (!u) return;
        var p = u.pathname.replace(/\/+$/, '');
        identity[p] = identity[p] || [];
        u.searchParams.forEach(function (v, k) { if (identity[p].indexOf(k) < 0) identity[p].push(k); });
    });
    var keyOf = function (href) {
        var u = toURL(href);
        if (!u) return href;
        var p = ER.alias(u) || u.pathname.replace(/\/+$/, ''); // E-Ride
        return p + (identity[p] || []).map(function (k) { return '|' + k + '=' + (u.searchParams.get(k) || ''); }).join('');
    };

    // ── Menus ──
    // A business whose sidebar has area headings (the hospital menu) gets one
    // menu per heading. Otherwise each group goes where its screens belong.
    var RULES = [
        [/^superadmin/, 'superadmin'],
        [/^(sells|pos|sell-return|shipments|discount|import-sales|sells-by-shift|types-of-service|bookings|modules\/(kitchen|orders)|subscriptions|sales-order|sell)/, 'sales'],
        [/^(products|purchase|purchases|purchase-order|purchase-requisition|purchase-return|stock-transfers|stock-adjustments|inventory-count|departments|department-issues|labels|variation-templates|units|taxonomies|brands|warranties|selling-price-group|update-product-price|import-products|import-opening-stock|manufacturing|opening-stock)/, 'stock'],
        [/^(contacts|customer-group|crm)/, 'contacts'],
        [/^(expenses|expense-categories|account|accounting|payment-account)/, 'money'],
        [/^reports/, 'reports'],
        [/^(users|roles|sales-commission-agents|hrm|essentials)/, 'people'],
        [/^(business|business-location|invoice-schemes|invoice-layouts|barcodes|printers|tax-rates|backup|notification-templates|manage-modules|subscription|shift-settings|payment-repair|settings)/, 'settings']
    ];
    var classify = function (href) {
        var u = toURL(href);
        var r = u ? relPath(u) : null;
        if (r === null) return 'more';
        for (var i = 0; i < RULES.length; i++) if (RULES[i][0].test(r)) return RULES[i][1];
        return 'more';
    };
    var ORDER = ['go', 'sales', 'stock', 'contacts', 'money', 'people', 'reports', 'settings', 'superadmin', 'more'];
    var menus = [];
    var menuFor = function (key, label) {
        for (var i = 0; i < menus.length; i++) if (menus[i].key === key) return menus[i];
        var m = { key: key, label: label || tx('menu_' + key, key), sections: [] };
        menus.push(m);
        return m;
    };
    var sectionOf = function (m, label) {
        var last = m.sections[m.sections.length - 1];
        if (label === null && last && last.label === null) return last;
        var s = { label: label, links: [] };
        m.sections.push(s);
        return s;
    };
    var asLink = function (a) { return { text: textOf(a), href: a.href, current: isActive(a) }; };
    var hasHeadings = rawItems.some(function (it) { return !!it.heading; });
    if (hasHeadings) {
        var cur = null;
        rawItems.forEach(function (it, i) {
            if (it.heading) { cur = menuFor('h' + i, it.heading); return; }
            var m = cur || menuFor('go');
            if (it.link) { if (!isHomeLink(it.link)) sectionOf(m, null).links.push(asLink(it.link)); }
            else sectionOf(m, it.label).links = it.links.map(asLink);
        });
    } else {
        rawItems.forEach(function (it) {
            if (it.heading) return;
            if (it.link) {
                if (isHomeLink(it.link)) return;
                sectionOf(menuFor(classify(it.link.href)), null).links.push(asLink(it.link));
                return;
            }
            var votes = {};
            it.links.forEach(function (a) { var k = classify(a.href); votes[k] = (votes[k] || 0) + 1; });
            var best = Object.keys(votes).sort(function (a, b) { return votes[b] - votes[a]; })[0];
            sectionOf(menuFor(best), it.label).links = it.links.map(asLink);
        });
        menus.sort(function (a, b) { return ORDER.indexOf(a.key) - ORDER.indexOf(b.key); });
    }

    var openItem = null;
    var closeMenus = function () {
        if (!openItem) return;
        openItem.classList.remove('open');
        openItem.querySelector('.pi-mb-top').setAttribute('aria-expanded', 'false');
        openItem = null;
    };
    var openMenu = function (item, focusFirst) {
        if (openItem && openItem !== item) closeMenus();
        openItem = item;
        item.classList.add('open');
        item.querySelector('.pi-mb-top').setAttribute('aria-expanded', 'true');
        if (focusFirst) { var f = item.querySelector('.pi-mb-link'); if (f) f.focus(); }
    };
    menus.forEach(function (m) {
        var sections = m.sections.filter(function (s) { return s.links.length; });
        if (!sections.length) return;
        var count = sections.reduce(function (n, s) { return n + s.links.length + (s.label ? 1 : 0); }, 0);
        var item = make('div', 'pi-mb-item');
        var top = make('button', 'pi-mb-top', m.label);
        top.type = 'button';
        top.setAttribute('role', 'menuitem');
        top.setAttribute('aria-haspopup', 'true');
        top.setAttribute('aria-expanded', 'false');
        // A long menu reads better in columns than in one tall list.
        var drop = make('div', 'pi-mb-drop' + (count > 44 ? ' pi-cols-3' : count > 20 ? ' pi-cols-2' : ''));
        drop.setAttribute('role', 'menu');
        sections.forEach(function (s) {
            var sec = make('div', 'pi-mb-sec');
            if (s.label && sections.length > 1) sec.appendChild(make('div', 'pi-mb-head', s.label));
            s.links.forEach(function (l) {
                var a = make('a', 'pi-mb-link' + (l.current ? ' current' : ''), l.text);
                a.href = l.href;
                a.setAttribute('role', 'menuitem');
                if (l.current) item.classList.add('current');
                sec.appendChild(a);
            });
            drop.appendChild(sec);
        });
        item.appendChild(top);
        item.appendChild(drop);
        bar.appendChild(item);
        top.addEventListener('click', function () { if (openItem === item) closeMenus(); else openMenu(item); });
        top.addEventListener('mouseenter', function () { if (openItem && openItem !== item) openMenu(item); });
    });
    bar.addEventListener('keydown', function (e) {
        var item = e.target.closest('.pi-mb-item');
        if (!item) return;
        var all = Array.prototype.slice.call(bar.querySelectorAll('.pi-mb-item'));
        var i = all.indexOf(item);
        var links = Array.prototype.slice.call(item.querySelectorAll('.pi-mb-link'));
        var j = links.indexOf(e.target);
        var rtl = getComputedStyle(bar).direction === 'rtl';
        if (e.key === 'Escape') { closeMenus(); item.querySelector('.pi-mb-top').focus(); e.preventDefault(); }
        else if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
            var fwd = (e.key === 'ArrowRight') !== rtl;
            var next = all[(i + (fwd ? 1 : -1) + all.length) % all.length];
            var wasOpen = !!openItem;
            closeMenus();
            next.querySelector('.pi-mb-top').focus();
            if (wasOpen) openMenu(next, true);
            e.preventDefault();
        } else if (e.key === 'ArrowDown') {
            if (j < 0) openMenu(item, true); else links[Math.min(j + 1, links.length - 1)].focus();
            e.preventDefault();
        } else if (e.key === 'ArrowUp' && j >= 0) {
            (j === 0 ? item.querySelector('.pi-mb-top') : links[j - 1]).focus();
            e.preventDefault();
        }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest || !e.target.closest('#pi-menubar')) closeMenus(); });
    var markMenus = function (href) {
        var k = keyOf(href);
        bar.querySelectorAll('.pi-mb-item').forEach(function (item) {
            var any = false;
            item.querySelectorAll('.pi-mb-link').forEach(function (a) { var on = keyOf(a.href) === k; a.classList.toggle('current', on); any = any || on; });
            item.classList.toggle('current', any);
        });
        // E-Ride: the rail marks the screen on screen too (not the page this window loaded).
        side.querySelectorAll('a[href]:not(.drop_down)').forEach(function (a) { a.classList.toggle('active', keyOf(a.href) === k); });
        side.querySelectorAll('a.drop_down').forEach(function (d) { d.classList.toggle('pi-has-current', !!(d.nextElementSibling && d.nextElementSibling.querySelector('a.active'))); });
    };

    // ── Sidebar as an icon rail; the header's sidebar button widens it ──
    side.querySelectorAll('a').forEach(function (a) { if (!a.getAttribute('title')) a.setAttribute('title', textOf(a)); });
    var setFull = function (on) {
        html.classList.toggle('pi-side-full', on);
        local.set('pi.desk.sideFull', on ? '1' : '0');
    };
    document.addEventListener('click', function (e) {
        if (!wide.matches) return;
        if (e.target.closest && e.target.closest('.side-bar-collapse')) {
            // In the workspace this button widens and narrows the menu instead of hiding it.
            e.preventDefault();
            e.stopImmediatePropagation();
            setFull(!html.classList.contains('pi-side-full'));
            return;
        }
        var drop = e.target.closest && e.target.closest('#side-bar a.drop_down');
        if (drop && !html.classList.contains('pi-side-full')) {
            // A group clicked on the rail opens the full menu at that group.
            setFull(true);
            var sub = drop.nextElementSibling;
            if (sub && sub.classList.contains('chiled') && sub.style.display !== 'none') { e.preventDefault(); e.stopImmediatePropagation(); }
        }
    }, true);

    // ── Tabs: the screens open in this window, kept alive ──────────────────
    // The page this window loaded is the first screen. Every other screen
    // opens in a frame of its own laid over the screen area, so going back to
    // a tab shows the screen exactly as it was left: no reload, scroll and
    // typing kept. Besides Home, two screens stay open at a time; opening a
    // third asks first, naming the one that will close.
    var MAX_TABS = 2;
    var LIVE = 'pi.desk.live';
    var homeKey = keyOf(cfg.home);
    var homeTitle = tx('home', 'Home');
    var bizSuffix = cfg.suffix != null ? cfg.suffix : cfg.business ? ' - ' + cfg.business : ''; // E-Ride: cfg.suffix
    var titleOf = function (doc) {
        var t = ER.untitle((doc.title || '').trim()); // E-Ride
        if (bizSuffix && t.slice(-bizSuffix.length) === bizSuffix) t = t.slice(0, -bizSuffix.length);
        else if (t.lastIndexOf(' - ') > 0) t = t.slice(0, t.lastIndexOf(' - '));
        return t.trim();
    };
    var labelFor = function (href) {
        var k = keyOf(href);
        for (var i = 0; i < screens.length; i++) if (keyOf(screens[i].href) === k) return screens[i].name;
        return tx('opening', '…');
    };
    // Tabs belong to this browser window and this person: kept for a
    // refresh, not shared, and dropped when someone else signs in here.
    var saved = (function () { try { return JSON.parse(sessionStorage.getItem(LIVE) || 'null') || {}; } catch (e) { return {}; } })();
    if (saved.who !== cfg.who) saved = {};
    var seq = saved.seq || 1;
    var home = { id: 'home', url: cfg.home, title: homeTitle, pinned: true, stack: [], pos: -1 };
    var open = (Array.isArray(saved.tabs) ? saved.tabs : []).filter(function (t) {
        var u = t && t.url && toURL(t.url);
        return u && t.id && isScreen(u) && keyOf(t.url) !== homeKey;
    }).slice(-MAX_TABS).map(function (t) { return { id: t.id, url: t.url, title: t.title || '', used: t.used || 0, stack: [], pos: -1 }; });
    var all = function () { return [home].concat(open); };
    var active = null;
    var save = function () {
        try {
            sessionStorage.setItem(LIVE, JSON.stringify({
                seq: seq, who: cfg.who, active: active ? active.id : null, at: location.href,
                tabs: open.map(function (t) { return { id: t.id, url: t.url, title: t.title, used: t.used }; })
            }));
        } catch (e) { /* private mode */ }
    };
    var byOldest = function (a, b) { return (a.used || 0) - (b.used || 0); };
    // The tab to close when a third is wanted: the one used longest ago, never the one on screen.
    var leastUsed = function () { return open.filter(function (t) { return t !== active; }).sort(byOldest)[0] || null; };
    var notice = function (text) {
        if (window.toastr) window.toastr.info(text);
        else ER.notice(text); // E-Ride
    };
    var dropped = function (t) { notice(fill(tx('tab_dropped', '“:old” was closed.'), { old: t.title })); };

    // This page's own content is the screen of one tab.
    var nativeKey = keyOf(location.href);
    var native = null;
    if (nativeKey === homeKey) native = home;
    else {
        native = open.filter(function (t) { return keyOf(t.url) === nativeKey; })[0];
        if (!native) {
            native = { id: 't' + (seq++), url: location.href, title: '', stack: [], pos: -1 };
            if (open.length >= MAX_TABS) {
                var gone = open.slice().sort(byOldest)[0];
                open.splice(open.indexOf(gone), 1);
                setTimeout(function () { dropped(gone); }, 600);
            }
            open.push(native);
        }
    }
    native.url = location.href;
    if (native !== home) native.title = titleOf(document) || labelFor(location.href);
    native.isNative = true;
    native.stack = [location.href];
    native.pos = 0;
    native.used = Date.now();
    native.ready = true;
    active = native;

    var record = function (t, href) {
        if (t.goingTo != null) { t.pos = t.goingTo; t.goingTo = null; }
        else if (t.stack[t.pos] !== href) { t.stack = t.stack.slice(0, t.pos + 1); t.stack.push(href); t.pos = t.stack.length - 1; }
        t.url = href;
    };

    // The frames, laid over the screen area: below the tabs, beside the
    // sidebar, above the status bar.
    var host = make('div');
    host.id = 'pi-frames';
    host.setAttribute('data-opening', tx('opening', 'Opening…'));
    document.body.appendChild(host);
    var busyLine = make('div');
    busyLine.id = 'pi-busy';
    document.body.appendChild(busyLine);
    var place = function () {
        var m = main.getBoundingClientRect();
        var top = strip.getBoundingClientRect().bottom;
        var bottom = statusBar ? statusBar.getBoundingClientRect().top : window.innerHeight;
        var box = { left: m.left + 'px', top: top + 'px', width: m.width + 'px', height: Math.max(0, bottom - top) + 'px' };
        Object.keys(box).forEach(function (k) { host.style[k] = box[k]; });
        busyLine.style.left = box.left; busyLine.style.top = box.top; busyLine.style.width = box.width;
    };
    place();
    if (window.ResizeObserver) { var ro = new ResizeObserver(place); ro.observe(main); ro.observe(header); ro.observe(strip); }
    window.addEventListener('resize', place);
    // The support bar below can be closed, which moves the status bar down.
    new MutationObserver(function () { requestAnimationFrame(place); }).observe(document.body, { attributes: true, attributeFilter: ['class'] });

    var frameLoaded;
    var makeFrame = function (t, name) {
        var f = make('iframe');
        f.title = t.title || '';
        if (name) f.name = name;
        f.piTab = t;
        f.addEventListener('load', function () { if (frameLoaded) frameLoaded(f); });
        host.appendChild(f);
        return f;
    };
    var tabOf = function (win) { return all().filter(function (t) { return t.frame && t.frame.contentWindow === win; })[0]; };

    // Forms on this page's own screen are sent into its tab's frame, so
    // sending one does not reload the window and close the other tabs.
    var nativeFrame = makeFrame(native, 'pi-native');
    var aim = function (f) {
        if (!nativeFrame || f.getAttribute('target') || f.hasAttribute('data-pi-skip') || f.closest(CHROME)) return;
        var u = toURL(f.getAttribute('action') || location.href);
        if (!u || u.origin !== location.origin || isWindowPage(u)) return;
        f.setAttribute('target', 'pi-native');
    };
    document.querySelectorAll('form').forEach(aim);
    var formWatch = new MutationObserver(function (changes) {
        changes.forEach(function (c) {
            c.addedNodes.forEach(function (n) {
                if (n.nodeType !== 1) return;
                if (n.tagName === 'FORM') aim(n);
                else if (n.querySelectorAll) n.querySelectorAll('form').forEach(aim);
            });
        });
    });
    formWatch.observe(document.body, { childList: true, subtree: true });
    // Once this page's screen has been replaced, let it go.
    var freeNative = function () {
        if (native.freed) return;
        native.freed = true;
        formWatch.disconnect();
        ER.letGo(); // E-Ride
        // A dialog of the old screen must not stay over the new one.
        if (window.jQuery) {
            try { window.jQuery('.modal.in, .modal.show').modal('hide'); } catch (e) { /* not a bootstrap modal */ }
            window.jQuery('.modal-backdrop').remove();
            window.jQuery('body').removeClass('modal-open');
        }
        container.replaceChildren();
        // From now on the address follows the tab on screen.
        try { history.replaceState(history.state, '', active.url); } catch (e) { /* ignore */ }
    };

    // The busy line and the tab's spinner follow the screen loading.
    var busyTimer = {};
    var drawTabs;
    var syncBusy = function () {
        var on = !!(active && active.loading);
        busyLine.classList.toggle('on', on);
        host.classList.toggle('pi-opening', !!(active && active.frame && !active.ready));
    };
    var setBusy = function (t, on) {
        t.loading = on;
        clearTimeout(busyTimer[t.id]);
        // A download never finishes loading a page; stop waiting for it.
        if (on) busyTimer[t.id] = setTimeout(function () { t.loading = false; drawTabs(); syncBusy(); }, 15000);
        drawTabs();
        syncBusy();
    };

    var titleBar = document.querySelector('#pi-titlebar .pi-tb-title');
    var suffix = bizSuffix || '';
    var show = function (t) {
        var framed = !!t.frame;
        host.classList.toggle('on', framed);
        html.classList.toggle('pi-frame-on', framed);
        container.inert = framed;
        host.querySelectorAll('iframe').forEach(function (f) { f.classList.toggle('on', f === t.frame && !!t.ready); });
        if (titleBar) titleBar.textContent = t.title;
        document.title = (t.title || '') + suffix;
        markMenus(t.url);
        syncBusy();
        requestAnimationFrame(place);
        // The address follows the tab on screen, so a refresh opens it; not
        // while this page's own screen is alive, whose forms may post to it.
        if (native.freed) { try { history.replaceState(history.state, '', t.url); } catch (e) { /* ignore */ } }
    };
    drawTabs = function () {
        strip.replaceChildren();
        all().forEach(function (t) {
            var a = make('a', 'pi-tab' + (t === active ? ' active' : '') + (t.pinned ? ' pinned' : '') + (t.loading ? ' loading' : ''));
            a.href = t.url;
            a.title = t.title;
            a.dataset.id = t.id;
            a.setAttribute('role', 'tab');
            a.setAttribute('aria-selected', String(t === active));
            if (t.pinned) a.appendChild(make('i', cfg.homeIcon || 'fas fa-home')); // E-Ride: cfg.homeIcon
            a.appendChild(make('span', null, t.title || tx('opening', '…')));
            if (t.badge > 0 && t !== active) a.appendChild(make('b', 'pi-tab-badge', t.badge > 99 ? '99+' : String(t.badge)));
            if (!t.pinned) {
                var x = make('button', 'pi-tab-x', '×');
                x.type = 'button';
                x.title = tx('tab_close', 'Close this tab');
                x.setAttribute('aria-label', tx('tab_close', 'Close this tab') + ': ' + t.title);
                a.appendChild(x);
            }
            strip.appendChild(a);
            if (t === active) requestAnimationFrame(function () { a.scrollIntoView({ inline: 'nearest', block: 'nearest' }); });
        });
    };
    var focusTab = function (t) { if (t.frame && t.ready) { try { t.frame.contentWindow.focus(); } catch (e) { /* ignore */ } } };
    var activate = function (t) {
        if (!t) return;
        closeMenus();
        active = t;
        t.badge = 0;
        t.used = Date.now();
        // A tab not loaded yet (after a refresh, or this page's own once let go) loads now.
        if (!t.frame && (!t.isNative || t.freed)) {
            t.frame = makeFrame(t);
            t.stack = [];
            t.pos = -1;
            t.ready = false;
            setBusy(t, true);
            t.frame.src = t.url;
        }
        show(t);
        drawTabs();
        save();
        focusTab(t);
    };
    var closeTab = function (t, keepActive) {
        var i = open.indexOf(t);
        if (i < 0) return;
        open.splice(i, 1);
        clearTimeout(busyTimer[t.id]);
        if (t.frame) t.frame.remove();
        if (t.isNative) {
            freeNative();
            if (nativeFrame && nativeFrame !== t.frame) nativeFrame.remove();
            nativeFrame = null;
        }
        if (active === t && !keepActive) activate(open[i] || open[i - 1] || home);
        else { drawTabs(); save(); }
    };

    // The question before a third screen closes one.
    var askClose = function (victim, nextTitle, ok) {
        var v = { 'new': nextTitle, old: victim.title };
        var d = make('dialog', 'pi-dialog');
        d.setAttribute('aria-labelledby', 'pi-dialog-title');
        var h = make('h3', null, tx('tabs_full_title', 'Two screens are already open'));
        h.id = 'pi-dialog-title';
        var p = make('p', null, fill(tx('tabs_full', 'Opening “:new” will close “:old”.'), v));
        var row = make('div', 'pi-dialog-row');
        var no = make('button', 'pi-btn', tx('cancel', 'Cancel'));
        no.type = 'button';
        var yes = make('button', 'pi-btn primary', fill(tx('tabs_full_ok', 'Close “:old” and open'), v));
        yes.type = 'button';
        row.appendChild(no);
        row.appendChild(yes);
        d.appendChild(h);
        d.appendChild(p);
        d.appendChild(row);
        document.body.appendChild(d);
        var done = function (go) { if (d.open) d.close(); d.remove(); if (go) ok(); };
        no.addEventListener('click', function () { done(false); });
        yes.addEventListener('click', function () { done(true); });
        d.addEventListener('cancel', function (e) { e.preventDefault(); done(false); });
        d.showModal();
        yes.focus();
    };

    // This page's own screen moves on, in its tab.
    var navigateNative = function (href) {
        if (!nativeFrame) { openUrl(href, true); return; }
        setBusy(native, true);
        nativeFrame.src = href;
    };
    // Open a screen: its tab if it is open (moved to href when fresh), else a new one.
    var openUrl = function (href, fresh) {
        var key = keyOf(href);
        var have = key === homeKey ? home : open.filter(function (x) { return keyOf(x.url) === key; })[0];
        if (have) {
            activate(have);
            if (fresh && have.url !== href) {
                if (have.frame) { setBusy(have, true); have.frame.contentWindow.location.href = href; }
                else if (have.isNative) navigateNative(href);
            }
            return;
        }
        var go = function () {
            var t = { id: 't' + (seq++), url: href, title: labelFor(href), stack: [], pos: -1 };
            open.push(t);
            activate(t);
        };
        if (open.length < MAX_TABS) { go(); return; }
        var victim = leastUsed();
        if (!victim) return;
        askClose(victim, labelFor(href), function () { closeTab(victim, true); go(); });
    };

    frameLoaded = function (f) {
        var t = f.piTab;
        if (!t) return;
        var w;
        var href;
        try { w = f.contentWindow; href = w.location.href; } catch (e) { return; }
        if (!href || href === 'about:blank') return;
        var d = w.document;
        var u = toURL(href);
        var theirs = null;
        try { theirs = w.PI_DESK_CFG || null; } catch (e) { /* ignore */ }
        // Signed out, or signed in as someone else (a superadmin switching
        // users): the whole window follows.
        if (!d.getElementById('side-bar') && d.querySelector('input[type="password"]')) { goWindow(href); return; }
        if (theirs && theirs.who && theirs.who !== cfg.who) { goWindow(href); return; }
        // The POS screen keeps the whole window, as it always has.
        if (u && isWindowPage(u)) { if (t !== home) closeTab(t, true); save(); goWindow(href); return; }
        if (t.isNative && f === nativeFrame && !t.frame) { t.frame = f; freeNative(); }
        t.ready = true;
        record(t, href);
        t.title = titleOf(d) || t.title || labelFor(href);
        f.title = t.title;
        var key = keyOf(href);
        if (t === home && key !== homeKey) {
            // Something sent on Home opened another screen: that is a tab of its own.
            setBusy(home, false);
            var nt = { id: 't' + (seq++), url: href, title: t.title, frame: f, ready: true, stack: home.stack, pos: home.pos, used: Date.now() };
            f.piTab = nt;
            home.frame = null; home.stack = []; home.pos = -1; home.url = cfg.home; home.title = homeTitle; home.ready = false;
            if (home.isNative) home.freed = true;
            if (open.length >= MAX_TABS) {
                var gone = leastUsed() || open[0];
                closeTab(gone, true);
                dropped(gone);
            }
            open.push(nt);
            activate(nt);
            return;
        }
        if (t !== home && key === homeKey) {
            // A screen led back to Home: that is the pinned tab.
            setBusy(t, false);
            var homeAlive = home.frame || (home.isNative && !home.freed);
            open.splice(open.indexOf(t), 1);
            if (homeAlive) f.remove();
            else { home.frame = f; f.piTab = home; home.ready = true; home.stack = t.stack; home.pos = t.pos; }
            activate(home);
            return;
        }
        if (t === home) home.title = homeTitle;
        setBusy(t, false);
        if (t === active) { show(t); focusTab(t); }
        drawTabs();
        save();
    };

    // Back, forward and refresh act on the tab on screen, and only on it.
    var step = function (dir) {
        var t = active;
        if (!t.frame) return;
        var p = t.pos + dir;
        if (p < 0 || p >= t.stack.length) return;
        t.goingTo = p;
        setBusy(t, true);
        t.frame.contentWindow.location.replace(t.stack[p]);
    };
    var reloadTab = function () {
        var t = active;
        if (t.frame) { setBusy(t, true); t.frame.contentWindow.location.reload(); }
        // This page's own screen reloads into its frame, which keeps the other tabs.
        else if (t.isNative) navigateNative(t.url);
    };
    var printTab = function () {
        if (active.frame) { active.frame.contentWindow.focus(); active.frame.contentWindow.print(); }
        else window.print();
    };

    strip.addEventListener('click', function (e) {
        var a = e.target.closest('.pi-tab');
        if (!a) return;
        var t = all().filter(function (x) { return x.id === a.dataset.id; })[0];
        if (!t) return;
        e.preventDefault();
        if (e.target.closest('.pi-tab-x')) { e.stopPropagation(); closeTab(t); return; }
        activate(t);
    });
    strip.addEventListener('auxclick', function (e) {
        var a = e.target.closest('.pi-tab:not(.pinned)');
        if (e.button !== 1 || !a) return;
        e.preventDefault();
        var t = all().filter(function (x) { return x.id === a.dataset.id; })[0];
        if (t) closeTab(t);
    });

    // Links: the menus, the sidebar, the header and Home open screens in
    // tabs; a link inside a screen moves that screen on, like a browser tab.
    document.addEventListener('click', function (e) {
        if (!plainClick(e)) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || a.closest('#pi-tabs')) return;
        // E-Ride: a link marked data-pi-window takes the whole window (the accounting frame's "Back to the portal").
        if (a.hasAttribute('data-pi-window')) { e.preventDefault(); closeMenus(); save(); goWindow(a.href); return; }
        var u = linkTarget(a);
        if (!u || !isScreen(u)) return;
        e.preventDefault();
        closeMenus();
        if (a.closest(CHROME) || native.freed) openUrl(u.href);
        // On Home a link opens a tab of its own; one to Home itself (its
        // Hospital / Pharmacy sales views) moves Home on, in its tab.
        else if (native === home && keyOf(u.href) !== homeKey) openUrl(u.href);
        else navigateNative(u.href);
    });
    document.addEventListener('submit', function (e) {
        if (!e.defaultPrevented && e.target.getAttribute && e.target.getAttribute('target') === 'pi-native') setBusy(native, true);
    });
    // This page's own scripts moving on (window.location = ..., location.reload()).
    if (window.navigation && window.navigation.addEventListener) {
        window.navigation.addEventListener('navigate', function (e) {
            if (leaving || !crossDocument(e) || e.navigationType === 'traverse' || e.formData) return;
            var u = toURL(e.destination.url);
            if (!u) return;
            if (e.navigationType === 'reload') {
                if (!native.freed && open.length) { e.preventDefault(); navigateNative(location.href); }
                // A leftover timer of a screen already let go: its tab has its own.
                else if (native.freed) e.preventDefault();
                return;
            }
            if (!isScreen(u)) return;
            e.preventDefault();
            if (native.freed || (native === home && keyOf(u.href) !== homeKey)) openUrl(u.href, true); else navigateNative(u.href);
        });
    }

    // ── "Go to…" (Ctrl+K) ──
    var openScreen = function (href) {
        var u = toURL(href);
        if (u && isScreen(u)) openUrl(u.href); else goWindow(href);
    };
    var paletteBox = makePalette(screens, function (href) { openScreen(href); }, function () { focusTab(active); });
    var openPalette = function () { closeMenus(); paletteBox(); };

    // ── Toolbar, status bar buttons and keys ──
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-pi]');
        if (!b) return;
        var act = b.getAttribute('data-pi');
        if (act === 'back') step(-1);
        else if (act === 'forward') step(1);
        else if (act === 'reload') reloadTab();
        else if (act === 'print') printTab();
        else if (act === 'palette') openPalette();
    });
    var focusMenus = function () { var b = bar.querySelector('.pi-mb-top'); if (b) b.focus(); };
    document.addEventListener('keydown', function (e) {
        if (e.defaultPrevented || !wide.matches) return;
        var k = (e.key || '').toLowerCase();
        var ctl = e.ctrlKey || e.metaKey;
        if (ctl && !e.shiftKey && !e.altKey && k === 'k') { e.preventDefault(); openPalette(); return; }
        // F10 reaches the menus, as in a Windows program.
        if (e.key === 'F10' && !e.shiftKey && !ctl) { e.preventDefault(); focusMenus(); return; }
        // Alt+1 … Alt+9: the tab in that place.
        if (e.altKey && !ctl && /^[1-9]$/.test(e.key)) {
            var t = all()[Number(e.key) - 1];
            if (t) { e.preventDefault(); activate(t); }
            return;
        }
        // Alt+← and Alt+→: back and forward in the tab on screen.
        if (e.altKey && !ctl && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
            e.preventDefault();
            step(e.key === 'ArrowLeft' ? -1 : 1);
            return;
        }
        // F5 and Ctrl+P act on the screen in the tab, not on the whole window.
        if (active.frame && ((e.key === 'F5' && !ctl && !e.shiftKey) || (ctl && !e.shiftKey && !e.altKey && k === 'r'))) { e.preventDefault(); reloadTab(); }
        else if (active.frame && ctl && !e.shiftKey && !e.altKey && k === 'p') { e.preventDefault(); printTab(); }
    });

    // Connection, as the screens' own requests find it.
    var conn = document.getElementById('pi-conn');
    var connState = 'online';
    var setConn = function (state) {
        if (state === connState || !conn) return;
        connState = state;
        conn.setAttribute('data-state', state);
        var label = conn.querySelector('[data-conn-text]');
        if (label) label.textContent = tx('conn_' + state, state);
    };
    ER.watchFetch(setConn); // E-Ride
    window.addEventListener('offline', function () { setConn('offline'); });
    window.addEventListener('online', function () { setConn('online'); });
    if (window.jQuery) {
        window.jQuery(document).ajaxSuccess(function () { setConn('online'); });
        window.jQuery(document).ajaxError(function (ev, xhr) { if (xhr && xhr.status === 0 && xhr.statusText !== 'abort') setConn(navigator.onLine === false ? 'offline' : 'unreachable'); });
    }
    var clock = document.getElementById('pi-clock');
    if (clock) {
        var fmt;
        try { fmt = new Intl.DateTimeFormat(html.lang || undefined, { hour: '2-digit', minute: '2-digit' }); } catch (e) { fmt = null; }
        var tick = function () { clock.textContent = fmt ? fmt.format(new Date()) : new Date().toLocaleTimeString(); };
        tick();
        setInterval(tick, 15000);
    }

    // The screens in the frames reach the window through this.
    window.PI_DESK = {
        palette: openPalette,
        menus: focusMenus,
        tab: function (n) { activate(all()[n - 1]); },
        step: step,
        conn: setConn,
        busy: function (win, on) { var t = tabOf(win); if (t && !!t.loading !== on) setBusy(t, on); },
        moved: function (win) {
            var t = tabOf(win);
            if (!t) return;
            try { record(t, win.location.href); } catch (e) { return; }
            if (t === active) show(t);
            drawTabs();
            save();
        },
        link: function (win, href) {
            // Only Home sends its links to tabs of their own, and not those to Home itself.
            if (tabOf(win) !== home || keyOf(href) === homeKey) return false;
            openUrl(href);
            return true;
        },
        leave: function (href) { save(); goWindow(href); },
        // a screen counting something new while it is not on screen (live sales lists)
        badge: function (win, n) { var t = tabOf(win) || ((win === window && !native.freed) || (native.frame && native.frame.contentWindow === win) ? native : null); if (t && t.badge !== n) { t.badge = n; drawTabs(); } }, // E-Ride: this page's own screen too
        isActive: function (win) { var t = tabOf(win); return !!t && t === active; }
    };

    drawTabs();
    show(active);
    save();
    // After a refresh, the tab that was on screen comes back on screen; a
    // screen asked for by its address (a bookmark, a link) stays on screen.
    if (saved.at === location.href && saved.active && saved.active !== active.id) {
        activate(all().filter(function (x) { return x.id === saved.active; })[0]);
    }
})();
