<?php
/**
 * Plugin Name: 410 Response Manager
 * Plugin URI: https://rathly.com/wordpress-plugins/410-response-manager/
 * Description: Manage 410 Gone responses with manual entries, regex patterns, and CSV import functionality.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: Rathly
 * Author URI: https://rathly.com/services/web-design/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 410-response-manager
 * Domain Path: /languages
 *
 * @package 410-response-manager
 * @author Rathly
 * @copyright 2024 Rathly
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define plugin constants
 */
define('RATHLY_410_VERSION', '1.0.0');
define('RATHLY_410_FILE', __FILE__);
define('RATHLY_410_PATH', plugin_dir_path(__FILE__));
define('RATHLY_410_URL', plugin_dir_url(__FILE__));

class RathlyResponse410Manager {
    /**
     * Instance of this class.
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * WP Filesystem instance.
     *
     * @var WP_Filesystem_Base
     */
    private $fs;

    /**
     * Cache group name.
     *
     * @var string
     */
    private $cache_group = 'rathly_410_manager';

    /**
     * Table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Get instance of this class.
     *
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        global $wpdb;
        $this->version = RATHLY_410_VERSION;
        $this->table_name = $wpdb->prefix . 'rathly_410_urls';
        
        // Initialize WP_Filesystem
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;
        $this->fs = $wp_filesystem;
        
        // Initialize hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('template_redirect', array($this, 'check_410_status'), 1);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_rathly_delete_410_url', array($this, 'handle_ajax_delete'));
        add_action('wp_ajax_rathly_bulk_410_action', array($this, 'handle_bulk_action'));
        
        register_activation_hook(RATHLY_410_FILE, array($this, 'activate_plugin'));
        register_deactivation_hook(RATHLY_410_FILE, array($this, 'deactivate_plugin'));
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'rathly_410_manager_settings', 
            'rathly_410_manager_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array(
                    'convert_404_to_410' => false
                ),
                'show_in_rest' => false
            )
        );
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Input array to sanitize.
     * @return array
     */
    public function sanitize_settings($input) {
        if (!is_array($input)) {
            return array(
                'convert_404_to_410' => false
            );
        }

        return array(
            'convert_404_to_410' => !empty($input['convert_404_to_410'])
        );
    }

    /**
     * Activate plugin.
     */
    public function activate_plugin() {
        $this->create_database_table();
        
        add_option('rathly_410_manager_version', $this->version);
        add_option('rathly_410_manager_settings', array(
            'convert_404_to_410' => false
        ));
        
        flush_rewrite_rules();
    }

    /**
     * Deactivate plugin.
     */
    public function deactivate_plugin() {
        $this->clear_all_cache();
        flush_rewrite_rules();
    }

