<?php

declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

date_default_timezone_set(config('timezone') ?: 'America/Sao_Paulo');

require __DIR__ . '/../app/api_client.php';
require __DIR__ . '/../app/repository.php';

$hoursAhead = isset($argv[1]) ? max(1, (int) $argv[1]) : 24;
$created = create_due_reminders($hoursAhead);

echo "Lembretes criados: " . $created . PHP_EOL;
