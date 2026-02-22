<?php
/**
 * Script-Blocker: Blockiert Drittanbieter-Skripte und iframes bis Consent erteilt wird.
 *
 * Funktionsweise:
 * 1. Output Buffering fängt das gesamte HTML vor der Auslieferung ab
 * 2. Skript-Tags mit externen Domains werden von type="text/javascript" auf
 *    type="text/plain" umgeschrieben und erhalten ein data-hpcc-category Attribut
 * 3. iframes mit externen Domains werden durch Platzhalter ersetzt
 * 4. Das Frontend-JS aktiviert die Skripte/iframes nach Consent dynamisch
 *
 * @package HPCookieConsent
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

class HPCC_Script_Blocker {

    /**
     * Mapping: Domain-Pattern => Cookie-Kategorie
     */
    private array $domain_category_map = [];

    /**
     * Domains die niemals blockiert werden (eigene Site, WordPress-Core, CDNs für Basis-Funktionalität)
     */
    private array $whitelist = [];
    private array $consented_categories = [];

    public function init(): void {
        $this->build_domain_map();
        $this->build_whitelist();
        $this->consented_categories = $this->get_consented_categories();

        if (!$this->is_consent_given_for_all() && !is_admin() && !wp_doing_ajax() && !wp_doing_cron()) {
            add_action('template_redirect', [$this, 'start_output_buffer'], 0);
        }
    }

    /**
     * Baut das Domain-zu-Kategorie-Mapping auf.
     * Kann über den Filter 'hpcc_domain_category_map' erweitert werden.
     */
    private function build_domain_map(): void {
        $this->domain_category_map = [
            // Statistik
            'google-analytics.com'      => 'statistics',
            'googletagmanager.com'      => 'statistics',
            'analytics.google.com'      => 'statistics',
            'matomo'                    => 'statistics',
            'piwik'                     => 'statistics',
            'hotjar.com'                => 'statistics',
            'clarity.ms'                => 'statistics',
            'plausible.io'              => 'statistics',
            'stats.wp.com'              => 'statistics',

            // Marketing
            'facebook.net'              => 'marketing',
            'facebook.com/tr'           => 'marketing',
            'fbevents.js'               => 'marketing',
            'connect.facebook'          => 'marketing',
            'doubleclick.net'           => 'marketing',
            'googlesyndication.com'     => 'marketing',
            'googleadservices.com'      => 'marketing',
            'google.com/pagead'         => 'marketing',
            'adservice.google'          => 'marketing',
            'linkedin.com/insight'      => 'marketing',
            'snap.licdn.com'            => 'marketing',
            'ads-twitter.com'           => 'marketing',
            'analytics.tiktok.com'      => 'marketing',
            'pinterest.com/ct'          => 'marketing',
            'hubspot.com'               => 'marketing',
            'hs-scripts.com'            => 'marketing',
            'hs-analytics.net'          => 'marketing',

            // Externe Medien
            'youtube.com'               => 'external',
            'youtube-nocookie.com'      => 'external',
            'youtu.be'                  => 'external',
            'vimeo.com'                 => 'external',
            'player.vimeo.com'          => 'external',
            'dailymotion.com'           => 'external',
            'soundcloud.com'            => 'external',
            'spotify.com'               => 'external',
            'open.spotify.com'          => 'external',
            'maps.google'               => 'external',
            'maps.googleapis.com'       => 'external',
            'google.com/maps'           => 'external',
            'platform.twitter.com'      => 'external',
            'platform.instagram.com'    => 'external',
            'instagram.com/embed'       => 'external',
            'tiktok.com/embed'          => 'external',
        ];

        /**
         * Filter: Domain-Kategorie-Mapping erweitern.
         *
         * @param array $map Domain-Pattern => Kategorie
         */
        $this->domain_category_map = apply_filters('hpcc_domain_category_map', $this->domain_category_map);

        // Custom Rules aus den Admin-Einstellungen laden
        $custom_rules = get_option('hpcc_custom_rules', []);
        foreach ($custom_rules as $rule) {
            if (!empty($rule['domain']) && !empty($rule['category'])) {
                $this->domain_category_map[$rule['domain']] = $rule['category'];
            }
        }
    }

    /**
     * Baut die Whitelist auf – diese Domains werden nie blockiert.
     */
    private function build_whitelist(): void {
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';

        $this->whitelist = [
            $site_host,
            'wp.com/wp-content',
            'cdn.jsdelivr.net',
            'cdnjs.cloudflare.com',
            'unpkg.com',
            'ajax.googleapis.com',
            'fonts.googleapis.com',
            'fonts.gstatic.com',
        ];

        /**
         * Filter: Whitelist erweitern.
         *
         * @param array  $whitelist Domain-Patterns
         * @param string $site_host Hostname der eigenen Seite
         */
        $this->whitelist = apply_filters('hpcc_script_whitelist', $this->whitelist, $site_host);
    }

    /**
     * Prüft ob bereits für alle Kategorien Consent vorliegt (Cookie serverseitig lesen).
     */
    private function is_consent_given_for_all(): bool {
        if (empty($this->consented_categories)) {
            return false;
        }

        $categories = get_option('hpcc_categories', []);
        foreach ($categories as $key => $cat) {
            if (empty($cat['required']) && empty($this->consented_categories[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Liest die aktuell zugestimmten Kategorien aus dem Consent-Cookie.
     */
    private function get_consented_categories(): array {
        if (empty($_COOKIE['hpcc_consent'])) {
            return [];
        }

        $consent = json_decode(wp_unslash($_COOKIE['hpcc_consent']), true);
        if (!is_array($consent)) {
            return [];
        }

        // Support both formats:
        // legacy: {"statistics":true,...}
        // current: {"categories":{"statistics":true,...},"timestamp":...,"version":"..."}
        if (isset($consent['categories']) && is_array($consent['categories'])) {
            return $consent['categories'];
        }

        return $consent;
    }

    /**
     * Startet den Output Buffer.
     */
    public function start_output_buffer(): void {
        ob_start([$this, 'process_output']);
    }

    /**
     * Verarbeitet den gesamten HTML-Output.
     */
    public function process_output(string $html): string {
        if (empty($html) || strpos($html, '<head') === false) {
            return $html;
        }

        $html = $this->block_scripts($html);
        $html = $this->block_iframes($html);

        return $html;
    }

    /**
     * Blockiert externe Script-Tags.
     * Ändert type="text/javascript" zu type="text/plain" und fügt data-hpcc-category hinzu.
     */
    private function block_scripts(string $html): string {
        return preg_replace_callback(
            '/<script\b([^>]*)>(.*?)<\/script>/is',
            function (array $matches): string {
                $attributes = $matches[1];
                $content = $matches[2];

                // Bereits blockiert?
                if (strpos($attributes, 'data-hpcc-category') !== false) {
                    return $matches[0];
                }

                // Eigenes Plugin-Skript nicht blockieren
                if (strpos($attributes, 'id="hpcc-frontend-js"') !== false || strpos($attributes, "id='hpcc-frontend-js'") !== false) {
                    return $matches[0];
                }

                // src extrahieren
                $src = '';
                if (preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attributes, $src_match)) {
                    $src = $src_match[1];
                }

                // Inline-Script: Inhalt prüfen
                $check_string = $src ?: $content;

                if (empty($check_string)) {
                    return $matches[0];
                }

                // Whitelist prüfen
                if ($this->is_whitelisted($check_string)) {
                    return $matches[0];
                }

                // Kategorie ermitteln
                $category = $this->get_category($check_string);
                if (!$category) {
                    return $matches[0];
                }

                // Bereits zugestimmte Kategorie nicht blockieren
                if (!empty($this->consented_categories[$category])) {
                    return $matches[0];
                }

                // Script blockieren: type umschreiben
                $new_attributes = $attributes;

                // Bestehenden type entfernen
                $new_attributes = preg_replace('/\btype=["\'][^"\']*["\']/i', '', $new_attributes);

                // Neuen type und Kategorie setzen
                $new_attributes .= ' type="text/plain" data-hpcc-category="' . esc_attr($category) . '"';

                // Wenn src vorhanden: data-hpcc-src setzen und src entfernen (verhindert Laden)
                if ($src) {
                    $new_attributes = preg_replace('/\bsrc=["\']([^"\']+)["\']/i', 'data-hpcc-src="$1"', $new_attributes);
                }

                return '<script' . $new_attributes . '>' . $content . '</script>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Blockiert externe iframes und ersetzt sie durch Platzhalter.
     */
    private function block_iframes(string $html): string {
        return preg_replace_callback(
            '/<iframe\b([^>]*)(?:\/>|>(.*?)<\/iframe>)/is',
            function (array $matches): string {
                $attributes = $matches[1];

                // Bereits blockiert?
                if (strpos($attributes, 'data-hpcc-category') !== false) {
                    return $matches[0];
                }

                // src extrahieren
                if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $attributes, $src_match)) {
                    return $matches[0];
                }

                $src = $src_match[1];

                // Whitelist prüfen
                if ($this->is_whitelisted($src)) {
                    return $matches[0];
                }

                // Kategorie ermitteln
                $category = $this->get_category($src);
                if (!$category) {
                    return $matches[0];
                }

                // Bereits zugestimmte Kategorie nicht blockieren
                if (!empty($this->consented_categories[$category])) {
                    return $matches[0];
                }

                // Dimensionen beibehalten
                $width = '100%';
                $height = '400px';
                if (preg_match('/\bwidth=["\']?(\d+)/i', $attributes, $w)) {
                    $width = $w[1] . 'px';
                }
                if (preg_match('/\bheight=["\']?(\d+)/i', $attributes, $h)) {
                    $height = $h[1] . 'px';
                }

                $categories = get_option('hpcc_categories', []);
                $cat_label = $categories[$category]['label'] ?? $category;

                // Platzhalter mit Original-iframe als data-Attribut
                $escaped_original = esc_attr($matches[0]);
                $placeholder_text = sprintf(
                    /* translators: %s: category name */
                    __('Dieser Inhalt wird blockiert. Bitte akzeptieren Sie die Kategorie „%s" in den Cookie-Einstellungen, um diesen Inhalt anzuzeigen.', 'hp-cookie-consent'),
                    $cat_label
                );

                return '<div class="hpcc-iframe-placeholder" data-hpcc-category="' . esc_attr($category) . '" ' .
                    'data-hpcc-iframe="' . $escaped_original . '" ' .
                    'style="width:' . esc_attr($width) . ';height:' . esc_attr($height) . ';display:flex;align-items:center;justify-content:center;' .
                    'background:#f3f4f6;border:2px dashed #d1d5db;border-radius:8px;padding:20px;text-align:center;font-family:sans-serif;font-size:14px;color:#6b7280;">' .
                    '<div>' .
                    '<div style="font-size:32px;margin-bottom:12px;">🔒</div>' .
                    '<p style="margin:0 0 12px 0;">' . esc_html($placeholder_text) . '</p>' .
                    '<button class="hpcc-iframe-accept-btn" data-hpcc-category="' . esc_attr($category) . '" ' .
                    'style="padding:8px 20px;border:none;border-radius:6px;background:var(--hpcc-primary,#2563eb);color:#fff;font-size:14px;font-weight:600;cursor:pointer;">' .
                    esc_html__('Akzeptieren & laden', 'hp-cookie-consent') .
                    '</button>' .
                    '</div></div>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Prüft ob eine URL/String auf der Whitelist steht.
     */
    private function is_whitelisted(string $check): bool {
        foreach ($this->whitelist as $pattern) {
            if (!empty($pattern) && stripos($check, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ermittelt die Cookie-Kategorie für eine URL/String.
     */
    private function get_category(string $check): ?string {
        foreach ($this->domain_category_map as $pattern => $category) {
            if (stripos($check, $pattern) !== false) {
                return $category;
            }
        }
        return null;
    }
}
