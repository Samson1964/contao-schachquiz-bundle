<?php

declare(strict_types=1);

/*
 * Prüfstand für schachbulle/contao-schachquiz-bundle.
 *
 * Bootet den Contao-Kernel einer Testinstallation und prüft:
 * Anmeldung von Bundle, Modulen, Route und Rückrufen; DCA-Paletten gegen die
 * Felder; den Import der Beispielfragen; den Quizablauf für Gäste und
 * Mitglieder gegen die echte Datenbank — einschließlich der Regeln „keine
 * Frage zweimal“ und „stärkere Spieler bekommen schwerere Fragen“.
 *
 * Aufruf: php tests/pruefstand.php <pfad-zur-installation>
 *
 * Voraussetzung: Das Bundle ist in der Installation als Pfad-Paket
 * eingebunden, der Container-Cache (var/cache) wurde danach geleert und die
 * Tabellen sind angelegt (contao:migrate).
 *
 * Der Prüfstand legt ein Prüfthema und ein Prüfmitglied an und entfernt
 * beide samt Verlauf am Ende wieder. Vorhandene Themen bleiben unberührt.
 */

use Contao\Controller;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Contao\StringUtil;
use Contao\System;
use Schachbulle\ContaoSchachquizBundle\Backend\ImportModul;
use Schachbulle\ContaoSchachquizBundle\Controller\QuizController;
use Schachbulle\ContaoSchachquizBundle\Import\FragenLeser;
use Schachbulle\ContaoSchachquizBundle\Import\FragenSpeicher;
use Schachbulle\ContaoSchachquizBundle\Quiz\Fragenauswahl;
use Schachbulle\ContaoSchachquizBundle\Quiz\QuizDienst;
use Schachbulle\ContaoSchachquizBundle\Quiz\Quizeinstellung;
use Schachbulle\ContaoSchachquizBundle\Quiz\Rangliste;
use Schachbulle\ContaoSchachquizBundle\Quiz\TeilnehmerSpeicher;
use Schachbulle\ContaoSchachquizBundle\Wertung\Glicko2;
use Schachbulle\ContaoSchachquizBundle\Wertung\Schwierigkeit;
use Schachbulle\ContaoSchachquizBundle\Wertung\Wertungsstand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$root = $argv[1] ?? null;

if (!$root || !is_dir($root)) {
    fwrite(STDERR, "Pfad zur Installation fehlt\n");
    exit(1);
}

require $root.'/vendor/autoload.php';

$fehler = 0;

/**
 * Gibt eine Prüfzeile aus und zählt Fehlschläge.
 *
 * @param string $name Bezeichnung der Prüfung
 * @param bool   $ok   Ergebnis
 * @param string $info Zusatzangabe, die in Klammern angehängt wird
 */
function melde(string $name, bool $ok, string $info = ''): void
{
    global $fehler;

    if (!$ok) {
        ++$fehler;
    }

    printf("%-66s %s%s\n", $name, $ok ? 'OK' : 'FEHLER', '' !== $info ? '  ('.$info.')' : '');
}

/**
 * Erzeugt eine frische Sitzung ohne Cookie und ohne Dateisystem.
 *
 * @return Session Die Sitzung
 */
function sitzung(): Session
{
    return new Session(new MockArraySessionStorage());
}

putenv('APP_ENV=prod');
$_SERVER['APP_ENV'] = 'prod';

$kernel = ContaoKernel::fromInput($root, new ArrayInput([]));
$kernel->boot();
$container = $kernel->getContainer();

echo 'Contao '.ContaoCoreBundle::getVersion().', PHP '.PHP_VERSION."\n";
echo str_repeat('-', 78)."\n";

melde('Bundle im Kernel angemeldet', isset($kernel->getBundles()['ContaoSchachquizBundle']));

$request = Request::create('http://localhost/');
$request->attributes->set('_scope', 'frontend');
$container->get('request_stack')->push($request);
$container->get('contao.framework')->initialize();

// --- Anmeldung -----------------------------------------------------------

