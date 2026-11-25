package com.simpres.app.ui.home

import android.view.View
import android.widget.LinearLayout
import android.widget.ScrollView
import androidx.appcompat.app.AlertDialog
import com.simpres.app.R
import com.simpres.app.data.ApiResult
import com.simpres.app.data.Attendance
import com.simpres.app.data.FieldDutyItem
import com.simpres.app.data.Format
import com.simpres.app.data.Leave
import com.simpres.app.data.Schedule
import com.simpres.app.data.call
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.Tone
import com.simpres.app.ui.addMessage
import com.simpres.app.ui.onClick
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import java.time.DayOfWeek
import java.time.LocalDate

/** Data satu bulan untuk Riwayat dan Jadwal, diambil sekaligus. */
internal class MonthData(
    val attendances: Map<LocalDate, Attendance>,
    val schedules: Map<LocalDate, Schedule>,
    val leaves: List<Leave>,
    val duties: List<FieldDutyItem>,
    val offline: Boolean,
) {
    /** Izin/cuti yang mencakup tanggal ini (diterima lebih dulu, lalu menunggu). Yang ditolak diabaikan. */
    fun leaveOn(date: LocalDate): Leave? = leaves
        .filter { it.status != "ditolak" && !date.isBefore(LocalDate.parse(it.startDate)) && !date.isAfter(LocalDate.parse(it.endDate)) }
        .filter { it.type != "izin_keluar" || it.startDate == date.toString() }
        .minByOrNull { if (it.status == "diterima") 0 else 1 }

    fun dutyOn(date: LocalDate): FieldDutyItem? = duties.filter { it.date == date.toString() && it.status != "ditolak" }
        .minByOrNull { if (it.status == "diterima") 0 else 1 }
}

internal suspend fun loadMonth(month: LocalDate, withAttendance: Boolean): MonthData = coroutineScope {
    val key = Format.month(month)
    val att = async { if (withAttendance) call { history(key) } else null }
    val sch = async { call { schedules(key) } }
    val lv = async { call { leaves(null) } }
    val fd = async { if (withAttendance) call { fieldDuties() } else null }
    val results = listOf(att.await(), sch.await(), lv.await(), fd.await())
    @Suppress("UNCHECKED_CAST")
    fun <T> list(r: ApiResult<*>?): List<T> = ((r as? ApiResult.Ok<*>)?.value as? com.simpres.app.data.ListResponse<T>)?.data ?: emptyList()
    MonthData(
        attendances = list<Attendance>(results[0]).associateBy { LocalDate.parse(it.date) },
        schedules = list<Schedule>(results[1]).associateBy { LocalDate.parse(it.date) },
        leaves = list(results[2]),
        duties = list(results[3]),
        offline = results.any { it == ApiResult.Offline },
    )
}

/** Warna tanggal Riwayat (dipakai juga rekap Beranda): absensi lebih dulu, lalu tugas lapangan, cuti, libur, alpa. */
internal fun historyMark(d: LocalDate, data: MonthData, today: LocalDate): Mark? {
    val a = data.attendances[d]
    val s = data.schedules[d]
    val leave = data.leaveOn(d)
    return when {
        a?.status == "terlambat" -> Marks.TERLAMBAT
        a?.status == "hadir" -> Marks.HADIR
        a?.status == "izin" -> Marks.CUTI
        a?.status == "alpa" -> Marks.ALPA
        data.dutyOn(d)?.status == "diterima" -> Marks.LAPANGAN
        leave?.status == "diterima" && leave.type != "izin_keluar" -> Marks.CUTI
        s?.holiday != null || s?.isDayOff == true || (s == null && d.dayOfWeek == DayOfWeek.SUNDAY) -> Marks.LIBUR
        s != null && d.isBefore(today) -> Marks.ALPA
        leave?.status == "menunggu" -> Marks.MENUNGGU
        else -> null
    }
}

private fun leaveName(l: Leave) = Format.leaveType(l.type)

private fun shiftText(s: Schedule?): String? =
    s?.shift?.let { "${it.name} · ${Format.clock(it.startTime)}–${Format.clock(it.endTime)}" }

