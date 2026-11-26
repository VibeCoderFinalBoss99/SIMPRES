/* Komponen yang di Android dibuat lewat kode: kalender bulan, kartu hari, panduan, tombol geser, pilih foto. */
'use strict';

/** Context.text(style, value, color) */
function textView(style, value = '', color = null) {
    const t = document.createElement('div');
    t.className = 'tv s-' + style;
    t.textContent = value;
    if (color) t.style.color = `var(--${color})`;
    return t;
}

function row(css = '') { const r = document.createElement('div'); r.className = 'll-h'; r.style.cssText = css; return r; }
function col(css = '') { const r = document.createElement('div'); r.className = 'll-v'; r.style.cssText = css; return r; }

// ------------------------------------------------------------------ warna tanggal (Marks)
const Marks = {
    HADIR: { label: 'Hadir', fill: 'success_tint', text: 'success_text' },
    TERLAMBAT: { label: 'Terlambat', fill: 'warn_tint', text: 'warn_text' },
    CUTI: { label: 'Cuti / izin', fill: 'primary_tint', text: 'primary_on_tint' },
    LAPANGAN: { label: 'Tugas lapangan', fill: 'teal_tint', text: 'teal_text' },
    ALPA: { label: 'Alpa', fill: 'danger_tint', text: 'danger_text' },
    LIBUR: { label: 'Libur', fill: 'surface', text: 'danger' },
    MENUNGGU: { label: 'Izin menunggu', fill: 'border', text: 'ink_muted' },
    PAGI: { label: 'Pagi', fill: 'success_tint', text: 'success_text' },
    SIANG: { label: 'Siang', fill: 'warn_tint', text: 'warn_text' },
    MALAM: { label: 'Malam', fill: 'primary', text: 'white' },
    forShift(name) {
        return { pagi: this.PAGI, siang: this.SIANG, malam: this.MALAM }[(name || '').toLowerCase()]
            || { label: name || 'Shift', fill: 'teal_tint', text: 'teal_text' };
    },
};

const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
const addMonths = (d, n) => new Date(d.getFullYear(), d.getMonth() + n, 1);
const sameDay = (a, b) => isoDate(a) === isoDate(b);
const today0 = () => { const n = new Date(); return new Date(n.getFullYear(), n.getMonth(), n.getDate()); };
const monthStart = d => new Date(d.getFullYear(), d.getMonth(), 1);
function daysOfMonth(month) {
    const out = [];
    for (let d = month; d.getMonth() === month.getMonth(); d = addDays(d, 1)) out.push(d);
    return out;
}

// ------------------------------------------------------------------ kalender bulan (MonthCalendar)
class MonthCalendar {
    constructor(root, onMonth, onDay) {
        this.root = root;
        this.month = monthStart(new Date());
        onClickEl(find(root, 'btnPrevMonth'), () => onMonth(addMonths(this.month, -1)));
        onClickEl(find(root, 'btnNextMonth'), () => onMonth(addMonths(this.month, 1)));
        this.onDay = onDay;
    }

    render(month, marks, legend, summary) {
        this.month = monthStart(month);
        setText(find(this.root, 'tvCalendarMonth'), Format.monthTitle(this.month));
        this.drawGrid(marks);
        this.drawLegend(legend);
        this.drawSummary(summary);
    }

