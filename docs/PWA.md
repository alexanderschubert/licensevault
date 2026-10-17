# LicenseVault auf dem iPhone (PWA) und HomeKit-QR per Kamera

## Als App installieren

1. LicenseVault in **Safari** öffnen (über deine HTTPS-Adresse, siehe unten) und anmelden – „Angemeldet bleiben“ anhaken.
2. **Teilen** → **Zum Home-Bildschirm**.
3. Die App startet danach im Vollbild mit eigenem Icon. Die Navigation erreichst du über das Menü-Symbol oben links.

Die App speichert bewusst **keine Seiten offline** (kein Service-Worker-Cache): Lizenzschlüssel, Codes und QR-Bilder landen so nie im Browser-Cache. Ohne Verbindung zu deinem Server ist die App daher nicht nutzbar.

### Das Icon stimmt nicht (Buchstabe „L“, Screenshot oder altes Logo)

iOS holt das Icon **einmalig beim Hinzufügen** und merkt es sich pro Adresse. Gehe so vor:

1. App vom Home-Bildschirm **löschen**.
2. iPhone: *Einstellungen → Apps → Safari → Erweitert → Website-Daten* → Eintrag deiner Domain löschen (oder *Verlauf und Websitedaten löschen*).
3. Safari beenden, neu öffnen, Seite **vollständig laden lassen**, dann *Teilen → Zum Home-Bildschirm*.
4. Prüfe vorher am iPhone im **privaten Tab**, ob `https://deine-domain/apple-touch-icon.png` ohne Anmeldung das Logo zeigt. iOS lädt das Icon ohne deine Sitzung: Liegt vor der Domain eine **Zugriffsliste/Basic-Auth** (Nginx Proxy Manager *Access List*), schlägt das fehl und iOS nimmt einen Buchstaben. Dann `/apple-touch-icon.png`, `/icons/` und `/manifest.webmanifest` von der Zugriffsliste ausnehmen (Custom Location) oder die Zugriffsliste für diese Pfade abschalten.

Die Icon-Dateien wurden korrigiert (zuvor waren die Größen 180/192 px Bildausschnitte und wirkten stark herangezoomt). Seit Version 0.5.x tragen die Icon-Dateien ein `-v3` im Namen, damit iOS nicht das alte Bild aus dem Cache nimmt. Zusätzlich liegt eine Kopie unter `/apple-touch-icon.png` (Standardpfad, den iOS ohne Link-Tag abfragt).

## HomeKit- und Matter-Geräte per Kamera hinzufügen

1. Menü → **HomeKit-QR-Codes** → **Scannen** (oder beim neuen Gerät **QR-Code mit Kamera scannen**).
2. Kamera auf den HomeKit-QR-Code am Gerät oder in der Anleitung halten. Erkannt werden HomeKit-Codes (`X-HM://…`) und Matter-Codes (`MT:…`); der Standard wird automatisch umgestellt.
3. LicenseVault übernimmt den QR-Payload, leitet bei HomeKit den **Einrichtungscode** (`123-45-678`) und die Kategorie ab, bei Matter den 11-stelligen **manuellen Kopplungscode** (`3497-011-2332`) sowie Hersteller-/Produkt-ID (in die Notizen) und füllt Name und Code aus, soweit die Felder leer sind. **Vergleiche den Code mit dem Aufdruck am Gerät**, ergänze Raum/Hersteller und speichere.

Die Erkennung läuft komplett im Browser; das Kamerabild wird nicht hochgeladen oder gespeichert.

### Wichtig: HTTPS

Der Live-Scan nutzt die Kamera des Browsers – iOS erlaubt das **nur über HTTPS**. Über `http://192.168.x.x:8080` erscheint stattdessen der Knopf **„Foto aufnehmen“** (öffnet die normale iPhone-Kamera; das Foto wird nur lokal gelesen). Für den Live-Scan und die PWA-Installation brauchst du eine HTTPS-Adresse mit gültigem Zertifikat, z. B.:

- **Nginx Proxy Manager** (Let's Encrypt, DNS-Challenge für eine interne Domain) → `http://<unraid-ip>:<WEB_PORT>`
- **Tailscale**: `tailscale serve` bzw. MagicDNS-HTTPS-Zertifikat für den Unraid-Host

Danach in der `.env` anpassen und neu erzeugen:

```dotenv
APP_URL=https://licensevault.deine-domain.example
SESSION_SECURE_COOKIE=true
```

Selbstsignierte Zertifikate funktionieren auf iOS für Kamera/PWA nur, wenn du das Zertifikat auf dem iPhone als vertrauenswürdig installierst.

## Nginx Proxy Manager einrichten

Proxy Host:

| Feld | Wert |
|---|---|
| Domain Names | deine HTTPS-Domain |
| Scheme | `http` |
| Forward Hostname / IP | IP deines Unraid |
| Forward Port | dein `WEB_PORT` (z. B. `9088`) |
| Block Common Exploits | an |
| SSL | Zertifikat wählen, **Force SSL** an, HTTP/2 an |

Tab **Advanced** (für Uploads bis 25 MB):

```nginx
client_max_body_size 25m;
```

In der `.env` von LicenseVault:

```dotenv
APP_URL=https://deine-domain
SESSION_SECURE_COOKIE=true
# optional: nur den Proxy vertrauen, z. B. die IP von Nginx Proxy Manager
# TRUSTED_PROXIES=192.168.1.5
```

Danach `docker compose … up -d --build` (bzw. `--force-recreate`). Ab dieser Version vertraut LicenseVault den `X-Forwarded-*`-Headern privater Netze und erzeugt hinter dem Proxy `https://`-Links; ohne das lädt die Seite ohne Design, weil der Browser `http://`-Skripte und -Styles auf einer HTTPS-Seite blockiert.

### Wenn die Seite trotzdem falsch lädt

Prüfe in `.env`, dass `APP_URL` mit `https://` beginnt, und teste:

```bash
curl -sI https://deine-domain/login | head -5
curl -s https://deine-domain/login | grep -o 'http://[^"]*' | head
```

Die zweite Zeile sollte nichts ausgeben (außer `w3.org`-Namensräumen).

## Hinweise zu Matter

- Matter-QR-Codes beginnen mit `MT:`, der manuelle Kopplungscode hat 11 (oder 21) Ziffern mit Prüfziffer – eine Fehleingabe wird erkannt.
- Die Ableitung wurde gegen den Testvektor des Matter-SDK geprüft (`MT:-24J0AFN00KA0648G00` ↔ `3497-011-2332`).
- Ein bereits mit einem System (z. B. Apple Home) gekoppeltes Matter-Gerät akzeptiert den ursprünglichen Code oft nicht mehr. Für weitere Systeme in der Home-App „Kopplung freigeben“ wählen und den **neuen** Code hier eintragen.

## Drucken am iPhone

In der zum Home-Bildschirm hinzugefügten App tut der normale Druck-Befehl des Browsers nichts. Deshalb öffnet **Drucken** dort automatisch ein **PDF** der QR-Codes (einzeln oder die gefilterte Liste). Im PDF-Fenster: **Teilen-Symbol → Drucken** (AirPrint) oder **In Dateien sichern**. Zusätzlich gibt es überall die Knöpfe **PDF** bzw. **Als PDF**. Das PDF wird bei jedem Aufruf frisch erzeugt, nicht gespeichert und nur nach Anmeldung ausgeliefert; es enthält den QR-Code als Grafik, nie den Code als Text.
