package com.simpres.app.ui.settings

import android.content.Intent
import android.net.Uri
import android.provider.Settings
import android.view.View
import androidx.navigation.fragment.findNavController
import com.simpres.app.R
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.onClick

class ConfigFragment : BaseFragment(R.layout.fragment_config) {
    override fun onReady(view: View) {
        view.onClick(R.id.rowResetPassword) { go(R.id.forgotFragment) }
        view.onClick(R.id.btnSave) {
            toast(R.string.msg_saved)
            findNavController().popBackStack()
        }
    }
}

class SyncFragment : BaseFragment(R.layout.fragment_sync) {
    override fun onReady(view: View) {
        // TODO: enqueue the WorkManager sync job when the offline queue is implemented.
        view.onClick(R.id.btnSync) { toast(if (isOnline()) R.string.msg_syncing else R.string.msg_still_offline) }
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
