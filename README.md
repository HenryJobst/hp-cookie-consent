# HP Cookie Consent

Einfaches, leichtgewichtiges WordPress-Plugin für DSGVO- und CCPA-konforme Cookie-Einwilligung – ohne Shareware-Beschränkungen, externe Dienste oder Abo-Zwang.

---

## Funktionsübersicht

### 🍪 Cookie-Banner

- **3 Positionen:** Oben, Unten, Mitte (Modal mit Overlay)
- **2 Stile:** Leiste (volle Breite) oder Box (kompakt, rechts)
- **Frei konfigurierbare Farben:** Primärfarbe, Textfarbe, Hintergrundfarbe (mit WordPress Color-Picker)
- **Anpassbare Texte:** Überschrift und Beschreibungstext im Admin einstellbar
- **Responsive Design:** Optimiert für Desktop, Tablet und Smartphone
- **Animiert:** Sanfte Ein-/Ausblend-Animationen

### ✅ Consent-Optionen

Besucher haben drei klare Auswahlmöglichkeiten:

| Button | Verhalten |
|--------|-----------|
| **Alle akzeptieren** | Aktiviert alle Cookie-Kategorien |
| **Nur notwendige** | Aktiviert ausschließlich die Pflicht-Kategorie |
| **Einstellungen** | Öffnet die granulare Kategorie-Auswahl mit Toggle-Schaltern |

### 📂 Cookie-Kategorien

Vier vorkonfigurierte Kategorien mit individuellen Beschreibungen und Toggle-Schaltern:

| Kategorie | Standard | Beschreibung |
|-----------|----------|--------------|
| **Notwendig** | Immer aktiv (Pflicht) | Grundlegende Website-Funktionen, Session-Cookies |
| **Statistik** | Opt-in | Analytics, anonyme Nutzungsdaten |
| **Marketing** | Opt-in | Werbe-Tracking, Remarketing |
| **Externe Medien** | Opt-in | YouTube, Vimeo, Social-Media-Embeds |

Jede Kategorie kann im Admin individuell benannt und beschrieben werden. Optionale Kategorien lassen sich auf Opt-out umstellen (standardmäßig aktiviert).

### 📊 Consent-Logging (DSGVO-Nachweispflicht)

Jede Einwilligung wird in einer eigenen Datenbanktabelle protokolliert:

- **Consent-ID** – Eindeutige Kennung pro Besucher
- **IP-Hash** – SHA-256-Hash der IP-Adresse (mit WordPress-Salt, nicht rückverfolgbar)
- **User-Agent-Hash** – SHA-256-Hash des Browsers
- **Kategorien** – JSON der akzeptierten/abgelehnten Kategorien
- **Zeitstempel** – Datum und Uhrzeit der Einwilligung

Das Consent-Log ist im Admin unter dem Tab „Consent-Log" einsehbar (paginiert, 20 Einträge pro Seite).

### 🔗 Tracking-Integration

| Dienst | Einstellung | Verhalten |
|--------|-------------|-----------|
| **Google Tag Manager** | GTM-ID eingeben | Wird erst nach Zustimmung zur Kategorie „Statistik" geladen |
| **Google Analytics 4** | GA4-ID eingeben | Wird erst nach Zustimmung geladen, `anonymize_ip: true` ist aktiv |

Die Tracking-Skripte werden sowohl beim initialen Seitenaufruf (wenn Consent-Cookie vorhanden) als auch dynamisch nach Zustimmung geladen.

### 🔄 Revoke-Button

Nach erteilter Einwilligung erscheint ein kleiner 🍪-Button (unten links), über den Besucher ihre Cookie-Einstellungen jederzeit ändern können.

### 🔌 JavaScript-API

Eigene Skripte können den Consent-Status abfragen:

```javascript
// Prüfen, ob eine Kategorie zugestimmt wurde
if (HPCookieConsent.hasConsent('statistics')) {
    // Statistik-Skript laden
}

// Aktuellen Consent abrufen (Objekt oder null)
var consent = HPCookieConsent.getConsent();
// → { necessary: true, statistics: true, marketing: false, external: false }

// Banner programmatisch öffnen
HPCookieConsent.showBanner();

// Direkt die Einstellungen öffnen
HPCookieConsent.showSettings();
```

### 📡 Events & DataLayer

