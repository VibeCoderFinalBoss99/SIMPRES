package com.simpres.app.data

import android.content.Context
import com.google.firebase.FirebaseApp
import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

/**
 * Notifikasi seketika lewat Firebase Cloud Messaging.
 * Saat admin memutuskan pengajuan, server mengirim pesan ke token HP pegawai; pesan itu ditampilkan oleh [Notifier].
 * Jika Firebase tidak tersedia, notifikasi tetap datang lewat pengecekan berkala ([SyncWorker]).
 */
object Push {
    private fun ready(context: Context) = FirebaseApp.getApps(context).isNotEmpty()

    /** Kirim token HP ini ke server supaya server tahu ke mana notifikasi dikirim. Dipanggil setelah login. */
    fun register(context: Context) {
        if (!ready(context)) return
        runCatching {
            FirebaseMessaging.getInstance().token.addOnSuccessListener { token -> sendToServer(token) }
        }
    }

    /** Saat keluar akun: token lama dihapus, jadi HP ini tidak menerima notifikasi akun tadi lagi. */
    fun unregister(context: Context) {
        if (!ready(context)) return
        runCatching { FirebaseMessaging.getInstance().deleteToken() }
    }

    fun sendToServer(token: String) {
        if (!Session.isLoggedIn) return
        CoroutineScope(Dispatchers.IO).launch {
            call { saveDeviceToken(mapOf("fcm_token" to token, "platform" to "android")) }
        }
    }
}

class PushService : FirebaseMessagingService() {
    override fun onNewToken(token: String) {
        Session.init(applicationContext)
        Push.sendToServer(token)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        Session.init(applicationContext)
        val data = message.data
        val id = data["id"]?.toLongOrNull() ?: return
        Notifier.showPushed(applicationContext, id, data["title"] ?: "SIMPRES", data["body"] ?: "")
    }
}
