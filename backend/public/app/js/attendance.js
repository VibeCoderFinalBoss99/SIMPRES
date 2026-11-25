/* Check-in/check-out, peta, hasil absensi, tugas lapangan (AttendanceScreens.kt dan OsmMap.kt). */
'use strict';

const AttendanceFlow = { kind: 'check-in', attendance: null, message: null, distanceM: null, accuracyM: null, location: null };

// ------------------------------------------------------------------ peta OpenStreetMap (OsmMap.kt)
const OsmMap = {
    TILE: 256,
    cache: new Map(),

    tile(z, x, y) {
        const key = `${z}/${x}/${y}`;
        if (!this.cache.has(key)) {
            this.cache.set(key, new Promise(resolve => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = () => { this.cache.delete(key); resolve(null); };
                img.src = `https://tile.openstreetmap.org/${key}.png`;
            }));
        }
        return this.cache.get(key);
    },

    pixel(lat, lng, zoom) {
        const size = this.TILE * 2 ** zoom;
        const r = lat * Math.PI / 180;
        return [(lng + 180) / 360 * size, (1 - Math.log(Math.tan(r) + 1 / Math.cos(r)) / Math.PI) / 2 * size];
    },

    /** Menggambar peta di kanvas: tile, lingkaran radius, titik Puskesmas, posisi pegawai. */
    async draw(canvas, w, h, place, me, color, zoomDelta, panX, panY) {
        const metersPerPx0 = 156543.03392 * Math.cos(place.latitude * Math.PI / 180);
        let span = place.radius_m * 2.6;
        if (me) span = Math.max(span, distanceM(place.latitude, place.longitude, me.latitude, me.longitude) * 1.3 + place.radius_m);
        const zoom = Math.min(19, Math.max(3, Math.floor(Math.log2(metersPerPx0 * Math.min(w, h) / span)) + zoomDelta));
        const p = this.pixel(place.latitude, place.longitude, zoom);
        const m = me ? this.pixel(me.latitude, me.longitude, zoom) : null;
        const cx = (m ? (p[0] + m[0]) / 2 : p[0]) + panX;
        const cy = (m ? (p[1] + m[1]) / 2 : p[1]) + panY;
        const left = cx - w / 2, top = cy - h / 2;
        const keys = [];
        for (let tx = Math.floor(left / this.TILE); tx <= Math.floor((left + w) / this.TILE); tx++)
            for (let ty = Math.floor(top / this.TILE); ty <= Math.floor((top + h) / this.TILE); ty++) keys.push([tx, ty]);
        const tiles = await Promise.all(keys.map(([x, y]) => this.tile(zoom, x, y)));
        if (tiles.every(t => !t)) return false;

        const dpr = window.devicePixelRatio || 1;
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        const g = canvas.getContext('2d');
        g.setTransform(dpr, 0, 0, dpr, 0, 0);
        g.fillStyle = '#F2EFE9';
        g.fillRect(0, 0, w, h);
        keys.forEach(([x, y], i) => { if (tiles[i]) g.drawImage(tiles[i], x * this.TILE - left, y * this.TILE - top, this.TILE, this.TILE); });

        const px = p[0] - left, py = p[1] - top;
        const radiusPx = place.radius_m / (metersPerPx0 / 2 ** zoom);
        g.beginPath(); g.arc(px, py, radiusPx, 0, Math.PI * 2);
        g.globalAlpha = 40 / 255; g.fillStyle = color; g.fill();
        g.globalAlpha = 1; g.setLineDash([8, 6]); g.lineWidth = 2.5; g.strokeStyle = color; g.stroke(); g.setLineDash([]);
        this.dot(g, px, py, color);
        if (m) this.dot(g, m[0] - left, m[1] - top, '#1A73E8');
        return true;
    },

    dot(g, x, y, color) {
        g.beginPath(); g.arc(x, y, 9, 0, Math.PI * 2); g.fillStyle = '#FFFFFF'; g.fill();
        g.beginPath(); g.arc(x, y, 6.5, 0, Math.PI * 2); g.fillStyle = color; g.fill();
    },
};

