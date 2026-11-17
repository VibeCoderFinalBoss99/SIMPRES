package com.simpres.app.data

import android.content.Context
import android.content.SharedPreferences
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import java.util.UUID

/** Login token and the signed-in employee, kept in private SharedPreferences. */
object Session {
    private lateinit var prefs: SharedPreferences
    private val gson = Gson()

    fun init(context: Context) {
        prefs = context.getSharedPreferences("simpres_session", Context.MODE_PRIVATE)
    }

    val token: String? get() = prefs.getString("token", null)
    val isLoggedIn: Boolean get() = token != null

    var user: User?
        get() = prefs.getString("user", null)?.let { gson.fromJson(it, User::class.java) }
        set(value) = prefs.edit().putString("user", value?.let(gson::toJson)).apply()

    fun save(token: String, user: User) {
        prefs.edit().putString("token", token).putString("user", gson.toJson(user)).apply()
    }

    fun clear() = prefs.edit().remove("token").remove("user").apply()
}

/** Attendance that could not be sent because there was no connection. Sent again later. */
data class PendingAttendance(val kind: String, val body: LocationBody, val savedAt: String)

object Outbox {
    private lateinit var prefs: SharedPreferences
    private val gson = Gson()
    private val listType = object : TypeToken<List<PendingAttendance>>() {}.type

    fun init(context: Context) {
        prefs = context.getSharedPreferences("simpres_outbox", Context.MODE_PRIVATE)
    }

    fun all(): List<PendingAttendance> =
        prefs.getString("items", null)?.let { gson.fromJson<List<PendingAttendance>>(it, listType) } ?: emptyList()

    fun add(item: PendingAttendance) = save(all() + item)

    fun remove(item: PendingAttendance) = save(all().filterNot { it.body.clientUuid == item.body.clientUuid })

    var lastSync: String?
        get() = prefs.getString("last_sync", null)
        set(value) = prefs.edit().putString("last_sync", value).apply()

    private fun save(items: List<PendingAttendance>) = prefs.edit().putString("items", gson.toJson(items)).apply()

    fun newUuid(): String = UUID.randomUUID().toString()

    /**
     * Sends every queued item. Items the server rejects (422, e.g. already checked in) are dropped too,
     * because sending them again would give the same answer. Returns how many were sent or rejected.
     */
    suspend fun flush(): Int {
        var done = 0
        for (item in all()) {
            val result = call { if (item.kind == "check-out") checkOut(item.body) else checkIn(item.body) }
            when (result) {
                is ApiResult.Ok, is ApiResult.Failed -> { remove(item); done++ }
                ApiResult.Offline -> return done
            }
        }
        if (done > 0) lastSync = Format.nowTime()
        return done
    }
}
