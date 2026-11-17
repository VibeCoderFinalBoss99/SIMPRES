package com.simpres.app.data

import java.time.LocalDate
import java.time.LocalDateTime
import java.time.LocalTime
import java.time.format.DateTimeFormatter
import java.util.Locale
import kotlin.math.roundToInt

/** Turns API values into the Indonesian text shown on screen. */
object Format {
    private val id = Locale("id", "ID")
    private val dateTime = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")
    private val clock = DateTimeFormatter.ofPattern("HH.mm")
    private val dayDate = DateTimeFormatter.ofPattern("EEE, d MMM yyyy", id)
    private val longDate = DateTimeFormatter.ofPattern("EEEE, d MMMM yyyy", id)
    private val monthYear = DateTimeFormatter.ofPattern("MMMM yyyy", id)

    fun time(value: String?): String =
        value?.let { runCatching { LocalDateTime.parse(it, dateTime).format(clock) }.getOrNull() } ?: "--.--"

    /** "07:30:00" -> "07.30" */
    fun clock(value: String?): String =
        value?.let { runCatching { LocalTime.parse(it).format(clock) }.getOrNull() } ?: "--.--"

    fun day(value: String): String = runCatching { LocalDate.parse(value).format(dayDate) }.getOrDefault(value)
    fun longDay(value: LocalDate): String = value.format(longDate)
    fun monthTitle(value: LocalDate): String = value.format(monthYear).replaceFirstChar { it.uppercase() }
    fun month(value: LocalDate): String = value.toString().substring(0, 7)

    fun meters(value: Double?): String = value?.let { "${it.roundToInt()} m" } ?: "-"

    fun nowTime(): String = LocalTime.now().format(clock)

    fun attendanceStatus(status: String): String = when (status) {
        "hadir" -> "Tepat waktu"
        "terlambat" -> "Terlambat"
        "izin" -> "Izin"
        "alpa" -> "Tidak hadir"
        else -> status
    }

    fun leaveType(type: String): String = when (type) {
        "izin_keluar" -> "Izin keluar jam kerja"
        "sakit" -> "Izin sakit"
        "cuti" -> "Cuti"
        else -> "Izin lainnya"
    }

    fun submissionStatus(status: String): String = when (status) {
        "menunggu" -> "Menunggu Konfirmasi"
        "diterima" -> "Diterima"
        "ditolak" -> "Ditolak"
        else -> status
    }

    fun initials(name: String): String =
        name.split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.first().uppercase() }
}
