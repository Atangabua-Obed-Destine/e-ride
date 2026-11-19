<script>
// "Open in the app" links are Android intents: elsewhere (a laptop, an iPhone) they go to the "get the app" page.
(function () {
    if (/Android/i.test(navigator.userAgent)) return;
    document.querySelectorAll('a[data-fallback]').forEach(function (a) { a.href = a.dataset.fallback; });
})();
</script>
