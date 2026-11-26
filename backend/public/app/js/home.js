/* Beranda, Riwayat, Jadwal, Koreksi, Notifikasi, Profil (HomeScreens.kt dan CalendarScreens.kt). */
'use strict';

let lastToday = null; // [userId, today] selama aplikasi terbuka

defineScreen('home', {
    layout: 'fragment_home',
    fullBleed: true,
    statusBar: 'primary',
    onReady(view, args, ctx) {
        bindBottomNav(view, 'home');
        onClick(view, 'btnBell', () => go('notification'));
        onClick(view, 'tvSeeAll', () => go('history'));
        onClick(view, 'cardSync', () => go('sync'));
        onClick(view, 'btnLeave', () => go('leaveList'));
        onClick(view, 'btnCorrection', () => go('correction'));
        // Kepala biru ikut mengisi area status bar iPhone.
        const header = view.querySelector('.bg-bg_home_header');
        if (header) header.style.paddingTop = `calc(${header.style.paddingTop || '0px'} + env(safe-area-inset-top))`;

        ctx.swipeKind = 'check-in';
        ctx.current = null;
        ctx.resetSwipe = setupSwipeToConfirm(find(view, 'swipeCheckOut'), find(view, 'swipeKnob'), find(view, 'tvSwipeLabel'),
            () => ctx.alive && confirmSwipe(ctx));

        showUser(view);
        showQueue(view);
        loadToday(view, ctx);
        loadRecap(view, ctx);
        loadUnread(view, ctx);
        enablePullRefresh(find(view, 'homeScroll'), () => { showQueue(view); return Promise.all([loadToday(view, ctx), loadRecap(view, ctx), loadUnread(view, ctx)]); });
        later(ctx, 300, () => CoachMarks.showOnce('home', [
            { target: find(view, 'gridToday'), title: 'guide_today_title', body: 'guide_today_body' },
            { target: find(view, 'swipeCheckOut'), title: 'guide_checkout_title', body: 'guide_checkout_body' },
            { target: find(view, 'quickActions'), title: 'guide_quick_title', body: 'guide_quick_body' },
            { target: find(view, 'btnBell'), title: 'guide_bell_title', body: 'guide_bell_body' },
            { target: find(view, 'navProfile'), title: 'guide_menu_title', body: 'guide_menu_body' },
        ]));
    },
});

function showUser(view) {
    const user = Session.user;
    if (!user) return;
    const h = new Date().getHours();
    setText(find(view, 'tvGreeting'), h >= 4 && h <= 10 ? 'Selamat pagi,' : h <= 14 && h >= 11 ? 'Selamat siang,' : h >= 15 && h <= 17 ? 'Selamat sore,' : 'Selamat malam,');
    setText(find(view, 'tvName'), user.name);
    setText(find(view, 'tvAvatar'), Format.initials(user.name));
    setText(find(view, 'tvTodayDate'), Format.longDay(new Date()));
    const info = find(view, 'infoList');
    info.innerHTML = '';
    [
        ['ic_person', 'JABATAN', user.position || '-'],
        ['ic_home', 'UNIT KERJA', user.unit || '-'],
        ['ic_file', 'NIP', user.nip],
        ['ic_clock', 'JENIS KERJA', workTypeLabel(user)],
        ['ic_location', 'LOKASI KERJA', user.work_location?.name || 'Belum diatur'],
    ].forEach(([ic, label, value]) => {
        const line = row('align-self:stretch;align-items:center;padding:10px 0 4px');
        const box = el(`<div class="fl bg-bg_tile_primary" style="width:40px;height:40px;flex-shrink:0;place-items:center"><span class="iv tint" style="width:20px;height:20px;color:var(--primary_on_tint)">${icon(ic)}</span></div>`);
        const texts = col('flex:1 1 0;min-width:0;margin-left:12px');
        const l = textView('T_Small', label, 'ink_muted');
        l.style.letterSpacing = '0.08em';
        texts.append(l, textView('T_Label', value));
        line.append(box, texts);
        info.appendChild(line);
    });
}

