package com.simpres.app.ui.attendance

import android.Manifest
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.location.Location
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.core.os.bundleOf
import com.simpres.app.R
import com.simpres.app.data.ApiResult
import com.simpres.app.data.Attendance
import com.simpres.app.data.Format
import com.simpres.app.data.Locator
import com.simpres.app.data.Outbox
import com.simpres.app.data.PendingAttendance
import com.simpres.app.data.Session
import com.simpres.app.data.call
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.onClick
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.ByteArrayOutputStream
import java.time.LocalDate
import java.time.LocalTime

/** Result of the last check-in/check-out, shown by the result screens. */
object AttendanceFlow {
    const val ARG_KIND = "kind"
    const val CHECK_IN = "check-in"
    const val CHECK_OUT = "check-out"

    var kind = CHECK_IN
    var attendance: Attendance? = null
    var message: String? = null
    var distanceM: Double? = null
    var accuracyM: Double? = null
}

/**
 * Check-in and check-out screen. Gets one GPS fix, sends it to the server and moves to the matching result:
 * accepted, outside the radius, or saved offline. The server makes the final decision (radius 150 m, GPS
 * accuracy, mock location, once per day).
 */
class CheckInFragment : BaseFragment(R.layout.fragment_checkin) {
    private val permission = registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { granted ->
        if (granted.values.any { it }) locateAndSend() else showStatus(getString(R.string.msg_need_location), retry = true)
    }
    private val kind get() = arguments?.getString(AttendanceFlow.ARG_KIND) ?: AttendanceFlow.CHECK_IN

    override fun onReady(view: View) {
        view.findViewById<TextView>(R.id.tvScreenTitle)
            .setText(if (kind == AttendanceFlow.CHECK_OUT) R.string.checkout_title else R.string.checkin_title)
        Session.user?.workLocation?.let { loc ->
            view.findViewById<TextView>(R.id.tvAreaSub).text = getString(R.string.checkin_area_sub, loc.name, loc.radiusM)
        }
        view.findViewById<TextView>(R.id.tvServerTime).text = Format.nowTime()
        view.findViewById<TextView>(R.id.tvDistance).text = "-"
        view.findViewById<TextView>(R.id.tvAccuracy).text = "-"
        view.findViewById<TextView>(R.id.tvSchedule).text = "-"
        view.onClick(R.id.btnLocate) { start() }
        view.onClick(R.id.btnAuto) { start() }
        start()
    }

    private fun start() {
        val ctx = requireContext()
        val fine = ContextCompat.checkSelfPermission(ctx, Manifest.permission.ACCESS_FINE_LOCATION)
        if (fine == PackageManager.PERMISSION_GRANTED) locateAndSend()
        else permission.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
    }

    private fun showStatus(text: String, retry: Boolean) {
        val v = view ?: return
        v.findViewById<TextView>(R.id.tvHint).text = text
        v.findViewById<Button>(R.id.btnAuto).apply {
            this.text = if (retry) getString(R.string.checkin_retry) else text
            isEnabled = retry
        }
    }

    private fun locateAndSend() = launch {
        showStatus(getString(R.string.checkin_locating), retry = false)
        val location = Locator.current(requireContext())
        if (location == null) {
            showStatus(getString(R.string.msg_gps_failed), retry = true)
            return@launch
        }
        showLocalPreview(location)
        showStatus(getString(R.string.checkin_sending), retry = false)

        val uuid = Outbox.newUuid()
        val body = Locator.body(location, uuid)
        AttendanceFlow.kind = kind
        AttendanceFlow.accuracyM = location.accuracy.toDouble()
        val result = call { if (kind == AttendanceFlow.CHECK_OUT) checkOut(body) else checkIn(body) }
        when (result) {
            is ApiResult.Ok -> {
                AttendanceFlow.attendance = result.value.data
                AttendanceFlow.message = result.value.message
                replace(if (kind == AttendanceFlow.CHECK_OUT) R.id.checkOutSuccessFragment else R.id.checkInSuccessFragment)
            }
            is ApiResult.Failed -> {
                val distance = result.body?.get("distance_m")?.takeIf { !it.isJsonNull }?.asDouble
                AttendanceFlow.message = result.message
                AttendanceFlow.distanceM = distance
                if (result.code == 422 && result.message.contains("luar radius")) {
                    replace(R.id.outsideRadiusFragment)
                } else {
                    showStatus(result.message, retry = true)
                    alert(result.message)
                }
            }
            ApiResult.Offline -> {
                Outbox.add(PendingAttendance(kind, body, LocalTime.now().toString()))
                replace(R.id.offlineSavedFragment)
            }
        }
    }

