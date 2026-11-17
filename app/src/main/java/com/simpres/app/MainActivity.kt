package com.simpres.app

import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import com.simpres.app.data.Outbox
import com.simpres.app.data.Session

/** Single activity; every screen is a Fragment inside the navigation graph (res/navigation/nav_graph.xml). */
class MainActivity : AppCompatActivity(R.layout.activity_main) {
    override fun onCreate(savedInstanceState: Bundle?) {
        Session.init(applicationContext)
        Outbox.init(applicationContext)
        super.onCreate(savedInstanceState)
    }
}
