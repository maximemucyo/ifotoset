@props(['variant' => 'neutral'])
@php
    $font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    $styles = [
        'success' => ['#f0fdf4', '#bbf7d0', '#166534', 'em-callout-success'],
        'danger'  => ['#fef2f2', '#fecaca', '#991b1b', 'em-callout-danger'],
        'warning' => ['#fffbeb', '#fde68a', '#92400e', 'em-callout-warning'],
        'neutral' => ['#f7f7f5', '#e8e8e5', '#3f3f46', 'em-panel em-p'],
    ];
    [$bg, $border, $color, $class] = $styles[$variant] ?? $styles['neutral'];
@endphp
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 24px;">
    <tr>
        <td class="{{ $class }}" bgcolor="{{ $bg }}" style="padding: 12px 16px; background-color: {{ $bg }}; border: 1px solid {{ $border }}; border-radius: 8px; font-family: {{ $font }}; font-size: 14px; line-height: 1.55; color: {{ $color }};">
            {{ $slot }}
        </td>
    </tr>
</table>