    drawGrid(marks) {
        const grid = find(this.root, 'calendarGrid');
        grid.innerHTML = '';
        const head = row('align-self:stretch');
        ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].forEach((d, i) => {
            const t = textView('T_Small', d, i === 6 ? 'danger' : 'ink_muted');
            t.style.cssText += ';flex:1 1 0;height:24px;display:flex;align-items:center;justify-content:center';
            head.appendChild(t);
        });
        grid.appendChild(head);
        const today = today0();
        let day = addDays(this.month, -((this.month.getDay() + 6) % 7));
        const end = addMonths(this.month, 1);
        let line;
        while (day < end || day.getDay() !== 1) {
            if (day.getDay() === 1) { line = row('align-self:stretch'); grid.appendChild(line); }
            line.appendChild(this.cell(day, marks[isoDate(day)], sameDay(day, today)));
            day = addDays(day, 1);
        }
    }

    cell(day, mark, isToday) {
        const box = row('flex:1 1 0;height:42px;align-items:center;justify-content:center');
        if (day.getMonth() !== this.month.getMonth()) return box;
        const red = day.getDay() === 0 || mark === Marks.LIBUR;
        const colored = mark && mark !== Marks.LIBUR;
        const n = textView('T_Label', String(day.getDate()), colored ? mark.text : (red ? 'danger' : 'ink'));
        n.style.cssText += ';width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:18px';
        if (colored || isToday) n.style.background = `var(--${colored ? mark.fill : 'surface'})`;
        if (isToday) { n.style.boxShadow = 'inset 0 0 0 2px var(--primary)'; n.style.fontWeight = '600'; }
        n.setAttribute('aria-label', [isoDate(day), mark?.label].filter(Boolean).join(', '));
        box.appendChild(n);
        onClickEl(box, () => this.onDay(day));
        return box;
    }

    drawLegend(legend) {
        const host = find(this.root, 'calendarLegend');
        host.innerHTML = '';
        for (let i = 0; i < legend.length; i += 3) {
            const line = row('align-self:stretch;padding:3px 0');
            legend.slice(i, i + 3).forEach(m => {
                const item = row('flex:1 1 0;align-items:center;min-width:0');
                const sw = document.createElement('div');
                sw.style.cssText = `width:12px;height:12px;border-radius:5px;margin-right:6px;flex-shrink:0;background:var(--${m === Marks.LIBUR ? 'danger_tint' : m.fill})`
                    + (m === Marks.LIBUR ? ';box-shadow:inset 0 0 0 2px var(--danger)' : '');
                item.append(sw, textView('T_Small', m.label, 'ink_muted'));
                line.appendChild(item);
            });
            host.appendChild(line);
        }
    }

    drawSummary(summary) {
        const host = find(this.root, 'calendarSummary');
        host.innerHTML = '';
        show(host, summary.length > 0);
        summary.forEach(([label, count], i) => {
            const box = col(`flex:1 1 0;align-items:center;background:var(--bg);border-radius:12px;padding:8px 0;${i > 0 ? 'margin-left:6px' : ''}`);
            box.append(textView('T_Title', String(count)), textView('T_Small', label, 'ink_muted'));
            host.appendChild(box);
        });
    }
}

/** Kartu satu hari dengan lencana tanggal besar (addDayCard di CalendarUi.kt). */
function addDayCard(list, date, mark, title, chips, infos, note = null, onClick = null) {
    const red = date.getDay() === 0 || mark === Marks.LIBUR;
    const card = row('align-self:stretch;padding:12px 14px 12px 12px;margin-top:10px');
    card.classList.add('bg-bg_card');
    const badgeText = mark && mark !== Marks.LIBUR ? mark.text : (red ? 'danger' : 'ink');
    const fill = !mark || mark === Marks.LIBUR ? (red ? 'danger_tint' : 'bg') : mark.fill;
    const badge = col(`width:84px;min-height:96px;margin-right:14px;align-self:center;align-items:center;justify-content:center;padding:10px 0;border-radius:14px;flex-shrink:0;background:var(--${fill})`);
    const wd = textView('T_Label', ID_DAYS_SHORT[date.getDay()].toUpperCase(), badgeText);
    wd.style.letterSpacing = '0.1em';
    const num = textView('T_Display', String(date.getDate()), badgeText);
    num.style.fontSize = '34px';
    num.style.lineHeight = '1.1';
    badge.append(wd, num, textView('T_Label', ID_MONTHS_SHORT[date.getMonth()], badgeText));
    card.appendChild(badge);

    const right = col('flex:1 1 0;min-width:0;align-self:center');
    right.appendChild(textView('T_Item', title));
    for (let i = 0; i < chips.length; i += 2) {
        const line = row('padding-top:6px;flex-wrap:wrap');
        chips.slice(i, i + 2).forEach(([label, tone]) => {
            const c = textView('T_Chip', label, tone[1]);
            c.classList.add('bg-' + tone[0]);
            c.style.marginRight = '6px';
            line.appendChild(c);
        });
        right.appendChild(line);
    }
    if (infos.length) {
        const line = row('padding-top:8px;align-self:stretch');
        infos.forEach(([label, value]) => {
            const c = col('flex:1 1 0;min-width:0');
            c.append(textView('T_Small', label, 'ink_muted'), textView('T_Label', value));
            line.appendChild(c);
        });
        right.appendChild(line);
    }
    if (note) { const n = textView('T_Small', note, 'ink_muted'); n.style.paddingTop = '6px'; right.appendChild(n); }
    card.appendChild(right);
    if (onClick) {
        const chev = el(`<span class="iv tint" style="width:18px;height:18px;align-self:center;color:var(--ink_faint)">${icon('ic_chevron_right')}</span>`);
        card.appendChild(chev);
        onClickEl(card, onClick);
    }
    list.appendChild(card);
    return card;
}

