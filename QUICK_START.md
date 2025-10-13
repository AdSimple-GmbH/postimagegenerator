# Quick Start Guide - AI Post Generator Dashboard

## 🚀 Schnellstart in 3 Minuten

### 1. Dashboard öffnen

Gehe im WordPress Admin zu:
**AI Dashboard** (im Hauptmenü mit 📊 Icon)

Oder direkt: http://localhost:8080/wp-admin/admin.php?page=ai-post-dashboard

### 2. Ersten Test durchführen

#### Option A: Via Dashboard (GUI)

1. **Test-Post erstellen**
   - Klicke auf "Neuen Test-Post erstellen"
   - Warte 1-2 Sekunden

2. **Konfiguration**
   - Länge: `Mittel (800-1200 Wörter)` (vorausgewählt)
   - Auto-Korrektur: ✓ Aktiviert
   - Max. Korrekturen: `2`

3. **Generierung starten**
   - Klicke auf "Content generieren"
   - Warte 30-60 Sekunden
   
4. **Ergebnis ansehen**
   - Wortanzahl prüfen
   - Korrekturen sehen
   - Content-Vorschau lesen
   - Statistiken aktualisieren

#### Option B: Via WP-CLI (Kommandozeile)

```bash
# Einfachster Test (erstellt automatisch Test-Post)
docker exec -it postimagegenerator-wordpress-1 wp ai-post test

# Mit automatischer Speicherung
docker exec -it postimagegenerator-wordpress-1 wp ai-post test --save

# Statistiken anzeigen
docker exec -it postimagegenerator-wordpress-1 wp ai-post stats
```

### 3. Ergebnis verstehen

**Erfolgreiche Generierung zeigt:**
- ✅ **Wortanzahl**: z.B. 950 Wörter (Ziel: 800-1200) ✓
- 🔄 **Korrekturen**: z.B. 1 (Initial: 780 → Final: 950)
- 📈 **Änderung**: +21.8%
- 📁 **Kategorie**: Automatisch generiert
- 🏷️ **Tags**: 7-10 relevante Tags

**Bei Problemen:**
- ❌ Fehler: "OpenAI API key is not set"
  - **Lösung**: API Key konfigurieren in Einstellungen → AI Featured Image

## 🎯 Häufige Anwendungsfälle

### Use Case 1: Verschiedene Längen testen

```bash
# Via CLI für schnelles Testing
for length in short medium long verylong; do
  echo "=== Testing $length ==="
  docker exec -it postimagegenerator-wordpress-1 wp ai-post test \
    --length=$length \
    --save
done
```

### Use Case 2: Mit/Ohne Auto-Korrektur vergleichen

**Dashboard:**
1. Ersten Test MIT Auto-Korrektur durchführen
2. Gleichen Post wählen
3. Zweiten Test OHNE Auto-Korrektur durchführen (Checkbox deaktivieren)
4. Ergebnisse vergleichen

**CLI:**
```bash
# Mit Auto-Korrektur
docker exec -it postimagegenerator-wordpress-1 wp ai-post test --post_id=123

# Ohne Auto-Korrektur
docker exec -it postimagegenerator-wordpress-1 wp ai-post test --post_id=123 --no-auto-correct
```

### Use Case 3: Content direkt speichern

**Nur via CLI möglich:**
```bash
docker exec -it postimagegenerator-wordpress-1 wp ai-post test \
  --length=medium \
  --save
```

Spart Zeit, da Content automatisch im Post gespeichert wird.

### Use Case 4: Performance-Test

```bash
# Zeit messen
time docker exec -it postimagegenerator-wordpress-1 wp ai-post test --length=long

# Typische Zeiten:
# short:    20-40s
# medium:   30-60s
# long:     45-90s
# verylong: 60-120s
```

## 📊 Dashboard-Features nutzen

### Statistiken interpretieren

- **Gesamt generiert**: Wie viele Posts insgesamt erstellt wurden
- **Heute**: Posts des aktuellen Tages
- **Erfolgsrate**: % der Posts mit gültiger Länge (Ziel: >80%)
- **Ø Korrekturen**: Durchschnittliche Anzahl (Ziel: <2)

**Gute Werte:**
- Erfolgsrate: 85-95%
- Ø Korrekturen: 0.5-1.5

**Optimierung bei schlechten Werten:**
- Erfolgsrate <70%: Toleranz in Code erhöhen oder bessere Prompts
- Ø Korrekturen >2: Max. Korrekturen erhöhen oder Längen anpassen

### Ergebnisse analysieren

**Grüner Status ✅**
- Länge passt perfekt
- Keine oder wenige Korrekturen nötig
- → Content kann verwendet werden

**Gelber Status ⚠️**
- Länge außerhalb Toleranz (±10%)
- Viele Korrekturen durchgeführt
- → Content prüfen, evtl. neu generieren

### Korrektur-Verlauf verstehen

