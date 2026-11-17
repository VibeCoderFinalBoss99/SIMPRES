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

class ConfigFragment : BaseFragment(R.layout.fragment_config) {
    override fun onReady(view: View) {
        view.onClick(R.id.rowResetPassword) { go(R.id.forgotFragment) }
        view.onClick(R.id.btnSave) {
            toast(R.string.msg_saved)
            findNavController().popBackStack()
        }
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