    /** Distance shown on screen right away; the server repeats the same calculation and decides. */
    private fun showLocalPreview(location: Location) {
        val v = view ?: return
        val loc = Session.user?.workLocation ?: return
        val out = FloatArray(1)
        Location.distanceBetween(location.latitude, location.longitude, loc.latitude, loc.longitude, out)
        val inside = out[0] <= loc.radiusM
        v.findViewById<TextView>(R.id.tvDistance).text = Format.meters(out[0].toDouble())
        v.findViewById<TextView>(R.id.tvAccuracy).text = getString(R.string.checkin_accuracy, location.accuracy.toInt())
        v.findViewById<TextView>(R.id.tvAreaTitle).setText(if (inside) R.string.checkin_inside else R.string.checkin_outside)
    }
}

class OutsideRadiusFragment : BaseFragment(R.layout.fragment_outside_radius) {
    override fun onReady(view: View) {
        val distance = Format.meters(AttendanceFlow.distanceM)
        view.findViewById<TextView>(R.id.tvHeadline).text = getString(R.string.outside_headline, distance)
        view.findViewById<TextView>(R.id.tvDistance).text = distance
        view.findViewById<TextView>(R.id.tvAccuracy).text = Format.meters(AttendanceFlow.accuracyM)
        view.findViewById<TextView>(R.id.tvRadius).text = "${Session.user?.workLocation?.radiusM ?: 150} m"
        AttendanceFlow.message?.let { view.findViewById<TextView>(R.id.tvMessage).text = it }
        val again = { replace(R.id.checkInFragment, bundleOf(AttendanceFlow.ARG_KIND to AttendanceFlow.kind)) }
        view.onClick(R.id.btnRefresh) { again() }
        view.onClick(R.id.btnLocate) { again() }
        view.onClick(R.id.rowFieldDuty) { go(R.id.fieldDutyFragment) }
    }
}

/** Optional face photo (not part of the MVP flow, kept from the PRD). */
class FacePhotoFragment : BaseFragment(R.layout.fragment_face_photo) {
    override fun onReady(view: View) {
        view.onClick(R.id.btnShutter) { replace(R.id.checkInSuccessFragment) }
    }
}

/** Fills the shared result layout (check-in and check-out success) from [AttendanceFlow]. */
private fun fillResult(view: View, checkOut: Boolean) {
    val a = AttendanceFlow.attendance ?: return
    val place = Session.user?.workLocation?.name ?: "Puskesmas"
    val time = if (checkOut) a.checkOutAt else a.checkInAt
    val distance = if (checkOut) a.checkOutDistanceM else a.checkInDistanceM
    view.findViewById<TextView>(R.id.tvTime).text = Format.time(time)
    view.findViewById<TextView>(R.id.tvPlace).text = view.context.getString(R.string.result_place, place, Format.meters(distance))
    view.findViewById<TextView>(R.id.tvNote).text =
        if (!checkOut && a.status == "terlambat") view.context.getString(R.string.result_late, a.lateMinutes)
        else Format.attendanceStatus(a.status)
    AttendanceFlow.message?.let { view.findViewById<TextView>(R.id.tvSubtitle).text = it }
}

class CheckInSuccessFragment : BaseFragment(R.layout.fragment_checkin_success) {
    override fun onReady(view: View) {
        fillResult(view, checkOut = false)
        view.onClick(R.id.btnPrimary) { backTo(R.id.homeFragment) }
        view.onClick(R.id.tvDetail) { go(R.id.historyFragment) }
    }
}

class CheckOutSuccessFragment : BaseFragment(R.layout.fragment_checkout_success) {
    override fun onReady(view: View) {
        fillResult(view, checkOut = true)
        view.onClick(R.id.btnPrimary) { backTo(R.id.homeFragment) }
        view.onClick(R.id.tvDetail) { go(R.id.historyFragment) }
    }
}