function showQueue(view) {
    const pending = Outbox.all().length;
    show(find(view, 'cardSync'), pending > 0);
    setText(find(view, 'tvSyncTitle'), S('sync_pending', pending));
    setText(find(view, 'tvSyncNote'), S('sync_waiting'));
}

async function loadToday(view, ctx) {
    const cached = lastToday && lastToday[0] === Session.user?.id ? lastToday[1] : null;
    if (cached) showToday(view, ctx, cached); else showPlaceholders(view);
    if (Outbox.all().length && await Outbox.flush() > 0 && ctx.alive) showQueue(view);
    const r = await api.today();
    if (!ctx.alive) return;
    if (!r.ok) {
        if (r.code === 401) reset('login', 'home');
        return;
    }
    lastToday = [Session.user?.id, r.value.data];
    showToday(view, ctx, r.value.data);
}

async function loadUnread(view, ctx) {
    const r = await api.notifications();
    if (!ctx.alive) return;
    const unread = r.ok ? (r.value.unread_count ?? r.value.data.filter(n => !n.is_read).length) : 0;
    show(find(view, 'bellDot'), unread > 0);
}

async function loadRecap(view, ctx) {
    const month = monthStart(new Date());
    setText(find(view, 'tvRecapMonth'), Format.monthTitle(month));
    const data = await loadMonth(month, true);
    if (!ctx.alive) return;
    const today = today0();
    const marks = daysOfMonth(month).filter(d => d <= today).map(d => historyMark(d, data, today)).filter(Boolean);
    const box = find(view, 'recapRow');
    box.innerHTML = '';
    [
        ['Hadir', marks.filter(m => m === Marks.HADIR || m === Marks.TERLAMBAT || m === Marks.LAPANGAN).length, Marks.HADIR],
        ['Terlambat', marks.filter(m => m === Marks.TERLAMBAT).length, Marks.TERLAMBAT],
        ['Izin/Cuti', marks.filter(m => m === Marks.CUTI).length, Marks.CUTI],
        ['Alpa', marks.filter(m => m === Marks.ALPA).length, Marks.ALPA],
    ].forEach(([label, count, mark], i) => {
        const c = col(`flex:1 1 0;align-items:center;justify-content:center;border-radius:14px;padding:12px 0;background:var(--${mark.fill});${i > 0 ? 'margin-left:8px' : ''}`);
        c.append(textView('T_Headline', String(count), mark.text), textView('T_Small', label, mark.text));
        box.appendChild(c);
    });
}

function showPlaceholders(view) {
    ['tvCheckInTime', 'tvCheckOutTime'].forEach(id => setText(find(view, id), '--.--'));
    ['tvCheckInStatus', 'tvCheckOutNote'].forEach(id => setText(find(view, id), '-'));
    setText(find(view, 'tvShift'), S('msg_loading'));
}

function showToday(view, ctx, t) {
    ctx.current = t;
    const s = t.schedule;
    setText(find(view, 'tvShift'), !s ? S('today_no_schedule') : s.is_day_off ? S('today_day_off')
        : `${s.shift || 'Jadwal'} · ${Format.clock(s.start_time)}–${Format.clock(s.end_time)}`);
    const a = t.attendance;
    const start = s && !s.is_day_off ? s.start_time : null;
    setText(find(view, 'tvCheckInTime'), Format.time(a?.check_in_at));
    showNote(find(view, 'tvCheckInStatus'),
        !a && start ? [`Masuk ${Format.clock(start)}`, 'ink_muted']
            : !a ? [S('today_none'), 'ink_muted']
                : a.status === 'terlambat' ? [S('result_late', a.late_minutes), 'warn']
                    : a.status === 'hadir' ? ['Tepat waktu', 'success'] : [Format.attendanceStatus(a.status), 'ink_muted']);
    setText(find(view, 'tvCheckOutTime'), Format.time(a?.check_out_at));
    showNote(find(view, 'tvCheckOutNote'),
        a?.check_out_at && a.early_leave_minutes > 0 ? [`Pulang cepat ${a.early_leave_minutes} menit`, 'warn']
            : a?.check_out_at ? ['Sesuai jadwal', 'success']
                : [s?.end_time && start ? `Batas ${Format.clock(s.end_time)}` : '-', 'ink_muted']);
    showSwipe(view, ctx, t);
}

