<?php
/**
 * Weather Update Cron Job
 * Run this script periodically to update weather data for all locations
 */

require_once '../config/database.php';
require_once '../models/Location.php';
require_once '../services/WeatherService.php';

// Log execution
error_log("Weather update cron job started at " . date('Y-m-d H:i:s'));

try {
    $database = new Database();
    $db = $database->connect();
    
    // You should store the API key in a configuration file or database
    $api_key = 'your_openweathermap_api_key_here';
    $weatherService = new WeatherService($db, $api_key);
    
    // Process all locations
    $results = $weatherService->process_all_locations();
    
    // Log results
    $message = "Weather update completed: " . 
               "Success: {$results['success']}, " .
               "Failed: {$results['failed']}, " .
               "Skipped: {$results['skipped']}";
    
    error_log($message);
    echo $message . "\n";
    
} catch (Exception $e) {
    error_log("Weather update error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
?>
