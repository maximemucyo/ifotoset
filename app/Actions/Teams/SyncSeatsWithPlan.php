<?php

namespace App\Actions\Teams;

use App\Mail\TeamSeatsSuspendedMail;
use App\Models\AccountMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SyncSeatsWithPlan
{
    /**
     * Fit the team to the owner's current plan after a plan change. Members over
     * the seat limit are suspended (newest first) and restored oldest first when
     * seats come back; pending invitations that no longer fit are revoked.
     * Nothing is deleted. Safe to call inside a transaction.
     */
    public function execute(User $owner): void
    {
        $owner->load('plan');
        $limit = $owner->seatLimit();

        $active = $owner->teamMembers()->active()->with('user:id,name')->orderBy('created_at')->orderBy('id')->get();
        $suspended = collect();
        $restored = 0;

        if ($active->count() > $limit) {
            $suspended = $active->slice($limit)->values();
            AccountMember::whereIn('id', $suspended->pluck('id'))->update(['suspended_at' => now()]);
        } elseif ($active->count() < $limit) {
            $restored = AccountMember::where('account_id', $owner->id)
                ->whereNotNull('suspended_at')
                ->orderBy('created_at')->orderBy('id')
                ->limit($limit - $active->count())
                ->update(['suspended_at' => null]);
        }

        $freeSeats = max(0, $limit - min($active->count(), $limit) - $restored);
        $overflow = $owner->teamInvitations()->pending()->orderBy('created_at')->orderBy('id')->get()->slice($freeSeats);
        if ($overflow->isNotEmpty()) {
            $owner->teamInvitations()->whereIn('id', $overflow->pluck('id'))->update(['revoked_at' => now()]);
        }

        if ($suspended->isNotEmpty() && $owner->email && $owner->is_active) {
            $names = $suspended->map(fn (AccountMember $member) => $member->user?->name)->filter()->values()->all();
            DB::afterCommit(fn () => Mail::to($owner->email)->queue(new TeamSeatsSuspendedMail($owner, $names)));
        }
    }
}
