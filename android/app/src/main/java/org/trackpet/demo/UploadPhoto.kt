package org.trackpet.demo

import android.content.ContentResolver
import android.graphics.BitmapFactory
import android.net.Uri
import java.io.ByteArrayOutputStream
import java.io.IOException

internal const val PHOTO_HELP = "JPG, PNG, WebP o GIF · hasta 5 MiB · máximo 5000 × 5000 píxeles."
private const val MAX_BYTES = 5 * 1024 * 1024
internal data class UploadPhoto(val bytes: ByteArray, val mime: String, val extension: String)

internal fun readPhoto(resolver: ContentResolver, uri: Uri): UploadPhoto {
    val bytes = try {
        resolver.openInputStream(uri)?.use { input ->
            val output = ByteArrayOutputStream()
            val buffer = ByteArray(8192)
            while (true) {
                val n = input.read(buffer)
                if (n < 0) break
                if (output.size() + n > MAX_BYTES)
                    throw ApiFailure(0, "La foto supera los 5 MiB. Reduce su tamaño o elige otra.")
                output.write(buffer, 0, n)
            }
            output.toByteArray()
        }
    } catch (error: ApiFailure) { throw error }
    catch (_: IOException) { null }
    catch (_: SecurityException) { null }
        ?: throw ApiFailure(0, "No se pudo leer la foto. Si está en la nube, descárgala al teléfono y vuelve a seleccionarla.")
    if (bytes.isEmpty()) throw ApiFailure(0, "El archivo está vacío. Elige otra foto.")
    // Providers may omit or mislabel the MIME type. Read the image header, without allocating a bitmap.
    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    BitmapFactory.decodeByteArray(bytes, 0, bytes.size, bounds)
    val mime = bounds.outMimeType
    val extension = when (mime) {
        "image/jpeg" -> "jpg"; "image/png" -> "png"; "image/webp" -> "webp"; "image/gif" -> "gif"
        else -> throw ApiFailure(0, "No se reconoce una imagen compatible. Si es HEIC u otro formato, expórtala como JPG o PNG.")
    }
    if (bounds.outWidth <= 0 || bounds.outHeight <= 0)
        throw ApiFailure(0, "La imagen parece dañada. Vuelve a guardarla o elige otra.")
    if (bounds.outWidth > 5000 || bounds.outHeight > 5000)
        throw ApiFailure(0, "La foto mide ${bounds.outWidth} × ${bounds.outHeight} píxeles. Reduce cada lado a 5000 píxeles o menos.")
    return UploadPhoto(bytes, mime, extension)
}

internal fun uploadError(code: Int, message: String): String = when {
    code == 413 || message in listOf("Upload failed with error code 1.", "Upload failed with error code 2.") ->
        "El servidor rechazó el tamaño de la foto. Prueba con una versión más pequeña."
    message == "Upload failed with error code 3." || message.startsWith("No file received") ->
        "El servidor no recibió la foto completa. Comprueba la conexión y vuelve a elegirla."
    code == 401 -> "Tu sesión terminó. Inicia sesión y vuelve a elegir la foto."
    code == 403 -> "Tu cuenta no tiene permiso para cambiar esta foto."
    message == "Image dimensions are too large." -> "Reduce cada lado de la foto a 5000 píxeles o menos."
    message == "File is not a valid image." -> "El servidor no reconoce la imagen. Expórtala como JPG o PNG e inténtalo de nuevo."
    code >= 500 || message.startsWith("Upload failed with error code") || message == "Upload directory is not writable." || message == "Failed to move uploaded file into place." ->
        "El servidor no pudo guardar la foto. Inténtalo más tarde."
    else -> message
}
