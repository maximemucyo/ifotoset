{{-- Eyebrow + heading for a portfolio section. Vars: $section (from PortfolioSettings::section()), $center (bool), $as ('h1'|'h2') --}}
@php($tag = $as ?? 'h2')
<div class="{{ ($center ?? false) ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl' }}">
    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">{{ $section['eyebrow'] }}</p>
    <{{ $tag }} class="pf-heading mt-3 text-4xl leading-[1.05] text-foreground sm:text-5xl">{{ $section['heading'] }}</{{ $tag }}>
</div>
