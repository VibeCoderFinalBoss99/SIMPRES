package com.simpres.app.ui.leave

import android.app.DatePickerDialog
import android.app.TimePickerDialog
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.TextView
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import com.simpres.app.R
import com.simpres.app.data.ApiResult
import com.simpres.app.data.Format
import com.simpres.app.data.Leave
import com.simpres.app.data.call
import com.simpres.app.ui.BaseFragment
import com.simpres.app.ui.addMessage
import com.simpres.app.ui.addRow
import com.simpres.app.ui.childContaining
import com.simpres.app.ui.clearAfter
import com.simpres.app.ui.onClick
import com.simpres.app.ui.toneForStatus
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.ByteArrayOutputStream
import java.time.LocalDate

/** Izin & Cuti: list of own requests (filtered by status), detail with cancel while still waiting. */
class LeaveListFragment : BaseFragment(R.layout.fragment_leave_list) {
    private var status: String? = null
    private lateinit var list: ViewGroup
    private lateinit var anchor: View

    override fun onReady(view: View) {
        // The list column is the parent of the sample items from the design; keep everything up to the tabs.
        list = view.findViewById<View>(R.id.itemAccepted).parent as ViewGroup
        anchor = list.childContaining(view.findViewById(R.id.tabAll))
        val statuses = mapOf(R.id.tabAll to null, R.id.tabPending to "menunggu",
            R.id.tabAccepted to "diterima", R.id.tabRejected to "ditolak")
        singleChoice(view, R.id.tabAll, R.id.tabPending, R.id.tabAccepted, R.id.tabRejected) {
            status = statuses[it]; load(view)
        }
        view.onClick(R.id.btnNew) { go(R.id.leaveFormFragment) }
        load(view)
    }

    private fun load(view: View) = launch {
        list.clearAfter(anchor)
        val loading = list.addMessage(getString(R.string.msg_loading))
        val r = call { leaves(status) }
        list.removeView(loading)
        when (r) {
            is ApiResult.Ok -> {
                if (r.value.data.isEmpty()) list.addMessage(getString(R.string.msg_empty))
                r.value.data.forEach { l ->
                    val dates = if (l.startDate == l.endDate) Format.day(l.startDate)
                    else "${Format.day(l.startDate)} – ${Format.day(l.endDate)}"
                    list.addRow(R.drawable.ic_file, Format.leaveType(l.type), dates,
                        Format.submissionStatus(l.status), toneForStatus(l.status)) { showDetail(view, l) }
                }
            }
            is ApiResult.Failed -> list.addMessage(r.message)
            ApiResult.Offline -> list.addMessage(getString(R.string.msg_offline_list))
        }
    }

    private fun showDetail(view: View, l: Leave) {
        val time = if (l.startTime != null) "\nJam: ${Format.clock(l.startTime)}–${Format.clock(l.endTime)}" else ""
        val note = l.adminNote?.let { "\nCatatan admin: $it" } ?: ""
        val builder = AlertDialog.Builder(requireContext())
            .setTitle(Format.leaveType(l.type))
            .setMessage("Tanggal: ${Format.day(l.startDate)} s.d. ${Format.day(l.endDate)}$time" +
                "\nAlasan: ${l.reason}\nStatus: ${Format.submissionStatus(l.status)}$note")
            .setPositiveButton(R.string.leave_close, null)
        if (l.status == "menunggu") {
            builder.setNegativeButton(R.string.leave_cancel) { _, _ ->
                launch {
                    when (val r = call { cancelLeave(l.id) }) {
                        is ApiResult.Ok -> { toast(R.string.msg_cancelled); load(view) }
                        is ApiResult.Failed -> alert(r.message)
                        ApiResult.Offline -> alert(getString(R.string.msg_server_unreachable))
                    }
                }
            }
        }
        builder.show()
    }
}

