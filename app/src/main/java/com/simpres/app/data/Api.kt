package com.simpres.app.data

import com.google.gson.Gson
import com.google.gson.JsonObject
import com.google.gson.annotations.SerializedName
import com.simpres.app.BuildConfig
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.RequestBody
import okhttp3.ResponseBody
import retrofit2.HttpException
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.PartMap
import retrofit2.http.Path
import retrofit2.http.Query
import java.io.IOException
import java.util.concurrent.TimeUnit

// ---------------------------------------------------------------------------------------------
// Data models (field names follow backend/API.md)
// ---------------------------------------------------------------------------------------------

data class WorkLocation(
    val id: Long,
    val name: String,
    val address: String?,
    val latitude: Double,
    val longitude: Double,
    @SerializedName("radius_m") val radiusM: Int,
)

data class User(
    val id: Long,
    val nip: String,
    val name: String,
    val email: String,
    val phone: String?,
    val position: String?,
    val unit: String?,
    val role: String,
    @SerializedName("work_location") val workLocation: WorkLocation?,
)

data class LoginResponse(val message: String, val token: String, val user: User)
data class UserResponse(val user: User)
/** Reply of forgot-password: [email] is masked, for example si***@gmail.com. */
data class ForgotResponse(val message: String, val email: String)

data class Attendance(
    val id: Long,
    val date: String,
    val status: String,
    @SerializedName("late_minutes") val lateMinutes: Int,
    @SerializedName("check_in_at") val checkInAt: String?,
    @SerializedName("check_in_distance_m") val checkInDistanceM: Double?,
    @SerializedName("check_in_accuracy_m") val checkInAccuracyM: Double?,
    @SerializedName("check_out_at") val checkOutAt: String?,
    @SerializedName("check_out_distance_m") val checkOutDistanceM: Double?,
    val source: String?,
    /** Lama kerja (menit), null jika belum check-out. */
    @SerializedName("work_minutes") val workMinutes: Int? = null,
    /** Menit pulang lebih cepat dari jam pulang shift (0 jika tidak). */
    @SerializedName("early_leave_minutes") val earlyLeaveMinutes: Int = 0,
)

data class TodaySchedule(
    @SerializedName("is_day_off") val isDayOff: Boolean,
    val shift: String?,
    @SerializedName("start_time") val startTime: String?,
    @SerializedName("end_time") val endTime: String?,
)

data class Today(
    val date: String,
    val schedule: TodaySchedule?,
    @SerializedName("has_checked_in") val hasCheckedIn: Boolean,
    @SerializedName("has_checked_out") val hasCheckedOut: Boolean,
    val attendance: Attendance?,
)

data class Shift(
    val id: Long,
    val name: String,
    @SerializedName("start_time") val startTime: String,
    @SerializedName("end_time") val endTime: String,
)

data class Schedule(
    val id: Long?,
    val date: String,
    @SerializedName("is_day_off") val isDayOff: Boolean,
    val shift: Shift?,
    /** true = dari shift tetap pegawai, bukan diatur khusus di roster. */
    @SerializedName("is_default") val isDefault: Boolean = false,
    /** Nama hari libur nasional (Konfigurasi admin), null jika bukan. */
    val holiday: String? = null,
)

data class Leave(
    val id: Long,
    val type: String,
    @SerializedName("start_date") val startDate: String,
    @SerializedName("end_date") val endDate: String,
    @SerializedName("start_time") val startTime: String?,
    @SerializedName("end_time") val endTime: String?,
    val reason: String,
    @SerializedName("has_attachment") val hasAttachment: Boolean,
    val status: String,
    @SerializedName("admin_note") val adminNote: String?,
    @SerializedName("created_at") val createdAt: String,
)

data class AppNotification(
    val id: Long,
    val type: String,
    val title: String,
    val body: String,
    @SerializedName("is_read") val isRead: Boolean,
    @SerializedName("created_at") val createdAt: String,
)

data class FieldDutyItem(
    val id: Long,
    val date: String,
    @SerializedName("location_name") val locationName: String,
    val status: String,
)

data class DataResponse<T>(val message: String?, val data: T)
data class ListResponse<T>(val data: List<T>, @SerializedName("unread_count") val unreadCount: Int?)

