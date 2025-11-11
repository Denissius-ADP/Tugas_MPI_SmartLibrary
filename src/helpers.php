<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function bootstrapJson(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function jsonBody(): array
{
    $raw = file_get_contents('php://input') ?: '';

    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        fail('Payload harus berupa JSON valid', 422);
    }

    return $decoded;
}

function success($payload = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 400, array $context = []): void
{
    http_response_code($status);
    echo json_encode(['message' => $message, 'context' => $context], JSON_UNESCAPED_UNICODE);
    exit;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireAuth(?array $allowedRoles = null): array
{
    $user = currentUser();

    if (!$user) {
        fail('Session berakhir, silakan login ulang', 401);
    }

    if ($allowedRoles && !in_array($user['role'], $allowedRoles, true)) {
        fail('Akses ditolak', 403);
    }

    return $user;
}

function storeSession(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['nama_lengkap'],
        'username' => $user['username'],
        'email' => $user['email'] ?? null,
        'role' => $user['role'],
    ];
}

function clearSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function logAction(?int $userId, string $aktivitas): void
{
    $stmt = db()->prepare('INSERT INTO log_aktivitas (user_id, aktivitas) VALUES (:user_id, :aktivitas)');

    $stmt->execute([
        ':user_id' => $userId,
        ':aktivitas' => $aktivitas,
    ]);
}

function paginate(int $default = 25): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? $default)));
    $offset = ($page - 1) * $limit;

    return [$limit, $offset];
}

function ensure(array $data, array $rules): void
{
    foreach ($rules as $field => $message) {
        if (!isset($data[$field]) || $data[$field] === '') {
            fail($message, 422, ['field' => $field]);
        }
    }
}

function only(array $data, array $keys): array
{
    $filtered = [];

    foreach ($keys as $key) {
        if (array_key_exists($key, $data)) {
            $filtered[$key] = $data[$key];
        }
    }

    return $filtered;
}

function intParam(string $key, ?array $source = null): ?int
{
    $source ??= $_GET;
    if (!array_key_exists($key, $source)) {
        return null;
    }

    $value = filter_var($source[$key], FILTER_VALIDATE_INT);

    return $value === false ? null : $value;
}
