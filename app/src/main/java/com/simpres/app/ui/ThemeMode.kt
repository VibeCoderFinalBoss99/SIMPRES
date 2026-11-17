package com.simpres.app.ui

import android.content.Context
import androidx.appcompat.app.AppCompatDelegate

/** Pilihan tampilan: ikuti sistem, terang, atau gelap. Disimpan di HP dan dipakai saat aplikasi dibuka. */
object ThemeMode {
    const val SYSTEM = "system"
    const val LIGHT = "light"
    const val DARK = "dark"
    private const val PREFS = "simpres_theme"

    val options = listOf(SYSTEM to "Ikuti sistem", LIGHT to "Terang", DARK to "Gelap")

    fun current(context: Context): String =
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString("mode", SYSTEM) ?: SYSTEM

    fun label(context: Context): String = options.first { it.first == current(context) }.second

    fun set(context: Context, mode: String) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().putString("mode", mode).apply()
        apply(context)
    }

    fun apply(context: Context) = AppCompatDelegate.setDefaultNightMode(
        when (current(context)) {
            LIGHT -> AppCompatDelegate.MODE_NIGHT_NO
            DARK -> AppCompatDelegate.MODE_NIGHT_YES
            else -> AppCompatDelegate.MODE_NIGHT_FOLLOW_SYSTEM
        }
    )
}
