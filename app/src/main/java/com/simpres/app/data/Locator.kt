package com.simpres.app.data

import android.annotation.SuppressLint
import android.content.Context
import android.location.Location
import android.os.Build
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import com.google.android.gms.tasks.CancellationTokenSource
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/** One fresh GPS fix for check-in/check-out. The caller must already hold the location permission. */
object Locator {
    @SuppressLint("MissingPermission")
    suspend fun current(context: Context): Location? = suspendCancellableCoroutine { cont ->
        val cancel = CancellationTokenSource()
        LocationServices.getFusedLocationProviderClient(context)
            .getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, cancel.token)
            .addOnSuccessListener { cont.resume(it) }
            .addOnFailureListener { cont.resume(null) }
        cont.invokeOnCancellation { cancel.cancel() }
    }

    fun isMock(location: Location): Boolean =
        if (Build.VERSION.SDK_INT >= 31) location.isMock else @Suppress("DEPRECATION") location.isFromMockProvider

    fun body(location: Location, uuid: String?) = LocationBody(
        latitude = location.latitude,
        longitude = location.longitude,
        accuracy = location.accuracy.toDouble(),
        isMock = isMock(location),
        clientUuid = uuid,
        deviceInfo = "${Build.MANUFACTURER} ${Build.MODEL}, Android ${Build.VERSION.RELEASE}".take(255),
    )
}
