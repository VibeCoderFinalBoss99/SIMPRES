package com.simpres.app.ui

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import java.io.ByteArrayOutputStream

/** Foto lampiran diperkecil (sisi terpanjang 1600 px) lalu disimpan sebagai JPEG, supaya di bawah batas 2 MB server. */
fun shrinkImage(context: Context, uri: Uri): ByteArray? {
    val bmp = context.contentResolver.openInputStream(uri)?.use(BitmapFactory::decodeStream) ?: return null
    val scale = minOf(1f, 1600f / maxOf(bmp.width, bmp.height))
    val small = Bitmap.createScaledBitmap(bmp, (bmp.width * scale).toInt(), (bmp.height * scale).toInt(), true)
    return ByteArrayOutputStream().also { small.compress(Bitmap.CompressFormat.JPEG, 80, it) }.toByteArray()
}