function showNote(tv, [text, color]) { setText(tv, text); setColor(tv, color); }

/** Check-out sebelum jam pulang: tanya dulu karena akan dicatat pulang lebih cepat. */
function confirmSwipe(ctx) {
    const open = () => go('checkIn', { kind: ctx.swipeKind });
    const s = ctx.current?.schedule;
    if (!s || !s.end_time || s.is_day_off || ctx.swipeKind !== 'check-out') return open();
    const [eh, em] = s.end_time.split(':').map(Number);
    const now = new Date();
    let endAt = new Date(now.getFullYear(), now.getMonth(), now.getDate(), eh, em);
    if (s.start_time && s.end_time <= s.start_time) endAt = addDays(endAt, 1);
    if (now >= endAt) return open();
    const minutes = Math.floor((endAt - now) / 60000) + 1;
    dialog({
        title: S('checkout_early_title'),
        message: S('checkout_early_body', s.shift || 'kerja', Format.clock(s.end_time), minutes),
        cancelable: false,
        buttons: [
            { text: S('checkout_early_cancel'), onClick: () => ctx.resetSwipe() },
            { text: S('checkout_early_ok'), onClick: open },
        ],
    });
}

function showSwipe(view, ctx, t) {
    const queued = Outbox.all().map(i => i.kind);
    const checkedIn = t.has_checked_in || queued.includes('check-in');
    const checkedOut = t.has_checked_out || queued.includes('check-out');
    ctx.swipeKind = checkedIn ? 'check-out' : 'check-in';
    const label = S(checkedIn ? 'home_geser_untuk_check_out' : 'home_geser_untuk_check_in');
    setText(find(view, 'tvSwipeLabel'), label);
    const track = find(view, 'swipeCheckOut');
    track.setAttribute('aria-label', label);
    show(track, !checkedOut);
    show(find(view, 'tvDoneToday'), checkedOut);
}

// ------------------------------------------------------------------ Riwayat
const leaveName = l => Format.leaveType(l.type);
const shiftText = s => (s?.shift ? `${s.shift.name} · ${Format.clock(s.shift.start_time)}–${Format.clock(s.shift.end_time)}` : null);

defineScreen('history', {
    layout: 'fragment_history',
    onReady(view, args, ctx) {
        bindBottomNav(view, 'history');
        ctx.tab = 'tabAll';
        ctx.month = monthStart(new Date());
        ctx.cards = {};
        ctx.calendar = new MonthCalendar(find(view, 'calendarHost'), m => { ctx.month = m; loadHistory(view, ctx); }, d => scrollToCard(view, ctx, d, true));
        singleChoice(view, ['tabAll', 'tabRoutine', 'tabField'], id => { ctx.tab = id; loadHistory(view, ctx); });
        onClick(view, 'btnFilter', () => {
            const months = [...Array(12).keys()].map(i => addMonths(monthStart(new Date()), -i));
            choiceDialog(null, months.map(m => Format.monthTitle(m)), -1, i => { ctx.month = months[i]; loadHistory(view, ctx); });
        });
        loadHistory(view, ctx);
        enablePullRefresh(find(view, 'scroll'), () => loadHistory(view, ctx));
    },
});

function scrollToCard(view, ctx, date, pulse) {
    const card = ctx.cards[isoDate(date)];
    if (!card) { if (pulse) toast(S('calendar_no_record', Format.day(isoDate(date)))); return; }
    const scroll = find(view, 'scroll');
    scroll.scrollTo({ top: card.offsetTop - scroll.offsetTop - 24, behavior: 'smooth' });
    if (pulse) card.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.03)' }, { transform: 'scale(1)' }], { duration: 280 });
}

