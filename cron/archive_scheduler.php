<?php
require_once '../config/database.php';
require_once '../models/ArchiveSettings.php';
require_once '../services/ArchiveScheduler.php';

// Simple CLI script for cron jobs
if (PHP_SAPI !== 'cli') {
    die('This script can only be run from command line');
}

$database = new Database();
$db = $database->connect();
$archiveScheduler = new ArchiveScheduler($db);

// Parse command line arguments
$options = getopt('f:l:', ['frequency:', 'location:']);
$frequency = $options['f'] ?? $options['frequency'] ?? 'all';
$location_id = $options['l'] ?? $options['location'] ?? null;

echo "Starting archive process...\n";
echo "Frequency: " . $frequency . "\n";
echo "Location: " . ($location_id ?: 'all') . "\n";

try {
    $result = $archiveScheduler->process_archive($location_id);
    echo "Archive completed: " . $result['message'] . "\n";
    
    // Get storage info
    $storage_info = $archiveScheduler->get_storage_info();
    echo "Storage: " . ($storage_info['total_size'] ?? 'N/A') . "\n";
    
} catch (Exception $e) {
    echo "Archive failed: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
?>
