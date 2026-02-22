#!/usr/bin/env bash
#
# Build-Script: Erzeugt ein ZIP-Archiv des HP Cookie Consent Plugins
# für den Upload über die WordPress Admin-UI (Plugins → Installieren → Plugin hochladen).
#
# Nutzung: ./build.sh

set -euo pipefail

PLUGIN_DIR="hp-cookie-consent"
VERSION=$(grep "^ \* Version:" "${PLUGIN_DIR}/hp-cookie-consent.php" | sed 's/.*Version:[[:space:]]*//')
OUTPUT="hp-cookie-consent-${VERSION}.zip"

cd "$(dirname "$0")"

if [ ! -d "$PLUGIN_DIR" ]; then
    echo "❌ Plugin-Verzeichnis '${PLUGIN_DIR}' nicht gefunden."
    exit 1
fi

rm -f "$OUTPUT"

zip -r "$OUTPUT" "$PLUGIN_DIR" \
    -x "${PLUGIN_DIR}/.*" \
    -x "${PLUGIN_DIR}/__MACOSX/*" \
    -x "${PLUGIN_DIR}/.DS_Store" \
    -x "${PLUGIN_DIR}/node_modules/*" \
    -x "${PLUGIN_DIR}/.git/*"

echo "✅ ${OUTPUT} erstellt ($(du -h "$OUTPUT" | cut -f1))"