/**
 * Riwayat absensi: kalender bulan (hadir hijau, terlambat kuning, cuti/izin biru, tugas lapangan hijau tosca,
 * alpa merah, Minggu/libur bertulisan merah), ringkasan, lalu kartu per hari dengan lencana tanggal besar.
 */
class HistoryFragment : BaseFragment(R.layout.fragment_history) {
    private var tab = R.id.tabAll
    private var month = LocalDate.now().withDayOfMonth(1)
    private lateinit var calendar: MonthCalendar
    private val cards = mutableMapOf<LocalDate, View>()

    override fun onReady(view: View) {
        bindBottomNav(view, R.id.historyFragment)
        calendar = MonthCalendar(view.findViewById(R.id.calendarHost), { month = it; load(view) }, { scrollTo(view, it) })
        singleChoice(view, R.id.tabAll, R.id.tabRoutine, R.id.tabField) { tab = it; load(view) }
        view.onClick(R.id.btnFilter) { pickMonth(view) }
        load(view)
    }

    private fun pickMonth(view: View) {
        val months = (0 until 12).map { LocalDate.now().withDayOfMonth(1).minusMonths(it.toLong()) }
        AlertDialog.Builder(requireContext())
            .setItems(months.map { Format.monthTitle(it) }.toTypedArray()) { _, i -> month = months[i]; load(view) }
            .show()
    }

    private fun scrollTo(view: View, date: LocalDate) {
        val card = cards[date] ?: return toast(getString(R.string.calendar_no_record, Format.day(date.toString())))
        view.findViewById<ScrollView>(R.id.scroll).smoothScrollTo(0, (card.parent as View).top + card.top - 24)
        card.animate().scaleX(1.03f).scaleY(1.03f).setDuration(140).withEndAction {
            card.animate().scaleX(1f).scaleY(1f).setDuration(140).start()
        }.start()
    }

    private fun load(view: View) = launch {
        val list = view.findViewById<LinearLayout>(R.id.dayList)
        list.removeAllViews()
        cards.clear()
        val loading = list.addMessage(getString(R.string.msg_loading))
        val data = loadMonth(month, withAttendance = true)
        list.removeView(loading)

        val today = LocalDate.now()
        val marks = mutableMapOf<LocalDate, Mark>()
        val days = generateSequence(month) { it.plusDays(1) }.takeWhile { it.month == month.month }.toList()
        days.forEach { d -> historyMark(d, data, today)?.let { marks[d] = it } }

        val counted = marks.values
        calendar.render(month, marks,
            listOf(Marks.HADIR, Marks.TERLAMBAT, Marks.CUTI, Marks.LAPANGAN, Marks.ALPA, Marks.LIBUR),
            listOf(
                "Hadir" to counted.count { it == Marks.HADIR || it == Marks.TERLAMBAT || it == Marks.LAPANGAN },
                "Terlambat" to counted.count { it == Marks.TERLAMBAT },
                "Cuti/izin" to counted.count { it == Marks.CUTI },
                "Alpa" to counted.count { it == Marks.ALPA },
            ))

        if (data.offline) {
            list.addMessage(getString(R.string.msg_offline_list)); return@launch
        }
        days.sortedDescending().forEach { d -> addCard(list, d, marks[d], data, today) }
        if (list.childCount == 0) list.addMessage(getString(R.string.msg_empty))
    }

