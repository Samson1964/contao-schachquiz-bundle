# Schachquiz-Bundle Changelog

## Version 1.0.1 (2026-09-27)

* Fix: Der Import übersprang Stellungsaufgaben mit gleichem Fragetext als vermeintliche
  Duplikate (etwa alle „Weiß am Zug. Welcher Zug setzt matt?“ nach der ersten). Als
  vorhanden gilt eine Frage jetzt nur, wenn Text **und** Stellung übereinstimmen.

## Version 1.0.0 (2026-09-26)

* Add: Backend-Modul „Schachquiz" mit Themen (`tl_schachquiz`) und Fragen
  (`tl_schachquiz_items`): Einfach- und Mehrfachauswahl, zwei bis sechs Antworten,
  Schwierigkeit 1–10, optionale Stellung als FEN mit wählbarer Brettansicht, Erklärung.
  Die FEN wird beim Speichern geprüft und vervollständigt.
* Add: Frontend-Modul „Schachquiz": Fragen mit Diagramm, Themenwahl, Wertungsanzeige
  und Erklärung nach dem Antworten. Bedienbar mit Tastatur (1–6, Enter).
* Add: Wertung nach Glicko-2 für Spieler und Fragen; jede Antwort ist eine Partie
  gegen die Frage. Die redaktionelle Stufe setzt nur die Anfangswertung der Frage.
  Neue Spieler starten mit einer Abweichung von 200 statt der üblichen 350, damit die
  ersten Antworten nicht um mehrere hundert Punkte ausschlagen.
* Add: Fragenauswahl nach Wertungsnähe mit Zufall: Stärkere Spieler bekommen
  schwerere Fragen. Eine einmal gestellte Frage kommt nie wieder, bei Mitgliedern
  dauerhaft über den Verlauf, bei Gästen für die Sitzung.
* Add: Gäste spielen mit einer Sitzungswertung, verändern aber die Wertung der Fragen
  nicht und erscheinen nicht in der Rangliste; per Moduleinstellung abschaltbar.
* Add: Frontend-Modul „Schachquiz-Rangliste" mit Mindestzahl an Antworten, wählbarem
  Namensformat und Hervorhebung des eigenen Platzes.
* Add: Backend-Modul „Quiz-Wertungen" zum Ansehen und Zurücksetzen der Mitgliederwertungen.
* Add: Import aus CSV (Excel-tauglich) und JSON, mit Fehlerliste je Zeile und
  Überspringen bereits vorhandener Fragen; 40 mitgelieferte Beispielfragen in fünf Themen.
* Add: Figurenrunde, die während der Denkphase dezent mitdenkt, bei richtigen Antworten
  jubelt und bei falschen umkippt; abschaltbar und still bei „Bewegung reduzieren".
* Add: Deutsche und englische Beschriftungen für Backend und Frontend; Meldungen der
  Schnittstelle kommen mit Code und werden in der Sprache der Seite angezeigt.
* Add: Lauffähig unter Contao 4.13 und Contao 5 aus derselben Fassung. Geprüft gegen
  Contao 4.13.58 und 5.7.7 mit PHP 8.4.24 (Unit-Tests und `tests/pruefstand.php`).
