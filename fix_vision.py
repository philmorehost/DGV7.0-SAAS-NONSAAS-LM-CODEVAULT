import os
import re

apps = [
    "DGV7.0-SAAS/DG7-Android",
    "DGV7.0-SAAS/MZEEVTU-Android",
    "DGV7.0-NON-SAAS/DG6-Android",
    "DGV7.0-NON-SAAS/MZEEVTU-Android",
    "DGV7.0-NON-SAAS/PayHub-Android"
]

base_dir = r"C:\Users\User\Downloads\DGV7.0 SAAS and NON-SAAS and LM"

for app in apps:
    app_dir = os.path.join(base_dir, app)
    if not os.path.exists(app_dir):
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
                
    pkg_path = namespace.replace(".", "/")
    target_api = os.path.join(app_dir, f"app/src/main/java/{pkg_path}/api/VtuApiService.kt")
    
    if os.path.exists(target_api):
        with open(target_api, "r", encoding="utf-8") as f:
            api_content = f.read()
        if "parseAiVision" not in api_content:
            addition = """
    @POST("api/app-backend/ai-vision")
    suspend fun parseAiVision(
        @Header("Authorization") token: String,
        @Body body: Map<String, Any>
    ): retrofit2.Response<Map<String, Any>>
"""
            # Need to be careful because some apps might not import retrofit2.Response properly if they don't use it, but they already use Response<Map<String, Any>>.
            api_content = api_content.replace("}", addition + "\n}")
            with open(target_api, "w", encoding="utf-8") as f:
                f.write(api_content)
        print(f"Patched {app}")
