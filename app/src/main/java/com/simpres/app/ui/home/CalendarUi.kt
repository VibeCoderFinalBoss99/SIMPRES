package com.simpres.app.ui.home

import android.content.Context
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import androidx.annotation.ColorRes
import androidx.annotation.StyleRes
import androidx.core.content.ContextCompat
import androidx.core.widget.TextViewCompat
import com.simpres.app.R
import com.simpres.app.ui.Tone
import java.time.DayOfWeek
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.time.format.TextStyle
import java.util.Locale

private val ID = Locale("id", "ID")
private val MONTH_TITLE = DateTimeFormatter.ofPattern("MMMM yyyy", ID)

/** Warna satu tanggal di kalender dan lencana tanggal di daftar. */
data class Mark(val label: String, @ColorRes val fill: Int, @ColorRes val text: Int)

object Marks {
    val HADIR = Mark("Hadir", R.color.success_tint, R.color.success_text)
    val TERLAMBAT = Mark("Terlambat", R.color.warn_tint, R.color.warn_text)
    val CUTI = Mark("Cuti / izin", R.color.primary_tint, R.color.primary_on_tint)
    val LAPANGAN = Mark("Tugas lapangan", R.color.teal_tint, R.color.teal_text)
    val ALPA = Mark("Alpa", R.color.danger_tint, R.color.danger_text)
    val LIBUR = Mark("Libur", R.color.surface, R.color.danger)
    val MENUNGGU = Mark("Izin menunggu", R.color.border, R.color.ink_muted)

    // Jadwal: warna per shift.
    val PAGI = Mark("Pagi", R.color.success_tint, R.color.success_text)
    val SIANG = Mark("Siang", R.color.warn_tint, R.color.warn_text)
    val MALAM = Mark("Malam", R.color.primary, R.color.white)
    val SHIFT_LAIN = Mark("Shift lain", R.color.teal_tint, R.color.teal_text)

    fun forShift(name: String?): Mark = when (name?.lowercase()) {
        "pagi" -> PAGI
        "siang" -> SIANG
        "malam" -> MALAM
        else -> SHIFT_LAIN.copy(label = name ?: "Shift")
    }
}

fun Context.dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()

fun Context.text(@StyleRes style: Int, value: CharSequence = "", @ColorRes color: Int? = null): TextView =
    TextView(this).apply {
        TextViewCompat.setTextAppearance(this, style)
        text = value
        color?.let { setTextColor(ContextCompat.getColor(context, it)) }
    }

private fun Context.rounded(@ColorRes fill: Int, radiusDp: Int, @ColorRes stroke: Int? = null) = GradientDrawable().apply {
    setColor(ContextCompat.getColor(this@rounded, fill))
    cornerRadius = dp(radiusDp).toFloat()
    stroke?.let { setStroke(dp(2), ContextCompat.getColor(this@rounded, it)) }
}

/**
 * Kalender satu bulan (Senin sampai Minggu). Setiap tanggal diberi warna dari [render]. Minggu dan libur bertulisan
 * merah, hari ini diberi lingkaran. [onMonth] dipanggil saat tombol bulan ditekan, [onDay] saat tanggal diketuk.
 */
