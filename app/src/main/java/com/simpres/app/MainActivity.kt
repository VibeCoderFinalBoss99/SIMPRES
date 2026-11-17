package com.simpres.app

import android.content.res.Configuration
import android.os.Bundle
import androidx.core.view.WindowCompat
import androidx.appcompat.app.AppCompatActivity
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
    }
}
