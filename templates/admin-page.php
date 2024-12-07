<?php
/**
 * Admin page template for 410 Response Manager
 *
 * @package 410-response-manager
 * @author Harry Laurel
 * @copyright 2024 Rathly
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('410_response_manager_messages'); ?>
    
    <div class="nav-tab-wrapper">
        <a href="#add-url" class="nav-tab nav-tab-active"><?php esc_html_e('Add URL', '410-response-manager'); ?></a>
        <a href="#csv-upload" class="nav-tab"><?php esc_html_e('CSV Upload', '410-response-manager'); ?></a>
        <a href="#url-list" class="nav-tab"><?php esc_html_e('Manage URLs', '410-response-manager'); ?></a>
        <a href="#settings" class="nav-tab"><?php esc_html_e('Settings', '410-response-manager'); ?></a>
    </div>
    
    <!-- Add URL Section -->
    <div class="tab-content" id="add-url">
        <form method="post" action="">
            <?php wp_nonce_field('410_response_manager_action', '_wpnonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="url_pattern"><?php esc_html_e('URL Pattern', '410-response-manager'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="url_pattern" id="url_pattern" class="regular-text" required>
                        <p class="description">
                            <?php esc_html_e('Enter the URL path or pattern (e.g., /old-page or ^/products/.*)', '410-response-manager'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Pattern Type', '410-response-manager'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_regex" id="is_regex" value="1">
                            <?php esc_html_e('Use as Regular Expression', '410-response-manager'); ?>
                        </label>
                        <span class="help-tip" title="<?php esc_attr_e('Enable this to use regular expressions for pattern matching', '410-response-manager'); ?>">?</span>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="add_url" class="button button-primary" value="<?php esc_attr_e('Add URL', '410-response-manager'); ?>">
            </p>
        </form>
    </div>
    
    <!-- CSV Upload Section -->
    <div class="tab-content" id="csv-upload">
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('410_response_manager_action', '_wpnonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="csv_file"><?php esc_html_e('CSV File', '410-response-manager'); ?></label>
                    </th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <p class="description">
                            <?php esc_html_e('Upload a CSV file with URL patterns. Format: url_pattern,is_regex', '410-response-manager'); ?>
                            <br>
                            <?php esc_html_e('Maximum file size: 5MB', '410-response-manager'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="upload_csv" class="button button-primary" value="<?php esc_attr_e('Upload CSV', '410-response-manager'); ?>">
            </p>
        </form>
        
        <!-- CSV Template Download -->
        <div class="card csvinfo-card">
            <h3><?php esc_html_e('CSV Template', '410-response-manager'); ?></h3>
            <p><?php esc_html_e('Download our CSV template to ensure proper formatting:', '410-response-manager'); ?></p>
            <a href="<?php echo esc_url(plugins_url('template.csv', __FILE__)); ?>" class="button" download>
                <?php esc_html_e('Download Template', '410-response-manager'); ?>
            </a>
            <div class="csv-format">
                <h4><?php esc_html_e('CSV Format:', '410-response-manager'); ?></h4>
                <code>url_pattern,is_regex</code>
                <p><?php esc_html_e('Examples:', '410-response-manager'); ?></p>
                <ul>
                    <li><code>/old-page,0</code> <?php esc_html_e('(exact match)', '410-response-manager'); ?></li>
                    <li><code>^/products/.*,1</code> <?php esc_html_e('(regex pattern)', '410-response-manager'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- URL List Section -->
    <div class="tab-content" id="url-list">
        <div class="bulk-actions">
            <select name="bulk-action-selector" id="bulk-action-selector">
                <option value=""><?php esc_html_e('Bulk Actions', '410-response-manager'); ?></option>
                <option value="delete"><?php esc_html_e('Delete', '410-response-manager'); ?></option>
            </select>
            <button type="button" id="bulk-action-submit" class="button">
                <?php esc_html_e('Apply', '410-response-manager'); ?>
            </button>
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
                <?php if (!empty($entries)): ?>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" class="url-checkbox" value="<?php echo esc_attr($entry->id); ?>">
                            </th>
                            <td><?php echo esc_html($entry->url_pattern); ?></td>
                            <td><?php echo $entry->is_regex ? esc_html__('Regex', '410-response-manager') : esc_html__('Exact Match', '410-response-manager'); ?></td>
                            <td><?php echo esc_html(get_date_from_gmt($entry->created_at, get_option('date_format') . ' ' . get_option('time_format'))); ?></td>
                            <td>
                                <a href="#" class="delete-url" data-id="<?php echo esc_attr($entry->id); ?>">
                                    <?php esc_html_e('Delete', '410-response-manager'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="5">
                            <?php esc_html_e('No URL patterns found.', '410-response-manager'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-2">
                    </td>
                    <th scope="col"><?php esc_html_e('URL Pattern', '410-response-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Type', '410-response-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Created', '410-response-manager'); ?></th>
                    <th scope="col"><?php esc_html_e('Actions', '410-response-manager'); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <!-- Settings Section -->
    <div class="tab-content" id="settings">
        <form method="post" action="options.php">
            <?php
                settings_fields('410_response_manager_settings');
                $settings = get_option('410_response_manager_settings', array());
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('404 to 410 Conversion', '410-response-manager'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="410_response_manager_settings[convert_404_to_410]" value="1"
                                <?php checked(isset($settings['convert_404_to_410']) && $settings['convert_404_to_410']); ?>>
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