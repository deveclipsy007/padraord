<?php

declare(strict_types=1);

$manifestPath = $argv[1] ?? null;
$publicRoot = $argv[2] ?? null;

if (! is_string($manifestPath) || ! is_string($publicRoot)) {
    fwrite(STDERR, "Usage: verify-vite-manifest.php MANIFEST PUBLIC_ROOT\n");
    exit(2);
}

try {
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, "Invalid Vite manifest: {$exception->getMessage()}\n");
    exit(1);
}

if (! is_array($manifest) || $manifest === []) {
    fwrite(STDERR, "Vite manifest is empty.\n");
    exit(1);
}

$missing = [];
foreach ($manifest as $entry) {
    if (! is_array($entry)) {
        continue;
    }

    foreach (['file', 'css'] as $key) {
        $paths = $key === 'css' ? ($entry[$key] ?? []) : [$entry[$key] ?? null];
        foreach ($paths as $relative) {
            if (! is_string($relative) || $relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
                $missing[] = (string) $relative;

                continue;
            }

            $path = rtrim($publicRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relative;
            if (! is_file($path)) {
                $missing[] = $relative;
            }
        }
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Vite manifest references missing or unsafe assets:\n- ".implode("\n- ", $missing)."\n");
    exit(1);
}

echo "Vite manifest assets verified.\n";
