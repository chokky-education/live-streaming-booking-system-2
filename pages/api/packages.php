<?php
/**
 * List active packages as JSON
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Package.php';

init_request_id();

try {
    $db = get_db_connection();
    $package = new Package($db);
    $rows = $package->getActivePackages(true);

    // Ensure equipment list defaults to array and items include safe defaults
    foreach ($rows as &$row) {
        if (empty($row['equipment_list']) || !is_array($row['equipment_list'])) {
            $row['equipment_list'] = [];
        }

        if (!empty($row['items'])) {
            foreach ($row['items'] as &$item) {
                $item['image_url'] = $item['image_path'] ? ('/' . ltrim($item['image_path'], '/')) : null;
                $item['image_alt'] = $item['image_alt'] ?? $item['name'];
                unset($item['image_path']);
                unset($item['package_id']);
                unset($item['created_at']);
                unset($item['updated_at']);
            }
        } else {
            $row['items'] = [];
        }
    }

    api_success(['packages' => $rows]);
} catch (Throwable $e) {
    log_event('API packages error: ' . $e->getMessage(), 'ERROR');
    api_error('INTERNAL_ERROR', 'Unexpected error', 500);
}

?>
