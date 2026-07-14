<?php

declare(strict_types=1);

session_start();

$defaultUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/.');

if (!isset($_SESSION['BASE_URL'])) {
    $_SESSION['BASE_URL'] = $defaultUrl;
}

$successFile = __DIR__ . '/installation_success.txt';
if (is_file($successFile) && !isset($_GET['reinstall'])) {
    header('Location: ' . $_SESSION['BASE_URL']);
    exit;
}

$currentStep = min(4, max(1, ((int) ($_SESSION['step'] ?? 0)) + 1));
$requirements = [
    'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'Zip extension' => class_exists('ZipArchive'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'OpenSSL' => extension_loaded('openssl'),
    'Project archive' => is_file(__DIR__ . '/crm-fablead.zip'),
    'Writable directory' => is_writable(__DIR__),
];
$requirementsOk = !in_array(false, $requirements, true);
$passedRequirements = count(array_filter($requirements));
$totalRequirements = count($requirements);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Install Fablead CRM</title>
    <link rel="icon" type="image/png" href="installer-config/fablead-favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="installer-config/style.css">
</head>
<body>
<main class="installer-shell">
    <aside class="brand-panel">
        <div>
            <img class="brand-mark" src="installer-config/fablead-logo-TM.png" alt="Fablead CRM logo">
            <p class="eyebrow">Fablead CRM</p>
            <h1>Your workspace,<br>ready in minutes.</h1>
            <p class="brand-copy">This wizard extracts the Laravel application, connects MySQL, runs migrations, and creates your administrator.</p>
        </div>
        <div class="security-note"><span>✓</span> Guided Laravel setup with migrations, storage, and admin account.</div>
    </aside>

    <section class="wizard-panel">
        <header class="wizard-header">
            <div>
                <p class="eyebrow">Laravel installer</p>
                <h2>Set up your CRM</h2>
            </div>
            <span class="step-count">Step <strong id="stepNumber"><?= $currentStep ?></strong> of 4</span>
        </header>

        <nav class="stepper" aria-label="Installation progress">
            <?php foreach (['Extract', 'Connect', 'Migrate', 'Admin'] as $index => $label): $number = $index + 1; ?>
                <div class="step-dot <?= $number < $currentStep ? 'complete' : ($number === $currentStep ? 'current' : '') ?>" data-step-dot="<?= $number ?>">
                    <span><?= $number < $currentStep ? '✓' : $number ?></span><small><?= $label ?></small>
                </div>
            <?php endforeach; ?>
        </nav>
        <div class="progress-track"><span id="overallProgress" style="width: <?= (($currentStep - 1) / 3) * 100 ?>%"></span></div>

        <div id="notice" class="notice" role="alert" aria-live="polite"></div>

        <section class="step <?= $currentStep === 1 ? 'active' : '' ?>" data-step="1">
            <div class="step-icon">⇩</div>
            <p class="eyebrow">Step one</p>
            <h3>Extract the application</h3>
            <p class="description">We’ll unpack <code>crm-fablead.zip</code> into this directory and verify that the complete Laravel project is present.</p>
            <div class="requirements-summary <?= $requirementsOk ? 'passed' : 'failed' ?>">
                <div>
                    <strong><?= $passedRequirements ?>/<?= $totalRequirements ?> checks ready</strong>
                    <span><?= $requirementsOk ? 'Server is ready for Laravel extraction.' : 'Resolve failed checks before extracting.' ?></span>
                </div>
            </div>
            <div class="requirements">
                <?php foreach ($requirements as $label => $passed): ?>
                    <div class="requirement <?= $passed ? 'passed' : 'failed' ?>"><span><?= $passed ? '✓' : '!' ?></span><?= htmlspecialchars($label) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="extract-progress" id="extractProgress" aria-live="polite">
                <div class="extract-progress__meta">
                    <span>Extracting project</span>
                    <strong id="extractPercent">0%</strong>
                </div>
                <div class="extract-progress__bar"><span id="extractBar"></span></div>
            </div>
            <button class="primary-button" id="unzipBtn" <?= !$requirementsOk ? 'disabled' : '' ?>>Extract Laravel project <span>→</span></button>
        </section>

        <section class="step <?= $currentStep === 2 ? 'active' : '' ?>" data-step="2">
            <div class="step-icon">⌁</div>
            <p class="eyebrow">Step two</p>
            <h3>Connect your database</h3>
            <p class="description">The database will be created if it doesn’t exist. Existing databases are never dropped.</p>
            <form id="dbForm" novalidate>
                <div class="field-grid two">
                    <label><span class="field-label"><i class="fa-solid fa-server"></i>Database host</span><input name="dbHost" value="127.0.0.1" required></label>
                    <label><span class="field-label"><i class="fa-solid fa-network-wired"></i>Port</span><input name="dbPort" value="3306" inputmode="numeric" required></label>
                </div>
                <label><span class="field-label"><i class="fa-solid fa-database"></i>Database name</span><input name="dbName" placeholder="fablead_crm" pattern="[A-Za-z0-9_$-]+" required></label>
                <div class="field-grid two">
                    <label><span class="field-label"><i class="fa-solid fa-user"></i>Username</span><input name="dbUser" value="root" autocomplete="username" required></label>
                    <label><span class="field-label"><i class="fa-solid fa-lock"></i>Password <small>(can be blank)</small></span><span class="password-field"><input name="dbPassword" type="password" autocomplete="current-password"><button type="button" class="password-toggle" aria-label="Show password" data-toggle-password><i class="fa-solid fa-eye"></i></button></span></label>
                </div>
                <label><span class="field-label"><i class="fa-solid fa-link"></i>Application URL</span><input name="baseUrl" type="url" value="<?= htmlspecialchars($_SESSION['BASE_URL']) ?>" required></label>
                <button class="primary-button" type="submit">Save and continue <span>→</span></button>
            </form>
        </section>

        <section class="step <?= $currentStep === 3 ? 'active' : '' ?>" data-step="3">
            <div class="step-icon">⌘</div>
            <p class="eyebrow">Step three</p>
            <h3>Build the database</h3>
            <p class="description">Laravel will run all migrations and seed only essential roles, permissions, master data, and settings. This can take a few minutes.</p>
            <div class="command-preview"><span>$</span> php artisan migrate --force</div>
            <button class="primary-button" id="migrateBtn">Run Laravel setup <span>→</span></button>
        </section>

        <section class="step <?= $currentStep === 4 ? 'active' : '' ?>" data-step="4">
            <div class="step-icon">♙</div>
            <p class="eyebrow">Final step</p>
            <h3>Create your administrator</h3>
            <p class="description">This account receives the CRM’s admin role. Use a strong password with at least 8 characters.</p>
            <form id="adminForm" novalidate>
                <label><span class="field-label"><i class="fa-solid fa-user-shield"></i>Full name</span><input name="adminUsername" autocomplete="name" required></label>
                <label><span class="field-label"><i class="fa-solid fa-envelope"></i>Email address</span><input name="adminEmail" type="email" autocomplete="email" required></label>
                <label><span class="field-label"><i class="fa-solid fa-key"></i>Password</span><span class="password-field"><input name="adminPassword" type="password" minlength="8" autocomplete="new-password" required><button type="button" class="password-toggle" aria-label="Show password" data-toggle-password><i class="fa-solid fa-eye"></i></button></span></label>
                <button class="primary-button" type="submit">Finish installation <span>✓</span></button>
            </form>
        </section>
    </section>
</main>
<footer class="installer-footer">
    <a href="https://www.fableadtechnolabs.com/" target="_blank" rel="noopener">
        &copy; <?= date('Y') ?> Copyright - Fablead Developers Technolab
    </a>
</footer>
<script>window.installerStep = <?= $currentStep ?>;</script>
<script src="installer-config/script.js"></script>
</body>
</html>
