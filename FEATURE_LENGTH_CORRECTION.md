# AI Post Length Correction Feature

## Übersicht

Dieses Feature löst das Problem, dass AI-generierte Beiträge oft nicht die gewünschte Länge haben. Es implementiert einen **automatischen Korrekturmechanismus**, der die Wortanzahl validiert und bei Bedarf in einer zweiten Runde korrigieren lässt.

## Problem

Bei der AI-Beitrag-Generierung gibt es häufig Abweichungen von der gewünschten Länge:
- **Zu kurze Beiträge**: GPT stoppt früher als gewünscht
- **Zu lange Beiträge**: GPT überschreitet die Zielvorgabe
- **Inkonsistenz**: Unterschiedliche Ergebnisse bei gleichen Parametern

## Lösung

### 1. Wortanzahl-Validierung
Nach der initialen Generierung wird die tatsächliche Wortanzahl ermittelt und mit dem Zielbereich verglichen:

```php
$word_count = count_html_words($content_html);
$validation = validate_word_count($word_count, $min_words, $max_words);
```

**Toleranzbereich**: ±10% für Flexibilität

### 2. Automatische Korrektur
Falls die Validierung fehlschlägt, wird eine Korrektur durchgeführt:

#### Zu kurz (Expand)
- Bestehende Abschnitte werden mit Details und Beispielen erweitert
- Tiefergehende Erklärungen werden hinzugefügt
- Struktur und HTML-Tags bleiben erhalten

#### Zu lang (Shorten)
- Redundante Informationen werden entfernt
- Absätze werden prägnanter formuliert
- Kernaussagen werden erhalten

### 3. Iterativer Prozess
- Bis zu `max_corrections` Versuche (Standard: 2)
- Jede Iteration wird geloggt
- Stopp bei Erfolg oder Limit

## Implementation

### Neue Methoden in `class-ai-featured-image-api-connector.php`

1. **`count_html_words()`**: Zählt Wörter in HTML-Inhalt
2. **`validate_word_count()`**: Validiert Wortanzahl mit Toleranz
3. **`build_correction_prompt()`**: Erstellt Korrektur-Prompt für GPT
4. **`correct_content_length()`**: Führt Längenkorrektur durch
5. **`register_rest_routes()`**: Registriert REST API Endpunkt
6. **`rest_generate_post()`**: REST API Callback mit Auto-Korrektur

### REST API Endpunkt

**Endpoint**: `POST /wp-json/ai-featured-image/v1/generate-post`

**Parameter**:
- `post_id` (required): WordPress Post-ID
- `length` (optional): `short`, `medium`, `long`, `verylong` (default: `short`)
- `auto_correct` (optional): Boolean (default: `true`)
- `max_corrections` (optional): 0-3 (default: `2`)

**Response**:
```json
{
  "success": true,
  "data": {
    "content_html": "...",
    "word_count": {
      "initial": 280,
      "final": 420,
      "target_min": 300,
      "target_max": 500,
      "valid": true,
      "message": "Word count valid: 420 words (target: 300-500)"
    },
    "corrections": {
      "enabled": true,
      "made": 1,
      "max_allowed": 2,
      "history": [...]
    }
  }
}
```

## Verwendung

### Via REST API (empfohlen für Tests)

```bash
curl -X POST http://localhost:8080/wp-json/ai-featured-image/v1/generate-post \
  -H "Content-Type: application/json" \
  -u admin:admin \
  -d '{
    "post_id": 123,
    "length": "medium",
    "auto_correct": true,
    "max_corrections": 2
  }'
```

### Test-Skript ausführen

```bash
bash test-rest-api-length-correction.sh
```

Das Skript:
1. Erstellt einen Test-Post
2. Testet mit/ohne Auto-Korrektur
3. Testet alle Längenoptionen
4. Zeigt Statistiken an
5. Räumt auf

## Vorteile

✅ **Präzision**: Beiträge entsprechen der gewünschten Länge  
✅ **Konsistenz**: Verlässliche Ergebnisse über verschiedene Themen  
✅ **Qualität**: Inhaltliche Qualität bleibt erhalten  
✅ **Transparenz**: Detaillierte Logs aller Korrekturen  
✅ **Flexibilität**: Konfigurierbare Parameter  
✅ **Effizienz**: Automatischer Prozess ohne manuellen Eingriff

## Längen-Spezifikationen

