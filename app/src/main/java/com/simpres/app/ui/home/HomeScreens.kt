package com.simpres.app.ui.home

import android.app.TimePickerDialog
import android.graphics.Color
import android.graphics.drawable.ColorDrawable
import android.graphics.drawable.InsetDrawable
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.TextView
import androidx.appcompat.app.AlertDialog
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
import java.time.LocalDate
import java.time.format.TextStyle
import java.util.Locale

private val ID = Locale("id", "ID")

/** Beranda: employee, this week, today's attendance (from the server) and the offline queue. */
class HomeFragment : BaseFragment(R.layout.fragment_home) {
    override fun onReady(view: View) {
        bindBottomNav(view, R.id.homeFragment)
        view.onClick(R.id.btnBell) { go(R.id.notificationFragment) }
        view.onClick(R.id.tvSeeAll) { go(R.id.historyFragment) }
        view.onClick(R.id.cardSync) { go(R.id.syncFragment) }
        setupSwipeToConfirm(
            track = view.findViewById(R.id.swipeCheckOut),
            knob = view.findViewById(R.id.swipeKnob),
            label = view.findViewById(R.id.tvSwipeLabel),
        ) { if (isAdded) go(R.id.checkInFragment, bundleOf(AttendanceFlow.ARG_KIND to AttendanceFlow.CHECK_OUT)) }

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
        val a = t.attendance
        view.findViewById<TextView>(R.id.tvCheckInTime).text = Format.time(a?.checkInAt)
        view.findViewById<TextView>(R.id.tvCheckInStatus).text = when {
            a == null -> getString(R.string.today_none)
            a.status == "terlambat" -> getString(R.string.result_late, a.lateMinutes)
            else -> Format.attendanceStatus(a.status)
        }
        view.findViewById<TextView>(R.id.tvCheckOutTime).text = Format.time(a?.checkOutAt)
        view.findViewById<TextView>(R.id.tvCheckOutNote).text =
            t.schedule?.endTime?.let { "Batas ${Format.clock(it)}" } ?: "-"
        view.findViewById<TextView>(R.id.tvDistance).text = Format.meters(a?.checkOutDistanceM ?: a?.checkInDistanceM)
        view.findViewById<TextView>(R.id.tvDistanceNote).text = if (a?.checkInAt != null) "Di dalam area" else "Belum ada data"
        view.findViewById<TextView>(R.id.tvActivityTitle).text = when {
            a?.checkOutAt != null -> "Check-out ${Format.time(a.checkOutAt)}"
            a?.checkInAt != null -> "Check-in ${Format.time(a.checkInAt)}"
            else -> getString(R.string.today_none)
        }
        view.findViewById<TextView>(R.id.tvActivityDate).text = Format.day(t.date)
        // Swipe only makes sense after check-in and before check-out.
        view.findViewById<View>(R.id.swipeCheckOut).visibility =
            if (t.hasCheckedIn && !t.hasCheckedOut) View.VISIBLE else View.GONE
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
        ).filter { it.target.isShown }) // the check-out slider only appears after check-in
    }
}

/** Riwayat: attendance and field duty of one month, filtered by the tabs. */
class HistoryFragment : BaseFragment(R.layout.fragment_history) {
    private var month = LocalDate.now()
    private var tab = R.id.tabAll
    private var historyList: ViewGroup? = null

    override fun onReady(view: View) {
        bindBottomNav(view, R.id.historyFragment)
        singleChoice(view, R.id.tabAll, R.id.tabRoutine, R.id.tabField) { tab = it; load(view) }
        view.onClick(R.id.btnFilter) { pickMonth(view) }
        load(view)
    }

    private fun pickMonth(view: View) {
        val months = (0 until 6).map { LocalDate.now().minusMonths(it.toLong()) }
        AlertDialog.Builder(requireContext())
            .setItems(months.map { Format.monthTitle(it) }.toTypedArray()) { _, i -> month = months[i]; load(view) }
            .show()
    }

