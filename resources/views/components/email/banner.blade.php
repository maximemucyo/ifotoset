@props(['title', 'meta' => null, 'note' => null])
@php
    $font = "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    $display = "'Bricolage Grotesque', 'Helvetica Neue', Helvetica, Arial, sans-serif";
@endphp
{{-- A gallery "cover" in type only: gallery pages never show the cover before access, so emails don't either --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 28px;">
    <tr>
        <td class="em-banner" bgcolor="#1e4a58" style="padding: 32px 28px; background-color: #1e4a58; border-radius: 12px; font-family: {{ $font }};">
            @if($note)
                <p style="margin: 0 0 14px; font-size: 14px; line-height: 1.4; color: #c9d6da;">{{ $note }}</p>
            @endif
            <p class="em-banner-title" style="margin: 0; font-family: {{ $display }}; font-size: 34px; font-weight: 700; letter-spacing: -0.025em; line-height: 1.05; color: #ffffff;">{{ $title }}</p>
            @if($meta)
                <p style="margin: 10px 0 0; font-size: 15px; line-height: 1.5; color: #e3eaec;">{{ $meta }}</p>
            @endif
        </td>
    </tr>
</table>
