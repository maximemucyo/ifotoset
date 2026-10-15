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
     * Update the public portfolio address (username) and whether the profile is publicly visible.
     */
    public function updatePublicProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/',
                \Illuminate\Validation\Rule::unique('users', 'username')->ignore($user->id),
                \Illuminate\Validation\Rule::notIn(config('reserved_usernames', [])),
            ],
        ], [
            'username.regex' => 'Use 3–50 lowercase letters, numbers or hyphens (no hyphen at the start or end).',
            'username.not_in' => 'That address is reserved. Please choose another.',
        ]);

        $usernameChanged = $validated['username'] !== $user->username;

        $user->update([
            'username' => $validated['username'],
            'profile_public' => $request->boolean('profile_public'),
        ]);

        return redirect()->to(route('studio.settings.index') . '#public')->with(
            'success',
            $usernameChanged ? 'Portfolio address updated. Share your new link with clients.' : 'Public profile settings saved.'
        );
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

        $submitted = [];
        foreach (\App\Enums\NotificationPreference::cases() as $preference) {
            $submitted[$preference->value] = $request->boolean($preference->value);
        }

        // Merge so keys this form doesn't show are never erased
        $user->update([
            'notification_preferences' => array_merge($user->notification_preferences ?? [], $submitted),
        ]);

        return back()->with('success', 'Notification preferences saved successfully.');
    }
}
