package com.simpres.app.ui.attendance

import android.Manifest
import android.annotation.SuppressLint
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.location.Location
import android.view.GestureDetector
import android.view.MotionEvent
import android.view.ScaleGestureDetector
import android.view.View
import android.view.ViewConfiguration
import android.widget.Button
import android.widget.EditText
import android.widget.ImageView
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.core.os.bundleOf
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
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
import kotlin.math.log2
import kotlinx.coroutines.launch
import java.util.Locale
import kotlinx.coroutines.withContext
import kotlinx.coroutines.Dispatchers
import android.location.Geocoder

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
    var location: Location? = null
}

/** Zoom dan geseran peta yang dipilih pegawai. Pan dalam piksel gambar peta pada zoom itu. */
private class MapState(var zoom: Int = 0, var panX: Double = 0.0, var panY: Double = 0.0)

/**
 * Peta asli (OpenStreetMap) di kotak peta halaman, dengan radius Puskesmas dan posisi pegawai jika ada.
 * Bisa digeser dengan satu jari, diperbesar/diperkecil dengan tombol + dan -, cubit dua jari, atau ketuk dua kali.
 */
@SuppressLint("ClickableViewAccessibility")
private fun Fragment.showMap(view: View, me: Location?) {
    val place = Session.user?.workLocation ?: return
    val box = view.findViewById<View>(R.id.mapBox)
    val image = view.findViewById<ImageView>(R.id.mapImage)
    val color = ContextCompat.getColor(requireContext(), R.color.primary)
    // Disimpan di tag kotak peta, supaya zoom dan geseran tidak kembali semula saat posisi GPS masuk.
    val state = box.getTag(R.id.mapZoom) as? MapState ?: MapState().also { box.setTag(R.id.mapZoom, it) }

    fun render() = box.post {
        if (this.view == null) return@post
        viewLifecycleOwner.lifecycleScope.launch {
            OsmMap.show(box, image, place, me, color, state.zoom, state.panX, state.panY)
            image.scaleX = 1f; image.scaleY = 1f
            image.translationX = 0f; image.translationY = 0f
        }
    }
    fun zoom(step: Int) {
        val next = (state.zoom + step).coerceIn(-8, 5)
        val factor = Math.pow(2.0, (next - state.zoom).toDouble())
        // Titik di tengah layar tetap di tengah setelah zoom.
        state.panX *= factor; state.panY *= factor
        state.zoom = next
        render()
    }
    view.findViewById<View>(R.id.btnZoomIn).setOnClickListener { zoom(1) }
    view.findViewById<View>(R.id.btnZoomOut).setOnClickListener { zoom(-1) }

    var pinch = 1f
    val scale = ScaleGestureDetector(requireContext(), object : ScaleGestureDetector.SimpleOnScaleGestureListener() {
        override fun onScaleBegin(d: ScaleGestureDetector): Boolean { pinch = 1f; return image.drawable != null }
        override fun onScale(d: ScaleGestureDetector): Boolean {
            pinch = (pinch * d.scaleFactor).coerceIn(0.25f, 4f)
            image.scaleX = pinch // pratinjau langsung, lalu peta digambar ulang dengan zoom baru
            image.scaleY = pinch
            return true
        }
        override fun onScaleEnd(d: ScaleGestureDetector) {
            val step = Math.round(log2(pinch.toDouble())).toInt()
            if (step != 0) zoom(step) else { image.scaleX = 1f; image.scaleY = 1f }
        }
    })
    val taps = GestureDetector(requireContext(), object : GestureDetector.SimpleOnGestureListener() {
        override fun onDoubleTap(e: MotionEvent): Boolean { if (image.drawable != null) zoom(1); return true }
    })

    val slop = ViewConfiguration.get(requireContext()).scaledTouchSlop
    var downX = 0f; var downY = 0f
    var dragging = false; var twoFingers = false
    box.setOnTouchListener { v, e ->
        scale.onTouchEvent(e)
        taps.onTouchEvent(e)
        when (e.actionMasked) {
            MotionEvent.ACTION_DOWN -> { downX = e.x; downY = e.y; dragging = false; twoFingers = false }
            MotionEvent.ACTION_POINTER_DOWN -> {
                twoFingers = true
                image.translationX = 0f; image.translationY = 0f
                v.parent.requestDisallowInterceptTouchEvent(true)
            }
            MotionEvent.ACTION_MOVE -> if (!twoFingers && image.drawable != null) {
                val dx = e.x - downX; val dy = e.y - downY
                if (!dragging && Math.hypot(dx.toDouble(), dy.toDouble()) > slop) {
                    dragging = true
                    // Halaman tidak ikut tergulir selama peta digeser.
                    v.parent.requestDisallowInterceptTouchEvent(true)
                }
                if (dragging) { image.translationX = dx; image.translationY = dy }
            }
            MotionEvent.ACTION_UP -> if (dragging && !twoFingers) {
                val k = OsmMap.scale(box)
                state.panX -= (e.x - downX) / k
                state.panY -= (e.y - downY) / k
                render()
            }
            MotionEvent.ACTION_CANCEL -> { image.translationX = 0f; image.translationY = 0f }
        }
        true
    }
    render()
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
            view.findViewById<TextView>(R.id.tvOfficeName).text = loc.name
            view.findViewById<TextView>(R.id.tvOfficeAddress).text =
                listOfNotNull(loc.address?.takeIf { it.isNotBlank() }, "Radius ${loc.radiusM} m").joinToString(" · ")
        }
        view.findViewById<TextView>(R.id.tvServerTime).text = Format.nowTime()
        view.findViewById<TextView>(R.id.tvDistance).text = "-"
        view.findViewById<TextView>(R.id.tvAccuracy).text = "-"
        view.findViewById<TextView>(R.id.tvSchedule).text = "-"
        view.onClick(R.id.btnLocate) { start() }
        view.onClick(R.id.btnAuto) { start() }
        showMap(view, null)
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
        // Fake GPS: beri peringatan di HP. Data tetap dikirim, server yang menolak absennya.
        if (Locator.isMock(location)) com.simpres.app.data.Notifier.showFakeGps(requireContext().applicationContext)
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
                    alert(result.message, getString(R.string.checkin_failed_title))
                }
            }
            ApiResult.Offline -> {
                Outbox.add(PendingAttendance(kind, body.copy(source = "offline"), LocalTime.now().toString()))
                // Terkirim otomatis di latar belakang begitu internet tersambung, walaupun aplikasi ditutup.
                com.simpres.app.data.Background.runSoon(requireContext())
                replace(R.id.offlineSavedFragment)
            }
        }
    }

    /** Distance shown on screen right away; the server repeats the same calculation and decides. */
    private fun showLocalPreview(location: Location) {
        val v = view ?: return
        AttendanceFlow.location = location
        showMap(v, location)
        val loc = Session.user?.workLocation ?: return
        val out = FloatArray(1)
        Location.distanceBetween(location.latitude, location.longitude, loc.latitude, loc.longitude, out)
        val inside = out[0] <= loc.radiusM
        v.findViewById<TextView>(R.id.tvDistance).text = Format.meters(out[0].toDouble())
        // Baris Lokasi Anda dan jarak ke Titik Kantor (hijau di dalam radius, merah di luar).
        v.findViewById<TextView>(R.id.tvMyCoords).text =
            getString(R.string.place_coords, location.latitude, location.longitude, location.accuracy.toInt())
        v.findViewById<TextView>(R.id.tvOfficeDistance).apply {
            text = if (out[0] >= 1000) "%.1f km".format(out[0] / 1000) else Format.meters(out[0].toDouble())
            setBackgroundResource(if (inside) R.drawable.bg_chip_success else R.drawable.bg_chip_danger)
            setTextColor(ContextCompat.getColor(context, if (inside) R.color.success else R.color.danger))
        }
        showAddress(v, location)
        v.findViewById<TextView>(R.id.tvAccuracy).text = getString(R.string.checkin_accuracy, location.accuracy.toInt())
        v.findViewById<TextView>(R.id.tvAreaTitle).setText(if (inside) R.string.checkin_inside else R.string.checkin_outside)
    }
}

