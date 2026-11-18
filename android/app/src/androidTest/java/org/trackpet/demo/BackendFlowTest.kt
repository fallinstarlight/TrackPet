package org.trackpet.demo

import androidx.test.platform.app.InstrumentationRegistry
import androidx.test.ext.junit.runners.AndroidJUnit4
import kotlinx.coroutines.runBlocking
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith
import java.util.UUID

/** Run against a disposable backend using -e server http://10.0.2.2:PORT. */
@RunWith(AndroidJUnit4::class)
class BackendFlowTest {
    @Test fun accountPetAndBeaconRoundTrip() = runBlocking {
        val instrumentation=InstrumentationRegistry.getInstrumentation()
        val context=instrumentation.targetContext
        val api=Api(context)
        val server=InstrumentationRegistry.getArguments().getString("server")
            ?: error("Provide the disposable server URL as an instrumentation argument.")
        api.setServer(server)
        val username="mobile_"+UUID.randomUUID().toString().take(8)
        val password="TrackPetDemo123!"
        api.call("user","POST",json("firstName" to "Mobile","surname" to "Test","username" to username,
            "email" to "$username@example.test","password" to password))
        api.call("login","POST",json("username" to username,"password" to password))
        val profile=Api(context).call("session") as JSONObject
        assertEquals(username,profile.getString("username"))
        val pet=api.call("pet","POST",json("petName" to "Luna","petSpecies" to "Dog","petColour" to "Black")) as JSONObject
        val report=api.call("report","POST",json("petId" to pet.getString("petId"),"lastSeenArea" to "Demo park",
            "clientRequestId" to UUID.randomUUID().toString())) as JSONObject
        val lookup=api.call("report",params=mapOf("petId" to pet.getString("petId"))) as JSONObject
        assertEquals(report.getString("reportId"),lookup.getString("reportId"))
        val params=mapOf("reportId" to report.getString("reportId"),"clientRequestId" to UUID.randomUUID().toString())
        val point=json("latitude" to 19.4326,"longitude" to -99.1332)
        val sighting=api.call("beaconSighting","POST",point,params) as JSONObject
        val retry=api.call("beaconSighting","POST",point,params) as JSONObject
        assertEquals(sighting.getString("sightingId"),retry.getString("sightingId"))
        assertTrue(retry.getBoolean("replayed"))
        val tracking=api.call("tracking",params=mapOf("reportId" to report.getString("reportId"))) as JSONObject
        assertEquals("ble",tracking.getJSONObject("latestSighting").getString("source"))
        api.call("report","PATCH",json("status" to "recovered"),mapOf("reportId" to report.getString("reportId")))
        try {
            api.call("beaconSighting","POST",point,params+("clientRequestId" to UUID.randomUUID().toString()))
            fail("Closed report accepted a new point")
        } catch(error:ApiFailure) { assertEquals(409,error.code) }
        api.call("logout","POST")
        try { api.call("session");fail("Logged-out session remained usable") }
        catch(error:ApiFailure) { assertEquals(401,error.code) }
    }
}
