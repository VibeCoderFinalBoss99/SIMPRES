/* Izin & Cuti (LeaveScreens.kt). */
'use strict';

let lastLeave = null;

/** Anak langsung dari group yang memuat descendant (childContaining). */
function childContaining(group, descendant) {
    let v = descendant;
    while (v.parentElement !== group) v = v.parentElement;
    return v;
}

defineScreen('leaveList', {
    layout: 'fragment_leave_list',
    onReady(view, args, ctx) {
        ctx.list = find(view, 'itemAccepted').parentElement;
        ctx.anchor = childContaining(ctx.list, find(view, 'tabAll'));
        ctx.status = null;
        const statuses = { tabAll: null, tabPending: 'menunggu', tabAccepted: 'diterima', tabRejected: 'ditolak' };
        singleChoice(view, Object.keys(statuses), id => { ctx.status = statuses[id]; loadLeaves(view, ctx); });
        onClick(view, 'btnNew', () => go('leaveForm'));
        loadLeaves(view, ctx);
    },
});

async function loadLeaves(view, ctx) {
    const list = ctx.list;
    clearAfter(list, ctx.anchor);
    const loading = addMessage(list, S('msg_loading'));
    const status = ctx.status;
    const r = await api.leaves(status);
    if (!ctx.alive || status !== ctx.status) return;
    loading.remove();
    if (r.ok) {
        if (!r.value.data.length) addMessage(list, S('msg_empty'));
        r.value.data.forEach(l => {
            const dates = l.start_date === l.end_date ? Format.day(l.start_date) : `${Format.day(l.start_date)} – ${Format.day(l.end_date)}`;
            addRow(list, 'ic_file', Format.leaveType(l.type), dates, Format.submissionStatus(l.status), toneForStatus(l.status), () => showLeaveDetail(view, ctx, l));
        });
    } else addMessage(list, r.offline ? S('msg_offline_list') : r.message);
}

function showLeaveDetail(view, ctx, l) {
    const time = l.start_time ? `\nJam: ${Format.clock(l.start_time)}–${Format.clock(l.end_time)}` : '';
    const note = l.admin_note ? `\nCatatan admin: ${l.admin_note}` : '';
    const buttons = [];
    if (l.status === 'menunggu') {
        buttons.push({ text: S('leave_cancel'), onClick: async () => {
            const r = await api.cancelLeave(l.id);
            if (r.ok) { toast(S('msg_cancelled')); loadLeaves(view, ctx); } else showFailure(r);
        } });
    }
    buttons.push({ text: S('leave_close') });
    dialog({
        title: Format.leaveType(l.type),
        message: `Tanggal: ${Format.day(l.start_date)} s.d. ${Format.day(l.end_date)}${time}\nAlasan: ${l.reason}\nStatus: ${Format.submissionStatus(l.status)}${note}`,
        buttons,
    });
}

defineScreen('leaveForm', {
    layout: 'fragment_leave_form',
    onReady(view, args, ctx) {
        ctx.type = 'izin_keluar';
        ctx.attachment = null;
        const groupOut = find(view, 'groupOut');
        const groupSick = find(view, 'groupSick');
        const types = { chipOut: 'izin_keluar', chipSick: 'sakit', chipLeave: 'cuti', chipOther: 'lainnya' };
        singleChoice(view, Object.keys(types), id => {
            ctx.type = types[id];
            const range = ctx.type === 'sakit' || ctx.type === 'cuti';
            show(groupSick, range);
            show(groupOut, !range);
            const sick = ctx.type === 'sakit';
            setText(find(view, 'tvComplaintLabel'), S(sick ? 'leave_form_keluhan_alasan' : 'common_alasan'));
            setText(find(view, 'tvFileLabel'), S(sick ? 'leave_form_surat_keterangan_dokter' : 'leave_form_lampiran'));
            setText(find(view, 'tvFileNote'), S(sick ? 'leave_form_wajib_jika_1_hari' : 'common_opsional'));
            setText(find(view, 'tvSickInfo'), S(sick ? 'leave_form_izin_sakit_lebih_dari' : 'leave_form_cuti_info'));
        });
        const today = isoDate(new Date());
        ['etDate', 'etStart'].forEach(id => { const f = find(view, id); makeDateField(f); f.value = today; });
        const end = find(view, 'etEnd'); makeDateField(end); end.value = isoDate(addDays(new Date(), 1));
        const from = find(view, 'etFrom'); makeTimeField(from); from.value = '10:00';
        const to = find(view, 'etTo'); makeTimeField(to); to.value = '11:30';
        find(view, 'etReason').value = '';
        find(view, 'etComplaint').value = '';

        const pick = async () => { const b = await pickImage(); if (b && ctx.alive) { ctx.attachment = b; showFile(view, ctx); } };
        const fileRow = find(view, 'btnRemoveFile').parentElement;
        showFile(view, ctx);
        onClickEl(fileRow, pick);
        onClick(view, 'btnRemoveFile', () => { ctx.attachment = null; showFile(view, ctx); });
        onClick(view, 'rowAttach', pick);
        onClick(view, 'btnSubmit', () => submitLeave(view, ctx));
    },
});

