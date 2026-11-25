package com.simpres.app.ui.home

import android.Manifest
import android.app.TimePickerDialog
import android.content.pm.PackageManager
import android.os.Build
import android.graphics.Color
import android.graphics.drawable.ColorDrawable
import android.graphics.drawable.InsetDrawable
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.core.content.ContextCompat
import androidx.core.os.bundleOf
import com.simpres.app.R
import com.simpres.app.data.ApiResult
import com.simpres.app.data.Attendance
import com.simpres.app.data.Format
import com.simpres.app.data.Outbox
import com.simpres.app.data.Session
import com.simpres.app.data.Today
import com.simpres.app.data.call
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.CoachMarks
import com.simpres.app.ui.GuideStep
import com.simpres.app.ui.Tone
import com.simpres.app.ui.addMessage
import com.simpres.app.ui.addRow
import com.simpres.app.ui.attendance.AttendanceFlow
import com.simpres.app.ui.childContaining
import com.simpres.app.ui.clearAfter
import com.simpres.app.ui.onClick
import com.simpres.app.ui.setupSwipeToConfirm
import com.simpres.app.ui.toneForStatus
import java.time.Duration
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import com.simpres.app.ui.shrinkImage
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.format.TextStyle
import java.util.Locale

private val ID = Locale("id", "ID")

