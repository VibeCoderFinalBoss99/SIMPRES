package com.simpres.app.ui.settings

import android.content.Intent
import android.net.Uri
import android.provider.Settings
import android.view.View
import androidx.navigation.fragment.findNavController
import com.simpres.app.R
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.onClick
import android.view.ViewGroup
import android.widget.TextView
import com.simpres.app.data.Outbox
import com.simpres.app.ui.Tone
import com.simpres.app.ui.addMessage
import com.simpres.app.ui.addRow
import com.simpres.app.ui.clearAfter
import com.simpres.app.ui.ThemeMode
import androidx.appcompat.app.AlertDialog

class ConfigFragment : BaseFragment(R.layout.fragment_config) {
    override fun onReady(view: View) {
        view.onClick(R.id.rowResetPassword) { go(R.id.forgotFragment) }
        view.onClick(R.id.rowChangeInfo) { askChangeInfo() }
        com.simpres.app.data.Session.user?.let { u ->
            view.findViewById<TextView>(R.id.tvUserName).text = u.name
            view.findViewById<TextView>(R.id.tvUserRole).text = listOfNotNull(u.position, u.unit).joinToString(" · ")
            // Mode Tugas Lapangan mengikuti jenis kerja yang diatur admin, bukan saklar yang bisa diubah pegawai.
            view.findViewById<androidx.appcompat.widget.SwitchCompat>(R.id.swFieldMode).apply {
                isChecked = u.workType == "petugas_lapangan"
                isEnabled = false
                isClickable = false
                contentDescription = getString(if (isChecked) R.string.config_field_on else R.string.config_field_off)
            }
        }

        // Mode tampilan: Ikuti sistem / Terang / Gelap. Layar langsung berganti setelah dipilih.
        val themeValue = view.findViewById<TextView>(R.id.tvThemeValue)
        themeValue.text = ThemeMode.label(requireContext())
        view.onClick(R.id.rowTheme) {
            val keys = ThemeMode.options.map { it.first }
            val checked = keys.indexOf(ThemeMode.current(requireContext()))
            AlertDialog.Builder(requireContext())
                .setTitle(R.string.config_mode_tampilan)
                .setSingleChoiceItems(ThemeMode.options.map { it.second }.toTypedArray(), checked) { dialog, which ->
                    dialog.dismiss()
                    ThemeMode.set(requireContext().applicationContext, keys[which])
                }
                .show()
        }

        view.onClick(R.id.btnSave) {
            toast(R.string.msg_saved)
            findNavController().popBackStack()
        }
    }

    /** Data profil hanya bisa diubah admin, jadi pegawai mengirim permintaan berisi data yang perlu diubah. */
    private fun askChangeInfo() {
        val input = android.widget.EditText(requireContext()).apply {
            hint = getString(R.string.change_info_hint)
            minLines = 3
            gravity = android.view.Gravity.TOP
        }
        val box = android.widget.FrameLayout(requireContext()).apply {
            val pad = (20 * resources.displayMetrics.density).toInt()
            setPadding(pad, pad / 2, pad, 0)
            addView(input)
        }
        AlertDialog.Builder(requireContext())
            .setTitle(R.string.config_ajukan_perubahan_informasi)
            .setMessage(R.string.change_info_body)
            .setView(box)
            .setNegativeButton(R.string.checkout_early_cancel, null)
            .setPositiveButton(R.string.change_info_send) { _, _ ->
                val text = input.text.toString().trim()
                if (text.length < 5) return@setPositiveButton toast(R.string.change_info_short)
                launch {
                    when (val r = com.simpres.app.data.call { requestProfileChange(mapOf("message" to text)) }) {
                        is com.simpres.app.data.ApiResult.Ok -> alert(r.value.get("message").asString)
                        is com.simpres.app.data.ApiResult.Failed -> alert(r.message)
                        com.simpres.app.data.ApiResult.Offline -> alert(getString(R.string.msg_server_unreachable))
                    }
                }
            }
            .show()
    }
}

/** Antrean sinkronisasi: attendance saved while offline; sent again with the button or on next Beranda visit. */
class SyncFragment : BaseFragment(R.layout.fragment_sync) {
    override fun onReady(view: View) {
        show(view)
        view.onClick(R.id.btnSync) {
            if (Outbox.all().isEmpty()) return@onClick toast(R.string.msg_nothing_queued)
            launch {
                val sent = Outbox.flush()
                toast(if (Outbox.all().isEmpty()) getString(R.string.msg_sync_done, sent) else getString(R.string.msg_still_offline))
                show(view)
            }
        }
    }

    private fun show(view: View) {
        val items = Outbox.all()
        view.findViewById<TextView>(R.id.tvNetwork).setText(if (isOnline()) R.string.sync_online else R.string.sync_offline)
        view.findViewById<TextView>(R.id.tvLastSync).text = Outbox.lastSync?.let { getString(R.string.sync_last, it) } ?: "-"
        view.findViewById<TextView>(R.id.tvQueued).text = items.size.toString()
        view.findViewById<TextView>(R.id.tvFailed).text = "0"
        val list = view.findViewById<ViewGroup>(R.id.queueList)
        list.clearAfter(null)
        if (items.isEmpty()) list.addMessage(getString(R.string.msg_nothing_queued))
        items.forEach {
            list.addRow(R.drawable.ic_sync, if (it.kind == "check-out") "Check-out" else "Check-in",
                "Disimpan di perangkat · ${it.savedAt.take(5)}", "Menunggu", Tone.WARN)
        }
    }
}

class PrivacyFragment : BaseFragment(R.layout.fragment_privacy) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnOpenSettings) {
            val intent = Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS)
                .setData(Uri.fromParts("package", requireContext().packageName, null))
            startActivity(intent)
        }
    }
}

class AboutFragment : BaseFragment(R.layout.fragment_about) {
    override fun onReady(view: View) = Unit
}