class MonthCalendar(
    private val root: View,
    private val onMonth: (LocalDate) -> Unit,
    private val onDay: (LocalDate) -> Unit,
) {
    var month: LocalDate = LocalDate.now().withDayOfMonth(1)
        private set
    private val ctx = root.context

    init {
        root.findViewById<View>(R.id.btnPrevMonth).setOnClickListener { onMonth(month.minusMonths(1)) }
        root.findViewById<View>(R.id.btnNextMonth).setOnClickListener { onMonth(month.plusMonths(1)) }
    }

    fun render(month: LocalDate, marks: Map<LocalDate, Mark>, legend: List<Mark>, summary: List<Pair<String, Int>>) {
        this.month = month.withDayOfMonth(1)
        root.findViewById<TextView>(R.id.tvCalendarMonth).text =
            this.month.format(MONTH_TITLE).replaceFirstChar { it.uppercase() }
        drawGrid(marks)
        drawLegend(legend)
        drawSummary(summary)
    }

    private fun drawGrid(marks: Map<LocalDate, Mark>) {
        val grid = root.findViewById<LinearLayout>(R.id.calendarGrid)
        grid.removeAllViews()
        grid.addView(row().apply {
            DayOfWeek.entries.forEach { d ->
                addView(ctx.text(R.style.T_Small, d.getDisplayName(TextStyle.SHORT, ID),
                    if (d == DayOfWeek.SUNDAY) R.color.danger else R.color.ink_muted).apply {
                    gravity = Gravity.CENTER
                    layoutParams = LinearLayout.LayoutParams(0, ctx.dp(24), 1f)
                })
            }
        })
        val today = LocalDate.now()
        var day = month.minusDays((month.dayOfWeek.value - 1).toLong())
        while (day.isBefore(month.plusMonths(1)) || day.dayOfWeek != DayOfWeek.MONDAY) {
            if (day.dayOfWeek == DayOfWeek.MONDAY) grid.addView(row())
            val line = grid.getChildAt(grid.childCount - 1) as LinearLayout
            line.addView(cell(day, marks[day], day == today))
            day = day.plusDays(1)
        }
    }

    private fun row() = LinearLayout(ctx).apply { orientation = LinearLayout.HORIZONTAL }

    private fun cell(day: LocalDate, mark: Mark?, isToday: Boolean): View {
        val inMonth = day.month == month.month
        val box = LinearLayout(ctx).apply {
            gravity = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(0, ctx.dp(42), 1f)
        }
        if (!inMonth) return box
        val red = day.dayOfWeek == DayOfWeek.SUNDAY || mark == Marks.LIBUR
        val number = ctx.text(R.style.T_Label, day.dayOfMonth.toString(),
            when { mark != null && mark != Marks.LIBUR -> mark.text; red -> R.color.danger; else -> R.color.ink }).apply {
            gravity = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(ctx.dp(36), ctx.dp(36))
            if (mark != null && mark != Marks.LIBUR || isToday) {
                background = ctx.rounded(if (mark != null && mark != Marks.LIBUR) mark.fill else R.color.surface, 18,
                    if (isToday) R.color.primary else null)
            }
            if (isToday) setTypeface(typeface, Typeface.BOLD)
            contentDescription = listOfNotNull(day.toString(), mark?.label).joinToString(", ")
        }
        box.addView(number)
        box.setOnClickListener { onDay(day) }
        return box
    }

    private fun drawLegend(legend: List<Mark>) {
        val host = root.findViewById<LinearLayout>(R.id.calendarLegend)
        host.removeAllViews()
        legend.chunked(3).forEach { chunk ->
            host.addView(row().apply {
                setPadding(0, ctx.dp(3), 0, ctx.dp(3))
                chunk.forEach { m ->
                    addView(row().apply {
                        gravity = Gravity.CENTER_VERTICAL
                        layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f)
                        addView(View(ctx).apply {
                            background = ctx.rounded(if (m == Marks.LIBUR) R.color.danger_tint else m.fill, 5,
                                if (m == Marks.LIBUR) R.color.danger else null)
                            layoutParams = LinearLayout.LayoutParams(ctx.dp(12), ctx.dp(12)).apply { marginEnd = ctx.dp(6) }
                        })
                        addView(ctx.text(R.style.T_Small, m.label, R.color.ink_muted))
                    })
                }
            })
        }
    }

    private fun drawSummary(summary: List<Pair<String, Int>>) {
        val host = root.findViewById<LinearLayout>(R.id.calendarSummary)
        host.removeAllViews()
        host.visibility = if (summary.isEmpty()) View.GONE else View.VISIBLE
        summary.forEachIndexed { i, (label, count) ->
            host.addView(LinearLayout(ctx).apply {
                orientation = LinearLayout.VERTICAL
                gravity = Gravity.CENTER
                background = ctx.rounded(R.color.bg, 12)
                setPadding(0, ctx.dp(8), 0, ctx.dp(8))
                layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f).apply {
                    if (i > 0) marginStart = ctx.dp(6)
                }
                addView(ctx.text(R.style.T_Title, count.toString()).apply { gravity = Gravity.CENTER })
                addView(ctx.text(R.style.T_Small, label, R.color.ink_muted).apply { gravity = Gravity.CENTER })
            })
        }
    }
}

/** Satu baris info di kartu hari: label kecil di atas, nilai tebal di bawah. */
data class Info(val label: String, val value: String)

/**
 * Kartu satu hari: lencana tanggal besar di kiri (warna dari [mark]), di kanan judul, chip status, info
 * (masuk/pulang/jam kerja atau jam shift), dan catatan.
 */
