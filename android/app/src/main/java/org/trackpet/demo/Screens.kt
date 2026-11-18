package org.trackpet.demo

import android.Manifest
import android.content.*
import android.content.pm.PackageManager
import android.net.Uri
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import coil.compose.AsyncImage
import kotlinx.coroutines.*
import org.json.JSONArray
import org.json.JSONObject
import java.time.*
import java.time.format.DateTimeFormatter
import java.util.UUID

data class Field(val key: String, val label: String, val value: String = "", val required: Boolean = true)
data class Editor(val title: String, val fields: List<Field>, val submit: suspend (Map<String, String>) -> Unit)

private fun localTime(value: String): String = try {
    Instant.parse(value).atZone(ZoneId.systemDefault()).format(DateTimeFormatter.ofPattern("dd MMM, HH:mm"))
} catch (_: Exception) { value }
private fun statusLabel(value: String) = when (value) {
    "open" -> "En búsqueda"; "recovered" -> "Recuperada"; else -> "Cancelada"
}

@Composable
private fun Heading(title: String, subtitle: String = "") {
    Text(title, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
    if (subtitle.isNotEmpty()) Text(subtitle, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
}

@Composable
private fun Photo(api: Api, path: String) {
    api.asset(path)?.let { AsyncImage(it, "Foto", modifier = Modifier.fillMaxWidth().height(180.dp), contentScale = ContentScale.Crop) }
}

@Composable
private fun Panel(content: @Composable ColumnScope.() -> Unit) {
    Card(Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp), content = content)
    }
}

