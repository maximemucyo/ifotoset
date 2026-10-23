{{-- Hero call-to-action buttons. Vars: $center (bool), $inverse (bool, on a photo) --}}
@php($showWork = $galleries->isNotEmpty() && $portfolio->sectionVisible('work'))
@if($hasPackages || $showWork)
    <div class="flex flex-wrap gap-3 {{ ($center ?? false) ? 'justify-center' : '' }}">
        @if($hasPackages)
            <a href="{{ $bookUrl }}" class="btn btn-primary btn-lg">Book a session <x-icon name="arrow-right" /></a>
        @endif
        @if($showWork)
            <a href="#work" class="btn btn-lg {{ ($inverse ?? false) ? 'border border-white/40 bg-white/10 text-white backdrop-blur hover:bg-white/20' : 'btn-outline' }}">View work</a>
        @endif
    </div>
@else
    <div class="flex flex-wrap gap-3 {{ ($center ?? false) ? 'justify-center' : '' }}">
        <a href="{{ $photographer->public_url }}/contact" class="btn btn-primary btn-lg">Get in touch <x-icon name="arrow-right" /></a>
    </div>
@endif
