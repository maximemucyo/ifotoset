@if($impersonating ?? false)
<div class="relative z-[60] flex flex-col items-center justify-between gap-2 bg-foreground px-4 py-2 text-background sm:flex-row">
    <p class="flex items-center gap-2 text-[13px]">
        <span class="inline-flex items-center gap-1 rounded-md bg-brand px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white">
            <x-icon name="eye" class="size-3" /> Impersonating
        </span>
        <span class="opacity-80">Viewing as</span>
        <strong class="font-semibold">{{ auth()->user()->name }}</strong>
        <span class="hidden opacity-60 md:inline">{{ auth()->user()->email }}</span>
        @if(!empty($impersonatorAdmin))
            <span class="hidden opacity-60 lg:inline">· by {{ $impersonatorAdmin->name }}</span>
        @endif
    </p>
    <form method="POST" action="{{ route('impersonation.leave') }}" class="shrink-0">
        @csrf
        <button type="submit" class="btn btn-xs bg-background text-foreground hover:bg-background/90">
            Exit impersonation <x-icon name="log-out" />
        </button>
    </form>
</div>
@endif
