package com.simpres.app.ui.leave

import android.view.View
import com.simpres.app.R
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.onClick

class LeaveListFragment : BaseFragment(R.layout.fragment_leave_list) {
    override fun onReady(view: View) {
        singleChoice(view, R.id.tabAll, R.id.tabPending, R.id.tabAccepted, R.id.tabRejected)
        view.onClick(R.id.btnNew) { go(R.id.leaveFormFragment) }
        view.onClick(R.id.itemAccepted) { go(R.id.leaveAcceptedFragment) }
        view.onClick(R.id.itemRejected) { go(R.id.leaveRejectedFragment) }
    }
}

/** One form, two field groups: "Sakit" shows the sick-leave fields, other types show the time-off fields. */
class LeaveFormFragment : BaseFragment(R.layout.fragment_leave_form) {
    override fun onReady(view: View) {
        val groupOut = view.findViewById<View>(R.id.groupOut)
        val groupSick = view.findViewById<View>(R.id.groupSick)

        singleChoice(view, R.id.chipOut, R.id.chipSick, R.id.chipOther) { selected ->
            val sick = selected == R.id.chipSick
            groupSick.visibility = if (sick) View.VISIBLE else View.GONE
            groupOut.visibility = if (sick) View.GONE else View.VISIBLE
        }
        view.onClick(R.id.btnRemoveFile) { /* TODO: clear the picked attachment */ }
        view.onClick(R.id.btnSubmit) { replace(R.id.leaveSentFragment) }
    }
}

class LeaveSentFragment : BaseFragment(R.layout.fragment_leave_sent) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnPrimary) { backTo(R.id.leaveListFragment) }
        view.onClick(R.id.tvHome) { backTo(R.id.homeFragment) }
    }
}

class LeaveAcceptedFragment : BaseFragment(R.layout.fragment_leave_accepted) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnPrimary) { backTo(R.id.leaveListFragment) }
    }
}

class LeaveRejectedFragment : BaseFragment(R.layout.fragment_leave_rejected) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnPrimary) { replace(R.id.leaveFormFragment) }
    }
}