/**
 * Ajukan izin. "Keluar jam kerja" and "Lainnya" use one date (+ hours for izin keluar);
 * "Sakit" and "Cuti" use a date range. Sakit needs a photo of the doctor's letter, for cuti a file is optional.
 */
class LeaveFormFragment : BaseFragment(R.layout.fragment_leave_form) {
    private var type = "izin_keluar"
    private var attachment: ByteArray? = null
    private val picker = registerForActivityResult(ActivityResultContracts.GetContent()) { uri -> uri?.let(::useImage) }

    override fun onReady(view: View) {
        val groupOut = view.findViewById<View>(R.id.groupOut)
        val groupSick = view.findViewById<View>(R.id.groupSick)
        singleChoice(view, R.id.chipOut, R.id.chipSick, R.id.chipLeave, R.id.chipOther) { selected ->
            type = when (selected) {
                R.id.chipSick -> "sakit"; R.id.chipLeave -> "cuti"; R.id.chipOther -> "lainnya"; else -> "izin_keluar"
            }
            val range = type == "sakit" || type == "cuti"
            groupSick.visibility = if (range) View.VISIBLE else View.GONE
            groupOut.visibility = if (range) View.GONE else View.VISIBLE
            // The date-range group is shared: cuti shows "Alasan" and an optional attachment.
            val sick = type == "sakit"
            view.findViewById<TextView>(R.id.tvComplaintLabel)
                .setText(if (sick) R.string.leave_form_keluhan_alasan else R.string.common_alasan)
            view.findViewById<TextView>(R.id.tvFileLabel)
                .setText(if (sick) R.string.leave_form_surat_keterangan_dokter else R.string.leave_form_lampiran)
            view.findViewById<TextView>(R.id.tvFileNote)
                .setText(if (sick) R.string.leave_form_wajib_jika_1_hari else R.string.common_opsional)
            view.findViewById<TextView>(R.id.tvSickInfo)
                .setText(if (sick) R.string.leave_form_izin_sakit_lebih_dari else R.string.leave_form_cuti_info)
        }

        val today = LocalDate.now().toString()
        listOf(R.id.etDate, R.id.etStart, R.id.etEnd).forEach { datePicker(view.findViewById(it), today) }
        datePicker(view.findViewById(R.id.etEnd), LocalDate.now().plusDays(1).toString())
        timePicker(view.findViewById(R.id.etFrom), "10:00")
        timePicker(view.findViewById(R.id.etTo), "11:30")
        view.findViewById<EditText>(R.id.etReason).setText("")
        view.findViewById<EditText>(R.id.etComplaint).setText("")

        // Sick-leave attachment: tap the file row to pick a photo; the X clears it.
        val fileRow = view.findViewById<View>(R.id.btnRemoveFile).parent as View
        showFile(view)
        fileRow.setOnClickListener { picker.launch("image/*") }
        view.onClick(R.id.btnRemoveFile) { attachment = null; showFile(view) }

        view.onClick(R.id.btnSubmit) { submit(view) }
    }

    private fun datePicker(field: EditText, value: String) {
        field.isFocusable = false
        field.setText(value)
        field.setOnClickListener {
            val d = LocalDate.parse(field.text.toString())
            DatePickerDialog(requireContext(), { _, y, m, day ->
                field.setText(LocalDate.of(y, m + 1, day).toString())
            }, d.year, d.monthValue - 1, d.dayOfMonth).show()
        }
    }

    private fun timePicker(field: EditText, value: String) {
        field.isFocusable = false
        field.setText(value)
        field.setOnClickListener {
            val (h, m) = field.text.split(":").map { it.toInt() }
            TimePickerDialog(requireContext(), { _, hh, mm -> field.setText("%02d:%02d".format(hh, mm)) }, h, m, true).show()
        }
    }

