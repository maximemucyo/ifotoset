{{-- One getting-started step on the Overview. Expects $step (title, description, url) and $done. --}}
<li>
    <a href="{{ $step['url'] }}" class="group/step -mx-2 flex items-start gap-3 rounded-md px-2 py-2 transition-colors hover:bg-muted/60">
        @if($done)
            <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full bg-success text-white"><x-icon name="check" class="size-2.5" /></span>
        @else
            <span class="mt-0.5 size-4 shrink-0 rounded-full border border-border-strong group-hover/step:border-foreground"></span>
        @endif
        <span class="min-w-0 leading-5">
            <span class="block text-[13px] font-medium {{ $done ? 'text-muted-foreground line-through decoration-border-strong' : 'text-foreground' }}">{{ $step['title'] }}</span>
            <span class="block text-[13px] text-muted-foreground">{{ $step['description'] }}</span>
        </span>
    </a>
</li>
