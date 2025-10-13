# Test-Anleitung: AI-Post-Generierung - Alle Längen

## Schnell-Test (alle 4 Längen manuell)

### 1. KURZ (300-500 Worte)
- Neuer Post: http://localhost:8080/wp-admin/post-new.php
- Titel: "Machine Learning Grundlagen"
- Länge: **kurz (300-500)**
- Generieren → Wortanzahl prüfen
- Erwartung: **300-500 Worte**

### 2. MITTEL (800-1200 Worte)
- Neuer Post erstellen
- Titel: "Deep Learning in der Bildverarbeitung"
- Länge: **mittel (800-1200)**
- Generieren → Wortanzahl prüfen
- Erwartung: **800-1200 Worte**

### 3. LANG (1500-2000 Worte)
- Neuer Post erstellen
- Titel: "Künstliche Intelligenz in der Automobilindustrie"
- Länge: **lang (1500-2000)**
- Generieren → Wortanzahl prüfen
- Erwartung: **1500-2000 Worte**

### 4. SEHR LANG (2500+ Worte)
- Neuer Post erstellen
- Titel: "KI-gestützte Automatisierung in der Industrie 4.0"
- Länge: **sehr lang (2500+)**
- Generieren → Wortanzahl prüfen (kann 90-120 Sekunden dauern!)
- Erwartung: **2500-3000 Worte**

---

## Wortanzahl prüfen

### In WordPress:
1. Post öffnen
2. Rechts unten im Editor: "Wörter: XXX" (bei Gutenberg)
3. Oder: Content kopieren → https://wordcounter.net/

### Mit WP CLI:
```bash
# Post-Content mit Wortanzahl anzeigen
docker compose run --rm wpcli wp post get 35 --field=post_content | wc -w
```

---

## Verbesserte Prompts - Was wurde geändert:

### Vorher (Version 1):
```
Target length: 800-1200 words
Minimum length: 800 words
```
❌ Ergebnis: 426 Worte (53% vom Minimum)

### Nachher (Version 2 - Aktuell):
```
STEP 1 - CREATE THESE EXACT SECTIONS:

<h2>Einleitung</h2>
250+ words

<h2>Was ist [topic]?</h2>  
180+ words (3 paragraphs)

<h2>Warum ist [topic] wichtig?</h2>
180+ words (3 paragraphs)

... (12 weitere vorgegebene Sektionen für "lang")

<h2>Fazit</h2>
200+ words

CALCULATION: 250 + (12 × 180) + 200 = 2610 words
```

**Neue Strategie:**
- ✅ Template mit exakten Sektionen
- ✅ Mathematische Berechnung der Sektionsanzahl
- ✅ Jede Sektion hat Mindest-Wortanzahl
- ✅ Temperature reduziert (0.3 statt 0.7)
- ✅ max_completion_tokens für bessere Kontrolle

---

## Test-Ergebnisse dokumentieren

Bitte trage hier deine Ergebnisse ein:

| Länge | Ziel | Erhalten | Status | Post-ID |
|-------|------|----------|--------|---------|
| kurz | 300-500 | ___ | ⬜ | ___ |
| mittel | 800-1200 | ___ | ⬜ | ___ |
| lang | 1500-2000 | ___ | ⬜ | ___ |
| sehr lang | 2500-3000 | ___ | ⬜ | ___ |

Status: ✅ = Erreicht | ⚠️ = Zu kurz | ❌ = Weit verfehlt

---

## Erwartete Verbesserung

**Bisherige Tests:**
- mittel (800-1200): 426 Worte → **53% vom Minimum** ❌
- sehr lang (2500+): 1268 Worte → **51% vom Minimum** ❌
- lang (1500-2000): 913 Worte → **61% vom Minimum** ❌

**Mit neuen Prompts:**
- Sollte **deutlich näher** an die Zielvorgaben kommen
- Template-Ansatz erzwingt Struktur
- Mathematische Berechnung stellt sicher, dass genug Sektionen erstellt werden

---

## Wenn Tests fehlschlagen

Falls die Wortanzahl immer noch zu niedrig ist:

1. **GPT-4o Mini statt GPT-4o testen**
   - Eventuell befolgt das kleinere Modell Anweisungen besser

2. **Two-Step Approach**
   - Erst Outline generieren
   - Dann jede Sektion einzeln generieren

3. **Post-Processing**
   - Nach Generierung automatisch expandieren wenn zu kurz

4. **Alternative: Claude 3.5 Sonnet**
   - Nutzt andere API, befolgt Längen-Anweisungen oft besser

Sag mir die Ergebnisse und ich kann weiter optimieren!