/** Beranda: employee, this week, today's attendance (from the server) and the offline queue. */
class HomeFragment : BaseFragment(R.layout.fragment_home) {
    // Android 13 ke atas: izin notifikasi diminta supaya keputusan admin bisa muncul di HP.
    private val notifPermission = registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (!granted) toast(getString(R.string.notif_permission_denied))
    }

    /** Geser di Beranda: check-in sebelum masuk, check-out setelah check-in. */
    private var swipeKind = AttendanceFlow.CHECK_IN
    private var resetSwipe: () -> Unit = {}
    private var current: Today? = null

    override fun onReady(view: View) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU &&
            ContextCompat.checkSelfPermission(requireContext(), Manifest.permission.POST_NOTIFICATIONS) !=
            PackageManager.PERMISSION_GRANTED
        ) notifPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
        bindBottomNav(view, R.id.homeFragment)
        view.onClick(R.id.btnBell) { go(R.id.notificationFragment) }
        view.onClick(R.id.tvSeeAll) { go(R.id.historyFragment) }
        view.onClick(R.id.cardSync) { go(R.id.syncFragment) }
        resetSwipe = setupSwipeToConfirm(
            track = view.findViewById(R.id.swipeCheckOut),
            knob = view.findViewById(R.id.swipeKnob),
            label = view.findViewById(R.id.tvSwipeLabel),
        ) { if (isAdded) confirmSwipe() }

        showUser(view)
        showWeek(view)
        showQueue(view)
        loadToday(view)
        showGuide(view)
    }

    private fun showUser(view: View) {
        val user = Session.user ?: return
        view.findViewById<TextView>(R.id.tvName).text = user.name
        view.findViewById<TextView>(R.id.tvAvatar).text = Format.initials(user.name)
        view.findViewById<TextView>(R.id.tvPosition).text = listOfNotNull(user.position, user.unit).joinToString(" · ")
    }

    /** Five day chips around today; the middle one (today) keeps the highlighted style from the design. */
    private fun showWeek(view: View) {
        val strip = view.findViewById<ViewGroup>(R.id.weekStrip)
        val today = LocalDate.now()
        for (i in 0 until strip.childCount) {
            val day = today.plusDays((i - 2).toLong())
            val chip = strip.getChildAt(i) as? ViewGroup ?: continue
            (chip.getChildAt(0) as? TextView)?.text = day.dayOfMonth.toString()
            (chip.getChildAt(1) as? TextView)?.text = day.dayOfWeek.getDisplayName(TextStyle.SHORT, ID)
        }
    }

    private fun showQueue(view: View) {
        val pending = Outbox.all().size
        view.findViewById<TextView>(R.id.tvSyncTitle).text =
            if (pending == 0) getString(R.string.sync_safe) else getString(R.string.sync_pending, pending)
        view.findViewById<TextView>(R.id.tvSyncNote)
            .setText(if (pending == 0) R.string.sync_all_sent else R.string.sync_waiting)
    }

    private fun loadToday(view: View) = launch {
        // Show the last known data at once (or empty placeholders), so the sample text of the layout never flashes.
        val cached = lastToday?.takeIf { it.first == Session.user?.id }?.second
        if (cached != null) showToday(view, cached) else showPlaceholders(view)

        if (Outbox.all().isNotEmpty() && Outbox.flush() > 0) showQueue(view)
        val result = call { today() }
        if (result !is ApiResult.Ok) {
            if (result is ApiResult.Failed && result.code == 401) reset(R.id.loginFragment, R.id.homeFragment)
            return@launch
        }
        lastToday = Session.user?.id to result.value.data
        showToday(view, result.value.data)
    }

    private fun showPlaceholders(view: View) {
        for (id in listOf(R.id.tvCheckInTime, R.id.tvCheckOutTime)) view.findViewById<TextView>(id).text = "--.--"
        for (id in listOf(R.id.tvShift, R.id.tvCheckInStatus, R.id.tvCheckOutNote, R.id.tvDistance, R.id.tvDistanceNote,
            R.id.tvActivityTitle, R.id.tvActivityDate)) view.findViewById<TextView>(id).text = "-"
        view.findViewById<TextView>(R.id.tvShift).setText(R.string.msg_loading)
    }

    private fun showToday(view: View, t: Today) {
        view.findViewById<TextView>(R.id.tvShift).text = when {
            t.schedule == null -> getString(R.string.today_no_schedule)
            t.schedule.isDayOff -> getString(R.string.today_day_off)
            else -> "${t.schedule.shift ?: "Jadwal"} · ${Format.clock(t.schedule.startTime)}–${Format.clock(t.schedule.endTime)}"
        }
        current = t
        val a = t.attendance
        val start = t.schedule?.startTime?.takeIf { !t.schedule.isDayOff }
        view.findViewById<TextView>(R.id.tvCheckInTime).text = Format.time(a?.checkInAt)
        // Keterangan kartu Check-in: tepat waktu (hijau) atau terlambat (kuning); sebelum absen: jam masuk shift.
        showNote(view.findViewById(R.id.tvCheckInStatus), when {
            a == null && start != null -> "Belum check-in · masuk ${Format.clock(start)}" to R.color.ink_muted
            a == null -> getString(R.string.today_none) to R.color.ink_muted
            a.status == "terlambat" -> getString(R.string.result_late, a.lateMinutes) to R.color.warn
            a.status == "hadir" -> "Tepat waktu" to R.color.success
            else -> Format.attendanceStatus(a.status) to R.color.ink_muted
        })
        view.findViewById<TextView>(R.id.tvCheckOutTime).text = Format.time(a?.checkOutAt)
        // Keterangan kartu Check-out: pulang lebih cepat (kuning) atau sesuai jadwal (hijau); sebelumnya: batas pulang.
        showNote(view.findViewById(R.id.tvCheckOutNote), when {
            a?.checkOutAt != null && a.earlyLeaveMinutes > 0 -> "Pulang cepat ${a.earlyLeaveMinutes} menit" to R.color.warn
            a?.checkOutAt != null -> "Sesuai jadwal" to R.color.success
            else -> (t.schedule?.endTime?.takeIf { start != null }?.let { "Batas ${Format.clock(it)}" } ?: "-") to R.color.ink_muted
        })
        view.findViewById<TextView>(R.id.tvDistance).text = Format.meters(a?.checkOutDistanceM ?: a?.checkInDistanceM)
        view.findViewById<TextView>(R.id.tvDistanceNote).text = if (a?.checkInAt != null) "Di dalam area" else "Belum ada data"
        view.findViewById<TextView>(R.id.tvActivityTitle).text = when {
            a?.checkOutAt != null -> "Check-out ${Format.time(a.checkOutAt)}"
            a?.checkInAt != null -> "Check-in ${Format.time(a.checkInAt)}"
            else -> getString(R.string.today_none)
        }
        view.findViewById<TextView>(R.id.tvActivityDate).text = Format.day(t.date)
        showSwipe(view, t)
    }

    /**
     * Satu tombol geser untuk dua hal: "Geser untuk Check-in" sebelum check-in, lalu "Geser untuk Check-out".
     * Absensi yang masih di antrean offline dianggap sudah dilakukan, supaya tidak terkirim dua kali.
     * Setelah check-out, tombol disembunyikan.
     */
    private fun showNote(tv: TextView, note: Pair<String, Int>) {
        tv.text = note.first
        tv.setTextColor(ContextCompat.getColor(requireContext(), note.second))
    }

    /**
     * Sebelum check-out lewat geser: jika belum jam pulang shift, tanya dulu, karena absensi akan dicatat
     * pulang lebih cepat. Shift malam (jam pulang lebih kecil dari jam masuk) pulang esok hari.
     */
    private fun confirmSwipe() {
        val open = { go(R.id.checkInFragment, bundleOf(AttendanceFlow.ARG_KIND to swipeKind)) }
        val s = current?.schedule
        val endText = s?.endTime
        if (s == null || endText == null || s.isDayOff || swipeKind != AttendanceFlow.CHECK_OUT) return open()
        val end = LocalTime.parse(endText)
        val start = s.startTime?.let { LocalTime.parse(it) }
        val endAt = LocalDate.now().atTime(end).let { if (start != null && !end.isAfter(start)) it.plusDays(1) else it }
        val now = LocalDateTime.now()
        if (!now.isBefore(endAt)) return open()
        val minutes = Duration.between(now, endAt).toMinutes() + 1
        AlertDialog.Builder(requireContext())
            .setTitle(R.string.checkout_early_title)
            .setMessage(getString(R.string.checkout_early_body, s.shift ?: "kerja", Format.clock(endText), minutes))
            .setNegativeButton(R.string.checkout_early_cancel) { _, _ -> resetSwipe() }
            .setPositiveButton(R.string.checkout_early_ok) { _, _ -> open() }
            .setOnCancelListener { resetSwipe() }
            .show()
    }

    private fun showSwipe(view: View, t: Today) {
        val queued = Outbox.all().map { it.kind }
        val checkedIn = t.hasCheckedIn || AttendanceFlow.CHECK_IN in queued
        val checkedOut = t.hasCheckedOut || AttendanceFlow.CHECK_OUT in queued
        swipeKind = if (checkedIn) AttendanceFlow.CHECK_OUT else AttendanceFlow.CHECK_IN
        val label = getString(if (checkedIn) R.string.home_geser_untuk_check_out else R.string.home_geser_untuk_check_in)
        view.findViewById<TextView>(R.id.tvSwipeLabel).text = label
        view.findViewById<View>(R.id.swipeCheckOut).apply {
            contentDescription = label
            visibility = if (checkedOut) View.GONE else View.VISIBLE
        }
    }

    companion object {
        /** Last "today" data per user, kept while the app is open. */
        private var lastToday: Pair<Long?, Today>? = null
    }

    /** First-run guide; shown once, can be shown again from Profil. */
    private fun showGuide(view: View) = view.post {
        if (!isAdded) return@post
        CoachMarks.showOnce(requireActivity(), "home", listOf(
            GuideStep(view.findViewById<View>(R.id.gridToday).parent as View, R.string.guide_today_title, R.string.guide_today_body),
            GuideStep(view.findViewById(R.id.navFab), R.string.guide_checkin_title, R.string.guide_checkin_body),
            GuideStep(view.findViewById(R.id.swipeCheckOut), R.string.guide_checkout_title, R.string.guide_checkout_body),
            GuideStep(view.findViewById(R.id.cardSync), R.string.guide_sync_title, R.string.guide_sync_body),
            GuideStep(view.findViewById(R.id.btnBell), R.string.guide_bell_title, R.string.guide_bell_body),
            GuideStep(view.findViewById(R.id.navProfile), R.string.guide_menu_title, R.string.guide_menu_body),
        ).filter { it.target.isShown }) // tombol geser disembunyikan setelah check-out
    }
}

