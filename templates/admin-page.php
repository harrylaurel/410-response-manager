<?php
/**
 * Admin page template for 410 Response Manager
 *
 * @package 410-response-manager
 * @author Rathly
 * @copyright 2024 Rathly
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('rathly_410_manager_messages'); ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="#add-url" class="nav-tab nav-tab-active"><?php esc_html_e('Add URL', '410-response-manager'); ?></a>
        <a href="#csv-upload" class="nav-tab"><?php esc_html_e('CSV Upload', '410-response-manager'); ?></a>
        <a href="#url-list" class="nav-tab"><?php esc_html_e('Manage URLs', '410-response-manager'); ?></a>
        <a href="#settings" class="nav-tab"><?php esc_html_e('Settings', '410-response-manager'); ?></a>
    </h2>
    
    <!-- Add URL Section -->
    <div id="add-url" class="tab-content">
        <form method="post" action="">
            <?php wp_nonce_field('rathly_410_manager_action'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="url_pattern"><?php esc_html_e('URL Pattern', '410-response-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="url_pattern" 
                               id="url_pattern" 
                               class="regular-text" 
                               placeholder="/example-page"
                               required>
                        <p class="description">
                            <?php esc_html_e('Enter the URL path or pattern (e.g., /old-page or ^/products/.*)', '410-response-manager'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="is_regex"><?php esc_html_e('Pattern Type', '410-response-manager'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="is_regex" 
                                   id="is_regex" 
                                   value="1">
                            <?php esc_html_e('Use as Regular Expression', '410-response-manager'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Enable this to use regular expressions for pattern matching.', '410-response-manager'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" 
                       name="add_url" 
                       class="button button-primary" 
                       value="<?php esc_attr_e('Add URL', '410-response-manager'); ?>">
            </p>
        </form>
    </div>
    
    <!-- CSV Upload Section -->
    <div id="csv-upload" class="tab-content" style="display:none;">
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('rathly_410_manager_action'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="csv_file"><?php esc_html_e('CSV File', '410-response-manager'); ?></label>
                    </th>
                    <td>
                        <input type="file" 
                               name="csv_file" 
                               id="csv_file" 
                               accept=".csv" 
                               required>
                        <p class="description">
                            <?php 
                            echo wp_kses(
                                __('Upload a CSV file with URL patterns. Format: <code>url_pattern,is_regex</code>', '410-response-manager'),
                                array('code' => array())
                            ); 
                            ?>
                            <br>
                            <?php esc_html_e('Maximum file size: 5MB', '410-response-manager'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" 
                       name="upload_csv" 
                       class="button button-primary" 
                       value="<?php esc_attr_e('Upload CSV', '410-response-manager'); ?>">
            </p>
        </form>
        
        <div class="card">
            <h3><?php esc_html_e('CSV Template', '410-response-manager'); ?></h3>
            <p><?php esc_html_e('Example CSV content:', '410-response-manager'); ?></p>
            <pre><code>/old-page,0
/discontinued/.*,1
/2023-sale,0
^/archived/\d{4}/.*,1</code></pre>
            <p class="description">
                <?php esc_html_e('The second column should be 1 for regex patterns, 0 for exact matches.', '410-response-manager'); ?>
            </p>
        </div>
    </div>
    
    <!-- URL List Section -->
    <div id="url-list" class="tab-content" style="display:none;">
        <?php if (!empty($entries)) : ?>
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector" class="screen-reader-text">
                        <?php esc_html_e('Select bulk action', '410-response-manager'); ?>
                    </label>
                    <select name="bulk-action-selector" id="bulk-action-selector">
                        <option value=""><?php esc_html_e('Bulk Actions', '410-response-manager'); ?></option>
                        <option value="delete"><?php esc_html_e('Delete', '410-response-manager'); ?></option>
                    </select>
                    <button type="button" id="bulk-action-submit" class="button action">
                        <?php esc_html_e('Apply', '410-response-manager'); ?>
                    </button>
                </div>
                <div class="tablenav-pages one-page">
                    <span class="displaying-num">
                        <?php 
                        echo esc_html(
                            sprintf(
                                /* translators: %s: number of items */
                                _n('%s item', '%s items', count($entries), '410-response-manager'),
                                number_format_i18n(count($entries))
                            )
                        ); 
                        ?>
                    </span>
                </div>
                <br class="clear">
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th scope="col"><?php esc_html_e('URL Pattern', '410-response-manager'); ?></th>
                        <th scope="col"><?php esc_html_e('Type', '410-response-manager'); ?></th>
                        <th scope="col"><?php esc_html_e('Created', '410-response-manager'); ?></th>
                        <th scope="col"><?php esc_html_e('Actions', '410-response-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry) : ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" class="url-checkbox" value="<?php echo esc_attr($entry->id); ?>">
                            </th>
                            <td><?php echo esc_html($entry->url_pattern); ?></td>
                            <td>
                                <?php 
                                echo $entry->is_regex ? 
                                    esc_html__('Regex', '410-response-manager') : 
                                    esc_html__('Exact Match', '410-response-manager'); 
                                ?>
                            </td>
                            <td>
                                <?php 
                                echo esc_html(
                                    wp_date(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        strtotime($entry->created_at)
                                    )
                                ); 
                                ?>
                            </td>
                            <td>
                                <a href="#" class="delete-url" data-id="<?php echo esc_attr($entry->id); ?>">
                                    <?php esc_html_e('Delete', '410-response-manager'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No URL patterns found.', '410-response-manager'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Settings Section -->
    <div id="settings" class="tab-content" style="display:none;">
        <form method="post" action="options.php">
            <?php
                settings_fields('rathly_410_manager_settings');
                $settings = get_option('rathly_410_manager_settings', array());
            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('404 to 410 Conversion', '410-response-manager'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="rathly_410_manager_settings[convert_404_to_410]" 
                                   value="1"
                                   <?php checked(!empty($settings['convert_404_to_410'])); ?>>
                            <?php esc_html_e('Convert all 404 responses to 410', '410-response-manager'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Enable this option to automatically convert all 404 (Not Found) responses to 410 (Gone) responses.', '410-response-manager'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
</div>
