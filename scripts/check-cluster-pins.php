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

/** @var array{versions: array<string, array{reference?: string, aliases?: list<string>}>} $installed */
$installed = require $installedPhp;
$versions = $installed['versions'] ?? [];

/**
 * @param array<string, array{alias: string, reference: string}> $map
 *
 * @return list<string>
 */
function checkClusterPins(array $map, array $versions): array
{
    $errors = [];

    foreach ($map as $name => $want) {
        if (!isset($versions[$name])) {
            $errors[] = sprintf('%s is not installed.', $name);
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
            $errors[] = sprintf('%s has no reference.', $name);
            continue;
        }

        if (!hash_equals($want['reference'], $reference)) {
            $errors[] = sprintf(
                '%s reference mismatch (expected %s, got %s).',
                $name,
                $want['reference'],
                $reference
            );
        }
    }

    return $errors;
}

$wrongDemo = $expected;
$wrongDemo['psr/cache']['reference'] = '46880c803d3ff3e5826ba4bbf09d67c46caf0a8b';
$demoErrors = checkClusterPins($wrongDemo, $versions);
if ($demoErrors === []) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA self-test failed to detect a bad reference.\n");
    exit(1);
}
$hasCacheMismatch = false;
foreach ($demoErrors as $error) {
    if (str_contains($error, 'psr/cache') && str_contains($error, 'reference mismatch')) {
        $hasCacheMismatch = true;
        break;
    }
}
if (!$hasCacheMismatch) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA self-test did not report psr/cache reference mismatch.\n");
    fwrite(STDERR, implode("\n", $demoErrors) . "\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-SHA self-test detected expected psr/cache mismatch.\n");

$errors = checkClusterPins($expected, $versions);
if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'check-cluster-pins: ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