class OfflineSavedFragment : BaseFragment(R.layout.fragment_offline_saved) {
    override fun onReady(view: View) {
        view.findViewById<TextView>(R.id.tvType)
            .setText(if (AttendanceFlow.kind == AttendanceFlow.CHECK_OUT) R.string.checkout_title else R.string.checkin_title)
        view.findViewById<TextView>(R.id.tvTime).text = Format.nowTime()
        view.findViewById<TextView>(R.id.tvQueue).text = getString(R.string.queue_count, Outbox.all().size)
        view.onClick(R.id.btnRetry) {
            launch {
                val sent = Outbox.flush()
                if (Outbox.all().isEmpty()) {
                    toast(getString(R.string.msg_sync_done, sent))
                    backTo(R.id.homeFragment)
                } else {
                    toast(R.string.msg_still_offline)
                }
            }
        }
        view.onClick(R.id.btnPrimary) { backTo(R.id.homeFragment) }
    }
}

/** Field duty report: place, description, optional letter number and a camera photo (no gallery). */
class FieldDutyFragment : BaseFragment(R.layout.fragment_field_duty) {
    private var photo: Bitmap? = null
    private var gps: Location? = null
    private val camera = registerForActivityResult(ActivityResultContracts.TakePicturePreview()) { bmp ->
        if (bmp != null) {
            photo = bmp
            view?.findViewById<TextView>(R.id.tvPhotoLabel)?.setText(R.string.photo_taken)
        }
    }

    override fun onReady(view: View) {
        val place = view.findViewById<TextView>(R.id.tvDutyType)
        val letter = view.findViewById<EditText>(R.id.etLetterNo)
        val reason = view.findViewById<EditText>(R.id.etReason)
        reason.setText("")
        place.setOnClickListener {
            val input = EditText(requireContext()).apply { setText(place.text) }
            androidx.appcompat.app.AlertDialog.Builder(requireContext())
                .setTitle(R.string.duty_location_title).setView(input)
                .setPositiveButton(android.R.string.ok) { _, _ -> if (input.text.isNotBlank()) place.text = input.text }
                .setNegativeButton(android.R.string.cancel, null).show()
        }
        view.onClick(R.id.boxPhoto) { camera.launch(null) }
        if (ContextCompat.checkSelfPermission(requireContext(), Manifest.permission.ACCESS_FINE_LOCATION)
            == PackageManager.PERMISSION_GRANTED
        ) launch {
            gps = Locator.current(requireContext())
            gps?.let {
                view.findViewById<TextView>(R.id.tvGpsCoords).text = "%.5f, %.5f".format(it.latitude, it.longitude)
            }
        }

        view.onClick(R.id.btnSubmit) {
            val bmp = photo ?: return@onClick toast(R.string.msg_take_photo)
            if (reason.text.isBlank()) return@onClick toast(R.string.msg_fill_form)
            val text = "text/plain".toMediaType()
            val description = buildString {
                append(reason.text.trim())
                if (letter.text.isNotBlank()) append(" (Surat tugas: ${letter.text.trim()})")
            }
            val fields = mutableMapOf(
                "date" to LocalDate.now().toString().toRequestBody(text),
                "location_name" to place.text.toString().toRequestBody(text),
                "description" to description.toRequestBody(text),
            )
            gps?.let {
                fields["latitude"] = it.latitude.toString().toRequestBody(text)
                fields["longitude"] = it.longitude.toString().toRequestBody(text)
            }
            val jpg = ByteArrayOutputStream().also { bmp.compress(Bitmap.CompressFormat.JPEG, 85, it) }.toByteArray()
            val part = MultipartBody.Part.createFormData("foto", "tugas.jpg", jpg.toRequestBody("image/jpeg".toMediaType()))
            launch {
                when (val r = call { createFieldDuty(fields, part) }) {
                    is ApiResult.Ok -> {
                        toast(r.value.get("message").asString)
                        backTo(R.id.homeFragment)
                    }
                    is ApiResult.Failed -> alert(r.message)
                    ApiResult.Offline -> alert(getString(R.string.msg_server_unreachable))
                }
            }
        }
    }
}

class CameraFragment : BaseFragment(R.layout.fragment_camera) {
    override fun onReady(view: View) {
        // Field duty photos use the system camera directly (FieldDutyFragment); this screen only goes back.
        view.onClick(R.id.btnShutter) { requireActivity().onBackPressedDispatcher.onBackPressed() }
    }
}
