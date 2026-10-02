<?php

declare(strict_types=1);

// Facebook webhook endpoint. Verification, signature check and event
// handling are added in Phase 1.

http_response_code(503);
header('Content-Type: text/plain; charset=utf-8');
echo 'Webhook not enabled yet.';
