# Gson uses reflection to (de)serialize model fields by name — keep field names intact
# or every API response silently fails to parse into these classes after obfuscation.
-keep class com.payhub.guest.data.model.** { *; }
-keep class com.payhub.guest.api.** { *; }
-keepattributes Signature
-keepattributes *Annotation*
-keepattributes EnclosingMethod
-keepattributes InnerClasses

# Gson
-keep class com.google.gson.stream.** { *; }
-keep class sun.misc.Unsafe { *; }
-keep class com.google.gson.reflect.TypeToken
-keep class * extends com.google.gson.reflect.TypeToken
-dontwarn sun.misc.**

# Retrofit / OkHttp (bundled consumer-rules cover most of this; kept for older AGP/R8 configs)
-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn retrofit2.**
-keepattributes Exceptions
-keepclasseswithmembers class * {
    @retrofit2.http.* <methods>;
}
