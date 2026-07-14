<?php

declare(strict_types=1);

session_start();
set_time_limit(0);
ini_set('memory_limit', '512M');
header('Content-Type: application/x-ndjson; charset=utf-8');
header('Cache-Control: no-cache');

function sendEvent(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
}

$projectRoot = realpath(__DIR__ . '/..');

function applyLaravelCompatibilityPatches(string $projectRoot): void
{
    $faviconSource = __DIR__ . '/fablead-favicon.png';
    $faviconPublic = $projectRoot . '/public/fablead-favicon.png';
    if (is_file($faviconSource)) {
        if (!is_dir(dirname($faviconPublic))) {
            @mkdir(dirname($faviconPublic), 0775, true);
        }
        @copy($faviconSource, $faviconPublic);
    }

    $faviconTag = "    <link rel=\"icon\" type=\"image/png\" href=\"{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'fablead-favicon.png') }}\">\n";
    $ensureFavicon = static function (string $viewPath) use ($faviconTag): void {
        if (!is_file($viewPath)) {
            return;
        }

        $contents = file_get_contents($viewPath);
        if (str_contains($contents, 'fablead-favicon.png')) {
            return;
        }

        $contents = preg_replace('/\s*<link\s+[^>]*rel=["\'](?:shortcut\s+)?icon["\'][^>]*>\s*/i', "\n", $contents);

        if (str_contains($contents, '</head>')) {
            $contents = str_replace('</head>', $faviconTag . '</head>', $contents);
            file_put_contents($viewPath, $contents, LOCK_EX);
        }
    };

    $basePathPatch = <<<'BLADE'
    <script>
        window.CRM_INSTALLER_BASE_PATH_PATCH_V2 = true;
        window.CRM_BASE_PATH = @json(rtrim(parse_url(url('/'), PHP_URL_PATH) ?: '', '/'));

        (function () {
            const appPaths = /^(api|dashboard|meetings|tasks|leads|deals|customers|users|settings|reports|products|services|projects|follow-ups|invoices|documents|pipeline|workspace|whatsapp|login|logout|auth|masters|tickets|quotations|bookings|marketing)(?:\/|$)/;

            window.crmUrl = function (url) {
                const basePath = window.CRM_BASE_PATH || '';

                if (!basePath || !url || typeof url !== 'string') {
                    return url;
                }

                if (/^(mailto:|tel:|javascript:|data:|blob:|#)/i.test(url)) {
                    return url;
                }

                try {
                    const parsed = new URL(url, window.location.origin);

                    if (parsed.origin !== window.location.origin) {
                        return url;
                    }

                    if (parsed.pathname === basePath || parsed.pathname.startsWith(basePath + '/')) {
                        return url.startsWith(window.location.origin)
                            ? parsed.pathname + parsed.search + parsed.hash
                            : url;
                    }

                    const cleanPath = parsed.pathname.replace(/^\/+/, '');

                    if (!appPaths.test(cleanPath)) {
                        return url;
                    }

                    parsed.pathname = basePath + '/' + cleanPath;
                    return parsed.pathname + parsed.search + parsed.hash;
                } catch (error) {
                    return url;
                }
            };

            const normalizeElementUrls = function (root) {
                const scope = root?.querySelectorAll ? root : document;
                scope.querySelectorAll('a[href], form[action]').forEach(function (element) {
                    const attribute = element.matches('form') ? 'action' : 'href';
                    const original = element.getAttribute(attribute);
                    const normalized = window.crmUrl(original);

                    if (normalized !== original) {
                        element.setAttribute(attribute, normalized);
                    }
                });
            };

            const nativeFetch = window.fetch?.bind(window);
            if (nativeFetch) {
                window.fetch = function (input, init) {
                    const nextInit = init ? { ...init } : {};

                    if (input instanceof Request) {
                        const headers = new Headers(input.headers || {});
                        if (window.crmApplyAuthHeaders) {
                            window.crmApplyAuthHeaders(headers);
                        }

                        return nativeFetch(new Request(window.crmUrl(input.url), {
                            method: input.method,
                            headers,
                            body: input.body,
                            mode: input.mode,
                            credentials: input.credentials,
                            cache: input.cache,
                            redirect: input.redirect,
                            referrer: input.referrer,
                            referrerPolicy: input.referrerPolicy,
                            integrity: input.integrity,
                            keepalive: input.keepalive,
                            signal: input.signal,
                        }), nextInit);
                    }

                    if (window.crmApplyAuthHeaders) {
                        nextInit.headers = window.crmApplyAuthHeaders(nextInit.headers || {});
                    }

                    return nativeFetch(window.crmUrl(input), nextInit);
                };
            }

            const nativeOpen = XMLHttpRequest.prototype.open;
            XMLHttpRequest.prototype.open = function (method, url) {
                arguments[1] = window.crmUrl(url);
                return nativeOpen.apply(this, arguments);
            };

            const patchJquery = function () {
                if (!window.jQuery || window.jQuery.__crmBasePathPatched) {
                    return Boolean(window.jQuery?.__crmBasePathPatched);
                }

                window.jQuery.ajaxPrefilter(function (options) {
                    if (options.url) {
                        options.url = window.crmUrl(options.url);
                    }
                });
                window.jQuery.__crmBasePathPatched = true;
                return true;
            };

            let attempts = 0;
            const jqueryTimer = window.setInterval(function () {
                attempts++;
                if (patchJquery() || attempts > 80) {
                    window.clearInterval(jqueryTimer);
                }
            }, 50);

            document.addEventListener('DOMContentLoaded', function () {
                normalizeElementUrls(document);
                patchJquery();

                new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        mutation.addedNodes.forEach(function (node) {
                            if (node.nodeType === 1) {
                                normalizeElementUrls(node);
                            }
                        });
                    });
                }).observe(document.documentElement, { childList: true, subtree: true });
            });

            document.addEventListener('click', function (event) {
                const link = event.target.closest?.('a[href]');

                if (link) {
                    link.setAttribute('href', window.crmUrl(link.getAttribute('href')));
                }
            }, true);

            document.addEventListener('submit', function (event) {
                const form = event.target;

                if (form?.matches?.('form[action]')) {
                    form.setAttribute('action', window.crmUrl(form.getAttribute('action')));
                }
            }, true);
        })();
    </script>
