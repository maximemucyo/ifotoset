@if(isset($studioAccount) && $studioAccount->isTeamContext())
<div class="relative z-[55] flex flex-col items-center justify-between gap-2 bg-foreground px-4 py-2 text-background sm:flex-row">
    <p class="flex items-center gap-2 text-[13px]">
        <span class="inline-flex items-center gap-1 rounded-md bg-brand-strong px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-brand-foreground">
            <x-icon name="building" class="size-3" /> Team
        </span>
        <span class="opacity-80">Working in</span>
        <strong class="font-semibold">{{ $studioAccount->account()->name }}</strong>
        <span class="hidden opacity-60 md:inline">as {{ $studioAccount->role()->label() }}</span>
    </p>
    @if(auth()->user()->hasOwnStudio())
        <form method="POST" action="{{ route('studio.account.switch') }}" class="shrink-0">
            @csrf
            <input type="hidden" name="account" value="{{ auth()->user()->uuid }}">
            <button type="submit" class="btn btn-xs bg-background text-foreground hover:bg-background/90">
                Switch to my studio <x-icon name="arrow-right" />
            </button>
        </form>
    @endif
</div>
@endif
