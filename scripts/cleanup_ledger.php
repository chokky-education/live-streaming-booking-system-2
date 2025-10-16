#!/usr/bin/env php
<?php
/**
 * Ledger cleanup utility.
 *
 * Deletes equipment availability rows for completed/cancelled bookings
 * beyond a retention window and reports orphaned ledger entries.
 *
 * Usage:
 *   php scripts/cleanup_ledger.php [--retention=30] [--dry-run] [--verbose]
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Booking.php';

$options = getopt('', ['retention::', 'dry-run', 'verbose']);
$retentionDays = isset($options['retention']) && is_numeric($options['retention'])
    ? max(0, (int)$options['retention'])
    : LEDGER_CLEANUP_RETENTION_DAYS;
$dryRun = array_key_exists('dry-run', $options);
$verbose = array_key_exists('verbose', $options);

$database = new Database();
$db = $database->getConnection();

$startTime = microtime(true);
$thresholdDate = (new DateTimeImmutable('now'))->modify('-' . $retentionDays . ' days');
$thresholdDateStr = $thresholdDate->format('Y-m-d');

$summary = [
    'retention_days' => $retentionDays,
    'threshold_date' => $thresholdDateStr,
    'stale_rows' => 0,
    'deleted_rows' => 0,
    'orphan_rows' => 0,
    'orphan_samples' => [],
];

// 1. Identify stale ledger rows
$staleQuery = $db->prepare(
    "SELECT ea.id
       FROM equipment_availability ea
       INNER JOIN bookings b ON b.id = ea.booking_id
      WHERE b.status IN ('completed', 'cancelled')
        AND (
            (b.return_date IS NOT NULL AND b.return_date <= :threshold)
            OR (b.return_date IS NULL AND b.updated_at <= :threshold_datetime)
        )"
);
$thresholdDateTime = $thresholdDate->format('Y-m-d H:i:s');
$staleQuery->bindParam(':threshold', $thresholdDateStr);
$staleQuery->bindParam(':threshold_datetime', $thresholdDateTime);
$staleQuery->execute();
$staleIds = $staleQuery->fetchAll(PDO::FETCH_COLUMN, 0);
$summary['stale_rows'] = count($staleIds);

if ($summary['stale_rows'] > 0 && !$dryRun) {
    $deleted = 0;
    $chunkSize = 500;
    $chunks = array_chunk($staleIds, $chunkSize);
    foreach ($chunks as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $deleteStmt = $db->prepare(
            "DELETE ea
               FROM equipment_availability ea
               INNER JOIN bookings b ON b.id = ea.booking_id
              WHERE ea.id IN ($placeholders)"
        );
        foreach ($chunk as $index => $id) {
            $deleteStmt->bindValue($index + 1, (int)$id, PDO::PARAM_INT);
        }
        $deleteStmt->execute();
        $deleted += $deleteStmt->rowCount();
    }
    $summary['deleted_rows'] = $deleted;
} else {
    $summary['deleted_rows'] = 0;
}

// 2. Detect orphaned ledger rows (should be rare due to FK cascade)
$orphanQuery = $db->query(
    "SELECT ea.id, ea.package_id, ea.date
       FROM equipment_availability ea
  LEFT JOIN bookings b ON b.id = ea.booking_id
      WHERE ea.booking_id IS NOT NULL AND b.id IS NULL"
);
$orphans = $orphanQuery->fetchAll(PDO::FETCH_ASSOC);
$summary['orphan_rows'] = count($orphans);
if ($summary['orphan_rows'] > 0) {
    $summary['orphan_samples'] = array_slice($orphans, 0, 10);
    if (!$dryRun) {
        foreach ($orphans as $orphan) {
            log_event(
                sprintf(
                    'Orphan ledger detected: id=%d, package_id=%d, date=%s',
                    $orphan['id'],
                    $orphan['package_id'],
                    $orphan['date']
                ),
                'WARNING'
            );
        }
    }
}

$duration = microtime(true) - $startTime;

if (!$dryRun) {
    $message = sprintf(
        'Ledger cleanup completed: deleted=%d, stale_found=%d, orphans=%d, retention=%d days',
        $summary['deleted_rows'],
        $summary['stale_rows'],
        $summary['orphan_rows'],
        $retentionDays
    );
    log_event($message, 'INFO');
}

// Emit summary to STDOUT
print "=== Ledger Cleanup Report ===\n";
printf("Retention days: %d\n", $retentionDays);
printf("Threshold date: %s\n", $thresholdDateStr);
printf("Stale rows found: %d\n", $summary['stale_rows']);
printf("Deleted rows: %d\n", $summary['deleted_rows']);
printf("Orphan rows: %d\n", $summary['orphan_rows']);
printf("Execution time: %.2f seconds\n", $duration);
if ($dryRun) {
    print "Mode: DRY RUN (no deletions performed)\n";
}
if ($summary['orphan_rows'] > 0 && $verbose) {
    print "Orphan samples:\n";
    foreach ($summary['orphan_samples'] as $sample) {
        printf("  - id=%d package=%d date=%s\n", $sample['id'], $sample['package_id'], $sample['date']);
    }
}

exit(0);
