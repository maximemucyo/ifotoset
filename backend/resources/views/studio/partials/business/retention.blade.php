{{-- Retention countdown for a trashed item. Vars: $deletedAt (Carbon), $retentionDays (int), $variant ('bar'|'badge') --}}
@php
    $variant = $variant ?? 'bar';
    $totalHours = max(1, $retentionDays * 24);
    $expiresAt = $deletedAt->copy()->addDays($retentionDays);
    $hoursLeft = max(0, (int) now()->diffInHours($expiresAt, false));
    $daysLeft = (int) ceil($hoursLeft / 24);
    $pct = max(2, min(100, round($hoursLeft / $totalHours * 100)));
    if ($hoursLeft < 24) {
        $tone = 'destructive';
        $label = $hoursLeft <= 1 ? 'Deletes within the hour' : "Deletes in {$hoursLeft}h";
    } elseif ($daysLeft === 1 || $hoursLeft < 48) {
        $tone = 'warning';
        $label = 'Deletes tomorrow';
    } else {
        $tone = 'muted';
        $label = "{$daysLeft} days left";
    }
    $text = ['destructive' => 'text-destructive', 'warning' => 'text-warning', 'muted' => 'text-muted-foreground'][$tone];
    $bar = ['destructive' => 'bg-destructive', 'warning' => 'bg-warning', 'muted' => 'bg-foreground/40'][$tone];
@endphp
@if($variant === 'badge')
    <span class="inline-flex items-center gap-1 rounded-md bg-black/60 px-1.5 py-0.5 text-[11px] font-medium text-white backdrop-blur-sm" title="Permanently deleted {{ $expiresAt->format('M j, g:i A') }}">
        <x-icon name="timer" class="size-3" /> {{ $label }}
    </span>
@else
    <div class="w-full" title="Permanently deleted {{ $expiresAt->format('M j, Y g:i A') }}">
        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="inline-flex items-center gap-1 font-medium {{ $text }}"><x-icon name="timer" class="size-3.5" /> {{ $label }}</span>
        </div>
        <div class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-muted">
            <span class="block h-full rounded-full {{ $bar }}" style="width: {{ $pct }}%"></span>
        </div>
    </div>
@endif
