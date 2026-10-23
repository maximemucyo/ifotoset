{{-- Schematic preview of a portfolio template's hero and work grid. Vars: $template --}}
<span class="block aspect-[16/10] overflow-hidden rounded-lg border border-border bg-background p-2.5" aria-hidden="true">
    @switch($template)
        @case('minimal')
            <span class="flex h-full flex-col items-center gap-1">
                <span class="mt-1 h-1 w-8 rounded-full bg-muted-foreground/30"></span>
                <span class="h-2.5 w-24 rounded-full bg-foreground/70"></span>
                <span class="h-1 w-16 rounded-full bg-muted-foreground/30"></span>
                <span class="mt-1 h-1.5 w-8 rounded-full bg-brand"></span>
                <span class="mt-1.5 w-full flex-1 rounded bg-muted-foreground/20"></span>
            </span>
            @break
        @case('bold')
            <span class="relative flex h-full flex-col justify-end overflow-hidden rounded bg-gradient-to-br from-stone-500 to-stone-800 p-2">
                <span class="h-1 w-8 rounded-full bg-white/50"></span>
                <span class="mt-1 h-3.5 w-28 rounded-full bg-white"></span>
                <span class="mt-1 h-1 w-20 rounded-full bg-white/50"></span>
                <span class="mt-1.5 h-2 w-10 rounded-full bg-brand"></span>
            </span>
            @break
        @case('grid')
            <span class="grid h-full grid-cols-5 gap-2">
                <span class="col-span-2 flex flex-col justify-center gap-1">
                    <span class="h-2.5 w-full rounded-full bg-foreground/70"></span>
                    <span class="h-1 w-3/4 rounded-full bg-muted-foreground/30"></span>
                    <span class="mt-1 h-1.5 w-8 rounded-full bg-brand"></span>
                </span>
                <span class="col-span-3 grid grid-cols-2 gap-0.5">
                    <span class="bg-muted-foreground/25"></span><span class="bg-muted-foreground/40"></span>
                    <span class="bg-muted-foreground/40"></span><span class="bg-muted-foreground/25"></span>
                </span>
            </span>
            @break
        @default
            <span class="grid h-full grid-cols-5 gap-2">
                <span class="col-span-2 flex flex-col justify-center gap-1">
                    <span class="size-3 rounded-full bg-muted-foreground/30"></span>
                    <span class="h-2.5 w-full rounded-full bg-foreground/70"></span>
                    <span class="h-1 w-3/4 rounded-full bg-muted-foreground/30"></span>
                    <span class="mt-1 h-1.5 w-8 rounded-full bg-brand"></span>
                </span>
                <span class="col-span-3 grid grid-cols-3 gap-1">
                    <span class="col-span-2 rounded bg-muted-foreground/30"></span>
                    <span class="flex flex-col gap-1 pt-2"><span class="flex-1 rounded bg-muted-foreground/20"></span><span class="flex-1 rounded bg-muted-foreground/40"></span></span>
                </span>
            </span>
    @endswitch
</span>
