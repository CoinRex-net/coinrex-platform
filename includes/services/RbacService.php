<?php

class RbacService
{
    private static array $permissionCache = [];

    public static function getAdminRole(int $adminId): ?string
    {
        $db = getDBConnection();
        if (!(function_exists('tableExists') && tableExists('roles') && function_exists('tableHasColumn') && tableHasColumn('admins', 'role_id'))) {
            return 'super_admin';
        }
        $stmt = $db->prepare("SELECT r.name FROM admins a LEFT JOIN roles r ON r.id = a.role_id WHERE a.id = ? LIMIT 1");
        $stmt->execute([$adminId]);
        $role = $stmt->fetchColumn();
        return $role ? (string) $role : null;
    }

    public static function isSuperAdmin(int $adminId): bool
    {
        return strtolower((string) self::getAdminRole($adminId)) === 'super_admin';
    }

    public static function hasPermission(int $adminId, string $permissionName): bool
    {
        if (!(function_exists('tableExists') && tableExists('roles') && tableExists('permissions') && tableExists('role_permissions'))) {
            return true;
        }

        if (self::isSuperAdmin($adminId)) {
            return true;
        }

        $db = getDBConnection();
        $stmt = $db->prepare(
            "SELECT 1
             FROM admins a
             INNER JOIN role_permissions rp ON rp.role_id = a.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE a.id = ? AND p.name = ?
             LIMIT 1"
        );
        $stmt->execute([$adminId, $permissionName]);
        return (bool) $stmt->fetchColumn();
    }

    public static function canAccess(int $adminId, string $permissionName): bool
    {
        $key = $adminId . ':' . $permissionName;
        if (!array_key_exists($key, self::$permissionCache)) {
            self::$permissionCache[$key] = self::hasPermission($adminId, $permissionName);
        }
        return self::$permissionCache[$key];
    }
}