/** Alamat dari titik GPS (Geocoder Android). Jika tidak tersedia, cukup tulis "Koordinat GPS". */
private fun Fragment.showAddress(view: View, location: Location) {
    val label = view.findViewById<TextView>(R.id.tvMyPlace)
    viewLifecycleOwner.lifecycleScope.launch {
        val address = withContext(Dispatchers.IO) {
            runCatching {
                @Suppress("DEPRECATION")
                Geocoder(requireContext(), Locale("id", "ID")).getFromLocation(location.latitude, location.longitude, 1)
                    ?.firstOrNull()?.let { a ->
                        // featureName kadang berisi kode Plus (misalnya XMH8+26J) yang tidak berarti bagi pegawai.
                        val place = a.thoroughfare ?: a.featureName?.takeUnless { it.contains('+') }
                        listOfNotNull(place, a.subLocality, a.locality ?: a.subAdminArea)
                            .distinct().joinToString(", ").ifBlank { a.getAddressLine(0) }
                    }
            }.getOrNull()
        }
        if (isAdded) label.text = address ?: getString(R.string.place_gps_only)
    }
}

class OutsideRadiusFragment : BaseFragment(R.layout.fragment_outside_radius) {
    override fun onReady(view: View) {
        showMap(view, AttendanceFlow.location)
        val distance = Format.meters(AttendanceFlow.distanceM)
        view.findViewById<TextView>(R.id.tvHeadline).text = getString(R.string.outside_headline, distance)
        view.findViewById<TextView>(R.id.tvDistance).text = distance
        view.findViewById<TextView>(R.id.tvAccuracy).text = Format.meters(AttendanceFlow.accuracyM)
        view.findViewById<TextView>(R.id.tvRadius).text = "${Session.user?.workLocation?.radiusM ?: 100} m"
        val place = Session.user?.workLocation
        view.findViewById<TextView>(R.id.tvMessage).text = AttendanceFlow.message
            ?: getString(R.string.outside_radius_absen_hanya_bisa_dalam, place?.radiusM ?: 100, place?.name ?: "Puskesmas")
        val again = { replace(R.id.checkInFragment, bundleOf(AttendanceFlow.ARG_KIND to AttendanceFlow.kind)) }
        view.onClick(R.id.btnRefresh) { again() }
        view.onClick(R.id.btnLocate) { again() }
        // Tugas Lapangan hanya untuk pegawai yang ditetapkan admin sebagai petugas lapangan (Data Pegawai > Jenis kerja).
        view.findViewById<View>(R.id.rowFieldDuty).visibility =
            if (Session.user?.workType == "petugas_lapangan") View.VISIBLE else View.GONE
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
    // Keterangan: check-in tepat waktu/terlambat, check-out sesuai jadwal/pulang cepat. Kuning jika ada catatan.
    val (note, warn) = when {
        checkOut && a.earlyLeaveMinutes > 0 -> "Pulang cepat ${a.earlyLeaveMinutes} menit" to true
        checkOut -> "Sesuai jadwal" to false
        a.status == "terlambat" -> view.context.getString(R.string.result_late, a.lateMinutes) to true
        else -> "Tepat waktu" to false
    }
    view.findViewById<TextView>(R.id.tvNote).apply {
        text = note
        setTextColor(androidx.core.content.ContextCompat.getColor(context, if (warn) R.color.warn else R.color.success))
    }
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
    // Izin kamera diminta dulu. Tanpa izin ini Android menolak membuka kamera dan aplikasi tertutup.
    private val cameraPermission = registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) openCamera() else toast(R.string.msg_need_camera)
    }

    private fun openCamera() {
        runCatching { camera.launch(null) }.onFailure { toast(R.string.msg_need_camera) }
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
        view.onClick(R.id.boxPhoto) {
            val granted = ContextCompat.checkSelfPermission(requireContext(), Manifest.permission.CAMERA) ==
                PackageManager.PERMISSION_GRANTED
            if (granted) openCamera() else cameraPermission.launch(Manifest.permission.CAMERA)
        }
        if (ContextCompat.checkSelfPermission(requireContext(), Manifest.permission.ACCESS_FINE_LOCATION)
            == PackageManager.PERMISSION_GRANTED
        ) launch {
            gps = Locator.current(requireContext())
            val coords = view.findViewById<TextView>(R.id.tvGpsCoords)
            val fix = gps
            if (fix != null && Locator.isMock(fix)) com.simpres.app.data.Notifier.showFakeGps(requireContext().applicationContext)
            if (fix == null) coords.setText(R.string.msg_gps_failed)
            else coords.text = getString(R.string.duty_gps_value, fix.latitude, fix.longitude, fix.accuracy.toInt())
        }

        view.onClick(R.id.btnSubmit) {
            if (place.text.isBlank()) return@onClick toast(R.string.msg_fill_place)
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
