# AI Post Generator Dashboard - Anleitung

## Übersicht

Das **AI Post Generator Dashboard** ist eine interaktive Benutzeroberfläche zum Testen und Verwenden der AI-Post-Generierung mit automatischer Längenkorrektur.

## Zugriff

Im WordPress Admin-Menü unter:
**AI Dashboard** (mit Analytik-Icon 📊)

Oder direkt: `http://localhost:8080/wp-admin/admin.php?page=ai-post-dashboard`

## Features

### 1. **Live-Testing**
- ✅ Wähle einen bestehenden Post oder erstelle einen neuen Test-Post
- ✅ Konfiguriere Länge (short, medium, long, verylong)
- ✅ Aktiviere/Deaktiviere Auto-Korrektur
- ✅ Stelle maximale Korrekturversuche ein (0-3)
- ✅ Starte Generierung mit einem Klick

### 2. **Echtzeit-Ergebnisse**
- 📊 Wortanzahl (Initial vs. Final)
- 🎯 Validierungsstatus (Gültig/Außerhalb Bereich)
- 🔄 Anzahl durchgeführter Korrekturen
- 📈 Prozentuale Änderung
- 📋 Korrektur-Verlauf mit Details
- 📁 Generierte Kategorie und Tags
- 📄 Content-Vorschau

### 3. **Statistiken**
- **Gesamt generiert**: Gesamtanzahl aller Generierungen
- **Heute**: Generierungen des aktuellen Tags
- **Erfolgsrate**: Prozentsatz gültiger Längen
- **Ø Korrekturen**: Durchschnittliche Korrekturversuche

### 4. **Visuelles Design**
- 🎨 Modernes, responsives Design
- 🌈 Farb-codierte Status-Anzeigen
- 📱 Mobile-optimiert
- ⚡ Animierte Fortschrittsanzeige

## Verwendung

### Schritt 1: Post auswählen

**Option A: Bestehenden Post verwenden**
1. Wähle einen Post aus dem Dropdown (zeigt die letzten 50 Posts)
2. Post-ID und Titel werden angezeigt

**Option B: Neuen Test-Post erstellen**
1. Klicke auf "Neuen Test-Post erstellen"
2. Ein Draft-Post mit zufälligem Titel wird erstellt
3. Der neue Post wird automatisch ausgewählt

### Schritt 2: Konfiguration

**Länge wählen:**
- **Kurz**: 300-500 Wörter (±10%)
- **Mittel**: 800-1200 Wörter (±10%)
- **Lang**: 1500-2000 Wörter (±10%)
- **Sehr Lang**: 2500-3000 Wörter (±10%)

**Auto-Korrektur:**
- ✓ Aktiviert: Automatische Längenanpassung bei Bedarf
- ✗ Deaktiviert: Nur initiale Generierung, keine Korrektur

**Max. Korrekturen:**
- 0: Keine Korrekturen
- 1-3: Maximale Anzahl Korrekturversuche

### Schritt 3: Generierung starten

1. Klicke auf "Content generieren"
2. Fortschrittsanzeige wird eingeblendet
3. Warte auf Ergebnis (30-120 Sekunden je nach Länge)

### Schritt 4: Ergebnisse prüfen

**Erfolgreiche Generierung:**
- ✅ Grüner Header bei gültiger Länge
- ⚠️ Gelber Header bei Abweichung außerhalb Toleranz

**Anzeige enthält:**
- Wortanzahl-Statistik mit Zielbereich
- Status-Information (Gültig/Ungültig)
- Anzahl durchgeführter Korrekturen
- Prozentuale Änderung der Wortanzahl
- Detaillierter Korrektur-Verlauf (falls vorhanden)
- Generierte Kategorie und Tags
- Content-Vorschau (erste 2000 Zeichen)

**Bei Fehlern:**
- ❌ Roter Fehlerbereich mit Fehlermeldung
- Mögliche Ursachen:
  - Kein OpenAI API Key konfiguriert
  - Ungültige Post-ID
  - API-Fehler
  - Timeout (bei sehr langen Texten)

### Schritt 5: Content verwenden

⚠️ **Wichtig**: Content wird NICHT automatisch gespeichert!

**Manuelle Speicherung:**
1. Kopiere den generierten Content aus der Vorschau
2. Öffne den Post im WordPress-Editor
3. Füge den Content ein
4. Speichere den Post

**Alternative: WP-CLI verwenden** (siehe unten)

## Statistiken aktualisieren

Klicke auf den "Aktualisieren"-Button in der Statistik-Karte, um die neuesten Zahlen zu laden.

Statistiken basieren auf der Log-Datei:
`wp-content/uploads/ai-featured-image.log`

## WP-CLI Befehle

Das Plugin bietet leistungsfähige WP-CLI Commands zum Testen und Verwenden der Funktionen.

### Test-Befehl

```bash
# Grundlegender Test mit neuem Post
docker exec -it postimagegenerator-wordpress-1 wp ai-post test

# Test mit bestehendem Post
docker exec -it postimagegenerator-wordpress-1 wp ai-post test --post_id=123

# Test mit spezifischer Länge
docker exec -it postimagegenerator-wordpress-1 wp ai-post test --length=long

# Test OHNE Auto-Korrektur
docker exec -it postimagegenerator-wordpress-1 wp ai-post test --no-auto-correct

# Test mit automatischer Speicherung
docker exec -it postimagegenerator-wordpress-1 wp ai-post test --save

# Vollständiges Beispiel
docker exec -it postimagegenerator-wordpress-1 wp ai-post test \
  --length=medium \
  --auto-correct \
  --max-corrections=3 \
  --save
```