async function loadHistory(view, ctx) {
    const list = find(view, 'dayList');
    list.innerHTML = '';
    ctx.cards = {};
    const loading = addMessage(list, S('msg_loading'));
    const month = ctx.month;
    const data = await loadMonth(month, true);
    if (!ctx.alive || month !== ctx.month) return;
    loading.remove();
    const today = today0();
    const days = daysOfMonth(month);
    const marks = {};
    days.forEach(d => { const m = historyMark(d, data, today); if (m) marks[isoDate(d)] = m; });
    const counted = Object.values(marks);
    ctx.calendar.render(month, marks,
        [Marks.HADIR, Marks.TERLAMBAT, Marks.CUTI, Marks.LAPANGAN, Marks.ALPA, Marks.LIBUR],
        [
            ['Hadir', counted.filter(m => m === Marks.HADIR || m === Marks.TERLAMBAT || m === Marks.LAPANGAN).length],
            ['Terlambat', counted.filter(m => m === Marks.TERLAMBAT).length],
            ['Cuti/izin', counted.filter(m => m === Marks.CUTI).length],
            ['Alpa', counted.filter(m => m === Marks.ALPA).length],
        ]);
    if (data.offline) { addMessage(list, S('msg_offline_list')); return; }
    days.slice().reverse().forEach(d => addHistoryCard(list, ctx, d, marks[isoDate(d)], data, today));
    if (!list.children.length) addMessage(list, S('msg_empty'));
}

function addHistoryCard(list, ctx, d, mark, data, today) {
    const key = isoDate(d);
    const a = data.attendances[key];
    const s = data.schedules[key];
    const duty = data.dutyOn(d);
    const leave = data.leaveOn(d);
    const routine = a || leave || mark === Marks.ALPA || s?.holiday;
    if (ctx.tab === 'tabField' && !duty) return;
    if (ctx.tab === 'tabRoutine' && !routine) return;
    if (!routine && !duty && mark !== Marks.LIBUR) return;
    if (mark === Marks.LIBUR && !s?.holiday && !a && !duty && !leave) return;

    const chips = [];
    let title, infos = [], note = shiftText(s) ? `Jadwal: ${shiftText(s)}` : null;
    if (a && ['hadir', 'terlambat'].includes(a.status)) {
        title = a.status === 'terlambat' ? 'Hadir, terlambat' : 'Hadir';
        chips.push(a.status === 'terlambat' ? [`Terlambat ${a.late_minutes} mnt`, Tone.WARN] : ['Tepat waktu', Tone.SUCCESS]);
        if (a.early_leave_minutes > 0) chips.push([`Pulang cepat ${a.early_leave_minutes} mnt`, Tone.WARN]);
        if (!a.check_out_at && d < today) chips.push(['Tidak check-out', Tone.DANGER]);
        if (a.source === 'offline') chips.push(['Dikirim offline', Tone.NEUTRAL]);
        infos = [['Masuk', Format.time(a.check_in_at)], ['Pulang', Format.time(a.check_out_at)], ['Jam kerja', durationText(a.work_minutes)]];
    } else if (a?.status === 'izin' || (leave?.status === 'diterima' && leave.type !== 'izin_keluar')) {
        title = leave ? leaveName(leave) : 'Izin';
        chips.push(['Disetujui', Tone.PRIMARY]);
        note = leave?.reason || null;
    } else if (mark === Marks.ALPA) {
        title = 'Tidak hadir';
        chips.push(['Alpa', Tone.DANGER]);
    } else if (mark === Marks.LIBUR) {
        title = s?.holiday || 'Libur';
        chips.push(['Libur nasional', Tone.DANGER]);
        note = null;
    } else {
        title = duty ? 'Tugas lapangan' : leave ? leaveName(leave) : '-';
    }
    if (leave && leave.status === 'menunggu') chips.push([`${leaveName(leave)} menunggu`, Tone.WARN]);
    if (leave && leave.type === 'izin_keluar' && leave.status === 'diterima') chips.push(['Izin keluar', Tone.PRIMARY]);
    if (duty) {
        if (title === '-') title = 'Tugas lapangan';
        chips.push([`Lapangan: ${Format.submissionStatus(duty.status)}`, duty.status === 'diterima' ? Tone.SUCCESS : Tone.WARN]);
        note = [note, `Lokasi: ${duty.location_name}`].filter(Boolean).join('\n');
    }
    if (sameDay(d, today)) chips.unshift(['Hari ini', Tone.PRIMARY]);
    const detailChips = chips.filter(c => c[0] !== 'Dikirim offline');
    ctx.cards[key] = addDayCard(list, d, mark, title, chips, infos, note, a ? () => showAttendance(d, a, s, mark, title, detailChips) : null);
}

