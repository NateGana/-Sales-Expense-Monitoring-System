<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/** Blocks anonymous visitors from a page. Call at the very top of every protected page. */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

/**
 * Blocks logged-in users whose role is not in $roles from a page.
 * $roles can be a single role string ('admin') or an array (['admin','staff']).
 */
function requireRole($roles): void
{
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/access_denied.php';
        exit;
    }
}

function currentUser(): array
{
    return [
        'user_id'   => $_SESSION['user_id']   ?? null,
        'username'  => $_SESSION['username']  ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role'      => $_SESSION['role']      ?? null,
    ];
}

function isAdmin(): bool
{
    return ($_SESSION['role'] ?? null) === 'admin';
}
