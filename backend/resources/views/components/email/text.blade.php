@props(['muted' => false, 'size' => null, 'align' => 'left', 'spacing' => '16px'])
@php
    $font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    $fontSize = $size ?? ($muted ? '14px' : '15px');
@endphp
<p class="{{ $muted ? 'em-muted' : 'em-p' }}" style="margin: 0 0 {{ $spacing }}; font-family: {{ $font }}; font-size: {{ $fontSize }}; line-height: 1.65; color: {{ $muted ? '#6b6b72' : '#3f3f46' }}; text-align: {{ $align }};">{{ $slot }}</p>
