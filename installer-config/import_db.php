<?php

declare(strict_types=1);

session_start();
set_time_limit(0);
ini_set('memory_limit', '512M');
header('Content-Type: application/json; charset=utf-8');

if (is_file(__DIR__ . '/../installation_success.txt')) {
    echo json_encode(['status' => 'error', 'message' => 'This CRM is already installed.']);
    exit;
}

function finish(string $status, string $message, array $details = []): never
{
    echo json_encode(['status' => $status, 'message' => $message, 'details' => $details]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (int) ($_SESSION['step'] ?? 0) < 2) {
    finish('error', 'Complete the database configuration step first.');
}

$root = realpath(__DIR__ . '/..');
if (!is_file($root . '/vendor/autoload.php') || !is_file($root . '/bootstrap/app.php')) {
    finish('error', 'Laravel bootstrap files are missing. Extract the complete project archive first.');
}

try {
    // Never bootstrap configuration cached on the machine that built the ZIP.
    if (is_file($root . '/bootstrap/cache/config.php')) {
        @unlink($root . '/bootstrap/cache/config.php');
    }
    require_once $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $commands = [];
    $run = static function (string $command, array $arguments = []) use (&$commands): void {
        $exitCode = Illuminate\Support\Facades\Artisan::call($command, $arguments);
        $output = trim(Illuminate\Support\Facades\Artisan::output());
        $commands[] = ['command' => $command, 'exit_code' => $exitCode, 'output' => $output];
        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : "Command failed: {$command}");
        }
    };

    $run('config:clear');
    $run('migrate', ['--force' => true]);

    // Seed only application defaults. DatabaseSeeder currently includes demo data.
    foreach (['RoleSeeder', 'MasterDataSeeder', 'SettingsSeeder'] as $seeder) {
        if (is_file($root . '/database/seeders/' . $seeder . '.php')) {
            $run('db:seed', ['--class' => 'Database\\Seeders\\' . $seeder, '--force' => true]);
        }
    }

    try {
        $run('storage:link');
    } catch (Throwable $ignored) {
        // Some shared hosts disallow symlinks; installation remains usable.
    }
    $run('optimize:clear');

    $_SESSION['step'] = 3;
    finish('success', 'Migrations and essential Laravel seeders completed.', $commands);
} catch (Throwable $e) {
    finish('error', 'Laravel setup failed: ' . $e->getMessage());
}
