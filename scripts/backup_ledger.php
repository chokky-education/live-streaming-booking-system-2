#!/usr/bin/env php
<?php
/**
 * Ledger backup and restore helper.
 *
 * Usage:
 *   php scripts/backup_ledger.php backup [--output=backups/ledger] [--format=json]
 *   php scripts/backup_ledger.php restore <file>
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$command = $argv[1] ?? null;
if (!in_array($command, ['backup', 'restore'], true)) {
    fwrite(STDERR, "Usage: php scripts/backup_ledger.php backup|restore [options]\n");
    exit(1);
}

$database = new Database();
$pdo = $database->getConnection();

switch ($command) {
    case 'backup':
        $options = getopt('', ['output::', 'format::']);
        $outputDir = $options['output'] ?? (__DIR__ . '/../backups/ledger');
        $format = strtolower($options['format'] ?? 'json');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }
        $timestamp = date('Ymd_His');
        $filename = sprintf('ledger_backup_%s.%s', $timestamp, $format);
        $path = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if ($format === 'json') {
            $stmt = $pdo->query(
                "SELECT ea.package_id, ea.booking_id, ea.date, ea.status,
                        b.status AS booking_status, b.pickup_date, b.return_date
                   FROM equipment_availability ea
              LEFT JOIN bookings b ON b.id = ea.booking_id"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            file_put_contents($path, json_encode([
                'created_at' => date(DATE_ATOM),
                'retention_days' => LEDGER_CLEANUP_RETENTION_DAYS,
                'count' => count($rows),
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            fwrite(STDERR, "Unsupported format: $format (only json).\n");
            exit(1);
        }

        printf("Backup written to %s (%s entries).\n", $path, file_exists($path) ? filesize($path) : 0);
        exit(0);

    case 'restore':
        $file = $argv[2] ?? null;
        if (!$file || !file_exists($file)) {
            fwrite(STDERR, "Restore requires an existing file.\n");
            exit(1);
        }
        $payload = json_decode(file_get_contents($file), true);
        if (!is_array($payload) || !isset($payload['rows'])) {
            fwrite(STDERR, "Invalid backup file format.\n");
            exit(1);
        }
        $rows = $payload['rows'];
        $pdo->beginTransaction();
        try {
            $pdo->exec('DELETE FROM equipment_availability');
            $insert = $pdo->prepare(
                "INSERT INTO equipment_availability (package_id, booking_id, date, status)
                 VALUES (:package_id, :booking_id, :date, :status)"
            );
            foreach ($rows as $row) {
                $insert->execute([
                    ':package_id' => $row['package_id'],
                    ':booking_id' => $row['booking_id'] ?: null,
                    ':date' => $row['date'],
                    ':status' => $row['status'],
                ]);
            }
            $pdo->commit();
            printf("Restored %d ledger rows from %s\n", count($rows), $file);
        } catch (Throwable $e) {
            $pdo->rollBack();
            fwrite(STDERR, "Restore failed: " . $e->getMessage() . "\n");
            exit(1);
        }
        exit(0);
}