    private fun addCard(list: LinearLayout, d: LocalDate, mark: Mark?, data: MonthData, today: LocalDate) {
        val a = data.attendances[d]
        val s = data.schedules[d]
        val duty = data.dutyOn(d)
        val leave = data.leaveOn(d)
        val routine = a != null || leave != null || mark == Marks.ALPA || s?.holiday != null
        if (tab == R.id.tabField && duty == null) return
        if (tab == R.id.tabRoutine && !routine) return
        if (!routine && duty == null && mark != Marks.LIBUR) return
        if (mark == Marks.LIBUR && s?.holiday == null && a == null && duty == null && leave == null) return

        val chips = mutableListOf<Pair<String, Tone>>()
        var title: String
        var infos = emptyList<Info>()
        var note: String? = shiftText(s)?.let { "Jadwal: $it" }
        when {
            a != null && a.status in listOf("hadir", "terlambat") -> {
                title = if (a.status == "terlambat") "Hadir, terlambat" else "Hadir"
                chips += if (a.status == "terlambat") "Terlambat ${a.lateMinutes} mnt" to Tone.WARN else "Tepat waktu" to Tone.SUCCESS
                if (a.earlyLeaveMinutes > 0) chips += "Pulang cepat ${a.earlyLeaveMinutes} mnt" to Tone.WARN
                if (a.checkOutAt == null && d.isBefore(today)) chips += "Tidak check-out" to Tone.DANGER
                if (a.source == "offline") chips += "Dikirim offline" to Tone.NEUTRAL
                infos = listOf(Info("Masuk", Format.time(a.checkInAt)), Info("Pulang", Format.time(a.checkOutAt)),
                    Info("Jam kerja", durationText(a.workMinutes)))
            }
            a?.status == "izin" || (leave?.status == "diterima" && leave.type != "izin_keluar") -> {
                title = leave?.let { leaveName(it) } ?: "Izin"
                chips += "Disetujui" to Tone.PRIMARY
                note = leave?.reason
            }
            mark == Marks.ALPA -> {
                title = "Tidak hadir"
                chips += "Alpa" to Tone.DANGER
            }
            mark == Marks.LIBUR -> {
                title = s?.holiday ?: "Libur"
                chips += "Libur nasional" to Tone.DANGER
                note = null
            }
            else -> title = duty?.let { "Tugas lapangan" } ?: leave?.let { leaveName(it) } ?: "-"
        }
        if (leave != null && leave.status == "menunggu") chips += "${leaveName(leave)} menunggu" to Tone.WARN
        if (leave != null && leave.type == "izin_keluar" && leave.status == "diterima") chips += "Izin keluar" to Tone.PRIMARY
        if (duty != null) {
            if (title == "-") title = "Tugas lapangan"
            chips += "Lapangan: ${Format.submissionStatus(duty.status)}" to (if (duty.status == "diterima") Tone.SUCCESS else Tone.WARN)
            note = listOfNotNull(note, "Lokasi: ${duty.locationName}").joinToString("\n")
        }
        if (d == today) chips.add(0, "Hari ini" to Tone.PRIMARY)

        cards[d] = list.addDayCard(d, mark, title, chips, infos, note, a?.let { { showAttendance(it, s) } })
    }

    private fun showAttendance(a: Attendance, s: Schedule?) = alert(
        "Status: ${Format.attendanceStatus(a.status)}" +
            (if (a.lateMinutes > 0) " (${a.lateMinutes} menit)" else "") +
            (shiftText(s)?.let { "\nJadwal: $it" } ?: "") +
            "\nCheck-in: ${Format.time(a.checkInAt)} · ${Format.meters(a.checkInDistanceM)}" +
            "\nCheck-out: ${Format.time(a.checkOutAt)} · ${Format.meters(a.checkOutDistanceM)}" +
            (if (a.earlyLeaveMinutes > 0) "\nPulang lebih cepat ${a.earlyLeaveMinutes} menit" else "") +
            "\nJam kerja: ${durationText(a.workMinutes)}" +
            "\nSumber: ${if (a.source == "offline") "Offline (dikirim belakangan)" else "Online"}",
        Format.day(a.date),
    )
}

/**
 * Jadwal kerja: kalender bulan berwarna per shift (Pagi hijau, Siang kuning, Malam biru tua), cuti/izin biru muda,
 * libur bertulisan merah, lalu kartu setiap tanggal.
 */
class ScheduleFragment : BaseFragment(R.layout.fragment_schedule) {
    private var month = LocalDate.now().withDayOfMonth(1)
    private lateinit var calendar: MonthCalendar
    private val cards = mutableMapOf<LocalDate, View>()