fun ViewGroup.addDayCard(
    date: LocalDate,
    mark: Mark?,
    title: String,
    chips: List<Pair<String, Tone>>,
    infos: List<Info>,
    note: String? = null,
    onClick: (() -> Unit)? = null,
): View {
    val ctx = context
    val red = date.dayOfWeek == DayOfWeek.SUNDAY || mark == Marks.LIBUR
    val card = LinearLayout(ctx).apply {
        orientation = LinearLayout.HORIZONTAL
        setBackgroundResource(R.drawable.bg_card)
        setPadding(ctx.dp(12), ctx.dp(12), ctx.dp(14), ctx.dp(12))
        layoutParams = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT)
            .apply { topMargin = ctx.dp(10) }
    }
    val badgeText = when { mark != null && mark != Marks.LIBUR -> mark.text; red -> R.color.danger; else -> R.color.ink }
    card.addView(LinearLayout(ctx).apply {
        orientation = LinearLayout.VERTICAL
        gravity = Gravity.CENTER
        background = ctx.rounded(if (mark == null || mark == Marks.LIBUR) (if (red) R.color.danger_tint else R.color.bg) else mark.fill, 14)
        // Lencana tanggal besar: hari, tanggal (angka besar), bulan.
        layoutParams = LinearLayout.LayoutParams(ctx.dp(84), ViewGroup.LayoutParams.WRAP_CONTENT).apply {
            marginEnd = ctx.dp(14)
            gravity = Gravity.CENTER_VERTICAL
        }
        setPadding(0, ctx.dp(10), 0, ctx.dp(10))
        minimumHeight = ctx.dp(96)
        addView(ctx.text(R.style.T_Label, date.dayOfWeek.getDisplayName(TextStyle.SHORT, ID).uppercase(), badgeText).apply {
            gravity = Gravity.CENTER; letterSpacing = 0.1f
        })
        addView(ctx.text(R.style.T_Display, date.dayOfMonth.toString(), badgeText).apply {
            gravity = Gravity.CENTER; textSize = 34f; includeFontPadding = false
        })
        addView(ctx.text(R.style.T_Label, date.month.getDisplayName(TextStyle.SHORT, ID), badgeText).apply { gravity = Gravity.CENTER })
    })
    val right = LinearLayout(ctx).apply {
        orientation = LinearLayout.VERTICAL
        layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f).apply { gravity = Gravity.CENTER_VERTICAL }
    }
    right.addView(ctx.text(R.style.T_Item, title))
    if (chips.isNotEmpty()) {
        // Paling banyak 2 chip per baris supaya tidak terpotong di layar kecil.
        chips.chunked(2).forEach { line ->
            right.addView(LinearLayout(ctx).apply {
                setPadding(0, ctx.dp(6), 0, 0)
                line.forEach { (label, tone) ->
                    addView(ctx.text(R.style.T_Chip, label, tone.text).apply {
                        setBackgroundResource(tone.bg)
                        layoutParams = LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT)
                            .apply { marginEnd = ctx.dp(6) }
                    })
                }
            })
        }
    }
    if (infos.isNotEmpty()) {
        right.addView(LinearLayout(ctx).apply {
            setPadding(0, ctx.dp(8), 0, 0)
            infos.forEach { info ->
                addView(LinearLayout(ctx).apply {
                    orientation = LinearLayout.VERTICAL
                    layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f)
                    addView(ctx.text(R.style.T_Small, info.label, R.color.ink_muted))
                    addView(ctx.text(R.style.T_Label, info.value))
                })
            }
        })
    }
    note?.let { right.addView(ctx.text(R.style.T_Small, it, R.color.ink_muted).apply { setPadding(0, ctx.dp(6), 0, 0) }) }
    card.addView(right)
    if (onClick != null) {
        card.addView(ImageView(ctx).apply {
            setImageResource(R.drawable.ic_chevron_right)
            setColorFilter(ContextCompat.getColor(ctx, R.color.ink_faint))
            layoutParams = LinearLayout.LayoutParams(ctx.dp(18), ctx.dp(18)).apply { gravity = Gravity.CENTER_VERTICAL }
        })
        card.setOnClickListener { onClick() }
    }
    addView(card)
    return card
}

/**
 * Detail satu hari dalam jendela yang bergaya sama dengan kartu Riwayat: kartu hari (lencana tanggal, judul, label
 * status, masuk/pulang/jam kerja) lalu baris keterangan tambahan sebagai label kecil.
 */
fun Context.showDayDetail(
    date: LocalDate,
    mark: Mark?,
    title: String,
    chips: List<Pair<String, Tone>>,
    infos: List<Info>,
    details: List<Pair<String, String>>,
) {
    val ctx = this
    val box = LinearLayout(ctx).apply {
        orientation = LinearLayout.VERTICAL
        background = ctx.rounded(R.color.bg, 22)
        setPadding(ctx.dp(14), ctx.dp(6), ctx.dp(14), ctx.dp(8))
    }
    box.addDayCard(date, mark, title, chips, infos)
    details.forEach { (label, value) ->
        box.addView(LinearLayout(ctx).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            background = ctx.rounded(R.color.surface, 14)
            setPadding(ctx.dp(14), ctx.dp(12), ctx.dp(14), ctx.dp(12))
            layoutParams = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT)
                .apply { topMargin = ctx.dp(8) }
            addView(ctx.text(R.style.T_Small, label, R.color.ink_muted).apply {
                layoutParams = LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f)
            })
            addView(ctx.text(R.style.T_Label, value).apply { gravity = Gravity.END })
        })
    }
    val close = ctx.text(R.style.T_Link, getString(R.string.common_tutup), R.color.primary).apply {
        gravity = Gravity.CENTER
        setPadding(0, ctx.dp(14), 0, ctx.dp(8))
    }
    box.addView(close)
    val dialog = androidx.appcompat.app.AlertDialog.Builder(ctx).setView(box).create()
    dialog.window?.setBackgroundDrawable(android.graphics.drawable.ColorDrawable(android.graphics.Color.TRANSPARENT))
    close.setOnClickListener { dialog.dismiss() }
    dialog.show()
}

/** "6 j 37 m" dari menit. */
fun durationText(minutes: Int?): String = when {
    minutes == null -> "-"
    minutes < 60 -> "$minutes m"
    else -> "${minutes / 60} j ${minutes % 60} m"
}