BLADE;

    $ensureBasePathPatch = static function (string $viewPath) use ($basePathPatch): void {
        if (!is_file($viewPath)) {
            return;
        }

        $contents = file_get_contents($viewPath);

        if (str_contains($contents, 'CRM_INSTALLER_BASE_PATH_PATCH_V2')) {
            return;
        }

        if (str_contains($contents, '</head>')) {
            $contents = str_replace('</head>', $basePathPatch . "\n</head>", $contents);
            file_put_contents($viewPath, $contents, LOCK_EX);
        }
    };

    // Make the bundled login flow safe when Laravel is installed in a subdirectory.
    $loginView = $projectRoot . '/resources/views/auth/login.blade.php';
    if (is_file($loginView)) {
        $loginContents = file_get_contents($loginView);
        $loginContents = str_replace(
            "window.location.href = '/dashboard';",
            "window.location.href = @json(route('dashboard'));",
            $loginContents
        );
        $loginContents = str_replace(
            "{{ asset('/logo/logo1.jpg') }}",
            "{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'logo/logo1.jpg') }}",
            $loginContents
        );
        file_put_contents($loginView, $loginContents, LOCK_EX);
    }
    $ensureFavicon($loginView);

    $redirectView = $projectRoot . '/resources/views/auth/redirect.blade.php';
    if (is_file($redirectView)) {
        $redirectContents = str_replace(
            "form.action = '/dashboard';",
            "form.action = @json(route('dashboard'));",
            file_get_contents($redirectView)
        );
        file_put_contents($redirectView, $redirectContents, LOCK_EX);
    }
    $ensureFavicon($redirectView);

    $layoutView = $projectRoot . '/resources/views/layouts/app.blade.php';
    $ensureFavicon($layoutView);
    $ensureFavicon($projectRoot . '/resources/views/auth/bootstrap.blade.php');
    $ensureBasePathPatch($layoutView);
    $ensureBasePathPatch($projectRoot . '/resources/views/auth/login.blade.php');
    $ensureBasePathPatch($projectRoot . '/resources/views/auth/bootstrap.blade.php');
    if (!is_file($layoutView)) {
        return;
    }

    $layoutContents = file_get_contents($layoutView);

    if (str_contains($layoutContents, 'window.CRM_BASE_PATH')) {
        return;
    }

    $needle = "    <script>\n        window.CRM_AUTH_TOKEN_KEY = 'crm_auth_token';";
    $replacement = <<<'BLADE'
    <script>
        window.CRM_BASE_PATH = @json(rtrim(parse_url(url('/'), PHP_URL_PATH) ?: '', '/'));
        window.CRM_AUTH_TOKEN_KEY = 'crm_auth_token';

        window.crmUrl = function (url) {
            const basePath = window.CRM_BASE_PATH || '';

            if (!basePath || !url || typeof url !== 'string') {
                return url;
            }

            if (/^(mailto:|tel:|javascript:|data:|#)/i.test(url)) {
                return url;
            }

            try {
                const parsed = new URL(url, window.location.origin);

                if (parsed.origin !== window.location.origin) {
                    return url;
                }

                if (parsed.pathname === basePath || parsed.pathname.startsWith(basePath + '/')) {
                    return url;
                }

                const path = parsed.pathname.replace(/^\/[^/]+(?=\/(?:api|dashboard|meetings|tasks|leads|deals|customers|users|settings|reports|products|services|projects|follow-ups|invoices|documents|pipeline|workspace|whatsapp|login|logout|auth)(?:\/|$))/, '');

                if (/^\/(?:api|dashboard|meetings|tasks|leads|deals|customers|users|settings|reports|products|services|projects|follow-ups|invoices|documents|pipeline|workspace|whatsapp|login|logout|auth)(?:\/|$)/.test(path)) {
                    parsed.pathname = basePath + path;
                    return parsed.pathname + parsed.search + parsed.hash;
                }
            } catch (error) {
                return url;
            }

            return url;
        };
BLADE;
    $layoutContents = str_replace($needle, $replacement, $layoutContents);

    $layoutContents = str_replace(
        "                    const requestHeaders = new Headers(request.headers || {});\n                    window.crmApplyAuthHeaders(requestHeaders);\n\n                    return nativeFetch(new Request(request, {\n                        headers: requestHeaders,\n                    }), nextInit);",
        "                    const requestHeaders = new Headers(request.headers || {});\n                    window.crmApplyAuthHeaders(requestHeaders);\n                    const requestUrl = window.crmUrl(request.url);\n\n                    return nativeFetch(new Request(requestUrl, {\n                        method: request.method,\n                        headers: requestHeaders,\n                        body: request.body,\n                        mode: request.mode,\n                        credentials: request.credentials,\n                        cache: request.cache,\n                        redirect: request.redirect,\n                        referrer: request.referrer,\n                        referrerPolicy: request.referrerPolicy,\n                        integrity: request.integrity,\n                        keepalive: request.keepalive,\n                        signal: request.signal,\n                    }), nextInit);",
        $layoutContents
    );

    $layoutContents = str_replace(
        "                return nativeFetch(input, nextInit);\n            };\n        })();",
        "                return nativeFetch(window.crmUrl(input), nextInit);\n            };\n        })();\n\n        (function () {\n            const nativeOpen = XMLHttpRequest.prototype.open;\n\n            XMLHttpRequest.prototype.open = function (method, url) {\n                arguments[1] = window.crmUrl(url);\n                return nativeOpen.apply(this, arguments);\n            };\n        })();\n\n        document.addEventListener('click', function (event) {\n            const link = event.target.closest?.('a[href]');\n\n            if (link) {\n                const normalized = window.crmUrl(link.getAttribute('href'));\n\n                if (normalized !== link.getAttribute('href')) {\n                    link.setAttribute('href', normalized);\n                }\n            }\n        }, true);\n\n        document.addEventListener('submit', function (event) {\n            const form = event.target;\n\n            if (form?.matches?.('form[action]')) {\n                const normalized = window.crmUrl(form.getAttribute('action'));\n\n                if (normalized !== form.getAttribute('action')) {\n                    form.setAttribute('action', normalized);\n                }\n            }\n        }, true);",
        $layoutContents
    );

    file_put_contents($layoutView, $layoutContents, LOCK_EX);
}

