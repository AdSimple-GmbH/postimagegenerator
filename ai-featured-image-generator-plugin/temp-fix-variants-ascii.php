<?php
/**
 * Temporary script to fix prompt variants with ASCII encoding
 */

$variants = array(
	'short' => 'Schreibe einen deutschen Artikel zum Thema: {post_title}

Kontext: {post_excerpt}

WORTANZAHL-ANFORDERUNG:
- Zielbereich: {min_words} bis {max_words} Woerter
- NICHT weniger als {min_words} Woerter  
- NICHT mehr als {max_words} Woerter

STRUKTUR (5 Abschnitte):
1. Einleitung (~80 Woerter)
2. Was ist [Thema]? (~70 Woerter)
3. Hauptmerkmale (~70 Woerter)
4. Anwendung/Vorteile (~70 Woerter)
5. Fazit (~60 Woerter)

STIL:
- Informativ und praezise
- HTML: <h2> fuer Abschnitte, <p> fuer Absaetze
- Keine Code-Beispiele

JSON-Format (NUR dies):
{
  "content_html": "HTML-Artikel",
  "category_name": "passende Kategorie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]
}',

	'medium' => 'Schreibe einen deutschen Artikel zum Thema: {post_title}

Kontext: {post_excerpt}

WORTANZAHL-ANFORDERUNG:
- Zielbereich: {min_words} bis {max_words} Woerter

STRUKTUR (7 Abschnitte):
1. Einleitung (~150 Woerter) - 2-3 Absaetze
2. Was ist [Thema]? - Grundlagen (~140 Woerter)
3. Warum ist das wichtig? (~140 Woerter)
4. Hauptmerkmale und Funktionen (~150 Woerter)
5. Vorteile und Nutzen (~140 Woerter)
6. Herausforderungen und Loesungen (~140 Woerter)
7. Fazit und Ausblick (~140 Woerter)

JSON-Format (NUR dies):
{
  "content_html": "HTML-Artikel",
  "category_name": "passende Kategorie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]
}',

	'long' => 'Schreibe einen deutschen Artikel zum Thema: {post_title}

Kontext: {post_excerpt}

WORTANZAHL: {min_words} bis {max_words} Woerter

STRUKTUR (9 Abschnitte a ~200 Woerter):
1. Einleitung
2. Grundlagen und Definition
3. Bedeutung und Relevanz
4. Hauptmerkmale im Detail
5. Vorteile und Chancen
6. Nachteile und Risiken
7. Anwendungsbereiche
8. Best Practices
9. Fazit

JSON-Format (NUR dies):
{
  "content_html": "HTML-Artikel",
  "category_name": "passende Kategorie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]
}',

	'verylong' => 'Schreibe einen deutschen Artikel zum Thema: {post_title}

Kontext: {post_excerpt}

WORTANZAHL: {min_words} bis {max_words} Woerter

STRUKTUR (11 Abschnitte a ~250 Woerter):
1. Einleitung
2. Grundlagen und Hintergrund
3. Bedeutung und Relevanz heute
4. Hauptmerkmale im Detail
5. Vorteile und Chancen
6. Nachteile und Herausforderungen
7. Anwendungsbereiche und Beispiele
8. Best Practices und Empfehlungen
9. Praktische Umsetzung
10. Zukunftsperspektiven
11. Fazit

JSON-Format (NUR dies):
{
  "content_html": "HTML-Artikel",
  "category_name": "passende Kategorie",
  "tags": ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6", "tag7"]
}'
);

// Update post meta
update_post_meta( 328, '_prompt_variants', $variants );

echo "Varianten erfolgreich aktualisiert (ASCII)!\n";


