package com.dgv6.app.ui.dashboard

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Bundle
import android.speech.RecognitionListener
import android.speech.RecognizerIntent
import android.speech.SpeechRecognizer
import android.view.MotionEvent
import android.widget.Button
import android.widget.EditText
import android.widget.ImageButton
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.dgv6.app.R
import com.dgv6.app.util.PreferenceManager
import kotlinx.coroutines.launch
import java.util.Locale

class AIChatActivity : AppCompatActivity() {
    private lateinit var tvLog: TextView
    private lateinit var etPrompt: EditText
    private lateinit var btnSend: Button
    private lateinit var btnMic: ImageButton
    private lateinit var prefs: PreferenceManager
    
    private val chatHistory = StringBuilder()
    private var speechRecognizer: SpeechRecognizer? = null
    private val REQUEST_RECORD_AUDIO_PERMISSION = 200

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_ai_chat)
        
        prefs = PreferenceManager(this)
        tvLog = findViewById(R.id.tv_chat_log)
        etPrompt = findViewById(R.id.et_prompt)
        btnSend = findViewById(R.id.btn_send)
        btnMic = findViewById(R.id.btn_mic)
        
        btnSend.setOnClickListener {
            val prompt = etPrompt.text.toString().trim()
            if (prompt.isNotEmpty()) {
                appendLog("You: $prompt")
                etPrompt.setText("")
                sendToAi(prompt)
            }
        }
        
        setupSpeechRecognizer()
    }
    
    private fun setupSpeechRecognizer() {
        if (SpeechRecognizer.isRecognitionAvailable(this)) {
            speechRecognizer = SpeechRecognizer.createSpeechRecognizer(this)
            val speechRecognizerIntent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH)
            speechRecognizerIntent.putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
            speechRecognizerIntent.putExtra(RecognizerIntent.EXTRA_LANGUAGE, Locale.getDefault())

            speechRecognizer?.setRecognitionListener(object : RecognitionListener {
                override fun onReadyForSpeech(params: Bundle?) {
                    etPrompt.hint = "Listening..."
                }
                override fun onBeginningOfSpeech() {}
                override fun onRmsChanged(rmsdB: Float) {}
                override fun onBufferReceived(buffer: ByteArray?) {}
                override fun onEndOfSpeech() {
                    etPrompt.hint = "Ask me anything..."
                    btnMic.alpha = 1.0f
                }
                override fun onError(error: Int) {
                    etPrompt.hint = "Ask me anything..."
                    btnMic.alpha = 1.0f
                    Toast.makeText(this@AIChatActivity, "Speech Recognition Error: $error", Toast.LENGTH_SHORT).show()
                }
                override fun onResults(results: Bundle?) {
                    val data = results?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION)
                    if (!data.isNullOrEmpty()) {
                        val spokenText = data[0]
                        etPrompt.setText(spokenText)
                        // Auto-send voice
                        btnSend.performClick()
                    }
                }
                override fun onPartialResults(partialResults: Bundle?) {}
                override fun onEvent(eventType: Int, params: Bundle?) {}
            })

            btnMic.setOnClickListener {
                if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
                    ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.RECORD_AUDIO), REQUEST_RECORD_AUDIO_PERMISSION)
                } else {
                    btnMic.alpha = 0.5f
                    speechRecognizer?.startListening(speechRecognizerIntent)
                }
            }
        } else {
            btnMic.setOnClickListener {
                Toast.makeText(this, "Speech Recognition not available", Toast.LENGTH_SHORT).show()
            }
        }
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == REQUEST_RECORD_AUDIO_PERMISSION && grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
            btnMic.performClick()
        }
    }
    
    private fun appendLog(msg: String) {
        chatHistory.append(msg).append("\n\n")
        tvLog.text = chatHistory.toString()
    }
    
    private fun sendToAi(prompt: String) {
        btnSend.isEnabled = false
        btnSend.text = "Thinking..."
        
        lifecycleScope.launch {
            try {
                val client = okhttp3.OkHttpClient()
                val json = """{"prompt": "", "page_context": "mobile_app"}"""
                val body = okhttp3.RequestBody.create(okhttp3.MediaType.parse("application/json"), json)
                
                val token = prefs.getToken() ?: ""
                val baseUrl = com.dgv6.app.util.Constants.BASE_URL
                val request = okhttp3.Request.Builder()
                    .url(baseUrl + "api/app-backend/ai-handler")
                    .addHeader("Authorization", "Bearer $token")
                    .post(body)
                    .build()
                    
                kotlinx.coroutines.Dispatchers.IO.invoke {
                    val response = client.newCall(request).execute()
                    val resStr = response.body()?.string()
                    kotlinx.coroutines.Dispatchers.Main.invoke {
                        if (response.isSuccessful && resStr != null) {
                            try {
                                val jsonObj = org.json.JSONObject(resStr)
                                if (jsonObj.optBoolean("success")) {
                                    appendLog("AI: " + jsonObj.optString("response"))
                                } else {
                                    appendLog("Error: " + jsonObj.optString("error", "Unknown error"))
                                }
                            } catch (e: Exception) {
                                appendLog("Error parsing response")
                            }
                        } else {
                            appendLog("Network Error: " + response.code())
                        }
                        btnSend.isEnabled = true
                        btnSend.text = "Send"
                    }
                }
            } catch (e: Exception) {
                appendLog("Error: " + e.message)
                btnSend.isEnabled = true
                btnSend.text = "Send"
            }
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        speechRecognizer?.destroy()
    }
}
