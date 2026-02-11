<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';

sz_logout();
header('Location: login.php?logout=1', true, 303);
exit;
