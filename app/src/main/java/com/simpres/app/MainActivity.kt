package com.simpres.app

import android.content.Intent
import android.content.res.Configuration
import android.os.Bundle
import androidx.core.view.WindowCompat
import androidx.appcompat.app.AppCompatActivity
import androidx.navigation.fragment.NavHostFragment
import com.simpres.app.data.Background
import com.simpres.app.data.Notifier
import com.simpres.app.data.Outbox
import com.simpres.app.data.Session
import com.simpres.app.ui.ThemeMode

/** Single activity; every screen is a Fragment inside the navigation graph (res/navigation/nav_graph.xml). */
class MainActivity : AppCompatActivity(R.layout.activity_main) {
    override fun onCreate(savedInstanceState: Bundle?) {
        Session.init(applicationContext)
        Outbox.init(applicationContext)
        ThemeMode.apply(applicationContext)
        super.onCreate(savedInstanceState)
        // Ikon status bar gelap di mode terang, terang di mode gelap (mengikuti pilihan tema di aplikasi).
        val night = resources.configuration.uiMode and Configuration.UI_MODE_NIGHT_MASK == Configuration.UI_MODE_NIGHT_YES
        WindowCompat.getInsetsController(window, window.decorView).apply {
            isAppearanceLightStatusBars = !night
            isAppearanceLightNavigationBars = !night
        }
        Notifier.createChannel(this)
        if (Session.isLoggedIn) Background.schedule(this)
        openNotificationsIfAsked(intent)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        openNotificationsIfAsked(intent)
    }

    // Saat aplikasi ditutup/diminimalkan, cek keputusan admin satu menit kemudian (lalu tetap tiap 15 menit).
    override fun onStop() {
        super.onStop()
        if (Session.isLoggedIn) Background.runSoon(this, 60)
    }

    /** Notifikasi HP diketuk: langsung buka halaman Notifikasi. */
    private fun openNotificationsIfAsked(intent: Intent?) {
        if (intent?.getBooleanExtra(Notifier.EXTRA_OPEN, false) != true || !Session.isLoggedIn) return
        intent.removeExtra(Notifier.EXTRA_OPEN)
        val host = supportFragmentManager.findFragmentById(R.id.navHost) as? NavHostFragment ?: return
        host.navController.navigate(R.id.notificationFragment)
    }
}
