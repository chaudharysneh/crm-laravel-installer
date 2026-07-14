<?php

declare(strict_types=1);

/**
 * Adapt browser-side root-relative application URLs for a subdirectory install.
 * The source ZIP remains unchanged; only the extracted deployment is normalized.
 */
function normalizeExtractedJavaScriptBasePath(string $projectRoot, string $baseUrl): array
{
    $basePath = (string) (parse_url($baseUrl, PHP_URL_PATH) ?? '');
    $basePath = '/' . trim(str_replace('\\', '/', $basePath), '/');

    if ($basePath === '/') {
        return ['base_path' => '', 'files_changed' => 0, 'urls_changed' => 0];
    }

    $javascriptRoot = $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'js';
    if (!is_dir($javascriptRoot)) {
        throw new RuntimeException('The extracted Laravel public/js directory was not found.');
    }

    $filesChanged = 0;
    $urlsChanged = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($javascriptRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'js') {
            continue;
        }

        $path = $file->getPathname();
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Could not read JavaScript file: ' . $path);
        }

        $count = 0;
        $updated = preg_replace_callback(
            // URL-safe characters only: this deliberately cannot match JS regex tails such as /g, "...".
            '~(["\'`])/(?!/)(?!' . preg_quote(trim($basePath, '/'), '~') . '(?:/|["\'`]))([A-Za-z0-9_\\~!$&()*+.:=@?%{}\\/-]*)\1~',
            static function (array $match) use ($basePath): string {
                return $match[1] . $basePath . '/' . $match[2] . $match[1];
            },
            $contents,
            -1,
            $count
        );

        if ($updated === null) {
            throw new RuntimeException('Could not normalize JavaScript URLs in: ' . $path);
        }

        if ($count > 0 && $updated !== $contents) {
            if (file_put_contents($path, $updated, LOCK_EX) === false) {
                throw new RuntimeException('Could not update JavaScript file: ' . $path);
            }
            $filesChanged++;
            $urlsChanged += $count;
        }
    }

    return ['base_path' => $basePath, 'files_changed' => $filesChanged, 'urls_changed' => $urlsChanged];
}
