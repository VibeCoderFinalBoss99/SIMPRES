/*
 * SIMPRES versi web: inti aplikasi (navigasi layar, API, sesi, format teks).
 * Isinya meniru BaseFragment, Rows, Format, Session, dan Outbox di aplikasi Android
 * supaya perilaku setiap layar sama dengan aplikasi.
 */
'use strict';

const API_URL = new URL('../api/', location.href).href;

// ------------------------------------------------------------------ teks dan ikon dari res Android
/** getString(R.string.x, a, b) dengan format %1$s / %d / %s seperti Android. */
function S(name, ...args) {
    let text = STR[name] ?? name;
    let i = 0;
    return text.replace(/%(?:(\d+)\$)?(?:\.(\d+))?([sdf])/g, (_, pos, digits, kind) => {
        const v = args[pos ? Number(pos) - 1 : i++];
        if (kind === 'f') return Number(v).toFixed(digits ? Number(digits) : 6);
        if (kind === 'd') return String(Math.round(Number(v)));
        return String(v ?? '');
    });
}

function icon(name) { return ICONS[name] || ''; }

// ------------------------------------------------------------------ penyimpanan (pengganti SharedPreferences)
const store = {
    get(key, fallback = null) {
        try { const v = localStorage.getItem('simpres_' + key); return v === null ? fallback : JSON.parse(v); } catch { return fallback; }
    },
    set(key, value) {
        if (value === null || value === undefined) localStorage.removeItem('simpres_' + key);
        else localStorage.setItem('simpres_' + key, JSON.stringify(value));
    },
};

const Session = {
    get token() { return store.get('token'); },
    get isLoggedIn() { return !!store.get('token'); },
    get user() { return store.get('user'); },
    set user(u) { store.set('user', u); },
    save(token, user) { store.set('token', token); store.set('user', user); },
    clear() { store.set('token', null); store.set('user', null); },
};

// ------------------------------------------------------------------ API (pengganti Retrofit + call{})
class ApiError extends Error {}

/**
 * Hasil sama seperti ApiResult Android: {ok:true, value} | {ok:false, code, message, body} | {offline:true}.
 */
async function call(method, path, body) {
    const headers = { Accept: 'application/json' };
    if (Session.token) headers.Authorization = 'Bearer ' + Session.token;
    let payload;
    if (body instanceof FormData) payload = body;
    else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    let res;
    try {
        res = await fetch(API_URL + path, { method, headers, body: payload, cache: 'no-store' });
    } catch {
        return { offline: true };
    }
    let json = null;
    try { json = await res.json(); } catch { /* bukan JSON */ }
    if (res.ok) return { ok: true, value: json };
    if (res.status === 401) Session.clear();
    return { ok: false, code: res.status, message: json?.message || `Terjadi kesalahan (${res.status}).`, body: json };
}

const api = {
    login: b => call('POST', 'login', b),
    forgotPassword: b => call('POST', 'forgot-password', b),
    logout: () => call('POST', 'logout'),
    me: () => call('GET', 'me'),
    today: () => call('GET', 'attendance/today'),
    checkIn: b => call('POST', 'attendance/check-in', b),
    checkOut: b => call('POST', 'attendance/check-out', b),
    history: month => call('GET', 'attendance?month=' + month),
    schedules: month => call('GET', 'schedules?month=' + month),
    leaves: status => call('GET', 'leaves' + (status ? '?status=' + status : '')),
    createLeave: b => call('POST', 'leaves', b),
    cancelLeave: id => call('DELETE', 'leaves/' + id),
    createCorrection: b => call('POST', 'corrections', b),
    fieldDuties: () => call('GET', 'field-duties'),
    createFieldDuty: b => call('POST', 'field-duties', b),
    notifications: () => call('GET', 'notifications'),
    readAllNotifications: () => call('POST', 'notifications/read-all'),
    requestProfileChange: b => call('POST', 'profile-change-request', b),
};

