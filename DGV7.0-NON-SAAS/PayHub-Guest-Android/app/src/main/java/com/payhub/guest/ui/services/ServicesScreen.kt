package com.payhub.guest.ui.services

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Casino
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.School
import androidx.compose.material.icons.filled.Tv
import androidx.compose.material.icons.filled.Wifi
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.payhub.guest.ui.theme.CAirtime
import com.payhub.guest.ui.theme.CAirtimeBg
import com.payhub.guest.ui.theme.CBetting
import com.payhub.guest.ui.theme.CBettingBg
import com.payhub.guest.ui.theme.CCable
import com.payhub.guest.ui.theme.CCableBg
import com.payhub.guest.ui.theme.CData
import com.payhub.guest.ui.theme.CDataBg
import com.payhub.guest.ui.theme.CElectric
import com.payhub.guest.ui.theme.CElectricBg
import com.payhub.guest.ui.theme.CExam
import com.payhub.guest.ui.theme.CExamBg
import com.payhub.guest.ui.theme.CText
import com.payhub.guest.ui.theme.CText2

private data class ServiceEntry(
    val key: String, val title: String, val caption: String,
    val icon: ImageVector, val color: Color, val bg: Color,
)

private val SERVICES = listOf(
    ServiceEntry("airtime", "Airtime", "Top up any network", Icons.Filled.Phone, CAirtime, CAirtimeBg),
    ServiceEntry("data", "Data Bundle", "SME, Shared, CG & Direct", Icons.Filled.Wifi, CData, CDataBg),
    ServiceEntry("cable", "Cable TV", "DStv, GOtv & more", Icons.Filled.Tv, CCable, CCableBg),
    ServiceEntry("electricity", "Electricity", "Prepaid & postpaid", Icons.Filled.Bolt, CElectric, CElectricBg),
    ServiceEntry("exam", "Exam Pins", "WAEC, NECO & more", Icons.Filled.School, CExam, CExamBg),
    ServiceEntry("betting", "Betting", "Fund your wallet", Icons.Filled.Casino, CBetting, CBettingBg),
)

@Composable
fun ServicesScreen(onOpenService: (String) -> Unit) {
    Column(modifier = Modifier.fillMaxSize().padding(horizontal = 20.dp)) {
        Text("All Services", fontWeight = FontWeight.Bold, fontSize = 20.sp, modifier = Modifier.padding(vertical = 20.dp))
        LazyVerticalGrid(
            columns = GridCells.Fixed(2),
            contentPadding = PaddingValues(bottom = 24.dp),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            items(SERVICES) { s ->
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .aspectRatio(1f)
                        .background(s.bg, RoundedCornerShape(20.dp))
                        .clickable { onOpenService(s.key) }
                        .padding(16.dp),
                    verticalArrangement = Arrangement.Center,
                ) {
                    Box(
                        modifier = Modifier.size(44.dp).background(s.color, RoundedCornerShape(14.dp)),
                        contentAlignment = Alignment.Center,
                    ) {
                        Icon(s.icon, contentDescription = s.title, tint = Color.White)
                    }
                    Text(s.title, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = CText, modifier = Modifier.padding(top = 10.dp))
                    Text(s.caption, fontSize = 11.sp, color = CText2, modifier = Modifier.padding(top = 2.dp))
                }
            }
        }
    }
}
