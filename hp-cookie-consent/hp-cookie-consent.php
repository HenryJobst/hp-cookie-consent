<?php
/**
 * Plugin Name:       HP Cookie Consent
 * Plugin URI:        https://example.com/hp-cookie-consent
 * Description:       Einfaches, DSGVO- und CCPA-konformes Cookie-Consent-Plugin mit granularer Kategorisierung, anpassbarem Banner und Consent-Logging.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Henry Privat
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hp-cookie-consent
 * Domain Path:       /languages
 *
 * @package HPCookieConsent
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('HPCC_VERSION', '1.0.0');
define('HPCC_FILE', __FILE__);
define('HPCC_PATH', plugin_dir_path(__FILE__));
define('HPCC_URL', plugin_dir_url(__FILE__));
define('HPCC_BASENAME', plugin_basename(__FILE__));

require_once HPCC_PATH . 'includes/class-activator.php';
require_once HPCC_PATH . 'includes/class-admin.php';
require_once HPCC_PATH . 'includes/class-frontend.php';
require_once HPCC_PATH . 'includes/class-consent-logger.php';

register_activation_hook(__FILE__, ['HPCC_Activator', 'activate']);

add_action('plugins_loaded', function (): void {
    load_plugin_textdomain('hp-cookie-consent', false, dirname(HPCC_BASENAME) . '/languages');

    if (is_admin()) {
        $admin = new HPCC_Admin();
        $admin->init();
    }

    $frontend = new HPCC_Frontend();
    $frontend->init();

    $logger = new HPCC_Consent_Logger();
    $logger->init();
});