@Composable
private fun EditorDialog(editor: Editor, dismiss: () -> Unit) {
    val values = remember(editor) { mutableStateMapOf<String, String>().apply { editor.fields.forEach { put(it.key, it.value) } } }
    var error by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()
    AlertDialog(onDismissRequest = { if (!busy) dismiss() }, title = { Text(editor.title) },
        text = {
            Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                editor.fields.forEach { field ->
                    OutlinedTextField(values[field.key] ?: "", { values[field.key] = it }, label = { Text(field.label) },
                        modifier = Modifier.fillMaxWidth(), enabled = !busy, singleLine = field.key != "details" && field.key != "notes",
                        visualTransformation = if (field.key == "password") PasswordVisualTransformation() else VisualTransformation.None)
                }
                if (error.isNotEmpty()) Text(error, color = MaterialTheme.colorScheme.error)
                if (busy) LinearProgressIndicator(Modifier.fillMaxWidth())
            }
        }, confirmButton = {
            TextButton(enabled = !busy, onClick = {
                error = ""
                if (editor.fields.any { it.required && values[it.key].isNullOrBlank() }) { error = "Completa los campos obligatorios."; return@TextButton }
                busy = true
                scope.launch {
                    try { editor.submit(values.toMap()); dismiss() }
                    catch (failure: Exception) { error = failure.message ?: "No se pudo guardar." }
                    finally { busy = false }
                }
            }) { Text("Guardar") }
        }, dismissButton = { TextButton(enabled = !busy, onClick = dismiss) { Text("Cancelar") } })
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TrackPetScreen(api: Api, activity: MainActivity) {
    var signedIn by remember { mutableStateOf(false) }
    var checking by remember { mutableStateOf(api.base.isNotEmpty()) }
    var bootError by remember { mutableStateOf("") }
    var server by rememberSaveable { mutableStateOf(api.base) }
    var settings by remember { mutableStateOf(api.base.isEmpty()) }
    var username by rememberSaveable { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var page by rememberSaveable { mutableStateOf("reports") }
    var selectedId by rememberSaveable { mutableStateOf("") }
    var revision by remember { mutableIntStateOf(0) }
    var offset by remember { mutableIntStateOf(0) }
    var query by rememberSaveable { mutableStateOf("") }
    var search by rememberSaveable { mutableStateOf("") }
    var data by remember { mutableStateOf<JSONObject?>(null) }
    var error by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var working by remember { mutableStateOf(false) }
    var editor by remember { mutableStateOf<Editor?>(null) }
    var registering by remember { mutableStateOf(false) }
    var confirmation by remember { mutableStateOf<Pair<String, suspend () -> Unit>?>(null) }
    var detection by remember { mutableStateOf(api.prefs.getBoolean("detection", false)) }
    var detectionStatus by remember { mutableStateOf(api.prefs.getString("detectionStatus", "Lista para activar")!!) }
    val scope = rememberCoroutineScope()
    val snackbar = remember { SnackbarHostState() }
    val lifecycle = LocalLifecycleOwner.current
    var visible by remember { mutableStateOf(true) }
    var photoTarget by rememberSaveable { mutableStateOf<String?>(null) }
    var photoError by rememberSaveable { mutableStateOf<String?>(null) }
    fun navigate(destination: String, id: String = "") {
        page = destination; selectedId = id; offset = 0; data = null; error = ""; revision++
    }
    fun perform(action: suspend () -> Unit) {
        if (working) return
        working = true
        scope.launch {
            try { action() }
            catch (failure: Exception) { snackbar.showSnackbar(failure.message ?: "Intenta de nuevo.") }
            finally { working = false }
        }
    }
    val photoPicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        val target = photoTarget
        if (uri != null) perform {
            try {
                api.upload(uri, target)
                revision++
                scope.launch { snackbar.showSnackbar("Foto actualizada") }
            } catch (failure: ApiFailure) { photoError = failure.message }
        }
    }
    DisposableEffect(Unit) {
        val listener = SharedPreferences.OnSharedPreferenceChangeListener { _, key ->
            if (key == "detection") detection = api.prefs.getBoolean("detection", false)
            if (key == "detectionStatus") detectionStatus = api.prefs.getString("detectionStatus", "")!!
            if (key == "signedIn" && !api.prefs.getBoolean("signedIn", false)) {
                signedIn = false
                activity.stopService(Intent(activity, BeaconService::class.java))
            }
        }
        api.prefs.registerOnSharedPreferenceChangeListener(listener)
        onDispose { api.prefs.unregisterOnSharedPreferenceChangeListener(listener) }
    }
    DisposableEffect(lifecycle) {
        val observer = LifecycleEventObserver { _, _ -> visible = lifecycle.lifecycle.currentState.isAtLeast(Lifecycle.State.RESUMED) }
        lifecycle.lifecycle.addObserver(observer)
        onDispose { lifecycle.lifecycle.removeObserver(observer) }
    }
    LaunchedEffect(Unit) {
        if (api.base.isNotEmpty()) {
            try {
                api.call("session")
                signedIn = true
                api.prefs.edit().putBoolean("signedIn", true).apply()
                activity.startDetection()
            } catch (failure: ApiFailure) {
                if (failure.code != 401) bootError = failure.message ?: "No se pudo conectar."
            } finally { checking = false }
        }
    }
    LaunchedEffect(signedIn, page, selectedId, revision, offset, search) {
        if (!signedIn || page == "detection" || page == "manual") return@LaunchedEffect
        loading = true; error = ""
        try {
            data = when (page) {
                "pets", "account" -> api.call("session") as JSONObject
                "report" -> api.call("report", params = mapOf("reportId" to selectedId)) as JSONObject
                "tracking" -> api.call("tracking", params = mapOf("reportId" to selectedId, "offset" to "$offset", "limit" to "20")) as JSONObject
                "sightings" -> api.call("sightings", params = mapOf("offset" to "$offset")) as JSONObject
                else -> api.call("reports", params = mapOf("q" to search, "offset" to "$offset", "limit" to "12", "mine" to (page == "mine").toString())) as JSONObject
            }
        } catch (failure: Exception) { error = failure.message ?: "Error al cargar." }
        finally { loading = false }
    }
    LaunchedEffect(page, selectedId, visible, offset, signedIn) {
        if (signedIn && page == "tracking" && visible && offset == 0) {
            while (isActive) {
                delay(10000)
                if (data?.optJSONObject("report")?.text("status") == "open") revision++
            }
        }
    }
    BackHandler(enabled = signedIn && page !in listOf("reports", "pets", "account")) { navigate("reports") }
    Scaffold(snackbarHost = { SnackbarHost(snackbar) },
        topBar = { TopAppBar(title = { Text("TrackPet", fontWeight = FontWeight.Bold) }, actions = {
            if (signedIn) TextButton(onClick = { revision++ }, enabled = !loading && !working) { Text("Actualizar") }
        }) },
        bottomBar = {
            if (signedIn) NavigationBar {
                listOf("reports" to "Búsquedas", "pets" to "Mis mascotas", "detection" to "Detectar", "account" to "Cuenta").forEach { (route, label) ->
                    NavigationBarItem(selected = page == route, onClick = { navigate(route) },
                        icon = { Text(when(route) { "reports" -> "⌕"; "pets" -> "♡"; "detection" -> "◎"; else -> "☰" }) }, label = { Text(label) })
                }
            }
        }) { padding ->
        Column(Modifier.fillMaxSize().padding(padding).imePadding().verticalScroll(rememberScrollState()).padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)) {
            if (checking) { CircularProgressIndicator(); Text("Comprobando tu sesión…") }
            else if (!signedIn) {
                Heading("Ayudemos a volver a casa", "Inicia sesión para encontrar mascotas y ayudar a tu comunidad.")
                if (settings) {
                    Panel {
                        Text("Conectar con TrackPet", style = MaterialTheme.typography.titleLarge)
                        Text("Dirección de la carpeta pública del servidor. En un teléfono usa la IP del equipo en tu red Wi-Fi.")
                        OutlinedTextField(server, { server = it }, label = { Text("http://192.168.1.10:8000") }, singleLine = true, modifier = Modifier.fillMaxWidth())
                        Button(enabled = !working, onClick = { perform {
                            api.setServer(server); api.call("viewer"); settings = false; bootError = ""
                        } }) { Text("Guardar y comprobar") }
                    }
                } else {
                    Panel {
                        OutlinedTextField(username, { username = it }, label = { Text("Usuario o correo") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
                        PasswordField(password, { password = it }, "Contraseña")
                        Button(enabled = !working && username.isNotBlank() && password.isNotEmpty(), modifier = Modifier.fillMaxWidth(), onClick = { perform {
                            api.call("login", "POST", json("username" to username.trim(), "password" to password))
                            password = ""; bootError = ""; signedIn = true
                            api.prefs.edit().putBoolean("signedIn", true).apply()
                            navigate("reports"); activity.startDetection()
                        } }) { Text("Iniciar sesión") }
                        TextButton(enabled = !working, onClick = { registering = true }) { Text("Crear una cuenta") }
                        TextButton(onClick = { settings = true }) { Text("Cambiar servidor") }
                    }
                }
                if (bootError.isNotEmpty()) Text(bootError, color = MaterialTheme.colorScheme.error)
            } else {
                if (loading || working) LinearProgressIndicator(Modifier.fillMaxWidth())
                if (error.isNotEmpty()) { Text(error, color = MaterialTheme.colorScheme.error); OutlinedButton(onClick = { revision++ }) { Text("Reintentar") } }
                when (page) {
                    "reports", "mine" -> {
                        Heading(if (page == "mine") "Mis búsquedas" else "Ayuda a encontrarles", "Cada observación puede acercar una mascota a casa.")
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            OutlinedTextField(query, { query = it }, modifier = Modifier.weight(1f), label = { Text("Nombre o zona") }, singleLine = true)
                            TextButton(onClick = { search = query.trim(); offset = 0 }) { Text("Buscar") }
                        }
                        data?.let { result ->
                            val items = result.optJSONArray("items")?.objects() ?: emptyList()
                            if (items.isEmpty()) Text("No hay búsquedas para mostrar.")
                            items.forEach { report -> Panel {
                                Photo(api, report.text("petPhoto"))
                                Text(report.text("petName"), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                                Text(statusLabel(report.text("status")) + " · " + report.text("petSpecies"))
                                Text(report.text("lastSeenArea"))
                                Button(onClick = { navigate("report", report.getString("reportId")) }) { Text("Ver reporte") }
                            } }
                            Pagination(offset, 12, result.optInt("total")) { offset = it }
                        }
                    }
                    "pets" -> {
                        Heading("Mis mascotas", "Sus datos y búsquedas en un solo lugar.")
                        Button(onClick = { editor = petEditor(null) { body -> api.call("pet", "POST", body); revision++ } }) { Text("Registrar mascota") }
                        data?.optJSONArray("pets")?.objects()?.let { pets ->
                            if (pets.isEmpty()) Text("Registra tu primera mascota para comenzar.")
                            pets.forEach { pet -> Panel {
                                val id = pet.getString("petId")
                                Photo(api, pet.text("petPhoto"))
                                Text(pet.text("petName"), style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
                                Text(pet.text("petSpecies") + " · " + pet.text("petColour"))
                                if (pet.text("petDescription").isNotEmpty()) Text(pet.text("petDescription"))
                                if (pet.text("reportId").isNotEmpty()) Button(onClick = { navigate("tracking", pet.getString("reportId")) }) { Text("Ver búsqueda activa") }
                                else Button(onClick = {
                                    val request = UUID.randomUUID().toString()
                                    editor = Editor("Reportar desaparición", reportFields()) { values ->
                                        val report = api.call("report", "POST", reportBody(values).put("petId",id).put("clientRequestId",request)) as JSONObject
                                        navigate("tracking", report.getString("reportId"))
                                    }
                                }) { Text("Reportar desaparición") }
                                Row {
                                    TextButton(onClick = { editor = petEditor(pet) { body -> api.call("pet","PATCH",body.put("petId",id)); revision++ } }) { Text("Editar") }
                                    TextButton(enabled = !working, onClick = { photoTarget=id; photoPicker.launch("image/*") }) { Text("Foto") }
                                    TextButton(onClick = { confirmation = "¿Dar de baja a ${pet.text("petName")} y cerrar su búsqueda?" to {
                                        api.call("pet","DELETE",json("petId" to id)); revision++
                                    } }) { Text("Dar de baja") }
                                }
                                Text(PHOTO_HELP, style = MaterialTheme.typography.bodySmall)
                                TextButton(onClick = {
                                    activity.getSystemService(ClipboardManager::class.java).setPrimaryClip(ClipData.newPlainText("UUID de mascota",id))
                                    scope.launch { snackbar.showSnackbar("UUID copiado para configurar el collar") }
                                }) { Text("Copiar UUID del collar") }
                            } }
                        }
                        OutlinedButton(onClick = { navigate("mine") }) { Text("Historial de búsquedas") }
                    }
                    "report" -> data?.let { report ->
                        Heading(report.text("petName"), statusLabel(report.text("status")))
                        Photo(api, report.text("petPhoto"))
                        Panel {
                            Text(report.text("petSpecies") + " · " + report.text("petColour"))
                            Text("Última zona conocida · " + report.text("lastSeenArea"))
                            Text("Desapareció · " + localTime(report.text("lostAt")))
                            Text(report.text("details").ifEmpty { "Sin detalles adicionales" })
                            Text("Dueño · @" + report.text("ownerUsername"))
                            if (report.text("status") == "open") Button(onClick = { navigate("manual",selectedId) }) { Text("La he visto") }
                            if (report.optBoolean("canTrack")) OutlinedButton(onClick = { navigate("tracking",selectedId) }) { Text("Mi mapa privado") }
                            TextButton(onClick = {
                                activity.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).setType("text/plain")
                                    .putExtra(Intent.EXTRA_TEXT, api.base + "/report.html?reportId=" + selectedId), "Compartir búsqueda"))
                            }) { Text("Compartir reporte") }
                        }
                    }
                    "manual" -> ManualSighting(api, activity, selectedId) { navigate("sightings") }
                    "tracking" -> data?.let { result ->
                        val report = result.getJSONObject("report")
                        val sightings = result.getJSONObject("sightings")
                        val items = sightings.getJSONArray("items").objects()
                        Heading("Buscando a " + report.text("petName"), statusLabel(report.text("status")) + " · Mapa privado")
                        val points = JSONArray()
                        if (!report.isNull("lastSeenLatitude")) points.put(json("latitude" to report.getDouble("lastSeenLatitude"),
                            "longitude" to report.getDouble("lastSeenLongitude"), "label" to "Último lugar conocido"))
                        items.forEach { points.put(json("latitude" to it.getDouble("latitude"),"longitude" to it.getDouble("longitude"),
                            "label" to localTime(it.text("observedAt")))) }
                        result.optJSONObject("latestSighting")?.let { latest ->
                            if (items.none { it.text("sightingId") == latest.text("sightingId") }) points.put(json(
                                "latitude" to latest.getDouble("latitude"),"longitude" to latest.getDouble("longitude"), "label" to "Última observación"))
                            Text("Última observación · " + localTime(latest.text("observedAt")))
                        }
                        PetMap(points, Modifier.fillMaxWidth().height(280.dp))
                        Text("Cada punto indica dónde se observó la mascota o se detectó su collar. Puede haberse desplazado.")
                        if (report.text("status") == "open") {
                            Row {
                                Button(onClick = { confirmation = "¿Confirmar que ${report.text("petName")} fue recuperada?" to {
                                    api.call("report","PATCH",json("status" to "recovered"),mapOf("reportId" to selectedId)); revision++
                                } }) { Text("Recuperada") }
                                TextButton(onClick = { confirmation = "¿Cancelar esta búsqueda?" to {
                                    api.call("report","PATCH",json("status" to "cancelled"),mapOf("reportId" to selectedId)); revision++
                                } }) { Text("Cancelar búsqueda") }
                            }
                            TextButton(onClick = {
                                editor=Editor("Editar búsqueda",reportFields(report)) { values ->
                                    api.call("report","PATCH",reportBody(values),mapOf("reportId" to selectedId));revision++
                                }
                            }) { Text("Editar detalles") }
                        }
                        if (items.isEmpty()) Text("Todavía no hay avistamientos activos.")
                        items.forEach { sighting -> SightingCard(sighting) {
                            confirmation = "¿Retirar este avistamiento del mapa?" to {
                                api.call("sighting","DELETE",json("sightingId" to sighting.text("sightingId"))); revision++
                            }
                        } }
                        Pagination(offset,20,sightings.getInt("total")) { offset=it }
                        TextButton(onClick = { navigate("report",selectedId) }) { Text("Ver reporte para compartir") }
                    }
                    "sightings" -> {
                        Heading("Mis avistamientos", "Las pistas que has compartido.")
                        data?.let { result ->
                            val items = result.getJSONArray("items").objects()
                            if(items.isEmpty()) Text("Tus avistamientos aparecerán aquí.")
                            items.forEach { sighting -> SightingCard(sighting) {
                                confirmation = "¿Retirar este avistamiento?" to {
                                    api.call("sighting","DELETE",json("sightingId" to sighting.text("sightingId")));revision++
                                }
                            } }
                            Pagination(offset,20,result.getInt("total")) { offset=it }
                        }
                    }
                    "detection" -> {
                        Heading("Ayuda mientras sigues tu día", "Tu teléfono puede detectar collares cercanos.")
                        Panel {
                            Row(verticalAlignment=Alignment.CenterVertically) {
                                Text("Detección en segundo plano",Modifier.weight(1f),fontWeight=FontWeight.Bold)
                                Switch(detection, onCheckedChange={ enabled ->
                                    if(enabled) confirmation = "TrackPet buscará collares con una notificación visible y enviará tu ubicación cuando confirme una mascota perdida. ¿Activar?" to { activity.enableDetection(true) }
                                    else activity.enableDetection(false)
                                })
                            }
                            Text(detectionStatus)
                            Text("Comparte la ubicación actual del teléfono únicamente para mascotas con una búsqueda abierta.")
                            Text("Al activar, concede Bluetooth y ubicación precisa. Mantén Bluetooth y ubicación encendidos.")
                            if(detection) OutlinedButton(onClick={ activity.startDetection() }) { Text("Comprobar detección") }
                            TextButton(onClick={
                                activity.startActivity(Intent(android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                                    Uri.parse("package:" + activity.packageName)))
                            }) { Text("Permisos y batería de la app") }
                        }
                        Panel {
                            Text("Última ayuda",style=MaterialTheme.typography.titleMedium)
                            Text(api.prefs.getString("lastDetection","Todavía no se han enviado detecciones.")!!)
                            Text("Android puede detener la app al forzar su cierre, reiniciar el teléfono o restringir su batería. Abre TrackPet para reanudarla.")
                        }
                    }
                    "account" -> data?.let { profile ->
                        Heading(profile.text("firstName") + " " + profile.text("surname"),"@" + profile.text("username"))
                        Photo(api, profile.text("profilePhoto"))
                        Panel {
                            Text(profile.text("email"))
                            TextButton(onClick={ editor=Editor("Editar perfil",listOf(Field("firstName","Nombre",profile.text("firstName")),
                                Field("surname","Apellido",profile.text("surname")),Field("username","Usuario",profile.text("username")),
                                Field("email","Correo",profile.text("email")),Field("phone","Teléfono",profile.text("phone"),false))) { values ->
                                api.call("user","PATCH",JSONObject(values));revision++
                            } }) { Text("Editar perfil") }
                            TextButton(enabled = !working, onClick={ photoTarget=null;photoPicker.launch("image/*") }) { Text("Cambiar foto") }
                            Text(PHOTO_HELP, style = MaterialTheme.typography.bodySmall)
                            TextButton(onClick={ editor=Editor("Cambiar contraseña",listOf(Field("password","Nueva contraseña"))) { values -> api.call("user","PATCH",JSONObject(values)) } }) { Text("Cambiar contraseña") }
                        }
                        OutlinedButton(onClick={ navigate("sightings") }) { Text("Mis avistamientos") }
                        OutlinedButton(onClick={ navigate("mine") }) { Text("Mis búsquedas") }
                        TextButton(enabled=!working,onClick={ perform {
                            activity.enableDetection(false)
                            try { api.call("logout","POST") } finally { api.clearSession();signedIn=false;data=null;password="" }
                        } }) { Text("Cerrar sesión") }
                        TextButton(onClick={ confirmation="¿Dar de baja tu cuenta y cerrar tus búsquedas activas?" to {
                            api.call("user","DELETE");activity.enableDetection(false);api.clearSession();signedIn=false
                        } }) { Text("Dar de baja mi cuenta") }
                    }
                }
            }
        }
    }
    if (registering) RegistrationDialog(submit = { body ->
        api.call("user", "POST", body)
        username = body.getString("username")
        scope.launch { snackbar.showSnackbar("Cuenta creada. Inicia sesión.") }
    }, dismiss = { registering = false })
    photoError?.let { message ->
        AlertDialog(onDismissRequest = { photoError = null }, title = { Text("No se pudo subir la foto") },
            text = { Text(message) }, confirmButton = { TextButton(onClick = { photoError = null }) { Text("Entendido") } })
    }
    editor?.let { EditorDialog(it) { editor=null } }
    confirmation?.let { (title,action) ->
        AlertDialog(onDismissRequest={ if(!working) confirmation=null },title={ Text(title) },
            confirmButton={ TextButton(enabled=!working,onClick={ perform { action(); confirmation=null } }) { Text("Confirmar") } },
            dismissButton={ TextButton(enabled=!working,onClick={ confirmation=null }) { Text("Volver") } })
    }
}

