<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package HPCookieConsent
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Remove options
$options = [
    'hpcc_banner_position',
    'hpcc_banner_style',
    'hpcc_primary_color',
    'hpcc_text_color',
    'hpcc_bg_color',
    'hpcc_banner_title',
    'hpcc_banner_text',
    'hpcc_privacy_page',
    'hpcc_imprint_page',
    'hpcc_cookie_lifetime',
    'hpcc_gtm_id',
    'hpcc_ga_id',
    'hpcc_categories',
    'hpcc_db_version',
];

foreach ($options as $option) {
    delete_option($option);
}

// Remove database table
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hpcc_consent_log");
