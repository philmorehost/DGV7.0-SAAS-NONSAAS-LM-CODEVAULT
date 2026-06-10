package com.datagifting.app.api

import com.datagifting.app.util.Constants
import com.google.gson.GsonBuilder
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.ResponseBody.Companion.toResponseBody
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.security.SecureRandom
import java.security.cert.X509Certificate
import java.util.concurrent.TimeUnit
import javax.net.ssl.SSLContext
import javax.net.ssl.TrustManager
import javax.net.ssl.X509TrustManager

object RetrofitClient {

    @Volatile private var retrofit: Retrofit? = null

    fun getService(): VtuApiService {
        if (retrofit == null) {
            synchronized(this) {
                if (retrofit == null) {
                    retrofit = buildRetrofit()
                }
            }
        }
        return retrofit!!.create(VtuApiService::class.java)
    }

    /** Call when BASE_URL changes (first-run setup) */
    fun reset() { retrofit = null }

    private fun buildRetrofit(): Retrofit {
        val baseUrl = Constants.BASE_URL

        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }

        // Trust all certificates to handle servers with incomplete certificate chains
        val trustAllCerts = arrayOf<TrustManager>(object : X509TrustManager {
            override fun checkClientTrusted(chain: Array<X509Certificate>, authType: String) {}
            override fun checkServerTrusted(chain: Array<X509Certificate>, authType: String) {}
            override fun getAcceptedIssuers(): Array<X509Certificate> = arrayOf()
        })
        val sslContext = SSLContext.getInstance("TLS")
        sslContext.init(null, trustAllCerts, SecureRandom())

        val client = OkHttpClient.Builder()
            .sslSocketFactory(sslContext.socketFactory, trustAllCerts[0] as X509TrustManager)
            .hostnameVerifier { _, _ -> true }
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                val req = chain.request().newBuilder()
                    .addHeader("X-App-Source", Constants.APP_SOURCE_HEADER)
                    .addHeader("Accept", "application/json")
                    .addHeader("Content-Type", "application/json")
                    .build()
                chain.proceed(req)
            }
            // Safety interceptor: convert non-JSON server responses (HTML error pages, PHP notices)
            // into a well-formed JSON error so Gson never crashes with setLenient errors
            .addInterceptor { chain ->
                val response = chain.proceed(chain.request())
                val contentType = response.header("Content-Type") ?: ""
                if (!contentType.contains("application/json", ignoreCase = true)) {
                    val rawBody = response.body?.string() ?: ""
                    // Extract any readable text from HTML for debugging
                    val stripped = rawBody
                        .replace(Regex("<[^>]*>"), " ")
                        .replace(Regex("\\s+"), " ")
                        .trim()
                        .take(200)
                    val safeJson = """{"status":"error","code":"SERVER_ERROR","message":"Server returned an unexpected response. Please try again.","debug":"${stripped.replace("\"","'")}"}"""
                    val jsonMediaType = "application/json; charset=utf-8".toMediaType()
                    response.newBuilder()
                        .body(safeJson.toResponseBody(jsonMediaType))
                        .header("Content-Type", "application/json; charset=utf-8")
                        .build()
                } else {
                    response
                }
            }
            .addInterceptor(logging)
            .build()

        // Use lenient Gson to tolerate minor JSON deviations
        val gson = GsonBuilder().setLenient().create()

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create(gson))
            .build()
    }
}