data class LocationBody(
    val latitude: Double,
    val longitude: Double,
    val accuracy: Double,
    @SerializedName("is_mock") val isMock: Boolean,
    @SerializedName("client_uuid") val clientUuid: String?,
    @SerializedName("device_info") val deviceInfo: String?,
    /** true jika aplikasi berjalan di emulator (server menolak jika diatur di Konfigurasi). */
    @SerializedName("is_emulator") val isEmulator: Boolean = false,
    /** "offline" when sent later from the outbox; null means a normal online check-in. */
    val source: String? = null,
)

// ---------------------------------------------------------------------------------------------
// Endpoints
// ---------------------------------------------------------------------------------------------

interface SimpresApi {
    @POST("login") suspend fun login(@Body body: Map<String, String>): LoginResponse
    @POST("forgot-password") suspend fun forgotPassword(@Body body: Map<String, String>): ForgotResponse
    @POST("logout") suspend fun logout(): JsonObject
    @GET("me") suspend fun me(): UserResponse

    @GET("attendance/today") suspend fun today(): DataResponse<Today>
    @POST("attendance/check-in") suspend fun checkIn(@Body body: LocationBody): DataResponse<Attendance>
    @POST("attendance/check-out") suspend fun checkOut(@Body body: LocationBody): DataResponse<Attendance>
    @GET("attendance") suspend fun history(@Query("month") month: String): ListResponse<Attendance>

    @GET("schedules") suspend fun schedules(@Query("month") month: String): ListResponse<Schedule>

    @GET("leaves") suspend fun leaves(@Query("status") status: String?): ListResponse<Leave>
    @POST("leaves") suspend fun createLeave(@Body body: Map<String, String>): DataResponse<Leave>
    @Multipart @POST("leaves") suspend fun createLeaveWithFile(
        @PartMap fields: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part file: MultipartBody.Part,
    ): DataResponse<Leave>
    @DELETE("leaves/{id}") suspend fun cancelLeave(@Path("id") id: Long): JsonObject

    @POST("corrections") suspend fun createCorrection(@Body body: Map<String, String>): JsonObject
    @Multipart @POST("corrections") suspend fun createCorrectionWithFile(
        @PartMap fields: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part file: MultipartBody.Part,
    ): JsonObject

    @GET("field-duties") suspend fun fieldDuties(): ListResponse<FieldDutyItem>
    @Multipart @POST("field-duties") suspend fun createFieldDuty(
        @PartMap fields: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part photo: MultipartBody.Part,
    ): JsonObject

    @GET("notifications") suspend fun notifications(): ListResponse<AppNotification>
    @POST("notifications/read-all") suspend fun readAllNotifications(): JsonObject
    @POST("device-token") suspend fun saveDeviceToken(@Body body: Map<String, String>): JsonObject
}

object ApiClient {
    val api: SimpresApi by lazy {
        val http = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                val request = chain.request().newBuilder().header("Accept", "application/json")
                Session.token?.let { request.header("Authorization", "Bearer $it") }
                val response = chain.proceed(request.build())
                if (response.code == 401) Session.clear() // token expired or logged out elsewhere
                response
            }
            .build()
        Retrofit.Builder()
            .baseUrl(BuildConfig.API_URL)
            .client(http)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(SimpresApi::class.java)
    }
}

// ---------------------------------------------------------------------------------------------
// Result handling: one place that turns exceptions into a message the screen can show.
// ---------------------------------------------------------------------------------------------

sealed class ApiResult<out T> {
    data class Ok<T>(val value: T) : ApiResult<T>()
    /** Server answered with an error (401/403/404/422). [body] is the parsed JSON, if any. */
    data class Failed(val code: Int, val message: String, val body: JsonObject?) : ApiResult<Nothing>()
    /** No connection or server unreachable. */
    data object Offline : ApiResult<Nothing>()
}

suspend fun <T> call(block: suspend SimpresApi.() -> T): ApiResult<T> = try {
    ApiResult.Ok(ApiClient.api.block())
} catch (e: HttpException) {
    val body = e.response()?.errorBody()?.let(::parseJson)
    val message = body?.get("message")?.asString ?: "Terjadi kesalahan (${e.code()})."
    ApiResult.Failed(e.code(), message, body)
} catch (e: IOException) {
    ApiResult.Offline
}

private fun parseJson(body: ResponseBody): JsonObject? = try {
    Gson().fromJson(body.string(), JsonObject::class.java)
} catch (e: Exception) {
    null
}