Bei jeder Consent-Änderung wird ein Custom Event ausgelöst:

```javascript
document.addEventListener('hpcc:consent', function(e) {
    console.log('Consent geändert:', e.detail);
});
```

Zusätzlich wird ein DataLayer-Event für den Google Tag Manager gepusht:

```javascript
// Automatisch bei Consent-Änderung:
// { event: 'hpcc_consent_update', hpcc_consent: { ... } }
```

### 🔒 Sicherheit

- Alle Eingaben werden serverseitig sanitized (`sanitize_text_field`, `sanitize_textarea_field`)
- Alle Ausgaben werden escaped (`esc_html`, `esc_attr`, `esc_url`, `esc_js`)
- AJAX-Requests werden mit WordPress-Nonces abgesichert
- Admin-Seiten prüfen `manage_options`-Capability
- IP-Adressen werden nur als gesalzener SHA-256-Hash gespeichert
- Cookies verwenden `SameSite=Lax` und `Secure`-Flags

### 🧹 Saubere Deinstallation

Bei Deinstallation über die WordPress-Admin-UI werden entfernt:
- Alle Plugin-Optionen aus `wp_options`
- Die Consent-Log-Datenbanktabelle

---

## Systemvoraussetzungen

- WordPress 6.0 oder höher
- PHP 8.0 oder höher
- HTTPS (für `Secure`-Cookie-Flag)

---

## Installation

### Option A: Upload über die WordPress-Admin-UI

1. ZIP-Archiv erstellen (im Projektverzeichnis):
   ```bash
   ./build.sh
   ```
2. Im WordPress-Admin navigieren zu **Plugins → Installieren → Plugin hochladen**
3. Die Datei `hp-cookie-consent-1.0.0.zip` auswählen und hochladen
4. Plugin aktivieren

### Option B: Manuell per FTP/SSH

1. Den Ordner `hp-cookie-consent/` nach `wp-content/plugins/` kopieren
2. Im WordPress-Admin unter **Plugins** das Plugin „HP Cookie Consent" aktivieren

### Nach der Aktivierung

1. Navigiere zu **Einstellungen → Cookie Consent**
2. Konfiguriere die fünf Tabs:

| Tab | Einstellungen |
|-----|---------------|
| **Allgemein** | Banner-Überschrift, Banner-Text, Datenschutz- und Impressum-Seite verknüpfen, Cookie-Laufzeit |
| **Design** | Position (oben/unten/modal), Stil (Leiste/Box), Farben |
| **Kategorien** | Bezeichnungen und Beschreibungen der vier Cookie-Kategorien anpassen |
| **Integration** | Google Tag Manager ID und/oder Google Analytics 4 ID eintragen |
| **Consent-Log** | Übersicht der protokollierten Einwilligungen (nur Ansicht) |

3. Einstellungen speichern – das Banner ist sofort auf der Website aktiv.

---

## Dateistruktur

```
hp-cookie-consent/
├── hp-cookie-consent.php          # Hauptdatei mit Plugin-Header
├── uninstall.php                  # Aufräumen bei Deinstallation
├── includes/
│   ├── class-activator.php        # DB-Tabelle & Standardwerte bei Aktivierung
│   ├── class-admin.php            # Einstellungsseite mit 5 Tabs
│   ├── class-consent-logger.php   # AJAX-Endpoint für Consent-Protokollierung
│   └── class-frontend.php         # Banner-Rendering & Script-Ausgabe
├── admin/
│   ├── css/admin.css              # Admin-Styles (Tabs, Kategorie-Karten)
│   └── js/admin.js                # Admin-JS (Tabs, Color-Picker)
└── public/
    ├── css/frontend.css            # Banner-Styles (responsive, animiert)
    └── js/frontend.js              # Banner-Logik, Consent-Management, API
```

---

## Cookies

Das Plugin setzt genau zwei Cookies:

| Cookie | Inhalt | Laufzeit |
|--------|--------|----------|
| `hpcc_consent` | JSON-Objekt mit den akzeptierten Kategorien | Konfigurierbar (Standard: 365 Tage) |
| `hpcc_consent_id` | Eindeutige ID zur Zuordnung im Consent-Log | Konfigurierbar (Standard: 365 Tage) |

---

## Lizenz

GPL v2 or later – [Lizenztext](https://www.gnu.org/licenses/gpl-2.0.html)
