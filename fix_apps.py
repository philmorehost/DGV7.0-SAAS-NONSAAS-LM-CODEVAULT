import os
import shutil
import re

apps = [
    "DGV7.0-SAAS/MZEEVTU-Android",
    "DGV7.0-NON-SAAS/DG6-Android",
    "DGV7.0-NON-SAAS/MZEEVTU-Android",
    "DGV7.0-NON-SAAS/PayHub-Android"
]

base_dir = r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM"

# Source files from DG7-Android
dg7_dir = os.path.join(base_dir, "DGV7.0-SAAS/DG7-Android")
src_ai_chat = os.path.join(dg7_dir, "app/src/main/java/com/dgv6/app/ui/dashboard/AIChatActivity.kt")
src_layout = os.path.join(dg7_dir, "app/src/main/res/layout/activity_ai_chat.xml")

with open(src_ai_chat, "r", encoding="utf-8") as f:
    ai_chat_content = f.read()

for app in apps:
    app_dir = os.path.join(base_dir, app)
    if not os.path.exists(app_dir):
        print(f"Skipping {app}, does not exist.")
        continue
    
    # Get namespace from app/build.gradle
    build_gradle = os.path.join(app_dir, "app/build.gradle")
    namespace = "com.dgv6.app"
    if os.path.exists(build_gradle):
        with open(build_gradle, "r", encoding="utf-8") as f:
            content = f.read()
            match = re.search(r'namespace\s+["\']([^"\']+)["\']', content)
            if match:
                namespace = match.group(1)
    print(f"Processing {app} with namespace {namespace}")
    
    # Paths for target app
    pkg_path = namespace.replace(".", "/")
    target_ai_chat = os.path.join(app_dir, f"app/src/main/java/{pkg_path}/ui/dashboard/AIChatActivity.kt")
    target_layout = os.path.join(app_dir, "app/src/main/res/layout/activity_ai_chat.xml")
    target_pref = os.path.join(app_dir, f"app/src/main/java/{pkg_path}/util/PreferenceManager.kt")
    target_api = os.path.join(app_dir, f"app/src/main/java/{pkg_path}/api/VtuApiService.kt")
    
    # 1. Copy AIChatActivity.kt with correct package name
    if os.path.exists(os.path.dirname(target_ai_chat)):
        new_content = ai_chat_content.replace("package com.dgv6.app.ui.dashboard", f"package {namespace}.ui.dashboard")
        new_content = new_content.replace("com.dgv6.app.R", f"{namespace}.R")
        new_content = new_content.replace("com.dgv6.app.util.", f"{namespace}.util.")
        new_content = new_content.replace("com.dgv6.app.api.", f"{namespace}.api.")
        with open(target_ai_chat, "w", encoding="utf-8") as f:
            f.write(new_content)
    
    # 2. Copy activity_ai_chat.xml
    if os.path.exists(os.path.dirname(target_layout)):
        shutil.copy2(src_layout, target_layout)
        # Update tools:context
        with open(target_layout, "r", encoding="utf-8") as f:
            layout_content = f.read()
        layout_content = layout_content.replace("com.dgv6.app.ui.dashboard.AIChatActivity", f"{namespace}.ui.dashboard.AIChatActivity")
        with open(target_layout, "w", encoding="utf-8") as f:
            f.write(layout_content)
            
    # 3. Patch PreferenceManager.kt
    if os.path.exists(target_pref):
        with open(target_pref, "r", encoding="utf-8") as f:
            pref_content = f.read()
        if "getAiVoiceStatus" not in pref_content:
            addition = """
    fun saveAiVoiceStatus(status: Int) = prefs.edit().putInt("ai_voice_status", status).apply()
    fun getAiVoiceStatus(): Int = prefs.getInt("ai_voice_status", 0)

    fun saveTrustScore(score: Int) = prefs.edit().putInt("ai_trust_score", score).apply()
    fun getTrustScore(): Int = prefs.getInt("ai_trust_score", 50)
"""
            pref_content = pref_content.replace("}", addition + "\n}")
            with open(target_pref, "w", encoding="utf-8") as f:
                f.write(pref_content)
                
    # 4. Patch VtuApiService.kt
    if os.path.exists(target_api):
        with open(target_api, "r", encoding="utf-8") as f:
            api_content = f.read()
        if "parseAiIntent" not in api_content:
            addition = """
    @POST("api/app-backend/ai-handler")
    suspend fun parseAiIntent(
        @Header("Authorization") token: String,
        @Body body: Map<String, Any>
    ): Response<Map<String, Any>>
"""
            api_content = api_content.replace("}", addition + "\n}")
            
        # Ensure purchaseBetting exists, but ONLY ONCE
        if "purchaseBetting" not in api_content:
            addition2 = """
    @POST("api/app-backend/vtu-action")
    suspend fun purchaseBetting(@Body body: @JvmSuppressWildcards Map<String, Any>): Response<Map<String, Any>>
"""
            api_content = api_content.replace("}", addition2 + "\n}")
            
        with open(target_api, "w", encoding="utf-8") as f:
            f.write(api_content)
            
print("Patching complete!")
