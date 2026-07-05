# Android WebView Workforce Requirements

The Laravel workforce page is WebView-ready, but Android must grant and forward native permissions. Use an HTTPS production URL. Camera and browser geolocation are not reliable on plain HTTP or local IP URLs.

## Manifest

Add these permissions to `AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />

<uses-feature
    android:name="android.hardware.camera.any"
    android:required="false" />
<uses-feature
    android:name="android.hardware.location.gps"
    android:required="false" />
```

Keep cleartext disabled in production:

```xml
<application
    android:usesCleartextTraffic="false">
```

## WebView Settings

Enable JavaScript, DOM storage, cookies, geolocation, media playback, and file access only where required:

```kotlin
CookieManager.getInstance().setAcceptCookie(true)

webView.settings.apply {
    javaScriptEnabled = true
    domStorageEnabled = true
    databaseEnabled = true
    setGeolocationEnabled(true)
    mediaPlaybackRequiresUserGesture = false
    allowFileAccess = true
    allowContentAccess = true
}
```

## Runtime Permissions

Request these permissions before loading the workforce page:

```kotlin
arrayOf(
    Manifest.permission.CAMERA,
    Manifest.permission.ACCESS_FINE_LOCATION,
    Manifest.permission.ACCESS_COARSE_LOCATION,
)
```

For Android 12 and newer, request fine and coarse location together. The employee should choose precise location.

## WebChromeClient

The wrapper must approve website camera and geolocation requests only for the trusted application host:

```kotlin
webView.webChromeClient = object : WebChromeClient() {
    override fun onPermissionRequest(request: PermissionRequest) {
        runOnUiThread {
            val trusted = Uri.parse(APP_URL).host == request.origin.host
            val cameraRequested = request.resources.contains(PermissionRequest.RESOURCE_VIDEO_CAPTURE)

            if (trusted && cameraRequested &&
                ContextCompat.checkSelfPermission(
                    this@MainActivity,
                    Manifest.permission.CAMERA
                ) == PackageManager.PERMISSION_GRANTED
            ) {
                request.grant(arrayOf(PermissionRequest.RESOURCE_VIDEO_CAPTURE))
            } else {
                request.deny()
            }
        }
    }

    override fun onGeolocationPermissionsShowPrompt(
        origin: String,
        callback: GeolocationPermissions.Callback
    ) {
        val trusted = Uri.parse(APP_URL).host == Uri.parse(origin).host
        val granted = ContextCompat.checkSelfPermission(
            this@MainActivity,
            Manifest.permission.ACCESS_FINE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED

        callback.invoke(origin, trusted && granted, false)
    }
}
```

Also implement `onShowFileChooser` so task proof `<input type="file" capture="environment">` can open the Android camera or gallery.

At minimum, forward the chooser callback to an `ActivityResultLauncher`:

```kotlin
private var fileCallback: ValueCallback<Array<Uri>>? = null

private val fileChooser = registerForActivityResult(
    ActivityResultContracts.StartActivityForResult()
) { result ->
    val uris = WebChromeClient.FileChooserParams.parseResult(
        result.resultCode,
        result.data
    )
    fileCallback?.onReceiveValue(uris)
    fileCallback = null
}

override fun onShowFileChooser(
    webView: WebView,
    callback: ValueCallback<Array<Uri>>,
    params: FileChooserParams
): Boolean {
    fileCallback?.onReceiveValue(null)
    fileCallback = callback

    return try {
        fileChooser.launch(params.createIntent())
        true
    } catch (error: ActivityNotFoundException) {
        fileCallback = null
        callback.onReceiveValue(null)
        false
    }
}
```

## Network and Security

- Use a valid public HTTPS certificate. Do not bypass SSL errors in `onReceivedSslError`.
- Keep the workforce URL on one stable host so Laravel session cookies remain available.
- Set production `APP_URL=https://your-domain.example`.
- Set `SESSION_SECURE_COOKIE=true` when using HTTPS.
- Do not enable mixed content unless a legacy dependency absolutely requires it.
- Show an Android offline screen when `ConnectivityManager` reports no validated network.

## Lifecycle

Call these from the activity lifecycle:

```kotlin
override fun onResume() {
    super.onResume()
    webView.onResume()
}

override fun onPause() {
    webView.onPause()
    super.onPause()
}

override fun onDestroy() {
    webView.destroy()
    super.onDestroy()
}
```

The workforce page automatically restarts camera and GPS checks when the WebView returns to the foreground.