private fun petEditor(pet: JSONObject?, save: suspend (JSONObject) -> Unit): Editor {
    val fields = listOf(Field("petName","Nombre",pet?.text("petName") ?: ""),
        Field("petSpecies","Especie",pet?.text("petSpecies") ?: ""),Field("petColour","Color",pet?.text("petColour") ?: ""),
        Field("petDescription","Descripción",pet?.text("petDescription") ?: "",false))
    return Editor(if(pet==null) "Registrar mascota" else "Editar mascota",fields) { save(JSONObject(it)) }
}

private fun reportFields(report: JSONObject? = null): List<Field> {
    val time = report?.text("lostAt")?.let { Instant.parse(it).atZone(ZoneId.systemDefault()).toLocalDateTime() }
        ?: LocalDateTime.now()
    return listOf(Field("lastSeenArea","Última zona conocida",report?.text("lastSeenArea") ?: ""),
        Field("lostAt","Fecha local (AAAA-MM-DDTHH:MM)",time.withNano(0).toString()),
        Field("details","Señas y detalles",report?.text("details") ?: "",false),
        Field("lastSeenLatitude","Latitud conocida (opcional)",report?.text("lastSeenLatitude") ?: "",false),
        Field("lastSeenLongitude","Longitud conocida (opcional)",report?.text("lastSeenLongitude") ?: "",false))
}

