# Cypress E2E Tests für AI Featured Image Generator Plugin

## 📚 Neue Test-Dokumentation

**→ Siehe [TEST_DOCUMENTATION.md](TEST_DOCUMENTATION.md) für vollständige Dokumentation der neuen Tests:**

- ✅ **Dashboard Tests** (`ai-dashboard.cy.js`) - AI Post Generator Dashboard mit REST API
- ✅ **REST API Tests** (`ai-rest-api.cy.js`) - `/ai-featured-image/v1/generate-post` Endpoint
- ✅ **Prompt Management Tests** (`ai-prompt-management.cy.js`) - CPT-basierte Prompt-Verwaltung

Diese Tests decken alle neuen Features ab:
- Content-Generierung mit verschiedenen Längen
- Automatische Längen-Korrektur
- Debug-Panel mit OpenAI Kommunikation
- Prompt-Verwaltung mit GPT-5 Unterstützung
- Temperature-Handling für GPT-5 Modelle

---

## Übersicht

Diese Test-Suite enthält umfassende End-to-End-Tests für das AI Featured Image Generator WordPress-Plugin.

## Test-Dateien

### 1. `wp-admin-login.cy.js`
Grundlegender WordPress-Login-Test.

**Tests:**
- WordPress Admin Login und Dashboard-Zugriff

**Dauer:** ~1 Sekunde

---

### 2. `ai-featured-image-plugin.cy.js`
Tests für die AI Featured Image Funktionalität.

**Tests:**
- Modal-Öffnung und -Schließung
- UI-Elemente-Validierung
- Anzahl der Bilder-Optionen
- Settings-Seite
- Classic Editor Integration
- Error-Handling

**Dauer:** ~19 Sekunden

---

### 3. `ai-post-generation.cy.js`
Tests für die AI-Beitragserstellung ohne echte API-Aufrufe.

**Tests:**
- UI-Kontrollen für AI-Post-Generierung
- Alle 4 Längenoptionen (short, medium, long, verylong)
- Post-Erstellung und -Veröffentlichung
- Zufällige KI-bezogene Titel

**Dauer:** ~77 Sekunden

---

### 4. `ai-post-generation-with-api.cy.js` ⭐ NEU
Erweiterte Tests mit echten OpenAI API-Aufrufen und Validierung.

**Tests:**
- **Real API Integration**: Echte OpenAI API-Aufrufe
- **Wortanzahl-Validierung**: Überprüft, ob generierte Inhalte die erwartete Länge haben
- **Screenshots**: Erstellt Screenshots für jeden wichtigen Schritt
- **Alle Längenoptionen**: Testet short, medium, long, verylong
- **Vergleichstest**: Erstellt Posts mit allen 4 Längen und vergleicht die Wortanzahlen

**Erwartete Wortanzahlen:**
- **short**: 250-650 Worte (Ziel: 300-500)
- **medium**: 700-1400 Worte (Ziel: 800-1200)
- **long**: 1400-2300 Worte (Ziel: 1500-2000)
- **verylong**: 2300-3500 Worte (Ziel: 2500+)

**Dauer:** ~10-15 Minuten (wegen API-Aufrufen)

**⚠️ WICHTIG:** Benötigt einen gültigen OpenAI API-Key in den WordPress-Einstellungen!

---

## Voraussetzungen

### 1. Docker-Container
```bash
docker compose up -d
```

Überprüfen:
```bash
docker ps
```

WordPress sollte auf `http://localhost:8080` laufen.

### 2. Umgebungsvariablen

Erstelle `cypress.env.json` (bereits vorhanden):
```json
{
  "wpUsername": "admin",
  "wpPassword": "admin"
}
```

### 3. OpenAI API-Key (nur für API-Tests)

Für Tests mit echten API-Aufrufen:
1. Gehe zu `http://localhost:8080/wp-admin/options-general.php?page=ai-featured-image-settings`
2. Trage deinen OpenAI API-Key ein
3. Speichere die Einstellungen

---

## Tests ausführen

### Alle Tests (ohne API)
```bash
npm run test:e2e
```

### Einzelne Test-Datei
```bash
npm run test:e2e -- --spec "cypress/e2e/ai-featured-image-plugin.cy.js"
```

### Interaktiver Modus (mit Browser-UI)
```bash
npm run cypress:open
```

### Nur API-Tests mit Screenshots
```bash
npm run test:e2e -- --spec "cypress/e2e/ai-post-generation-with-api.cy.js"
```

---

## Screenshots

Screenshots werden automatisch erstellt in:
```
cypress/screenshots/
```

### Screenshot-Typen:

#### Bei API-Tests (für jede Länge):
1. `{length}-01-empty-editor.png` - Leerer Editor
2. `{length}-02-title-added.png` - Titel hinzugefügt
3. `{length}-03-length-selected.png` - Länge ausgewählt
4. `{length}-04-generation-started.png` - Generierung gestartet
5. `{length}-05-api-response-received.png` - API-Antwort empfangen
6. `{length}-06-content-inserted.png` - Inhalt eingefügt
7. `{length}-07-draft-saved.png` - Als Entwurf gespeichert

#### Bei vollständigem Test:
- `complete-01-initial.png`
- `complete-02-title-added.png`
- `complete-03-ready-to-generate.png`
- `complete-04-content-generated.png`
- `complete-05-before-publish.png`
- `complete-06-publish-panel.png`
- `complete-07-published.png`
- `complete-08-post-list.png`
- `complete-09-search-results.png`

#### Bei Fehlern:
- `{testname}-ERROR-*.png` - Screenshot bei Fehler

---

## Test-Ergebnisse

### Erwartete Ausgabe (ohne API-Tests):
```
✓  All specs passed!
15 Tests | 15 Passing | 0 Failing
Dauer: ~1:40 Minuten
```

### Mit API-Tests:
```
✓  All specs passed!
21+ Tests | 21+ Passing | 0 Failing
Dauer: ~15-20 Minuten
```

---

## Validierungen in API-Tests

### 1. Wortanzahl-Validierung
```javascript
const wordCount = countWords(response.data.content_html);
expect(wordCount).to.be.at.least(expectations.min);
expect(wordCount).to.be.at.most(expectations.max);
```

### 2. Tags-Validierung
```javascript
expect(response.data.tags.length).to.be.within(7, 10);
```

### 3. Kategorie-Vorschlag
```javascript
expect(response.data.category_name).to.exist;
```

### 4. HTML-Struktur
```javascript
expect(response.data.content_html).to.include('<h2>');
expect(response.data.content_html).to.include('<p>');
```

---

## Troubleshooting

### Tests schlagen fehl: "API key is not set"
**Lösung:** OpenAI API-Key in WordPress-Einstellungen eintragen.

### Tests timeout bei API-Aufrufen
**Lösung:** 
- Timeout ist auf 3 Minuten gesetzt
- Bei langsamer Verbindung in `cypress.config.js` erhöhen:
```javascript
requestTimeout: 300000, // 5 Minuten
responseTimeout: 300000
```

### Screenshots werden nicht erstellt
**Lösung:** Überprüfe Ordner-Berechtigungen:
```bash
mkdir -p cypress/screenshots
chmod 755 cypress/screenshots
```

### Login schlägt fehl
**Lösung:** Überprüfe `cypress.env.json` und WordPress-Zugangsdaten.

### Classic Editor nicht verfügbar
**Lösung:** Installiere das Classic Editor Plugin in WordPress oder überspringe Classic-Editor-Tests.

---

## CI/CD Integration

### GitHub Actions Beispiel
```yaml
name: Cypress E2E Tests

on: [push, pull_request]

jobs:
  cypress:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Start Docker containers
        run: docker compose up -d
      
      - name: Wait for WordPress
        run: sleep 30
      
      - name: Run Cypress tests
        run: npm run test:e2e
        env:
          CYPRESS_wpUsername: admin
          CYPRESS_wpPassword: admin
      
      - name: Upload screenshots
        if: failure()
        uses: actions/upload-artifact@v3
        with:
          name: cypress-screenshots
          path: cypress/screenshots
```

---

## Erstellte Test-Posts

Die Tests erstellen Posts mit folgenden Titel-Mustern:
- "Künstliche Intelligenz revolutioniert die Arbeitswelt - API Test {length}"
- "Machine Learning: Die Zukunft der Datenanalyse - {timestamp}"
- "KI Multi-Test {length} - {timestamp}"

Diese findest du unter:
`http://localhost:8080/wp-admin/edit.php`

---

## Entwickler-Notizen

### Custom Commands
Definiert in `cypress/support/e2e.js`:
- `cy.wpLogin()` - WordPress Login mit Session-Caching

### Utilities
- `countWords(html)` - Zählt Wörter in HTML-Content
- `getRandomTitle()` - Wählt zufälligen KI-bezogenen Titel

### Best Practices
1. Tests verwenden `cy.session()` für schnelleres Login
2. API-Intercepting für Request-Tracking
3. Screenshots bei jedem wichtigen Schritt
4. Retry-Logik für flaky Tests
5. Ausführliche Logging mit `cy.log()`

---

## Lizenz

Dieses Projekt verwendet die gleiche Lizenz wie das Haupt-Plugin (GPL-2.0-or-later).