melde('Frontend-Modul schachquiz angemeldet', isset($GLOBALS['FE_MOD']['schachquiz']['schachquiz']));
melde('Frontend-Modul schachquiz_rangliste angemeldet', isset($GLOBALS['FE_MOD']['schachquiz']['schachquiz_rangliste']));
melde('Backend-Modul mit Import-Rückruf', ($GLOBALS['BE_MOD']['schachquiz']['schachquiz']['import'] ?? null) === [ImportModul::class, 'zeige']);
melde('Import-Seite als öffentlicher Dienst', $container->has(ImportModul::class));
melde('Quiz-Schnittstelle als öffentlicher Dienst', $container->has(QuizController::class));

try {
    $adresse = $container->get('router')->generate('schachquiz_aktion', ['modul' => 7, 'aktion' => 'frage']);
} catch (Throwable $e) {
    $adresse = 'AUSNAHME: '.$e->getMessage();
}

melde('Route schachquiz_aktion', '/_schachquiz/7/frage' === $adresse, $adresse);

// --- DCA -----------------------------------------------------------------

foreach (['tl_schachquiz', 'tl_schachquiz_items', 'tl_schachquiz_spieler', 'tl_schachquiz_verlauf', 'tl_module'] as $tabelle) {
    Controller::loadDataContainer($tabelle);
    System::loadLanguageFile($tabelle, 'de');
}

System::loadLanguageFile('default', 'de');
System::loadLanguageFile('modules', 'de');

$rueckrufe = $GLOBALS['TL_DCA']['tl_schachquiz_items']['list']['sorting']['child_record_callback'] ?? null;
melde('child_record_callback der Fragen verdrahtet', is_array($rueckrufe) || is_callable($rueckrufe));
melde('save_callback der FEN verdrahtet', !empty($GLOBALS['TL_DCA']['tl_schachquiz_items']['fields']['fen']['save_callback']));
melde('options_callback der Modulthemen verdrahtet', !empty($GLOBALS['TL_DCA']['tl_module']['fields']['schachquiz_themen']['options_callback']));

foreach ([['tl_schachquiz', 'default'], ['tl_schachquiz_items', 'default'], ['tl_module', 'schachquiz'], ['tl_module', 'schachquiz_rangliste']] as [$tabelle, $palette]) {
    $felder = $GLOBALS['TL_DCA'][$tabelle]['fields'];
    $fehlend = [];

    foreach (preg_split('/[;,]/', preg_replace('/\{[^}]+\}/', '', $GLOBALS['TL_DCA'][$tabelle]['palettes'][$palette] ?? '')) as $feld) {
        $feld = trim($feld);

        if ('' !== $feld && !isset($felder[$feld])) {
            $fehlend[] = $feld;
        }
    }

    melde("Palette $tabelle.$palette: alle Felder definiert", [] === $fehlend, implode(', ', $fehlend));
}

melde('Beschriftung FMD.schachquiz vorhanden', !empty($GLOBALS['TL_LANG']['FMD']['schachquiz']));
melde('Frontend-Texte vorhanden', !empty($GLOBALS['TL_LANG']['schachquiz']['richtig']));

// --- Tabellen ------------------------------------------------------------

$db = $container->get('database_connection');
$schema = $db->createSchemaManager();

foreach (['tl_schachquiz', 'tl_schachquiz_items', 'tl_schachquiz_spieler', 'tl_schachquiz_verlauf'] as $tabelle) {
    melde("Tabelle $tabelle angelegt", $schema->tablesExist([$tabelle]));
}

melde('Spalte tl_schachquiz_items.brett angelegt', isset($schema->listTableColumns('tl_schachquiz_items')['brett']));

// --- Importseite im Backend ------------------------------------------------

$beRequest = Request::create('http://localhost/contao?do=schachquiz&key=import');
$beRequest->attributes->set('_scope', 'backend');
$beRequest->setSession(sitzung());
$container->get('request_stack')->push($beRequest);

// Im echten Aufruf erledigt das der CsrfTokenCookieSubscriber bei kernel.request.
// Der Speicher ist ein privater Dienst und nur über den Token-Manager erreichbar.
$speicherfeld = new ReflectionProperty(Symfony\Component\Security\Csrf\CsrfTokenManager::class, 'storage');
$speicherfeld->setAccessible(true);
$speicherfeld->getValue($container->get('contao.csrf.token_manager'))->initialize([]);

