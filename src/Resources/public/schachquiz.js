/*
 * Contao Schachquiz Bundle – Frontend-Skript.
 *
 * Holt Fragen über die Route /_schachquiz/{modul}/frage, schickt Antworten an
 * /_schachquiz/{modul}/antwort und steuert Diagramm, Antwortknöpfe und die
 * Figurenrunde. Kommt ohne Bibliotheken aus.
 *
 * @license LGPL-3.0-or-later
 */
(function () {
    'use strict';

    /**
     * Zeichnet ein Brett aus einer FEN in den Behälter.
     *
     * In der Ansicht „auto“ steht die Seite am Zug unten – wie bei den
     * Taktikaufgaben auf lichess. „weiss“ und „schwarz“ legen die untere
     * Seite fest, etwa für Fragen nach dem Namen einer Eröffnung.
     *
     * @param {HTMLElement} behaelter Nimmt das Brett auf
     * @param {string}      fen       Vollständige, serverseitig geprüfte FEN
     * @param {string}      ansicht   „auto“, „weiss“ oder „schwarz“
     * @param {object}      texte     Beschriftungen (weissAmZug, schwarzAmZug)
     * @param {string}      figuren   Adresse der SVG-Datei mit den Figuren; jede
     *                                Figur ist dort eine Gruppe mit der ID aus Farbe
     *                                und Buchstabe, etwa „wk“ oder „bn“
     */
    function zeichneBrett(behaelter, fen, ansicht, texte, figuren) {
        var teile = fen.split(/\s+/);
        var schwarz = teile[1] === 'b';
        var gedreht = ansicht === 'schwarz' || (ansicht !== 'weiss' && schwarz);
        var reihen = teile[0].split('/');
        var felder = [];

        reihen.forEach(function (reihe) {
            var zeile = [];
            reihe.split('').forEach(function (z) {
                if (/\d/.test(z)) {
                    for (var i = 0; i < +z; i++) { zeile.push(''); }
                } else {
                    zeile.push(z);
                }
            });
            felder.push(zeile);
        });

        var brett = document.createElement('div');
        brett.className = 'schachquiz__felder';
        brett.setAttribute('role', 'img');
        brett.setAttribute('aria-label', 'Stellung: ' + fen);

        for (var r = 0; r < 8; r++) {
            for (var s = 0; s < 8; s++) {
                // Bei gedrehtem Brett von h1 aus zeichnen.
                var reihe = gedreht ? 7 - r : r;
                var linie = gedreht ? 7 - s : s;
                var feld = document.createElement('span');
                var figur = felder[reihe][linie];

                feld.className = 'schachquiz__feld ' + ((reihe + linie) % 2 ? 'schachquiz__feld--dunkel' : 'schachquiz__feld--hell');

                if (figur) {
                    feld.appendChild(stein(figuren, (figur === figur.toUpperCase() ? 'w' : 'b') + figur.toLowerCase()));
                }

                // Koordinaten am linken und unteren Rand.
                if (s === 0) {
                    feld.appendChild(koordinate('schachquiz__koord--reihe', String(8 - reihe)));
                }
                if (r === 7) {
                    feld.appendChild(koordinate('schachquiz__koord--linie', 'abcdefgh'.charAt(linie)));
                }

                brett.appendChild(feld);
            }
        }

        var zug = document.createElement('p');
        zug.className = 'schachquiz__zug schachquiz__zug--' + (schwarz ? 'schwarz' : 'weiss');
        zug.textContent = schwarz ? texte.schwarzAmZug : texte.weissAmZug;

        behaelter.replaceChildren(brett, zug);
        behaelter.hidden = false;
    }

    /**
     * Erzeugt eine Figur als Verweis in die SVG-Datei.
     *
     * Mit <use> lädt der Browser die Datei nur einmal, egal wie viele Figuren
     * auf dem Brett stehen, und die Figuren bleiben in jeder Größe scharf.
     *
     * @param {string} figuren Adresse der SVG-Datei
     * @param {string} id      Figur, etwa „wk“ für den weißen König
     * @returns {SVGElement}
     */
    function stein(figuren, id) {
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        var use = document.createElementNS(ns, 'use');

        svg.setAttribute('viewBox', '0 0 40 40');
        svg.setAttribute('class', 'schachquiz__stein');
        svg.setAttribute('aria-hidden', 'true');
        use.setAttribute('href', figuren + '#' + id);
        svg.appendChild(use);

        return svg;
    }

    /**
     * Erzeugt eine Randbeschriftung für das Brett.
     *
     * @param {string} art  Zusatzklasse für Reihe oder Linie
     * @param {string} text Die Ziffer oder der Buchstabe
     * @returns {HTMLElement}
     */
    function koordinate(art, text) {
        var el = document.createElement('i');
        el.className = 'schachquiz__koord ' + art;
        el.textContent = text;
        return el;
    }

    /**
     * Steuert ein einzelnes Quiz auf der Seite.
     *
     * @param {HTMLElement} wurzel Das Element mit data-schachquiz
     */
    function Quiz(wurzel) {
        this.wurzel = wurzel;
        this.konfig = JSON.parse(wurzel.getAttribute('data-schachquiz'));
        this.texte = this.konfig.texte || {};
        // -1 = „Thema der offenen Frage“ (QuizDienst::THEMA_WIE_ZULETZT);
        // der Server antwortet mit dem tatsächlichen Thema.
        this.thema = -1;
        this.frage = null;
        this.gewaehlt = [];
        this.gesperrt = false;
        this.zeitgeber = null;

        var q = function (klasse) { return wurzel.querySelector('.schachquiz__' + klasse); };
        this.el = {
            stand: q('stand'),
            brett: q('brett'),
            meta: q('meta'),
            frage: q('frage'),
            hinweis: q('hinweis'),
            antworten: q('antworten'),
            auswertung: q('auswertung'),
            urteil: q('urteil'),
            erklaerung: q('erklaerung'),
            abgeben: q('abgeben'),
            weiter: q('weiter'),
            figuren: q('figuren'),
            differenz: q('differenz'),
            gast: q('gasthinweis')
        };

        this.verdrahte();
        this.ladeFrage();
    }

    /**
     * Verbindet Knöpfe und Tastatur mit dem Quiz.
     *
     * Tasten 1 bis 6 wählen eine Antwort, Enter gibt ab oder holt die
     * nächste Frage. Tastendrücke in Eingabefeldern bleiben unberührt.
     */
    Quiz.prototype.verdrahte = function () {
        var quiz = this;

        this.el.abgeben.addEventListener('click', function () { quiz.gibAb(); });
        this.el.weiter.addEventListener('click', function () { quiz.ladeFrage(); });

        this.wurzel.querySelectorAll('.schachquiz__thema').forEach(function (knopf) {
            knopf.addEventListener('click', function () {
                quiz.wurzel.querySelectorAll('.schachquiz__thema').forEach(function (k) {
                    k.setAttribute('aria-pressed', k === knopf ? 'true' : 'false');
                });
                quiz.thema = +knopf.getAttribute('data-thema');
                quiz.ladeFrage();
            });
        });

        document.addEventListener('keydown', function (ereignis) {
            var ziel = ereignis.target;
            if (ereignis.altKey || ereignis.ctrlKey || ereignis.metaKey) { return; }
            if (ziel && /^(INPUT|TEXTAREA|SELECT)$/.test(ziel.tagName)) { return; }
            if (!quiz.wurzel.isConnected) { return; }

            if (/^[1-6]$/.test(ereignis.key) && quiz.frage && !quiz.gesperrt) {
                var knopf = quiz.el.antworten.querySelectorAll('.schachquiz__antwort')[+ereignis.key - 1];
                if (knopf) { knopf.click(); ereignis.preventDefault(); }
            } else if (ereignis.key === 'Enter' && ziel === document.body) {
                if (!quiz.el.weiter.hidden) { quiz.ladeFrage(); ereignis.preventDefault(); }
                else if (!quiz.el.abgeben.hidden && !quiz.el.abgeben.disabled) { quiz.gibAb(); ereignis.preventDefault(); }
            }
        });
    };

    /**
     * Schickt eine Anfrage an die Schnittstelle.
     *
     * Der Kopf „X-Schachquiz“ weist die Anfrage als eigene aus: Eine fremde
     * Seite kann ihn nur nach einer CORS-Vorabfrage setzen, die der Server
     * nicht beantwortet.
     *
     * @param {string} adresse Adresse der Aktion
     * @param {Array}  werte   Liste von [Name, Wert]
     * @returns {Promise<object>} Die JSON-Antwort
     */
    Quiz.prototype.sende = function (adresse, werte) {
        var daten = new URLSearchParams();
        werte.forEach(function (paar) { daten.append(paar[0], paar[1]); });

        return fetch(adresse, {
            method: 'POST',
            body: daten,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Schachquiz': '1' }
        }).then(function (antwort) {
            var art = antwort.headers.get('Content-Type') || '';
            if (art.indexOf('json') === -1) { throw new Error('HTTP ' + antwort.status); }
            return antwort.json();
        });
    };

    /**
     * Setzt die Stimmung der Figurenrunde.
     *
     * @param {string} zustand „laden", „denken", „richtig" oder „falsch"
     */
    Quiz.prototype.stimmung = function (zustand) {
        var w = this.wurzel;
        ['laden', 'denken', 'richtig', 'falsch'].forEach(function (z) {
            w.classList.toggle('ist-' + z, z === zustand);
        });
    };

    /** Holt die nächste (oder die noch offene) Frage. */
    Quiz.prototype.ladeFrage = function () {
        var quiz = this;

        this.gesperrt = true;
        this.stimmung('laden');
        this.el.weiter.hidden = true;

        this.sende(this.konfig.frage, [['thema', this.thema]]).then(function (daten) {
            quiz.zeigeStand(daten.spieler);

            if (typeof daten.thema === 'number') {
                quiz.thema = daten.thema;
                quiz.wurzel.querySelectorAll('.schachquiz__thema').forEach(function (k) {
                    k.setAttribute('aria-pressed', +k.getAttribute('data-thema') === daten.thema ? 'true' : 'false');
                });
            }

            if (daten.status === 'ok') {
                quiz.zeigeFrage(daten.frage);
            } else {
                quiz.zeigeMeldung(quiz.meldung(daten));
            }
        }).catch(function () {
            quiz.zeigeMeldung(quiz.texte.fehler);
        });
    };

    /**
     * Wählt den Text zu einer Serverantwort ohne Frage.
     *
     * Der Server kennt die Sprache der Seite nicht und schickt neben einer
     * deutschen Meldung einen Code; die Übersetzung steht in den Texten der
     * Seite unter „meldung_<code>“.
     *
     * @param {object} daten Antwort der Schnittstelle
     * @returns {string}
     */
    Quiz.prototype.meldung = function (daten) {
        return (daten.code && this.texte['meldung_' + daten.code]) || daten.meldung || this.texte.fehler;
    };

    /**
     * Zeigt eine Meldung statt einer Frage.
     *
     * @param {string} text Die Meldung
     */
    Quiz.prototype.zeigeMeldung = function (text) {
        this.frage = null;
        this.el.frage.textContent = text;
        this.el.meta.textContent = '';
        this.el.hinweis.textContent = '';
        this.el.antworten.replaceChildren();
        this.el.brett.hidden = true;
        this.el.auswertung.hidden = true;
        this.el.abgeben.hidden = true;
        this.stimmung('');
    };

    /**
     * Stellt eine Frage dar.
     *
     * @param {object} frage Frage aus der Schnittstelle (ohne Lösung)
     */
    Quiz.prototype.zeigeFrage = function (frage) {
        var quiz = this;
        var mehrfach = frage.typ === 'multiple';

        this.frage = frage;
        this.gewaehlt = [];
        this.gesperrt = false;

        this.el.meta.textContent = [frage.thema, (this.texte.stufe || 'Stufe') + ' ' + frage.stufe].filter(Boolean).join(' · ');
        this.el.frage.textContent = frage.text;
        this.el.hinweis.textContent = mehrfach ? this.texte.mehrfach : this.texte.einfach;
        this.el.auswertung.hidden = true;
        this.el.abgeben.hidden = !mehrfach;
        this.el.abgeben.disabled = true;

        if (frage.fen) {
            zeichneBrett(this.el.brett, frage.fen, frage.brett, this.texte, this.konfig.figuren);
        } else {
            this.el.brett.hidden = true;
            this.el.brett.replaceChildren();
        }

        var liste = frage.antworten.map(function (antwort, index) {
            var knopf = document.createElement('button');
            knopf.type = 'button';
            knopf.className = 'schachquiz__antwort';
            knopf.setAttribute('data-nr', antwort.nr);
            if (mehrfach) { knopf.setAttribute('aria-pressed', 'false'); }

            var marke = document.createElement('span');
            marke.className = 'schachquiz__marke';
            marke.textContent = String.fromCharCode(65 + index);

            var text = document.createElement('span');
            text.className = 'schachquiz__antworttext';
            text.textContent = antwort.text;

            knopf.append(marke, text);
            knopf.addEventListener('click', function () { quiz.waehle(knopf, antwort.nr, mehrfach); });

            return knopf;
        });

        this.el.antworten.replaceChildren.apply(this.el.antworten, liste);
        this.stimmung('denken');
    };

    /**
     * Reagiert auf einen Klick auf eine Antwort.
     *
     * Bei Einfachauswahl gilt der Klick sofort als Antwort, wie ein Zug auf
     * dem Brett. Bei Mehrfachauswahl wird umgeschaltet und erst mit
     * „Antworten" abgegeben.
     *
     * @param {HTMLElement} knopf    Der geklickte Knopf
     * @param {number}      nr       Nummer der Antwort
     * @param {boolean}     mehrfach Ob Mehrfachauswahl gilt
     */
    Quiz.prototype.waehle = function (knopf, nr, mehrfach) {
        if (this.gesperrt) { return; }

        if (!mehrfach) {
            this.gewaehlt = [nr];
            knopf.classList.add('ist-gewaehlt');
            this.gibAb();
            return;
        }

        var stelle = this.gewaehlt.indexOf(nr);
        if (stelle === -1) { this.gewaehlt.push(nr); } else { this.gewaehlt.splice(stelle, 1); }

        knopf.setAttribute('aria-pressed', stelle === -1 ? 'true' : 'false');
        knopf.classList.toggle('ist-gewaehlt', stelle === -1);
        this.el.abgeben.disabled = this.gewaehlt.length === 0;
    };

    /** Gibt die gewählten Antworten ab und zeigt die Auswertung. */
    Quiz.prototype.gibAb = function () {
        var quiz = this;

        if (this.gesperrt || !this.gewaehlt.length) { return; }
        this.gesperrt = true;
        this.el.abgeben.disabled = true;

        this.sende(this.konfig.antwort, this.gewaehlt.map(function (nr) { return ['antwort[]', nr]; })).then(function (daten) {
            if (daten.status !== 'ok') {
                quiz.gesperrt = false;
                quiz.el.hinweis.textContent = quiz.meldung(daten);
                return;
            }
            quiz.zeigeAuswertung(daten);
        }).catch(function () {
            quiz.gesperrt = false;
            quiz.el.hinweis.textContent = quiz.texte.fehler;
        });
    };

    /**
     * Markiert richtige und falsche Antworten, zeigt Erklärung und
     * Wertungsänderung und lässt die Figuren reagieren.
     *
     * @param {object} daten Antwort der Schnittstelle
     */
    Quiz.prototype.zeigeAuswertung = function (daten) {
        this.el.antworten.querySelectorAll('.schachquiz__antwort').forEach(function (knopf) {
            var nr = +knopf.getAttribute('data-nr');
            var korrekt = daten.korrekt.indexOf(nr) !== -1;
            var gewaehlt = daten.gewaehlt.indexOf(nr) !== -1;

            knopf.disabled = true;
            knopf.classList.toggle('ist-korrekt', korrekt);
            knopf.classList.toggle('ist-daneben', gewaehlt && !korrekt);
            knopf.classList.toggle('ist-verpasst', !gewaehlt && korrekt);
        });

        var vorzeichen = daten.differenz > 0 ? '+' : (daten.differenz < 0 ? '−' : '±');
        var betrag = vorzeichen + Math.abs(daten.differenz);

        this.el.urteil.textContent = (daten.richtig ? this.texte.richtig : this.texte.falsch) + ' ' + betrag;
        this.el.urteil.className = 'schachquiz__urteil ' + (daten.richtig ? 'schachquiz__urteil--richtig' : 'schachquiz__urteil--falsch');
        this.el.erklaerung.textContent = daten.erklaerung || '';
        this.el.erklaerung.hidden = !daten.erklaerung;
        this.el.auswertung.hidden = false;
        this.el.abgeben.hidden = true;
        this.el.hinweis.textContent = '';
        this.el.weiter.hidden = false;
        this.el.weiter.focus({ preventScroll: true });

        if (this.el.differenz) {
            this.el.differenz.textContent = betrag;
        }

        this.zeigeStand(daten.spieler);
        this.stimmung(daten.richtig ? 'richtig' : 'falsch');
    };

    /**
     * Aktualisiert die Anzeige von Wertung, Serie und Antwortzahl.
     *
     * @param {object|undefined} spieler Stand aus der Schnittstelle
     */
    Quiz.prototype.zeigeStand = function (spieler) {
        if (!spieler) { return; }

        var t = this.texte;
        var wertung = document.createElement('strong');
        wertung.textContent = spieler.wertung + (spieler.vorlaeufig ? '?' : '');
        if (spieler.vorlaeufig) { wertung.title = t.vorlaeufig || ''; }

        // „5 von 9 richtig“; bei Mitgliedern zusätzlich der Stand dieser
        // Sitzung, sofern er vom Gesamtstand abweicht, und ab zwei die Zahl
        // der richtigen Antworten in Folge.
        // Vor der ersten Antwort steht nur die Wertung da, nicht „0 von 0".
        var teile = spieler.anzahl > 0 ? [text(t.vonRichtig || '%d von %d richtig', spieler.richtig, spieler.anzahl)] : [];

        if (!spieler.gast && spieler.sitzungAnzahl > 0 && spieler.sitzungAnzahl !== spieler.anzahl) {
            teile.push(text(t.inSitzung || 'diese Sitzung %d von %d', spieler.sitzungRichtig, spieler.sitzungAnzahl));
        }

        if (spieler.serie >= 2) {
            teile.push(text(t.inFolge || '%d in Folge richtig', spieler.serie));
        }

        var rest = document.createElement('span');
        rest.textContent = teile.length ? ' · ' + teile.join(' · ') : '';

        this.el.stand.replaceChildren(document.createTextNode((t.wertung || 'Wertung') + ' '), wertung, rest);

        if (this.el.gast) { this.el.gast.hidden = !spieler.gast; }
    };

    /**
     * Setzt Zahlen der Reihe nach für „%d“ in einen Text ein.
     *
     * @param {string} vorlage Text mit Platzhaltern, etwa „%d von %d richtig“
     * @returns {string}
     */
    function text(vorlage) {
        var werte = Array.prototype.slice.call(arguments, 1);
        return vorlage.replace(/%d/g, function () { return String(werte.shift()); });
    }

    /** Startet alle Quizze der Seite. */
    function start() {
        document.querySelectorAll('[data-schachquiz]').forEach(function (wurzel) {
            if (!wurzel.schachquiz) { wurzel.schachquiz = new Quiz(wurzel); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