    override fun onReady(view: View) {
        bindBottomNav(view, R.id.scheduleFragment)
        calendar = MonthCalendar(view.findViewById(R.id.calendarHost), { month = it; load(view) }, { scrollTo(view, it) })
        load(view)
    }

    private fun scrollTo(view: View, date: LocalDate) {
        val card = cards[date] ?: return
        view.findViewById<ScrollView>(R.id.scroll).smoothScrollTo(0, (card.parent as View).top + card.top - 24)
    }

    private fun load(view: View) = launch {
        val list = view.findViewById<LinearLayout>(R.id.dayList)
        list.removeAllViews()
        cards.clear()
        val loading = list.addMessage(getString(R.string.msg_loading))
        val data = loadMonth(month, withAttendance = false)
        list.removeView(loading)

        val today = LocalDate.now()
        val days = generateSequence(month) { it.plusDays(1) }.takeWhile { it.month == month.month }.toList()
        val marks = days.mapNotNull { d -> markFor(d, data)?.let { d to it } }.toMap()
        val shiftNames = data.schedules.values.mapNotNull { it.shift?.name }.distinct()
        calendar.render(month, marks,
            shiftNames.map { Marks.forShift(it) } + listOf(Marks.CUTI, Marks.LIBUR),
            listOf(
                "Hari kerja" to marks.values.count { it != Marks.LIBUR && it != Marks.CUTI && it != Marks.MENUNGGU },
                "Libur" to marks.values.count { it == Marks.LIBUR },
                "Cuti/izin" to marks.values.count { it == Marks.CUTI },
            ))

        if (data.offline) {
            list.addMessage(getString(R.string.msg_offline_list)); return@launch
        }
        if (data.schedules.isEmpty()) list.addMessage(getString(R.string.schedule_empty))
        days.forEach { d ->
            val s = data.schedules[d]
            val leave = data.leaveOn(d)
            if (s == null && leave == null && d.dayOfWeek != DayOfWeek.SUNDAY) return@forEach
            val chips = mutableListOf<Pair<String, Tone>>()
            if (d == today) chips += "Hari ini" to Tone.PRIMARY
            val title = when {
                leave?.status == "diterima" && leave.type != "izin_keluar" -> leaveName(leave).also { chips += "Disetujui" to Tone.PRIMARY }
                s?.holiday != null -> s.holiday.also { chips += "Libur nasional" to Tone.DANGER }
                s?.isDayOff == true || s == null -> "Libur".also { chips += "Libur" to Tone.DANGER }
                else -> "Shift ${s.shift?.name ?: ""}".trim()
            }
            if (leave?.status == "menunggu") chips += "${leaveName(leave)} menunggu" to Tone.WARN
            if (leave?.type == "izin_keluar" && leave.status == "diterima") chips += "Izin keluar" to Tone.PRIMARY
            val infos = s?.shift?.takeIf { s.isDayOff.not() }?.let {
                listOf(Info("Masuk", Format.clock(it.startTime)), Info("Pulang", Format.clock(it.endTime)),
                    Info("Sumber", if (s.isDefault) "Shift tetap" else "Roster"))
            } ?: emptyList()
            cards[d] = list.addDayCard(d, marks[d], title, chips, infos, leave?.reason?.takeIf { leave.status != "ditolak" })
        }
        // Bulan ini: langsung gulir ke hari ini.
        if (month.month == today.month && month.year == today.year) view.post { scrollTo(view, today) }
    }

    private fun markFor(d: LocalDate, data: MonthData): Mark? {
        val s = data.schedules[d]
        val leave = data.leaveOn(d)
        return when {
            leave?.status == "diterima" && leave.type != "izin_keluar" -> Marks.CUTI
            s?.holiday != null || s?.isDayOff == true || (s == null && d.dayOfWeek == DayOfWeek.SUNDAY) -> Marks.LIBUR
            s?.shift != null -> Marks.forShift(s.shift.name)
            leave?.status == "menunggu" -> Marks.MENUNGGU
            else -> null
        }
    }
}
