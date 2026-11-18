package org.trackpet.demo

import android.app.Application
import android.content.Context
import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.*
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException

class TrackPetApp : Application() {
    lateinit var api: Api
    override fun onCreate() { super.onCreate(); api = Api(this) }
}

class ApiFailure(val code: Int, message: String, val retryAfter: Int = 60) : IOException(message)

fun JSONObject.text(key: String): String = if (isNull(key)) "" else optString(key, "")
fun JSONArray.objects(): List<JSONObject> = (0 until length()).map { getJSONObject(it) }
fun json(vararg fields: Pair<String, Any?>): JSONObject = JSONObject().also { obj ->
    fields.forEach { (key, value) -> obj.put(key, value ?: JSONObject.NULL) }
}

class Api(private val context: Context) {
    val prefs = context.getSharedPreferences("trackpet", Context.MODE_PRIVATE)
    val base: String get() = prefs.getString("server", "")!!
    private val jar = object : CookieJar {
        @Synchronized override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
            val current = loadFromRequest(url).associateBy { it.name }.toMutableMap()
            cookies.forEach { current[it.name] = it }
            prefs.edit().putString("cookies", JSONArray(current.values.map { it.toString() }).toString()).apply()
        }
        @Synchronized override fun loadForRequest(url: HttpUrl): List<Cookie> = loadFromRequest(url)
        private fun loadFromRequest(url: HttpUrl): List<Cookie> {
            val stored = JSONArray(prefs.getString("cookies", "[]"))
            return (0 until stored.length()).mapNotNull { Cookie.parse(url, stored.getString(it)) }
                .filter { it.expiresAt > System.currentTimeMillis() && it.matches(url) }
        }
    }
    private val client = OkHttpClient.Builder().cookieJar(jar)
        .connectTimeout(12, java.util.concurrent.TimeUnit.SECONDS)
        .callTimeout(20, java.util.concurrent.TimeUnit.SECONDS).build()

    fun setServer(raw: String) {
        val url = raw.trim().trimEnd('/').toHttpUrlOrNull()
            ?: throw IllegalArgumentException("Escribe una URL http:// o https:// válida.")
        require(url.encodedQuery == null && url.fragment == null && url.username.isEmpty() && url.password.isEmpty()) {
            "Usa la URL de la carpeta pública del servidor."
        }
        prefs.edit().putString("server", url.toString().trimEnd('/')).remove("cookies")
            .putBoolean("signedIn", false).putBoolean("detection", false).apply()
    }
    fun clearSession() { prefs.edit().remove("cookies").putBoolean("signedIn", false).apply() }
    fun asset(path: String): String? = path.takeIf { it.startsWith("assets/img/uploads/") }?.let { "$base/$it" }

    suspend fun call(resource: String, method: String = "GET", body: JSONObject? = null,
                     params: Map<String, String> = emptyMap()): Any = withContext(Dispatchers.IO) {
        val url = "$base/api/endpoint.php".toHttpUrlOrNull()?.newBuilder()
            ?: throw ApiFailure(0, "Configura la dirección del servidor.")
        url.addQueryParameter("resource", resource)
        params.forEach { (key, value) -> url.addQueryParameter(key, value) }
        val requestBody = if (method == "GET") null else (body ?: JSONObject()).toString()
            .toRequestBody("application/json; charset=utf-8".toMediaType())
        execute(Request.Builder().url(url.build()).method(method, requestBody).build())
    }
    private fun execute(request: Request): Any {
        try {
            client.newCall(request).execute().use { response ->
                val raw = response.body?.string().orEmpty()
                val result = try { JSONObject(raw) } catch (_: org.json.JSONException) { null }
                if (!response.isSuccessful || result?.optString("status") != "success") {
                    if (response.code == 401) clearSession()
                    val details = result?.optJSONArray("data")
                    val messages = (0 until (details?.length() ?: 0)).mapNotNull { details?.opt(it) as? String }.filter { it.isNotBlank() }
                    val message = messages.joinToString("\n").ifEmpty {
                        when (val reason = result?.text("message")) {
                            "Username already exists" -> "Ese nombre de usuario ya existe. Elige otro."
                            "Email or phone already in use" -> "Ya hay una cuenta con ese correo o teléfono."
                            "A unique value is already in use", "Email, phone or username already in use" -> "Ese usuario, correo o teléfono ya está registrado."
                            null, "" -> "El servidor devolvió una respuesta inesperada (HTTP ${response.code})."
                            else -> reason
                        }
                    }
                    throw ApiFailure(response.code, if (request.url.queryParameter("resource") == "upload") uploadError(response.code, message) else message,
                        response.header("Retry-After")?.toIntOrNull() ?: 60)
                }
                return result.get("data")
            }
        } catch (error: ApiFailure) { throw error }
        catch (_: IOException) { throw ApiFailure(0, "No se pudo conectar. Revisa la red y el servidor.") }
    }
    suspend fun upload(uri: Uri, petId: String? = null): Any = withContext(Dispatchers.IO) {
        val (bytes, mime, extension) = readPhoto(context.contentResolver, uri)
        val url = "$base/api/endpoint.php".toHttpUrlOrNull()!!.newBuilder().addQueryParameter("resource", "upload")
            .addQueryParameter("target", if (petId == null) "user" else "pet")
        if (petId != null) url.addQueryParameter("petId", petId)
        val body = MultipartBody.Builder().setType(MultipartBody.FORM).addFormDataPart("fileToUpload",
            "photo.$extension", bytes.toRequestBody(mime.toMediaType())).build()
        execute(Request.Builder().url(url.build()).post(body).build())
    }
}
