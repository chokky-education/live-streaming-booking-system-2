#!/usr/bin/env php
<?php
/**
 * Booking concurrency stress test.
 *
 * Reuses direct DB access to spawn concurrent booking attempts and repeat over multiple rounds.
 * Usage:
 *   php scripts/tests/booking_concurrency_stress.php [package_id] [pickup_date] [return_date] [attempts] [--rounds=5] [--keep]
 */

require_once dirname(__DIR__) . '/../includes/config.php';
require_once dirname(__DIR__) . '/../includes/functions.php';
require_once dirname(__DIR__) . '/../models/Package.php';
require_once dirname(__DIR__) . '/../models/Booking.php';

$options = getopt('', ['rounds::', 'keep']);
$args = array_values(array_diff($argv, ['--keep']));

$packageId = isset($args[1]) ? (int)$args[1] : 1;
$pickupDate = $args[2] ?? date('Y-m-d', strtotime('+1 day'));
$returnDate = $args[3] ?? $pickupDate;
$attempts = isset($args[4]) ? max(1, (int)$args[4]) : 12;
$rounds = isset($options['rounds']) && is_numeric($options['rounds']) ? max(1, (int)$options['rounds']) : 5;
$keepData = array_key_exists('keep', $options);

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

$attemptBooking = function () use ($packageId, $pickupDate, $returnDate, $userId, $totalPrice) {
    $childDb = new Database();
    $db = $childDb->getConnection();
    $childPackage = new Package($db);
    $childPackage->getById($packageId);
    $childBooking = new Booking($db);

    $suffix = bin2hex(random_bytes(2));
    if (!$childBooking->checkPackageAvailability($packageId, $pickupDate, $returnDate)) {
        $childBooking->logCapacityWarning($packageId, $pickupDate, $returnDate, $userId);
        return ['code' => 3];
    }

    $childBooking->user_id = $userId;
    $childBooking->package_id = $packageId;
    $childBooking->pickup_date = $pickupDate;
    $childBooking->return_date = $returnDate;
    $childBooking->pickup_time = BOOKING_DEFAULT_PICKUP_TIME;
    $childBooking->return_time = BOOKING_DEFAULT_RETURN_TIME;
    $childBooking->location = 'Stress Test';
    $childBooking->notes = 'CONCURRENCY_STRESS_' . $suffix;
    $childBooking->total_price = $totalPrice;
    $childBooking->status = 'pending';

    if ($childBooking->create()) {
        return ['code' => 0, 'booking_id' => $childBooking->id, 'notes' => $childBooking->notes];
    }

    if ($childBooking->error_code === 'capacity_conflict') {
        return ['code' => 2];
    }

    return ['code' => 1];
};

$globalStats = [
    'success' => 0,
    'capacity_conflict' => 0,
    'precheck_conflict' => 0,
    'failure' => 0,
];
$runId = bin2hex(random_bytes(4));
$keptNotes = [];

$runRound = function ($round) use ($attemptBooking, $attempts, &$globalStats, &$keptNotes, $keepData, $pdo) {
    $childPids = [];
    $results = [];

    if (function_exists('pcntl_fork')) {
        for ($i = 0; $i < $attempts; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                fwrite(STDERR, "[Round $round] Failed to fork child process\n");
                exit(1);
            }
            if ($pid === 0) {
                $result = $attemptBooking();
                $status = $result['code'];
                if ($status === 0 && isset($result['notes'])) {
                    echo json_encode($result) . "\n";
                }
                exit($status);
            }
            $childPids[] = $pid;
        }

        foreach ($childPids as $pid) {
            $status = 0;
            pcntl_waitpid($pid, $status);
            $code = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 1;
            switch ($code) {
                case 0:
                    $globalStats['success']++;
                    break;
                case 2:
                    $globalStats['capacity_conflict']++;
                    break;
                case 3:
                    $globalStats['precheck_conflict']++;
                    break;
                default:
                    $globalStats['failure']++;
            }
        }
    } else {
        fwrite(STDERR, "pcntl not available; running sequentially (round $round).\n");
        for ($i = 0; $i < $attempts; $i++) {
            $result = $attemptBooking();
            switch ($result['code']) {
                case 0:
                    $globalStats['success']++;
                    if (isset($result['notes'])) {
                        $keptNotes[] = $result['notes'];
                    }
                    break;
                case 2:
                    $globalStats['capacity_conflict']++;
                    break;
                case 3:
                    $globalStats['precheck_conflict']++;
                    break;
                default:
                    $globalStats['failure']++;
            }
        }
    }

    if (!$keepData) {
        $cleanup = $pdo->prepare("DELETE FROM bookings WHERE notes LIKE 'CONCURRENCY_STRESS_%'");
        $cleanup->execute();
    }
};

for ($round = 1; $round <= $rounds; $round++) {
    $runRound($round);
}

$expectedSuccess = min($capacity, $attempts) * $rounds;

print "=== Booking Concurrency Stress Test ===\n";
printf("Package ID: %d\n", $packageId);
printf("Capacity: %d\n", $capacity);
printf("Date range: %s .. %s\n", $pickupDate, $returnDate);
printf("Attempts per round: %d\n", $attempts);
printf("Rounds: %d\n", $rounds);
printf("Total attempts: %d\n", $attempts * $rounds);
printf("Success: %d (expected <= %d)\n", $globalStats['success'], $expectedSuccess);
printf("Capacity conflicts during create: %d\n", $globalStats['capacity_conflict']);
printf("Conflicts caught before create: %d\n", $globalStats['precheck_conflict']);
printf("Failures: %d\n", $globalStats['failure']);

if (!$keepData) {
    print "Temporary bookings cleaned up. Use --keep to retain for inspection.\n";
}

if ($globalStats['success'] > $expectedSuccess) {
    fwrite(STDERR, "WARNING: Successes exceeded expected capacity; investigate locking!\n");
    exit(2);
}
if ($globalStats['failure'] > 0) {
    fwrite(STDERR, "WARNING: Encountered unexpected failures; review logs.\n");
    exit(3);
}

exit(0);
