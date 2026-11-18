package org.trackpet.demo

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.material3.*
import androidx.compose.ui.graphics.Color

class MainActivity : ComponentActivity() {
    private val api get() = (application as TrackPetApp).api
    private val permissions = registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) {
        if (canDetect()) startDetection()
        else api.prefs.edit().putBoolean("detection", false)
            .putString("detectionStatus", "Concede Bluetooth y ubicación precisa para detectar collares.").apply()
    }
    private fun canDetect() = listOf(Manifest.permission.BLUETOOTH_SCAN, Manifest.permission.BLUETOOTH_CONNECT,
        Manifest.permission.ACCESS_FINE_LOCATION).all { checkSelfPermission(it) == PackageManager.PERMISSION_GRANTED }
    fun enableDetection(enabled: Boolean) {
        api.prefs.edit().putBoolean("detection", enabled).apply()
        if (!enabled) { stopService(Intent(this, BeaconService::class.java)); return }
        if (canDetect()) startDetection()
        else {
            val required = mutableListOf(Manifest.permission.BLUETOOTH_SCAN, Manifest.permission.BLUETOOTH_CONNECT,
                Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION)
            if (Build.VERSION.SDK_INT >= 33) required.add(Manifest.permission.POST_NOTIFICATIONS)
            permissions.launch(required.toTypedArray())
        }
    }
    fun startDetection() {
        if (!api.prefs.getBoolean("signedIn", false) || !api.prefs.getBoolean("detection", false)) return
        if (!canDetect()) {
            api.prefs.edit().putString("detectionStatus", "Revisa los permisos de Bluetooth y ubicación.").apply()
            return
        }
        try { startForegroundService(Intent(this, BeaconService::class.java)) }
        catch (error: Exception) { api.prefs.edit().putString("detectionStatus", error.message).apply() }
    }
    override fun onResume() { super.onResume(); startDetection() }
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme(colorScheme = trackPetColors) {
                TrackPetScreen(api, this)
            }
        }
    }
}

// Use the web pages' amber, cocoa, espresso and paper tones across Material components.
private val espresso = Color(0xFF2A1810)
private val cocoa = Color(0xFF6B4A34)
private val paper = Color(0xFFFFF8EE)
private val amber = Color(0xFFE8873A)
private val trackPetColors = lightColorScheme(
    primary = Color(0xFF904214), onPrimary = Color.White,
    primaryContainer = amber, onPrimaryContainer = espresso, inversePrimary = amber,
    secondary = cocoa, onSecondary = Color.White, secondaryContainer = Color(0xFFFFD9B3), onSecondaryContainer = espresso,
    tertiary = Color(0xFF904214), onTertiary = Color.White, tertiaryContainer = Color(0xFFF6DEC2), onTertiaryContainer = espresso,
    background = Color(0xFFF6E9DB), onBackground = espresso, surface = paper, onSurface = espresso,
    surfaceVariant = Color(0xFFF1DFC7), onSurfaceVariant = cocoa, surfaceTint = Color(0xFF904214),
    surfaceContainerLowest = Color.White, surfaceContainerLow = paper, surfaceContainer = Color(0xFFFFF6E9),
    surfaceContainerHigh = Color(0xFFF8EAD7), surfaceContainerHighest = Color(0xFFF1DFC7),
    surfaceBright = paper, surfaceDim = Color(0xFFE2C9AD), outline = Color(0xFF876D57), outlineVariant = Color(0xFFE2C9AD),
    inverseSurface = Color(0xFF3B2417), inverseOnSurface = paper,
    error = Color(0xFF8B1E1E), onError = Color.White, errorContainer = Color(0xFFFADDC8), onErrorContainer = Color(0xFF681414)
)
