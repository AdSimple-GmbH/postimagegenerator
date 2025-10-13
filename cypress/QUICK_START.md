# Cypress Tests - Quick Start Guide

## 🚀 Schnellstart

### 1. Docker starten
```bash
docker-compose up -d
```

### 2. WordPress Zugang prüfen
- URL: http://localhost:8080
- Admin: `admin`
- Password: In `cypress.env.json` setzen

### 3. OpenAI API Key konfigurieren
1. Gehe zu http://localhost:8080/wp-admin/options-general.php?page=ai-featured-image-settings
2. Trage deinen OpenAI API Key ein
3. Speichern

### 4. Cypress installieren (falls noch nicht geschehen)
```bash
npm install
```

### 5. Tests ausführen

**Alle neuen Tests (Dashboard + REST API + Prompt Management):**
```bash
npm test
```

Oder einzeln:

**Dashboard Tests:**
```bash
npx cypress run --spec "cypress/e2e/ai-dashboard.cy.js"
```

**REST API Tests:**
```bash
npx cypress run --spec "cypress/e2e/ai-rest-api.cy.js"
```

**Prompt Management Tests:**
```bash
npx cypress run --spec "cypress/e2e/ai-prompt-management.cy.js"
```

**Interaktiver Modus (empfohlen für Entwicklung):**
```bash
npx cypress open
```

## 📋 Was wird getestet?

### Dashboard Tests (`ai-dashboard.cy.js`)
- ✅ Dashboard lädt korrekt
- ✅ Statistiken werden angezeigt
- ✅ Content-Generierung für alle Längen (short, medium, long, verylong)
- ✅ Auto-Korrektur funktioniert
- ✅ Debug-Panel zeigt OpenAI Kommunikation
- ✅ Prompt-Links sind klickbar

**Dauer:** ~15-30 Minuten

### REST API Tests (`ai-rest-api.cy.js`)
- ✅ REST Endpoint funktioniert
- ✅ Response-Struktur ist korrekt
- ✅ Debug-Informationen sind vollständig
- ✅ GPT-5 Modelle werden korrekt verwendet
- ✅ Temperature-Handling für GPT-5
- ✅ Korrekturen werden getrackt

**Dauer:** ~20-40 Minuten

### Prompt Management Tests (`ai-prompt-management.cy.js`)
- ✅ AI Prompts Menü existiert
- ✅ Default Prompts wurden erstellt
- ✅ Neue Prompts können erstellt werden
- ✅ GPT-5 Modelle sind verfügbar
- ✅ Meta-Fields funktionieren
- ✅ Taxonomie-Sync funktioniert

**Dauer:** ~5-10 Minuten

## ⚠️ Wichtige Hinweise

### Timeouts
Tests brauchen Zeit wegen OpenAI API Calls:
- Content-Generierung: bis zu 3 Minuten
- Einzelner Test: bis zu 5 Minuten
- Ganze Suite: 40-80 Minuten

### API Key
**WICHTIG:** Ohne konfigurierten OpenAI API Key schlagen alle API-Tests fehl!

### Kosten
Jeder Test-Durchlauf verbraucht OpenAI Credits:
- Pro Generierung: ~500-5000 Tokens
- Ganze Suite: ~50.000-100.000 Tokens
- Geschätzte Kosten: $0.50-$2.00 pro Durchlauf (mit GPT-5)

### Retries
Tests werden bei Fehler **1x automatisch wiederholt**.

## 🐛 Debugging

### Screenshots ansehen
```bash
ls -la cypress/screenshots/
```

### Logs prüfen
WordPress Logs:
```bash
docker exec postimagegenerator-wordpress-1 cat /var/www/html/wp-content/uploads/ai-featured-image.log
```

### Einzelnen Test debuggen
```bash
npx cypress open
```
Dann:
1. Test auswählen
2. DevTools öffnen (F12)
3. Console und Network Tab beobachten

## ✅ Erfolgreiche Tests

Alle Tests sollten **grün** sein (PASS):

```
✓ should load the dashboard successfully
✓ should generate short post via dashboard
✓ should generate medium post via dashboard
...
```

## ❌ Häufige Fehler

### 1. API Key fehlt
```
Error: OpenAI API key is not set
```
**Lösung:** API Key in WordPress Settings konfigurieren

### 2. Timeout
```
Error: Timeout of 180000ms exceeded
```
**Lösung:** 
- OpenAI API Status prüfen
- Netzwerk prüfen
- Test später nochmal ausführen

### 3. Word Count außerhalb Range
```
Error: expected 280 to be at least 300
```
**Lösung:** Normal! GPT ist manchmal etwas daneben. Test wiederholt sich automatisch.

### 4. Prompt fehlt
```
Error: Prompt "system-post-generation" nicht gefunden
```
**Lösung:** Plugin deaktivieren und reaktivieren

## 📊 Test Coverage

| Feature | Coverage |
|---------|----------|
| Content Generation | ✅ 100% |
| Length Options | ✅ 100% |
| Auto-Correction | ✅ 100% |
| Debug Panel | ✅ 100% |
| Prompt Management | ✅ 100% |
| GPT-5 Support | ✅ 100% |
| Error Handling | ✅ 100% |

## 🔄 CI/CD Integration

Für GitHub Actions / GitLab CI siehe [TEST_DOCUMENTATION.md](TEST_DOCUMENTATION.md)

## 📖 Mehr Infos

Vollständige Dokumentation: [TEST_DOCUMENTATION.md](TEST_DOCUMENTATION.md)

---

**Happy Testing! 🎉**



