<?php
/**
 * Consent-Logging für DSGVO-Nachweispflicht
 *
 * @package HPCookieConsent
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class HPCC_Consent_Logger {

    public function init(): void {
        add_action('wp_ajax_hpcc_log_consent', [$this, 'log_consent']);
        add_action('wp_ajax_nopriv_hpcc_log_consent', [$this, 'log_consent']);
    }

    public function log_consent(): void {
        check_ajax_referer('hpcc_nonce', 'nonce');

        $consent_id = sanitize_text_field(wp_unslash($_POST['consent_id'] ?? ''));
        $categories = sanitize_text_field(wp_unslash($_POST['categories'] ?? ''));
        $consent_given = (int) ($_POST['consent_given'] ?? 0);

        if (empty($consent_id) || empty($categories)) {
            wp_send_json_error(__('Ungültige Daten.', 'hp-cookie-consent'), 400);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'hpcc_consent_log';

        $ip_raw = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        $ua_raw = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));

        $inserted = $wpdb->insert(
            $table_name,
            [
                'consent_id'      => $consent_id,
                'ip_hash'         => hash('sha256', $ip_raw . wp_salt()),
                'user_agent_hash' => hash('sha256', $ua_raw . wp_salt()),
                'categories'      => $categories,
                'consent_given'   => $consent_given,
            ],
            ['%s', '%s', '%s', '%s', '%d']
        );

        if ($inserted === false) {
            wp_send_json_error(__('Consent konnte nicht gespeichert werden.', 'hp-cookie-consent'), 500);
        }

        wp_send_json_success();
    }
}
