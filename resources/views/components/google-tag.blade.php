@php
    $gaId = config('services.google_analytics.id');
    // Marketing pages only: never on client galleries, photographer sites, studio or admin
    $isMarketingPage = request()->routeIs('home', 'legal.*', 'login', 'register');
@endphp
@if(app()->environment('production') && $gaId && $isMarketingPage)
<!-- Google tag (gtag.js), loaded async and only in production -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', @js($gaId));
</script>
@endif
