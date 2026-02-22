<?php
/**
 * Admin-Einstellungsseite
 *
 * @package HPCookieConsent
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class HPCC_Admin {

    public function init(): void {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_csv_export']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu(): void {
        add_options_page(
            __('Cookie Consent', 'hp-cookie-consent'),
            __('Cookie Consent', 'hp-cookie-consent'),
            'manage_options',
            'hpcc-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'settings_page_hpcc-settings') {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style(
            'hpcc-admin',
            HPCC_URL . 'admin/css/admin.css',
            [],
            HPCC_VERSION
        );
        wp_enqueue_script(
            'hpcc-admin',
            HPCC_URL . 'admin/js/admin.js',
            ['jquery', 'wp-color-picker'],
            HPCC_VERSION,
            true
        );
    }

    public function register_settings(): void {
        $text_fields = [
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
        ];

        foreach ($text_fields as $field) {
            register_setting('hpcc_settings', $field, [
                'sanitize_callback' => 'sanitize_text_field',
            ]);
        }

        register_setting('hpcc_settings', 'hpcc_hide_for_admins', [
            'sanitize_callback' => function ($val): string {
                return $val ? '1' : '';
            },
        ]);

        register_setting('hpcc_settings', 'hpcc_reconsent_days', [
            'sanitize_callback' => function ($val): int {
                return max(0, (int) $val);
            },
        ]);

        register_setting('hpcc_settings', 'hpcc_reconsent_version', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('hpcc_settings', 'hpcc_cookie_details', [
            'sanitize_callback' => function ($input): array {
                if (!is_array($input)) return [];
                $sanitized = [];
                foreach ($input as $cat => $cookies) {
                    $cat = sanitize_key($cat);
                    $sanitized[$cat] = [];
                    if (!is_array($cookies)) continue;
                    foreach ($cookies as $cookie) {
                        if (empty($cookie['name'])) continue;
                        $sanitized[$cat][] = [
                            'name'     => sanitize_text_field($cookie['name'] ?? ''),
                            'provider' => sanitize_text_field($cookie['provider'] ?? ''),
                            'purpose'  => sanitize_text_field($cookie['purpose'] ?? ''),
                            'duration' => sanitize_text_field($cookie['duration'] ?? ''),
                        ];
                    }
                }
                return $sanitized;
            },
        ]);

        register_setting('hpcc_settings', 'hpcc_custom_rules', [
            'sanitize_callback' => function ($input): array {
                if (!is_array($input)) return [];
                $sanitized = [];
                foreach ($input as $rule) {
                    if (empty($rule['domain'])) continue;
                    $sanitized[] = [
                        'domain'   => sanitize_text_field($rule['domain'] ?? ''),
                        'category' => sanitize_key($rule['category'] ?? 'statistics'),
                    ];
                }
                return $sanitized;
            },
        ]);

        register_setting('hpcc_settings', 'hpcc_categories', [
            'sanitize_callback' => [$this, 'sanitize_categories'],
        ]);
    }

    public function sanitize_categories(mixed $input): array {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $key => $cat) {
            $key = sanitize_key($key);
            $sanitized[$key] = [
                'label'       => sanitize_text_field($cat['label'] ?? ''),
                'description' => sanitize_textarea_field($cat['description'] ?? ''),
                'required'    => !empty($cat['required']),
                'enabled'     => !empty($cat['enabled']),
            ];
        }

        return $sanitized;
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $categories = get_option('hpcc_categories', []);
        $pages = get_pages();
        ?>
        <div class="wrap hpcc-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('hpcc_settings'); ?>

                <div class="hpcc-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#hpcc-tab-general" class="nav-tab nav-tab-active"><?php esc_html_e('Allgemein', 'hp-cookie-consent'); ?></a>
                        <a href="#hpcc-tab-design" class="nav-tab"><?php esc_html_e('Design', 'hp-cookie-consent'); ?></a>
                        <a href="#hpcc-tab-categories" class="nav-tab"><?php esc_html_e('Kategorien', 'hp-cookie-consent'); ?></a>
                        <a href="#hpcc-tab-integration" class="nav-tab"><?php esc_html_e('Integration', 'hp-cookie-consent'); ?></a>
                        <a href="#hpcc-tab-log" class="nav-tab"><?php esc_html_e('Consent-Log', 'hp-cookie-consent'); ?></a>
                    </nav>

                    <!-- Allgemein -->
                    <div id="hpcc-tab-general" class="hpcc-tab-content active">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_banner_title"><?php esc_html_e('Banner-Überschrift', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="hpcc_banner_title" name="hpcc_banner_title"
                                           value="<?php echo esc_attr(get_option('hpcc_banner_title')); ?>"
                                           class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_banner_text"><?php esc_html_e('Banner-Text', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <textarea id="hpcc_banner_text" name="hpcc_banner_text"
                                              rows="4" class="large-text"><?php echo esc_textarea(get_option('hpcc_banner_text')); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_privacy_page"><?php esc_html_e('Datenschutzseite', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <select id="hpcc_privacy_page" name="hpcc_privacy_page">
                                        <option value=""><?php esc_html_e('— Keine —', 'hp-cookie-consent'); ?></option>
                                        <?php foreach ($pages as $page) : ?>
                                            <option value="<?php echo esc_attr((string) $page->ID); ?>"
                                                <?php selected(get_option('hpcc_privacy_page'), (string) $page->ID); ?>>
                                                <?php echo esc_html($page->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_imprint_page"><?php esc_html_e('Impressum-Seite', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <select id="hpcc_imprint_page" name="hpcc_imprint_page">
                                        <option value=""><?php esc_html_e('— Keine —', 'hp-cookie-consent'); ?></option>
                                        <?php foreach ($pages as $page) : ?>
                                            <option value="<?php echo esc_attr((string) $page->ID); ?>"
                                                <?php selected(get_option('hpcc_imprint_page'), (string) $page->ID); ?>>
                                                <?php echo esc_html($page->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_cookie_lifetime"><?php esc_html_e('Cookie-Laufzeit (Tage)', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="hpcc_cookie_lifetime" name="hpcc_cookie_lifetime"
                                           value="<?php echo esc_attr(get_option('hpcc_cookie_lifetime', '365')); ?>"
                                           min="1" max="730" class="small-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Admin-Ausblendung', 'hp-cookie-consent'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="hpcc_hide_for_admins" value="1"
                                               <?php checked(get_option('hpcc_hide_for_admins', '')); ?> />
                                        <?php esc_html_e('Banner für eingeloggte Administratoren ausblenden', 'hp-cookie-consent'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_reconsent_days"><?php esc_html_e('Re-Consent nach (Tage)', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="hpcc_reconsent_days" name="hpcc_reconsent_days"
                                           value="<?php echo esc_attr((string) get_option('hpcc_reconsent_days', '0')); ?>"
                                           min="0" max="730" class="small-text" />
                                    <p class="description"><?php esc_html_e('Banner nach X Tagen erneut anzeigen (0 = deaktiviert). Nützlich bei Änderungen der Cookie-Richtlinie.', 'hp-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_reconsent_version"><?php esc_html_e('Consent-Version', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="hpcc_reconsent_version" name="hpcc_reconsent_version"
                                           value="<?php echo esc_attr(get_option('hpcc_reconsent_version', '1')); ?>"
                                           class="small-text" />
                                    <p class="description"><?php esc_html_e('Erhöhen Sie diese Nummer, um alle Besucher erneut um Consent zu bitten.', 'hp-cookie-consent'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Design -->
                    <div id="hpcc-tab-design" class="hpcc-tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Banner-Position', 'hp-cookie-consent'); ?></th>
                                <td>
                                    <select name="hpcc_banner_position">
                                        <option value="bottom" <?php selected(get_option('hpcc_banner_position'), 'bottom'); ?>><?php esc_html_e('Unten', 'hp-cookie-consent'); ?></option>
                                        <option value="top" <?php selected(get_option('hpcc_banner_position'), 'top'); ?>><?php esc_html_e('Oben', 'hp-cookie-consent'); ?></option>
                                        <option value="center" <?php selected(get_option('hpcc_banner_position'), 'center'); ?>><?php esc_html_e('Mitte (Modal)', 'hp-cookie-consent'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Banner-Stil', 'hp-cookie-consent'); ?></th>
                                <td>
                                    <select name="hpcc_banner_style">
                                        <option value="bar" <?php selected(get_option('hpcc_banner_style'), 'bar'); ?>><?php esc_html_e('Leiste', 'hp-cookie-consent'); ?></option>
                                        <option value="box" <?php selected(get_option('hpcc_banner_style'), 'box'); ?>><?php esc_html_e('Box', 'hp-cookie-consent'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_primary_color"><?php esc_html_e('Primärfarbe', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="hpcc_primary_color" name="hpcc_primary_color"
                                           value="<?php echo esc_attr(get_option('hpcc_primary_color', '#2563eb')); ?>"
                                           class="hpcc-color-picker" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_text_color"><?php esc_html_e('Textfarbe', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="hpcc_text_color" name="hpcc_text_color"
                                           value="<?php echo esc_attr(get_option('hpcc_text_color', '#1f2937')); ?>"
                                           class="hpcc-color-picker" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_bg_color"><?php esc_html_e('Hintergrundfarbe', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="hpcc_bg_color" name="hpcc_bg_color"
                                           value="<?php echo esc_attr(get_option('hpcc_bg_color', '#ffffff')); ?>"
                                           class="hpcc-color-picker" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Vorschau', 'hp-cookie-consent'); ?></th>
                                <td>
                                    <div id="hpcc-admin-preview" style="position:relative;border:1px solid #c3c4c7;border-radius:8px;padding:20px;max-width:500px;font-family:sans-serif;font-size:14px;">
                                        <h4 style="margin:0 0 8px 0;" id="hpcc-preview-title"><?php echo esc_html(get_option('hpcc_banner_title', '')); ?></h4>
                                        <p style="margin:0 0 12px;opacity:0.85;font-size:13px;" id="hpcc-preview-text"><?php echo esc_html(function_exists('mb_substr') ? mb_substr((string) get_option('hpcc_banner_text', ''), 0, 80) : substr((string) get_option('hpcc_banner_text', ''), 0, 80)); ?>…</p>
                                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                            <span id="hpcc-preview-accept" style="padding:6px 14px;border-radius:4px;color:#fff;font-size:12px;font-weight:600;"><?php esc_html_e('Alle akzeptieren', 'hp-cookie-consent'); ?></span>
                                            <span id="hpcc-preview-reject" style="padding:6px 14px;border-radius:4px;font-size:12px;font-weight:600;border:2px solid currentColor;opacity:0.8;"><?php esc_html_e('Nur notwendige', 'hp-cookie-consent'); ?></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Kategorien -->
                    <div id="hpcc-tab-categories" class="hpcc-tab-content">
                        <p class="description"><?php esc_html_e('Konfigurieren Sie die Cookie-Kategorien, die den Besuchern angezeigt werden.', 'hp-cookie-consent'); ?></p>
                        <?php foreach ($categories as $key => $cat) : ?>
                            <div class="hpcc-category-card">
                                <h3><?php echo esc_html($cat['label']); ?>
                                    <?php if (!empty($cat['required'])) : ?>
                                        <span class="hpcc-badge"><?php esc_html_e('Pflicht', 'hp-cookie-consent'); ?></span>
                                    <?php endif; ?>
                                </h3>
                                <table class="form-table">
                                    <tr>
                                        <th><label><?php esc_html_e('Bezeichnung', 'hp-cookie-consent'); ?></label></th>
                                        <td>
                                            <input type="text"
                                                   name="hpcc_categories[<?php echo esc_attr($key); ?>][label]"
                                                   value="<?php echo esc_attr($cat['label']); ?>"
                                                   class="regular-text" />
                                            <input type="hidden"
                                                   name="hpcc_categories[<?php echo esc_attr($key); ?>][required]"
                                                   value="<?php echo $cat['required'] ? '1' : '0'; ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php esc_html_e('Beschreibung', 'hp-cookie-consent'); ?></label></th>
                                        <td>
                                            <textarea name="hpcc_categories[<?php echo esc_attr($key); ?>][description]"
                                                      rows="3" class="large-text"><?php echo esc_textarea($cat['description']); ?></textarea>
                                        </td>
                                    </tr>
                                    <?php if (empty($cat['required'])) : ?>
                                        <tr>
                                            <th><label><?php esc_html_e('Standard aktiviert', 'hp-cookie-consent'); ?></label></th>
                                            <td>
                                                <label>
                                                    <input type="checkbox"
                                                           name="hpcc_categories[<?php echo esc_attr($key); ?>][enabled]"
                                                           value="1"
                                                           <?php checked(!empty($cat['enabled'])); ?> />
                                                    <?php esc_html_e('Standardmäßig aktiviert (Opt-out statt Opt-in)', 'hp-cookie-consent'); ?>
                                                </label>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        <?php endforeach; ?>

                        <h3 style="margin-top:30px;"><?php esc_html_e('Cookie-Details pro Kategorie', 'hp-cookie-consent'); ?></h3>
                        <p class="description"><?php esc_html_e('Diese Cookies werden den Besuchern in einer aufklappbaren Tabelle pro Kategorie angezeigt.', 'hp-cookie-consent'); ?></p>
                        <?php
                        $cookie_details = get_option('hpcc_cookie_details', []);
                        foreach ($categories as $cat_key => $cat) :
                            $cat_cookies = $cookie_details[$cat_key] ?? [];
                        ?>
                            <div class="hpcc-category-card">
                                <h3><?php echo esc_html($cat['label']); ?> — <?php esc_html_e('Cookies', 'hp-cookie-consent'); ?></h3>
                                <table class="widefat hpcc-cookie-table" data-category="<?php echo esc_attr($cat_key); ?>">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Name', 'hp-cookie-consent'); ?></th>
                                            <th><?php esc_html_e('Anbieter', 'hp-cookie-consent'); ?></th>
                                            <th><?php esc_html_e('Zweck', 'hp-cookie-consent'); ?></th>
                                            <th><?php esc_html_e('Laufzeit', 'hp-cookie-consent'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cat_cookies as $ci => $cookie) : ?>
                                            <tr>
                                                <td><input type="text" name="hpcc_cookie_details[<?php echo esc_attr($cat_key); ?>][<?php echo (int) $ci; ?>][name]" value="<?php echo esc_attr($cookie['name']); ?>" class="regular-text" /></td>
                                                <td><input type="text" name="hpcc_cookie_details[<?php echo esc_attr($cat_key); ?>][<?php echo (int) $ci; ?>][provider]" value="<?php echo esc_attr($cookie['provider']); ?>" class="regular-text" /></td>
                                                <td><input type="text" name="hpcc_cookie_details[<?php echo esc_attr($cat_key); ?>][<?php echo (int) $ci; ?>][purpose]" value="<?php echo esc_attr($cookie['purpose']); ?>" class="regular-text" /></td>
                                                <td><input type="text" name="hpcc_cookie_details[<?php echo esc_attr($cat_key); ?>][<?php echo (int) $ci; ?>][duration]" value="<?php echo esc_attr($cookie['duration']); ?>" style="width:120px;" /></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="hpcc-new-cookie-row">
                                            <td><input type="text" name="hpcc_cookie_details[<?php echo esc_attr($cat_key); ?>][new][name]" value="" placeholder="<?php esc_attr_e('Cookie-Name', 'hp-cookie-consent'); ?>" class="regular-text" /></td>
                                            <td><input type="text" name="hpcc_cookie_details[<?php echo esc_attr($cat_key); ?>][new][provider]" value="" placeholder="<?php esc_attr_e('Anbieter', 'hp-cookie-consent'); ?>" class="regular-text" /></td>
                                            <td><input type="text" name="hpcc_cookie_details[<?php echo esc_attr($cat_key); ?>][new][purpose]" value="" placeholder="<?php esc_attr_e('Zweck', 'hp-cookie-consent'); ?>" class="regular-text" /></td>
                                            <td><input type="text" name="hpcc_cookie_details[<?php echo esc_attr($cat_key); ?>][new][duration]" value="" placeholder="<?php esc_attr_e('Laufzeit', 'hp-cookie-consent'); ?>" style="width:120px;" /></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Integration -->
                    <div id="hpcc-tab-integration" class="hpcc-tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_gtm_id"><?php esc_html_e('Google Tag Manager ID', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="hpcc_gtm_id" name="hpcc_gtm_id"
                                           value="<?php echo esc_attr(get_option('hpcc_gtm_id', '')); ?>"
                                           class="regular-text" placeholder="GTM-XXXXXXX" />
                                    <p class="description"><?php esc_html_e('GTM wird erst geladen, wenn der Nutzer der Statistik-Kategorie zugestimmt hat.', 'hp-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="hpcc_ga_id"><?php esc_html_e('Google Analytics ID', 'hp-cookie-consent'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="hpcc_ga_id" name="hpcc_ga_id"
                                           value="<?php echo esc_attr(get_option('hpcc_ga_id', '')); ?>"
                                           class="regular-text" placeholder="G-XXXXXXXXXX" />
                                    <p class="description"><?php esc_html_e('GA4 wird erst geladen, wenn der Nutzer der Statistik-Kategorie zugestimmt hat.', 'hp-cookie-consent'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Eigene Skript-Regeln', 'hp-cookie-consent'); ?></th>
                                <td>
                                    <p class="description" style="margin-bottom:12px;"><?php esc_html_e('Eigene Domain-zu-Kategorie-Zuordnungen für den Script-Blocker.', 'hp-cookie-consent'); ?></p>
                                    <?php $custom_rules = get_option('hpcc_custom_rules', []); ?>
                                    <table class="widefat" id="hpcc-custom-rules" style="max-width:600px;">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Domain / URL-Teil', 'hp-cookie-consent'); ?></th>
                                                <th><?php esc_html_e('Kategorie', 'hp-cookie-consent'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($custom_rules as $ri => $rule) : ?>
                                                <tr>
                                                    <td><input type="text" name="hpcc_custom_rules[<?php echo (int) $ri; ?>][domain]" value="<?php echo esc_attr($rule['domain']); ?>" class="regular-text" /></td>
                                                    <td>
                                                        <select name="hpcc_custom_rules[<?php echo (int) $ri; ?>][category]">
                                                            <?php foreach ($categories as $ck => $cv) : ?>
                                                                <option value="<?php echo esc_attr($ck); ?>" <?php selected($rule['category'], $ck); ?>><?php echo esc_html($cv['label']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr>
                                                <td><input type="text" name="hpcc_custom_rules[new][domain]" value="" placeholder="beispiel.com" class="regular-text" /></td>
                                                <td>
                                                    <select name="hpcc_custom_rules[new][category]">
                                                        <?php foreach ($categories as $ck => $cv) : ?>
                                                            <option value="<?php echo esc_attr($ck); ?>"><?php echo esc_html($cv['label']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Consent-Log -->
                    <div id="hpcc-tab-log" class="hpcc-tab-content">
                        <?php $this->render_consent_log(); ?>
                    </div>
                </div>

                <?php submit_button(__('Einstellungen speichern', 'hp-cookie-consent')); ?>
            </form>
        </div>
        <?php
    }

    private function render_consent_log(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hpcc_consent_log';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
        );

        if (!$table_exists) {
            echo '<p>' . esc_html__('Die Consent-Log-Tabelle existiert noch nicht.', 'hp-cookie-consent') . '</p>';
            return;
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $page = max(1, (int) ($_GET['log_page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        echo '<div style="display:flex;align-items:center;gap:16px;margin-bottom:12px;">';
        echo '<p style="margin:0;">' . sprintf(
            /* translators: %d: number of consent entries */
            esc_html__('Gesamt: %d Einwilligungen protokolliert', 'hp-cookie-consent'),
            $total
        ) . '</p>';

        if ($total > 0) {
            $export_url = wp_nonce_url(
                add_query_arg(['page' => 'hpcc-settings', 'hpcc_export_csv' => '1'], admin_url('options-general.php')),
                'hpcc_export_csv'
            );
            echo '<a href="' . esc_url($export_url) . '" class="button button-secondary">📥 ' .
                 esc_html__('CSV exportieren', 'hp-cookie-consent') . '</a>';
        }
        echo '</div>';

        if (empty($logs)) {
            echo '<p>' . esc_html__('Noch keine Einträge vorhanden.', 'hp-cookie-consent') . '</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Datum', 'hp-cookie-consent') . '</th>';
        echo '<th>' . esc_html__('Consent-ID', 'hp-cookie-consent') . '</th>';
        echo '<th>' . esc_html__('Kategorien', 'hp-cookie-consent') . '</th>';
        echo '<th>' . esc_html__('Status', 'hp-cookie-consent') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log->created_at) . '</td>';
            echo '<td><code>' . esc_html(substr($log->consent_id, 0, 12)) . '…</code></td>';
            echo '<td>' . esc_html($log->categories) . '</td>';
            echo '<td>' . ($log->consent_given ? '✅' : '❌') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        $total_pages = (int) ceil($total / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo wp_kses_post(paginate_links([
                'base'    => add_query_arg('log_page', '%#%'),
                'format'  => '',
                'current' => $page,
                'total'   => $total_pages,
            ]));
            echo '</div></div>';
        }
    }

    public function handle_csv_export(): void {
        if (empty($_GET['hpcc_export_csv'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('hpcc_export_csv');

        global $wpdb;
        $table_name = $wpdb->prefix . 'hpcc_consent_log';

        $logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC", ARRAY_A);

        $filename = 'consent-log-' . gmdate('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // BOM for Excel UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            __('ID', 'hp-cookie-consent'),
            __('Consent-ID', 'hp-cookie-consent'),
            __('IP-Hash', 'hp-cookie-consent'),
            __('User-Agent-Hash', 'hp-cookie-consent'),
            __('Kategorien', 'hp-cookie-consent'),
            __('Consent erteilt', 'hp-cookie-consent'),
            __('Datum', 'hp-cookie-consent'),
        ], ';');

        foreach ($logs as $row) {
            fputcsv($output, [
                $row['id'],
                $row['consent_id'],
                $row['ip_hash'],
                $row['user_agent_hash'],
                $row['categories'],
                $row['consent_given'] ? __('Ja', 'hp-cookie-consent') : __('Nein', 'hp-cookie-consent'),
                $row['created_at'],
            ], ';');
        }

        fclose($output);
        exit;
    }
}
