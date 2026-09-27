# Schachquiz-Bundle für Contao

Ein Schachquiz nach dem Vorbild der Taktikaufgaben von lichess: Die Besucher
beantworten Fragen und bekommen dafür eine Wertung nach **Glicko-2**. Wer
richtig antwortet, steigt, und die nächsten Fragen werden schwerer. Wer danebenliegt,
bekommt leichtere Fragen. Eine kleine Runde Schachfiguren denkt mit, jubelt und
lässt die Köpfe hängen.

Lauffähig unter **Contao 4.13 und Contao 5** (geprüft mit 4.13.58 und 5.7.7),
PHP ab 8.1.

## Installation

```bash
composer require schachbulle/contao-schachquiz-bundle
```

Danach die Datenbank aktualisieren (Contao Manager oder `contao:migrate`).

## Einrichten

1. **Fragen anlegen:** Backend → Inhalte → Schachquiz. Ein Thema kann einen eigenen
   „Titel im Frontend" haben, der Besuchern statt des Titels gezeigt wird. Ein Thema anlegen und darin Fragen,
   oder über „Fragen importieren" eine CSV- oder JSON-Datei einspielen. Dort
   lassen sich auch die 40 mitgelieferten Beispielfragen mit einem Klick
   übernehmen.
2. **Modul „Schachquiz"** anlegen (Frontend-Modulgruppe Schachquiz) und in eine Seite einbinden.
   Einstellungen: welche Themen gelten (ohne Auswahl alle), ob Gäste mitspielen
   dürfen, ob die Figuren-Animationen laufen.
