package com.payhub.guest.ui.checkout

import android.annotation.SuppressLint
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import com.payhub.guest.ui.GuestViewModel
import com.payhub.guest.ui.theme.PhPrimary
import com.payhub.guest.ui.theme.PhPrimaryDark

/**
 * Loads the PayHub-hosted checkout URL returned by checkout-init.php directly — no local
 * gateway-SDK HTML/JS bridge is needed (unlike the legacy wallet-funding WebView flow) since
 * Guest Mode uses PayHub exclusively and checkout-init.php already returns a ready-to-load
 * hosted page. Completion is detected purely by URL: once the WebView navigates to
 * web/guest-payment-complete.php (the callback_url passed at checkout-init time), payment is
 * done from PayHub's perspective and we hand off to the Receipt screen, which polls
 * status.php for the authoritative, webhook-driven fulfillment result.
 */
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun CheckoutScreen(viewModel: GuestViewModel, onCancel: () -> Unit, onPaymentComplete: (reference: String) -> Unit) {
    val checkoutState by viewModel.checkoutState.collectAsState()
    val ready = checkoutState as? GuestViewModel.CheckoutState.Ready

    Box(modifier = Modifier.fillMaxSize().background(androidx.compose.ui.graphics.Brush.verticalGradient(listOf(PhPrimary, PhPrimaryDark)))) {
        if (ready != null) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { context ->
                    WebView(context).apply {
                        settings.javaScriptEnabled = true
                        settings.domStorageEnabled = true
                        webViewClient = object : WebViewClient() {
                            override fun onPageStarted(view: WebView?, url: String?, favicon: android.graphics.Bitmap?) {
                                super.onPageStarted(view, url, favicon)
                                if (url != null && url.contains("guest-payment-complete.php")) {
                                    onPaymentComplete(ready.reference)
                                }
                            }
                        }
                        loadUrl(ready.checkoutUrl)
                    }
                }
            )
        } else {
            Column(
                modifier = Modifier.fillMaxSize().padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = androidx.compose.foundation.layout.Arrangement.Center,
            ) {
                CircularProgressIndicator(color = Color.White)
                Text(
                    "Redirecting you to PayHub secure checkout…",
                    color = Color.White,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.padding(top = 16.dp),
                )
            }
        }
        TextButton(onClick = onCancel, modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 24.dp)) {
            Text("Cancel and go back", color = Color.White.copy(alpha = 0.85f))
        }
    }
}
