package com.simpres.app.ui

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.annotation.DrawableRes
import androidx.core.content.ContextCompat
import com.simpres.app.R

/** Colour of a status chip. */
enum class Tone(@DrawableRes val bg: Int, val text: Int) {
    SUCCESS(R.drawable.bg_chip_success, R.color.success),
    WARN(R.drawable.bg_chip_warn, R.color.warn),
    DANGER(R.drawable.bg_chip_danger, R.color.danger),
    PRIMARY(R.drawable.bg_chip_primary, R.color.primary_on_tint),
    NEUTRAL(R.drawable.bg_chip_neutral, R.color.ink_muted),
}

fun toneForStatus(status: String): Tone = when (status) {
    "hadir", "diterima" -> Tone.SUCCESS
    "terlambat", "menunggu" -> Tone.WARN
    "alpa", "ditolak" -> Tone.DANGER
    "izin" -> Tone.PRIMARY
    else -> Tone.NEUTRAL
}

/** Adds one card row (icon, title, subtitle, optional chip) to a list container. */
fun ViewGroup.addRow(
    @DrawableRes icon: Int,
    title: String,
    subtitle: String,
    chip: String? = null,
    tone: Tone = Tone.NEUTRAL,
    onClick: (() -> Unit)? = null,
): View {
    val row = LayoutInflater.from(context).inflate(R.layout.item_row, this, false)
    row.findViewById<ImageView>(R.id.rowIcon).setImageResource(icon)
    row.findViewById<TextView>(R.id.rowTitle).text = title
    row.findViewById<TextView>(R.id.rowSubtitle).text = subtitle
    row.findViewById<TextView>(R.id.rowChip).apply {
        if (chip == null) {
            visibility = View.GONE
        } else {
            text = chip
            setBackgroundResource(tone.bg)
            setTextColor(ContextCompat.getColor(context, tone.text))
        }
    }
    if (onClick != null) row.setOnClickListener { onClick() } else row.isClickable = false
    addView(row)
    return row
}

/** Grey centred message for an empty list, loading or an error. */
fun ViewGroup.addMessage(text: String): TextView {
    val tv = LayoutInflater.from(context).inflate(R.layout.item_message, this, false) as TextView
    tv.text = text
    addView(tv)
    return tv
}

/** The direct child of this group that contains [descendant] (e.g. the scroll row holding the tabs). */
fun ViewGroup.childContaining(descendant: View): View {
    var v = descendant
    while (v.parent !== this) v = v.parent as View
    return v
}

/** Removes every child after [keepUntil] (tabs, headers) so a list can be rebuilt. */
fun ViewGroup.clearAfter(keepUntil: View?) {
    val start = if (keepUntil == null) 0 else indexOfChild(keepUntil) + 1
    while (childCount > start) removeViewAt(start)
}