/** Menampilkan pesan gagal yang sama seperti di aplikasi: pesan server, atau server tidak terjangkau. */
function showFailure(r) {
    alertDialog(r.offline ? S('msg_server_unreachable') : r.message);
}

// ------------------------------------------------------------------ antrean offline (Outbox)
const Outbox = {
    all() { return store.get('outbox', []); },
    save(items) { store.set('outbox', items); },
    add(item) { this.save([...this.all(), item]); },
    remove(item) { this.save(this.all().filter(i => i.body.client_uuid !== item.body.client_uuid)); },
    get lastSync() { return store.get('last_sync'); },
    set lastSync(v) { store.set('last_sync', v); },
    newUuid() { return crypto.randomUUID ? crypto.randomUUID() : 'w-' + Date.now() + '-' + Math.random().toString(16).slice(2); },
    async flush() {
        let done = 0;
        for (const item of this.all()) {
            const r = item.kind === 'check-out' ? await api.checkOut(item.body) : await api.checkIn(item.body);
            if (r.offline) return done;
            this.remove(item); done++;
        }
        if (done > 0) this.lastSync = Format.nowTime();
        return done;
    },
};

// ------------------------------------------------------------------ format (Format.kt)
const ID_DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const ID_DAYS_SHORT = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const ID_MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const ID_MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const pad2 = n => String(n).padStart(2, '0');