    /**
     * Create database table.
     */
    private function create_database_table() {
        global $wpdb;
        
        $table_exists = $this->check_table_exists();

        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "rathly_410_urls (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                url_pattern varchar(255) NOT NULL,
                is_regex tinyint(1) NOT NULL DEFAULT '0',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY url_pattern (url_pattern)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Check if table exists.
     *
     * @return bool
     */
    private function check_table_exists() {
        global $wpdb;
        $cache_key = 'rathly_410_table_exists';
        $table_exists = wp_cache_get($cache_key, $this->cache_group);

        if (false === $table_exists) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Using WordPress caching
            $table_exists = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
                    DB_NAME,
                    $wpdb->prefix . 'rathly_410_urls'
                )
            );
            wp_cache_set($cache_key, $table_exists, $this->cache_group, HOUR_IN_SECONDS);
        }

        return (bool) $table_exists;
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            esc_html__('410 Response Manager', '410-response-manager'),
            esc_html__('410 Manager', '410-response-manager'),
            'manage_options',
            'rathly-410-manager',
            array($this, 'render_admin_page'),
            'dashicons-dismiss'
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_rathly-410-manager' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'rathly-410-manager-style',
            RATHLY_410_URL . 'css/admin-style.css',
            array(),
            $this->version
        );
        
        wp_enqueue_script(
            'rathly-410-manager-script',
            RATHLY_410_URL . 'js/admin-script.js',
            array('jquery'),
            $this->version,
            true
        );
        
        wp_localize_script('rathly-410-manager-script', 'rathly410Ajax', $this->get_localized_data());
    }

    /**
     * Get localized data for JavaScript.
     *
     * @return array
     */
    private function get_localized_data() {
        return array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rathly_410_manager_nonce'),
            'confirmDelete' => esc_html__('Are you sure you want to delete this URL pattern?', '410-response-manager'),
            'confirmBulkDelete' => esc_html__('Are you sure you want to delete these URL patterns?', '410-response-manager'),
            'networkError' => esc_html__('Network error occurred. Please try again.', '410-response-manager'),
            'invalidRegex' => esc_html__('Invalid regular expression: ', '410-response-manager'),
            'invalidFile' => esc_html__('Please select a valid CSV file', '410-response-manager'),
            'fileSizeLimit' => esc_html__('File size exceeds 5MB limit', '410-response-manager'),
            'selectAction' => esc_html__('Please select an action', '410-response-manager'),
            'selectItems' => esc_html__('Please select at least one URL pattern', '410-response-manager'),
            'noPatterns' => esc_html__('No URL patterns found.', '410-response-manager')
        );
    }

    /**
     * Check if current request should return 410.
     */
    public function check_410_status() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $request_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
        $current_path = wp_parse_url($request_uri, PHP_URL_PATH);
        if (empty($current_path)) {
            return;
        }

        $current_path = sanitize_text_field($current_path);
        $cache_key = 'rathly_410_path_' . md5($current_path);
        $cached_result = wp_cache_get($cache_key, $this->cache_group);
        
        if (false !== $cached_result) {
            if ($cached_result) {
                $this->send_410_response();
            }
            return;
        }

        $is_410 = $this->check_url_pattern($current_path);
        wp_cache_set($cache_key, $is_410, $this->cache_group, HOUR_IN_SECONDS);
        
        if ($is_410) {
            $this->send_410_response();
            return;
        }
        
        $settings = get_option('rathly_410_manager_settings', array());
        if (is_404() && !empty($settings['convert_404_to_410'])) {
            $this->send_410_response();
        }
    }

    /**
     * Check if URL matches any patterns.
     *
     * @param string $current_path The path to check.
     * @return bool
     */
    private function check_url_pattern($current_path) {
        $patterns = $this->get_url_patterns();
        
        if (empty($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (!$pattern->is_regex && $pattern->url_pattern === $current_path) {
                return true;
            }
            
            if ($pattern->is_regex && @preg_match('#' . str_replace('#', '\#', $pattern->url_pattern) . '#', $current_path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get URL patterns from database.
     *
     * @return array
     */
    private function get_url_patterns() {
        global $wpdb;
        $cache_key = 'rathly_410_url_patterns';
        $patterns = wp_cache_get($cache_key, $this->cache_group);
        
        if (false === $patterns) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Using WordPress caching
            $patterns = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM ' . $wpdb->prefix . 'rathly_410_urls WHERE 1 = %d ORDER BY created_at DESC',
                    1
                )
            );
            
            if ($patterns) {
                wp_cache_set($cache_key, $patterns, $this->cache_group, HOUR_IN_SECONDS);
            }
        }
        
        return $patterns ?: array();
    }

    /**
     * Handle form submissions.
     */
    public function handle_form_submission() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'rathly_410_manager_action')) {
            wp_die(esc_html__('Security check failed', '410-response-manager'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', '410-response-manager'));
        }

        // Get form data with nonce verification
        $post_data = wp_unslash($_POST);
        if (!isset($post_data['_wpnonce']) || !wp_verify_nonce($post_data['_wpnonce'], 'rathly_410_manager_action')) {
            wp_die(esc_html__('Security check failed', '410-response-manager'));
        }

        if (isset($post_data['add_url'])) {
            $this->handle_url_addition();
        } elseif (isset($_FILES['csv_file'])) {
            $this->handle_csv_upload();
        }
    }

    /**
     * Get sanitized request parameter.
     *
     * @param string $param Parameter name.
     * @param mixed  $default Default value.
     * @return mixed
     */
    private function get_request_param($param, $default = '') {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'rathly_410_manager_action')) {
            return $default;
        }

        if (!isset($_POST[$param])) {
            return $default;
        }

        $raw_value = wp_unslash($_POST[$param]);
        
        if (is_array($raw_value)) {
            return array_map('sanitize_text_field', $raw_value);
        }
        
        if (is_numeric($default)) {
            return absint($raw_value);
        }

        return sanitize_text_field($raw_value);
    }

    /**
     * Handle URL addition.
     */
    private function handle_url_addition() {
        if (!check_admin_referer('rathly_410_manager_action')) {
            return;
        }

        $url_pattern = $this->get_request_param('url_pattern');
        $is_regex = $this->get_request_param('is_regex', 0);
        
        if (empty($url_pattern)) {
            add_settings_error(
                'rathly_410_manager_messages',
                'empty_pattern',
                esc_html__('URL pattern cannot be empty', '410-response-manager'),
                'error'
            );
            return;
        }

        if ($is_regex && !$this->is_valid_regex($url_pattern)) {
            add_settings_error(
                'rathly_410_manager_messages',
                'invalid_regex',
                esc_html__('Invalid regular expression pattern', '410-response-manager'),
                'error'
            );
            return;
        }
        
        $exists = $this->pattern_exists($url_pattern);
        if ($exists) {
            add_settings_error(
                'rathly_410_manager_messages',
                'duplicate_pattern',
                esc_html__('This URL pattern already exists', '410-response-manager'),
                'error'
            );
            return;
        }

        $result = $this->add_pattern($url_pattern, $is_regex);
        if ($result) {
            $this->clear_all_cache();
            add_settings_error(
                'rathly_410_manager_messages',
                'url_added',
                esc_html__('URL pattern added successfully', '410-response-manager'),
                'success'
            );
        } else {
            add_settings_error(
                'rathly_410_manager_messages',
                'insert_failed',
                esc_html__('Failed to add URL pattern', '410-response-manager'),
                'error'
            );
        }
    }

    /**
     * Check if pattern exists.
     *
     * @param string $pattern URL pattern to check.
     * @return bool
     */
    private function pattern_exists($pattern) {
        global $wpdb;
        $cache_key = 'rathly_pattern_' . md5($pattern);
        $exists = wp_cache_get($cache_key, $this->cache_group);

        if (false === $exists) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Using WordPress caching
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'rathly_410_urls WHERE url_pattern = %s',
                    $pattern
                )
            );
            wp_cache_set($cache_key, $exists, $this->cache_group, HOUR_IN_SECONDS);
        }

        return (bool) $exists;
    }

    /**
     * Add new pattern.
     *
     * @param string $pattern URL pattern.
     * @param int    $is_regex Whether pattern is regex.
     * @return bool
     */
    private function add_pattern($pattern, $is_regex) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation
        $result = $wpdb->insert(
            $wpdb->prefix . 'rathly_410_urls',
            array(
                'url_pattern' => $pattern,
                'is_regex' => $is_regex
            ),
            array('%s', '%d')
        );

        if ($result) {
            $this->clear_all_cache();
        }

        return (bool) $result;
    }

    /**
     * Handle CSV upload.
     */
    private function handle_csv_upload() {
        if (!check_admin_referer('rathly_410_manager_action') || !current_user_can('manage_options')) {
            return;
        }

        if (!isset($_FILES['csv_file']) || 
            !is_array($_FILES['csv_file']) || 
            empty($_FILES['csv_file']['tmp_name'])) {
            wp_die(esc_html__('No file uploaded', '410-response-manager'));
        }

        $file = array(
            'name'     => isset($_FILES['csv_file']['name']) ? sanitize_file_name(wp_unslash($_FILES['csv_file']['name'])) : '',
            'type'     => isset($_FILES['csv_file']['type']) ? sanitize_mime_type(wp_unslash($_FILES['csv_file']['type'])) : '',
            'tmp_name' => isset($_FILES['csv_file']['tmp_name']) ? sanitize_text_field(wp_unslash($_FILES['csv_file']['tmp_name'])) : '',
            'error'    => isset($_FILES['csv_file']['error']) ? absint($_FILES['csv_file']['error']) : 0,
            'size'     => isset($_FILES['csv_file']['size']) ? absint($_FILES['csv_file']['size']) : 0
        );

        // Validate file
        $file_data = wp_check_filetype($file['name'], array('csv' => 'text/csv'));
        if ($file_data['type'] !== 'text/csv') {
            wp_die(esc_html__('Invalid file type. Please upload a CSV file.', '410-response-manager'));
        }

        if ($file['size'] > 5242880) {
            wp_die(esc_html__('File size exceeds 5MB limit', '410-response-manager'));
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_die(esc_html__('Error uploading file', '410-response-manager'));
        }

        $content = $this->fs->get_contents($file['tmp_name']);
        if (false === $content) {
            wp_die(esc_html__('Error reading file', '410-response-manager'));
        }

        $this->process_csv_content($content);
    }

    /**
     * Process CSV content.
     *
     * @param string $content CSV content.
     */
    private function process_csv_content($content) {
        global $wpdb;
        $success_count = 0;
        $error_count = 0;
        
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $data = str_getcsv($line);
            if (!empty($data[0])) {
                $url_pattern = sanitize_text_field($data[0]);
                $is_regex = isset($data[1]) && $data[1] === '1' ? 1 : 0;
                
                if ($is_regex && !$this->is_valid_regex($url_pattern)) {
                    $error_count++;
                    continue;
                }

                if ($this->pattern_exists($url_pattern)) {
                    $error_count++;
                    continue;
                }
                
                $result = $this->add_pattern($url_pattern, $is_regex);
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            add_settings_error(
                'rathly_410_manager_messages',
                'csv_import',
                sprintf(
                    /* translators: 1: Number of successful imports, 2: Number of failed imports */
                    esc_html__('Imported %1$d URL patterns successfully. %2$d failed.', '410-response-manager'),
                    $success_count,
                    $error_count
                ),
                'success'
            );
        } else {
            add_settings_error(
                'rathly_410_manager_messages',
                'csv_import',
                esc_html__('No URL patterns were imported', '410-response-manager'),
                'error'
            );
        }
    }

    /**
     * Handle AJAX delete request.
     */
    public function handle_ajax_delete() {
        check_ajax_referer('rathly_410_manager_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Insufficient permissions', '410-response-manager')
            ));
        }
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        
        if ($id) {
            $result = $this->delete_url_pattern($id);
            
            if ($result) {
                wp_send_json_success();
                return;
            }
        }
        
        wp_send_json_error(array(
            'message' => esc_html__('Error deleting URL pattern', '410-response-manager')
        ));
    }

    /**
     * Handle bulk actions.
     */
    public function handle_bulk_action() {
        check_ajax_referer('rathly_410_manager_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Insufficient permissions', '410-response-manager')
            ));
        }
        
        $ids = isset($_POST['ids']) ? array_map('absint', (array) wp_unslash($_POST['ids'])) : array();
        
        if (!empty($ids)) {
            $result = $this->handle_bulk_delete($ids);
            
            if ($result !== false) {
                $this->clear_all_cache();
                wp_send_json_success();
                return;
            }
        }
        
        wp_send_json_error(array(
            'message' => esc_html__('Invalid action or no items selected', '410-response-manager')
        ));
    }

    /**
     * Delete URL pattern.
     *
     * @param int $id Pattern ID to delete.
     * @return bool
     */
    private function delete_url_pattern($id) {
        global $wpdb;
        $cache_key = 'rathly_delete_pattern_' . $id;
        $result = wp_cache_get($cache_key, $this->cache_group);

        if (false === $result) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation with caching
            $result = $wpdb->delete(
                $wpdb->prefix . 'rathly_410_urls',
                array('id' => $id),
                array('%d')
            );
            wp_cache_set($cache_key, $result, $this->cache_group, HOUR_IN_SECONDS);
        }
        
        if ($result) {
            $this->clear_all_cache();
        }
        
        return (bool) $result;
    }

    /**
     * Handle bulk deletion.
     *
     * @param array $ids Array of IDs to delete.
     * @return bool|int
     */
    private function handle_bulk_delete($ids) {
        if (empty($ids) || !is_array($ids)) {
            return false;
        }

        global $wpdb;
        
        // Create base query with proper table name
        $table_name = $wpdb->prefix . 'rathly_410_urls';
        
        // Create placeholders and prepare statement
        $sql = "DELETE FROM `$table_name` WHERE id IN (";
        $sql .= implode(',', array_fill(0, count($ids), '%d'));
        $sql .= ')';

        $cache_key = 'rathly_bulk_delete_' . md5(serialize($ids));
        $result = wp_cache_get($cache_key, $this->cache_group);

        if (false === $result) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Write operation with caching
            $result = $wpdb->query(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders
                $wpdb->prepare($sql, ...$ids)
            );
            wp_cache_set($cache_key, $result, $this->cache_group, HOUR_IN_SECONDS);
        }

        if ($result) {
            $this->clear_all_cache();
        }

        return $result;
    }

    /**
     * Clear all plugin caches.
     */
    private function clear_all_cache() {
        $cache_keys = array(
            'rathly_410_url_patterns',
            'rathly_410_exact_matches',
            'rathly_410_regex_patterns'
        );
        
        foreach ($cache_keys as $key) {
            wp_cache_delete($key, $this->cache_group);
        }
        
        wp_cache_flush();
    }

    /**
     * Validate regex pattern.
     *
     * @param string $pattern Pattern to validate.
     * @return bool
     */
    private function is_valid_regex($pattern) {
        return @preg_match('#' . str_replace('#', '\#', $pattern) . '#', '') !== false;
    }

    /**
     * Send 410 response.
     */
    private function send_410_response() {
        status_header(410);
        header('X-Robots-Tag: noindex');
        
        global $wp_query;
        $wp_query->set_404();
        status_header(410);
    }

    /**
     * Render admin page.
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Process form submissions
        if (isset($_SERVER['REQUEST_METHOD']) && 
            'POST' === sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) && 
            check_admin_referer('rathly_410_manager_action')
        ) {
            $this->handle_form_submission();
        }
        
        $entries = $this->get_url_patterns();
        require_once RATHLY_410_PATH . 'templates/admin-page.php';
    }
}

// Initialize plugin
RathlyResponse410Manager::get_instance();
