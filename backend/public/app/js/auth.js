/* Splash, onboarding, login, lupa sandi (AuthScreens.kt) dan Pengaturan (SettingsScreens.kt). */
'use strict';

const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Animasi Lottie dari res/raw yang sama dengan aplikasi Android. */
function playLottie(container, raw, { loop = false, autoplay = true, onComplete } = {}) {
    const anim = lottie.loadAnimation({ container, renderer: 'svg', loop, autoplay, path: `gen/raw/${raw}.json` });
    if (onComplete) anim.addEventListener('complete', onComplete);
    return anim;
}

defineScreen('splash', {
    layout: 'fragment_splash',
    fullBleed: true,
    statusBar: 'primary',
    onReady(view, args, ctx) {
        const intro = find(view, 'lottieIntro');
        const texts = ['tvAppName', 'vGoldBar', 'tvTagline'].map(id => find(view, id));
        const next = () => { if (ctx.alive) Session.isLoggedIn ? reset('home', 'splash') : replace('onboarding'); };
        if (reducedMotion()) {
            const a = playLottie(intro, 'intro_simpres', { autoplay: false });
            a.addEventListener('DOMLoaded', () => a.goToAndStop(a.totalFrames - 1, true));
            later(ctx, 900, next);
            return;
        }
        texts.forEach((t, i) => t.animate(
            [{ opacity: 0, transform: 'translateY(16px)' }, { opacity: 1, transform: 'none' }],
            { duration: 450, delay: 900 + i * 120, fill: 'backwards', easing: 'ease-out' }));
        playLottie(intro, 'intro_simpres', { onComplete: () => later(ctx, 600, next) });
        later(ctx, 6000, next); // cadangan jika animasi gagal dimuat
    },
});

const onboardingPages = [
    ['onboarding_location', 'onboarding_absen_cukup_dari_hp', 'onboarding_lokasimu_dicek_otomatis_dengan'],
    ['onboarding_offline', 'onboarding_offline_title', 'onboarding_offline_desc'],
    ['onboarding_leave', 'onboarding_leave_title', 'onboarding_leave_desc'],
];

defineScreen('onboarding', {
    layout: 'fragment_onboarding',
    onReady(view, args, ctx) {
        const pager = find(view, 'pager');
        const dots = find(view, 'dots');
        const step = find(view, 'tvStep');
        const next = find(view, 'btnNext');
        const last = onboardingPages.length - 1;
        pager.style.cssText += ';display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none';
        const anims = onboardingPages.map(([raw, title, desc]) => {
            const page = inflate('item_onboarding');
            page.style.cssText += ';flex:0 0 100%;height:100%;scroll-snap-align:start';
            setText(find(page, 'tvTitle'), S(title));
            setText(find(page, 'tvDesc'), S(desc));
            pager.appendChild(page);
            return playLottie(find(page, 'lottie'), raw, { loop: true, autoplay: false });
        });
        let current = -1;
        const select = pos => {
            if (pos === current) return;
            current = pos;
            setText(step, S('onboarding_step', pos + 1, onboardingPages.length));
            setText(next, S(pos === last ? 'onboarding_mulai' : 'onboarding_lanjut'));
            [...dots.children].forEach((d, i) => {
                d.style.transition = 'width .22s';
                d.style.width = (i === pos ? 24 : 6) + 'px';
                d.style.backgroundColor = `var(--${i === pos ? 'primary' : 'dot_off'})`;
            });
            anims.forEach((a, i) => (i === pos && !reducedMotion() ? a.play() : a.pause()));
        };
        pager.addEventListener('scroll', () => select(Math.round(pager.scrollLeft / pager.clientWidth)));
        select(0);
        onClick(view, 'btnSkip', () => replace('login'));
        onClickEl(next, () => {
            if (current === last) replace('login');
            else pager.scrollTo({ left: (current + 1) * pager.clientWidth, behavior: 'smooth' });
        });
        ctx.anims = anims;
    },
    onDestroy(ctx) { ctx.anims?.forEach(a => a.destroy()); },
});

defineScreen('login', {
    layout: 'fragment_login',
    background: 'surface',
    statusBar: 'surface',
    onReady(view) {
        const username = find(view, 'etUsername');
        const password = find(view, 'etPassword');
        const toggle = find(view, 'btnTogglePassword');
        onClickEl(toggle, () => {
            const hidden = password.type === 'password';
            password.type = hidden ? 'text' : 'password';
            setImage(toggle, hidden ? 'ic_eye_off' : 'ic_eye');
            toggle.setAttribute('aria-label', S(hidden ? 'login_sembunyikan_kata_sandi' : 'login_tampilkan_kata_sandi'));
        });
        username.value = '';
        password.value = '';
        username.placeholder = S('login_email_hint');
        username.autocomplete = 'username';
        password.autocomplete = 'current-password';
        onClick(view, 'tvForgot', () => go('forgot'));
        const button = find(view, 'btnLogin');
        const submit = async () => {
            const email = username.value.trim();
            const pass = password.value;
            if (!email || !pass) return toast(S('msg_fill_credentials'));
            setEnabled(button, false);
            setText(button, S('login_loading'));
            const r = await api.login({ email, password: pass });
            if (r.ok) {
                Session.save(r.value.token, r.value.user);
                if (r.value.user.role === 'admin') toast(S('login_admin_note'));
                return reset('home', 'login');
            }
            showFailure(r);
            setEnabled(button, true);
            setText(button, S('login_masuk'));
        };
        onClickEl(button, submit);
        password.addEventListener('keydown', e => { if (e.key === 'Enter') submit(); });
    },
});

