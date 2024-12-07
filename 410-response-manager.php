<?php
/**
 * Plugin Name: 410 Response Manager
 * Plugin URI: https://rathly.com/plugins/410-response-manager/
 * Description: Manage 410 Gone responses with manual entries, regex patterns, and CSV import functionality.
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * Author: Harry Laurel
 * Author URI: https://rathly.com/about-us/harrylaurel/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 410-response-manager
 * Domain Path: /languages
 *
 * @package 410-response-manager
 * @author Harry Laurel
 * @copyright 2024 Rathly
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Response_410_Manager {
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
    private $version = '1.0.0';

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
    private $cache_group = '410_response_manager';

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
        // Initialize WP_Filesystem
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;
        $this->fs = $wp_filesystem;
        
        // Create languages directory
        $langs_dir = plugin_dir_path(__FILE__) . 'languages';
        if (!file_exists($langs_dir)) {
            wp_mkdir_p($langs_dir);
        }
        
        // Initialize hooks
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('template_redirect', array($this, 'check_410_status'), 1);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_delete_410_url', array($this, 'handle_ajax_delete'));
        add_action('wp_ajax_bulk_410_action', array($this, 'handle_bulk_action'));
        
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            '410-response-manager',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Activate plugin.
     */
    public function activate_plugin() {
        $this->create_table();
        
        add_option('410_response_manager_version', $this->version);
        add_option('410_response_manager_settings', array(
            'convert_404_to_410' => false
        ));
        
        flush_rewrite_rules();
    }

    /**
     * Create plugin table.
     */
    private function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}410_urls (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url_pattern varchar(255) NOT NULL,
            is_regex tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY url_pattern (url_pattern)
        ) $charset_collate";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            esc_html__('410 Response Manager', '410-response-manager'),
            esc_html__('410 Manager', '410-response-manager'),
            'manage_options',
            '410-manager',
            array($this, 'render_admin_page'),
            'dashicons-dismiss'
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting('410_response_manager_settings', '410_response_manager_settings');
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_410-manager' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            '410-response-manager-style',
            plugins_url('css/admin-style.css', __FILE__),
            array(),
            $this->version
        );
        
        wp_enqueue_script(
            '410-response-manager-script',
            plugins_url('js/admin-script.js', __FILE__),
            array('jquery'),
            $this->version,
            true
        );
        
        wp_localize_script('410-response-manager-script', 'response410Ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('410_response_manager_nonce')
        ));
    }

    /**
     * Check if current request should return 410.
     */
    public function check_410_status() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        // Sanitize and validate request URI
        $request_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
        $current_path = wp_parse_url($request_uri, PHP_URL_PATH);
        $current_path = sanitize_text_field($current_path);
        
        // Check cache
        $cache_key = '410_path_' . md5($current_path);
        $is_410 = wp_cache_get($cache_key, $this->cache_group);
        
        if (false === $is_410) {
            $is_410 = $this->check_url_pattern($current_path);
            wp_cache_set($cache_key, $is_410, $this->cache_group, 3600);
        }
        
        if ($is_410) {
            $this->send_410_response();
            return;
        }
        
        // Check 404 conversion setting
        if (is_404()) {
            $settings = get_option('410_response_manager_settings', array());
            if (!empty($settings['convert_404_to_410'])) {
                $this->send_410_response();
            }
        }
    }
    
    /**
     * Check if URL matches any patterns.
     *
     * @param string $current_path The path to check.
     * @return bool
     */
    private function check_url_pattern($current_path) {
        global $wpdb;
        
        // Check exact matches from cache
        $cache_key = '410_exact_matches';
        $exact_match = wp_cache_get($cache_key, $this->cache_group);
        
        if (false === $exact_match) {
            $exact_match = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}410_urls WHERE url_pattern = %s AND is_regex = 0",
                $current_path
            ));
            wp_cache_set($cache_key, $exact_match, $this->cache_group, 3600);
        }
        
        if ($exact_match) {
            return true;
        }
        
        // Check regex patterns from cache
        $patterns_key = '410_regex_patterns';
        $patterns = wp_cache_get($patterns_key, $this->cache_group);
        
        if (false === $patterns) {
            $patterns = $wpdb->get_col($wpdb->prepare(
                "SELECT url_pattern FROM {$wpdb->prefix}410_urls WHERE is_regex = %d",
                1
            ));
            wp_cache_set($patterns_key, $patterns, $this->cache_group, 3600);
        }
        
        foreach ($patterns as $pattern) {
            if (@preg_match('#' . str_replace('#', '\#', $pattern) . '#', $current_path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Send 410 response and use theme's 404 template.
     */
    private function send_410_response() {
        // Set 410 Gone status code
        status_header(410);
        
        // Set header for search engines
        header('X-Robots-Tag: noindex');
        
        // Let WordPress handle everything
        global $wp_query;
        $wp_query->set_404();
        status_header(410);
        return;
    }
    
    /**
     * Render admin page.
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Process form submission
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $nonce = isset($_POST['_wpnonce']) ? wp_unslash($_POST['_wpnonce']) : '';
            $request_method = wp_unslash($_SERVER['REQUEST_METHOD']);
            
            if ('POST' === $request_method && !empty($nonce)) {
                if (wp_verify_nonce($nonce, '410_response_manager_action')) {
                    $this->handle_form_submission();
                }
            }
        }
        
        $entries = $this->get_entries();
        include(plugin_dir_path(__FILE__) . 'templates/admin-page.php');
    }
    
    /**
     * Get entries from database with caching.
     *
     * @return array
     */
    private function get_entries() {
        // Get entries from cache
        $cache_key = '410_entries_list';
        $entries = wp_cache_get($cache_key, $this->cache_group);
        
        if (false === $entries) {
            global $wpdb;
            $entries = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}410_urls ORDER BY created_at DESC"
            );
            wp_cache_set($cache_key, $entries, $this->cache_group, 3600);
        }
        
        return $entries;
    }
    
    /**
     * Handle form submission.
     */
    private function handle_form_submission() {
        $nonce = isset($_POST['_wpnonce']) ? wp_unslash($_POST['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, '410_response_manager_action')) {
            return;
        }
        
        if (isset($_POST['add_url']) && isset($_POST['url_pattern'])) {
            $this->handle_url_addition();
        } elseif (isset($_FILES['csv_file'])) {
            $this->handle_csv_upload();
        }
    }
    
    /**
     * Handle URL addition.
     */
    private function handle_url_addition() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $url_pattern = isset($_POST['url_pattern']) ? sanitize_text_field(wp_unslash($_POST['url_pattern'])) : '';
        $is_regex = isset($_POST['is_regex']) ? 1 : 0;
        
        if (empty($url_pattern)) {
            return;
        }
        
        if ($is_regex && !$this->is_valid_regex($url_pattern)) {
            add_settings_error(
                '410_response_manager_messages',
                'invalid_regex',
                esc_html__('Invalid regular expression pattern', '410-response-manager'),
                'error'
            );
            return;
        }
        
        // Check for duplicates
        $cache_key = '410_url_' . md5($url_pattern);
        $exists = wp_cache_get($cache_key, $this->cache_group);
        
        if (false === $exists) {
            global $wpdb;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}410_urls WHERE url_pattern = %s",
                $url_pattern
            ));
            wp_cache_set($cache_key, $exists, $this->cache_group, 3600);
        }
        
        if ($exists) {
            add_settings_error(
                '410_response_manager_messages',
                'duplicate_pattern',
                esc_html__('This URL pattern already exists', '410-response-manager'),
                'error'
            );
            return;
        }
        
        // Add new pattern
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . '410_urls',
            array(
                'url_pattern' => $url_pattern,
                'is_regex' => $is_regex
            ),
            array('%s', '%d')
        );
        
        if ($result) {
            $this->clear_cache();
            add_settings_error(
                '410_response_manager_messages',
                'url_added',
                esc_html__('URL pattern added successfully', '410-response-manager'),
                'success'
            );
        }
    }

    /**
     * Handle CSV upload.
     */
    private function handle_csv_upload() {
        if (!isset($_FILES['csv_file']) || !current_user_can('manage_options')) {
            return;
        }
        
        $file = array_map('sanitize_text_field', wp_unslash($_FILES['csv_file']));
        
        // Basic validation
        if ($file['error'] !== UPLOAD_ERR_OK) {
            add_settings_error(
                '410_response_manager_messages',
                'file_upload',
                esc_html__('Error uploading file', '410-response-manager'),
                'error'
            );
            return;
        }
        
        if ($file['size'] > 5242880) { // 5MB limit
            add_settings_error(
                '410_response_manager_messages',
                'file_size',
                esc_html__('File size exceeds 5MB limit', '410-response-manager'),
                'error'
            );
            return;
        }
        
        // Read file content
        $content = $this->fs->get_contents($file['tmp_name']);
        if (false === $content) {
            add_settings_error(
                '410_response_manager_messages',
                'file_read',
                esc_html__('Error reading file', '410-response-manager'),
                'error'
            );
            return;
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
            $data = str_getcsv($line);
            if (!empty($data[0])) {
                $url_pattern = sanitize_text_field($data[0]);
                $is_regex = isset($data[1]) && $data[1] === '1' ? 1 : 0;
                
                if ($is_regex && !$this->is_valid_regex($url_pattern)) {
                    $error_count++;
                    continue;
                }
                
                $result = $wpdb->insert(
                    $wpdb->prefix . '410_urls',
                    array(
                        'url_pattern' => $url_pattern,
                        'is_regex' => $is_regex
                    ),
                    array('%s', '%d')
                );
                
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        $this->clear_cache();
        
        if ($success_count > 0) {
            add_settings_error(
                '410_response_manager_messages',
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
                '410_response_manager_messages',
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
        $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, '410_response_manager_nonce')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed', '410-response-manager')
            ));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Insufficient permissions', '410-response-manager')
            ));
        }
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        
        if ($id) {
            global $wpdb;
            $result = $wpdb->delete(
                $wpdb->prefix . '410_urls',
                array('id' => $id),
                array('%d')
            );
            
            if ($result) {
                $this->clear_cache();
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
        $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, '410_response_manager_nonce')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed', '410-response-manager')
            ));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Insufficient permissions', '410-response-manager')
            ));
        }
        
        $ids = isset($_POST['ids']) ? array_map('absint', (array) wp_unslash($_POST['ids'])) : array();
        
        if (!empty($ids)) {
            $result = $this->handle_bulk_delete($ids);
            
            if ($result !== false) {
                $this->clear_cache();
                wp_send_json_success();
                return;
            }
        }
        
        wp_send_json_error(array(
            'message' => esc_html__('Invalid action or no items selected', '410-response-manager')
        ));
    }

    /**
     * Handle bulk deletion.
     *
     * @param array $ids Array of IDs to delete.
     * @return bool|int
     */
    private function handle_bulk_delete($ids) {
        global $wpdb;
        
        // Create placeholders
        $placeholders = array_fill(0, count($ids), '%d');
        $placeholder_string = implode(',', $placeholders);
        
        // Execute delete query
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}410_urls WHERE id IN ($placeholder_string)",
                $ids
            )
        );
    }

    /**
     * Clear all plugin caches.
     */
    private function clear_cache() {
        $cache_keys = array(
            '410_entries_list',
            '410_exact_matches',
            '410_regex_patterns'
        );
        
        foreach ($cache_keys as $key) {
            wp_cache_delete($key, $this->cache_group);
        }
        
        // Clear all path caches
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
}

// Initialize plugin
Response_410_Manager::get_instance();