/** Jarak dua titik (meter), rumus haversine seperti Location.distanceBetween. */
function distanceM(lat1, lng1, lat2, lng2) {
    const R = 6371008.8, toRad = d => d * Math.PI / 180;
    const a = Math.sin(toRad(lat2 - lat1) / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(toRad(lng2 - lng1) / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(a));
}

/** Peta yang bisa digeser, dicubit, diketuk dua kali, dan diperbesar dengan tombol + dan -. */
function showMap(view, ctx, me) {
    const place = Session.user?.work_location;
    if (!place) return;
    const box = find(view, 'mapBox');
    const image = find(view, 'mapImage');
    box.style.overflow = 'hidden';
    box.style.touchAction = 'none';
    let canvas = image.querySelector('canvas');
    if (!canvas) { image.innerHTML = '<canvas style="width:100%;height:100%;display:block"></canvas>'; canvas = image.firstChild; }
    const color = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#1B3F8F';
    const state = ctx.mapState || (ctx.mapState = { zoom: 0, panX: 0, panY: 0 });
    ctx.mapMe = me;

    const render = async () => {
        const w = box.clientWidth, h = box.clientHeight;
        if (!w || !ctx.alive) return;
        const ok = await OsmMap.draw(canvas, w, h, place, ctx.mapMe, color, state.zoom, state.panX, state.panY);
        if (!ok || !ctx.alive) return;
        canvas.style.transform = '';
        show(image, true);
        box.querySelectorAll('[data-tag=fence],[data-tag=drop]').forEach(v => show(v, false));
        show(find(view, 'tvMapCredit'), true);
        show(find(view, 'mapZoom'), true);
    };
    ctx.renderMap = render;
    const zoom = step => {
        const next = Math.min(5, Math.max(-8, state.zoom + step));
        const f = 2 ** (next - state.zoom);
        state.panX *= f; state.panY *= f; state.zoom = next;
        render();
    };
    if (!ctx.mapBound) {
        ctx.mapBound = true;
        onClick(view, 'btnZoomIn', () => zoom(1));
        onClick(view, 'btnZoomOut', () => zoom(-1));
        const pts = new Map();
        let down = null, dragging = false, pinchStart = 0, pinch = 1, lastTap = 0;
        box.addEventListener('pointerdown', e => {
            if (e.target.closest('#mapZoom')) return;
            pts.set(e.pointerId, [e.clientX, e.clientY]);
            box.setPointerCapture(e.pointerId);
            if (pts.size === 1) {
                down = [e.clientX, e.clientY]; dragging = false;
                const now = Date.now();
                if (now - lastTap < 300) zoom(1);
                lastTap = now;
            } else if (pts.size === 2) {
                const [a, b] = [...pts.values()];
                pinchStart = Math.hypot(a[0] - b[0], a[1] - b[1]); pinch = 1;
                canvas.style.transform = '';
            }
        });
        box.addEventListener('pointermove', e => {
            if (!pts.has(e.pointerId)) return;
            pts.set(e.pointerId, [e.clientX, e.clientY]);
            if (pts.size === 2) {
                const [a, b] = [...pts.values()];
                pinch = Math.min(4, Math.max(0.25, Math.hypot(a[0] - b[0], a[1] - b[1]) / pinchStart));
                canvas.style.transform = `scale(${pinch})`;
            } else if (pts.size === 1 && down) {
                const dx = e.clientX - down[0], dy = e.clientY - down[1];
                if (!dragging && Math.hypot(dx, dy) > 8) dragging = true;
                if (dragging) canvas.style.transform = `translate(${dx}px,${dy}px)`;
            }
        });
        const up = e => {
            if (!pts.has(e.pointerId)) return;
            const wasPinch = pts.size === 2;
            pts.delete(e.pointerId);
            if (wasPinch) {
                const step = Math.round(Math.log2(pinch));
                if (step) zoom(step); else canvas.style.transform = '';
                down = null;
            } else if (pts.size === 0 && dragging && down) {
                state.panX -= e.clientX - down[0];
                state.panY -= e.clientY - down[1];
                dragging = false; down = null;
                render();
            }
        };
        box.addEventListener('pointerup', up);
        box.addEventListener('pointercancel', up);
    }
    requestAnimationFrame(render);
}

// ------------------------------------------------------------------ GPS (Locator.kt)
const Locator = {
    current() {
        return new Promise(resolve => {
            if (!navigator.geolocation) return resolve({ error: 'unsupported' });
            navigator.geolocation.getCurrentPosition(
                p => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: p.coords.accuracy }),
                e => resolve({ error: e.code === 1 ? 'denied' : 'failed' }),
                { enableHighAccuracy: true, timeout: 25000, maximumAge: 0 });
        });
    },
    body(loc, uuid) {
        return {
            latitude: loc.latitude, longitude: loc.longitude, accuracy: loc.accuracy,
            // Browser tidak bisa mendeteksi lokasi palsu atau emulator; server tetap memeriksa radius dan akurasi.
            is_mock: false, is_emulator: false, client_uuid: uuid,
            device_info: ('Web · ' + deviceName() + ' · ' + navigator.userAgent).slice(0, 255),
        };
    },
};