### Statistik-Befehl

```bash
# Zeige Statistiken
docker exec -it postimagegenerator-wordpress-1 wp ai-post stats
```

**Ausgabe:**
```
📊 AI Post Generation Statistiken
═══════════════════════════════════════

Gesamt generiert: 25
Heute generiert: 5

Erfolgsrate: 88%
Ø Korrekturen: 1.2

Nach Länge:
  - short: 8
  - medium: 10
  - long: 5
  - verylong: 2
```

## Tipps & Tricks

### Schnelles Testen verschiedener Längen

Erstelle einen Test-Post und verwende ihn für mehrere Tests:

```bash
# Post erstellen
POST_ID=$(docker exec postimagegenerator-wordpress-1 wp post create \
  --post_title="Test Post" --post_status=draft --porcelain)

# Verschiedene Längen testen
for length in short medium long verylong; do
  echo "Testing $length..."
  docker exec postimagegenerator-wordpress-1 wp ai-post test \
    --post_id=$POST_ID \
    --length=$length
done
```

### Batch-Testing

Teste mehrere Posts hintereinander:

```bash
#!/bin/bash
for i in {1..5}; do
  echo "=== Test $i ==="
  docker exec postimagegenerator-wordpress-1 wp ai-post test \
    --length=medium \
    --save
  sleep 5
done
```

### Performance-Test

Messe die Generierungszeit:

```bash
time docker exec postimagegenerator-wordpress-1 wp ai-post test --length=verylong
```

## Troubleshooting

### "Keine Berechtigung"
- Stelle sicher, dass du als Admin eingeloggt bist
- Erforderliche Capability: `edit_posts`

### "OpenAI API key is not set"
1. Gehe zu Einstellungen → AI Featured Image
2. Trage deinen OpenAI API Key ein
3. Speichere die Einstellungen

### Dashboard lädt nicht
- Lösche Browser-Cache
- Prüfe Browser-Konsole auf JavaScript-Fehler
- Stelle sicher, dass JavaScript aktiviert ist

### Timeout-Fehler
- Erhöhe PHP `max_execution_time` in `php.ini`
- Wähle kürzere Länge (z.B. `short` statt `verylong`)
- Reduziere `max_corrections`

### Statistiken werden nicht angezeigt
- Prüfe, ob Log-Datei existiert: `wp-content/uploads/ai-featured-image.log`
- Generiere mindestens einen Post, um Daten zu erzeugen
- Klicke auf "Aktualisieren"

### WP-CLI Befehle funktionieren nicht
- Stelle sicher, dass Docker läuft
- Prüfe Container-Namen: `docker ps`
- Verwende korrekten Container-Namen in Befehlen

## Tastenkombinationen

- **Enter** im Formular: Startet Generierung
- **ESC**: Schließt Benachrichtigungen (wenn implementiert)

## Technische Details

### AJAX Endpoints

**Test-Generierung:**
```
Action: ai_dashboard_test_generation
Nonce: ai_dashboard_nonce
```

**Statistiken abrufen:**
```
Action: ai_dashboard_get_stats
Nonce: ai_dashboard_nonce
```

**Test-Post erstellen:**
```
Action: ai_dashboard_create_test_post
Nonce: ai_dashboard_nonce
```

### REST API Integration

Das Dashboard nutzt intern die REST API:
```
POST /wp-json/ai-featured-image/v1/generate-post
```

Mit AJAX-Wrapper für bessere WordPress-Integration.

### Logging

Alle Generierungen werden geloggt:
- Event: `rest_api_post_complete`
- Enthält: post_id, word counts, corrections, validity
- Speicherort: WordPress Uploads-Verzeichnis

## Best Practices

1. **Teste zuerst mit kurzen Längen** um API-Credits zu sparen
2. **Verwende Auto-Korrektur** für bessere Ergebnisse
3. **Speichere nur qualitativ hochwertige** Inhalte
4. **Prüfe Content vor Veröffentlichung** manuell
5. **Nutze WP-CLI für Batch-Operationen**
6. **Überwache Statistiken** zur Optimierung

## Erweiterungsmöglichkeiten

Das Dashboard kann erweitert werden mit:
- Direkter Content-Speicherung (Ein-Klick)
- Export-Funktionen (PDF, DOCX)
- Content-Vergleich (vorher/nachher)
- A/B-Testing verschiedener Prompts
- Historien-Verwaltung
- Favoriten-Speicherung

## Support

Bei Problemen oder Fragen:
1. Prüfe die Logs: `wp-content/uploads/ai-featured-image.log`
2. Aktiviere WP_DEBUG für detaillierte Fehler
3. Konsultiere die REST API Dokumentation: `documentation/setup.md`

## Zusammenfassung

Das Dashboard bietet eine **komfortable, visuelle Oberfläche** zum Testen und Nutzen der AI-Post-Generierung. In Kombination mit **WP-CLI Commands** ermöglicht es sowohl manuelle als auch automatisierte Workflows.

**Vorteile:**
- ✅ Keine Programmierkenntnisse erforderlich
- ✅ Sofortiges visuelles Feedback
- ✅ Detaillierte Statistiken und Analysen
- ✅ Flexible Konfiguration
- ✅ CLI-Integration für Automatisierung


