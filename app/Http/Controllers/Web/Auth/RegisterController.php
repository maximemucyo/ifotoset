<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Rules\StudioPhone;
use App\Support\GoogleAnalytics;
use App\Support\Phone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;
use App\Support\StudioUsername;

class RegisterController extends Controller
{
    /**
     * Display the registration form.
     */
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    /**
     * Check the email from the first sign-up step, so a taken or temporary address
     * is caught before the person fills in the rest of the form.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $this->rejectStudiolessMember($request);

        $request->validate(['email' => self::emailRules()]);

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }

    /**
     * Handle user signup request.
     */
    public function register(Request $request): RedirectResponse
    {
        if ($request->has('username')) {
            $request->merge([
                'username' => $request->username ? strtolower(trim($request->username)) : null,
            ]);
        }

        $this->rejectStudiolessMember($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => StudioUsername::rules(),
            'email' => self::emailRules(),
            'phone_country' => ['nullable', 'string', 'size:2'],
            'phone' => ['required', 'string', 'max:30', new StudioPhone()],
            // No confirmation field: the form has a show-password toggle
            'password' => ['required', 'string', 'min:8'],
        ], StudioUsername::messages());

        $freePlan = Plan::firstOrCreate([
            'slug' => 'free',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024,
            'video_limit' => 0,
            'gallery_limit' => 3,
            'team_limit' => 0,
        ]);

        $user = User::forceCreate([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $freePlan->id,
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'phone' => Phone::normalize($validated['phone'], $validated['phone_country'] ?? null),
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        Auth::login($user);

        GoogleAnalytics::track($request, $user, 'sign_up', ['method' => 'email']);

        return redirect()->intended(route('studio.dashboard'))->with('success', 'Welcome to ifotoset! Your account has been created.');
    }

    /**
     * Rules for a new studio owner's email.
     */
    private static function emailRules(): array
    {
        return ['required', 'string', 'email', 'max:255', 'unique:users', new NotDisposableEmail()];
    }

    /**
     * Team members who joined by invite already have a login, just no studio yet.
     */
    private function rejectStudiolessMember(Request $request): void
    {
        $email = strtolower(trim((string) $request->input('email')));
        if ($email !== '' && User::where('email', $email)->whereNull('username')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'You already have an account. Log in, then choose "Create my own studio" from the studio menu.',
            ]);
        }
    }
}