try {
    $seite = $container->get(ImportModul::class)->zeige();
} catch (Throwable $e) {
    $seite = 'AUSNAHME: '.$e->getMessage();
}

$container->get('request_stack')->pop();
melde('Importseite rendert Formular mit Datei- und Themenauswahl', str_contains($seite, 'name="datei"') && str_contains($seite, 'name="ziel"') && str_contains($seite, 'REQUEST_TOKEN'), str_starts_with($seite, 'AUSNAHME') ? $seite : '');

// --- Import der Beispielfragen -------------------------------------------

$beispiel = dirname(__DIR__).'/src/Resources/beispiel/schachquiz-beispiel.json';
$ergebnis = (new FragenLeser())->lese((string) file_get_contents($beispiel), 'beispiel.json');

$db->insert('tl_schachquiz', ['tstamp' => time(), 'title' => 'Prüfstand '.uniqid(), 'published' => '1']);
$thema = (int) $db->lastInsertId();

$speicher = new FragenSpeicher($db);
$zaehler = $speicher->speichere($ergebnis, $thema);
melde('40 Beispielfragen ins Prüfthema importiert', 40 === $zaehler['fragen'], json_encode($zaehler));

$zweiter = $speicher->speichere($ergebnis, $thema);
melde('Zweiter Import überspringt alle 40', 0 === $zweiter['fragen'] && 40 === $zweiter['uebersprungen']);

$gespeichert = $db->fetchAssociative("SELECT * FROM tl_schachquiz_items WHERE pid = ? AND frage LIKE 'Welche dieser Spieler waren%'", [$thema]);
melde('Mehrfachauswahl als serialisiertes Array gespeichert', ['1', '3', '5'] === StringUtil::deserialize($gespeichert['richtig'] ?? ''));
melde('Anfangswertung nach Stufe 6', Schwierigkeit::wertung(6) === (float) $gespeichert['wertung']);

// --- Quiz als Gast -------------------------------------------------------

$glicko = new Glicko2();
$auswahl = new Fragenauswahl($db);
$teilnehmerSpeicher = new TeilnehmerSpeicher($db);
$quiz = new QuizDienst($db, $auswahl, $teilnehmerSpeicher, $glicko);
$einstellung = new Quizeinstellung(990001, [$thema], true);

/**
 * Liest die richtigen Antwortnummern einer Frage aus der Datenbank.
 */
$loesung = static fn (int $id): array => array_map('intval', StringUtil::deserialize($db->fetchOne('SELECT richtig FROM tl_schachquiz_items WHERE id = ?', [$id]), true));

$gast = sitzung();
$erste = $quiz->frage($einstellung, 0, $gast, 0);
melde('Gast bekommt eine Frage', 'ok' === $erste['status']);
melde('Frage enthält keine Lösung', !isset($erste['frage']['richtig']) && !str_contains(json_encode($erste), 'erklaerung'));
melde('Erneutes Laden liefert dieselbe Frage', $quiz->frage($einstellung, 0, $gast, 0)['frage']['id'] === $erste['frage']['id']);

$falscheNummer = array_values(array_diff(array_column($erste['frage']['antworten'], 'nr'), $loesung($erste['frage']['id'])))[0];
$ergebnisFalsch = $quiz->antwort($einstellung, 0, $gast, [$falscheNummer]);
melde('Falsche Antwort wird als falsch gewertet', 'ok' === $ergebnisFalsch['status'] && false === $ergebnisFalsch['richtig']);
melde('Wertung sinkt nach falscher Antwort', $ergebnisFalsch['differenz'] < 0, (string) $ergebnisFalsch['differenz']);
melde('Zweite Antwort auf dieselbe Frage abgewiesen', 'fehler' === $quiz->antwort($einstellung, 0, $gast, [$falscheNummer])['status']);

$gesehen = [$erste['frage']['id'] => true];
$wiederholt = false;
$richtigeSerie = 0;

for ($i = 1; $i < 40; ++$i) {
    $daten = $quiz->frage($einstellung, 0, $gast, 0);

    if ('ok' !== $daten['status']) {
        break;
    }

    $id = $daten['frage']['id'];
    $wiederholt = $wiederholt || isset($gesehen[$id]);
    $gesehen[$id] = true;

    $antwort = $quiz->antwort($einstellung, 0, $gast, $loesung($id));
    $richtigeSerie += $antwort['richtig'] ? 1 : 0;
}

