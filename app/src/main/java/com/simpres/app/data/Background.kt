package com.simpres.app.data

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import com.simpres.app.MainActivity
import com.simpres.app.R
import java.util.concurrent.TimeUnit

/**
 * Pekerjaan latar belakang (WorkManager), tetap jalan walaupun aplikasi ditutup:
 * 1. mengirim antrean absensi offline begitu internet tersambung,
 * 2. mengecek keputusan admin (izin, koreksi, tugas lapangan) lalu menampilkannya sebagai notifikasi HP.
 */
class SyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        Session.init(applicationContext)
        Outbox.init(applicationContext)
        if (!Session.isLoggedIn) return Result.success()
        if (Outbox.all().isNotEmpty()) Outbox.flush()
        Notifier.checkNew(applicationContext)
        return Result.success()
    }
}

object Background {
    private const val PERIODIC = "simpres_sync_berkala"
    private const val NOW = "simpres_sync_sekarang"
    private val online = Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()

    /** Cek berkala setiap 15 menit (batas terkecil Android) selama pegawai login. */
    fun schedule(context: Context) {
        val work = PeriodicWorkRequestBuilder<SyncWorker>(15, TimeUnit.MINUTES).setConstraints(online).build()
        WorkManager.getInstance(context).enqueueUniquePeriodicWork(PERIODIC, ExistingPeriodicWorkPolicy.KEEP, work)
    }

    /** Sekali jalan, menunggu internet tersambung. Dipakai saat ada antrean offline dan saat aplikasi ditutup. */
    fun runSoon(context: Context, delaySeconds: Long = 0) {
        val work = OneTimeWorkRequestBuilder<SyncWorker>()
            .setConstraints(online)
            .setInitialDelay(delaySeconds, TimeUnit.SECONDS)
            .build()
        WorkManager.getInstance(context).enqueueUniqueWork(NOW, ExistingWorkPolicy.REPLACE, work)
    }

    fun cancel(context: Context) {
        WorkManager.getInstance(context).cancelUniqueWork(PERIODIC)
        WorkManager.getInstance(context).cancelUniqueWork(NOW)
    }
}

object Notifier {
    const val CHANNEL = "keputusan_admin"
    const val EXTRA_OPEN = "buka_notifikasi"

    fun createChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val channel = NotificationChannel(CHANNEL, context.getString(R.string.notif_channel), NotificationManager.IMPORTANCE_HIGH)
        channel.description = context.getString(R.string.notif_channel_desc)
        context.getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
    }

    /**
     * Ambil notifikasi dari server, tampilkan yang baru (id lebih besar dari yang terakhir ditampilkan).
     * Saat pertama kali jalan, notifikasi lama hanya dicatat supaya HP tidak dibanjiri notifikasi lama.
     */
    suspend fun checkNew(context: Context) {
        val result = call { notifications() }
        if (result !is ApiResult.Ok) return
        val prefs = context.getSharedPreferences("simpres_notifier", Context.MODE_PRIVATE)
        val key = "terakhir_${Session.user?.id}"
        val last = prefs.getLong(key, -1L)
        val items = result.value.data
        val newest = items.maxOfOrNull { it.id } ?: 0L
        if (last >= 0) {
            items.filter { it.id > last && !it.isRead }.sortedBy { it.id }.forEach { show(context, it) }
        }
        prefs.edit().putLong(key, maxOf(last, newest)).apply()
    }

    private fun show(context: Context, n: AppNotification) {
        val allowed = Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
        if (!allowed) return
        createChannel(context)
        val open = Intent(context, MainActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
            .putExtra(EXTRA_OPEN, true)
        val tap = PendingIntent.getActivity(context, n.id.toInt(), open,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val notification = NotificationCompat.Builder(context, CHANNEL)
            .setSmallIcon(R.drawable.ic_bell)
            .setColor(ContextCompat.getColor(context, R.color.primary))
            .setContentTitle(n.title)
            .setContentText(n.body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(n.body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(tap)
            .setAutoCancel(true)
            .build()
        NotificationManagerCompat.from(context).notify(n.id.toInt(), notification)
    }
}
