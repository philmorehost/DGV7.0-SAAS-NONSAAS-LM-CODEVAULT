package com.payhub.app.ui.splash

import android.animation.ObjectAnimator
import android.content.Intent
import android.os.Bundle
import android.view.animation.LinearInterpolator
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.payhub.app.R
import com.payhub.app.api.RetrofitClient
import com.payhub.app.databinding.ActivitySplashBinding
import com.payhub.app.ui.MainActivity
import com.payhub.app.ui.auth.LoginActivity
import com.payhub.app.util.AppUpdateManager
import com.payhub.app.util.PreferenceManager
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

class SplashActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySplashBinding
    private lateinit var prefs: PreferenceManager

    override fun onCreate(savedInstanceState: Bundle?) {
        // Apply saved dark mode preference before inflation
        prefs = PreferenceManager(this)
        val darkModeEnabled = prefs.getBoolean(com.payhub.app.util.Constants.KEY_DARK_MODE, false)
        androidx.appcompat.app.AppCompatDelegate.setDefaultNightMode(
            if (darkModeEnabled) androidx.appcompat.app.AppCompatDelegate.MODE_NIGHT_YES
            else androidx.appcompat.app.AppCompatDelegate.MODE_NIGHT_NO
        )

        super.onCreate(savedInstanceState)
        binding = ActivitySplashBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Spin logo once (360Â° in 900ms)
        ObjectAnimator.ofFloat(binding.ivLogo, "rotation", 0f, 360f).apply {
            duration = 900
            interpolator = LinearInterpolator()
            start()
        }

        lifecycleScope.launch {
            // Fetch site info to get dynamic branding
            try {
                val resp = RetrofitClient.getService().getSiteInfo()
                if (resp.isSuccessful) {
                    @Suppress("UNCHECKED_CAST")
                    val data = resp.body()?.get("data") as? Map<String, Any>
                    data?.let {
                        prefs.saveString(com.payhub.app.util.Constants.KEY_SITE_TITLE, it["site_title"] as? String ?: "PayHub")
                        prefs.saveString(com.payhub.app.util.Constants.KEY_LOGO_URL, it["logo_url"] as? String ?: "")
                        prefs.saveString(com.payhub.app.util.Constants.KEY_PRIMARY_COLOR, it["primary_color"] as? String ?: "#0d6efd")
                        val support = it["support"] as? Map<*, *>
                        prefs.saveString(com.payhub.app.util.Constants.KEY_SUPPORT_WHATSAPP, support?.get("whatsapp") as? String ?: "")
                        prefs.saveString(com.payhub.app.util.Constants.KEY_SUPPORT_EMAIL, support?.get("email") as? String ?: "")
                    }
                }
            } catch (_: Exception) {}

            delay(1000) // Ensure spin animation completes
            navigateNext()
        }
    }

    private fun navigateNext() {
        val target = when {
            !prefs.isLoggedIn() -> LoginActivity::class.java
            // When biometric is enabled, route through LoginActivity so the prompt is shown
            prefs.getBoolean(com.payhub.app.util.Constants.KEY_BIOMETRIC_ENABLED, false) -> LoginActivity::class.java
            else -> MainActivity::class.java
        }
        startActivity(Intent(this, target))
        finish()
    }
}

