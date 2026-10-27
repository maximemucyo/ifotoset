<?php

namespace App\Enums;

/**
 * A person's role inside a studio account. Separate from users.role, which is the platform role.
 *
 * Each ability is registered as a "studio.{ability}" Gate for the current studio account.
 */
enum AccountRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Member = 'member';

    /**
     * Abilities granted to each role. Owners hold every ability.
     */
    private const ABILITIES = [
        // Create, edit, upload, hide, invite clients, restore from trash
        'galleries.manage' => ['manager', 'member'],
        // Delete galleries, purge or empty the trash
        'galleries.delete' => ['manager'],
        'bookings.manage' => ['manager', 'member'],
        'clients.manage' => ['manager', 'member'],
        'clients.delete' => ['manager'],
        // Packages and availability (members can still view them)
        'business.manage' => ['manager'],
        // Portfolio builder and public profile
        'profile.manage' => ['manager'],
        'analytics.view' => ['manager'],
        'team.view' => ['manager'],
        'team.manage' => [],
        'billing' => ['manager'],
        // Data export and account deletion
        'account.manage' => [],
    ];

    /**
     * @return list<string>
     */
    public static function abilities(): array
    {
        return array_keys(self::ABILITIES);
    }

    /**
     * Roles the owner can give to a member.
     *
     * @return list<string>
     */
    public static function assignable(): array
    {
        return [self::Manager->value, self::Member->value];
    }

    public function can(string $ability): bool
    {
        if (!array_key_exists($ability, self::ABILITIES)) {
            return false;
        }

        return $this === self::Owner || in_array($this->value, self::ABILITIES[$ability], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Member => 'Member',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full access, including billing and the team.',
            self::Manager => 'Everything, including billing, except the team and deleting the account.',
            self::Member => 'Galleries, uploads, bookings and clients. Can\'t delete galleries or clients.',
        };
    }
}
