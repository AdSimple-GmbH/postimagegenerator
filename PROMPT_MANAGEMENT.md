# AI Prompt Management - Dokumentation

## Übersicht

Das AI Featured Image Plugin verwendet ein flexibles Prompt-Management-System basierend auf WordPress Custom Post Types. Alle Prompts können über die WordPress-Admin-Oberfläche verwaltet, angepasst und getestet werden.

## Custom Post Type: AI Prompts

### Zugriff

Im WordPress Admin unter: **AI Prompts** (Menü-Icon: 📄)

Oder direkt: `http://localhost:8080/wp-admin/edit.php?post_type=ai_prompt`

### Features

- ✅ Visuelle Verwaltung aller Prompts
- ✅ Live-Testing mit OpenAI API
- ✅ GPT-Parameter pro Prompt konfigurierbar
- ✅ Versionierung durch WordPress Revisions
- ✅ Varianten für verschiedene Längen (short/medium/long/verylong)
- ✅ Variablen-System für dynamische Inhalte
- ✅ Aktiv/Inaktiv Status

## Prompt-Slugs (Referenz)

Das System verwendet folgende Standard-Prompts:

| Slug | Beschreibung | Typ | Varianten |
|------|--------------|-----|-----------|
| `system-post-generation` | System-Prompt für Post-Generierung | System | - |
| `system-correction` | System-Prompt für Längenkorrektur | System | - |
| `post-generation` | User-Prompt für Post-Generierung | Generation | short, medium, long, verylong |
| `correction-expand` | Prompt zum Erweitern zu kurzer Texte | Correction | - |
| `correction-shorten` | Prompt zum Kürzen zu langer Texte | Correction | - |
| `image-generation` | Prompt für Bild-Generierung | Image | - |

## Prompt-Struktur

### Meta-Felder

#### 1. Prompt-Konfiguration

**Prompt-Slug** (erforderlich)
- Eindeutige Kennung
- Format: `kebab-case` (z.B. `post-generation`)
- Wird im Code zum Laden verwendet

**Prompt-Typ**
- `generation`: Post-Content-Generierung
- `correction_expand`: Content erweitern
- `correction_shorten`: Content kürzen
- `system_generation`: System-Prompt für Generierung
- `system_correction`: System-Prompt für Korrektur
- `image`: Bild-Generierung

**Varianten (JSON)**

Für Prompts mit mehreren Varianten (z.B. verschiedene Längen):

```json
{
  "short": "Prompt-Text für kurze Beiträge...",
  "medium": "Prompt-Text für mittellange Beiträge...",
  "long": "Prompt-Text für lange Beiträge...",
  "verylong": "Prompt-Text für sehr lange Beiträge..."
}
```

#### 2. GPT-Parameter

**Modell**
- `gpt-5`: High-Performance ($1.25 / 1M tokens)
- `gpt-5-mini`: Balanced ($0.25 / 1M tokens) - **Empfohlen**
- `gpt-5-nano`: Fast & Günstig ($0.05 / 1M tokens)
- `gpt-image-1`: Bild-Generierung

**Temperature** (0.0 - 2.0)
- `0.0 - 0.3`: Deterministisch, konsistent (empfohlen für Content-Generierung)
- `0.4 - 0.7`: Ausgewogen
- `0.8 - 2.0`: Kreativ, variabel

**Max Tokens**
- Maximale Anzahl Tokens in der Antwort
- Leer lassen für automatische Berechnung

**Response Format**
- `text`: Reine Text-Antwort
- `json_object`: Strukturierte JSON-Antwort (für Post-Generierung)

#### 3. Status

**Aktiv/Inaktiv**
- Nur aktive Prompts werden vom System verwendet
- Inaktive Prompts bleiben erhalten, werden aber nicht geladen

## Variablen-System

Prompts können Platzhalter enthalten, die automatisch ersetzt werden:

### Verfügbare Variablen

