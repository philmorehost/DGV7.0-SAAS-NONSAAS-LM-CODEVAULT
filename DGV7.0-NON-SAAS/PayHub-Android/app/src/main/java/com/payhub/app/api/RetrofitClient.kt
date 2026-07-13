package com.payhub.app.api

import com.payhub.app.util.Constants
import com.google.gson.GsonBuilder
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.ResponseBody.Companion.toResponseBody
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

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

        val client = OkHttpClient.Builder()
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