defineScreen('forgot', {
    layout: 'fragment_forgot_password',
    onReady(view) {
        const input = find(view, 'etUsername');
        input.value = '';
        const button = find(view, 'btnSend');
        onClickEl(button, async () => {
            const login = input.value.trim();
            if (!login) return toast(S('forgot_fill'));
            setEnabled(button, false);
            setText(button, S('login_loading'));
            const r = await api.forgotPassword({ login });
            if (r.ok) return replace('resetSent', { email: r.value.email, login });
            showFailure(r);
            setEnabled(button, true);
            setText(button, S('forgot_password_kirim_tautan_reset'));
        });
        onClick(view, 'tvBackToLogin', () => backTo('login'));
    },
});

defineScreen('resetSent', {
    layout: 'fragment_reset_sent',
    onReady(view, args, ctx) {
        setText(find(view, 'tvSentTo'), S('reset_sent_tautan_atur_ulang_kata', args.email || ''));
        onClick(view, 'btnBackToLogin', () => backTo('login'));
        const resend = find(view, 'tvResend');
        const countdown = () => {
            setEnabled(resend, false);
            let s = 60;
            setText(resend, S('reset_sent_resend_wait', s));
            const t = every(ctx, 1000, () => {
                s--;
                if (s > 0) return setText(resend, S('reset_sent_resend_wait', s));
                clearInterval(t);
                setText(resend, S('reset_sent_resend'));
                setEnabled(resend, true);
            });
        };
        countdown();
        onClickEl(resend, async () => {
            setEnabled(resend, false);
            const r = await api.forgotPassword({ login: args.login || '' });
            if (r.ok) { toast(r.value.message); countdown(); } else { showFailure(r); setEnabled(resend, true); }
        });
    },
});

// ------------------------------------------------------------------ Pengaturan
defineScreen('config', {
    layout: 'fragment_config',
    onReady(view) {
        onClick(view, 'rowResetPassword', () => go('forgot'));
        onClick(view, 'rowChangeInfo', askChangeInfo);
        const u = Session.user;
        if (u) {
            setText(find(view, 'tvUserName'), u.name);
            setText(find(view, 'tvUserRole'), [u.position, u.unit].filter(Boolean).join(' · '));
            // Mode Tugas Lapangan mengikuti jenis kerja yang diatur admin.
            const sw = find(view, 'swFieldMode');
            sw.checked = u.work_type === 'petugas_lapangan';
            sw.disabled = true;
            sw.parentElement.setAttribute('aria-label', S(sw.checked ? 'config_field_on' : 'config_field_off'));
        }
        const themeValue = find(view, 'tvThemeValue');
        setText(themeValue, ThemeMode.label());
        onClick(view, 'rowTheme', () => {
            const keys = ThemeMode.options.map(o => o[0]);
            choiceDialog(S('config_mode_tampilan'), ThemeMode.options.map(o => o[1]), keys.indexOf(ThemeMode.current()), i => {
                ThemeMode.set(keys[i]);
                setText(themeValue, ThemeMode.label());
            });
        });
        onClick(view, 'btnSave', () => { toast(S('msg_saved')); back(); });
    },
});

function askChangeInfo() {
    const input = el(`<textarea rows="3"></textarea>`);
    input.placeholder = S('change_info_hint');
    dialog({
        title: S('config_ajukan_perubahan_informasi'),
        message: S('change_info_body'),
        body: input,
        buttons: [
            { text: S('checkout_early_cancel') },
            { text: S('change_info_send'), onClick: async () => {
                const text = input.value.trim();
                if (text.length < 5) return toast(S('change_info_short'));
                const r = await api.requestProfileChange({ message: text });
                if (r.ok) alertDialog(r.value.message); else showFailure(r);
            } },
        ],
    });
}

defineScreen('sync', {
    layout: 'fragment_sync',
    onReady(view, args, ctx) {
        const render = () => {
            const items = Outbox.all();
            setText(find(view, 'tvNetwork'), S(isOnline() ? 'sync_online' : 'sync_offline'));
            setText(find(view, 'tvLastSync'), Outbox.lastSync ? S('sync_last', Outbox.lastSync) : '-');
            setText(find(view, 'tvQueued'), String(items.length));
            setText(find(view, 'tvFailed'), '0');
            const list = find(view, 'queueList');
            clearAfter(list, null);
            if (!items.length) addMessage(list, S('msg_nothing_queued'));
            items.forEach(i => addRow(list, 'ic_sync', i.kind === 'check-out' ? 'Check-out' : 'Check-in',
                `Disimpan di perangkat · ${i.savedAt.slice(0, 5)}`, 'Menunggu', Tone.WARN));
        };
        render();
        onClick(view, 'btnSync', async () => {
            if (!Outbox.all().length) return toast(S('msg_nothing_queued'));
            const sent = await Outbox.flush();
            toast(!Outbox.all().length ? S('msg_sync_done', sent) : S('msg_still_offline'));
            if (ctx.alive) render();
        });
    },
});

defineScreen('privacy', {
    layout: 'fragment_privacy',
    onReady(view) {
        // Di web, izin lokasi diatur dari pengaturan browser, bukan pengaturan aplikasi Android.
        const btn = find(view, 'btnOpenSettings');
        setText(btn, 'Cara mengatur izin lokasi');
        onClickEl(btn, () => alertDialog(/iPhone|iPad/.test(navigator.userAgent)
            ? 'Buka Pengaturan iPhone > Privasi & Keamanan > Layanan Lokasi > Safari (atau SIMPRES jika sudah ditambahkan ke Layar Utama), lalu pilih "Saat Digunakan" dan nyalakan Lokasi Akurat.'
            : 'Ketuk ikon gembok di samping alamat situs, lalu izinkan Lokasi untuk SIMPRES.', 'Izin lokasi'));
    },
});

defineScreen('about', { layout: 'fragment_about', onReady() {} });
