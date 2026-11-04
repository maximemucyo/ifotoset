{{-- The studio's colour and title font on a client gallery that uses its brand. Vars: $gallery, $photographer --}}
@if($gallery->brand())
    @include('public.partials.portfolio-theme')
    @once
        @push('styles')
            <style>
                /* Gallery titles are set in the serif; the brand's title font takes its place */
                body .font-serif {
                    font-family: var(--pf-heading);
                    font-weight: var(--pf-heading-weight);
                    letter-spacing: var(--pf-heading-tracking);
                }
                /* A cut-out logo keeps to the background: white over a cover or in dark mode, black on a light page */
                :root:not(.dark) .gallery-logo[data-tone="light"] { filter: brightness(0); }
                :root.dark .gallery-logo[data-tone="dark"],
                #gallery-header[data-state="overlay"] .gallery-logo[data-tone] { filter: brightness(0) invert(1); }
            </style>
        @endpush
    @endonce
@endif