**Beispiel-Ausgabe:**
```
Korrektur-Verlauf:
1. Erweitert ↑: 280 → 420 Wörter
```

**Bedeutung:**
- Initial: 280 Wörter (zu kurz für "short" 300-500)
- GPT hat Content erweitert
- Final: 420 Wörter (jetzt gültig ✓)

## 🔧 Konfigurationstipps

### Optimale Einstellungen

**Für Produktions-Content:**
- Auto-Korrektur: ✓ Aktiviert
- Max. Korrekturen: 2-3
- Länge: Je nach Bedarf

**Für schnelles Testing:**
- Auto-Korrektur: ✗ Deaktiviert
- Max. Korrekturen: 0
- Länge: short

**Für beste Qualität:**
- Auto-Korrektur: ✓ Aktiviert
- Max. Korrekturen: 3
- Länge: medium oder long

### Längen-Empfehlungen

| Content-Typ | Empfohlene Länge | Grund |
|-------------|------------------|-------|
| Blog-Posts | medium (800-1200) | Guter Mix aus Detail und Lesbarkeit |
| News-Artikel | short (300-500) | Schnelle Information |
| Guides | long (1500-2000) | Umfassende Erklärungen |
| Whitepapers | verylong (2500-3000) | Tiefe Analyse |

## ⚡ Pro-Tipps

1. **Batch-Testing**
   ```bash
   # 10 Posts automatisch generieren und speichern
   for i in {1..10}; do
     docker exec -it postimagegenerator-wordpress-1 wp ai-post test --save
     sleep 5
   done
   ```

2. **JSON-Output für Automatisierung**
   ```bash
   # Stats als JSON
   docker exec -it postimagegenerator-wordpress-1 wp ai-post stats --format=json
   ```

3. **Logs überwachen**
   ```bash
   # Live-Log verfolgen
   docker exec -it postimagegenerator-wordpress-1 tail -f \
     /var/www/html/wp-content/uploads/ai-featured-image.log
   ```

4. **Dashboard-Zugriff direkt bookmarken**
   - Speichere den Dashboard-Link als Lesezeichen
   - Für schnellen Zugriff

5. **Statistiken regelmäßig prüfen**
   - Täglich Erfolgsrate checken
   - Bei Verschlechterung untersuchen

## 🐛 Häufige Probleme

### Problem: "Timeout"
**Lösung:**
```bash
# Kürzere Länge wählen
docker exec -it postimagegenerator-wordpress-1 wp ai-post test --length=short

# Oder max_corrections reduzieren
docker exec -it postimagegenerator-wordpress-1 wp ai-post test --max-corrections=1
```

### Problem: "API key not set"
**Lösung:**
1. http://localhost:8080/wp-admin/options-general.php?page=ai-featured-image
2. OpenAI API Key eintragen
3. Speichern

### Problem: Content hat falsche Sprache
**Lösung:**
- Prüfe Post-Titel (sollte deutsch sein)
- Evtl. Excerpt hinzufügen mit deutschem Text
- System-Prompt ist auf Deutsch konfiguriert

### Problem: Dashboard lädt nicht
**Lösung:**
```bash
# Cache leeren
docker exec -it postimagegenerator-wordpress-1 wp cache flush

# Browser-Cache leeren
# Strg + Shift + R (Windows/Linux)
# Cmd + Shift + R (Mac)
```

## 📚 Weiterführende Dokumentation

- **Dashboard-Anleitung**: [DASHBOARD_ANLEITUNG.md](DASHBOARD_ANLEITUNG.md)
- **Feature-Dokumentation**: [FEATURE_LENGTH_CORRECTION.md](FEATURE_LENGTH_CORRECTION.md)
- **REST API Docs**: [documentation/setup.md](documentation/setup.md)
- **Test-Skript**: [test-rest-api-length-correction.sh](test-rest-api-length-correction.sh)

## ✅ Checkliste: Erste Schritte

- [ ] Docker Container läuft
- [ ] WordPress erreichbar (http://localhost:8080)
- [ ] Als Admin eingeloggt
- [ ] OpenAI API Key konfiguriert
- [ ] Dashboard geöffnet (AI Dashboard im Menü)
- [ ] Ersten Test-Post erstellt
- [ ] Erste Generierung durchgeführt
- [ ] Ergebnis analysiert
- [ ] Statistiken geprüft
- [ ] WP-CLI Command getestet

## 🎉 Nächste Schritte

Nach dem Schnellstart:

1. **Verschiedene Längen testen** um Gefühl für Generierungszeit zu bekommen
2. **Auto-Korrektur optimieren** durch Testing verschiedener max_corrections
3. **Produktions-Workflow entwickeln** (Dashboard oder CLI?)
4. **Statistiken monitoren** für Qualitätssicherung
5. **Content-Review-Prozess** etablieren

Viel Erfolg! 🚀


