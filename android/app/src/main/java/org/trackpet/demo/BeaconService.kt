package org.trackpet.demo

import android.Manifest
import android.annotation.SuppressLint
import android.app.*
import android.bluetooth.BluetoothManager
import android.bluetooth.BluetoothAdapter
import android.bluetooth.le.*
import android.content.*
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.location.Location
import android.location.LocationManager
import android.location.LocationRequest
import android.os.*
import kotlinx.coroutines.*
import org.json.JSONObject
import java.util.UUID
import java.util.concurrent.atomic.AtomicBoolean
import kotlin.coroutines.resume

/** Obtains a fresh fix only when requested by a confirmed lost-pet encounter or a user action. */
suspend fun currentLocation(context: Context): Location = withTimeout(25000) {
    check(context.checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
        context.checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED) { "Concede permiso de ubicación." }
    val manager = context.getSystemService(LocationManager::class.java)
    val provider = listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
        .firstOrNull { manager.isProviderEnabled(it) } ?: error("Activa la ubicación del teléfono.")
    suspendCancellableCoroutine { continuation ->
        val cancellation = CancellationSignal()
        continuation.invokeOnCancellation { cancellation.cancel() }
        val request = LocationRequest.Builder(0).setQuality(LocationRequest.QUALITY_HIGH_ACCURACY)
            .setDurationMillis(20000).setMaxUpdates(1).build()
        try {
            manager.getCurrentLocation(provider, request, cancellation, context.mainExecutor) { location ->
                if (!continuation.isActive) return@getCurrentLocation
                if (location == null || SystemClock.elapsedRealtimeNanos() - location.elapsedRealtimeNanos > 60_000_000_000L) {
                    continuation.cancel(IllegalStateException("No se obtuvo una ubicación reciente. Intenta de nuevo."))
                } else continuation.resume(location)
            }
        } catch (error: Exception) { continuation.cancel(error) }
    }
}

// Activity checks permissions before starting. Every radio operation also handles permission revocation.
@SuppressLint("MissingPermission")
class BeaconService : Service() {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private val busy = AtomicBoolean(false)
    private val nextPet = mutableMapOf<String, Long>()
    private var nextUpload = 0L
    private var scanner: BluetoothLeScanner? = null
    private var sessionJob: Job? = null
    private val api get() = (application as TrackPetApp).api
    private val notifications get() = getSystemService(NotificationManager::class.java)
    private val bluetoothState = object : BroadcastReceiver() {
        override fun onReceive(context: Context, intent: Intent) {
            when (intent.getIntExtra(BluetoothAdapter.EXTRA_STATE, BluetoothAdapter.ERROR)) {
                BluetoothAdapter.STATE_OFF -> { scanner = null; status("Bluetooth desactivado") }
                BluetoothAdapter.STATE_ON -> {
                    try { listen() }
                    catch (error: Exception) { status(error.message ?: "Revisa Bluetooth y los permisos.") }
                }
            }
        }
    }

