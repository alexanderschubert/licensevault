import Alpine from 'alpinejs';

// Light/Dark/System – Auswahl wird lokal im Browser gemerkt.
Alpine.data('theme', () => ({
    mode: localStorage.getItem('lv-theme') || 'system',
    get dark() {
        return this.mode === 'dark' || (this.mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    },
    apply() {
        document.documentElement.classList.toggle('dark', this.dark);
    },
    toggle() {
        this.mode = this.dark ? 'light' : 'dark';
        localStorage.setItem('lv-theme', this.mode);
        this.apply();
    },
    init() {
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => this.apply());
    },
}));

// Geheimnis (Lizenzschlüssel / Einrichtungscode) nur auf Klick nachladen, nach 30 s wieder ausblenden.
Alpine.data('secretBox', (url) => ({
    value: null, error: '', copied: false, timer: null,
    async show() {
        this.error = '';
        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!r.ok) throw new Error();
            this.value = (await r.json()).secret;
            this.timer = setTimeout(() => this.hide(), 30000);
        } catch (e) { this.error = 'Konnte nicht geladen werden.'; }
    },
    hide() { this.value = null; this.copied = false; clearTimeout(this.timer); },
    async copy() {
        try { await navigator.clipboard.writeText(this.value); this.copied = true; setTimeout(() => (this.copied = false), 2000); }
        catch (e) { this.error = 'Kopieren nicht möglich – bitte manuell markieren.'; }
    },
}));

// QR-Bild erst auf Klick laden (nie im HTML eingebettet), nach 60 s wieder verbergen.
Alpine.data('qrCard', () => ({
    open: false, timer: null,
    toggle() {
        this.open = !this.open;
        clearTimeout(this.timer);
        if (this.open) this.timer = setTimeout(() => (this.open = false), 60000);
    },
}));

// ---- HomeKit-QR-Scanner (Kamera live oder Foto) ------------------------------------------------
// Dekodierung läuft komplett im Browser; es wird nichts hochgeladen.
const HK_CATEGORIES = {
    1: 'Sonstiges', 2: 'Bridge', 3: 'Ventilator', 4: 'Garagentor', 5: 'Lampe', 6: 'Türschloss', 7: 'Steckdose', 8: 'Schalter',
    9: 'Thermostat', 10: 'Sensor', 11: 'Alarmanlage', 12: 'Tür', 13: 'Fenster', 14: 'Rollo', 15: 'Taster', 16: 'Repeater',
    17: 'Kamera', 18: 'Video-Türklingel', 19: 'Luftreiniger', 20: 'Heizung', 21: 'Klimaanlage', 22: 'Luftbefeuchter',
    23: 'Luftentfeuchter', 28: 'Bewässerung', 29: 'Wasserhahn', 30: 'Dusche', 31: 'Fernseher', 32: 'Fernbedienung', 33: 'Router',
};

/** X-HM://<9 Zeichen Base36><4 Zeichen Setup-ID> -> { payload, setupCode, category } */
export function parseHomeKitPayload(text) {
    const m = /^X-HM:\/\/([0-9A-Z]{9})([0-9A-Z]{4})$/.exec(String(text).trim().toUpperCase());
    if (!m) return null;
    let v = 0n;
    for (const ch of m[1]) v = v * 36n + BigInt(parseInt(ch, 36));
    const code = Number(v & ((1n << 27n) - 1n)); // untere 27 Bit = Einrichtungscode
    const category = Number((v >> 31n) & 0xffn);
    const digits = String(code).padStart(8, '0');
    return {
        payload: m[0],
        setupCode: code <= 99999999 ? `${digits.slice(0, 3)}-${digits.slice(3, 5)}-${digits.slice(5)}` : null,
        category: HK_CATEGORIES[category] ?? null,
    };
}

// ---- Matter: "MT:" + Base38 (Matter Core Spec, Onboarding Payload) -----------------------------
const BASE38 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-.';
const VERHOEFF_D = ['0123456789', '1234067895', '2340178956', '3401289567', '4012395678', '5987604321', '6598710432', '7659821043', '8765932104', '9876543210'];
const VERHOEFF_P = ['0123456789', '1576283094', '5803796142', '8916043527', '9453126870', '4286573901', '2793806415', '7046913258'];
const VERHOEFF_INV = '0432156789';

function verhoeffCheckDigit(digits) {
    let c = 0;
    [...digits].reverse().forEach((ch, i) => { c = Number(VERHOEFF_D[c][Number(VERHOEFF_P[(i + 1) % 8][Number(ch)])]); });
    return VERHOEFF_INV[c];
}

