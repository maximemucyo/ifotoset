@if($impersonating ?? false)
<div class="sticky top-0 z-50 w-full bg-amber-500 text-zinc-950 px-4 py-2.5 shadow-md flex flex-col sm:flex-row items-center justify-between gap-3 border-b border-amber-600 font-sans">
    <div class="flex items-center gap-3 text-xs sm:text-sm font-semibold">
        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-zinc-950 text-amber-400 text-[11px] uppercase tracking-wider font-extrabold shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            Impersonation Mode
        </span>
        <div class="flex flex-wrap items-center gap-x-2">
            <span>
                Viewing ifotoset as <strong class="underline decoration-zinc-950/40">{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }})
            </span>
            @if(!empty($impersonatorAdmin))
                <span class="text-xs text-zinc-800 hidden md:inline">
                    &bull; Original Admin: <span class="font-bold">{{ $impersonatorAdmin->name }}</span>
                </span>
            @endif
        </div>
    </div>
    <form method="POST" action="{{ route('impersonation.leave') }}" class="shrink-0">
        @csrf
        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-zinc-950 hover:bg-zinc-900 text-white font-bold text-xs shadow transition-all cursor-pointer flex items-center gap-1.5">
            <span>Exit Impersonation</span>
            <span>&rarr;</span>
        </button>
    </form>
</div>
@endif
