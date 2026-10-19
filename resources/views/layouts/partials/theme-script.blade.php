{{-- Apply the stored / system theme before first paint to avoid a flash of the wrong theme. --}}
<script>
    (function () {
        try {
            var forced = @json($forceTheme ?? null);
            var stored = localStorage.getItem('theme');
            var mode = forced || stored || @json($defaultMode ?? 'system');
            var dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        } catch (e) {}
    })();
</script>
