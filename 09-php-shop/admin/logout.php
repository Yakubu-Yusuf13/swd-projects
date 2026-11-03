<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verify_csrf();
unset($_SESSION['is_admin']);
session_regenerate_id(true);
header('Location: ' . url('admin/login.php'), true, 303);
exit;