melde('Gast bekommt alle 40 Fragen genau einmal', 40 === count($gesehen) && !$wiederholt, count($gesehen).' verschiedene');
melde('Richtige Antworten werden als richtig gewertet', 39 === $richtigeSerie);

$leer = $quiz->frage($einstellung, 0, $gast, 0);
melde('Danach: „alle Fragen gehabt“ statt Wiederholung', 'leer' === $leer['status'] && str_contains($leer['meldung'], 'alle Fragen'), $leer['meldung'] ?? '');
melde('Gastwertung nach 39 richtigen deutlich gestiegen', $leer['spieler']['wertung'] > 1800 && $leer['spieler']['gast'], (string) $leer['spieler']['wertung']);
melde('Gäste verändern die Wertung der Fragen nicht', 0 === (int) $db->fetchOne('SELECT SUM(anzahl) FROM tl_schachquiz_items WHERE pid = ?', [$thema]));

melde('Ohne Gastfreigabe keine Frage für Gäste', 'fehler' === $quiz->frage(new Quizeinstellung(990002, [$thema], false), 0, sitzung(), 0)['status']);

// Themenwechsel: Die offene Frage verfällt, bleibt aber verbraucht.
$wechsel = sitzung();
$offen = $quiz->frage($einstellung, 0, $wechsel, 0)['frage']['id'];
$quiz->frage($einstellung, 0, $wechsel, $thema);
melde('Verlassene Frage gilt als gesehen', in_array($offen, $teilnehmerSpeicher->gastFragen($wechsel), true));

// Neuladen bei gewähltem Thema: Das Skript kennt das Thema nicht mehr.
$gewaehlt = $quiz->frage($einstellung, 0, $wechsel, $thema);
$neuGeladen = $quiz->frage($einstellung, 0, $wechsel, QuizDienst::THEMA_WIE_ZULETZT);
melde('Neuladen bei gewähltem Thema liefert dieselbe Frage und das Thema', $neuGeladen['frage']['id'] === $gewaehlt['frage']['id'] && $thema === $neuGeladen['thema']);

// --- Schwierigkeit folgt der Wertung --------------------------------------

/**
 * Zieht für eine vorgegebene Gastwertung zwanzig erste Fragen und gibt die
 * mittlere Stufe zurück.
 */
$mittlereStufe = static function (float $wertung) use ($quiz, $einstellung): float {
    $summe = 0;

    for ($i = 0; $i < 20; ++$i) {
        $s = sitzung();
        $s->set(TeilnehmerSpeicher::SITZUNG_GAST, (new Wertungsstand($wertung, 60))->alsZeile());
        $summe += $quiz->frage($einstellung, 0, $s, 0)['frage']['stufe'];
    }

    return $summe / 20;
};

$schwach = $mittlereStufe(1000.0);
$mittel = $mittlereStufe(1500.0);
$stark = $mittlereStufe(2100.0);
melde('Stärkere Spieler bekommen schwerere Fragen', $schwach < $mittel && $mittel < $stark, sprintf('Stufe Ø %.1f / %.1f / %.1f bei 1000 / 1500 / 2100', $schwach, $mittel, $stark));

// --- Quiz als Mitglied ---------------------------------------------------

$db->insert('tl_member', [
    'tstamp' => time(),
    'firstname' => 'Paula',
    'lastname' => 'Prüfstand',
    'username' => 'pruefstand_'.uniqid(),
    'email' => 'pruefstand@example.invalid',
    'dateAdded' => time(),
]);
$mitglied = (int) $db->lastInsertId();

$ms = sitzung();
$frageM = $quiz->frage($einstellung, $mitglied, $ms, 0);
melde('Verlaufseintrag entsteht schon beim Stellen', 1 === (int) $db->fetchOne("SELECT COUNT(*) FROM tl_schachquiz_verlauf WHERE member = ? AND antwort = ''", [$mitglied]));