private fun reportBody(values: Map<String,String>): JSONObject {
    val latitude=values["lastSeenLatitude"]!!.trim()
    val longitude=values["lastSeenLongitude"]!!.trim()
    require(latitude.isEmpty()==longitude.isEmpty()) { "Completa ambas coordenadas o deja ambas vacías." }
    val lat=if(latitude.isEmpty()) null else latitude.toDouble()
    val lon=if(longitude.isEmpty()) null else longitude.toDouble()
    require(lat==null || (lat.isFinite() && lat in -90.0..90.0 && lon!!.isFinite() && lon in -180.0..180.0)) { "Revisa las coordenadas." }
    return json("lastSeenArea" to values["lastSeenArea"],"details" to values["details"],
        "lostAt" to LocalDateTime.parse(values["lostAt"]).atZone(ZoneId.systemDefault()).toInstant().toString(),
        "lastSeenLatitude" to lat,"lastSeenLongitude" to lon)
}

@Composable
private fun Pagination(offset:Int,size:Int,total:Int,change:(Int)->Unit) {
    if(total>size) Row(Modifier.fillMaxWidth(),horizontalArrangement=Arrangement.SpaceBetween,verticalAlignment=Alignment.CenterVertically) {
        TextButton(enabled=offset>0,onClick={ change((offset-size).coerceAtLeast(0)) }) { Text("Anterior") }
        Text("${offset+1}–${(offset+size).coerceAtMost(total)} de $total")
        TextButton(enabled=offset+size<total,onClick={ change(offset+size) }) { Text("Siguiente") }
    }
}

