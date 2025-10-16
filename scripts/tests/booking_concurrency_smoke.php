#!/usr/bin/env php
<?php
/**
 * Simple concurrency smoke test for booking capacity locking.
 *
 * Usage:
 *   php scripts/tests/booking_concurrency_smoke.php [package_id] [pickup_date] [return_date] [attempts] [--keep]
 */

$projectRoot = dirname(__DIR__, 1);
require_once $projectRoot . '/../includes/config.php';
require_once $projectRoot . '/../includes/functions.php';
require_once $projectRoot . '/../models/Package.php';
require_once $projectRoot . '/../models/Booking.php';

$options = getopt('', ['keep']);
$args = array_values(array_diff($argv, ['--keep']));

$packageId = isset($args[1]) ? (int)$args[1] : 1;
$pickupDate = $args[2] ?? date('Y-m-d', strtotime('+1 day'));
$returnDate = $args[3] ?? $pickupDate;
$attempts = isset($args[4]) ? max(1, (int)$args[4]) : 6;
$keepData = isset($options['keep']);

$database = new Database();
$pdo = $database->getConnection();
$packageModel = new Package($pdo);

if (!$packageModel->getById($packageId)) {
    fwrite(STDERR, "Package {$packageId} not found\n");
    exit(1);
}

$capacity = (int)($packageModel->max_concurrent_reservations ?? 1);

$userStmt = $pdo->query("SELECT id FROM users WHERE role = 'customer' LIMIT 1");
$userId = (int)$userStmt->fetchColumn();
if (!$userId) {
    $fallbackUser = $pdo->query("SELECT id FROM users LIMIT 1");
    $userId = (int)$fallbackUser->fetchColumn();
}
if (!$userId) {
    fwrite(STDERR, "No user found for booking creation\n");
    exit(1);
}

$booking = new Booking($pdo);
if (!$booking->checkPackageAvailability($packageId, $pickupDate, $returnDate)) {
    fwrite(STDERR, "Selected date range already at capacity. Choose another window.\n");
    exit(1);
}

$pricing = $booking->calculatePricingBreakdown($packageModel->price, $pickupDate, $returnDate);
$subtotal = $pricing['subtotal'];
$vat = $subtotal * VAT_RATE;
$totalPrice = $subtotal + $vat;

$runId = bin2hex(random_bytes(4));
$noteTag = 'CONCURRENCY_SMOKE_' . $runId;

$attempt = function () use ($packageId, $pickupDate, $returnDate, $userId, $totalPrice, $noteTag) {
    $childDb = new Database();
    $db = $childDb->getConnection();
    $childPackage = new Package($db);
    $childPackage->getById($packageId);
    $childBooking = new Booking($db);

    if (!$childBooking->checkPackageAvailability($packageId, $pickupDate, $returnDate)) {
        $childBooking->logCapacityWarning($packageId, $pickupDate, $returnDate, $userId);
        return 3;
    }

    $childBooking->user_id = $userId;
    $childBooking->package_id = $packageId;
    $childBooking->pickup_date = $pickupDate;
    $childBooking->return_date = $returnDate;
    $childBooking->pickup_time = BOOKING_DEFAULT_PICKUP_TIME;
    $childBooking->return_time = BOOKING_DEFAULT_RETURN_TIME;
    $childBooking->location = 'Concurrency Test';
    $childBooking->notes = $noteTag;
    $childBooking->total_price = $totalPrice;
    $childBooking->status = 'pending';

    if ($childBooking->create()) {
        return 0;
    }

    if ($childBooking->error_code === 'capacity_conflict') {
        return 2;
    }

    return 1;
};

$childPids = [];
$successCount = 0;
$conflictCount = 0;
$preCheckConflicts = 0;
$failCount = 0;

if (function_exists('pcntl_fork')) {
    for ($i = 0; $i < $attempts; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            fwrite(STDERR, "Failed to fork child process\n");
            exit(1);
        }
        if ($pid === 0) {
            $code = $attempt();
            exit($code);
        }
        $childPids[] = $pid;
    }

    foreach ($childPids as $pid) {
        $status = 0;
        pcntl_waitpid($pid, $status);
        $code = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 1;
        switch ($code) {
            case 0:
                $successCount++;
                break;
            case 2:
                $conflictCount++;
                break;
            case 3:
                $preCheckConflicts++;
                break;
            default:
                $failCount++;
        }
    }
} else {
    fwrite(STDERR, "pcntl extension unavailable; running sequential attempts. Results may not reflect true concurrency.\n");
    for ($i = 0; $i < $attempts; $i++) {
        $code = $attempt();
        switch ($code) {
            case 0:
                $successCount++;
                break;
            case 2:
                $conflictCount++;
                break;
            case 3:
                $preCheckConflicts++;
                break;
            default:
                $failCount++;
        }
    }
}

if (!$keepData) {
    try {
        $cleanupStmt = $pdo->prepare("DELETE FROM bookings WHERE notes = :noteTag");
        $cleanupStmt->bindParam(':noteTag', $noteTag);
        $cleanupStmt->execute();
    } catch (PDOException $e) {
        fwrite(STDERR, "Cleanup failed (skipping): " . $e->getMessage() . "\n");
    }
}

echo "=== Booking Concurrency Smoke Test ===\n";
printf("Package ID: %d (capacity %d)\n", $packageId, $capacity);
printf("Date range: %s .. %s\n", $pickupDate, $returnDate);
printf("Attempts: %d\n", $attempts);
printf("Success: %d\n", $successCount);
printf("Capacity conflicts: %d\n", $conflictCount + $preCheckConflicts);
if ($preCheckConflicts > 0) {
    printf("  - Conflicts before create: %d\n", $preCheckConflicts);
}
printf("Failures: %d\n", $failCount);

if (!$keepData) {
    echo "Temporary bookings cleaned up. Use --keep to retain results.\n";
}

if ($successCount > $capacity) {
    fwrite(STDERR, "WARNING: successes exceeded declared capacity! Investigate locking logic.\n");
    exit(2);
}

exit(0);
