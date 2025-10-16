#!/usr/bin/env php
<?php
/**
 * Availability cache smoke test.
 *
 * Requires the web server to be running (e.g., php -S localhost:8080 -t .)
 * Usage: php scripts/tests/availability_cache_smoke.php [package_id] [start] [end]
 */

$packageId = isset($argv[1]) ? (int)$argv[1] : 1;
$start = $argv[2] ?? date('Y-m-d');
$end = $argv[3] ?? date('Y-m-d', strtotime('+5 days'));
$baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';

function fetchAvailability($baseUrl, $packageId, $start, $end, $fresh = false) {
    $url = sprintf(
        '%s/pages/api/availability.php?package_id=%d&start=%s&end=%s%s',
        rtrim($baseUrl, '/'),
        $packageId,
        $start,
        $end,
        $fresh ? '&fresh=1' : ''
    );
    $response = @file_get_contents($url);
    if ($response === false) {
        fwrite(STDERR, "Failed to fetch availability from $url\n");
        exit(1);
    }
    $payload = json_decode($response, true);
    if (!is_array($payload) || empty($payload['success'])) {
        fwrite(STDERR, "Unexpected response: $response\n");
        exit(1);
    }
    return $payload['data'];
}

$first = fetchAvailability($baseUrl, $packageId, $start, $end, true);
$second = fetchAvailability($baseUrl, $packageId, $start, $end);

print "=== Availability Cache Smoke Test ===\n";
printf("Package ID: %d\n", $packageId);
printf("Window: %s .. %s\n", $start, $end);

$firstFresh = $first['cache']['fresh'] ?? null;
$secondFresh = $second['cache']['fresh'] ?? null;
printf("First call fresh flag: %s\n", var_export($firstFresh, true));
printf("Second call fresh flag: %s\n", var_export($secondFresh, true));

if (!isset($first['capacity'], $first['usage'])) {
    fwrite(STDERR, "Missing capacity/usage metadata\n");
    exit(1);
}

printf("Capacity: %d\n", (int)$first['capacity']);
printf("Usage entries: %d\n", count($first['usage']));

if ($firstFresh !== true || $secondFresh !== false) {
    fwrite(STDERR, "Cache behaviour unexpected (fresh flags).\n");
    exit(2);
}

print "Cache behaviour looks healthy.\n";
