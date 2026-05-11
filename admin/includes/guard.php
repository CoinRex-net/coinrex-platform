<?php

function adminGuardIsLoggedIn(): bool {
    return !empty($_SESSION[ADMIN_SESSION_ID_KEY]);
}

function adminGuardRequireAuth(): void {
    if (!adminGuardIsLoggedIn()) {
        header('Location: ' . ADMIN_BASE_URL . '/login.php');
        exit();
    }
}

function adminGuardRequirePermission(string $permission): void {
    adminGuardRequireAuth();
    $adminId = (int) ($_SESSION[ADMIN_SESSION_ID_KEY] ?? 0);
    if ($adminId <= 0 || !RbacService::canAccess($adminId, $permission)) {
        http_response_code(403);
        die('Access Denied');
    }
}
