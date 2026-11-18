package org.trackpet.demo

import android.annotation.SuppressLint
import android.view.MotionEvent
import android.webkit.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.viewinterop.AndroidView
import org.json.JSONArray
import java.io.ByteArrayInputStream

private const val MAP_HOST = "appassets.androidplatform.net"
private const val MAP_URL = "https://$MAP_HOST/map/index.html"
private val mapAssets = mapOf("index.html" to "text/html", "map.js" to "text/javascript",
    "leaflet.js" to "text/javascript", "leaflet.css" to "text/css")

@SuppressLint("SetJavaScriptEnabled", "ClickableViewAccessibility")
@Composable
fun PetMap(points: JSONArray, modifier: Modifier, onPick: ((Double, Double) -> Unit)? = null) {
    val picker by rememberUpdatedState(onPick)
    val command = "window.renderPoints && window.renderPoints(${points}, ${onPick != null})"
    AndroidView(modifier = modifier, factory = { context ->
        WebView(context).apply {
            settings.javaScriptEnabled = true
            settings.allowFileAccess = false
            settings.allowContentAccess = false
            settings.mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
            settings.userAgentString += " TrackPet-Android (${context.packageName})"
            webViewClient = object : WebViewClient() {
                override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest) = true
                override fun shouldInterceptRequest(view: WebView, request: WebResourceRequest): WebResourceResponse? {
                    val uri = request.url
                    if (uri.scheme == "https" && uri.host == "tile.openstreetmap.org") return null
                    val name = uri.path?.removePrefix("/map/").orEmpty()
                    if (uri.scheme == "https" && uri.host == MAP_HOST && uri.path == "/map/$name") {
                        val mime = mapAssets[name]
                        if (mime != null) return WebResourceResponse(mime, "UTF-8", context.assets.open("map/$name"))
                    }
                    return WebResourceResponse("text/plain", "UTF-8", 404, "Not found", emptyMap(), ByteArrayInputStream(byteArrayOf()))
                }
                override fun onPageFinished(view: WebView, url: String) {
                    if (url == MAP_URL) (view.tag as? String)?.let { view.evaluateJavascript(it, null) }
                }
            }
            addJavascriptInterface(object {
                @JavascriptInterface fun select(latitude: Double, longitude: Double) {
                    if (latitude in -90.0..90.0 && longitude in -180.0..180.0)
                        post { picker?.invoke(latitude, longitude) }
                }
            }, "TrackPetMap")
            setOnTouchListener { view, event ->
                view.parent.requestDisallowInterceptTouchEvent(event.actionMasked != MotionEvent.ACTION_UP && event.actionMasked != MotionEvent.ACTION_CANCEL)
                false
            }
            loadUrl(MAP_URL)
        }
    }, onRelease = { it.removeJavascriptInterface("TrackPetMap"); it.destroy() }, update = { view ->
        if (view.tag != command) {
            view.tag = command
            view.evaluateJavascript(command, null)
        }
    })
}