/** MT:… -> { protocol, payload, setupCode (11-stelliger manueller Code), details } */
export function parseMatterPayload(text) {
    const m = /^MT:([0-9A-Z.\-]{19,})$/.exec(String(text).trim().toUpperCase());
    if (!m) return null;
    let n = 0n, shift = 0n;
    let pos = 0;
    for (const [chars, bytes] of [[5, 3], [5, 3], [5, 3], [4, 2]]) {
        const chunk = m[1].slice(pos, pos + chars); pos += chars;
        let v = 0n;
        for (const ch of [...chunk].reverse()) v = v * 38n + BigInt(BASE38.indexOf(ch));
        if (v >= 1n << BigInt(8 * bytes)) return null;
        n |= v << shift; shift += BigInt(8 * bytes);
    }
    const bits = (lo, w) => Number((n >> BigInt(lo)) & ((1n << BigInt(w)) - 1n));
    const version = bits(0, 3), vendorId = bits(3, 16), productId = bits(19, 16), flow = bits(35, 2);
    const discriminator = bits(45, 12), passcode = bits(57, 27);
    if (version !== 0 || passcode < 1 || passcode > 99999998) return null;

    let setupCode = null;
    if (flow === 0) {
        const s = `${(discriminator >> 10) & 3}${String(((((discriminator >> 8) & 3) << 14) | (passcode & 0x3fff))).padStart(5, '0')}${String(passcode >> 14).padStart(4, '0')}`;
        const d = s + verhoeffCheckDigit(s);
        setupCode = `${d.slice(0, 4)}-${d.slice(4, 7)}-${d.slice(7)}`;
    }
    const hex = (v) => '0x' + v.toString(16).toUpperCase().padStart(4, '0');
    return {
        protocol: 'matter', payload: m[0], setupCode,
        details: `Matter: Hersteller-ID ${hex(vendorId)}, Produkt-ID ${hex(productId)}, Discriminator ${discriminator}`,
    };
}

Alpine.data('hkScanner', () => ({
    open: false, error: '', note: '', stream: null, raf: null,
    live: window.isSecureContext && !!navigator.mediaDevices?.getUserMedia,
    init() {
        if (new URLSearchParams(location.search).has('scan')) this.$nextTick(() => this.start());
    },
    async start() {
        this.error = ''; this.note = ''; this.open = true;
        if (!this.live) return; // ohne HTTPS: nur Foto-Aufnahme möglich
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            const v = this.$refs.video;
            v.srcObject = this.stream;
            v.setAttribute('playsinline', '');
            await v.play();
            this.loop();
        } catch (e) {
            this.error = 'Kamerazugriff nicht möglich. Erlaube die Kamera in den Einstellungen oder nutze „Foto aufnehmen“.';
        }
    },
    async loop() {
        const { default: jsQR } = await import('jsqr');
        const v = this.$refs.video;
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        const tick = () => {
            if (!this.open) return;
            if (v.readyState >= 2 && v.videoWidth) {
                canvas.width = v.videoWidth; canvas.height = v.videoHeight;
                ctx.drawImage(v, 0, 0);
                const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const r = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
                if (r && this.accept(r.data)) return;
            }
            this.raf = requestAnimationFrame(tick);
        };
        tick();
    },
    async fromPhoto(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        this.error = ''; this.note = '';
        try {
            const bmp = await createImageBitmap(file);
            const scale = Math.min(1, 1600 / Math.max(bmp.width, bmp.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(bmp.width * scale); canvas.height = Math.round(bmp.height * scale);
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
            const { default: jsQR } = await import('jsqr');
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const r = jsQR(img.data, img.width, img.height, { inversionAttempts: 'attemptBoth' });
            if (!r) { this.error = 'Kein QR-Code erkannt. Näher heran, besser beleuchten und erneut versuchen.'; return; }
            this.accept(r.data);
        } catch (e) {
            this.error = 'Das Bild konnte nicht gelesen werden.';
        }
    },
    accept(text) {
        const hk = parseHomeKitPayload(text);
        const matter = hk ? null : parseMatterPayload(text);
        if (!hk && !matter) { this.error = 'Das ist kein HomeKit- (X-HM://) oder Matter-QR-Code (MT:).'; return false; }
        const set = (id, value, onlyIfEmpty = false) => {
            const el = document.getElementById(id);
            if (!el || !value || (onlyIfEmpty && el.value.trim() !== '')) return;
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const protocol = hk ? 'homekit' : 'matter';
        const current = document.querySelector('input[name=protocol]:checked')?.value;
        const radio = current === 'both' ? null : document.querySelector(`input[name=protocol][value=${protocol}]`);
        if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); radio.dispatchEvent(new Event('input', { bubbles: true })); }

        const info = hk ?? matter;
        set('qr_payload', info.payload);
        set('setup_code', info.setupCode, true);
        if (hk) set('name', hk.category, true);
        if (matter) set('notes', matter.details, true);

        this.note = `${hk ? 'HomeKit' : 'Matter'}-QR-Code übernommen`
            + (info.setupCode ? ` · ${hk ? 'Einrichtungscode' : 'Kopplungscode'} ${info.setupCode} (bitte mit dem Aufdruck am Gerät vergleichen)` : '')
            + (hk?.category ? ` · Kategorie: ${hk.category}` : '');
        this.stop();
        return true;
    },
    stop() {
        this.open = false;
        cancelAnimationFrame(this.raf);
        this.stream?.getTracks().forEach((t) => t.stop());
        this.stream = null;
    },
}));

