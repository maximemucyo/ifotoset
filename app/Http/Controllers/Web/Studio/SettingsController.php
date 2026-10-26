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
            'specialties'   => ['nullable', 'array', 'max:' . config('directory.max_specialties')],
            'specialties.*' => ['string', 'distinct', \Illuminate\Validation\Rule::in(array_keys(config('directory.specialties')))],
            'website'  => ['nullable', 'url', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:50'],
        ], [
            'specialties.max' => 'Choose up to ' . config('directory.max_specialties') . ' specialties.',
        ]);

        $validated['specialties'] = array_values($validated['specialties'] ?? []) ?: null;

        // The portfolio is already their website: linking it to itself would add nothing
        if (filled($validated['website'] ?? null) && $user->username
            && strcasecmp((string) parse_url($validated['website'], PHP_URL_HOST), (string) parse_url($user->public_url, PHP_URL_HOST)) === 0) {
            $validated['website'] = null;
        }

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

        event(new \Illuminate\Auth\Events\PasswordReset($user));

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

    /**
     * Download everything the account holds as one JSON file.
     */
    public function exportData(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'profile' => $user->only([
                'name', 'username', 'email', 'phone', 'location', 'city', 'specialties', 'website', 'bio',
                'timezone', 'profile_public', 'notification_preferences', 'portfolio_settings', 'created_at',
            ]),
            'plan' => $user->plan?->only(['name', 'slug']),
            'galleries' => $user->galleries()->withCount('photos')->orderBy('id')->get()->map(fn ($gallery) => [
                'title' => $gallery->title,
                'slug' => $gallery->slug,
                'visibility' => $gallery->visibility,
                'event_date' => $gallery->event_date,
                'photo_count' => $gallery->photos_count,
                'public_url' => $gallery->public_url,
                'photos' => $gallery->photos()->orderBy('id')->pluck('original_filename'),
                'created_at' => $gallery->created_at,
            ]),
            'clients' => $user->clients()->orderBy('id')->get()
                ->map(fn ($client) => $client->only(['name', 'email', 'phone', 'company_name', 'location', 'notes', 'created_at'])),
            'packages' => $user->packages()->orderBy('id')->get()
                ->map(fn ($package) => $package->only(['name', 'description', 'price', 'currency', 'duration_minutes', 'deliverables'])),
            'bookings' => $user->bookings()->with(['client:id,name,email', 'package:id,name'])->orderBy('id')->get()
                ->map(fn ($booking) => [
                    'title' => $booking->title,
                    'status' => $booking->status,
                    'starts_at' => $booking->starts_at,
                    'ends_at' => $booking->ends_at,
                    'client' => $booking->client?->only(['name', 'email']),
                    'package' => $booking->package?->name,
                    'price' => $booking->price,
                    'currency' => $booking->currency,
                    'notes' => $booking->notes,
                ]),
            'payments' => \App\Models\Payment::where('user_id', $user->id)->orderBy('id')->get()
                ->map(fn ($payment) => $payment->only(['amount', 'currency', 'status', 'purpose', 'billing_cycle', 'created_at'])),
            'note' => 'Photo files are not included. Download them per gallery with the ZIP export.',
        ];

        $filename = 'ifotoset-data-' . $user->username . '-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(
            fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Close the account. Content is erased after a grace period by
     * users:purge-deleted, so an accidental deletion can still be undone by support.
     */
    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'delete_password' => ['required', 'current_password'],
            'confirm' => ['required', 'in:DELETE'],
        ], [
            'delete_password.current_password' => 'The password is incorrect.',
            'confirm.in' => 'Type DELETE to confirm.',
        ]);

        $user = $request->user();

        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            \App\Models\Subscription::where('user_id', $user->id)
                ->where('status', \App\Enums\SubscriptionStatus::Active->value)
                ->update(['status' => \App\Enums\SubscriptionStatus::Canceled->value]);

            $user->delete();
        });

        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Your account has been deleted.');
    }
}
