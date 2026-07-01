<?php

declare(strict_types=1);

use App\Core\Application;

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

$app = Application::boot(base_path());
$app->run();
