package com.simpres.app.ui.attendance

import android.view.View
import com.simpres.app.R
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.onClick

/** Geofence rule from the spec: attendance is only valid within 150 m of the Puskesmas. */
object Geofence {
    const val RADIUS_M = 150

    /** Demo value from the Figma file (42 m = inside, 212 m = outside). Replace with a real location fix. */
    var distanceM = 42
}

private const val AUTO_CHECKIN_DELAY_MS = 1500L

class CheckInFragment : BaseFragment(R.layout.fragment_checkin) {
    override fun onReady(view: View) {
        if (Geofence.distanceM > Geofence.RADIUS_M) {
            replace(R.id.outsideRadiusFragment)
            return
        }
        // Inside the radius: the check-in is recorded automatically, no confirmation step.
        view.postDelayed({
            if (!isAdded) return@postDelayed
            replace(if (isOnline()) R.id.checkInSuccessFragment else R.id.offlineSavedFragment)
        }, AUTO_CHECKIN_DELAY_MS)
    }
}

class OutsideRadiusFragment : BaseFragment(R.layout.fragment_outside_radius) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnRefresh) { replace(R.id.checkInFragment) }
        view.onClick(R.id.btnLocate) { replace(R.id.checkInFragment) }
        view.onClick(R.id.rowFieldDuty) { go(R.id.fieldDutyFragment) }
    }
}

/** Optional face photo (not part of the MVP flow, kept from the PRD). */
class FacePhotoFragment : BaseFragment(R.layout.fragment_face_photo) {
    override fun onReady(view: View) {
        // TODO: CameraX preview (front camera) goes into the placeholder above the shutter button.
        view.onClick(R.id.btnShutter) { replace(R.id.checkInSuccessFragment) }
    }
}

class CheckInSuccessFragment : BaseFragment(R.layout.fragment_checkin_success) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnPrimary) { backTo(R.id.homeFragment) }
        view.onClick(R.id.tvDetail) { go(R.id.attendanceDetailFragment) }
    }
}

class CheckOutSuccessFragment : BaseFragment(R.layout.fragment_checkout_success) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnPrimary) { backTo(R.id.homeFragment) }
        view.onClick(R.id.tvDetail) { go(R.id.attendanceDetailFragment) }
    }
}

class OfflineSavedFragment : BaseFragment(R.layout.fragment_offline_saved) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnRetry) {
            if (isOnline()) replace(R.id.checkInSuccessFragment) else toast(R.string.msg_still_offline)
        }
        view.onClick(R.id.btnPrimary) { backTo(R.id.homeFragment) }
    }
}

class FieldDutyFragment : BaseFragment(R.layout.fragment_field_duty) {
    override fun onReady(view: View) {
        view.onClick(R.id.boxPhoto) { go(R.id.cameraFragment) }
        view.onClick(R.id.btnSubmit) {
            toast(R.string.msg_request_sent)
            backTo(R.id.homeFragment)
        }
    }
}

class CameraFragment : BaseFragment(R.layout.fragment_camera) {
    override fun onReady(view: View) {
        // TODO: CameraX back-camera preview; photos from the gallery are not allowed for field evidence.
        view.onClick(R.id.btnShutter) {
            toast(R.string.msg_photo_saved)
            requireActivity().onBackPressedDispatcher.onBackPressed()
        }
    }
}