/** Detail absensi satu hari, bergaya kartu Riwayat (tanpa baris sumber data). */
function showAttendance(d, a, s, mark, title, chips) {
    const details = [
        shiftText(s) ? ['Jadwal', shiftText(s)] : null,
        ['Jarak saat check-in', Format.meters(a.check_in_distance_m)],
        a.check_out_at ? ['Jarak saat check-out', Format.meters(a.check_out_distance_m)] : null,
    ].filter(Boolean);
    showDayDetail(d, mark, title, chips,
        [['Masuk', Format.time(a.check_in_at)], ['Pulang', Format.time(a.check_out_at)], ['Jam kerja', durationText(a.work_minutes)]],
        details);
}

// ------------------------------------------------------------------ Jadwal
defineScreen('schedule', {
    layout: 'fragment_schedule',
    onReady(view, args, ctx) {
        bindBottomNav(view, 'schedule');
        ctx.month = monthStart(new Date());
        ctx.cards = {};
        ctx.calendar = new MonthCalendar(find(view, 'calendarHost'), m => { ctx.month = m; loadSchedule(view, ctx); }, d => scrollToCard(view, ctx, d, false));
        loadSchedule(view, ctx);
    },
});

function scheduleMark(d, data) {
    const s = data.schedules[isoDate(d)];
    const leave = data.leaveOn(d);
    if (leave?.status === 'diterima' && leave.type !== 'izin_keluar') return Marks.CUTI;
    if (s?.holiday || s?.is_day_off || (!s && d.getDay() === 0)) return Marks.LIBUR;
    if (s?.shift) return Marks.forShift(s.shift.name);
    if (leave?.status === 'menunggu') return Marks.MENUNGGU;
    return null;
}

