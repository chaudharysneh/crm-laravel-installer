<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (is_file(__DIR__ . '/../installation_success.txt')) {
    echo json_encode(['status' => 'error', 'message' => 'This CRM is already installed.']);
    exit;
}

function respond(string $status, string $message, array $extra = []): never
{
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (int) ($_SESSION['step'] ?? 0) < 3) {
    respond('error', 'Run the Laravel migrations before creating an administrator.');
}

$name = trim((string) ($_POST['adminUsername'] ?? ''));
$email = strtolower(trim((string) ($_POST['adminEmail'] ?? '')));
$password = (string) ($_POST['adminPassword'] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    respond('error', 'Enter a name, a valid email address, and a password of at least 8 characters.');
}

$root = realpath(__DIR__ . '/..');
try {
    require_once $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    if (App\Models\User::where('email', $email)->exists()) {
        respond('error', 'A user with this email address already exists.');
    }

    $user = App\Models\User::create([
        'name' => $name,
        'email' => $email,
        'password' => Illuminate\Support\Facades\Hash::make($password),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $role = Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole($role);

    $marker = $root . '/installation_success.txt';
    $markerContents = "Fablead CRM Laravel installation completed.\n"
        . "Administrator: {$email}\n"
        . 'Installed at: ' . date(DATE_ATOM) . "\n";
    if (file_put_contents($marker, $markerContents, LOCK_EX) === false) {
        throw new RuntimeException('The installation marker could not be written.');
    }

    Illuminate\Support\Facades\Artisan::call('optimize:clear');
    $_SESSION['step'] = 4;
    respond('success', 'Administrator created. Your CRM is ready.', ['url' => $_SESSION['BASE_URL'] ?? '/']);
} catch (Throwable $e) {
    respond('error', 'Administrator setup failed: ' . $e->getMessage());
}
