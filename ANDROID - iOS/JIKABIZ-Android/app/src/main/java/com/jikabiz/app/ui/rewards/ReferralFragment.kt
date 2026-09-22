package com.jikabiz.app.ui.rewards

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import com.jikabiz.app.R
import com.jikabiz.app.api.RetrofitClient
import com.jikabiz.app.databinding.FragmentReferralBinding
import com.jikabiz.app.util.PreferenceManager
import kotlinx.coroutines.launch
import java.util.Locale

/**
 * "Refer and Earn".
 *
 * The code and link are produced by the server and are the same values the website shows, so a
 * link shared from the app credits the same account as one shared from the dashboard.
 *
 * Sign-ups and actual rewards are shown separately. A referral only pays out once the referred
 * person completes their first transaction, so lumping "pending" in with "earned" would promise
 * users coins they have not been given yet.
 */
class ReferralFragment : Fragment(R.layout.fragment_referral) {

    private var _binding: FragmentReferralBinding? = null
    private val binding get() = _binding!!
    private lateinit var prefs: PreferenceManager

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        _binding = FragmentReferralBinding.bind(view)
        prefs = PreferenceManager(requireContext())

        binding.btnBack.setOnClickListener { requireActivity().onBackPressedDispatcher.onBackPressed() }
        binding.btnOpenCoins.setOnClickListener {
            findNavController().navigate(R.id.nav_coins)
        }
        load()
    }

    private fun load() {
        binding.progressBar.visibility = View.VISIBLE
        lifecycleScope.launch {
            try {
                val resp = RetrofitClient.getService()
                    .getReferral(mapOf("api_key" to prefs.getApiKey()))
                val data = asMap(resp.body()?.get("data"))
                activity?.runOnUiThread {
                    binding.progressBar.visibility = View.GONE
                    render(data)
                }
            } catch (e: Exception) {
                activity?.runOnUiThread {
                    binding.progressBar.visibility = View.GONE
                    Toast.makeText(requireContext(), "Unable to load your referral details.", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    private fun render(d: Map<String, Any>) {
        val code = (d["referral_code"] as? String).orEmpty()
        val link = (d["referral_link"] as? String).orEmpty()
        val message = (d["share_message"] as? String).orEmpty()

        binding.tvReferralCode.text = code.ifBlank { "—" }
        binding.tvReferralLink.text = link.ifBlank { "—" }
        binding.tvBonus.text = String.format(Locale.US, "%,d Coins", num(d["referral_bonus"], 0.0).toInt())
        binding.tvTotalReferrals.text = num(d["total_referrals"], 0.0).toInt().toString()
        binding.tvQualifiedReferrals.text = num(d["qualified_referrals"], 0.0).toInt().toString()
        binding.tvPendingReferrals.text = num(d["pending_referrals"], 0.0).toInt().toString()
        binding.tvCoinsFromReferrals.text = String.format(Locale.US, "%,d", num(d["coins_from_referrals"], 0.0).toInt())
        binding.tvCoinsBalance.text = String.format(Locale.US, "%,d", num(d["coins_balance"], 0.0).toInt())

        // Conversion is a separate service (isServiceEnabled('vtu_coins')); when a vendor has it
        // off, offering the button would lead to a dead screen.
        val coinsEnabled = d["coins_enabled"] as? Boolean ?: true
        binding.cardCoins.visibility = if (coinsEnabled) View.VISIBLE else View.GONE

        binding.btnCopyCode.setOnClickListener { copy("Referral code", code) }
        binding.btnShareLink.setOnClickListener {
            val body = if (link.isBlank()) code else "$message\n$link".trim()
            share(body)
        }

        val users = d["referred_users"] as? List<Map<String, Any>> ?: emptyList()
        binding.tvReferredEmpty.visibility = if (users.isEmpty()) View.VISIBLE else View.GONE
        binding.containerReferred.removeAllViews()
        users.forEach { u ->
            // Reusing the ledger row shape: label = who, date = when they joined,
            // amount = whether the referral has actually paid out yet.
            val row = layoutInflater.inflate(R.layout.item_points_row, binding.containerReferred, false)
            row.findViewById<TextView>(R.id.tv_point_label).text =
                (u["name"] as? String)?.ifBlank { null } ?: (u["username"] as? String).orEmpty()
            row.findViewById<TextView>(R.id.tv_point_date).text =
                "Joined " + prettyDate((u["joined"] as? String).orEmpty())
            val qualified = (u["qualified"] as? String) == "Yes"
            row.findViewById<TextView>(R.id.tv_point_amount).apply {
                text = if (qualified) "Earned" else "Pending"
                setTextColor(
                    android.graphics.Color.parseColor(if (qualified) "#2E7D32" else "#F9A825")
                )
            }
            binding.containerReferred.addView(row)
        }
    }

    private fun copy(label: String, value: String) {
        if (value.isBlank()) return
        val cm = requireContext().getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
        cm.setPrimaryClip(ClipData.newPlainText(label, value))
        Toast.makeText(requireContext(), "$label copied", Toast.LENGTH_SHORT).show()
    }

    private fun share(text: String) {
        if (text.isBlank()) return
        val send = Intent(Intent.ACTION_SEND).apply {
            type = "text/plain"
            putExtra(Intent.EXTRA_TEXT, text)
        }
        startActivity(Intent.createChooser(send, "Share referral link"))
    }

    private fun prettyDate(raw: String): String = raw.replace("T", " ").take(10).ifBlank { "—" }

    @Suppress("UNCHECKED_CAST")
    private fun asMap(any: Any?): Map<String, Any> = any as? Map<String, Any> ?: emptyMap()

    private fun num(any: Any?, fallback: Double): Double = when (any) {
        is Number -> any.toDouble()
        is String -> any.toDoubleOrNull() ?: fallback
        else -> fallback
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
