<?php

namespace App\Support;

use App\Enums\AccountRole;
use App\Models\User;

/**
 * The studio a request works in (the account, an owner's user row) and the
 * person doing the work (the actor, the logged-in user).
 *
 * Resolved per request by ResolveStudioAccount. Outside the studio routes it
 * falls back to the logged-in user's own account, so code shared with the API
 * keeps its single-user behaviour.
 */
class StudioAccount
{
    public const SESSION_KEY = 'studio_account_id';
    public const REQUEST_ATTRIBUTE = 'studio_account';

    public function __construct(
        private readonly User $account,
        private readonly User $actor,
        private readonly AccountRole $role,
    ) {}

    public static function own(User $user): self
    {
        return new self($user, $user, AccountRole::Owner);
    }

    /**
     * The context of the current request, or null when nobody is logged in.
     */
    public static function current(): ?self
    {
        $request = request();
        $context = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        if ($context instanceof self) {
            return $context;
        }

        $user = $request->user();

        return $user ? self::own($user) : null;
    }

    public function account(): User
    {
        return $this->account;
    }

    public function actor(): User
    {
        return $this->actor;
    }

    public function role(): AccountRole
    {
        return $this->role;
    }

    public function isOwner(): bool
    {
        return $this->role === AccountRole::Owner;
    }

    /**
     * Working in someone else's studio rather than one's own.
     */
    public function isTeamContext(): bool
    {
        return $this->account->id !== $this->actor->id;
    }

    public function can(string $ability): bool
    {
        return $this->role->can($ability);
    }

    /**
     * Whether a record owned by $userId belongs to this studio.
     */
    public function belongsTo(?int $userId): bool
    {
        return $userId !== null && $userId === $this->account->id;
    }

    /**
     * Whether a record owned by $userId belongs to this studio and the actor may use $ability on it.
     */
    public function owns(?int $userId, string $ability): bool
    {
        return $this->belongsTo($userId) && $this->can($ability);
    }

    /**
     * Team studios the actor can switch to.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\AccountMember>
     */
    public function teamMemberships(): \Illuminate\Support\Collection
    {
        return $this->teamMemberships ??= $this->actor->activeMemberships();
    }

    private ?\Illuminate\Support\Collection $teamMemberships = null;
}