// Drucken: In der iPhone-App (Home-Bildschirm) tut window.print() nichts – dort stattdessen das PDF öffnen
// (Teilen-Symbol → Drucken / In Dateien sichern).
window.lvPrint = (pdfUrl) => {
    const iosApp = /iPhone|iPad|iPod/.test(navigator.userAgent) && (navigator.standalone || matchMedia('(display-mode: standalone)').matches);
    if (iosApp && pdfUrl) { location.href = pdfUrl; return; }
    window.print();
};

// Foto vor dem Upload im Browser verkleinern (iPhone-Fotos sind mehrere MB groß).
window.lvShrinkImage = async (input, max = 1600, quality = 0.85) => {
    const file = input.files?.[0];
    if (!file) return;
    const isHeic = /heic|heif/i.test(file.type) || /\.(heic|heif)$/i.test(file.name);
    if (!isHeic && !file.type.startsWith('image/')) return;
    try {
        // Safari/iOS kann HEIC decodieren; andere Browser werfen hier – dann bleibt das Original.
        const bmp = await createImageBitmap(file);
        const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
        if (!isHeic && scale === 1 && file.size < 1500000) return;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bmp.width * scale);
        canvas.height = Math.round(bmp.height * scale);
        canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
        const png = file.type === 'image/png'; // Transparenz erhalten
        const blob = await new Promise((r) => (png ? canvas.toBlob(r, 'image/png') : canvas.toBlob(r, 'image/jpeg', quality)));
        if (!blob) return;
        const dt = new DataTransfer();
        dt.items.add(new File([blob], file.name.replace(/\.[^.]+$/, '') + (png ? '.png' : '.jpg'), { type: png ? 'image/png' : 'image/jpeg' }));
        input.files = dt.files;
    } catch (e) { /* Original behalten */ }
};

// ---- Web-Push (Service Worker + VAPID) ---------------------------------------------------------
const b64ToBytes = (b64) => {
    const pad = '='.repeat((4 - (b64.length % 4)) % 4);
    const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
};

Alpine.data('pushSettings', (cfg) => ({
    supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
    // iOS liefert Web-Push nur an Apps, die zum Home-Bildschirm hinzugefügt wurden
    needsInstall: /iPhone|iPad|iPod/.test(navigator.userAgent) && !(navigator.standalone || matchMedia('(display-mode: standalone)').matches),
    denied: false, subscribed: false, busy: false, error: '',
    async init() {
        if (!this.supported) return;
        this.denied = Notification.permission === 'denied';
        try {
            const reg = await navigator.serviceWorker.getRegistration('/');
            this.subscribed = !!(reg && (await reg.pushManager.getSubscription()));
        } catch (e) { /* ignorieren */ }
    },
    csrf() { return document.querySelector('meta[name=csrf-token]').content; },
    async post(url, body) {
        const r = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() }, body: JSON.stringify(body) });
        if (!r.ok) throw new Error('Server antwortete mit ' + r.status);
    },
    async enable() {
        this.busy = true; this.error = '';
        try {
            const permission = await Notification.requestPermission();
            this.denied = permission === 'denied';
            if (permission !== 'granted') return;
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(cfg.publicKey) });
            await this.post(cfg.subscribeUrl, sub.toJSON());
            this.subscribed = true;
            location.reload();
        } catch (e) {
            this.error = 'Aktivieren fehlgeschlagen: ' + (e?.message || e);
        } finally { this.busy = false; }
    },
    async disable() {
        this.busy = true; this.error = '';
        try {
            const reg = await navigator.serviceWorker.getRegistration('/');
            const sub = reg && (await reg.pushManager.getSubscription());
            if (sub) {
                await this.post(cfg.unsubscribeUrl, { endpoint: sub.endpoint });
                await sub.unsubscribe();
            }
            this.subscribed = false;
            location.reload();
        } catch (e) {
            this.error = 'Deaktivieren fehlgeschlagen: ' + (e?.message || e);
        } finally { this.busy = false; }
    },
}));

window.Alpine = Alpine;
Alpine.start();
