<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Migration
{
    public string $version;
    public string $name;
    /** @var array<int, string> */
    public array $legacyVersions = [];

    abstract public function up(PDO $pdo): void;
}
