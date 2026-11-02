@props(['variant' => 'neutral'])
@php
    $font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    $styles = [
        'success' => ['#f0fdf4', '#bbf7d0', '#166534', 'em-callout-success'],
        'danger'  => ['#fef2f2', '#fecaca', '#991b1b', 'em-callout-danger'],
        'warning' => ['#fffbeb', '#fde68a', '#92400e', 'em-callout-warning'],
        'neutral' => ['#f2f4f0', '#e2e5df', '#2f3532', 'em-panel em-p'],
    ];
    [$bg, $border, $color, $class] = $styles[$variant] ?? $styles['neutral'];
@endphp
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 24px;">
    <tr>
        <td class="{{ $class }}" bgcolor="{{ $bg }}" style="padding: 14px 18px; background-color: {{ $bg }}; border: 1px solid {{ $border }}; border-radius: 12px; font-family: {{ $font }}; font-size: 15px; line-height: 1.55; color: {{ $color }};">
            {{ $slot }}
        </td>
    </tr>
</table>
