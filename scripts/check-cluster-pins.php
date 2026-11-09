<?php

declare(strict_types=1);

/**
 * Fail if any cluster package is not installed at the expected index SHA and root alias.
 */

$expected = [
    'psr/cache' => [
        'alias' => '3.0.0',
        'reference' => '46880c803d3ff3e5826ba4bbf09d67c46caf0a8a',
    ],
    'psr/container' => [
        'alias' => '2.0.2',
        'reference' => '57d8cac2ff3db652075e2d6e44e0ede70720e8e5',
    ],
    'psr/event-dispatcher' => [
        'alias' => '1.0.0',
        'reference' => '2b4e85d5a882c9a0312aec7be4e8f9774544f230',
    ],
    'symfony/polyfill-intl-idn' => [
        'alias' => '1.33.0',
        'reference' => '09a2cc83a4a6b61a1c5c18ff70fc3a459831d533',
    ],
    'symfony/polyfill-intl-normalizer' => [
        'alias' => '1.33.0',
        'reference' => 'c6839534bfcaf6a8b566e44e5bb971e672a678cd',
    ],
    'symfony/phpunit-bridge' => [
        'alias' => '8.0.7',
        'reference' => 'af548a57fca0c4faa38c9214a3e9a63aba15bac0',
    ],
];

$installedPhp = __DIR__ . '/../vendor/composer/installed.php';
if (!is_readable($installedPhp)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}

$installedJson = __DIR__ . '/../vendor/composer/installed.json';
if (!is_readable($installedJson)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
    exit(1);
}

/** @var array{versions: array<string, array{reference?: string, aliases?: list<string>}>} $installed */
$installed = require $installedPhp;
$versions = $installed['versions'] ?? [];

/** @return array<string, array<string, mixed>> */
function loadInstalledJsonPackages(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('check-cluster-pins: could not read installed.json.');
    }

    try {
        /** @var mixed $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('check-cluster-pins: installed.json is invalid JSON.', 0, $e);
    }

    if (!is_array($data)) {
        throw new RuntimeException('check-cluster-pins: installed.json root must be an object.');
    }

    $merged = [];
    foreach (['packages', 'packages-dev'] as $sectionKey) {
        if (!isset($data[$sectionKey]) || !is_array($data[$sectionKey])) {
            continue;
        }

        $section = $data[$sectionKey];
        if ($section === []) {
            continue;
        }

        $isList = array_is_list($section);
        if ($isList) {
            foreach ($section as $package) {
                if (!is_array($package) || !isset($package['name'])) {
                    continue;
                }
                $merged[$package['name']] = $package;
            }
            continue;
        }

        foreach ($section as $key => $package) {
            if (!is_array($package)) {
                continue;
            }

            $name = $package['name'] ?? null;
            if (!is_string($name) || $name === '') {
                if (is_string($key) && str_contains($key, '/')) {
                    $name = $key;
                } else {
                    continue;
                }
            }

            $merged[$name] = $package;
        }
    }

    return $merged;
}

function isFortyCharGitSha(string $reference): bool
{
    return (bool) preg_match('/^[a-f0-9]{40}$/', $reference);
}

/**
 * @param array<string, array{alias: string, reference: string}> $map
 * @param array<string, array<string, mixed>>                    $versions
 *
 * @return list<string>
 */
function checkClusterPinsInstalledPhp(array $map, array $versions): array
{
    $errors = [];

    foreach ($map as $name => $want) {
        if (!isset($versions[$name])) {
            $errors[] = sprintf('%s is not installed (installed.php).', $name);
            continue;
        }

        $pkg = $versions[$name];
        $aliases = $pkg['aliases'] ?? [];
        if (!in_array($want['alias'], $aliases, true)) {
            $errors[] = sprintf(
                '%s alias mismatch (expected %s in aliases %s).',
                $name,
                $want['alias'],
                json_encode($aliases)
            );
        }

        $reference = $pkg['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            $errors[] = sprintf('%s has no reference (installed.php).', $name);
            continue;
        }

        if (!isFortyCharGitSha($reference)) {
            $errors[] = sprintf('%s reference is not a 40-char SHA (installed.php): %s', $name, $reference);
            continue;
        }

        if (!hash_equals($want['reference'], $reference)) {
            $errors[] = sprintf(
                '%s reference mismatch (installed.php expected %s, got %s).',
                $name,
                $want['reference'],
                $reference
            );
        }
    }

    return $errors;
}

/**
 * @param array<string, array{alias: string, reference: string}> $map
 * @param array<string, array<string, mixed>>                    $packages
 *
 * @return list<string>
 */
function checkClusterPinsInstalledJson(array $map, array $packages): array
{
    $errors = [];

    foreach ($map as $name => $want) {
        if (!isset($packages[$name])) {
            $errors[] = sprintf('%s is not installed (installed.json).', $name);
            continue;
        }

        $pkg = $packages[$name];
        $source = $pkg['source'] ?? null;
        if (!is_array($source)) {
            $errors[] = sprintf('%s has no source block (installed.json).', $name);
            continue;
        }

        $reference = $source['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            $errors[] = sprintf('%s has no source.reference (installed.json).', $name);
            continue;
        }

        if (!isFortyCharGitSha($reference)) {
            $errors[] = sprintf('%s source.reference is not a 40-char SHA (installed.json): %s', $name, $reference);
            continue;
        }

        if (!hash_equals($want['reference'], $reference)) {
            $errors[] = sprintf(
                '%s source.reference mismatch (installed.json expected %s, got %s).',
                $name,
                $want['reference'],
                $reference
            );
        }
    }

    return $errors;
}

/**
 * @param list<string> $errors
 */
function demoDetectedPsrCacheReferenceMismatch(array $errors, string $context): bool
{
    foreach ($errors as $error) {
        if (!str_contains($error, 'psr/cache')) {
            continue;
        }
        if (str_contains($error, 'reference mismatch') || str_contains($error, 'source.reference mismatch')) {
            if ($context === 'installed.php' && str_contains($error, 'installed.php')) {
                return true;
            }
            if ($context === 'installed.json' && str_contains($error, 'installed.json')) {
                return true;
            }
        }
    }

    return false;
}

try {
    $jsonPackages = loadInstalledJsonPackages($installedJson);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$wrongDemo = $expected;
$wrongDemo['psr/cache']['reference'] = '46880c803d3ff3e5826ba4bbf09d67c46caf0a8b';

$demoErrorsPhp = checkClusterPinsInstalledPhp($wrongDemo, $versions);
$demoErrorsJson = checkClusterPinsInstalledJson($wrongDemo, $jsonPackages);

if ($demoErrorsPhp === [] && $demoErrorsJson === []) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA self-test failed to detect a bad reference.\n");
    exit(1);
}

if (!demoDetectedPsrCacheReferenceMismatch($demoErrorsPhp, 'installed.php')) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA self-test did not report psr/cache reference mismatch (installed.php).\n");
    fwrite(STDERR, implode("\n", $demoErrorsPhp) . "\n");
    exit(1);
}

if (!demoDetectedPsrCacheReferenceMismatch($demoErrorsJson, 'installed.json')) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA self-test did not report psr/cache source.reference mismatch (installed.json).\n");
    fwrite(STDERR, implode("\n", $demoErrorsJson) . "\n");
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: wrong-SHA self-test detected expected psr/cache mismatch (installed.php and installed.json).\n");

$errors = array_merge(
    checkClusterPinsInstalledPhp($expected, $versions),
    checkClusterPinsInstalledJson($expected, $jsonPackages)
);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'check-cluster-pins: ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