async function loadSchedule(view, ctx) {
    const list = find(view, 'dayList');
    list.innerHTML = '';
    ctx.cards = {};
    const loading = addMessage(list, S('msg_loading'));
    const month = ctx.month;
    const data = await loadMonth(month, false);
    if (!ctx.alive || month !== ctx.month) return;
    loading.remove();
    const today = today0();
    const days = daysOfMonth(month);
    const marks = {};
    days.forEach(d => { const m = scheduleMark(d, data); if (m) marks[isoDate(d)] = m; });
    const shiftNames = [...new Set(Object.values(data.schedules).map(s => s.shift?.name).filter(Boolean))];
    const values = Object.values(marks);
    ctx.calendar.render(month, marks,
        [...shiftNames.map(n => Marks.forShift(n)), Marks.CUTI, Marks.LIBUR],
        [
            ['Hari kerja', values.filter(m => m !== Marks.LIBUR && m !== Marks.CUTI && m !== Marks.MENUNGGU).length],
            ['Libur', values.filter(m => m === Marks.LIBUR).length],
            ['Cuti/izin', values.filter(m => m === Marks.CUTI).length],
        ]);
    if (data.offline) { addMessage(list, S('msg_offline_list')); return; }
    if (!Object.keys(data.schedules).length) addMessage(list, S('schedule_empty'));
    days.forEach(d => {
        const key = isoDate(d);
        const s = data.schedules[key];
        const leave = data.leaveOn(d);
        if (!s && !leave && d.getDay() !== 0) return;
        const chips = [];
        if (sameDay(d, today)) chips.push(['Hari ini', Tone.PRIMARY]);
        let title;
        if (leave?.status === 'diterima' && leave.type !== 'izin_keluar') { title = leaveName(leave); chips.push(['Disetujui', Tone.PRIMARY]); }
        else if (s?.holiday) { title = s.holiday; chips.push(['Libur nasional', Tone.DANGER]); }
        else if (s?.is_day_off || !s) { title = 'Libur'; chips.push(['Libur', Tone.DANGER]); }
        else title = `Shift ${s.shift?.name || ''}`.trim();
        if (leave?.status === 'menunggu') chips.push([`${leaveName(leave)} menunggu`, Tone.WARN]);
        if (leave?.type === 'izin_keluar' && leave.status === 'diterima') chips.push(['Izin keluar', Tone.PRIMARY]);
        const infos = s?.shift && !s.is_day_off
            ? [['Masuk', Format.clock(s.shift.start_time)], ['Pulang', Format.clock(s.shift.end_time)], ['Sumber', s.is_default ? 'Shift tetap' : 'Roster']]
            : [];
        ctx.cards[key] = addDayCard(list, d, marks[key], title, chips, infos, leave && leave.status !== 'ditolak' ? leave.reason : null);
    });
    if (month.getMonth() === today.getMonth() && month.getFullYear() === today.getFullYear()) {
        requestAnimationFrame(() => scrollToCard(view, ctx, today, false));
    }
}

// ------------------------------------------------------------------ Koreksi absensi
defineScreen('correction', {
    layout: 'fragment_correction',
    onReady(view, args, ctx) {
        ctx.reason = 'GPS tidak akurat';
        ctx.fixCheckIn = true;
        ctx.attachment = null;
        const time = find(view, 'etTime');
        const note = find(view, 'etNote');
        note.value = '';
        makeTimeField(time, '08:00');
        const labels = { chipGps: 'GPS tidak akurat', chipNoInternet: 'Tidak ada internet', chipForgot: 'Lupa absen', chipCamera: 'Kamera bermasalah', chipOther: 'Lainnya' };
        singleChoice(view, Object.keys(labels), id => { ctx.reason = labels[id]; });
        api.today().then(r => {
            if (!ctx.alive || !r.ok) return;
            ctx.fixCheckIn = !r.value.data.has_checked_in;
            setText(find(view, 'tvTarget'), S('correction_target', ctx.fixCheckIn ? 'Check-in' : 'Check-out'));
            setText(find(view, 'tvTargetNote'), Format.day(r.value.data.date));
        });
        const attachRow = find(view, 'rowAttach');
        const showAttachment = () => setText(attachRow.children[1], ctx.attachment ? S('attach_selected', Math.round(ctx.attachment.size / 1024)) : S('common_tambah_lampiran'));
        onClickEl(attachRow, async () => {
            if (ctx.attachment) {
                return dialog({ message: S('common_tambah_lampiran') + '?', buttons: [
                    { text: 'Hapus', onClick: () => { ctx.attachment = null; showAttachment(); } },
                    { text: 'Ganti', onClick: async () => { const b = await pickImage(); if (b) { ctx.attachment = b; showAttachment(); } } },
                ] });
            }
            const b = await pickImage();
            if (b && ctx.alive) { ctx.attachment = b; showAttachment(); }
        });
        onClick(view, 'btnSubmit', async () => {
            if (!time.value.trim() || !note.value.trim()) return toast(S('msg_fill_form'));
            const body = { date: isoDate(new Date()), reason: `${ctx.reason}: ${note.value.trim()}` };
            body[ctx.fixCheckIn ? 'corrected_check_in' : 'corrected_check_out'] = time.value.slice(0, 5);
            let payload = body;
            if (ctx.attachment) {
                payload = new FormData();
                Object.entries(body).forEach(([k, v]) => payload.append(k, v));
                payload.append('lampiran', ctx.attachment, 'bukti.jpg');
            }
            const r = await api.createCorrection(payload);
            if (r.ok) { toast(r.value.message); backTo('home'); } else showFailure(r);
        });
    },
});