// ------------------------------------------------------------------ Check-in / check-out
defineScreen('checkIn', {
    layout: 'fragment_checkin',
    onReady(view, args, ctx) {
        ctx.kind = args.kind || 'check-in';
        setText(find(view, 'tvScreenTitle'), S(ctx.kind === 'check-out' ? 'checkout_title' : 'checkin_title'));
        const loc = Session.user?.work_location;
        if (loc) {
            setText(find(view, 'tvAreaSub'), S('checkin_area_sub', loc.name, loc.radius_m));
            setText(find(view, 'tvOfficeName'), loc.name);
            setText(find(view, 'tvOfficeAddress'), [loc.address?.trim() || null, `Radius ${loc.radius_m} m`].filter(Boolean).join(' · '));
        }
        setText(find(view, 'tvServerTime'), Format.nowTime());
        ['tvDistance', 'tvAccuracy', 'tvSchedule'].forEach(id => setText(find(view, id), '-'));
        onClick(view, 'btnLocate', () => locateAndSend(view, ctx));
        onClick(view, 'btnAuto', () => locateAndSend(view, ctx));
        showMap(view, ctx, null);
        locateAndSend(view, ctx);
    },
});

function showStatus(view, text, retry) {
    setText(find(view, 'tvHint'), text);
    const btn = find(view, 'btnAuto');
    setText(btn, retry ? S('checkin_retry') : text);
    setEnabled(btn, retry);
}

async function locateAndSend(view, ctx) {
    if (ctx.busy) return;
    ctx.busy = true;
    showStatus(view, S('checkin_locating'), false);
    const location = await Locator.current();
    if (!ctx.alive) return;
    if (location.error) {
        ctx.busy = false;
        return showStatus(view, S(location.error === 'denied' ? 'msg_need_location' : 'msg_gps_failed'), true);
    }
    showLocalPreview(view, ctx, location);
    showStatus(view, S('checkin_sending'), false);
    const uuid = Outbox.newUuid();
    const body = Locator.body(location, uuid);
    AttendanceFlow.kind = ctx.kind;
    AttendanceFlow.accuracyM = location.accuracy;
    const r = ctx.kind === 'check-out' ? await api.checkOut(body) : await api.checkIn(body);
    ctx.busy = false;
    if (!ctx.alive) return;
    if (r.ok) {
        AttendanceFlow.attendance = r.value.data;
        AttendanceFlow.message = r.value.message;
        replace(ctx.kind === 'check-out' ? 'checkOutSuccess' : 'checkInSuccess');
    } else if (r.offline) {
        Outbox.add({ kind: ctx.kind, body: { ...body, source: 'offline' }, savedAt: new Date().toTimeString().slice(0, 8) });
        replace('offlineSaved');
    } else {
        AttendanceFlow.message = r.message;
        AttendanceFlow.distanceM = r.body?.distance_m ?? null;
        if (r.code === 422 && r.message.includes('luar radius')) replace('outsideRadius');
        else { showStatus(view, r.message, true); alertDialog(r.message, S('checkin_failed_title')); }
    }
}