| Variable | Beschreibung | Beispiel |
|----------|--------------|----------|
| `{post_title}` | Titel des Posts | "Künstliche Intelligenz im Jahr 2025" |
| `{post_excerpt}` | Auszug des Posts | "Ein Überblick über..." |
| `{post_content}` | Inhalt des Posts (ohne HTML) | Volltext |
| `{min_words}` | Minimale Wortanzahl | 300 |
| `{max_words}` | Maximale Wortanzahl | 500 |
| `{current_words}` | Aktuelle Wortanzahl | 280 |
| `{length}` | Längen-Parameter | short, medium, long, verylong |

### Verwendung in Prompts

```
Schreibe einen deutschen Artikel zum Thema: "{post_title}"

Kontext: {post_excerpt}

🎯 WORTANZAHL-ANFORDERUNG:
- Zielbereich: {min_words} bis {max_words} Wörter
```

## Redaktionelle Einstellungen

### Blattlinie, Schreibstil und Zielgruppe

Unter **Einstellungen → AI Featured Image** können redaktionelle Vorgaben konfiguriert werden, die in System-Prompts verwendet werden:

#### Verfügbare Variablen für System-Prompts

| Variable | Beschreibung | Beispiel |
|----------|--------------|----------|
| `{editorial_line}` | Blattlinie der Publikation | "Progressive, technologieaffine Publikation" |
| `{author_style}` | Gewünschter Schreibstil | "Sachlich-informativ mit Beispielen" |
| `{target_audience}` | Zielgruppe | "Tech-interessierte Erwachsene 25-45" |

#### Konfiguration

1. Gehe zu **Einstellungen → AI Featured Image**
2. Scrolle zum Abschnitt **"Redaktionelle Einstellungen"**
3. Fülle die Felder aus:
   - **Blattlinie**: Beschreibe die redaktionelle Linie deiner Publikation
   - **Schreibstil**: Definiere den gewünschten Ton und Stil
   - **Zielgruppe**: Beschreibe deine Leserschaft

#### Beispiel System-Prompt mit Variablen

```
Du bist ein professioneller Content-Writer für deutsche Artikel.

BLATTLINIE: {editorial_line}
SCHREIBSTIL: {author_style}
ZIELGRUPPE: {target_audience}

Du schreibst Artikel auf Deutsch, die EXAKT die Wortzahl-Anforderungen erfüllen...
```

Die Variablen werden automatisch ersetzt, wenn der Prompt geladen wird.

## Prompt-Validierung

### Automatische Prüfung

Beim Speichern eines Prompts wird dieser automatisch auf erforderliche Bestandteile geprüft:

#### Validierungsregeln pro Typ

**Generation (`generation`)**
- Muss "JSON" und "Format" enthalten
- Muss "STRUKTUR" enthalten

**System-Generation (`system_generation`)**
- Muss "JSON" enthalten

**Korrektur Erweitern/Kürzen (`correction_expand`, `correction_shorten`)**
- Muss `{min_words}` und `{max_words}` enthalten

### Validierungsfehler

Bei fehlenden Bestandteilen erscheint eine Warnung:

```
⚠️ Prompt-Warnung:
• Fehlt: json
• Fehlt: structure

Der Prompt wurde gespeichert, enthält aber möglicherweise nicht alle erforderlichen Bestandteile.
```

Der Prompt wird trotzdem gespeichert, aber du solltest die fehlenden Teile ergänzen.

## Standard-Prompts

### System-Post-Generierung

```
Du bist ein professioneller Content-Writer für deutsche Artikel.

BLATTLINIE: {editorial_line}
SCHREIBSTIL: {author_style}
ZIELGRUPPE: {target_audience}

Du schreibst Artikel auf Deutsch, die EXAKT die Wortzahl-Anforderungen erfüllen. Du schreibst IMMER ALLE Abschnitte vollständig. Du antwortest IMMER mit gültigem JSON mit den Feldern content_html, category_name und tags.
```