function showFile(view, ctx) {
    const data = ctx.attachment;
    setText(find(view, 'rowAttach').children[1], data ? S('attach_selected', Math.round(data.size / 1024)) : S('common_tambah_lampiran'));
    setText(find(view, 'tvFileName'), data ? 'surat-keterangan.jpg' : S('pick_file_hint'));
    setText(find(view, 'tvFileSize'), data ? S('file_size', Math.round(data.size / 1024)) : '');
    show(find(view, 'btnRemoveFile'), !!data);
}

async function submitLeave(view, ctx) {
    const v = id => find(view, id).value.trim();
    let fields;
    if (ctx.type === 'sakit' || ctx.type === 'cuti') {
        fields = { type: ctx.type, start_date: v('etStart'), end_date: v('etEnd'), reason: v('etComplaint') };
    } else {
        const date = v('etDate');
        fields = { type: ctx.type, start_date: date, end_date: date, reason: v('etReason') };
        if (ctx.type === 'izin_keluar') { fields.start_time = v('etFrom').slice(0, 5); fields.end_time = v('etTo').slice(0, 5); }
    }
    if (!fields.reason) return toast(S('msg_fill_form'));
    if (ctx.type === 'sakit' && !ctx.attachment) return toast(S('msg_pick_photo'));
    let payload = fields;
    if (ctx.attachment) {
        payload = new FormData();
        Object.entries(fields).forEach(([k, val]) => payload.append(k, val));
        payload.append('lampiran', ctx.attachment, 'surat.jpg');
    }
    const r = await api.createLeave(payload);
    if (r.ok) { lastLeave = r.value.data; replace('leaveSent'); return; }
    if (r.offline) return showFailure(r);
    const errors = r.body?.errors;
    alertDialog(errors ? Object.values(errors).flat().join('\n') : r.message);
}

defineScreen('leaveSent', {
    layout: 'fragment_leave_sent',
    onReady(view) {
        const l = lastLeave;
        if (l) {
            setText(find(view, 'tvSentType'), Format.leaveType(l.type));
            setText(find(view, 'tvSentDate'), l.start_date === l.end_date ? Format.day(l.start_date) : `${Format.day(l.start_date)} – ${Format.day(l.end_date)}`);
            setText(find(view, 'tvSentTime'), l.start_time ? `${Format.clock(l.start_time)}–${Format.clock(l.end_time)}` : S('leave_all_day'));
            setText(find(view, 'tvSentAt'), `${Format.day(l.created_at.slice(0, 10))} · ${Format.time(l.created_at)}`);
        }
        onClick(view, 'btnPrimary', () => backTo('leaveList'));
        onClick(view, 'tvHome', () => backTo('home'));
    },
});

defineScreen('leaveAccepted', { layout: 'fragment_leave_accepted', onReady(view) { onClick(view, 'btnPrimary', () => backTo('leaveList')); } });
defineScreen('leaveRejected', { layout: 'fragment_leave_rejected', onReady(view) { onClick(view, 'btnPrimary', () => replace('leaveForm')); } });