// ------------------------------------------------------------------ Notifikasi
defineScreen('notification', {
    layout: 'fragment_notification',
    onReady(view, args, ctx) {
        onClick(view, 'tvMarkRead', async () => { await api.readAllNotifications(); loadNotifications(view, ctx); });
        loadNotifications(view, ctx);
    },
});

async function loadNotifications(view, ctx) {
    const list = find(view, 'listContent');
    clearAfter(list, null);
    const loading = addMessage(list, S('msg_loading'));
    const r = await api.notifications();
    if (!ctx.alive) return;
    loading.remove();
    if (r.ok) {
        const items = r.value.data;
        if (!items.length) addMessage(list, S('msg_empty'));
        items.forEach(n => {
            const tone = /diterima/i.test(n.title) ? Tone.SUCCESS : /ditolak/i.test(n.title) ? Tone.DANGER : Tone.PRIMARY;
            addRow(list, 'ic_bell', n.title, `${n.body}\n${n.created_at.slice(0, 16)}`, n.is_read ? null : 'Baru', tone);
        });
        // Halaman sudah dibuka = semua dianggap dibaca, supaya titik merah lonceng di Beranda hilang.
        if (items.some(n => !n.is_read)) api.readAllNotifications();
    } else addMessage(list, r.offline ? S('msg_offline_list') : r.message);
}

// ------------------------------------------------------------------ Profil
defineScreen('profile', {
    layout: 'fragment_profile',
    onReady(view) {
        bindBottomNav(view, 'profile');
        const u = Session.user;
        if (u) {
            setText(find(view, 'tvName'), u.name);
            setText(find(view, 'tvAvatar'), Format.initials(u.name));
            setText(find(view, 'tvPosition'), u.position || '-');
            setText(find(view, 'tvRole'), u.role === 'admin' ? 'Admin' : 'Pegawai');
            setText(find(view, 'tvNip'), u.nip);
            setText(find(view, 'tvUnit'), u.unit || '-');
            setText(find(view, 'tvDevice'), deviceName());
        }
        onClick(view, 'rowPermission', () => go('privacy'));
        onClick(view, 'rowPrivacy', () => go('privacy'));
        onClick(view, 'rowSettings', () => go('config'));
        onClick(view, 'rowAbout', () => go('about'));
        onClick(view, 'btnLogout', showLogoutDialog);
        onClick(view, 'tvShowGuide', () => { CoachMarks.reset(); backTo('home'); });
    },
});

function deviceName() {
    const ua = navigator.userAgent;
    if (/iPhone/.test(ua)) return 'iPhone (web)';
    if (/iPad/.test(ua)) return 'iPad (web)';
    if (/Android/.test(ua)) return 'Android (web)';
    return 'Browser (web)';
}

function showLogoutDialog() {
    const content = inflate('dialog_logout');
    const scrim = el('<div class="dialog-scrim" style="padding:28px"></div>');
    content.style.cssText += ';width:100%;max-width:380px';
    scrim.appendChild(content);
    const close = () => scrim.remove();
    scrim.addEventListener('click', e => { if (e.target === scrim) close(); });
    onClick(content, 'btnCancel', close);
    onClick(content, 'btnConfirm', async () => {
        close();
        await api.logout();
        Session.clear();
        reset('login', 'home');
    });
    document.body.appendChild(scrim);
}

defineScreen('attendanceDetail', { layout: 'fragment_attendance_detail', onReady() {} });
