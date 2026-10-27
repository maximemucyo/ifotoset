<?php

namespace App\Http\Controllers\Web\Auth;

use App\Actions\Teams\AcceptTeamInvitation;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\TeamInvitationController;
use App\Models\AccountInvitation;
use App\Models\Plan;
use App\Models\User;
use App\Rules\StudioPhone;
use App\Support\GoogleAnalytics;
use App\Support\Phone;
use App\Support\StudioAccount;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Ramsey\Uuid\Uuid;

/**
 * "Continue with Google" sign-in and sign-up (OpenID Connect, authorization code flow).
 *
 * Existing accounts are matched by Google id, then by verified email. New visitors
 * pick their studio URL on a short completion step before the account is created,
 * since the username becomes their public subdomain.
 */
class GoogleAuthController extends Controller
{
    private const STATE_KEY = 'google_auth.state';
    private const PROFILE_KEY = 'google_auth.profile';

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * Send the visitor to Google's consent screen.
     */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('auth.google.callback'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]));
    }

    /**
     * Google redirects here after consent: sign in, link, or start sign-up.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $expectedState = $request->session()->pull(self::STATE_KEY);
        if (!$expectedState || !hash_equals($expectedState, (string) $request->query('state'))) {
            return $this->fail('Your Google sign-in session expired. Please try again.');
        }

        // Visitor pressed "Cancel" on the consent screen
        if ($request->filled('error') || !$request->filled('code')) {
            return redirect()->route('login');
        }

        $profile = $this->fetchProfile((string) $request->query('code'));
        if (!$profile) {
            return $this->fail('We could not sign you in with Google. Please try again.');
        }

        if (!$profile['email_verified']) {
            return $this->fail('Your Google account email is not verified.');
        }

        $user = User::withTrashed()->where('google_id', $profile['sub'])->first()
            ?? User::withTrashed()->where('email', $profile['email'])->first();

        // Started from a team invitation: an existing user signs in and confirms on the invite page
        $inviteToken = $request->session()->pull(TeamInvitationController::GOOGLE_SESSION_KEY);

        if (!$user) {
            if ($inviteToken) {
                return $this->joinTeam($request, $inviteToken, $profile);
            }

            $request->session()->put(self::PROFILE_KEY, $profile);

            return redirect()->route('auth.google.complete');
        }

        if ($user->trashed()) {
            return $this->fail('This account has been deleted. Please contact support.');
        }

        if (!$user->is_active) {
            return $this->fail('This account has been suspended. Please contact support.');
        }

        // Admins keep to password sign-in
        if ($user->isAdmin()) {
            return $this->fail('Please sign in to admin accounts with your email and password.');
        }

        // A different Google account already owns this email's link
        if ($user->google_id && $user->google_id !== $profile['sub']) {
            return $this->fail('This email is linked to a different Google account.');
        }

        $user->forceFill([
            'google_id' => $profile['sub'],
            // Google vouched for the address, so a pending verification is no longer needed
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        GoogleAnalytics::track($request, $user, 'login', ['method' => 'google']);

        return redirect()->intended(route('studio.dashboard'));
    }

    /**
     * A new visitor accepting a team invitation joins without a studio of their own, so no username step.
     */
    private function joinTeam(Request $request, string $token, array $profile): RedirectResponse
    {
        $acceptor = app(AcceptTeamInvitation::class);
        $invitation = AccountInvitation::findByToken($token);

        if ($problem = $acceptor->problem($invitation)) {
            return $this->fail($problem);
        }
        if (Str::lower($invitation->email) !== $profile['email']) {
            return $this->fail("This invitation was sent to {$invitation->email}. Continue with that Google account, or create a password instead.");
        }

        $user = TeamInvitationController::createInvitedUser($invitation, $profile['name'], Hash::make(Str::random(64)), $profile['sub']);
        $acceptor->execute($invitation, $user);

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        $request->session()->put(StudioAccount::SESSION_KEY, $invitation->account_id);

        GoogleAnalytics::track($request, $user, 'sign_up', ['method' => 'google_team_invite']);

        return redirect()->route('studio.dashboard')->with('success', "Welcome to {$invitation->account->name}! You're now part of the team.");
    }

    /**
     * Second sign-up step: choose the studio URL and add a phone number.
     */
    public function showCompleteForm(Request $request): View|RedirectResponse
    {
        $profile = $request->session()->get(self::PROFILE_KEY);
        if (!$profile) {
            return redirect()->route('register');
        }

        return view('auth.google-complete', [
            'profile' => $profile,
            'suggestedUsername' => $this->suggestUsername($profile),
        ]);
    }

    /**
     * Create the account from the Google profile held in the session.
     */
    public function complete(Request $request): RedirectResponse
    {
        $profile = $request->session()->get(self::PROFILE_KEY);
        if (!$profile) {
            return redirect()->route('register');
        }

        $request->merge(['username' => strtolower(trim((string) $request->username))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/',
                'unique:users,username',
                Rule::notIn(config('reserved_usernames', [])),
            ],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'phone' => ['required', 'string', 'max:30', new StudioPhone()],
        ]);

        // The email or Google account may have registered in another tab since the callback
        if (User::withTrashed()->where('email', $profile['email'])->orWhere('google_id', $profile['sub'])->exists()) {
            $request->session()->forget(self::PROFILE_KEY);

            return $this->fail('An account with this email already exists. Please sign in.');
        }

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
            'email' => $profile['email'],
            'phone' => Phone::normalize($validated['phone'], $validated['phone_country'] ?? null),
            'google_id' => $profile['sub'],
            'email_verified_at' => now(),
            // Unusable until they set one through "Forgot password"
            'password' => Hash::make(Str::random(64)),
        ]);

        $request->session()->forget(self::PROFILE_KEY);

        event(new Registered($user));

        Auth::login($user, true);
        $request->session()->regenerate();

        GoogleAnalytics::track($request, $user, 'sign_up', ['method' => 'google']);

        return redirect()->intended(route('studio.dashboard'))->with('success', 'Welcome to ifotoset! Your account has been created.');
    }

    /**
     * Swap the authorization code for tokens and read the signed-in Google profile.
     *
     * @return array{sub: string, email: string, email_verified: bool, name: string}|null
     */
    private function fetchProfile(string $code): ?array
    {
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => route('auth.google.callback'),
            'grant_type' => 'authorization_code',
        ]);

        $accessToken = $tokenResponse->json('access_token');
        if ($tokenResponse->failed() || empty($accessToken)) {
            return null;
        }

        $userInfo = Http::withToken($accessToken)->get('https://openidconnect.googleapis.com/v1/userinfo');
        if ($userInfo->failed() || !$userInfo->json('sub') || !$userInfo->json('email')) {
            return null;
        }

        return [
            'sub' => (string) $userInfo->json('sub'),
            'email' => Str::lower((string) $userInfo->json('email')),
            'email_verified' => (bool) $userInfo->json('email_verified'),
            'name' => (string) ($userInfo->json('name') ?: Str::before($userInfo->json('email'), '@')),
        ];
    }

    /**
     * A free, valid username derived from the Google name or email, to prefill the form.
     */
    private function suggestUsername(array $profile): string
    {
        $base = Str::of(Str::ascii($profile['name']))->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(40, '')->toString();
        if (strlen($base) < 3) {
            $base = Str::of(Str::before($profile['email'], '@'))->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(40, '')->toString();
        }
        if (strlen($base) < 3) {
            $base = 'studio';
        }

        $reserved = config('reserved_usernames', []);
        $candidate = $base;
        for ($i = 2; in_array($candidate, $reserved, true) || User::withTrashed()->where('username', $candidate)->exists(); $i++) {
            $candidate = $base . $i;
        }

        return $candidate;
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}
