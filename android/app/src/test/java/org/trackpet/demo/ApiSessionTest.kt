package org.trackpet.demo

import kotlinx.coroutines.runBlocking
import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.annotation.Config
import java.util.UUID

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [31])
class ApiSessionTest {
    @Test fun android12ClientPreservesSessionsAndSightingRetries() = runBlocking {
        val server=System.getenv("TRACKPET_TEST_SERVER")
        org.junit.Assume.assumeTrue("Set TRACKPET_TEST_SERVER to a disposable backend", !server.isNullOrEmpty())
        val context=RuntimeEnvironment.getApplication()
        val api=Api(context)
        api.setServer(server!!)
        val username="api_"+UUID.randomUUID().toString().take(8)
        val password="TrackPetDemo123!"
        api.call("user","POST",json("firstName" to "Api","surname" to "Test","username" to username,
            "email" to "$username@example.test","password" to password))
        api.call("login","POST",json("username" to username,"password" to password))
        val restored=Api(context)
        val profile=restored.call("session") as JSONObject
        assertEquals(username,profile.getString("username"))
        val pet=restored.call("pet","POST",json("petName" to "Luna","petSpecies" to "Dog","petColour" to "Black")) as JSONObject
        val report=restored.call("report","POST",json("petId" to pet.getString("petId"),
            "lastSeenArea" to "Demo park","clientRequestId" to UUID.randomUUID().toString())) as JSONObject
        val params=mapOf("reportId" to report.getString("reportId"),"clientRequestId" to UUID.randomUUID().toString())
        val point=json("latitude" to 0,"longitude" to 0)
        val sighting=restored.call("beaconSighting","POST",point,params) as JSONObject
        val retry=restored.call("beaconSighting","POST",point,params) as JSONObject
        assertEquals(sighting.getString("sightingId"),retry.getString("sightingId"))
        assertTrue(retry.getBoolean("replayed"))
        val tracking=restored.call("tracking",params=mapOf("reportId" to report.getString("reportId"))) as JSONObject
        assertEquals("ble",tracking.getJSONObject("latestSighting").getString("source"))
        restored.call("report","PATCH",json("status" to "recovered"),mapOf("reportId" to report.getString("reportId")))
        try {
            restored.call("beaconSighting","POST",point,params+("clientRequestId" to UUID.randomUUID().toString()))
            fail("Closed report accepted new point")
        } catch(error:ApiFailure) { assertEquals(409,error.code) }
        restored.call("logout","POST")
        try { restored.call("session");fail("Logout failed") }
        catch(error:ApiFailure) { assertEquals(401,error.code) }
        assertFalse(api.prefs.getBoolean("signedIn",false))
    }
}
