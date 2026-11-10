package com.simpres.app.ui.home

import android.graphics.Color
import android.graphics.drawable.ColorDrawable
import android.graphics.drawable.InsetDrawable
import android.view.View
import android.view.ViewGroup
import androidx.appcompat.app.AlertDialog
import androidx.navigation.fragment.findNavController
import com.simpres.app.R
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.onClick

class HomeFragment : BaseFragment(R.layout.fragment_home) {
    override fun onReady(view: View) {
        bindBottomNav(view, R.id.homeFragment)
        view.onClick(R.id.btnBell) { go(R.id.notificationFragment) }
        view.onClick(R.id.tvSeeAll) { go(R.id.historyFragment) }
        view.onClick(R.id.cardSync) { go(R.id.syncFragment) }
        view.onClick(R.id.btnCheckOut) { go(R.id.checkOutSuccessFragment) }
    }
}

class HistoryFragment : BaseFragment(R.layout.fragment_history) {
    override fun onReady(view: View) {
        bindBottomNav(view, R.id.historyFragment)
        singleChoice(view, R.id.tabAll, R.id.tabRoutine, R.id.tabField)
        view.onClick(R.id.itemToday) { go(R.id.attendanceDetailFragment) }
        view.onClick(R.id.itemField) { go(R.id.attendanceDetailFragment) }
        view.onClick(R.id.itemRejected) { go(R.id.correctionFragment) }
    }
}

class AttendanceDetailFragment : BaseFragment(R.layout.fragment_attendance_detail) {
    override fun onReady(view: View) = Unit
}

class ScheduleFragment : BaseFragment(R.layout.fragment_schedule) {
    override fun onReady(view: View) = bindBottomNav(view, R.id.scheduleFragment)
}

class CorrectionFragment : BaseFragment(R.layout.fragment_correction) {
    override fun onReady(view: View) {
        singleChoice(view, R.id.chipGps, R.id.chipNoInternet, R.id.chipForgot, R.id.chipCamera, R.id.chipOther)
        view.onClick(R.id.btnSubmit) {
            toast(R.string.msg_request_sent)
            findNavController().popBackStack()
        }
    }
}

class NotificationFragment : BaseFragment(R.layout.fragment_notification) {
    override fun onReady(view: View) = Unit
}

class ProfileFragment : BaseFragment(R.layout.fragment_profile) {
    override fun onReady(view: View) {
        bindBottomNav(view, R.id.profileFragment)
        view.onClick(R.id.rowLeave) { go(R.id.leaveListFragment) }
        view.onClick(R.id.rowPermission) { go(R.id.privacyFragment) }
        view.onClick(R.id.rowPrivacy) { go(R.id.privacyFragment) }
        view.onClick(R.id.rowSettings) { go(R.id.configFragment) }
        view.onClick(R.id.rowAbout) { go(R.id.aboutFragment) }
        view.onClick(R.id.btnLogout) { showLogoutDialog() }
    }

    private fun showLogoutDialog() {
        val content = layoutInflater.inflate(R.layout.dialog_logout, null)
        val dialog = AlertDialog.Builder(requireContext()).setView(content).create()
        content.onClick(R.id.btnCancel) { dialog.dismiss() }
        content.onClick(R.id.btnConfirm) {
            dialog.dismiss()
            // TODO: clear the session; unsent attendance stays in the local queue.
            reset(R.id.loginFragment, R.id.homeFragment)
        }
        dialog.show()
        val margin = (28 * resources.displayMetrics.density).toInt()
        dialog.window?.apply {
            setBackgroundDrawable(InsetDrawable(ColorDrawable(Color.TRANSPARENT), margin))
            setLayout(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT)
        }
    }
}
