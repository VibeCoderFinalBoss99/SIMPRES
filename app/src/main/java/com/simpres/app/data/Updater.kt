package com.simpres.app.data

import android.app.DownloadManager
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Environment
import androidx.core.content.FileProvider
import com.google.gson.Gson
import com.google.gson.JsonObject
import com.simpres.app.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.File
import java.util.concurrent.TimeUnit

/**
 * Pembaruan aplikasi dari rilis GitHub terbaru (Latest). Jika versinya lebih baru dari versi terpasang,
 * APK diunduh dengan DownloadManager lalu pemasang Android dibuka. Pengguna tetap menekan "Pasang".
 */
object Updater {
    private const val LATEST = "https://api.github.com/repos/VibeCoderFinalBoss99/SIMPRES/releases/latest"
    const val APK_MIME = "application/vnd.android.package-archive"

    data class Release(val version: String, val notes: String, val apkUrl: String)

    private val client = OkHttpClient.Builder().callTimeout(15, TimeUnit.SECONDS).build()

    /** Rilis terbaru, atau null jika tidak ada internet / tidak ada file APK di rilis. */
    suspend fun latest(): Release? = withContext(Dispatchers.IO) {
        runCatching {
            val request = Request.Builder().url(LATEST)
                .header("Accept", "application/vnd.github+json")
                .header("User-Agent", "SIMPRES-Android")
                .build()
            client.newCall(request).execute().use { res ->
                if (!res.isSuccessful) return@use null
                val json = Gson().fromJson(res.body?.string(), JsonObject::class.java)
                val apk = json.getAsJsonArray("assets")
                    .map { it.asJsonObject }
                    .firstOrNull { it.get("name").asString.endsWith(".apk") } ?: return@use null
                Release(
                    version = json.get("tag_name").asString.removePrefix("v"),
                    notes = json.get("body")?.takeIf { !it.isJsonNull }?.asString.orEmpty(),
                    apkUrl = apk.get("browser_download_url").asString,
                )
            }
        }.getOrNull()
    }

    /** true jika [remote] (misalnya 0.3.2) lebih baru dari versi aplikasi yang terpasang. */
    fun isNewer(remote: String, local: String = BuildConfig.VERSION_NAME): Boolean {
        val r = remote.split(".").map { it.filter(Char::isDigit).toIntOrNull() ?: 0 }
        val l = local.split(".").map { it.filter(Char::isDigit).toIntOrNull() ?: 0 }
        for (i in 0 until maxOf(r.size, l.size)) {
            val a = r.getOrElse(i) { 0 }
            val b = l.getOrElse(i) { 0 }
            if (a != b) return a > b
        }
        return false
    }

    private fun file(context: Context, version: String) =
        File(context.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), "SIMPRES-$version.apk")

    /** Mulai mengunduh APK (tampil juga di notifikasi HP). Mengembalikan id unduhan. */
    fun download(context: Context, release: Release): Long {
        val target = file(context, release.version)
        target.delete() // sisa unduhan yang gagal
        val request = DownloadManager.Request(Uri.parse(release.apkUrl))
            .setTitle("SIMPRES ${release.version}")
            .setDescription("Mengunduh pembaruan aplikasi")
            .setMimeType(APK_MIME)
            .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            .setDestinationUri(Uri.fromFile(target))
        return context.getSystemService(DownloadManager::class.java).enqueue(request)
    }

    /** Status unduhan: persen (0-100), -1 jika gagal, 100 jika selesai. */
    fun progress(context: Context, id: Long): Int {
        val manager = context.getSystemService(DownloadManager::class.java)
        manager.query(DownloadManager.Query().setFilterById(id)).use { c ->
            if (!c.moveToFirst()) return -1
            return when (c.getInt(c.getColumnIndexOrThrow(DownloadManager.COLUMN_STATUS))) {
                DownloadManager.STATUS_SUCCESSFUL -> 100
                DownloadManager.STATUS_FAILED -> -1
                else -> {
                    val total = c.getLong(c.getColumnIndexOrThrow(DownloadManager.COLUMN_TOTAL_SIZE_BYTES))
                    val done = c.getLong(c.getColumnIndexOrThrow(DownloadManager.COLUMN_BYTES_DOWNLOADED_SO_FAR))
                    if (total > 0) (done * 99 / total).toInt() else 0
                }
            }
        }
    }

    /** Membuka pemasang Android untuk APK yang sudah diunduh. */
    fun install(context: Context, version: String) {
        val uri = FileProvider.getUriForFile(context, "${context.packageName}.files", file(context, version))
        context.startActivity(Intent(Intent.ACTION_VIEW)
            .setDataAndType(uri, APK_MIME)
            .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK))
    }
}
