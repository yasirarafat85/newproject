<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Webhook;

// Facebook webhook endpoint: GET = verification handshake, POST = events.

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    [$status, $body] = Webhook::verify($_GET);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = (string) file_get_contents('php://input', false, null, 0, Webhook::MAX_BODY_BYTES + 1);
    [$status, $body] = Webhook::receive($raw, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null);
} else {
    [$status, $body] = [405, 'Method not allowed'];
}

http_response_code($status);
echo $body;
