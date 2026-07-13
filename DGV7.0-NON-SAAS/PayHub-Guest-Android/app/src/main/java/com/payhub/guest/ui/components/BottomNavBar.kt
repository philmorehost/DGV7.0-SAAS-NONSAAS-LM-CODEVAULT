package com.payhub.guest.ui.components

import androidx.compose.animation.core.animateDpAsState
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Headset
import androidx.compose.material.icons.filled.History
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.GridView
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.getValue
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import com.payhub.guest.ui.theme.CText2
import com.payhub.guest.ui.theme.PhPrimary

enum class GuestTab(val route: String, val icon: ImageVector) {
    Home("home", Icons.Filled.Home),
    Services("services", Icons.Filled.GridView),
    History("history", Icons.Filled.History),
    Support("support", Icons.Filled.Headset),
}

@Composable
fun BottomNavBar(current: GuestTab, onSelect: (GuestTab) -> Unit) {
    Surface(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(12.dp, RoundedCornerShape(28.dp)),
        color = MaterialTheme.colorScheme.surface,
        shape = RoundedCornerShape(28.dp),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(64.dp),
            horizontalArrangement = androidx.compose.foundation.layout.Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            GuestTab.values().forEach { tab ->
                val selected = tab == current
                val bubbleOffset by animateDpAsState(if (selected) 0.dp else 8.dp, label = "navBubble")
                Box(
                    modifier = Modifier
                        .size(48.dp)
                        .offset(y = -bubbleOffset + 8.dp)
                        .clickable { onSelect(tab) },
                    contentAlignment = Alignment.Center,
                ) {
                    if (selected) {
                        Box(
                            modifier = Modifier
                                .size(44.dp)
                                .background(PhPrimary, CircleShape)
                        )
                    }
                    Icon(
                        imageVector = tab.icon,
                        contentDescription = tab.name,
                        tint = if (selected) androidx.compose.ui.graphics.Color.White else CText2,
                    )
                }
            }
        }
    }
}