function showLocalPreview(view, ctx, location) {
    AttendanceFlow.location = location;
    showMap(view, ctx, location);
    const loc = Session.user?.work_location;
    if (!loc) return;
    const d = distanceM(location.latitude, location.longitude, loc.latitude, loc.longitude);
    const inside = d <= loc.radius_m;
    setText(find(view, 'tvDistance'), Format.meters(d));
    setText(find(view, 'tvMyCoords'), S('place_coords', location.latitude, location.longitude, location.accuracy));
    const chip = find(view, 'tvOfficeDistance');
    setText(chip, d >= 1000 ? `${(d / 1000).toFixed(1).replace('.', ',')} km` : Format.meters(d));
    setBg(chip, inside ? 'bg_chip_success' : 'bg_chip_danger');
    setColor(chip, inside ? 'success' : 'danger');
    showAddress(view, ctx, location);
    setText(find(view, 'tvAccuracy'), S('checkin_accuracy', Math.round(location.accuracy)));
    setText(find(view, 'tvAreaTitle'), S(inside ? 'checkin_inside' : 'checkin_outside'));
}

/** Nama tempat dari titik GPS (pengganti Geocoder Android). Jika gagal, cukup "Koordinat GPS". */
async function showAddress(view, ctx, location) {
    let address = null;
    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&accept-language=id&lat=${location.latitude}&lon=${location.longitude}`;
        const a = (await (await fetch(url)).json()).address || {};
        const place = a.road || a.pedestrian || a.amenity || a.building;
        address = [...new Set([place, a.village || a.suburb || a.neighbourhood, a.city || a.town || a.county].filter(Boolean))].join(', ') || null;
    } catch { /* tidak ada internet */ }
    if (ctx.alive) setText(find(view, 'tvMyPlace'), address || S('place_gps_only'));
}

defineScreen('outsideRadius', {
    layout: 'fragment_outside_radius',
    onReady(view, args, ctx) {
        showMap(view, ctx, AttendanceFlow.location);
        const distance = Format.meters(AttendanceFlow.distanceM);
        setText(find(view, 'tvHeadline'), S('outside_headline', distance));
        setText(find(view, 'tvDistance'), distance);
        setText(find(view, 'tvAccuracy'), Format.meters(AttendanceFlow.accuracyM));
        const place = Session.user?.work_location;
        setText(find(view, 'tvRadius'), `${place?.radius_m ?? 100} m`);
        setText(find(view, 'tvMessage'), AttendanceFlow.message || S('outside_radius_absen_hanya_bisa_dalam', place?.radius_m ?? 100, place?.name ?? 'Puskesmas'));
        const again = () => replace('checkIn', { kind: AttendanceFlow.kind });
        onClick(view, 'btnRefresh', again);
        onClick(view, 'btnLocate', again);
        onClick(view, 'rowFieldDuty', () => go('fieldDuty'));
    },
});

function fillResult(view, checkOut) {
    const a = AttendanceFlow.attendance;
    if (!a) return;
    const place = Session.user?.work_location?.name || 'Puskesmas';
    setText(find(view, 'tvTime'), Format.time(checkOut ? a.check_out_at : a.check_in_at));
    setText(find(view, 'tvPlace'), S('result_place', place, Format.meters(checkOut ? a.check_out_distance_m : a.check_in_distance_m)));
    const [note, warn] = checkOut && a.early_leave_minutes > 0 ? [`Pulang cepat ${a.early_leave_minutes} menit`, true]
        : checkOut ? ['Sesuai jadwal', false]
            : a.status === 'terlambat' ? [S('result_late', a.late_minutes), true] : ['Tepat waktu', false];
    const tv = find(view, 'tvNote');
    setText(tv, note);
    setColor(tv, warn ? 'warn' : 'success');
    if (AttendanceFlow.message) setText(find(view, 'tvSubtitle'), AttendanceFlow.message);
}

['checkInSuccess', 'checkOutSuccess'].forEach(name => defineScreen(name, {
    layout: name === 'checkInSuccess' ? 'fragment_checkin_success' : 'fragment_checkout_success',
    onReady(view) {
        fillResult(view, name === 'checkOutSuccess');
        onClick(view, 'btnPrimary', () => backTo('home'));
        onClick(view, 'tvDetail', () => go('history'));
    },
}));

defineScreen('offlineSaved', {
    layout: 'fragment_offline_saved',
    onReady(view) {
        setText(find(view, 'tvType'), S(AttendanceFlow.kind === 'check-out' ? 'checkout_title' : 'checkin_title'));
        setText(find(view, 'tvTime'), Format.nowTime());
        setText(find(view, 'tvQueue'), S('queue_count', Outbox.all().length));
        onClick(view, 'btnRetry', async () => {
            const sent = await Outbox.flush();
            if (!Outbox.all().length) { toast(S('msg_sync_done', sent)); backTo('home'); } else toast(S('msg_still_offline'));
        });
        onClick(view, 'btnPrimary', () => backTo('home'));
    },
});

// Antrean terkirim otomatis begitu internet tersambung lagi (pengganti WorkManager).
window.addEventListener('online', () => { if (Session.isLoggedIn && Outbox.all().length) Outbox.flush(); });

// ------------------------------------------------------------------ Tugas lapangan
defineScreen('fieldDuty', {
    layout: 'fragment_field_duty',
    onReady(view, args, ctx) {
        const place = find(view, 'tvDutyType');
        const letter = find(view, 'etLetterNo');
        const reason = find(view, 'etReason');
        reason.value = '';
        ctx.photo = null;
        ctx.gps = null;
        onClickEl(place, () => {
            const input = el('<textarea rows="1" style="min-height:48px"></textarea>');
            input.value = getText(place);
            dialog({ title: S('duty_location_title'), body: input, buttons: [
                { text: 'Batal' },
                { text: 'OK', onClick: () => { if (input.value.trim()) setText(place, input.value.trim()); } },
            ] });
            setTimeout(() => input.focus(), 50);
        });
        // Foto langsung dari kamera (bukan galeri), sama seperti aplikasi.
        onClick(view, 'boxPhoto', async () => {
            const blob = await pickImage({ capture: 'environment' });
            if (!blob || !ctx.alive) return;
            ctx.photo = blob;
            setText(find(view, 'tvPhotoLabel'), S('photo_taken'));
        });
        Locator.current().then(fix => {
            if (!ctx.alive) return;
            const coords = find(view, 'tvGpsCoords');
            if (fix.error) setText(coords, S('msg_gps_failed'));
            else { ctx.gps = fix; setText(coords, S('duty_gps_value', fix.latitude, fix.longitude, fix.accuracy)); }
        });
        onClick(view, 'btnSubmit', async () => {
            if (!getText(place).trim()) return toast(S('msg_fill_place'));
            if (!ctx.photo) return toast(S('msg_take_photo'));
            if (!reason.value.trim()) return toast(S('msg_fill_form'));
            const form = new FormData();
            form.append('date', isoDate(new Date()));
            form.append('location_name', getText(place).trim());
            form.append('description', reason.value.trim() + (letter.value.trim() ? ` (Surat tugas: ${letter.value.trim()})` : ''));
            if (ctx.gps) { form.append('latitude', ctx.gps.latitude); form.append('longitude', ctx.gps.longitude); }
            form.append('foto', ctx.photo, 'tugas.jpg');
            const r = await api.createFieldDuty(form);
            if (r.ok) { toast(r.value.message); backTo('home'); } else showFailure(r);
        });
    },
});

defineScreen('facePhoto', { layout: 'fragment_face_photo', onReady(view) { onClick(view, 'btnShutter', () => replace('checkInSuccess')); } });
defineScreen('camera', { layout: 'fragment_camera', onReady(view) { onClick(view, 'btnShutter', () => back()); } });
