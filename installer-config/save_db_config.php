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

function envValue(string $value): string
{
    return '"' . str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $value) . '"';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', 'Invalid request method.');
}

$dbHost = trim((string) ($_POST['dbHost'] ?? ''));
$dbPort = trim((string) ($_POST['dbPort'] ?? '3306'));
$dbName = trim((string) ($_POST['dbName'] ?? ''));
$dbUser = trim((string) ($_POST['dbUser'] ?? ''));
$dbPassword = (string) ($_POST['dbPassword'] ?? '');
$baseUrl = rtrim(trim((string) ($_POST['baseUrl'] ?? '')), '/');

if ($dbHost === '' || $dbName === '' || $dbUser === '' || $baseUrl === '') {
    respond('error', 'Host, database name, username, and application URL are required.');
}
if (!preg_match('/^[A-Za-z0-9_$-]+$/', $dbName)) {
    respond('error', 'The database name contains unsupported characters.');
}
if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
    respond('error', 'Enter a valid application URL beginning with http:// or https://.');
}
if (!ctype_digit($dbPort) || (int) $dbPort < 1 || (int) $dbPort > 65535) {
    respond('error', 'Enter a valid database port.');
}

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $quotedDatabase = '`' . str_replace('`', '``', $dbName) . '`';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedDatabase} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE {$quotedDatabase}");
} catch (PDOException $e) {
    respond('error', 'Could not connect to MySQL or create the database. Check the credentials and privileges.');
}

$root = realpath(__DIR__ . '/..');
$example = $root . '/.env.example';
$envPath = $root . '/.env';
if (!is_file($example) && !is_file($envPath)) {
    respond('error', 'Laravel .env.example was not found. Extract the project first.');
}

$contents = file_get_contents(is_file($envPath) ? $envPath : $example);
$values = [
    'APP_NAME' => envValue('Fablead CRM'),
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL' => envValue($baseUrl),
    'PUBLIC_PATH' => 'public',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => envValue($dbHost),
    'DB_PORT' => $dbPort,
    'DB_DATABASE' => envValue($dbName),
    'DB_USERNAME' => envValue($dbUser),
    'DB_PASSWORD' => envValue($dbPassword),
];

if (!preg_match('/^APP_KEY=base64:.+$/m', $contents)) {
    $values['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));
}

foreach ($values as $key => $value) {
    $line = $key . '=' . $value;
    if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $contents)) {
        $contents = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $contents, 1);
    } else {
        $contents .= PHP_EOL . $line;
    }
}

if (file_put_contents($envPath, $contents, LOCK_EX) === false) {
    respond('error', 'Laravel .env could not be written. Check folder permissions.');
}

try {
    require_once __DIR__ . '/normalize_base_path.php';
    normalizeExtractedJavaScriptBasePath($root, $baseUrl);
} catch (Throwable $e) {
    respond('error', 'Laravel was configured, but browser URLs could not be prepared: ' . $e->getMessage());
}

$_SESSION['DB_HOST'] = $dbHost;
$_SESSION['DB_PORT'] = $dbPort;
$_SESSION['DB_NAME'] = $dbName;
$_SESSION['DB_USER'] = $dbUser;
$_SESSION['BASE_URL'] = $baseUrl;
$_SESSION['step'] = 2;

respond('success', 'Database connected and Laravel environment configured.');
