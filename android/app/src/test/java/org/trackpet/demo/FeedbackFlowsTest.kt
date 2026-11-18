package org.trackpet.demo

import android.net.Uri
import android.graphics.Bitmap
import kotlinx.coroutines.runBlocking
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.annotation.Config
import java.io.File
import java.net.InetAddress
import java.net.ServerSocket
import kotlin.concurrent.thread

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [31])
class FeedbackFlowsTest {
    private val valid = mapOf("firstName" to " Ana ", "surname" to "Díaz", "username" to "ana",
        "email" to "ana@example.test", "password" to "Abcdef1_", "confirm" to "Abcdef1_")

    @Test fun registrationHonorsServerRulesAndKeepsConfirmationLocal() {
        assertTrue(registrationErrors(valid).isEmpty())
        for (password in listOf("Abcd1_","abcdef1_","ABCDEF1_","Abcdefg_","Abcdef12","Abcdef1#","Abcdéf1_"))
            assertTrue(password, "password" in registrationErrors(valid + ("password" to password)))
        val errors = registrationErrors(valid + mapOf("email" to "ana@", "phone" to "123", "confirm" to "abcdef1_", "username" to "u".repeat(26)))
        assertEquals(setOf("email", "phone", "confirm", "username"), errors.keys)
        val body = registrationBody(valid)
        assertFalse(body.has("confirm"))
        assertFalse(body.has("phone"))
        assertEquals("Ana", body.getString("firstName"))
        assertEquals(valid["password"], body.getString("password"))
    }

    @Test fun clientShowsServerValidationDetailsAndHandlesNonJsonUploadLimits() {
        val validation = """{"status":"error","message":"there are some errors with your request","data":["not a valid email address","username exceeds 25 char limit"]}"""
        withServer(listOf(400 to validation, 413 to "<html>Payload Too Large</html>")) { api ->
            val invalid = assertThrows(ApiFailure::class.java) { runBlocking { api.call("user","POST",registrationBody(valid)) } }
            assertTrue(invalid.message!!.contains("email") && invalid.message!!.contains("25"))
            val large = assertThrows(ApiFailure::class.java) { runBlocking { api.call("upload","POST") } }
            assertTrue(large.message!!.contains("más pequeña"))
        }
    }

    @Test fun photoSelectionExplainsEmptyMissingAndOversizedFiles() {
        val context = RuntimeEnvironment.getApplication()
        val photo = File(context.cacheDir, "feedback-photo")
        fun message() = assertThrows(ApiFailure::class.java) { readPhoto(context.contentResolver, Uri.fromFile(photo)) }.message!!
        assertTrue(message().contains("seleccionarla"))
        photo.writeBytes(byteArrayOf())
        assertTrue(message().contains("vacío"))
        photo.writeBytes(ByteArray(5 * 1024 * 1024 + 1))
        assertTrue(message().contains("5 MiB"))
    }

    @Test
    @org.robolectric.annotation.GraphicsMode(org.robolectric.annotation.GraphicsMode.Mode.NATIVE)
    fun profileAndPetUploadsUseTheImageContentsAndEnforcePixelLimit() {
        val context = RuntimeEnvironment.getApplication()
        val photo = File(context.cacheDir, "gallery-file-without-extension")
        val uri = Uri.fromFile(photo)
        fun png(width: Int) {
            val bitmap = Bitmap.createBitmap(width, 1, Bitmap.Config.ARGB_8888)
            photo.outputStream().use { bitmap.compress(Bitmap.CompressFormat.PNG, 100, it) }
            bitmap.recycle()
        }
        png(5001)
        assertTrue(assertThrows(ApiFailure::class.java) { readPhoto(context.contentResolver, uri) }.message!!.contains("5001"))
        png(5000)
        val ok = 200 to """{"status":"success","data":{}}"""
        val requests = withServer(listOf(ok, ok)) { api ->
            runBlocking { api.upload(uri); api.upload(uri, "my-pet") }
        }
        assertTrue(requests[0].startsWith("POST /api/endpoint.php?resource=upload&target=user HTTP"))
        assertTrue(requests[1].startsWith("POST /api/endpoint.php?resource=upload&target=pet&petId=my-pet HTTP"))
        assertTrue(requests.all { it.contains("name=\"fileToUpload\"") && it.contains("filename=\"photo.png\"") && it.contains("image/png") })
    }

    private fun withServer(responses: List<Pair<Int, String>>, action: (Api) -> Unit): List<String> {
        return ServerSocket(0, 8, InetAddress.getByName("127.0.0.1")).use { server ->
            val requests = mutableListOf<String>()
            val worker = thread(isDaemon = true) {
                for ((code, response) in responses) server.accept().use { socket ->
                    socket.soTimeout = 5000
                    val reader = socket.getInputStream().bufferedReader(Charsets.ISO_8859_1)
                    val first = reader.readLine()
                    val headers = generateSequence { reader.readLine() }.takeWhile { it.isNotEmpty() }.toList()
                    val size = headers.firstOrNull { it.startsWith("Content-Length:", true) }?.substringAfter(':')?.trim()?.toInt() ?: 0
                    val body = CharArray(size)

                    var read = 0
                    while (read < size) {
                        val count = reader.read(body, read, size - read)
                        if (count < 0) break
                        read += count
                    }
                    requests.add(first + "\n" + String(body))
                    val bytes = response.toByteArray()
                    socket.getOutputStream().apply {
                        write("HTTP/1.1 $code Test\r\nConnection: close\r\nContent-Length: ${bytes.size}\r\n\r\n".toByteArray())
                        write(bytes); flush()
                    }
                }
            }
            val api = Api(RuntimeEnvironment.getApplication())
            api.setServer("http://127.0.0.1:${server.localPort}")
            action(api)
            worker.join(3000)
            assertFalse("Server did not receive the expected requests", worker.isAlive)
            requests
        }
    }
}
