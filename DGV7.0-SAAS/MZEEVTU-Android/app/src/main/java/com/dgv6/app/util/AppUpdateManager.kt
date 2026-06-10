package com.dgv6.app.util

import android.app.DownloadManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.net.Uri
import android.os.Build
import android.os.Environment
import androidx.core.content.pm.PackageInfoCompat
import androidx.core.content.FileProvider
import com.dgv6.app.api.RetrofitClient
import com.google.android.material.dialog.MaterialAlertDialogBuilder
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File

/**
 * Checks the server for a newer APK version and, if found, prompts the user to
 * download and install it.  The APK file is hosted in the cPanel file manager
 * at a URL returned by web/api/app-update.php.
 *
 * Usage:
 *   AppUpdateManager(context).checkForUpdate(scope)
 */
class AppUpdateManager(private val context: Context) {

    fun checkForUpdate(scope: CoroutineScope) {
        scope.launch(Dispatchers.IO) {
            try {
                val resp = RetrofitClient.getService().checkAppUpdate()
                if (!resp.isSuccessful) return@launch
                val body = resp.body() ?: return@launch
                if ((body["status"] as? String) != "update_available") return@launch

                val latestCode = (body["version_code"] as? Number)?.toInt() ?: return@launch
                val latestName = body["version_name"] as? String ?: return@launch
                val apkUrl = body["apk_url"] as? String ?: return@launch
                val changelog = body["changelog"] as? String ?: ""

                @Suppress("DEPRECATION")
                val packageInfo = context.packageManager
                    .getPackageInfo(context.packageName, 0)
                val currentCode = PackageInfoCompat.getLongVersionCode(packageInfo).toInt()

                if (latestCode > currentCode) {
                    withContext(Dispatchers.Main) {
                        promptUpdate(latestName, apkUrl, changelog)
                    }
                }
            } catch (_: Exception) {
                // Silent — do not disrupt the user experience on network errors
            }
        }
    }

    private fun promptUpdate(versionName: String, apkUrl: String, changelog: String) {
        val message = buildString {
            append("Version $versionName is available.\n")
            if (changelog.isNotEmpty()) append("\nWhat's new:\n$changelog")
        }
        MaterialAlertDialogBuilder(context)
            .setTitle("App Update Available")
            .setMessage(message)
            .setPositiveButton("Update Now") { _, _ -> downloadAndInstall(apkUrl, versionName) }
            .setNegativeButton("Later", null)
            .show()
    }

    private fun downloadAndInstall(apkUrl: String, versionName: String) {
        val fileName = "mzeevtu-$versionName.apk"
        val destFile = File(
            context.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS),
            fileName
        )
        // Remove any previous partial download
        if (destFile.exists()) destFile.delete()

        val request = DownloadManager.Request(Uri.parse(apkUrl)).apply {
            setTitle("MZEEVTU Update")
            setDescription("Downloading version $versionName…")
            setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            setDestinationUri(Uri.fromFile(destFile))
            setAllowedOverMetered(true)
            setAllowedOverRoaming(true)
        }

        val dm = context.getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
        val downloadId = dm.enqueue(request)

        // Register a one-shot receiver that triggers the installer when download completes
        val receiver = object : BroadcastReceiver() {
            override fun onReceive(ctx: Context?, intent: Intent?) {
                val id = intent?.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1L) ?: -1L
                if (id != downloadId) return
                context.unregisterReceiver(this)
                installApk(destFile)
            }
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            context.registerReceiver(
                receiver,
                IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE),
                Context.RECEIVER_NOT_EXPORTED
            )
        } else {
            @Suppress("UnspecifiedRegisterReceiverFlag")
            context.registerReceiver(
                receiver,
                IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE)
            )
        }
    }

    private fun installApk(apkFile: File) {
        if (!apkFile.exists()) return
        val uri = FileProvider.getUriForFile(
            context,
            "${context.packageName}.fileprovider",
            apkFile
        )
        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(uri, "application/vnd.android.package-archive")
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_GRANT_READ_URI_PERMISSION
        }
        context.startActivity(intent)
    }
}
