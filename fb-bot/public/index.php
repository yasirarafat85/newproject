<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

// Nothing public lives at the root. Send people to the admin login.
header('Location: admin/');
