/*
 * The accounting screens (paxhitestbed's module as ported to posinnova) are written for
 * Bootstrap 3, whose data attributes are data-toggle / data-dismiss / data-target. E-Ride runs
 * Bootstrap 5 (data-bs-*): this copies the attributes over, on the page and on anything loaded
 * into it later (the review dialog). It also supplies what posinnova's host app gave the
 * screens: the CSRF header on jQuery requests, the flash message (#status_span) as a toast,
 * and the number helpers (__number_f, __currency_trans_from_en) in FCFA.
 */
(function () {
    var ATTRS = ['toggle', 'dismiss', 'target', 'parent', 'backdrop', 'keyboard', 'placement', 'content', 'container', 'trigger'];

    function upgrade(root) {
        if (!root || !root.querySelectorAll) { return; }
        var nodes = [root].concat(Array.prototype.slice.call(root.querySelectorAll('[data-toggle],[data-dismiss],[data-target],[data-parent]')));
        nodes.forEach(function (el) {
            if (!el.getAttribute) { return; }
            ATTRS.forEach(function (a) {
                var v = el.getAttribute('data-' + a);
                if (v !== null && el.getAttribute('data-bs-' + a) === null) {
                    el.setAttribute('data-bs-' + a, v);
                }
            });
        });
        // An initially open collapse (Bootstrap 3: .collapse.in) is .show in Bootstrap 5.
        Array.prototype.forEach.call(root.querySelectorAll('.collapse.in'), function (el) { el.classList.add('show'); });
    }

    upgrade(document.documentElement);
    document.addEventListener('DOMContentLoaded', function () { upgrade(document.documentElement); });
    new MutationObserver(function (list) {
        list.forEach(function (m) {
            Array.prototype.forEach.call(m.addedNodes, function (n) { if (n.nodeType === 1) { upgrade(n); } });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });

    window.__number_f = function (n) {
        var v = Math.round(Number(n) || 0);
        return v.toLocaleString('fr-FR').replace(/ | /g, ' ');
    };
    window.__currency_trans_from_en = function (n, withSymbol) {
        return window.__number_f(n) + (withSymbol === false ? '' : ' FCFA');
    };
    window.__read_number = function (el) {
        var v = (el && el.val ? el.val() : el) || '0';
        return Number(String(v).replace(/[\s  ]/g, '').replace(',', '.')) || 0;
    };

    if (window.jQuery) {
        var token = document.querySelector('meta[name="csrf-token"]');
        jQuery.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token ? token.getAttribute('content') : '' } });
        if (window.toastr) {
            toastr.options = { positionClass: 'toast-top-right', timeOut: 5000, closeButton: true };
        }
        jQuery(function ($) {
            var s = $('#status_span');
            if (s.length && window.toastr) {
                var ok = String(s.data('status')) === '1';
                (ok ? toastr.success : toastr.error)(s.data('msg'));
            }
            if ($.fn.select2) {
                $('select.select2').each(function () {
                    if (!$(this).hasClass('select2-hidden-accessible')) { $(this).select2({ width: '100%' }); }
                });
            }
        });
    }
})();
