<?php

date_default_timezone_set('Europe/Paris');
require 'vendor/autoload.php';
require __DIR__.'/inc/Query.php';
require __DIR__.'/inc/Database.php';
require __DIR__.'/inc/fns.php';
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use App\Database;

// Initialize logging stream (specific loggers are created on demand)
$dateFormat = "d/M/Y, H:i:s e";
$output = "%datetime% > %channel%.%level_name% > %message% %context%\n";

// Create a handler
$loggingStream = new StreamHandler(__DIR__ . '/logs/main.log', DEBUG === true ? Logger::DEBUG : Logger::INFO);
$loggingStream->setFormatter(new LineFormatter($output, $dateFormat));

// Initialize db
$dbconf = json_decode(@file_get_contents(__DIR__."/db.conf"), true);
$svdb = [];
foreach ($dbconf as $svName => $conf) {
    $svdb[$svName] = new Database($conf);
}
