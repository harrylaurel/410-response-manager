<?php
/**
 * Uninstall routine for 410 Response Manager
 *
 * This file runs when the plugin is uninstalled to clean up the database.
 *
 * @package 410-response-manager
 * @author Rathly
 * @copyright 2024 Rathly
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Clear plugin caches
$cache_keys = array(
    'rathly_410_url_patterns',
    'rathly_410_exact_matches',
    'rathly_410_regex_patterns',
    'rathly_410_table_exists'
);

$cache_group = 'rathly_410_manager';

foreach ($cache_keys as $key) {
    wp_cache_delete($key, $cache_group);
}

// Get table name
$table_name = $wpdb->prefix . 'rathly_410_urls';

// Check if table exists using cache first
$cache_key = 'rathly_410_table_exists';
$table_exists = wp_cache_get($cache_key, $cache_group);

if (false === $table_exists) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall
    $table_exists = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
            DB_NAME,
            $table_name
        )
    );
    
    // Cache the result for future use
    wp_cache_set($cache_key, $table_exists, $cache_group, HOUR_IN_SECONDS);
}

if ($table_exists) {
    // First, remove all data
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall
    $wpdb->query(
        $wpdb->prepare(
            'DELETE FROM ' . $wpdb->prefix . 'rathly_410_urls WHERE 1 = %d',
            1
        )
    );

    // Then drop the table
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'rathly_410_urls');
}

// Delete plugin options
$options_to_delete = array(
    'rathly_410_manager_version',
    'rathly_410_manager_settings'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Final cache cleanup
wp_cache_flush();
