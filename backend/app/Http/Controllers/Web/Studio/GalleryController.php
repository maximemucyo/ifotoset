<?php

namespace App\Http\Controllers\Web\Studio;

use App\Enums\Visibility;
use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;

class GalleryController extends Controller
{
    /**
     * List all photographer's galleries.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Gallery::where('user_id', $user->id)
            ->with(['coverPhoto', 'stats'])
            ->orderBy('created_at', 'desc');

        if ($request->has('visibility') && $request->visibility !== 'all') {
            $query->where('visibility', $request->visibility);
        }

        $galleries = $query->paginate(12);

        return view('studio.galleries.index', [
            'galleries' => $galleries,
        ]);
    }

    /**
     * Show form to create new gallery.
     */
    public function create(): View
    {
        return view('studio.galleries.create');
    }

    /**
     * Store new gallery.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['required', 'string', 'in:public,private,password'],
            'password' => ['nullable', 'string', 'required_if:visibility,password'],
            'allow_photo_downloads' => ['nullable', 'boolean'],
            'allow_gallery_downloads' => ['nullable', 'boolean'],
        ]);

        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $count = Gallery::where('user_id', $user->id)->where('slug', $slug)->count();
        if ($count > 0) {
            $slug = "{$baseSlug}-" . time();
        }

        $gallery = Gallery::create([
            'uuid' => Uuid::uuid7()->toString(),
            'user_id' => $user->id,
            'title' => $validated['title'],
            'slug' => $slug,
            'client_name' => $validated['client_name'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'visibility' => $validated['visibility'],
            'password_hash' => !empty($validated['password']) ? Hash::make($validated['password']) : null,
            'allow_photo_downloads' => $request->boolean('allow_photo_downloads', true),
            'allow_gallery_downloads' => $request->boolean('allow_gallery_downloads', true),
            'version' => 1,
        ]);

        return redirect()->route('studio.galleries.show', $gallery->uuid)
            ->with('success', 'Gallery created successfully! You can now upload photos.');
    }

    /**
     * Manage photos inside the gallery.
     */
    public function show(Request $request, string $uuid): View
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $gallery);

        $photos = $gallery->photos()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(50);

        return view('studio.galleries.show', [
            'gallery' => $gallery,
            'photos' => $photos,
        ]);
    }

    /**
     * Edit gallery details and privacy.
     */
    public function edit(string $uuid): View
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        return view('studio.galleries.edit', [
            'gallery' => $gallery,
        ]);
    }

    /**
     * Update gallery metadata.
     */
    public function update(Request $request, string $uuid): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'visibility' => ['required', 'string', 'in:public,private,password'],
            'password' => ['nullable', 'string'],
            'allow_photo_downloads' => ['nullable', 'boolean'],
            'allow_gallery_downloads' => ['nullable', 'boolean'],
        ]);

        $updateData = [
            'title' => $validated['title'],
            'client_name' => $validated['client_name'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'visibility' => $validated['visibility'],
            'allow_photo_downloads' => $request->boolean('allow_photo_downloads'),
            'allow_gallery_downloads' => $request->boolean('allow_gallery_downloads'),
        ];

        if (!empty($validated['password'])) {
            $updateData['password_hash'] = Hash::make($validated['password']);
        } elseif ($validated['visibility'] !== 'password') {
            $updateData['password_hash'] = null;
        }

        $gallery->update($updateData);

        return redirect()->route('studio.galleries.show', $gallery->uuid)
            ->with('success', 'Gallery settings updated successfully.');
    }

    /**
     * Set a photo as the gallery cover.
     */
    public function setCover(Request $request, string $uuid): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $gallery);

        $validated = $request->validate([
            'photo_id' => ['required', 'integer'],
        ]);

        $photo = Photo::where('id', $validated['photo_id'])
            ->where('gallery_id', $gallery->id)
            ->firstOrFail();

        $gallery->update(['cover_photo_id' => $photo->id]);

        return back()->with('success', 'Cover photo updated.');
    }

    /**
     * Soft-delete the gallery.
     */
    public function destroy(string $uuid): RedirectResponse
    {
        $gallery = Gallery::where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $gallery);

        $gallery->delete();

        return redirect()->route('studio.galleries.index')
            ->with('success', 'Gallery moved to trash.');
    }
}
