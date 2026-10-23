{{-- Brand colour and heading font for the portfolio and booking pages. Vars: $photographer --}}
@once
    @php
        $themeSettings = $photographer->portfolio();
        $themeFont = $themeSettings->font();
        $themeBrand = $themeSettings->brandColor();
        $themeBrandFg = $themeSettings->brandForeground();
    @endphp
    @push('styles')
        @if(in_array($themeSettings->fontKey(), ['elegant', 'bold'], true))
            @vite('resources/css/portfolio.css')
        @endif
        {{-- Values come from fixed lists or a validated hex colour, so they are safe to print raw --}}
        <style>
            :root, :root.dark {
                --pf-heading: {!! $themeFont['family'] !!};
                --pf-heading-weight: {{ $themeFont['weight'] }};
                --pf-heading-tracking: {{ $themeFont['tracking'] }};
                @if($themeBrand)
                    --primary: {{ $themeBrand }};
                    --primary-foreground: {{ $themeBrandFg }};
                    --brand: {{ $themeBrand }};
                    --brand-strong: {{ $themeBrand }};
                    --brand-foreground: {{ $themeBrandFg }};
                    --brand-soft: color-mix(in oklab, {{ $themeBrand }} 14%, var(--background));
                    --ring: {{ $themeBrand }};
                @endif
            }
        </style>
    @endpush
@endonce