if (is_file(__DIR__ . '/../installation_success.txt')) {
    if (isset($_GET['repair']) && $_GET['repair'] === 'base-path') {
        applyLaravelCompatibilityPatches($projectRoot);
        sendEvent(['status' => 'success', 'message' => 'Laravel base-path compatibility patches were applied.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'This CRM is already installed.']) . "\n";
    exit;
}

$zipFile = $projectRoot . DIRECTORY_SEPARATOR . 'crm-fablead.zip';

if (!class_exists('ZipArchive')) {
    sendEvent(['status' => 'error', 'message' => 'The PHP Zip extension is not enabled.']);
    exit;
}

if (!is_file($zipFile)) {
    sendEvent(['status' => 'error', 'message' => 'crm-fablead.zip was not found beside installer.php.']);
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    sendEvent(['status' => 'error', 'message' => 'The project archive could not be opened.']);
    exit;
}

try {
    $total = max(1, $zip->numFiles);
    $prefix = '';

    // Detect the archive's single top-level directory (currently crm-fablead/).
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', $zip->getNameIndex($i));
        if ($name !== '') {
            $slash = strpos($name, '/');
            $prefix = $slash === false ? '' : substr($name, 0, $slash + 1);
            break;
        }
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        $normal = str_replace('\\', '/', $entry);
        $relative = $prefix !== '' && str_starts_with($normal, $prefix)
            ? substr($normal, strlen($prefix))
            : $normal;

        if ($relative === '' || str_ends_with($relative, '/')) {
            continue;
        }

        // Block absolute paths and traversal (Zip Slip).
        if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:\//', $relative) || in_array('..', explode('/', $relative), true)) {
            throw new RuntimeException('The archive contains an unsafe path: ' . $normal);
        }

        // Never allow the application archive to replace the installer itself.
        if ($relative === 'installer.php' || str_starts_with($relative, 'installer-config/')) {
            continue;
        }

        $target = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create directory: ' . $directory);
        }

        $source = $zip->getStream($entry);
        $destination = fopen($target, 'wb');
        if ($source === false || $destination === false) {
            throw new RuntimeException('Could not extract: ' . $normal);
        }
        stream_copy_to_stream($source, $destination);
        fclose($source);
        fclose($destination);

        if ($i % 25 === 0) {
            sendEvent(['status' => 'progress', 'progress' => (int) floor((($i + 1) / $total) * 100)]);
        }
    }

    foreach (['storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $directory) {
        $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }

    applyLaravelCompatibilityPatches($projectRoot);

    if (!is_file($projectRoot . '/artisan') || !is_file($projectRoot . '/vendor/autoload.php')) {
        throw new RuntimeException('Extraction finished, but this does not appear to be a complete Laravel project.');
    }

    $_SESSION['step'] = 1;
    sendEvent(['status' => 'success', 'progress' => 100, 'message' => 'Laravel project extracted successfully.']);
} catch (Throwable $e) {
    sendEvent(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    $zip->close();
}