@Composable
private fun SightingCard(sighting:JSONObject,retract:()->Unit) {
    Panel {
        if(sighting.text("petName").isNotEmpty()) Text(sighting.text("petName"),fontWeight=FontWeight.Bold)
        Text(localTime(sighting.text("observedAt")),style=MaterialTheme.typography.titleMedium)
        Text(if(sighting.text("source")=="ble") "Collar detectado cerca de este teléfono" else "Avistamiento compartido")
        Text("${sighting.optDouble("latitude")}, ${sighting.optDouble("longitude")}")
        if(sighting.has("reporterUsername")) Text(if(sighting.isNull("reporterId")) "Anónimo" else "@" + sighting.text("reporterUsername"))
        if(sighting.text("notes").isNotEmpty()) Text(sighting.text("notes"))
        if(!sighting.isNull("accuracyMeters")) Text("Precisión del teléfono · ${sighting.optDouble("accuracyMeters")} m")
        if(sighting.optBoolean("isRetracted")) Text("Retirado del mapa")
        else TextButton(onClick=retract) { Text("Retirar avistamiento") }
    }
}

@Composable
private fun ManualSighting(api:Api,activity:MainActivity,reportId:String,done:()->Unit) {
    var latitude by rememberSaveable { mutableStateOf("") }
    var longitude by rememberSaveable { mutableStateOf("") }
    var observed by rememberSaveable { mutableStateOf(LocalDateTime.now().withNano(0).toString()) }
    var notes by rememberSaveable { mutableStateOf("") }
    var accuracy by remember { mutableStateOf<Double?>(null) }
    var source by remember { mutableStateOf("manual") }
    var confirmed by remember { mutableStateOf(false) }
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf("") }
    var pendingBody by remember { mutableStateOf("") }
    var pendingId by rememberSaveable { mutableStateOf(UUID.randomUUID().toString()) }
    var point by remember { mutableStateOf(JSONArray()) }
    val scope=rememberCoroutineScope()
    fun locate() {
        busy=true; error=""
        scope.launch {
            try {
                val location=currentLocation(activity)
                latitude=location.latitude.toString();longitude=location.longitude.toString();accuracy=location.accuracy.toDouble();source="gps"
                observed=LocalDateTime.now().withNano(0).toString()
                point=JSONArray().put(json("latitude" to location.latitude,"longitude" to location.longitude,"label" to "Mi ubicación"))
            } catch(failure:Exception) { error=failure.message ?: "No se obtuvo tu ubicación." }
            finally { busy=false }
        }
    }
    val permission=rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { grants ->
        if(grants.values.any { it }) locate() else error="Puedes seleccionar el punto en el mapa."
    }
    Heading("Comparte una pista","Selecciona el lugar donde viste a la mascota.")
    PetMap(point,Modifier.fillMaxWidth().height(260.dp)) { lat,lon -> latitude=lat.toString();longitude=lon.toString();source="manual";accuracy=null }
    OutlinedTextField(latitude,{latitude=it;source="manual";accuracy=null},label={Text("Latitud")},modifier=Modifier.fillMaxWidth(),keyboardOptions=KeyboardOptions(keyboardType=KeyboardType.Decimal))
    OutlinedTextField(longitude,{longitude=it;source="manual";accuracy=null},label={Text("Longitud")},modifier=Modifier.fillMaxWidth(),keyboardOptions=KeyboardOptions(keyboardType=KeyboardType.Decimal))
    OutlinedButton(enabled=!busy,onClick={
        if(activity.checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION)==PackageManager.PERMISSION_GRANTED) locate()
        else permission.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION,Manifest.permission.ACCESS_COARSE_LOCATION))
    }) { Text("Estoy allí · usar mi ubicación") }
    OutlinedTextField(observed,{observed=it},label={Text("Fecha y hora local")},supportingText={Text("Formato 2026-11-05T14:30:00")},modifier=Modifier.fillMaxWidth())
    OutlinedTextField(notes,{notes=it},label={Text("¿Qué observaste?")},modifier=Modifier.fillMaxWidth())
    Row(verticalAlignment=Alignment.CenterVertically) {
        Checkbox(confirmed,{confirmed=it})
        Text("Confirmo que el punto corresponde al lugar del avistamiento.",Modifier.weight(1f))
    }
    if(error.isNotEmpty()) Text(error,color=MaterialTheme.colorScheme.error)
    Button(enabled=confirmed && !busy,onClick={
        busy=true;error=""
        scope.launch {
            try {
                val lat=latitude.toDoubleOrNull();val lon=longitude.toDoubleOrNull()
                require(lat!=null && lon!=null && lat.isFinite() && lon.isFinite() && lat in -90.0..90.0 && lon in -180.0..180.0) { "Revisa las coordenadas." }
                val time=LocalDateTime.parse(observed).atZone(ZoneId.systemDefault()).toInstant().toString()
                val body=json("reportId" to reportId,"latitude" to lat,"longitude" to lon,"observedAt" to time,"notes" to notes,"source" to source,"accuracyMeters" to accuracy)
                if(body.toString()!=pendingBody) {pendingBody=body.toString();pendingId=UUID.randomUUID().toString()}
                // The public website endpoint permits guests; the app verifies its account before submitting.
                api.call("session")
                api.call("sighting","POST",body.put("clientRequestId",pendingId));done()
            } catch(failure:Exception) {error=failure.message ?: "No se pudo enviar."}
            finally {busy=false}
        }
    }) {Text(if(busy) "Enviando…" else "Enviar al dueño")}
}
