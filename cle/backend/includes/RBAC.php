<?php

require_once __DIR__ . '/Response.php';

/**
 * Centralized authorization checks. Every API endpoint MUST call the
 * relevant method here before reading/writing data — never rely on the
 * frontend to hide a button. This is the single source of truth for
 * "who can do what, where."
 */
class RBAC
{
    private const COMPANY_WIDE_ROLES = ['SUPER_ADMIN', 'ADMIN'];

    public static function isCompanyWide(array $user): bool
    {
        return in_array($user['role_name'], self::COMPANY_WIDE_ROLES, true);
    }

    /** True if the user is allowed to operate on data belonging to $locationId. */
    public static function canAccessLocation(array $user, int $locationId): bool
    {
        if (self::isCompanyWide($user)) return true;
        if ((int)$user['location_id'] === $locationId) return true;
        return in_array($locationId, $user['additional_location_ids'], true);
    }

    /** Halts the request with 403 if the user cannot access $locationId. */
    public static function requireLocation(array $user, int $locationId): void
    {
        if (!self::canAccessLocation($user, $locationId)) {
            Response::forbidden('You do not have access to this location\'s data');
        }
    }

    public static function hasPermission(array $user, string $permissionKey): bool
    {
        return in_array($permissionKey, $user['permissions'], true);
    }

    public static function requirePermission(array $user, string $permissionKey): void
    {
        if (!self::hasPermission($user, $permissionKey)) {
            Response::forbidden("Missing required permission: $permissionKey");
        }
    }

    public static function requireRole(array $user, array $allowedRoles): void
    {
        if (!in_array($user['role_name'], $allowedRoles, true)) {
            Response::forbidden('Your role cannot perform this action');
        }
    }

    /**
     * Returns a SQL fragment + bound params restricting a query to locations
     * the user may see. Usage:
     *   [$clause, $params] = RBAC::locationScopeSql($user, 'sales');
     *   "SELECT * FROM sales WHERE 1=1 $clause"
     */
    public static function locationScopeSql(array $user, string $column = 'location_id'): array
    {
        if (self::isCompanyWide($user)) {
            return ['', []];
        }
        $ids = array_merge([(int)$user['location_id']], $user['additional_location_ids']);
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            // No assigned location at all — see nothing.
            return [" AND 1=0", []];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return [" AND $column IN ($placeholders)", $ids];
    }

    /**
     * Discount authority check (section 15/16 of the spec). Returns true if
     * the requested percentage is within the role's ceiling and therefore
     * does NOT require approval.
     */
    public static function discountWithinOwnAuthority(array $user, float $discountPercent): bool
    {
        if (self::isCompanyWide($user)) return true;
        return $discountPercent <= (float)$user['max_discount_percent'];
    }
}
