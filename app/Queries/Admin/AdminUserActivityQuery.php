<?php

namespace App\Queries\Admin;

use App\Models\AdminAuditLog;
use App\Models\Gallery;
use App\Models\User;
use App\Services\AdminAuditIpFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminUserActivityQuery
{
    /**
     * Retrieve and normalize user activity logs and administrative audit events into a chronological stream.
     */
    public function getForUser(User $user, int $limit = 50): Collection
    {
        // 1. Resolve user's galleries
        $galleries = Gallery::where('user_id', $user->id)->get(['id', 'uuid', 'title', 'slug']);
        $galleryIds = $galleries->pluck('id')->all();
        $galleryUuids = $galleries->pluck('uuid')->all();
        $galleryMap = $galleries->keyBy('id');

        // 2. Fetch standard activity logs (user account events + visitor gallery events)
        $userActivityQuery = DB::table('activity_logs')
            ->where(function ($q) use ($user, $galleryIds) {
                $q->where('user_id', $user->id);
                if (!empty($galleryIds)) {
                    $q->orWhereIn('gallery_id', $galleryIds);
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $normalizedUserLogs = $userActivityQuery->map(function ($row) use ($user, $galleryMap) {
            $galleryTitle = isset($galleryMap[$row->gallery_id]) ? $galleryMap[$row->gallery_id]->title : null;
            $gallerySlug = isset($galleryMap[$row->gallery_id]) ? $galleryMap[$row->gallery_id]->slug : null;

            $props = $row->properties ? json_decode($row->properties, true) : [];
            $description = $this->formatUserEventDescription($row->event, $galleryTitle, $user->name);

            return [
                'id' => 'act-' . $row->id,
                'source' => 'activity_log',
                'raw_id' => $row->id,
                'audit_log_id' => null,
                'can_reveal_ip' => false,
                'category' => $this->categorizeEvent($row->event),
                'event' => $row->event,
                'description' => $description,
                'gallery_title' => $galleryTitle,
                'gallery_slug' => $gallerySlug,
                'actor_name' => $row->user_id === $user->id ? $user->name : 'Visitor / Client',
                'actor_role' => $row->user_id === $user->id ? 'User' : 'Guest',
                'ip_masked' => AdminAuditIpFormatter::mask($row->ip_address),
                'created_at' => Carbon::parse($row->created_at)->toIso8601String(),
                'created_at_human' => Carbon::parse($row->created_at)->diffForHumans(),
                'metadata' => $props,
            ];
        });

        // 3. Fetch administrative audit logs targeting this user or their galleries
        $auditQuery = AdminAuditLog::with('admin')
            ->where(function ($q) use ($user, $galleryUuids) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('target_type', 'User')
                        ->where('target_id', (string) $user->id);
                });

                if (!empty($galleryUuids)) {
                    $q->orWhere(function ($sub) use ($galleryUuids) {
                        $sub->where('target_type', 'Gallery')
                            ->whereIn('target_id', $galleryUuids);
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $normalizedAuditLogs = $auditQuery->map(function ($audit) {
            $adminName = $audit->admin?->name ?? 'Administrator';
            $description = $this->formatAdminActionDescription($audit->action, $audit->metadata, $adminName);

            return [
                'id' => 'aud-' . $audit->id,
                'source' => 'admin_audit',
                'raw_id' => $audit->id,
                'audit_log_id' => $audit->id,
                'can_reveal_ip' => !empty($audit->ip_address),
                'category' => 'admin_action',
                'event' => $audit->action,
                'description' => $description,
                'gallery_title' => $audit->metadata['title'] ?? null,
                'gallery_slug' => null,
                'actor_name' => $adminName,
                'actor_role' => 'Administrator',
                'ip_masked' => AdminAuditIpFormatter::mask($audit->ip_address),
                'created_at' => $audit->created_at->toIso8601String(),
                'created_at_human' => $audit->created_at->diffForHumans(),
                'metadata' => $audit->metadata ?? [],
            ];
        });

        // 4. Merge and sort chronologically
        return $normalizedUserLogs->concat($normalizedAuditLogs)
            ->sortByDesc('created_at')
            ->values()
            ->take($limit);
    }

    private function categorizeEvent(string $event): string
    {
        if (str_contains($event, 'view')) return 'views';
        if (str_contains($event, 'download')) return 'downloads';
        if (str_contains($event, 'favorite')) return 'favorites';
        if (str_contains($event, 'login') || str_contains($event, 'auth')) return 'security';
        if (str_contains($event, 'photo') || str_contains($event, 'upload')) return 'media';
        return 'general';
    }

    private function formatUserEventDescription(string $event, ?string $galleryTitle, string $userName): string
    {
        $galleryLabel = $galleryTitle ? "in gallery '{$galleryTitle}'" : "";

        return match ($event) {
            'gallery_viewed' => "Visitor viewed gallery '{$galleryTitle}'",
            'photo_viewed' => "Visitor viewed a photo {$galleryLabel}",
            'photo_downloaded' => "Visitor downloaded a photo {$galleryLabel}",
            'gallery_zip_downloaded' => "Full archive ZIP download requested {$galleryLabel}",
            'photo_favorited' => "Visitor marked a photo as favorite {$galleryLabel}",
            'gallery_created' => "Photographer created new gallery '{$galleryTitle}'",
            'gallery_updated' => "Photographer updated gallery '{$galleryTitle}'",
            'photo_uploaded' => "Photo upload completed {$galleryLabel}",
            'user.login' => "{$userName} logged into studio dashboard",
            default => str_replace('_', ' ', ucfirst($event)) . ($galleryLabel ? " {$galleryLabel}" : ""),
        };
    }

    private function formatAdminActionDescription(string $action, ?array $metadata, string $adminName): string
    {
        $meta = $metadata ?? [];
        $title = $meta['title'] ?? '';

        return match ($action) {
            'user.status_changed' => "{$adminName} changed account status to " . ($meta['new_status'] ?? 'unknown'),
            'user.role_changed' => "{$adminName} changed user role to " . ($meta['new_role'] ?? 'unknown'),
            'user.plan_assigned' => "{$adminName} assigned subscription plan " . ($meta['plan_name'] ?? $meta['plan_slug'] ?? ''),
            'user.plan_revoked' => "{$adminName} revoked subscription plan",
            'user.email_verified' => "{$adminName} manually marked email as verified",
            'user.email_unverified' => "{$adminName} marked email as unverified",
            'user.verification_email_sent' => "{$adminName} triggered a verification email",
            'user.password_reset_requested' => "{$adminName} requested a password reset link",
            'user.deleted' => "{$adminName} soft-deleted this account",
            'user.impersonated', 'impersonation.started' => "{$adminName} started user view impersonation",
            'impersonation.ended' => "{$adminName} exited user impersonation",
            'admin_note.added' => "{$adminName} added an administrative note",
            'gallery.visibility_changed' => "{$adminName} changed gallery '{$title}' visibility to " . ($meta['new'] ?? ''),
            'gallery.taken_down' => "{$adminName} took down gallery '{$title}' (Reason: " . ($meta['reason'] ?? 'Content moderation') . ")",
            'gallery.restored' => "{$adminName} restored gallery '{$title}' to normal moderation status",
            'admin.ip_revealed' => "{$adminName} requested audit IP address reveal",
            default => "{$adminName} executed " . str_replace(['.', '_'], ' ', $action),
        };
    }
}
