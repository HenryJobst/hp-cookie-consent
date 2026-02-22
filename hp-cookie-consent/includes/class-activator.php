<?php
/**
 * Plugin-Aktivierung
 *
 * @package HPCookieConsent
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class HPCC_Activator {

    public static function activate(): void {
        self::create_tables();
        self::set_defaults();
    }

    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'hpcc_consent_log';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            consent_id varchar(64) NOT NULL,
            ip_hash varchar(64) NOT NULL,
            user_agent_hash varchar(64) NOT NULL,
            categories text NOT NULL,
            consent_given tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY consent_id (consent_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('hpcc_db_version', HPCC_VERSION);
    }

    private static function set_defaults(): void {
        $defaults = [
            'hpcc_banner_position'    => 'bottom',
            'hpcc_banner_style'       => 'bar',
            'hpcc_primary_color'      => '#2563eb',
            'hpcc_text_color'         => '#1f2937',
            'hpcc_bg_color'           => '#ffffff',
            'hpcc_banner_title'       => __('Wir verwenden Cookies', 'hp-cookie-consent'),
            'hpcc_banner_text'        => __('Diese Website verwendet Cookies, um Ihnen die bestmögliche Erfahrung zu bieten. Sie können wählen, welche Cookie-Kategorien Sie zulassen möchten.', 'hp-cookie-consent'),
            'hpcc_privacy_page'       => '',
            'hpcc_imprint_page'       => '',
            'hpcc_cookie_lifetime'    => 365,
            'hpcc_gtm_id'            => '',
            'hpcc_ga_id'             => '',
            'hpcc_categories'         => [
                'necessary'  => [
                    'label'       => __('Notwendig', 'hp-cookie-consent'),
                    'description' => __('Notwendige Cookies ermöglichen grundlegende Funktionen und sind für das einwandfreie Funktionieren der Website erforderlich.', 'hp-cookie-consent'),
                    'required'    => true,
                    'enabled'     => true,
                ],
                'statistics' => [
                    'label'       => __('Statistik', 'hp-cookie-consent'),
                    'description' => __('Statistik-Cookies helfen uns zu verstehen, wie Besucher mit der Website interagieren, indem sie Informationen anonym sammeln und melden.', 'hp-cookie-consent'),
                    'required'    => false,
                    'enabled'     => false,
                ],
                'marketing'  => [
                    'label'       => __('Marketing', 'hp-cookie-consent'),
                    'description' => __('Marketing-Cookies werden verwendet, um Besuchern auf Websites zu folgen. Die Absicht ist, Anzeigen zu zeigen, die relevant und ansprechend für den einzelnen Benutzer sind.', 'hp-cookie-consent'),
                    'required'    => false,
                    'enabled'     => false,
                ],
                'external'   => [
                    'label'       => __('Externe Medien', 'hp-cookie-consent'),
                    'description' => __('Inhalte von Videoplattformen und Social-Media-Plattformen werden standardmäßig blockiert. Wenn Cookies externer Medien akzeptiert werden, bedarf der Zugriff auf diese Inhalte keiner manuellen Einwilligung mehr.', 'hp-cookie-consent'),
                    'required'    => false,
                    'enabled'     => false,
                ],
            ],
            'hpcc_cookie_details'     => [
                'necessary'  => [
                    ['name' => 'hpcc_consent',    'provider' => 'Eigene', 'purpose' => 'Speichert die Cookie-Einwilligung', 'duration' => '1 Jahr'],
                    ['name' => 'hpcc_consent_id', 'provider' => 'Eigene', 'purpose' => 'Eindeutige Consent-ID',            'duration' => '1 Jahr'],
                ],
                'statistics' => [
                    ['name' => '_ga / _gid',      'provider' => 'Google', 'purpose' => 'Google Analytics Tracking', 'duration' => '2 Jahre / 24h'],
                ],
                'marketing'  => [
                    ['name' => '_fbp',             'provider' => 'Facebook', 'purpose' => 'Facebook Pixel Tracking', 'duration' => '3 Monate'],
                ],
                'external'   => [
                    ['name' => 'VISITOR_INFO1_LIVE', 'provider' => 'YouTube', 'purpose' => 'YouTube Video-Player',  'duration' => '6 Monate'],
                ],
            ],
            'hpcc_custom_rules'       => [],
            'hpcc_reconsent_version'  => '1',
            'hpcc_reconsent_days'     => 0,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }
}
