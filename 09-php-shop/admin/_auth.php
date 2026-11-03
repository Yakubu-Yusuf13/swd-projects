<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

final class AdminOrderException extends RuntimeException
{
}

function admin_is_authenticated(): bool
{
    return ($_SESSION['is_admin'] ?? false) === true;
}

function admin_require_login(): void
{
    if (!admin_is_authenticated()) {
        header('Location: ' . url('admin/login.php'), true, 303);
        exit;
    }
}

function admin_status_transitions(string $status): array
{
    return match ($status) {
        'pending' => ['paid', 'cancelled'],
        'paid' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'cancelled'],
        default => [],
    };
}

function admin_status_label(string $status): string
{
    return ucfirst($status);
}
