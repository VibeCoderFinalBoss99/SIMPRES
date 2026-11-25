import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

// Firebase (notifikasi seketika) aktif jika app/google-services.json ada. File itu tidak di-upload ke repo,
// jadi tanpa file itu proyek tetap bisa di-build; notifikasi memakai pengecekan berkala (WorkManager).
if (file("google-services.json").exists()) apply(plugin = "com.google.gms.google-services")

// API address. Emulator default: 10.0.2.2 is the laptop running `php artisan serve`.
// For a real phone or the hosting server, set simpres.apiUrl in local.properties,
// for example: simpres.apiUrl=https://api.namadomain.com/api/
val localProps = Properties().apply {
    val f = rootProject.file("local.properties")
    if (f.exists()) f.inputStream().use { load(it) }
}
val apiUrl = localProps.getProperty("simpres.apiUrl") ?: "http://10.0.2.2:8000/api/"
// The release APK talks to the public server (tunnel or hosting) and is signed with the release keystore.
// Both are set only in local.properties, so the keystore password never goes to GitHub.
val releaseApiUrl = localProps.getProperty("simpres.releaseApiUrl") ?: apiUrl

android {
    namespace = "com.simpres.app"
    compileSdk = 34

    defaultConfig {
        applicationId = "com.simpres.app"
        minSdk = 26
        targetSdk = 34
        versionCode = 5
        versionName = "0.3.1"
        buildConfigField("String", "API_URL", "\"$apiUrl\"")
    }

    buildFeatures {
        buildConfig = true
    }

    signingConfigs {
        create("release") {
            localProps.getProperty("simpres.storeFile")?.let { storeFile = file(it) }
            storePassword = localProps.getProperty("simpres.storePassword")
            keyAlias = localProps.getProperty("simpres.keyAlias")
            keyPassword = localProps.getProperty("simpres.storePassword")
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            buildConfigField("String", "API_URL", "\"$releaseApiUrl\"")
            if (localProps.getProperty("simpres.storeFile") != null) signingConfig = signingConfigs.getByName("release")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions {
        jvmTarget = "17"
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.fragment:fragment-ktx:1.8.2")
    implementation("androidx.navigation:navigation-fragment-ktx:2.7.7")
    implementation("androidx.viewpager2:viewpager2:1.1.0")
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.8.4")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.8.1")
    implementation("com.airbnb.android:lottie:6.4.1")
    implementation("com.squareup.retrofit2:retrofit:2.11.0")
    implementation("com.squareup.retrofit2:converter-gson:2.11.0")
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("com.google.android.gms:play-services-location:21.3.0")
    // Sinkronisasi antrean offline dan notifikasi keputusan admin di latar belakang.
    implementation("androidx.work:work-runtime-ktx:2.9.1")
    // Notifikasi seketika dari server (Firebase Cloud Messaging).
    implementation(platform("com.google.firebase:firebase-bom:33.4.0"))
    implementation("com.google.firebase:firebase-messaging")
}