    private fun load(view: View) = launch {
        val list = view.findViewById<View>(R.id.itemToday)?.parent as? ViewGroup ?: historyList ?: return@launch
        historyList = list
        list.clearAfter(list.childContaining(view.findViewById(R.id.tabs)))
        val loading = list.addMessage(getString(R.string.msg_loading))
        val key = Format.month(month)
        var offline = false
        val attendance = if (tab == R.id.tabField) emptyList() else when (val r = call { history(key) }) {
            is ApiResult.Ok -> r.value.data
            is ApiResult.Failed -> emptyList()
            ApiResult.Offline -> { offline = true; emptyList() }
        }
        val duties = if (tab == R.id.tabRoutine) emptyList() else when (val r = call { fieldDuties() }) {
            is ApiResult.Ok -> r.value.data
            is ApiResult.Failed -> emptyList()
            ApiResult.Offline -> { offline = true; emptyList() }
        }
        list.removeView(loading)
        list.addMessage(Format.monthTitle(month)).apply { textAlignment = View.TEXT_ALIGNMENT_VIEW_START; setPadding(0, 24, 0, 0) }

        if (offline) {
            list.addMessage(getString(R.string.msg_offline_list)); return@launch
        }
        var count = 0
        attendance.forEach { a ->
            count++
            val chip = if (a.status == "terlambat") "Terlambat" else Format.attendanceStatus(a.status)
            list.addRow(R.drawable.ic_clock, Format.day(a.date),
                "Masuk ${Format.time(a.checkInAt)} · Pulang ${Format.time(a.checkOutAt)}",
                chip, toneForStatus(a.status)) { showAttendance(a) }
        }
        duties.filter { it.date.startsWith(key) }.forEach { d ->
            count++
            list.addRow(R.drawable.ic_location, "Tugas lapangan · ${Format.day(d.date)}", d.locationName,
                Format.submissionStatus(d.status), toneForStatus(d.status))
        }
        if (count == 0) list.addMessage(getString(R.string.msg_empty))
    }

    private fun showAttendance(a: Attendance) = alert(
        "Status: ${Format.attendanceStatus(a.status)}" +
            (if (a.lateMinutes > 0) " (${a.lateMinutes} menit)" else "") +
            "\nCheck-in: ${Format.time(a.checkInAt)} · ${Format.meters(a.checkInDistanceM)}" +
            "\nCheck-out: ${Format.time(a.checkOutAt)} · ${Format.meters(a.checkOutDistanceM)}" +
            "\nSumber: ${if (a.source == "offline") "Offline (dikirim belakangan)" else "Online"}",
        Format.day(a.date),
    )
}

class AttendanceDetailFragment : BaseFragment(R.layout.fragment_attendance_detail) {
    override fun onReady(view: View) = Unit
}

/** Jadwal kerja bulan ini, one row per day. */
class ScheduleFragment : BaseFragment(R.layout.fragment_schedule) {
    override fun onReady(view: View) {
        bindBottomNav(view, R.id.scheduleFragment)
        val month = LocalDate.now()
        view.findViewById<TextView>(R.id.tvMonth).text = Format.monthTitle(month)
        val list = view.findViewById<ViewGroup>(R.id.listContent)
        list.clearAfter(null)
        val loading = list.addMessage(getString(R.string.msg_loading))
        launch {
            val r = call { schedules(Format.month(month)) }
            list.removeView(loading)
            when (r) {
                is ApiResult.Ok -> {
                    if (r.value.data.isEmpty()) list.addMessage(getString(R.string.msg_empty))
                    r.value.data.forEach { s ->
                        val isToday = s.date == LocalDate.now().toString()
                        when {
                            s.isDayOff -> list.addRow(R.drawable.ic_calendar, Format.day(s.date), "Libur", "Libur", Tone.NEUTRAL)
                            else -> list.addRow(R.drawable.ic_calendar, Format.day(s.date),
                                "${s.shift?.name ?: "Shift"} · ${Format.clock(s.shift?.startTime)}–${Format.clock(s.shift?.endTime)}",
                                if (isToday) "Hari ini" else null, Tone.PRIMARY)
                        }
                    }
                }
                is ApiResult.Failed -> list.addMessage(r.message)
                ApiResult.Offline -> list.addMessage(getString(R.string.msg_offline_list))
            }
        }
    }
}

/** Koreksi absensi hari ini: the wrong part is the check-in if there is none yet, otherwise the check-out. */
class CorrectionFragment : BaseFragment(R.layout.fragment_correction) {
    private var reason = "GPS tidak akurat"
    private var fixCheckIn = true

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
        view.onClick(R.id.btnSubmit) {
            if (time.text.isBlank() || note.text.isBlank()) return@onClick toast(R.string.msg_fill_form)
            val body = mutableMapOf("date" to LocalDate.now().toString(), "reason" to "$reason: ${note.text.trim()}")
            body[if (fixCheckIn) "corrected_check_in" else "corrected_check_out"] = time.text.toString()
            launch {
                when (val r = call { createCorrection(body) }) {
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