    override fun onBind(intent: Intent?) = null
    override fun onCreate() {
        super.onCreate()
        notifications.createNotificationChannel(NotificationChannel("detection", "Detección cercana", NotificationManager.IMPORTANCE_LOW))
        if (Build.VERSION.SDK_INT >= 33) {
            registerReceiver(bluetoothState, IntentFilter(BluetoothAdapter.ACTION_STATE_CHANGED), Context.RECEIVER_EXPORTED)
        } else {
            registerReceiver(bluetoothState, IntentFilter(BluetoothAdapter.ACTION_STATE_CHANGED))
        }
    }
    private fun notification(message: String): Notification {
        val open = PendingIntent.getActivity(this, 0, Intent(this, MainActivity::class.java), PendingIntent.FLAG_IMMUTABLE)
        val stop = PendingIntent.getService(this, 1, Intent(this, BeaconService::class.java).setAction("STOP"), PendingIntent.FLAG_IMMUTABLE)
        return Notification.Builder(this, "detection").setSmallIcon(R.drawable.ic_paw)
            .setContentTitle("TrackPet · Detección cercana").setContentText(message)
            .setContentIntent(open).setOngoing(true).addAction(Notification.Action.Builder(null, "Pausar", stop).build()).build()
    }
    private fun status(message: String) {
        api.prefs.edit().putString("detectionStatus", message).apply()
        notifications.notify(1, notification(message))
    }
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == "STOP") {
            api.prefs.edit().putBoolean("detection", false).apply()
            stopSelf()
            return START_NOT_STICKY
        }
        try {
            startForeground(1, notification("Escuchando collares cercanos"),
                ServiceInfo.FOREGROUND_SERVICE_TYPE_CONNECTED_DEVICE or ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION)
            if (!api.prefs.getBoolean("signedIn", false) || !api.prefs.getBoolean("detection", false)) {
                stopSelf()
                return START_NOT_STICKY
            }
            listen()
            if (sessionJob == null) {
                sessionJob = scope.launch {
                    while (isActive) {
                        try { api.call("session") }
                        catch (error: ApiFailure) {
                            if (error.code == 401) {
                                status("Inicia sesión para reanudar la detección.")
                                stopSelf()
                                return@launch
                            }
                        }
                        delay(10 * 60 * 1000L)
                    }
                }
            }
        } catch (error: Exception) {
            api.prefs.edit().putString("detectionStatus", error.message ?: "Revisa los permisos de detección.").apply()
            stopSelf()
        }
        // Resume from the next visible activity after a system kill; never request location from an unauthorized restart.
        return START_NOT_STICKY
    }
    private fun listen() {
        if (scanner != null) return
        val adapter = getSystemService(BluetoothManager::class.java).adapter
        require(adapter != null && adapter.isEnabled) { "Activa Bluetooth y vuelve a abrir TrackPet." }
        val radio = adapter.bluetoothLeScanner ?: error("Bluetooth no está disponible.")
        val filter = ScanFilter.Builder().setDeviceName("TrackPet").build()
        radio.startScan(listOf(filter), ScanSettings.Builder().setScanMode(ScanSettings.SCAN_MODE_LOW_POWER).build(), callback)
        scanner = radio
        status("Escuchando collares cercanos")
    }
    private val callback = object : ScanCallback() {
        override fun onScanFailed(errorCode: Int) {
            status("Bluetooth detuvo la detección ($errorCode). Abre TrackPet para reintentar.")
            stopSelf()
        }
        override fun onScanResult(callbackType: Int, result: ScanResult) {
            val uuids = result.scanRecord?.serviceUuids ?: return
            if (uuids.size != 1) return
            val petId = uuids.first().uuid.toString()
            val now = SystemClock.elapsedRealtime()
            if (now < nextUpload || now < (nextPet[petId] ?: 0L) || !busy.compareAndSet(false, true)) return
            if (nextPet.size > 200) nextPet.entries.removeAll { it.value <= now }
            nextPet[petId] = now + 60000
            scope.launch {
                try {
                    val report = api.call("report", params = mapOf("petId" to petId)) as JSONObject
                    if (report.text("status") != "open") return@launch
                    // A public lookup succeeds without a session, so check authentication before obtaining coordinates.
                    api.call("session")
                    val location = currentLocation(this@BeaconService)
                    val requestId = UUID.randomUUID().toString()
                    val body = json("latitude" to location.latitude, "longitude" to location.longitude)
                    val params = mapOf("reportId" to report.getString("reportId"), "clientRequestId" to requestId)
                    try { api.call("beaconSighting", "POST", body, params) }
                    catch (error: ApiFailure) {
                        if (error.code != 0 && error.code < 500) throw error
                        delay(2000)
                        api.call("beaconSighting", "POST", body, params)
                    }
                    nextPet[petId] = SystemClock.elapsedRealtime() + 120000
                    nextUpload = SystemClock.elapsedRealtime() + 5000
                    api.prefs.edit().putString("lastDetection", "${report.text("petName")} · ${java.time.Instant.now()}").apply()
                    status("Ubicación enviada para ${report.text("petName")}")
                } catch (error: ApiFailure) {
                    when (error.code) {
                        401 -> { status("Inicia sesión para reanudar la detección."); stopSelf() }
                        429 -> { nextUpload = SystemClock.elapsedRealtime() + error.retryAfter.coerceAtLeast(60) * 1000L; status("Esperando para enviar más detecciones") }
                        404, 409 -> status("Escuchando collares cercanos")
                        else -> status(error.message ?: "No se pudo comprobar la detección.")
                    }
                } catch (error: Exception) {
                    if (scope.isActive) status(error.message ?: "No se obtuvo una ubicación reciente.")
                } finally { busy.set(false) }
            }
        }
    }
    override fun onDestroy() {
        try { scanner?.stopScan(callback) } catch (_: SecurityException) { }
        scanner = null
        unregisterReceiver(bluetoothState)
        sessionJob?.cancel()
        scope.cancel()
        stopForeground(STOP_FOREGROUND_REMOVE)
        super.onDestroy()
    }
}
