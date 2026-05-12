<?php
define('APP_ROOT', __DIR__);
require APP_ROOT . '/app/bootstrap.php';

use App\Models\MonitorServer;
use App\Services\Monitor\MonitorService;

$service = new MonitorService();
$servers = (new MonitorServer())->enabledDue();
echo '[' . date('Y-m-d H:i:s') . '] monitor cron start, due servers: ' . count($servers) . PHP_EOL;
foreach ($servers as $server) {
    try {
        $service->collect($server);
        echo '[' . date('Y-m-d H:i:s') . '] collected server #' . $server['id'] . PHP_EOL;
    } catch (Exception $e) {
        echo '[' . date('Y-m-d H:i:s') . '] failed server #' . $server['id'] . ': ' . $e->getMessage() . PHP_EOL;
    }
}
echo '[' . date('Y-m-d H:i:s') . '] monitor cron done' . PHP_EOL;
