package org.trackpet.demo

import android.util.Patterns
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.*
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.DialogProperties
import kotlinx.coroutines.launch
import org.json.JSONObject

internal const val PASSWORD_HELP = "Usa al menos 8 caracteres: una mayúscula, una minúscula, un número y un símbolo de @\$!%*?&_. Usa solo letras de la A a la Z, números y esos símbolos."
private val passwordPattern = Regex("(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[@\$!%*?&_])[A-Za-z0-9@\$!%*?&_]{8,}")
private val fields = listOf("firstName" to "Nombre", "surname" to "Apellido", "username" to "Usuario",
    "email" to "Correo", "phone" to "Teléfono (opcional)", "password" to "Contraseña", "confirm" to "Confirmar contraseña")

internal fun registrationErrors(values: Map<String, String>): Map<String, String> = buildMap {
    for ((key, label) in fields) {
        val raw = values[key].orEmpty()
        val value = if (key in listOf("password", "confirm")) raw else raw.trim()
        val limit = when (key) { "username" -> 25; "phone" -> 15; else -> 50 }
        val problem = when {
            key == "confirm" && value.isEmpty() -> "Vuelve a escribir tu contraseña."
            key != "phone" && value.isEmpty() -> "Completa el campo «${label}»."
            key == "password" -> if (passwordPattern.matches(value)) null else PASSWORD_HELP
            key == "confirm" -> if (value == values["password"]) null else "Las contraseñas no coinciden."
            value.codePointCount(0, value.length) > limit -> "Usa como máximo $limit caracteres."
            key == "email" && !Patterns.EMAIL_ADDRESS.matcher(value).matches() -> "Escribe un correo válido, por ejemplo ana@ejemplo.com."
            key == "phone" && value.isNotEmpty() && !Regex("\\+?[0-9 ()-]{7,15}").matches(value) ->
                "Usa de 7 a 15 caracteres: números, espacios, guiones o paréntesis. El + solo puede ir al inicio."
            else -> null
        }
        if (problem != null) put(key, problem)
    }
}

internal fun registrationBody(values: Map<String, String>): JSONObject = JSONObject().apply {
    for (key in listOf("firstName", "surname", "username", "email")) put(key, values.getValue(key).trim())
    put("password", values.getValue("password"))
    values["phone"]?.trim()?.takeIf { it.isNotEmpty() }?.let { put("phone", it) }
}

@Composable
internal fun PasswordField(value: String, change: (String) -> Unit, label: String, enabled: Boolean = true,
                          error: String? = null, hint: String? = null) {
    var show by remember { mutableStateOf(false) }
    OutlinedTextField(value, change, label = { Text(label) }, singleLine = true, enabled = enabled,
        modifier = Modifier.fillMaxWidth(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
        visualTransformation = if (show) VisualTransformation.None else PasswordVisualTransformation(),
        trailingIcon = { TextButton(onClick = { show = !show }, enabled = enabled) { Text(if (show) "Ocultar" else "Mostrar") } },
        isError = error != null, supportingText = (error ?: hint)?.let { { Text(it) } })
}

@Composable
internal fun RegistrationDialog(submit: suspend (JSONObject) -> Unit, dismiss: () -> Unit) {
    val values = remember { mutableStateMapOf<String, String>() }
    var attempted by remember { mutableStateOf(false) }
    var serverError by remember { mutableStateOf("") }
    var busy by remember { mutableStateOf(false) }
    val errors = if (attempted) registrationErrors(values) else emptyMap()
    val scope = rememberCoroutineScope()
    AlertDialog(onDismissRequest = { if (!busy) dismiss() },
        properties = DialogProperties(dismissOnClickOutside = false), title = { Text("Crear cuenta") },
        text = { Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            for ((key, label) in fields) {
                val change: (String) -> Unit = { values[key] = it; serverError = "" }
                val value = values[key].orEmpty()
                if (key == "password" || key == "confirm")
                    PasswordField(value, change, label, !busy, errors[key], if (key == "password") PASSWORD_HELP else null)
                else OutlinedTextField(value, change, label = { Text(label) }, modifier = Modifier.fillMaxWidth(),
                    singleLine = true, enabled = !busy, isError = key in errors,
                    supportingText = errors[key]?.let { { Text(it) } },
                    keyboardOptions = KeyboardOptions(keyboardType = when (key) {
                        "email" -> KeyboardType.Email; "phone" -> KeyboardType.Phone; else -> KeyboardType.Text
                    }))
            }
            if (busy) LinearProgressIndicator(Modifier.fillMaxWidth())
        } }, confirmButton = { Column(horizontalAlignment = androidx.compose.ui.Alignment.End) {
            if (serverError.isNotEmpty()) Text(serverError, color = MaterialTheme.colorScheme.error)
            else if (errors.isNotEmpty()) Text("Revisa: " + fields.filter { it.first in errors }.joinToString { it.second }, color = MaterialTheme.colorScheme.error)
            TextButton(enabled = !busy, onClick = {
                attempted = true
                if (registrationErrors(values).isEmpty()) {
                    busy = true
                    scope.launch {
                        try { submit(registrationBody(values)); dismiss() }
                        catch (failure: ApiFailure) { serverError = failure.message ?: "No se pudo crear la cuenta." }
                        finally { busy = false }
                    }
                }
            }) { Text("Crear cuenta") }
        } }, dismissButton = { TextButton(enabled = !busy, onClick = dismiss) { Text("Cancelar") } })
}