**Parameter:**
- Model: gpt-5-mini
- Temperature: 0.2
- Response Format: json_object

**Hinweis:** Die Variablen `{editorial_line}`, `{author_style}` und `{target_audience}` werden aus den Plugin-Einstellungen geladen.

### Post-Generierung (Variante: short)

```
Schreibe einen deutschen Artikel zum Thema: "{post_title}"

Kontext: {post_excerpt}

🎯 WORTANZAHL-ANFORDERUNG:
- Zielbereich: {min_words} bis {max_words} Wörter

STRUKTUR (5 Abschnitte):
1. Einleitung (~80 Wörter)
2. Was ist [Thema]? (~70 Wörter)
3. Hauptmerkmale (~70 Wörter)
4. Anwendung/Vorteile (~70 Wörter)
5. Fazit (~60 Wörter)

JSON-Format (NUR dies):
{
  "content_html": "HTML-Artikel",
  "category_name": "passende Kategorie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]
}
```

### Korrektur: Erweitern

```
Der folgende Artikel hat nur {current_words} Wörter, braucht aber {min_words}-{max_words} Wörter.

WICHTIG: Erweitere den Inhalt, indem du:
1. Bestehende Abschnitte mit mehr Details und Beispielen erweiterst
2. Tiefergehende Erklärungen hinzufügst
3. Die Struktur und alle HTML-Tags beibehältst
4. NICHT neue Abschnitte hinzufügst

Aktueller Artikel:
{post_content}

Antworte NUR mit dem erweiterten HTML-Inhalt. Ziel: {min_words}-{max_words} Wörter.
```

**Parameter:**
- Model: gpt-5-mini
- Temperature: 0.3
- Max Tokens: 6000
- Response Format: text

### Bild-Generierung

```
Create a high-quality featured image for a blog post titled "{post_title}". The content is about: {post_excerpt}. Do not include any text, captions, labels, watermarks, typography, or logos in the image (text-free image).
```

**Parameter:**
- Model: gpt-image-1
- Temperature: 0.7
- Response Format: text

## Prompt erstellen

### Schritt-für-Schritt

1. **Neuer Prompt**
   - Gehe zu: AI Prompts → Hinzufügen
   - Oder: http://localhost:8080/wp-admin/post-new.php?post_type=ai_prompt

2. **Titel eingeben**
   - Beschreibender Name (z.B. "Post-Generierung (mittel)")

3. **Prompt-Text**
   - Haupt-Content-Bereich
   - Verwende Variablen wo nötig
   - Bei Varianten: Feld leer lassen und JSON in "Varianten" eintragen

4. **Konfiguration**
   - **Prompt-Slug**: Eindeutige ID (z.B. `custom-post-gen`)
   - **Prompt-Typ**: Passenden Typ wählen
   - **Varianten**: Optional JSON mit Varianten

5. **GPT-Parameter**
   - **Modell**: gpt-5-mini (empfohlen für Start)
   - **Temperature**: 0.2 (für konsistente Ergebnisse)
   - **Max Tokens**: Abhängig von erwarteter Antwortlänge
   - **Response Format**: text oder json_object

6. **Status**
   - ✓ Prompt ist aktiv (anhaken)

7. **Veröffentlichen**
   - Button "Veröffentlichen" klicken

### Best Practices

#### Prompt-Writing

1. **Sei spezifisch**
   ```
   ❌ "Schreibe einen Artikel"
   ✅ "Schreibe einen deutschen Artikel mit genau 5 Abschnitten..."
   ```

2. **Strukturiere klar**
   ```
   🎯 ANFORDERUNG:
   - Punkt 1
   - Punkt 2
   
   STRUKTUR:
   1. ...
   2. ...
   ```

3. **Nutze Variablen**
   ```
   ❌ "zum Thema [THEMA]"
   ✅ "zum Thema: \"{post_title}\""
   ```