class AttendanceDetailFragment : BaseFragment(R.layout.fragment_attendance_detail) {
    override fun onReady(view: View) = Unit
}

/** Koreksi absensi hari ini: the wrong part is the check-in if there is none yet, otherwise the check-out. */
class CorrectionFragment : BaseFragment(R.layout.fragment_correction) {
    private var reason = "GPS tidak akurat"
    private var fixCheckIn = true
    /** Lampiran bukti (opsional), sudah diperkecil menjadi JPEG di bawah 2 MB. */
    private var attachment: ByteArray? = null
    private val picker = registerForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        val bytes = uri?.let { shrinkImage(requireContext(), it) } ?: return@registerForActivityResult
        attachment = bytes
        view?.let(::showAttachment)
    }

    private fun showAttachment(view: View) {
        (view.findViewById<ViewGroup>(R.id.rowAttach).getChildAt(1) as TextView).text = attachment?.let {
            getString(R.string.attach_selected, it.size / 1024)
        } ?: getString(R.string.common_tambah_lampiran)
    }

    override fun onReady(view: View) {
        val time = view.findViewById<EditText>(R.id.etTime)
        val note = view.findViewById<EditText>(R.id.etNote)
        note.setText("")
        time.setText("")
        time.isFocusable = false
        time.setOnClickListener {
            TimePickerDialog(requireContext(), { _, h, m -> time.setText("%02d:%02d".format(h, m)) }, 8, 0, true).show()
        }
        val labels = mapOf(R.id.chipGps to "GPS tidak akurat", R.id.chipNoInternet to "Tidak ada internet",
            R.id.chipForgot to "Lupa absen", R.id.chipCamera to "Kamera bermasalah", R.id.chipOther to "Lainnya")
        singleChoice(view, R.id.chipGps, R.id.chipNoInternet, R.id.chipForgot, R.id.chipCamera, R.id.chipOther) {
            reason = labels[it] ?: reason
        }
        launch {
            val r = call { today() }
            if (r is ApiResult.Ok) {
                fixCheckIn = !r.value.data.hasCheckedIn
                view.findViewById<TextView>(R.id.tvTarget).text =
                    getString(R.string.correction_target, if (fixCheckIn) "Check-in" else "Check-out")
                view.findViewById<TextView>(R.id.tvTargetNote).text = Format.day(r.value.data.date)
            }
        }
        view.findViewById<View>(R.id.rowAttach).apply {
            setOnClickListener { picker.launch("image/*") }
            setOnLongClickListener { attachment = null; showAttachment(view); true }
        }
        view.onClick(R.id.btnSubmit) {
            if (time.text.isBlank() || note.text.isBlank()) return@onClick toast(R.string.msg_fill_form)
            val body = mutableMapOf("date" to LocalDate.now().toString(), "reason" to "$reason: ${note.text.trim()}")
            body[if (fixCheckIn) "corrected_check_in" else "corrected_check_out"] = time.text.toString()
            launch {
                val file = attachment
                val result = if (file == null) call { createCorrection(body) } else {
                    val plain = "text/plain".toMediaType()
                    val part = MultipartBody.Part.createFormData("lampiran", "bukti.jpg", file.toRequestBody("image/jpeg".toMediaType()))
                    call { createCorrectionWithFile(body.mapValues { it.value.toRequestBody(plain) }, part) }
                }
                when (val r = result) {
                    is ApiResult.Ok -> { toast(r.value.get("message").asString); backTo(R.id.homeFragment) }
                    is ApiResult.Failed -> alert(r.message)
                    ApiResult.Offline -> alert(getString(R.string.msg_server_unreachable))
                }
            }
        }
    }
}