/** Detail satu hari bergaya kartu Riwayat: kartu hari, lalu baris keterangan tambahan. Sama dengan showDayDetail Android. */
function showDayDetail(date, mark, title, chips, infos, details) {
    const body = col('align-self:stretch;margin-top:-10px');
    addDayCard(body, date, mark, title, chips, infos);
    details.forEach(([label, value]) => {
        const r = row('align-self:stretch;align-items:center;margin-top:8px;padding:12px 14px;border-radius:14px;background:var(--bg)');
        const l = textView('T_Small', label, 'ink_muted');
        l.style.flex = '1 1 0';
        const v = textView('T_Label', value);
        v.style.textAlign = 'right';
        r.append(l, v);
        body.appendChild(r);
    });
    dialog({ body, buttons: [{ text: S('common_tutup') }] });
}

/**
 * Tarik ke bawah untuk memuat ulang (iPhone tidak punya bawaan di mode Layar Utama). Hanya ikon putar, tanpa tulisan.
 * reload() mengembalikan Promise; ikon hilang setelah selesai.
 */
function enablePullRefresh(scroll, reload) {
    if (!scroll) return;
    const spin = el('<div class="ptr"><div class="ptr-ring"></div></div>');
    scroll.parentElement.insertBefore(spin, scroll);
    let startY = null, pull = 0, busy = false;
    scroll.addEventListener('touchstart', e => { startY = scroll.scrollTop <= 0 && !busy ? e.touches[0].clientY : null; pull = 0; }, { passive: true });
    scroll.addEventListener('touchmove', e => {
        if (startY === null) return;
        pull = Math.max(0, Math.min(90, (e.touches[0].clientY - startY) * 0.5));
        spin.style.height = pull + 'px';
        spin.classList.toggle('ready', pull >= 60);
    }, { passive: true });
    scroll.addEventListener('touchend', async () => {
        if (startY === null) return;
        startY = null;
        if (pull < 60) { spin.style.height = '0'; return; }
        busy = true;
        spin.style.height = '56px';
        spin.classList.add('busy');
        try { await reload(); } finally {
            busy = false;
            spin.classList.remove('busy', 'ready');
            spin.style.height = '0';
        }
    });
}

function durationText(minutes) {
    if (minutes === null || minutes === undefined) return '-';
    return minutes < 60 ? `${minutes} m` : `${Math.floor(minutes / 60)} j ${minutes % 60} m`;
}

