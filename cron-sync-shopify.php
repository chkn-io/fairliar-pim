<?php
/**
 * Shopify Stock Sync CRON Job
 * 
 * This file should be called by Hostinger CRON job to sync stock to Shopify
 * for variants with custom.pim_sync = 'true'.
 * 
 * Hostinger CRON Setup:
 * Command: /usr/bin/php /home/[your-username]/domains/pim-fairliar.com/public_html/cron-sync-shopify.php
 * Schedule: Star-slash-30 star star star star (every 30 minutes)
 * Actual cron format: /30 * * * *
 */

// Define the base path
define('LARAVEL_START', microtime(true));

// Register the Composer autoloader
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';

// Create the kernel
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Run the shopify:sync-stock command
$status = $kernel->call('shopify:sync-stock');

// Get the output
$output = $kernel->output();

// Log the result
$logFile = __DIR__ . '/storage/logs/cron-shopify-sync.log';
$timestamp = date('Y-m-d H:i:s');
$logMessage = "[{$timestamp}] Shopify stock sync completed with status: {$status}\n";
$logMessage .= "Output: " . trim($output) . "\n";
$logMessage .= str_repeat('-', 80) . "\n";

file_put_contents($logFile, $logMessage, FILE_APPEND);

// Output for cron log
echo $logMessage;

// Exit with the command status
exit($status);
