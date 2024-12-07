<?php
/**
 * Uninstall routine for 410 Response Manager
 *
 * This file runs when the plugin is uninstalled to clean up the database.
 *
 * @package 410-response-manager
 * @author Harry Laurel
 * @copyright 2024 Rathly
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clear all plugin caches
$cache_keys = array(
    '410_entries_list',
    '410_exact_matches',
    '410_regex_patterns',
    '410_url_patterns'
);

$cache_group = '410_response_manager';

foreach ($cache_keys as $key) {
    wp_cache_delete($key, $cache_group);
}

global $wpdb;

// Check if table exists
$table_exists = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
        DB_NAME,
        $wpdb->prefix . '410_urls'
    )
);

if ($table_exists) {
    // Remove table data first
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Cleanup on uninstall
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}410_urls");
    
    // Drop the table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Cleanup on uninstall
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}410_urls");
}

// Delete plugin options
$options_to_delete = array(
    '410_response_manager_version',
    '410_response_manager_settings'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Final cache cleanup
wp_cache_flush();