3. Optional das **Modul „Schachquiz-Rangliste"** anlegen: Art der Rangliste (siehe
   unten), Anzahl der Plätze,
   Mindestzahl an Antworten, Namensformat (Standard: „Anna S."). Ein angemeldetes
   Mitglied sieht seinen eigenen Platz immer, auch wenn er weiter hinten liegt
   (nach einer Leerzeile unter der Liste) oder es die Mindestzahl noch nicht
   erreicht hat (dann mit Hinweis, wie viele Antworten noch fehlen).

## Fragen

| Feld | Bedeutung |
| --- | --- |
| Frage | Fragetext, Zeilenumbrüche bleiben erhalten |
| Fragetyp | Einfachauswahl (eine richtige Antwort, ein Klick genügt) oder Mehrfachauswahl (eine oder mehrere; es zählt nur die vollständig richtige Auswahl) |
| Schwierigkeit | 1 (sehr leicht, Wertung 900) bis 10 (sehr schwer, 2250); Stufe 5 entspricht der Startwertung 1500 |
| Stellung (FEN) | Optional; wird als Diagramm neben der Frage gezeigt |
| Brettansicht | Automatisch (Seite am Zug unten), Weiß unten oder Schwarz unten |
| Antwort 1–6 | Mindestens zwei |
| Richtige Antwort(en) | Die Kontrollkästchen zeigen nach dem ersten Speichern die Antworttexte |
| Erklärung | Erscheint nach dem Antworten, egal ob richtig oder falsch |

In der Fragenliste steht neben der redaktionellen Stufe die tatsächliche, sobald
Mitglieder geantwortet haben, und die Trefferquote. So fallen Fragen auf, die
leichter oder schwerer sind als gedacht.

## Wertung

* Jede Antwort ist eine Glicko-2-Partie zwischen Spieler und Frage: Beide haben
  eine Wertung, eine Abweichung (Unsicherheit) und eine Volatilität.
* Eine richtige Antwort auf eine schwere Frage bringt viel, auf eine leichte wenig.
  Ein Fehler bei einer leichten Frage kostet viel, bei einer schweren wenig.
* Neue Spieler beginnen bei 1500 mit einer Abweichung von 200; ihre Wertung bewegt
  sich anfangs in größeren Schritten (etwa ±80 bei einer gleich schweren Frage) und
  beruhigt sich mit jeder Antwort. Wertungen mit
  einer Abweichung über 110 gelten als vorläufig und tragen ein „?".
* Die Stufe einer Frage setzt nur ihre Anfangswertung. Danach bestimmen die
  Antworten der Mitglieder, wie schwer die Frage wirklich ist.
* **Gäste** spielen mit einer Wertung, die nur für ihre Sitzung gilt. Sie verändern
  die Wertung der Fragen nicht und erscheinen nicht in der Rangliste.

## Ranglisten

Das Ranglistenmodul zeigt wahlweise:

| Art | Inhalt |
| --- | --- |
| Aktuelle Wertung | Die heutige Wertung aller Mitglieder. |
| Ewige Bestenliste | Die höchste je erreichte Wertung jedes Mitglieds, mit dem Tag, an dem sie erreicht wurde. Es zählt nur eine gefestigte Wertung (ohne „?"), damit ein zufälliger Ausschlag in den ersten Antworten nicht für immer oben steht. |
| Monatsstände | Die am Monatsersten gesicherte Rangliste. Besucher wählen den Monat aus einer Liste; vorgegeben ist der neueste. |

Zu jedem Mitglied speichert das Bundle außerdem die erste Nutzung und die höchste
Wertung mit Datum (im Backend unter „Quiz-Wertungen" zu sehen).

### Monatsersten sichern (Cronjob)

Die Sicherung läuft als Contao-Cronjob mit dem Intervall „monthly", also am Ersten
des Monats um 0 Uhr. Damit er pünktlich läuft, sollte auf dem Server der Cron von
Contao jede Minute angestoßen werden, etwa per Crontab:

```
* * * * * /pfad/zu/php /pfad/zu/contao/vendor/bin/contao-console contao:cron
```

Ohne Crontab stößt Contao seine Cronjobs bei Seitenaufrufen an, sofern das nicht
unter System → Einstellungen abgeschaltet ist. Dann läuft die Sicherung beim ersten
Besuch nach Mitternacht. Eine verpasste Sicherung wird bis zum 7. des Monats nachgeholt,
später nicht mehr, damit kein falscher Stand als Monatsersten erscheint. Jeder Monat
wird nur einmal gesichert.

Von Hand, etwa zum Testen:

```bash
vendor/bin/contao-console schachquiz:rangliste-sichern
```

Die gesicherten Stände stehen im Backend unter Schachquiz → „Monatsranglisten".

## Fragenauswahl

* Gezogen wird zufällig aus den acht Fragen, deren Wertung der des Spielers am
  nächsten liegt. Mit steigender Wertung werden die Fragen also schwerer.
* **Keine Frage zweimal:** Eine Frage gilt ab dem Stellen als gesehen, nicht erst
  nach der Antwort. Mitglieder führt das Bundle dauerhaft im Verlauf, Gäste für
  die Dauer der Sitzung. Sind alle Fragen erschöpft, sagt das Quiz das offen.
* Wer die Seite neu lädt, bekommt dieselbe offene Frage wieder. Eine unbequeme
  Frage lässt sich also nicht wegklicken. Ein Themenwechsel verwirft die offene
  Frage ohne Wertung; sie bleibt aber verbraucht.
* Im Backend unter Inhalte → Schachquiz → „Quiz-Wertungen" setzt das Löschen eines Eintrags
  ein Mitglied samt Verlauf zurück.

## Import

Backend → Schachquiz → „Fragen importieren". Als Ziel wird entweder ein
vorhandenes Thema gewählt, oder die Themen werden aus der Datei übernommen
(vorhandene gleichen Titels werden ergänzt). Fehlerhafte Zeilen erscheinen mit
Zeilennummer, die übrigen werden trotzdem übernommen. Eine Frage, deren Text und
Stellung im Thema schon vorkommen, wird übersprungen. Dieselbe Datei lässt sich also gefahrlos
erneut einspielen. Vorlagen stehen auf der Importseite zum Herunterladen bereit.

### CSV

Erste Zeile mit den Spaltennamen, Reihenfolge beliebig. Semikolon, Komma oder
Tabulator; UTF-8 oder die Kodierung, mit der Excel unter Windows speichert.

| Spalte | Pflicht | Inhalt |
| --- | --- | --- |
| `frage` | ja | Fragetext |
| `antwort1` … `antwort6` | mind. zwei | Antworttexte |
| `richtig` | ja | Nummern oder Buchstaben, z. B. `2`, `1,3`, `A;C` |
| `typ` | nein | `single` / `multiple` (auch `einfach`, `mehrfach`, `sc`, `mc`); fehlt er, entscheidet die Zahl der richtigen Antworten |
| `schwierigkeit` | nein | 1–10, Vorgabe 5 |
| `fen` | nein | Stellung; auch nur der Figurenteil |
| `brett` | nein | `auto`, `weiss`, `schwarz` |
| `erklaerung` | nein | Erklärung |
| `thema`, `beschreibung` | nein | Nur beim Import „Themen aus der Datei" |

### JSON

```json
{
    "themen": [
        {
            "titel": "Taktik",
            "fragen": [
                {
                    "frage": "Weiß am Zug. Welcher Zug setzt matt?",
                    "schwierigkeit": 2,
                    "fen": "6k1/5ppp/8/8/8/8/5PPP/4R1K1 w - - 0 1",
                    "antworten": ["Te8#", "Te7", "h3", "Kf1"],
                    "richtig": [1],
                    "erklaerung": "Das klassische Grundreihenmatt."
                }
            ]
        }
    ]
}
```

Statt `"richtig"` dürfen die Antworten auch Objekte sein:
`{"text": "Te8#", "richtig": true}`. Ein Objekt mit nur `"fragen"` oder eine
bloße Liste von Fragen wird ebenfalls verstanden.

## Anpassen

* **Templates:** `mod_schachquiz.html5` und `mod_schachquiz_rangliste.html5`.
* **Brett und Figuren:** Die Figuren sind Vektorgrafiken (Satz „Cburnett", bekannt
  von lichess und Wikipedia) in `bundles/contaoschachquiz/figuren/cburnett.svg`; das
  Brett ist standardmäßig blaugrau. Die Feldfarben lassen sich über `--sq-hell` und
  `--sq-dunkel` ändern, etwa `#f0d9b5`/`#b58863` für Holz oder `#eeeed2`/`#769656`
  für Grün.
* **Farben:** Alle Farben stehen als CSS-Variablen an `.schachquiz`
  (`--sq-hell`, `--sq-dunkel`, `--sq-akzent`, `--sq-richtig`, `--sq-falsch` …)
  und lassen sich im Theme überschreiben.
* **Texte:** Deutsch und Englisch liegen bei. Eigene Fassungen über
  `$GLOBALS['TL_LANG']['schachquiz']` in einer eigenen Sprachdatei. Die Fehlermeldungen
  beim Import und bei der FEN-Prüfung sind vorerst nur deutsch.
* **Animationen:** Pro Modul abschaltbar. Besucher mit der Systemeinstellung
  „Bewegung reduzieren" sehen die Stimmung nur an Farbe und Haltung der Figuren.

## Technik

* Das Quiz-Skript spricht über `POST /_schachquiz/{modul}/{frage|antwort}` mit dem
  Server. Die Lösung verlässt den Server erst mit der Auswertung, und angenommen
  wird nur die Antwort auf die gerade gestellte Frage.
* Gegen Anfragen fremder Seiten schützt ein eigener Header (`X-Schachquiz`) samt
  Prüfung des `Origin`. Contaos REQUEST_TOKEN passt hier nicht: Contao entfernt es
  aus Seiten ohne Cookie, und die erste Quizanfrage startet gerade erst die Sitzung.
* Tabellen: `tl_schachquiz`, `tl_schachquiz_items`, `tl_schachquiz_spieler`
  (eine Zeile je Mitglied), `tl_schachquiz_verlauf` (jede gestellte Frage),
  `tl_schachquiz_rangliste` (Monatsstände, mit den Namen zum Stichtag).

## Prüfstand

```bash
composer install --no-plugins
vendor/bin/phpunit
```

Die Unit-Tests prüfen Glicko-2 gegen Glickmans veröffentlichtes Rechenbeispiel,
die FEN-Prüfung, den Import (einschließlich der Beispieldateien) und die
Schwierigkeitsstufen.

```bash
php tests/pruefstand.php <pfad-zur-contao-installation>
```

Der Prüfstand bootet eine Testinstallation, in der das Bundle eingebunden ist, und
prüft Anmeldung, DCA, Import und den ganzen Quizablauf gegen die echte Datenbank:
keine Wiederholungen, schwerere Fragen für stärkere Spieler, Wertungen für Gäste
und Mitglieder. Prüfthema und Prüfmitglieder räumt er danach wieder ab.

## Lizenz

LGPL-3.0-or-later.

Ausgenommen sind die Schachfiguren in `src/Resources/public/figuren/cburnett.svg`:
Sie stammen von Wikimedia Commons (Cburnett, Rfc1394; bearbeitet von Stefan Haack
für cm-chessboard) und stehen unter CC BY-SA 3.0
(<https://creativecommons.org/licenses/by-sa/3.0/>). Der Lizenzhinweis steht auch
in der Datei selbst.