// ------------------------------------------------------------------ data satu bulan (MonthData)
async function loadMonth(month, withAttendance) {
    const key = Format.month(month);
    const [att, sch, lv, fd] = await Promise.all([
        withAttendance ? api.history(key) : null,
        api.schedules(key),
        api.leaves(null),
        withAttendance ? api.fieldDuties() : null,
    ]);
    const list = r => (r && r.ok ? r.value.data : []);
    const byDate = items => Object.fromEntries(items.map(i => [i.date, i]));
    const data = {
        attendances: byDate(list(att)),
        schedules: byDate(list(sch)),
        leaves: list(lv),
        duties: list(fd),
        offline: [att, sch, lv, fd].some(r => r && r.offline),
        leaveOn(d) {
            const day = isoDate(d);
            return this.leaves
                .filter(l => l.status !== 'ditolak' && day >= l.start_date && day <= l.end_date)
                .filter(l => l.type !== 'izin_keluar' || l.start_date === day)
                .sort((a, b) => (a.status === 'diterima' ? 0 : 1) - (b.status === 'diterima' ? 0 : 1))[0] || null;
        },
        dutyOn(d) {
            const day = isoDate(d);
            return this.duties.filter(x => x.date === day && x.status !== 'ditolak')
                .sort((a, b) => (a.status === 'diterima' ? 0 : 1) - (b.status === 'diterima' ? 0 : 1))[0] || null;
        },
    };
    return data;
}

/** Warna tanggal Riwayat (dipakai juga rekap Beranda). */
function historyMark(d, data, today) {
    const key = isoDate(d);
    const a = data.attendances[key];
    const s = data.schedules[key];
    const leave = data.leaveOn(d);
    if (a?.status === 'terlambat') return Marks.TERLAMBAT;
    if (a?.status === 'hadir') return Marks.HADIR;
    if (a?.status === 'izin') return Marks.CUTI;
    if (a?.status === 'alpa') return Marks.ALPA;
    if (data.dutyOn(d)?.status === 'diterima') return Marks.LAPANGAN;
    if (leave?.status === 'diterima' && leave.type !== 'izin_keluar') return Marks.CUTI;
    if (s?.holiday || s?.is_day_off || (!s && d.getDay() === 0)) return Marks.LIBUR;
    if (s && d < today) return Marks.ALPA;
    if (leave?.status === 'menunggu') return Marks.MENUNGGU;
    return null;
}

// ------------------------------------------------------------------ tombol geser (SwipeToConfirm.kt)
/** Mengembalikan fungsi untuk mengembalikan tombol ke awal. */
function setupSwipeToConfirm(track, knob, label, onConfirm) {
    let startX = 0, x = 0, done = false, dragging = false;
    const maxX = () => Math.max(1, track.clientWidth - parseFloat(getComputedStyle(track).paddingLeft) - parseFloat(getComputedStyle(track).paddingRight) - knob.offsetWidth);
    const set = (v, anim = '') => {
        x = v;
        knob.style.transition = anim;
        label.style.transition = anim ? anim.replace('transform', 'opacity') : '';
        knob.style.transform = `translateX(${v}px)`;
        label.style.opacity = String(Math.max(0, 1 - (v / maxX()) * 1.4));
    };
    const confirm = () => {
        if (done) return;
        done = true;
        navigator.vibrate?.(20);
        set(maxX(), 'transform .12s ease-out');
        setTimeout(onConfirm, 130);
    };
    knob.style.touchAction = 'none';
    knob.style.cursor = 'grab';
    knob.addEventListener('pointerdown', e => {
        if (done) return;
        dragging = true;
        startX = e.clientX - x;
        knob.setPointerCapture(e.pointerId);
    });
    knob.addEventListener('pointermove', e => { if (dragging) set(Math.min(maxX(), Math.max(0, e.clientX - startX))); });
    const up = () => {
        if (!dragging) return;
        dragging = false;
        if (x >= maxX() * 0.8) confirm();
        else set(0, 'transform .26s cubic-bezier(.34,1.56,.64,1)');
    };
    knob.addEventListener('pointerup', up);
    knob.addEventListener('pointercancel', up);
    track.setAttribute('role', 'button');
    track.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') confirm(); });
    return () => { done = false; set(0, 'transform .22s ease-out'); };
}

