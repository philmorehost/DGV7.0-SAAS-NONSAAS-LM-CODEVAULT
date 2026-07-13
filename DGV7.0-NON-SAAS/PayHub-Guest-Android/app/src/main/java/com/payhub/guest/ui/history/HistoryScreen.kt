package com.payhub.guest.ui.history

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.payhub.guest.ui.theme.CText
import com.payhub.guest.ui.theme.CText2

/**
 * Guest Mode intentionally stores no server-side transaction history (confirmed product
 * decision — see the approved mockup's History screen copy). This is always an empty state
 * pointing the guest to their receipt, never a fabricated/sample transaction list.
 */
@Composable
fun HistoryScreen() {
    Column(
        modifier = Modifier.fillMaxSize().padding(horizontal = 32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = androidx.compose.foundation.layout.Arrangement.Center,
    ) {
        Text("Transaction History", fontWeight = FontWeight.Bold, fontSize = 20.sp, modifier = Modifier.padding(bottom = 40.dp))
        Icon(Icons.Filled.Receipt, contentDescription = null, tint = CText2, modifier = Modifier.padding(bottom = 12.dp))
        Text("No saved history", fontWeight = FontWeight.Bold, color = CText, modifier = Modifier.padding(bottom = 6.dp))
        Text(
            "Guest transactions aren't stored on our servers. Download or email your receipt right after payment to keep a copy!",
            fontSize = 13.sp,
            color = CText2,
            textAlign = TextAlign.Center,
        )
    }
}
