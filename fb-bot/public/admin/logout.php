<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

App\Auth::startSession();
App\Auth::logout();
header('Location: login.php');