// ------------------------------------------------------------------ panduan pertama kali (CoachMarks.kt)
const CoachMarks = {
    isDone(key) { return store.get('guide_' + key, false); },
    reset() { Object.keys(localStorage).filter(k => k.startsWith('simpres_guide_')).forEach(k => localStorage.removeItem(k)); },
    showOnce(key, steps) {
        steps = steps.filter(s => s.target && s.target.offsetParent !== null);
        if (!steps.length || this.isDone(key)) return;
        const overlay = el(`<div style="position:fixed;inset:0;z-index:40;animation:fade .2s ease-out">
            <svg style="position:absolute;inset:0;width:100%;height:100%"><defs><mask id="guideMask"><rect width="100%" height="100%" fill="white"/><rect id="guideHole" rx="18" fill="black"/></mask></defs>
            <rect width="100%" height="100%" fill="rgba(8,14,28,.725)" mask="url(#guideMask)"/><rect id="guideRing" rx="18" fill="none" stroke="#fff" stroke-width="2"/></svg></div>`);
        const card = inflate('view_coach_mark');
        card.style.cssText += ';position:absolute;left:20px;right:20px;transition:top .22s ease-out';
        overlay.appendChild(card);
        document.getElementById('app').appendChild(overlay);
        let index = 0;
        const finish = () => { store.set('guide_' + key, true); overlay.remove(); };
        const showStep = i => {
            index = i;
            const step = steps[i];
            step.target.scrollIntoView({ block: 'center' });
            requestAnimationFrame(() => {
                const r = step.target.getBoundingClientRect();
                const o = overlay.getBoundingClientRect();
                const pad = 6;
                const hole = { x: r.left - o.left - pad, y: r.top - o.top - pad, w: r.width + pad * 2, h: r.height + pad * 2 };
                ['guideHole', 'guideRing'].forEach(id => {
                    const rect = overlay.querySelector('#' + id);
                    rect.setAttribute('x', hole.x); rect.setAttribute('y', hole.y);
                    rect.setAttribute('width', hole.w); rect.setAttribute('height', hole.h);
                });
                setText(find(card, 'tvGuideStep'), S('guide_step', i + 1, steps.length));
                setText(find(card, 'tvGuideTitle'), S(step.title));
                setText(find(card, 'tvGuideBody'), S(step.body));
                setText(find(card, 'btnGuideNext'), S(i === steps.length - 1 ? 'guide_done' : 'guide_next'));
                const gap = 14, h = card.offsetHeight;
                const below = hole.y + hole.h + gap;
                let y = below + h < o.height - gap ? below : hole.y - gap - h;
                card.style.top = Math.min(Math.max(gap, y), Math.max(gap, o.height - h - gap)) + 'px';
            });
        };
        onClickEl(find(card, 'btnGuideSkip'), finish);
        onClickEl(find(card, 'btnGuideNext'), () => (index === steps.length - 1 ? finish() : showStep(index + 1)));
        showStep(0);
    },
};

// ------------------------------------------------------------------ foto (pilih dari galeri / kamera)
/** Memilih gambar lalu memperkecilnya (sisi terpanjang 1600 px, JPEG 80%) seperti shrinkImage. */
function pickImage({ capture = null } = {}) {
    return new Promise(resolve => {
        const input = Object.assign(document.createElement('input'), { type: 'file', accept: 'image/*' });
        if (capture) input.setAttribute('capture', capture);
        input.onchange = async () => resolve(input.files[0] ? await shrinkImage(input.files[0]) : null);
        input.click();
    });
}

async function shrinkImage(file) {
    const bmp = await createImageBitmap(file).catch(() => null);
    if (!bmp) return null;
    const scale = Math.min(1, 1600 / Math.max(bmp.width, bmp.height));
    const canvas = Object.assign(document.createElement('canvas'), { width: Math.round(bmp.width * scale), height: Math.round(bmp.height * scale) });
    canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
    return new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.8));
}

/** Kolom jam (TimePickerDialog): di iPhone muncul roda jam bawaan. */
function openPicker(input) { input.addEventListener('click', () => { try { input.showPicker(); } catch { /* iPhone membuka sendiri */ } }); }

function makeTimeField(input, initial = '08:00') {
    input.type = 'time';
    openPicker(input);
    input.value = '';
    input.step = 60;
    input.addEventListener('focus', () => { if (!input.value) input.value = initial; });
}

function makeDateField(input) {
    input.type = 'date';
    openPicker(input);
    input.value = '';
}