class NotificationFragment : BaseFragment(R.layout.fragment_notification) {
    override fun onReady(view: View) {
        view.onClick(R.id.tvMarkRead) { launch { call { readAllNotifications() }; load(view) } }
        load(view)
    }

    private fun load(view: View) = launch {
        val list = view.findViewById<ViewGroup>(R.id.listContent)
        list.clearAfter(null)
        val loading = list.addMessage(getString(R.string.msg_loading))
        val r = call { notifications() }
        list.removeView(loading)
        when (r) {
            is ApiResult.Ok -> {
                if (r.value.data.isEmpty()) list.addMessage(getString(R.string.msg_empty))
                r.value.data.forEach { n ->
                    val tone = when {
                        n.title.contains("diterima", true) -> Tone.SUCCESS
                        n.title.contains("ditolak", true) -> Tone.DANGER
                        else -> Tone.PRIMARY
                    }
                    list.addRow(R.drawable.ic_bell, n.title, "${n.body}\n${n.createdAt.take(16)}",
                        if (n.isRead) null else "Baru", tone)
                }
            }
            is ApiResult.Failed -> list.addMessage(r.message)
            ApiResult.Offline -> list.addMessage(getString(R.string.msg_offline_list))
        }
    }
}

class ProfileFragment : BaseFragment(R.layout.fragment_profile) {
    override fun onReady(view: View) {
        bindBottomNav(view, R.id.profileFragment)
        Session.user?.let { u ->
            view.findViewById<TextView>(R.id.tvName).text = u.name
            view.findViewById<TextView>(R.id.tvAvatar).text = Format.initials(u.name)
            view.findViewById<TextView>(R.id.tvPosition).text = u.position ?: "-"
            view.findViewById<TextView>(R.id.tvRole).text = if (u.role == "admin") "Admin" else "Pegawai"
            view.findViewById<TextView>(R.id.tvNip).text = u.nip
            view.findViewById<TextView>(R.id.tvUnit).text = u.unit ?: "-"
            view.findViewById<TextView>(R.id.tvDevice).text = android.os.Build.MODEL
        }
        view.onClick(R.id.rowLeave) { go(R.id.leaveListFragment) }
        view.onClick(R.id.rowPermission) { go(R.id.privacyFragment) }
        view.onClick(R.id.rowPrivacy) { go(R.id.privacyFragment) }
        view.onClick(R.id.rowSettings) { go(R.id.configFragment) }
        view.onClick(R.id.rowCorrection) { go(R.id.correctionFragment) }
        view.onClick(R.id.rowAbout) { go(R.id.aboutFragment) }
        view.onClick(R.id.btnLogout) { showLogoutDialog() }
        view.onClick(R.id.tvShowGuide) {
            CoachMarks.reset(requireContext())
            backTo(R.id.homeFragment)
        }
    }

    private fun showLogoutDialog() {
        val content = layoutInflater.inflate(R.layout.dialog_logout, null)
        val dialog = AlertDialog.Builder(requireContext()).setView(content).create()
        content.onClick(R.id.btnCancel) { dialog.dismiss() }
        content.onClick(R.id.btnConfirm) {
            dialog.dismiss()
            // Token is revoked on the server; unsent attendance stays in the local queue.
            launch {
                call { logout() }
                Session.clear()
                com.simpres.app.data.Background.cancel(requireContext())
                com.simpres.app.data.Push.unregister(requireContext())
                reset(R.id.loginFragment, R.id.homeFragment)
            }
        }
        dialog.show()
        val margin = (28 * resources.displayMetrics.density).toInt()
        dialog.window?.apply {
            setBackgroundDrawable(InsetDrawable(ColorDrawable(Color.TRANSPARENT), margin))
            setLayout(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT)
        }
    }
}
