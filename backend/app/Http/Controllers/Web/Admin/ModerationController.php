<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Admin\ModerateGallery;
use App\Http\Controllers\Controller;
use App\Models\Gallery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModerationController extends Controller
{
    /**
     * Display the content moderation queue.
     */
    public function index(): View
    {
        // For demonstration & active moderation, show private/password galleries as pending review, and public galleries
        $pendingGalleries = Gallery::where('visibility', '!=', 'public')
            ->with(['user', 'stats', 'coverPhoto'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.moderation', [
            'galleries' => $pendingGalleries,
        ]);
    }

    /**
     * Approve or reject a gallery in the moderation queue.
     */
    public function moderate(Request $request, string $uuid, ModerateGallery $action): RedirectResponse
    {
        $admin = $request->user();
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason'   => ['nullable', 'string', 'max:500'],
        ]);

        $action->execute($admin, $gallery, $validated['decision'], $validated['reason'] ?? null);

        $actionName = ($validated['decision'] === 'approve') ? 'approved and published' : 'rejected and set to private';

        return back()->with('success', "Gallery '{$gallery->title}' has been {$actionName}.");
    }
}