$vorFrage = (float) $db->fetchOne('SELECT wertung FROM tl_schachquiz_items WHERE id = ?', [$frageM['frage']['id']]);
$antwortM = $quiz->antwort($einstellung, $mitglied, $ms, $loesung($frageM['frage']['id']));
$nachFrage = $db->fetchAssociative('SELECT wertung, anzahl, anzahl_richtig FROM tl_schachquiz_items WHERE id = ?', [$frageM['frage']['id']]);
$spieler = $db->fetchAssociative('SELECT * FROM tl_schachquiz_spieler WHERE member = ?', [$mitglied]);
$verlauf = $db->fetchAssociative('SELECT * FROM tl_schachquiz_verlauf WHERE member = ?', [$mitglied]);

melde('Mitglied: richtige Antwort erhöht die Wertung', $antwortM['richtig'] && $antwortM['differenz'] > 0);
melde('Mitglied: Spielerdatensatz angelegt', is_array($spieler) && 1 === (int) $spieler['anzahl'] && 1 === (int) $spieler['richtig']);
melde('Mitglied: Frage verliert Wertung, Zähler steigen', (float) $nachFrage['wertung'] < $vorFrage && 1 === (int) $nachFrage['anzahl'] && 1 === (int) $nachFrage['anzahl_richtig']);
melde('Mitglied: Verlaufseintrag um die Antwort ergänzt', is_array($verlauf) && '1' === $verlauf['richtig'] && '' !== $verlauf['antwort']);

// Neue Sitzung (etwa neuer Tag, anderer Rechner): Die Frage kommt nicht wieder.
$wieder = false;

for ($i = 0; $i < 39; ++$i) {
    $d = $quiz->frage($einstellung, $mitglied, sitzung(), 0);
    $wieder = $wieder || ('ok' === $d['status'] && $d['frage']['id'] === $frageM['frage']['id']);
}

melde('Mitglied: gesehene Frage kommt auch in neuer Sitzung nicht wieder', !$wieder);
melde('Mitglied: nach 40 gestellten Fragen ist Schluss', 'leer' === $quiz->frage($einstellung, $mitglied, sitzung(), 0)['status']);

$platz = (new Rangliste($db))->eigenerPlatz($mitglied, 0, 'kurz');
melde('Rangliste: Name im Kurzformat', 'Paula P.' === ($platz['name'] ?? null), $platz['name'] ?? '');

// Anmeldung nach dem Ziehen als Gast: Der Verlauf wird nachgetragen.
$wechselSitzung = sitzung();
$db->insert('tl_member', ['tstamp' => time(), 'firstname' => 'Gustav', 'lastname' => 'Gast', 'username' => 'pruefstand_'.uniqid(), 'email' => 'gast@example.invalid', 'dateAdded' => time()]);
$spaet = (int) $db->lastInsertId();
$gastFrage = $quiz->frage($einstellung, 0, $wechselSitzung, 0)['frage']['id'];
$nachLogin = $quiz->antwort($einstellung, $spaet, $wechselSitzung, $loesung($gastFrage));
melde('Als Gast gezogen, als Mitglied beantwortet: Verlauf vorhanden', 'ok' === $nachLogin['status'] && 1 === (int) $db->fetchOne('SELECT COUNT(*) FROM tl_schachquiz_verlauf WHERE member = ? AND item = ?', [$spaet, $gastFrage]));

// --- Aufräumen -----------------------------------------------------------

$db->executeStatement('DELETE FROM tl_schachquiz_verlauf WHERE member IN (?, ?)', [$mitglied, $spaet]);
$db->executeStatement('DELETE FROM tl_schachquiz_spieler WHERE member IN (?, ?)', [$mitglied, $spaet]);
$db->executeStatement('DELETE FROM tl_member WHERE id IN (?, ?)', [$mitglied, $spaet]);
$db->executeStatement('DELETE FROM tl_schachquiz_items WHERE pid = ?', [$thema]);
$db->executeStatement('DELETE FROM tl_schachquiz WHERE id = ?', [$thema]);

echo str_repeat('-', 78)."\n";
echo 0 === $fehler ? "Alle Prüfungen bestanden.\n" : "$fehler Prüfung(en) fehlgeschlagen.\n";

exit($fehler > 0 ? 1 : 0);