4. **Definiere Format**
   ```
   JSON-Format (NUR dies, kein zusätzlicher Text):
   {
     "field": "value"
   }
   ```

#### GPT-Parameter

**Temperature-Wahl:**
- Content-Generierung: `0.2` (konsistent)
- Kreative Texte: `0.7`
- Bild-Prompts: `0.7` (mehr Varianz)

**Modell-Wahl:**
- Komplexe Aufgaben: `gpt-5`
- Standard-Aufgaben: `gpt-5-mini` ⭐
- Einfache Aufgaben: `gpt-5-nano`
- Bilder: `gpt-image-1`

## Prompt testen

### Im Editor

1. **Prompt öffnen**
   - AI Prompts → Prompt auswählen

2. **Test-Button**
   - Rechte Sidebar → "Prompt testen"
   - Button "Prompt testen" klicken

3. **Test-Parameter**
   - **Test-Post ID**: Optional, für Variablen-Ersetzung
   - **Variante**: z.B. "short", "medium" (nur bei Varianten-Prompts)

4. **Test starten**
   - Button "Test starten"
   - Warten (30-120 Sekunden)

5. **Ergebnis prüfen**
   - Token-Verwendung
   - Antwort-Vorschau
   - Fehlermeldungen

### Testergebnis

**Erfolg:**
- ✅ Grüne Meldung
- Token-Statistik
- Antwort-Vorschau
- Wird im Prompt gespeichert

**Fehler:**
- ❌ Rote Meldung
- Fehlerbeschreibung
- Mögliche Ursachen prüfen

## Prompt bearbeiten

### Workflow

1. **Öffnen**
   - AI Prompts → Prompt auswählen
   - Oder: Dashboard → Prompt-Verwaltung → Bearbeiten

2. **Ändern**
   - Text anpassen
   - Parameter optimieren
   - Status ändern

3. **Testen**
   - Mit Test-Funktion validieren
   - Ergebnis prüfen

4. **Aktualisieren**
   - Button "Aktualisieren"
   - Cache wird automatisch geleert

### Versionierung

**WordPress Revisions:**
- Alle Änderungen werden gespeichert
- Revision-Vergleich verfügbar
- Wiederherstellen möglich

**Zugriff:**
- Editor → Rechte Sidebar → "Revisionen"
- Diff-Ansicht
- Wiederherstellen-Button

## Troubleshooting

### Prompt wird nicht gefunden

**Fehler:** _"Prompt 'xyz' nicht gefunden"_

**Lösung:**
1. Prüfen ob Prompt existiert: AI Prompts
2. Prüfen ob Slug korrekt ist
3. Prüfen ob Status "Aktiv"
4. Cache leeren (Prompt speichern)

### Test schlägt fehl

**API Key fehlt:**
- Einstellungen → AI Featured Image
- OpenAI API Key eintragen

**Timeout:**
- Max Tokens reduzieren
- Einfacheres Modell wählen (gpt-5-nano)

**Ungültige Antwort:**
- Response Format prüfen
- Prompt-Anweisungen klarer formulieren

### Variablen werden nicht ersetzt

**Problem:** Variablen erscheinen als `{post_title}` im generierten Content

**Lösung:**
- Test-Post ID angeben beim Testen
- Prüfen ob Variablen-Namen korrekt sind
- Prüfen ob Groß-/Kleinschreibung stimmt

### Performance-Probleme

**Langsame Generierung:**
- Kleineres Modell: gpt-5-mini statt gpt-5
- Max Tokens reduzieren
- Prompt vereinfachen

**Hohe Kosten:**
- gpt-5-nano für einfache Aufgaben
- Max Tokens limitieren
- Temperature senken (weniger Tokens)

## Integration im Code

### Prompt laden

