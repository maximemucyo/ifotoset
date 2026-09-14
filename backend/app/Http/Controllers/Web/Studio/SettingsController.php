<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Services\StorageStatisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Show settings view with profile, security, and plan info.
     */
    public function index(Request $request, StorageStatisticsService $storageService): View
    {
        $user = $request->user();
        $storage = $storageService->getStorageStats($user);

        return view('studio.settings.index', [
            'user'    => $user,
            'plan'    => $user->plan,
            'storage' => $storage,
        ]);
    }

    /**
     * Update photographer profile details.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'bio'      => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'website'  => ['nullable', 'url', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:50'],
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Change password using standard Laravel security primitives.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $request->session()->regenerate();

        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * Update notification preferences.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();

        $prefs = [
            'booking_created'  => $request->boolean('booking_created'),
            'photo_downloaded' => $request->boolean('photo_downloaded'),
            'photo_favorited'  => $request->boolean('photo_favorited'),
        ];

        $user->update(['notification_preferences' => $prefs]);

        return back()->with('success', 'Notification preferences saved successfully.');
    }
}
