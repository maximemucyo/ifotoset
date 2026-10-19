<?php

namespace App\Http\Controllers\Web\Studio;

use App\Actions\Studio\CreatePackage;
use App\Actions\Studio\DeactivatePackage;
use App\Actions\Studio\UpdatePackage;
use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageController extends Controller
{
    /**
     * List photography pricing packages.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $packages = Package::where('user_id', $user->id)
            ->withCount('bookings')
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('studio.packages.index', [
            'packages' => $packages,
        ]);
    }

    /**
     * Store a new pricing package.
     */
    public function store(Request $request, CreatePackage $action): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'price'             => ['required', 'numeric', 'min:0'],
            'currency'          => ['required', 'string', 'max:10'],
            'duration_minutes'  => ['required', 'integer', 'min:15'],
            'deliverables_text' => ['nullable', 'string'],
            'deposit_type'      => ['required', 'in:none,percentage,fixed'],
            'deposit_amount'    => ['nullable', 'numeric', 'min:0'],
            'is_active'         => ['nullable', 'boolean'],
            'sort_order'        => ['nullable', 'integer'],
        ]);

        $package = $action->execute($user, $validated);

        return back()->with('success', "Package '{$package->name}' created successfully.");
    }

    /**
     * Update an existing package.
     */
    public function update(Request $request, string $uuid, UpdatePackage $action): RedirectResponse
    {
        $user = $request->user();

        $package = Package::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'price'             => ['required', 'numeric', 'min:0'],
            'currency'          => ['required', 'string', 'max:10'],
            'duration_minutes'  => ['required', 'integer', 'min:15'],
            'deliverables_text' => ['nullable', 'string'],
            'deposit_type'      => ['required', 'in:none,percentage,fixed'],
            'deposit_amount'    => ['nullable', 'numeric', 'min:0'],
            'is_active'         => ['nullable', 'boolean'],
            'sort_order'        => ['nullable', 'integer'],
        ]);

        $action->execute($user, $package, $validated);

        return back()->with('success', "Package '{$package->name}' updated successfully.");
    }

    /**
     * Toggle active status.
     */
    public function toggle(Request $request, string $uuid): RedirectResponse
    {
        $user = $request->user();

        $package = Package::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $package->update(['is_active' => ! $package->is_active]);

        $status = $package->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Package '{$package->name}' {$status}.");
    }

    /**
     * Safely delete / deactivate package.
     */
    public function destroy(Request $request, string $uuid, DeactivatePackage $action): RedirectResponse
    {
        $user = $request->user();

        $package = Package::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $name = $package->name;
        $action->execute($user, $package);

        return back()->with('success', "Package '{$name}' has been archived.");
    }
}