| Länge | Ziel-Wörter | Toleranzbereich | Max Tokens |
|-------|-------------|-----------------|------------|
| `short` | 300-500 | 270-550 | 1800 |
| `medium` | 800-1200 | 720-1320 | 3500 |
| `long` | 1500-2000 | 1350-2200 | 5000 |
| `verylong` | 2500-3000 | 2250-3300 | 8000 |

## Logging

Alle Aktionen werden geloggt:

```json
{
  "ts": "2025-10-10T14:30:00Z",
  "message": "rest_api_correction_attempt",
  "context": {
    "post_id": 123,
    "attempt": 1,
    "current_words": 280,
    "direction": "expand"
  }
}
```

**Log-Events**:
- `rest_api_post_request`: Initiale Anfrage
- `rest_api_correction_attempt`: Korrekturversuch
- `rest_api_correction_failed`: Fehlgeschlagene Korrektur
- `rest_api_post_complete`: Abschluss mit Statistiken

**Log-Datei**: WordPress Uploads-Verzeichnis → `ai-featured-image.log`

## Testing

### 1. Manuelle Tests

```bash
# Post erstellen
POST_ID=$(docker exec postimagegenerator-wordpress-1 wp post create \
  --post_title="Test" --post_status=draft --user=admin --porcelain)

# API aufrufen
curl -X POST http://localhost:8080/wp-json/ai-featured-image/v1/generate-post \
  -u admin:admin \
  -H "Content-Type: application/json" \
  -d "{\"post_id\": $POST_ID, \"length\": \"medium\", \"auto_correct\": true}"

# Aufräumen
docker exec postimagegenerator-wordpress-1 wp post delete $POST_ID --force
```

### 2. Automatisierte Tests

Das bereitgestellte Shell-Skript `test-rest-api-length-correction.sh` führt umfassende Tests durch.

### 3. Browser-Konsole

```javascript
fetch('/wp-json/ai-featured-image/v1/generate-post', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    post_id: 123,
    length: 'medium',
    auto_correct: true
  })
})
.then(r => r.json())
.then(d => console.log('Result:', d));
```

## Fehlerbehandlung

| Fehlercode | Beschreibung | Lösung |
|------------|--------------|--------|
| `401` | Nicht authentifiziert | Login prüfen |
| `403` | Keine Berechtigung | `edit_posts` Capability erforderlich |
| `404` | Post nicht gefunden | Post-ID prüfen |
| `400` | API Key fehlt | OpenAI API Key in Settings konfigurieren |
| `500` | API Fehler | OpenAI Status prüfen, Logs ansehen |

## Technische Details

### Verwendete APIs
- **OpenAI GPT-4o**: Content-Generierung und Korrektur
- **WordPress REST API**: Endpunkt-Framework
- **WordPress Post API**: Post-Verwaltung

### Performance
- Initial: ~30-60 Sekunden (abhängig von Länge)
- Pro Korrektur: +20-40 Sekunden
- Timeout: 180 Sekunden

### Sicherheit
- WordPress Nonce/Cookie-Authentifizierung
- Capability-Check: `edit_posts`
- Input-Validierung und Sanitization
- Rate-Limiting via OpenAI API

## Dokumentation

Vollständige API-Dokumentation: [documentation/setup.md](documentation/setup.md#rest-api---ai-post-generation-with-length-correction)

## Beispiel-Output

```json
{
  "success": true,
  "data": {
    "content_html": "<h2>Einleitung</h2><p>Künstliche Intelligenz...</p>",
    "category_id": 5,
    "category_name": "Technologie",
    "tags": ["AI", "KI", "Innovation", "Zukunft", "Technologie", "Automation", "Machine Learning"],
    "word_count": {
      "initial": 780,
      "final": 950,
      "target_min": 800,
      "target_max": 1200,
      "valid": true,
      "message": "Word count valid: 950 words (target: 800-1200)"
    },
    "corrections": {
      "enabled": true,
      "made": 1,
      "max_allowed": 2,
      "history": [
        {
          "attempt": 1,
          "before_words": 780,
          "after_words": 950,
          "direction": "expand"
        }
      ]
    }
  }
}
```

## Zusammenfassung

Dieses Feature bietet eine **robuste Lösung** für das Problem inkonsistenter AI-genierter Beitragslängen. Durch automatische Validierung und Korrektur werden **präzise, qualitativ hochwertige** Inhalte generiert, die den Anforderungen entsprechen.

Die **REST API** ermöglicht einfaches Testing und Integration in externe Workflows. Alle Prozesse sind **transparent geloggt** und über konfigurierbare Parameter **flexibel anpassbar**.


