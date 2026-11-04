{{-- The studio's logo on a client gallery; see public.partials.gallery-brand for how its tone is used --}}
@props(['photographer'])
<img src="{{ $photographer->logo_url }}" alt="{{ $photographer->name }}" decoding="async"
     @if($tone = $photographer->portfolio()->logoTone()) data-tone="{{ $tone }}" @endif
     {{ $attributes->class('gallery-logo w-auto object-contain') }}>