/** Tanggal lokal "yyyy-mm-dd" tanpa geser zona waktu. */
function parseDate(s) { const [y, m, d] = s.slice(0, 10).split('-').map(Number); return new Date(y, m - 1, d); }
function isoDate(d) { return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`; }

const Format = {
    time(v) { const m = /(\d{2}):(\d{2})/.exec((v || '').slice(11)); return m ? `${m[1]}.${m[2]}` : '--.--'; },
    clock(v) { const m = /^(\d{2}):(\d{2})/.exec(v || ''); return m ? `${m[1]}.${m[2]}` : '--.--'; },
    day(v) { const d = parseDate(v); return `${ID_DAYS_SHORT[d.getDay()]}, ${d.getDate()} ${ID_MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}`; },
    longDay(d) { return `${ID_DAYS[d.getDay()]}, ${d.getDate()} ${ID_MONTHS[d.getMonth()]} ${d.getFullYear()}`; },
    monthTitle(d) { return `${ID_MONTHS[d.getMonth()]} ${d.getFullYear()}`; },
    month(d) { return isoDate(d).slice(0, 7); },
    meters(v) { return v === null || v === undefined ? '-' : `${Math.round(v)} m`; },
    nowTime() { const d = new Date(); return `${pad2(d.getHours())}.${pad2(d.getMinutes())}`; },
    attendanceStatus(s) { return { hadir: 'Tepat waktu', terlambat: 'Terlambat', izin: 'Izin', alpa: 'Tidak hadir' }[s] || s; },
    leaveType(t) { return { izin_keluar: 'Izin keluar jam kerja', sakit: 'Izin sakit', cuti: 'Cuti' }[t] || 'Izin lainnya'; },
    submissionStatus(s) { return { menunggu: 'Menunggu Konfirmasi', diterima: 'Diterima', ditolak: 'Ditolak' }[s] || s; },
    initials(name) { return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join(''); },
};

function workTypeLabel(u) {
    const shift = u.default_shift ? ' · ' + u.default_shift : '';
    return { shift: 'Shift (jadwal dari roster)', petugas_lapangan: 'Petugas lapangan' + shift, non_shift: 'Non-shift' + shift }[u.work_type] || '-';
}

// ------------------------------------------------------------------ tampilan (pengganti View)
/** Membuat elemen dari layout Android yang sudah diubah (gen/res.js). */
function inflate(name) {
    const t = document.createElement('template');
    t.innerHTML = LAYOUTS[name].trim();
    const el = t.content.firstElementChild;
    el.style.width = ''; // ukuran ikut induknya seperti LayoutInflater(parent, false)
    if (el.style.height === '100%') el.style.height = '';
    return el;
}

function el(html) { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstElementChild; }

function setText(view, value) {
    if (!view) return;
    const target = view.querySelector(':scope > .txt') || view;
    target.textContent = value;
}
function getText(view) { return (view.querySelector(':scope > .txt') || view).textContent; }
function setImage(view, name) { if (view) { view.innerHTML = icon(name) || (SHAPES.includes(name) ? `<span class="bg-${name}" style="display:block;width:100%;height:100%"></span>` : ''); view.dataset.src = name; } }
function show(view, visible) { if (view) view.classList.toggle('gone', !visible); }
function invisible(view, hidden) { if (view) view.classList.toggle('invisible', hidden); }
function setBg(view, drawable) {
    if (!view) return;
    [...view.classList].filter(c => c.startsWith('bg-')).forEach(c => view.classList.remove(c));
    if (drawable) view.classList.add('bg-' + drawable);
}
function setColor(view, colorName) { if (view) view.style.color = `var(--${colorName})`; }
function setTint(view, colorName) { if (view) { view.classList.add('tint'); view.style.color = `var(--${colorName})`; } }
function setBgTint(view, colorName) { if (view) { view.style.backgroundColor = `var(--${colorName})`; view.style.backgroundImage = 'none'; } }
function onClickEl(view, fn) { if (view) { view.classList.add('clickable'); view.onclick = e => { e.stopPropagation(); fn(e); }; } }
function setEnabled(view, enabled) {
    if (!view) return;
    if ('disabled' in view) view.disabled = !enabled;
    view.style.pointerEvents = enabled ? '' : 'none';
    view.dataset.enabled = enabled ? '1' : '0';
}

/** Warna chip status (Tone di Rows.kt). */
const Tone = {
    SUCCESS: ['bg_chip_success', 'success'],
    WARN: ['bg_chip_warn', 'warn'],
    DANGER: ['bg_chip_danger', 'danger'],
    PRIMARY: ['bg_chip_primary', 'primary_on_tint'],
    NEUTRAL: ['bg_chip_neutral', 'ink_muted'],
};
function toneForStatus(s) {
    return { hadir: Tone.SUCCESS, diterima: Tone.SUCCESS, terlambat: Tone.WARN, menunggu: Tone.WARN, alpa: Tone.DANGER, ditolak: Tone.DANGER, izin: Tone.PRIMARY }[s] || Tone.NEUTRAL;
}

/** item_row: ikon, judul, keterangan, chip status. */
function addRow(list, iconName, title, subtitle, chip = null, tone = Tone.NEUTRAL, onClick = null) {
    const row = inflate('item_row');
    setImage(row.querySelector('#rowIcon'), iconName);
    setText(row.querySelector('#rowTitle'), title);
    setText(row.querySelector('#rowSubtitle'), subtitle);
    const chipView = row.querySelector('#rowChip');
    if (chip === null) show(chipView, false);
    else { setText(chipView, chip); setBg(chipView, tone[0]); setColor(chipView, tone[1]); }
    row.style.alignSelf = 'stretch';
    if (onClick) onClickEl(row, onClick);
    list.appendChild(row);
    return row;
}

function addMessage(list, text) {
    const tv = inflate('item_message');
    tv.style.alignSelf = 'stretch';
    setText(tv, text);
    list.appendChild(tv);
    return tv;
}

function clearAfter(group, keepUntil) {
    const kids = [...group.children];
    const start = keepUntil ? kids.indexOf(keepUntil) + 1 : 0;
    kids.slice(start).forEach(k => k.remove());
}

// ------------------------------------------------------------------ dialog dan toast
function toast(message) {
    document.querySelectorAll('.toast').forEach(t => t.remove());
    const t = el(`<div class="toast"></div>`);
    t.textContent = message;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3200);
}

/**
 * AlertDialog sederhana. buttons: [{text, primary?, onClick}] dari kanan ke kiri seperti Android
 * (positif paling kanan). Mengembalikan fungsi untuk menutup dialog.
 */
function dialog({ title, message, body, buttons = [], cancelable = true, sheet = false }) {
    const scrim = el(`<div class="dialog-scrim${sheet ? ' sheet' : ''}"><div class="dialog" role="dialog" aria-modal="true"></div></div>`);
    const box = scrim.firstElementChild;
    if (title) box.appendChild(Object.assign(document.createElement('h3'), { textContent: title }));
    if (message) box.appendChild(Object.assign(document.createElement('p'), { textContent: message }));
    if (body) box.appendChild(body);
    const close = () => scrim.remove();
    if (buttons.length) {
        const actions = el('<div class="actions"></div>');
        buttons.forEach(b => {
            const btn = Object.assign(document.createElement('button'), { type: 'button', textContent: b.text });
            if (b.color) btn.style.color = `var(--${b.color})`;
            btn.onclick = () => { if (b.keepOpen !== true) close(); b.onClick?.(); };
            actions.appendChild(btn);
        });
        box.appendChild(actions);
    }
    if (cancelable) scrim.addEventListener('click', e => { if (e.target === scrim) close(); });
    document.body.appendChild(scrim);
    return close;
}

function alertDialog(message, title = null) {
    return dialog({ title, message, buttons: [{ text: 'OK' }] });
}

/** setSingleChoiceItems: pilih satu dari daftar, dialog tertutup setelah memilih. */
function choiceDialog(title, items, checked, onPick) {
    const list = el('<div class="choices"></div>');
    let close;
    items.forEach((label, i) => {
        const c = el(`<div class="choice${i === checked ? ' on' : ''}"></div>`);
        c.textContent = label;
        c.onclick = () => { close(); onPick(i); };
        list.appendChild(c);
    });
    close = dialog({ title, body: list });
}

// ------------------------------------------------------------------ navigasi (pengganti Navigation Component)
const screens = {}; // nama -> {layout, onReady(view, args, ctx), fullBleed?, statusBar?}
const stack = [];   // [{name, args, node, ctx}]

function defineScreen(name, def) { screens[name] = def; }

function render(entry, direction) {
    const def = screens[entry.name];
    const node = el(`<div class="screen ${direction}"></div>`);
    if (def.fullBleed) node.classList.add('full-bleed');
    node.style.background = def.background ? `var(--${def.background})` : '';
    const view = inflate(def.layout);
    view.style.height = '100%';
    node.appendChild(view);
    const ctx = { alive: true, timers: [], view, args: entry.args || {} };
    entry.node = node;
    entry.ctx = ctx;
    const app = document.getElementById('app');
    app.querySelectorAll('.screen').forEach(s => s.remove());
    app.appendChild(node);
    setThemeColor(def.statusBar || 'bg');
    // Tombol kembali dan tutup bersama (BaseFragment.onViewCreated).
    onClick(view, 'btnBack', () => back());
    onClick(view, 'btnClose', () => backTo('home'));
    def.onReady(view, ctx.args, ctx);
    IconMotion.apply(view);
}

function destroy(entry) {
    if (!entry?.ctx) return;
    entry.ctx.alive = false;
    entry.ctx.timers.forEach(t => clearTimeout(t) || clearInterval(t));
    screens[entry.name].onDestroy?.(entry.ctx);
}

function current() { return stack[stack.length - 1]; }

function go(name, args) {
    destroy(current());
    stack.push({ name, args });
    history.pushState({ depth: stack.length }, '');
    render(current(), 'enter');
}

/** Pindah dan buang layar sekarang dari tumpukan. */
function replace(name, args) {
    destroy(current());
    stack.pop();
    stack.push({ name, args });
    render(current(), 'enter');
}

/** Pindah dan bersihkan tumpukan sampai layar popTo (termasuk). */
function reset(name, popTo) {
    destroy(current());
    const i = stack.map(e => e.name).lastIndexOf(popTo);
    stack.length = i >= 0 ? i : 0;
    stack.push({ name, args: undefined });
    render(current(), 'enter');
}

function back() {
    if (stack.length <= 1) return;
    destroy(current());
    stack.pop();
    render(current(), 'back-enter');
}

function backTo(name) {
    const i = stack.map(e => e.name).lastIndexOf(name);
    if (i < 0) return go(name);
    destroy(current());
    stack.length = i + 1;
    render(current(), 'back-enter');
}

/** Tab navigasi bawah: kembali ke Beranda lalu buka tab (popUpTo home, launchSingleTop). */
function goTab(name) {
    destroy(current());
    const i = stack.map(e => e.name).indexOf('home');
    stack.length = i >= 0 ? i + 1 : 0;
    if (name !== 'home' || i < 0) stack.push({ name });
    render(current(), 'enter');
}

// Tombol kembali browser / geser kembali iPhone.
window.addEventListener('popstate', () => {
    if (document.querySelector('.dialog-scrim')) { document.querySelector('.dialog-scrim').remove(); history.pushState({}, ''); return; }
    if (stack.length > 1) back();
});

// ------------------------------------------------------------------ bantuan layar
function find(view, id) { return view.querySelector('#' + id); }
function onClick(view, id, fn) { const v = find(view, id); if (v) onClickEl(v, fn); }

function bindBottomNav(view, currentName) {
    const tabs = { navHome: 'home', navHistory: 'history', navSchedule: 'schedule', navProfile: 'profile' };
    Object.entries(tabs).forEach(([id, dest]) => {
        const item = find(view, id);
        if (!item) return;
        const active = dest === currentName;
        const color = active ? 'primary_on_tint' : 'ink_faint';
        const iconView = item.children[0];
        setTint(iconView, color);
        if (active) iconView.classList.add('anim-bounce');
        setColor(item.children[1], color);
        onClickEl(item, () => { if (!active) goTab(dest); });
    });
    onClick(view, 'navFab', () => go('checkIn'));
    const nav = find(view, 'navHome')?.closest('.fl');
    if (nav) nav.parentElement.classList.add('bottom-nav-host');
    view.closest('.screen')?.classList.add('has-nav');
}

/** Hanya satu yang terpilih (tab, pilihan). Yang pertama terpilih di awal. */
function singleChoice(view, ids, onSelect = () => {}) {
    find(view, ids[0]).classList.add('selected');
    ids.forEach(id => onClickEl(find(view, id), () => {
        ids.forEach(o => find(view, o).classList.toggle('selected', o === id));
        onSelect(id);
    }));
}

function later(ctx, ms, fn) { const t = setTimeout(() => ctx.alive && fn(), ms); ctx.timers.push(t); return t; }
function every(ctx, ms, fn) { const t = setInterval(() => ctx.alive ? fn() : clearInterval(t), ms); ctx.timers.push(t); return t; }
const delay = ms => new Promise(r => setTimeout(r, ms));
function isOnline() { return navigator.onLine; }

// ------------------------------------------------------------------ tema (ThemeMode.kt)
const ThemeMode = {
    options: [['system', 'Ikuti sistem'], ['light', 'Terang'], ['dark', 'Gelap']],
    current() { return store.get('theme', 'system'); },
    label() { return this.options.find(o => o[0] === this.current())[1]; },
    set(mode) { store.set('theme', mode); this.apply(); },
    isNight() {
        const m = this.current();
        return m === 'dark' || (m === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    },
    apply() {
        document.documentElement.classList.toggle('dark', this.isNight());
        if (current()) setThemeColor(screens[current().name].statusBar || 'bg');
    },
};
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => ThemeMode.apply());

/** Warna status bar (setStatusBar di Android). */
function setThemeColor(colorName) {
    const meta = document.querySelector('meta[name=theme-color]');
    const value = getComputedStyle(document.documentElement).getPropertyValue('--' + colorName).trim();
    if (meta && value) meta.content = value;
}

// ------------------------------------------------------------------ animasi ikon (IconMotion.kt)
const IconMotion = {
    apply(root) {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        root.querySelectorAll('[data-tag]').forEach(v => {
            const tag = v.dataset.tag;
            if (['pop', 'drop', 'ring', 'pulse'].includes(tag)) v.classList.add('anim-' + tag);
        });
    },
};
