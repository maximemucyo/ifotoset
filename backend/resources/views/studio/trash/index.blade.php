@extends('layouts.app', ['title' => 'Trash - Studio'])

@section('content')
<div class="space-y-6" x-data="{ emptyModalOpen: false }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Trash &amp; Recovery</h1>
            <p class="text-xs text-muted-foreground mt-1">Recover soft-deleted client galleries or permanently purge them.</p>
        </div>

        @if($galleries->total() > 0)
            <button type="button"
                    @click="emptyModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-destructive/10 text-destructive border border-destructive/20 hover:bg-destructive/20 transition-colors">
                Empty All Trash ({{ $galleries->total() }})
            </button>
        @endif
    </div>

    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Gallery Title</th>
                        <th class="px-6 py-4">Photos</th>
                        <th class="px-6 py-4">Deleted Date</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($galleries as $gallery)
                        <tr class="hover:bg-muted/30 transition-colors">
                            <td class="px-6 py-4 font-semibold text-foreground">
                                {{ $gallery->title }}
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ $gallery->photos_count ?? 0 }} photos
                            </td>
                            <td class="px-6 py-4 text-xs text-muted-foreground">
                                {{ $gallery->deleted_at->format('M j, Y g:i A') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('studio.trash.restore') }}">
                                        @csrf
                                        <input type="hidden" name="uuid" value="{{ $gallery->uuid }}">
                                        <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-secondary text-foreground hover:bg-border transition-colors">
                                            Restore
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('studio.trash.purge') }}" onsubmit="return confirm('Permanently delete this gallery? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="uuid" value="{{ $gallery->uuid }}">
                                        <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-destructive/10 text-destructive hover:bg-destructive/20 transition-colors">
                                            Delete Permanently
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-muted-foreground">
                                Trash is currently empty.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="pt-4">
        {{ $galleries->links() }}
    </div>

    <!-- Empty Trash Confirmation Modal -->
    <div x-show="emptyModalOpen"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-foreground/40 backdrop-blur-sm" @click="emptyModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md rounded-2xl bg-card border border-border p-6 shadow-2xl text-left">
                <h3 class="text-lg font-bold text-foreground">Empty All Trash?</h3>
                <p class="text-xs text-muted-foreground mt-2 leading-relaxed">
                    This will permanently delete all soft-deleted galleries and their photos from cloud storage. This action is irreversible.
                </p>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" @click="emptyModalOpen = false" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('studio.trash.empty') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-destructive text-white hover:opacity-90 shadow-sm transition-opacity">
                            Yes, Empty All Trash
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