    /** Re-encodes the picked image as JPEG under 2 MB (server limit). */
    private fun useImage(uri: Uri) {
        val bmp = requireContext().contentResolver.openInputStream(uri)?.use(BitmapFactory::decodeStream) ?: return
        val scale = minOf(1f, 1600f / maxOf(bmp.width, bmp.height))
        val small = Bitmap.createScaledBitmap(bmp, (bmp.width * scale).toInt(), (bmp.height * scale).toInt(), true)
        attachment = ByteArrayOutputStream().also { small.compress(Bitmap.CompressFormat.JPEG, 80, it) }.toByteArray()
        view?.let(::showFile)
    }

    private fun showFile(view: View) {
        val name = view.findViewById<TextView>(R.id.tvFileName)
        val size = view.findViewById<TextView>(R.id.tvFileSize)
        val data = attachment
        if (data == null) {
            name.setText(R.string.pick_file_hint)
            size.text = ""
            view.findViewById<View>(R.id.btnRemoveFile).visibility = View.GONE
        } else {
            name.text = "surat-keterangan.jpg"
            size.text = getString(R.string.file_size, data.size / 1024)
            view.findViewById<View>(R.id.btnRemoveFile).visibility = View.VISIBLE
        }
    }

    private fun text(view: View, id: Int) = view.findViewById<EditText>(id).text.toString().trim()

    private fun submit(view: View) {
        val fields = if (type == "sakit" || type == "cuti") {
            mapOf("type" to type, "start_date" to text(view, R.id.etStart), "end_date" to text(view, R.id.etEnd),
                "reason" to text(view, R.id.etComplaint))
        } else {
            val date = text(view, R.id.etDate)
            mutableMapOf("type" to type, "start_date" to date, "end_date" to date, "reason" to text(view, R.id.etReason))
                .apply { if (type == "izin_keluar") { put("start_time", text(view, R.id.etFrom)); put("end_time", text(view, R.id.etTo)) } }
        }
        if (fields["reason"].isNullOrBlank()) return toast(R.string.msg_fill_form)
        if (type == "sakit" && attachment == null) return toast(R.string.msg_pick_photo)

        launch {
            val file = attachment
            val r = if (file != null && (type == "sakit" || type == "cuti")) {
                val plain = "text/plain".toMediaType()
                val parts = fields.mapValues { it.value.toRequestBody(plain) }
                val file = MultipartBody.Part.createFormData("lampiran", "surat.jpg",
                    file.toRequestBody("image/jpeg".toMediaType()))
                call { createLeaveWithFile(parts, file) }
            } else {
                call { createLeave(fields) }
            }
            when (r) {
                is ApiResult.Ok -> {
                    LeaveSentFragment.last = r.value.data
                    replace(R.id.leaveSentFragment)
                }
                is ApiResult.Failed -> alert(errorText(r))
                ApiResult.Offline -> alert(getString(R.string.msg_server_unreachable))
            }
        }
    }

    /** All validation messages from a 422, one per line. */
    private fun errorText(r: ApiResult.Failed): String {
        val errors = r.body?.getAsJsonObject("errors") ?: return r.message
        return errors.entrySet().flatMap { e -> e.value.asJsonArray.map { it.asString } }.joinToString("\n")
    }
}

class LeaveSentFragment : BaseFragment(R.layout.fragment_leave_sent) {
    companion object {
        /** The leave that was just sent, shown on this screen instead of the sample text. */
        var last: Leave? = null
    }

    override fun onReady(view: View) {
        last?.let { l ->
            view.findViewById<TextView>(R.id.tvSentType).text = Format.leaveType(l.type)
            view.findViewById<TextView>(R.id.tvSentDate).text =
                if (l.startDate == l.endDate) Format.day(l.startDate) else "${Format.day(l.startDate)} – ${Format.day(l.endDate)}"
            view.findViewById<TextView>(R.id.tvSentTime).text =
                if (l.startTime != null) "${Format.clock(l.startTime)}–${Format.clock(l.endTime)}" else getString(R.string.leave_all_day)
            view.findViewById<TextView>(R.id.tvSentAt).text = "${Format.day(l.createdAt.take(10))} · ${Format.time(l.createdAt)}"
        }
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
