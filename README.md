# DIVERA 24/7 für IP-Symcon

Dieses Modul bindet die offizielle DIVERA-24/7-REST-API ausschließlich lesend in IP-Symcon ein und stellt eine eigene HTML-Kachel für die Kachelvisualisierung bereit.

## Voraussetzungen

- IP-Symcon ab Version 7.1
- DIVERA-24/7-Accesskey
- PHP-Erweiterungen cURL und GD (GD nur für die optionale Karte)
- Internetzugriff auf DIVERA sowie optional OpenStreetMap und Nominatim

## Installation

Nach Veröffentlichung kann die Bibliothek direkt über den IP-Symcon Module Store installiert werden. Während der Entwicklung kann die URL des GitHub-Repositorys im Module Control als Modulquelle eingetragen werden.

Anschließend:

1. Instanz `DIVERA Einsatzkachel` hinzufügen.
2. DIVERA-Accesskey eintragen.
3. Abrufintervall auswählen und Änderungen übernehmen.
4. Die Instanz in einer Kachelvisualisierung sichtbar machen.

Für die vollständige Darstellung wird eine Kachelgröße von mindestens 3 × 3 Feldern empfohlen.

## Veröffentlichung

Veröffentlichungskontakt für das geplante GitHub-Repository:

- E-Mail: `lars.schroeder@steuerbar.systems`

Passwörter, Personal Access Tokens und andere Zugangsdaten dürfen nicht in diesem Repository oder in der Moduldokumentation gespeichert werden. Für den Zugriff auf GitHub ist der Credential Manager beziehungsweise ein Personal Access Token zu verwenden.

## Funktionen

- regelmäßiger Abruf des neuesten offenen Einsatzes
- eigene Symcon-Statusvariablen ohne feste Objekt-IDs
- native HTML-Kachel über das HTML-SDK
- optionale Einsatzkarte mit OpenStreetMap-Kacheln
- einmalige Geokodierung der Adresse über Nominatim, falls keine Koordinaten geliefert werden
- letzte gültige Einsatzdaten bleiben bei einem vorübergehenden Abruffehler erhalten

## Datenschutz und externe Dienste

Der Accesskey wird als geschützte Instanzeigenschaft in IP-Symcon gespeichert und ausschließlich beim Abruf der DIVERA-API verwendet. Das Modul sendet keine Alarmierungen, Rückmeldungen oder Statusänderungen an DIVERA.

Bei aktivierter Karte werden Kartenkacheln anhand der Einsatzkoordinaten von `tile.openstreetmap.org` geladen. Fehlen Koordinaten, wird die von DIVERA gelieferte Einsatzadresse zur Geokodierung an Nominatim übertragen.

Dieses Projekt ist eine inoffizielle Integration und steht in keiner geschäftlichen Verbindung zur DIVERA GmbH. DIVERA 24/7 und die zugehörigen Kennzeichen sind Marken ihrer jeweiligen Rechteinhaber.

## Lizenz

MIT
