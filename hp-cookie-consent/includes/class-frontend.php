<?php
/**
 * Frontend: Banner-Rendering und Script-Ausgabe
 *
 * @package HPCookieConsent
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class HPCC_Frontend {

    public function init(): void {
        if ($this->should_hide_for_admin()) {
            return;
        }
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_banner']);
        add_action('wp_head', [$this, 'output_tracking_scripts'], 1);
    }

    private function should_hide_for_admin(): bool {
        return get_option('hpcc_hide_for_admins', '') && current_user_can('manage_options');
    }

    public function enqueue_assets(): void {
        wp_enqueue_style(
            'hpcc-frontend',
            HPCC_URL . 'public/css/frontend.css',
            [],
            HPCC_VERSION
        );
        wp_enqueue_script(
            'hpcc-frontend',
            HPCC_URL . 'public/js/frontend.js',
            [],
            HPCC_VERSION,
            true
        );

        $categories = get_option('hpcc_categories', []);
        $cat_data = [];
        foreach ($categories as $key => $cat) {
            $cat_data[$key] = [
                'label'       => $cat['label'],
                'description' => $cat['description'],
                'required'    => (bool) $cat['required'],
                'enabled'     => (bool) $cat['enabled'],
            ];
        }

        $privacy_page_id = get_option('hpcc_privacy_page', '');
        $imprint_page_id = get_option('hpcc_imprint_page', '');

        wp_localize_script('hpcc-frontend', 'hpccConfig', [
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('hpcc_nonce'),
            'categories'     => $cat_data,
            'cookieLifetime' => (int) get_option('hpcc_cookie_lifetime', 365),
            'position'       => get_option('hpcc_banner_position', 'bottom'),
            'style'          => get_option('hpcc_banner_style', 'bar'),
            'primaryColor'   => get_option('hpcc_primary_color', '#2563eb'),
            'textColor'      => get_option('hpcc_text_color', '#1f2937'),
            'bgColor'        => get_option('hpcc_bg_color', '#ffffff'),
            'title'          => get_option('hpcc_banner_title', ''),
            'text'           => get_option('hpcc_banner_text', ''),
            'privacyUrl'     => $privacy_page_id ? get_permalink((int) $privacy_page_id) : '',
            'imprintUrl'     => $imprint_page_id ? get_permalink((int) $imprint_page_id) : '',
            'gtmId'          => get_option('hpcc_gtm_id', ''),
            'gaId'           => get_option('hpcc_ga_id', ''),
            'i18n'           => [
                'acceptAll'    => __('Alle akzeptieren', 'hp-cookie-consent'),
                'acceptSelected' => __('Auswahl bestätigen', 'hp-cookie-consent'),
                'rejectAll'    => __('Nur notwendige', 'hp-cookie-consent'),
                'settings'     => __('Einstellungen', 'hp-cookie-consent'),
                'privacy'      => __('Datenschutz', 'hp-cookie-consent'),
                'imprint'      => __('Impressum', 'hp-cookie-consent'),
                'required'     => __('(erforderlich)', 'hp-cookie-consent'),
                'save'         => __('Speichern', 'hp-cookie-consent'),
                'back'         => __('Zurück', 'hp-cookie-consent'),
            ],
        ]);
    }

    public function render_banner(): void {
        echo '<div id="hpcc-cookie-consent"></div>';
        echo '<button id="hpcc-revoke-btn" class="hpcc-revoke-btn" aria-label="' .
             esc_attr__('Cookie-Einstellungen', 'hp-cookie-consent') . '" title="' .
             esc_attr__('Cookie-Einstellungen ändern', 'hp-cookie-consent') . '" style="display:none;">🍪</button>';
    }

    public function output_tracking_scripts(): void {
        $gtm_id = get_option('hpcc_gtm_id', '');
        $ga_id = get_option('hpcc_ga_id', '');

        if (empty($gtm_id) && empty($ga_id)) {
            return;
        }
        ?>
        <script>
        (function() {
            function getCookie(name) {
                var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? decodeURIComponent(match[2]) : null;
            }

            var consent = getCookie('hpcc_consent');
            if (!consent) return;

            try {
                var categories = JSON.parse(consent);
            } catch(e) {
                return;
            }

            if (!categories.statistics) return;

            <?php if (!empty($gtm_id)) : ?>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');
            <?php endif; ?>

            <?php if (!empty($ga_id)) : ?>
            var s = document.createElement('script');
            s.async = true;
            s.src = 'https://www.googletagmanager.com/gtag/js?id=<?php echo esc_js($ga_id); ?>';
            document.head.appendChild(s);
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo esc_js($ga_id); ?>', { anonymize_ip: true });
            <?php endif; ?>
        })();
        </script>
        <?php
    }
}
