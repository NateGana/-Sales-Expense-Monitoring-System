<?php

function base_url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function clean(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/** Queues a one-time message shown at the top of the next page load. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Renders and clears any queued flash messages. Call this inside header.php. */
function render_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $f) {
        $type = $f['type'] === 'error' ? 'danger' : $f['type'];
        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">'
            . htmlspecialchars($f['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/** Call at the top of every POST handler. Stops the request with a friendly message on mismatch. */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('error', 'Your session has expired. Please try again.');
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

function peso(?float $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/** Writes one row to activity_logs. Never throws - a logging failure should not break the main action. */
function log_activity(mysqli $conn, int $userId, string $action, string $details = ''): void
{
    try {
        $stmt = $conn->prepare('INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $userId, $action, $details);
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('log_activity failed: ' . $e->getMessage());
    }
}

function friendly_db_error(mysqli_sql_exception $e): string
{
    error_log('DB error: ' . $e->getMessage());
    // Duplicate-entry errors are common and worth a specific message.
    if ($e->getCode() === 1062) {
        return 'That record already exists. Please check your entry and try again.';
    }
    return 'Unable to save the record. Please check the information and try again.';
}
