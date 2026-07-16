package com.mzeevtu.apprelease.util

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.navigation.NavController
import androidx.navigation.NavDirections
import com.google.android.material.snackbar.Snackbar
import java.text.NumberFormat
import java.util.Locale

fun View.show() { visibility = View.VISIBLE }
fun View.hide() { visibility = View.GONE }
fun View.invisible() { visibility = View.INVISIBLE }

fun Double.toNaira(): String {
    val fmt = NumberFormat.getInstance(Locale.US)
    fmt.minimumFractionDigits = 2
    fmt.maximumFractionDigits = 2
    return "₦${fmt.format(this)}"
}

fun Context.showToast(msg: String) = Toast.makeText(this, msg, Toast.LENGTH_SHORT).show()

fun View.showSnack(msg: String, action: String? = null, onAction: (() -> Unit)? = null) {
    val s = Snackbar.make(this, msg, Snackbar.LENGTH_LONG)
    if (action != null && onAction != null) s.setAction(action) { onAction() }
    s.show()
}

fun Context.copyToClipboard(label: String, text: String) {
    val cm = getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
    cm.setPrimaryClip(ClipData.newPlainText(label, text))
    showToast("Copied to clipboard")
}

/**
 * Safe wrapper around [NavController.navigate] to prevent crashes caused by rapid/double taps
 * or stale click handlers that trigger navigation after the destination has already changed.
 */
fun NavController.safeNavigate(resId: Int, args: Bundle? = null) {
    try {
        navigate(resId, args)
    } catch (_: IllegalArgumentException) {}
}

fun NavController.safeNavigate(directions: NavDirections) {
    try {
        navigate(directions)
    } catch (_: IllegalArgumentException) {}
}
