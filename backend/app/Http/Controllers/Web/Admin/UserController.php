<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Admin\ChangeUserRole;
use App\Actions\Admin\ToggleUserStatus;
use App\Actions\Billing\AssignPlan;
use App\Actions\Billing\RevokePlan;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminNote;
use App\Models\Gallery;
use App\Models\Plan;
use App\Models\User;
use App\Queries\Admin\AdminUserActivityQuery;
use App\Queries\Admin\AdminUsersQuery;
use App\Services\StorageStatisticsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display directory of users.
     */
    public function index(Request $request, AdminUsersQuery $query): View
    {
        $search = $request->input('search');
        $role = $request->input('role');
        $verified = $request->input('verified');

        $users = $query->paginate($search, $role, $verified, 20);
        $plans = Plan::orderBy('monthly_price')->get();

        return view('admin.users', [
            'users'    => $users,
            'plans'    => $plans,
            'search'   => $search,
            'role'     => $role,
            'verified' => $verified,
        ]);
    }

    /**
     * Get complete profile, galleries, activities, storage stats, and notes for the user drawer (JSON).
     */
    public function details(Request $request, int $id, AdminUserActivityQuery $activityQuery, StorageStatisticsService $storageService): JsonResponse
    {
        $user = User::with(['plan', 'adminNotes.admin'])->findOrFail($id);

        $storageStats = $storageService->getStorageStats($user);

        // Fetch user's galleries with counts and cover
        $galleries = Gallery::where('user_id', $user->id)
            ->with(['coverPhoto', 'stats'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($gallery) {
                return [
                    'id' => $gallery->id,
                    'uuid' => $gallery->uuid,
                    'title' => $gallery->title,
                    'slug' => $gallery->slug,
                    'visibility' => $gallery->visibility,
                    'moderation_status' => $gallery->moderation_status ?? 'normal',
                    'is_taken_down' => $gallery->isTakenDown(),
                    'takedown_reason' => $gallery->takedown_reason,
                    'photo_count' => $gallery->photo_count,
                    'views_count' => $gallery->stats?->downloads_count ?? 0,
                    'total_bytes' => $gallery->stats?->total_bytes ?? 0,
                    'cover_url' => $gallery->getCoverUrl('md'),
                    'public_url' => $gallery->public_url,
                    'created_at' => $gallery->created_at->format('M j, Y'),
                ];
            });

        // Fetch unified activity timeline
        $activities = $activityQuery->getForUser($user, 40);

        // Fetch notes
        $notes = $user->adminNotes->sortByDesc('created_at')->values()->map(function ($note) {
            return [
                'id' => $note->id,
                'admin_name' => $note->admin?->name ?? 'Administrator',
                'content' => $note->content,
                'created_at' => $note->created_at->format('M j, Y H:i'),
                'created_at_human' => $note->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'phone' => $user->phone,
                'location' => $user->location,
                'website' => $user->website,
                'bio' => $user->bio,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'is_email_verified' => $user->email_verified_at !== null,
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->format('M j, Y H:i') : null,
                'plan_name' => $user->plan->name ?? 'Free',
                'plan_slug' => $user->plan->slug ?? 'free',
                'joined_at' => $user->created_at->format('M j, Y'),
                'public_url' => $user->public_url,
                'storage' => $storageStats,
            ],
            'galleries' => $galleries,
            'activities' => $activities,
            'notes' => $notes,
        ]);
    }

    /**
     * Manually mark user's email as verified.
     */
    public function verifyEmail(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        if ($targetUser->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Email is already verified.'], 200)
                : back()->with('info', "Email for '{$targetUser->name}' is already verified.");
        }

        $targetUser->forceFill(['email_verified_at' => now()])->save();

        AdminAuditLog::record(
            $admin,
            'user.email_verified',
            'User',
            (string) $targetUser->id,
            ['target_email' => $targetUser->email]
        );

        return $request->expectsJson()
            ? response()->json(['message' => "Email for '{$targetUser->name}' has been marked verified."], 200)
            : back()->with('success', "Email for '{$targetUser->name}' has been verified.");
    }

    /**
     * Manually un-verify a user's email (requires confirmation in UI).
     */
    public function unverifyEmail(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        $targetUser->forceFill(['email_verified_at' => null])->save();

        AdminAuditLog::record(
            $admin,
            'user.email_unverified',
            'User',
            (string) $targetUser->id,
            ['target_email' => $targetUser->email]
        );

        return $request->expectsJson()
            ? response()->json(['message' => "Email for '{$targetUser->name}' has been marked unverified."], 200)
            : back()->with('success', "Email for '{$targetUser->name}' has been marked unverified.");
    }

    /**
     * Send email verification notification to user.
     */
    public function resendVerification(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        if ($targetUser->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'User email is already verified.'], 400)
                : back()->with('info', "User '{$targetUser->name}' already has a verified email.");
        }

        $targetUser->sendEmailVerificationNotification();

        AdminAuditLog::record(
            $admin,
            'user.verification_email_sent',
            'User',
            (string) $targetUser->id,
            ['target_email' => $targetUser->email]
        );

        return $request->expectsJson()
            ? response()->json(['message' => "Verification email sent to {$targetUser->email}."], 200)
            : back()->with('success', "Verification email sent to {$targetUser->email}.");
    }

    /**
     * Trigger a secure password reset link to user via Laravel Password Broker.
     */
    public function passwordReset(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        Password::broker()->sendResetLink(['email' => $targetUser->email]);

        AdminAuditLog::record(
            $admin,
            'user.password_reset_requested',
            'User',
            (string) $targetUser->id,
            ['target_email' => $targetUser->email]
        );

        return $request->expectsJson()
            ? response()->json(['message' => "Password reset email dispatched to {$targetUser->email}."], 200)
            : back()->with('success', "Password reset link sent to {$targetUser->email}.");
    }

    /**
     * Impersonate user into Studio dashboard with hardened session semantics.
     */
    public function impersonate(Request $request, int $id): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        // Strict Target Authorization Guards
        if ($targetUser->id === $admin->id) {
            return back()->with('error', 'You cannot impersonate your own administrator account.');
        }

        if ($targetUser->isAdmin()) {
            return back()->with('error', 'You cannot impersonate another platform administrator.');
        }

        if (! $targetUser->is_active) {
            return back()->with('error', 'Cannot impersonate a suspended photographer account.');
        }

        if ($targetUser->trashed()) {
            return back()->with('error', 'Cannot impersonate a deleted account.');
        }

        // 1. Regenerate session to prevent fixation
        $request->session()->regenerate();

        // 2. Establish complete impersonation episode context
        $nonce = Str::random(40);
        $request->session()->put('impersonation', [
            'admin_id' => $admin->id,
            'target_user_id' => $targetUser->id,
            'started_at' => now()->toIso8601String(),
            'nonce' => $nonce,
        ]);

        // 3. Authenticate as target user
        Auth::login($targetUser);

        // 4. Immutable audit record
        AdminAuditLog::record(
            $admin,
            'impersonation.started',
            'User',
            (string) $targetUser->id,
            [
                'target_email' => $targetUser->email,
                'target_name' => $targetUser->name,
                'nonce' => $nonce,
            ]
        );

        return redirect()->route('studio.dashboard')
            ->with('success', "Now viewing ifotoset as {$targetUser->name} ({$targetUser->email}).");
    }

    /**
     * Soft-delete user account with last-admin and self-deletion safeguards.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        if ($targetUser->id === $admin->id) {
            return back()->with('error', 'You cannot delete your own administrator account.');
        }

        if ($targetUser->isAdmin()) {
            $activeAdminCount = User::where('role', 'admin')->whereNull('deleted_at')->count();
            if ($activeAdminCount <= 1) {
                return back()->with('error', 'Cannot delete the final remaining administrator on the platform.');
            }
        }

        $targetUser->delete();

        AdminAuditLog::record(
            $admin,
            'user.deleted',
            'User',
            (string) $targetUser->id,
            [
                'name' => $targetUser->name,
                'email' => $targetUser->email,
            ]
        );

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$targetUser->name}' has been soft-deleted. Files and billing records retained according to storage lifecycle.");
    }

    /**
     * Add an administrative note to a user's record.
     */
    public function addNote(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $note = AdminNote::create([
            'user_id' => $targetUser->id,
            'admin_user_id' => $admin->id,
            'content' => $validated['content'],
        ]);

        AdminAuditLog::record(
            $admin,
            'admin_note.added',
            'User',
            (string) $targetUser->id,
            ['note_id' => $note->id]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'note' => [
                    'id' => $note->id,
                    'admin_name' => $admin->name,
                    'content' => $note->content,
                    'created_at' => $note->created_at->format('M j, Y H:i'),
                    'created_at_human' => $note->created_at->diffForHumans(),
                ]
            ], 201);
        }

        return back()->with('success', 'Admin note added.');
    }

    /**
     * Reveal masked IP for an authorized administrator with rate limiting and audit logging.
     */
    public function revealIp(Request $request, int $auditLogId): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin->isAdmin(), 403);

        $auditLog = AdminAuditLog::findOrFail($auditLogId);

        AdminAuditLog::record(
            $admin,
            'admin.ip_revealed',
            'AdminAuditLog',
            (string) $auditLog->id,
            [
                'target_action' => $auditLog->action,
            ]
        );

        return response()->json([
            'ip' => $auditLog->ip_address ?: 'Unknown',
        ]);
    }

    /**
     * Toggle user active/suspended status with Last-Admin protection.
     */
    public function toggleStatus(Request $request, int $id, ToggleUserStatus $action): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        try {
            $action->execute($admin, $targetUser);
            $status = $targetUser->is_active ? 'activated' : 'suspended';
            return back()->with('success', "User '{$targetUser->name}' has been {$status}.");
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }
    }

    /**
     * Change user role with Last-Admin protection.
     */
    public function changeRole(Request $request, int $id, ChangeUserRole $action): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:user,admin'],
        ]);

        try {
            $action->execute($admin, $targetUser, $validated['role']);
            return back()->with('success', "User '{$targetUser->name}' role updated to {$validated['role']}.");
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }
    }

    /**
     * Admin manually assigns a plan to a user with audit trail.
     */
    public function assignPlan(Request $request, int $id, AssignPlan $action): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
            'billing_cycle' => ['required', 'string', 'in:monthly,annual'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        try {
            $action->execute(
                admin: $admin,
                targetUser: $targetUser,
                newPlan: $plan,
                billingCycle: $validated['billing_cycle'],
                reason: $validated['reason'] ?? 'Admin manual assignment'
            );

            return back()->with('success', "User '{$targetUser->name}' has been assigned the '{$plan->name}' plan.");
        } catch (Exception $e) {
            return back()->with('error', "Failed to assign plan: {$e->getMessage()}");
        }
    }

    /**
     * Admin revokes a user's paid plan back to Free tier with audit trail.
     */
    public function revokePlan(Request $request, int $id, RevokePlan $action): RedirectResponse
    {
        $admin = $request->user();
        $targetUser = User::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $action->execute(
                admin: $admin,
                targetUser: $targetUser,
                reason: $validated['reason'] ?? 'Admin manual revocation'
            );

            return back()->with('success', "Paid plan revoked for user '{$targetUser->name}'. Reverted to Free tier (2 GB).");
        } catch (Exception $e) {
            return back()->with('error', "Failed to revoke plan: {$e->getMessage()}");
        }
    }
}
