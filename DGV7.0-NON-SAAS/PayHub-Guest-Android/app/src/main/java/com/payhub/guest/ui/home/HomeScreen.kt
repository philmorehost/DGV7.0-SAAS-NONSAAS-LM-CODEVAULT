package com.payhub.guest.ui.home

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Casino
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.NotificationsNone
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.School
import androidx.compose.material.icons.filled.Tv
import androidx.compose.material.icons.filled.Wifi
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.payhub.guest.ui.theme.CAirtime
import com.payhub.guest.ui.theme.CBetting
import com.payhub.guest.ui.theme.CCable
import com.payhub.guest.ui.theme.CData
import com.payhub.guest.ui.theme.CElectric
import com.payhub.guest.ui.theme.CExam
import com.payhub.guest.ui.theme.CText
import com.payhub.guest.ui.theme.CText2
import com.payhub.guest.ui.theme.PhPrimary
import com.payhub.guest.ui.theme.PhPrimaryDark

data class QuickAction(val label: String, val icon: ImageVector, val color: Color, val onClick: () -> Unit)

@Composable
fun HomeScreen(
    onOpenService: (String) -> Unit,
    onOpenHistory: () -> Unit,
) {
    val actions = listOf(
        QuickAction("Airtime", Icons.Filled.Phone, CAirtime) { onOpenService("airtime") },
        QuickAction("Data", Icons.Filled.Wifi, CData) { onOpenService("data") },
        QuickAction("Cable TV", Icons.Filled.Tv, CCable) { onOpenService("cable") },
        QuickAction("Electric", Icons.Filled.Bolt, CElectric) { onOpenService("electricity") },
        QuickAction("Exam Pins", Icons.Filled.School, CExam) { onOpenService("exam") },
        QuickAction("Betting", Icons.Filled.Casino, CBetting) { onOpenService("betting") },
        QuickAction("History", Icons.Filled.History, PhPrimary) { onOpenHistory() },
    )

    Column(modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(top = 24.dp, bottom = 16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier.size(36.dp).background(PhPrimary, RoundedCornerShape(11.dp)),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(Icons.Filled.AccountBalanceWallet, contentDescription = null, tint = Color.White, modifier = Modifier.size(18.dp))
                }
                Text("PayHub", fontWeight = FontWeight.ExtraBold, fontSize = 18.sp, modifier = Modifier.padding(start = 10.dp))
            }
            Box(
                modifier = Modifier.size(40.dp).background(Color.White, CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                Icon(Icons.Filled.NotificationsNone, contentDescription = "Notifications", tint = CText2)
            }
        }

        // Hero
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(Brush.linearGradient(listOf(PhPrimary, PhPrimaryDark)), RoundedCornerShape(24.dp))
                .padding(20.dp)
        ) {
            Column {
                Text(
                    "Buy Airtime, Data & Bills — Instantly",
                    color = Color.White,
                    fontWeight = FontWeight.Bold,
                    fontSize = 18.sp,
                )
                Text(
                    "Pay once, get instant delivery. No sign up, no wallet needed.",
                    color = Color.White.copy(alpha = 0.85f),
                    fontSize = 12.sp,
                    modifier = Modifier.padding(top = 6.dp, bottom = 12.dp),
                )
                Row {
                    TrustChip("⚡ Instant Delivery")
                    androidx.compose.foundation.layout.Spacer(Modifier.padding(start = 6.dp))
                    TrustChip("🛡 Secured by PayHub")
                }
            }
        }

        androidx.compose.foundation.layout.Spacer(Modifier.padding(top = 20.dp))

        LazyVerticalGrid(
            columns = GridCells.Fixed(4),
            contentPadding = PaddingValues(vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
            modifier = Modifier.fillMaxWidth().size(240.dp),
        ) {
            items(actions) { action ->
                Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.clickable(onClick = action.onClick)) {
                    Box(
                        modifier = Modifier.size(52.dp).background(action.color, RoundedCornerShape(16.dp)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(action.icon, contentDescription = action.label, tint = Color.White)
                    }
                    Text(action.label, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = CText, modifier = Modifier.padding(top = 6.dp))
                }
            }
        }
    }
}

@Composable
private fun TrustChip(text: String) {
    Box(
        modifier = Modifier
            .background(Color.White.copy(alpha = 0.18f), RoundedCornerShape(999.dp))
            .padding(horizontal = 10.dp, vertical = 5.dp)
    ) {
        Text(text, color = Color.White, fontSize = 10.sp, fontWeight = FontWeight.SemiBold)
    }
}