```php
$loader = new AI_Featured_Image_Prompt_Loader();

// Einfacher Prompt
$prompt = $loader->get_prompt_by_slug( 'system-post-generation' );

// Mit Variante
$prompt = $loader->get_prompt_by_slug( 'post-generation', 'medium' );

// Mit Variablen
$variables = array(
    'post_title' => 'Mein Titel',
    'min_words' => 300,
    'max_words' => 500
);
$prompt = $loader->get_prompt_by_slug( 'post-generation', 'short', $variables );
```

### Konfiguration laden

```php
$config = $loader->get_prompt_config( 'post-generation' );

$model = $config['model']; // z.B. 'gpt-5-mini'
$temperature = $config['temperature']; // z.B. 0.2
$max_tokens = $config['max_tokens'];
$response_format = $config['response_format'];
```

### Fehlerbehandlung

```php
try {
    $prompt = $loader->get_prompt_by_slug( 'my-prompt' );
} catch ( Exception $e ) {
    // Prompt fehlt - zeige Fehler
    error_log( $e->getMessage() );
    // Fallback oder Abbruch
}
```

## API-Dokumentation

### REST API

Keine direkte REST API für Prompts (verwendet WordPress Post API).

### WP-CLI

```bash
# Prompts listen
wp post list --post_type=ai_prompt

# Prompt erstellen
wp post create --post_type=ai_prompt \
  --post_title="Mein Prompt" \
  --post_content="Prompt-Text..." \
  --post_status=publish

# Meta setzen
wp post meta set POST_ID _prompt_slug "my-prompt"
wp post meta set POST_ID _gpt_model "gpt-5-mini"
wp post meta set POST_ID _is_active "1"
```

## Beispiele

### Beispiel 1: Custom Post-Generierungs-Prompt

**Szenario:** Spezieller Prompt für Tech-Artikel

**Schritte:**
1. Neuer Prompt erstellen
2. Titel: "Tech-Artikel Generierung"
3. Slug: `tech-post-generation`
4. Typ: `generation`
5. Prompt-Text:
   ```
   Schreibe einen technischen Artikel zum Thema: "{post_title}"
   
   Zielgruppe: IT-Professionals
   Ton: Technisch, präzise
   
   STRUKTUR:
   1. Executive Summary
   2. Technische Details
   3. Implementation
   4. Best Practices
   5. Conclusion
   
   Wortanzahl: {min_words}-{max_words}
   
   JSON-Format:
   {
     "content_html": "...",
     "category_name": "Technology",
     "tags": ["tech", "..."]
   }
   ```
6. Modell: gpt-5
7. Temperature: 0.3
8. Response Format: json_object
9. Aktiv: ✓
10. Veröffentlichen

**Verwendung im Code:**
```php
$prompt = $loader->get_prompt_by_slug( 'tech-post-generation', null, $variables );
```

### Beispiel 2: Multi-Sprachen-Prompt

**Szenario:** Prompts für verschiedene Sprachen

**Varianten-JSON:**
```json
{
  "de": "Schreibe einen deutschen Artikel...",
  "en": "Write an English article...",
  "fr": "Écrivez un article français..."
}
```

**Verwendung:**
```php
$prompt = $loader->get_prompt_by_slug( 'multi-lang-post', 'de', $variables );
```

## Zusammenfassung

Das Prompt-Management-System bietet:

✅ **Flexibilität**: Alle Prompts anpassbar  
✅ **Transparenz**: Sichtbare Konfiguration  
✅ **Testbarkeit**: Live-Testing möglich  
✅ **Versionierung**: Änderungen nachvollziehbar  
✅ **Performance**: Effizientes Caching  
✅ **Sicherheit**: Fehlerbehandlung integriert  

**Nächste Schritte:**
1. Standard-Prompts prüfen und bei Bedarf anpassen
2. Eigene Prompts für spezielle Anwendungsfälle erstellen
3. Regelmäßig testen und optimieren
4. Dashboard für schnellen Überblick nutzen

