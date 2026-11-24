package com.simpres.app.ui.attendance

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Canvas
import android.graphics.DashPathEffect
import android.graphics.Paint
import android.location.Location
import android.view.View
import android.widget.ImageView
import com.simpres.app.R
import com.simpres.app.data.WorkLocation
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.util.concurrent.TimeUnit
import kotlin.math.PI
import kotlin.math.cos
import kotlin.math.floor
import kotlin.math.ln
import kotlin.math.log2
import kotlin.math.max
import kotlin.math.min
import kotlin.math.tan

/**
 * Peta sederhana dari tile OpenStreetMap (tanpa API key dan tanpa library peta).
 * Tile di sekitar titik Puskesmas diunduh lalu digabung jadi satu gambar, kemudian
 * lingkaran radius, titik Puskesmas, dan posisi pegawai digambar di atasnya.
 */
object OsmMap {
    private const val TILE = 256
    private val client = OkHttpClient.Builder().callTimeout(15, TimeUnit.SECONDS).build()
    private val cache = HashMap<String, Bitmap>()

    /** Mengisi [image] di dalam kotak [box]. Jika gagal (tidak ada internet), gambar bawaan tetap tampil. */
    /** Satu piksel gambar peta = [scale] piksel layar (supaya tulisan peta tidak terlalu kecil). */
    fun scale(view: View) = max(1f, view.resources.displayMetrics.density / 1.5f)

    /**
     * [zoomDelta] menambah zoom otomatis. [panX]/[panY] menggeser pusat peta, dalam piksel gambar peta
     * pada zoom yang dipakai (hasil seret jari).
     */
    suspend fun show(
        box: View, image: ImageView, place: WorkLocation, me: Location?, color: Int,
        zoomDelta: Int = 0, panX: Double = 0.0, panY: Double = 0.0,
    ) {
        if (box.width == 0) return
        val scale = scale(box)
        val w = (box.width / scale).toInt()
        val h = (box.height / scale).toInt()
        val bmp = withContext(Dispatchers.IO) { runCatching { draw(w, h, place, me, color, zoomDelta, panX, panY) }.getOrNull() } ?: return
        image.setImageBitmap(bmp)
        image.visibility = View.VISIBLE
        // Lingkaran dan pin hiasan dari desain disembunyikan, sudah diganti gambar peta asli.
        box.findViewWithTag<View>("fence")?.visibility = View.GONE
        box.findViewWithTag<View>("drop")?.visibility = View.GONE
        box.findViewById<View>(R.id.tvMapCredit)?.visibility = View.VISIBLE
        box.findViewById<View>(R.id.mapZoom)?.visibility = View.VISIBLE
    }

    private fun draw(
        w: Int, h: Int, place: WorkLocation, me: Location?, color: Int, zoomDelta: Int, panX: Double, panY: Double,
    ): Bitmap? {
        val metersPerPx0 = 156543.03392 * cos(Math.toRadians(place.latitude))
        var span = place.radiusM * 2.6
        if (me != null) {
            val d = FloatArray(1)
            Location.distanceBetween(place.latitude, place.longitude, me.latitude, me.longitude, d)
            span = max(span, d[0] * 1.3 + place.radiusM)
        }
        // Zoom otomatis supaya radius (dan posisi pegawai) muat, lalu ditambah zoom dari tombol atau cubit.
        val zoom = (floor(log2(metersPerPx0 * min(w, h) / span)).toInt() + zoomDelta).coerceIn(3, 19)

        val p = pixel(place.latitude, place.longitude, zoom)
        val m = me?.let { pixel(it.latitude, it.longitude, zoom) }
        val cx = (if (m == null) p.first else (p.first + m.first) / 2) + panX
        val cy = (if (m == null) p.second else (p.second + m.second) / 2) + panY
        val left = cx - w / 2.0
        val top = cy - h / 2.0

        val out = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(out)
        for (tx in floor(left / TILE).toInt()..floor((left + w) / TILE).toInt()) {
            for (ty in floor(top / TILE).toInt()..floor((top + h) / TILE).toInt()) {
                val tile = tile(zoom, tx, ty) ?: return null
                canvas.drawBitmap(tile, (tx * TILE - left).toFloat(), (ty * TILE - top).toFloat(), null)
            }
        }

        val px = (p.first - left).toFloat()
        val py = (p.second - top).toFloat()
        val radiusPx = (place.radiusM / (metersPerPx0 / (1 shl zoom))).toFloat()
        val paint = Paint(Paint.ANTI_ALIAS_FLAG)
        paint.color = color; paint.alpha = 40
        canvas.drawCircle(px, py, radiusPx, paint)
        paint.style = Paint.Style.STROKE; paint.strokeWidth = 2.5f; paint.alpha = 255
        paint.pathEffect = DashPathEffect(floatArrayOf(8f, 6f), 0f)
        canvas.drawCircle(px, py, radiusPx, paint)
        paint.pathEffect = null
        dot(canvas, px, py, color)
        if (m != null) dot(canvas, (m.first - left).toFloat(), (m.second - top).toFloat(), 0xFF1A73E8.toInt())
        return out
    }

    private fun dot(canvas: Canvas, x: Float, y: Float, color: Int) {
        val paint = Paint(Paint.ANTI_ALIAS_FLAG)
        paint.color = 0xFFFFFFFF.toInt()
        canvas.drawCircle(x, y, 9f, paint)
        paint.color = color
        canvas.drawCircle(x, y, 6.5f, paint)
    }

    /** Posisi titik dalam piksel dunia (proyeksi Web Mercator) pada [zoom]. */
    private fun pixel(lat: Double, lng: Double, zoom: Int): Pair<Double, Double> {
        val size = TILE.toDouble() * (1 shl zoom)
        val r = Math.toRadians(lat)
        val x = (lng + 180) / 360 * size
        val y = (1 - ln(tan(r) + 1 / cos(r)) / PI) / 2 * size
        return x to y
    }

    private fun tile(zoom: Int, x: Int, y: Int): Bitmap? {
        val key = "$zoom/$x/$y"
        cache[key]?.let { return it }
        val request = Request.Builder()
            .url("https://tile.openstreetmap.org/$key.png")
            .header("User-Agent", "SIMPRES/1.0 (aplikasi presensi Puskesmas Simbang)")
            .build()
        val bmp = client.newCall(request).execute().use { res ->
            if (!res.isSuccessful) null else res.body?.byteStream()?.let(BitmapFactory::decodeStream)
        } ?: return null
        cache[key] = bmp
        return bmp
    }
}
