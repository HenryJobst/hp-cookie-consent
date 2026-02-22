# HP Cookie Consent

Deutsch: [DE](#deutsch) | English: [EN](#english)

## Deutsch

Leichtgewichtiges WordPress-Plugin fuer DSGVO- und CCPA-konforme Cookie-Einwilligung mit granularen Kategorien, Script-Blocker und Consent-Logging.

### Funktionen

- Cookie-Banner mit 3 Positionen: oben, unten, modal (center)
- 2 Designvarianten: bar oder box
- Voll konfigurierbare Texte und Farben (WordPress Color Picker)
- Granulare Kategorien (notwendig, statistik, marketing, externe medien)
- Cookie-Details pro Kategorie (ausklappbare Tabelle im Banner)
- Re-Consent per Version und/oder Zeitintervall (Tage)
- Script- und iframe-Blocker mit Kategorie-Freigabe
- Eigene Domain-Regeln fuer den Script-Blocker
- Consent-Logging mit CSV-Export im Admin
- JavaScript API und Consent-Event (`hpcc:consent`)

### Voraussetzungen

- WordPress >= 6.0
- PHP >= 8.0
- HTTPS empfohlen (Secure-Cookies)

### Installation

#### Option A: ZIP-Upload im WordPress-Admin

1. ZIP erstellen:

```bash
./build.sh
```

2. In WordPress: `Plugins -> Installieren -> Plugin hochladen`
3. Datei `hp-cookie-consent-<version>.zip` auswaehlen
4. Plugin aktivieren

#### Option B: Manuell per FTP/SSH

1. Ordner `hp-cookie-consent/` nach `wp-content/plugins/` kopieren
2. Plugin im WordPress-Admin aktivieren

### Konfiguration

Pfad: `Einstellungen -> Cookie Consent`

- Allgemein: Banner-Text, Seiten-Links, Laufzeit, Re-Consent
- Design: Position, Stil, Farben + Live-Vorschau
- Kategorien: Kategorien konfigurieren + Cookie-Details pflegen
- Integration: GTM/GA IDs + eigene Script-Blocker-Regeln
- Consent-Log: Einwilligungen einsehen und als CSV exportieren

### JavaScript API

```javascript
if (HPCookieConsent.hasConsent('statistics')) {
  // Statistik-Skripte laden
}

const consent = HPCookieConsent.getConsent();
HPCookieConsent.showBanner();
HPCookieConsent.showSettings();
```

### Events

```javascript
document.addEventListener('hpcc:consent', function (e) {
  console.log(e.detail);
});
```

### Cookies

- `hpcc_consent`: Consent-Daten inkl. Kategorien, Zeitstempel, Version
- `hpcc_consent_id`: Eindeutige Consent-ID fuer Logging

### Deinstallation

Bei Deinstallation ueber WordPress werden:

- Plugin-Optionen aus `wp_options` entfernt
- Tabelle `${wp_prefix}hpcc_consent_log` geloescht

### Projektstruktur

```text
hp-cookie-consent/
├── hp-cookie-consent.php
├── uninstall.php
├── includes/
│   ├── class-activator.php
│   ├── class-admin.php
│   ├── class-consent-logger.php
│   ├── class-frontend.php
│   └── class-script-blocker.php
├── admin/
│   ├── css/admin.css
│   └── js/admin.js
└── public/
    ├── css/frontend.css
    └── js/frontend.js
```

---

## English

Lightweight WordPress plugin for GDPR/CCPA-compliant cookie consent with granular categories, script blocking, and consent logging.

### Features

- Cookie banner with 3 positions: top, bottom, center modal
- 2 layout styles: bar or box
- Fully configurable copy and colors (WordPress color picker)
- Granular categories (necessary, statistics, marketing, external media)
- Per-category cookie details (expandable table in banner)
- Re-consent by version and/or time window (days)
- Script and iframe blocker with category-based unblocking
- Custom domain-to-category rules for script blocking
- Consent logging with CSV export in admin
- JavaScript API and consent change event (`hpcc:consent`)

### Requirements

- WordPress >= 6.0
- PHP >= 8.0
- HTTPS recommended (secure cookies)

### Installation

#### Option A: Upload ZIP in WordPress Admin

1. Build package:

```bash
./build.sh
```

2. In WordPress: `Plugins -> Add New -> Upload Plugin`
3. Select `hp-cookie-consent-<version>.zip`
4. Activate plugin

#### Option B: Manual (FTP/SSH)

1. Copy `hp-cookie-consent/` into `wp-content/plugins/`
2. Activate plugin in WordPress admin

### Configuration

Path: `Settings -> Cookie Consent`

- General: banner text, legal links, cookie lifetime, re-consent
- Design: position, style, colors + live preview
- Categories: category setup + cookie details
- Integration: GTM/GA IDs + custom script-blocker rules
- Consent Log: inspect consent entries and export CSV

### JavaScript API

```javascript
if (HPCookieConsent.hasConsent('statistics')) {
  // Load analytics scripts
}

const consent = HPCookieConsent.getConsent();
HPCookieConsent.showBanner();
HPCookieConsent.showSettings();
```

### Events

```javascript
document.addEventListener('hpcc:consent', function (e) {
  console.log(e.detail);
});
```

### Cookies

- `hpcc_consent`: Consent payload including categories, timestamp, version
- `hpcc_consent_id`: Unique consent identifier for logging

### Uninstall

When uninstalling via WordPress admin, the plugin removes:

- Plugin options from `wp_options`
- `${wp_prefix}hpcc_consent_log` table

### Project Structure

```text
hp-cookie-consent/
├── hp-cookie-consent.php
├── uninstall.php
├── includes/
│   ├── class-activator.php
│   ├── class-admin.php
│   ├── class-consent-logger.php
│   ├── class-frontend.php
│   └── class-script-blocker.php
├── admin/
│   ├── css/admin.css
│   └── js/admin.js
└── public/
    ├── css/frontend.css
    └── js/frontend.js
```

## License

MIT License. See `/Users/henryprivat/Documents/gitrepos/hp-cookie-consent/LICENSE`.
