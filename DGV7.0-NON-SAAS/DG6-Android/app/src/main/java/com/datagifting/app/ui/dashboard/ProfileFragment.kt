package com.datagifting.app.ui.dashboard

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.biometric.BiometricManager
import androidx.fragment.app.Fragment
import androidx.navigation.fragment.findNavController
import com.datagifting.app.R
import com.datagifting.app.databinding.FragmentProfileBinding
import com.datagifting.app.ui.auth.LoginActivity
import com.datagifting.app.util.Constants
import com.datagifting.app.util.PreferenceManager
import com.datagifting.app.util.safeNavigate

class ProfileFragment : Fragment(R.layout.fragment_profile) {

    private var _binding: FragmentProfileBinding? = null
    private val binding get() = _binding!!
    private lateinit var prefs: PreferenceManager

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        _binding = FragmentProfileBinding.bind(view)
        prefs = PreferenceManager(requireContext())

        populateProfile()
        setupButtons()
    }

    private fun populateProfile() {
        binding.tvUsername.text = "@${prefs.getString(Constants.KEY_USERNAME)}"
        binding.tvFullName.text = "${prefs.getString(Constants.KEY_FIRSTNAME)} ${prefs.getString(Constants.KEY_LASTNAME)}"
        binding.tvEmail.text = prefs.getString(Constants.KEY_EMAIL)
        binding.tvPhone.text = prefs.getString(Constants.KEY_PHONE)
    }

    private fun setupButtons() {
        binding.btnSetPin.setOnClickListener {
            findNavController().safeNavigate(R.id.action_profile_to_set_pin)
        }

        binding.btnKyc.setOnClickListener {
            findNavController().safeNavigate(R.id.action_profile_to_kyc)
        }

        binding.btnNinCard.setOnClickListener {
            findNavController().safeNavigate(R.id.action_profile_to_nin)
        }

        // Dark mode toggle
        binding.switchDarkMode.isChecked = prefs.getBoolean(Constants.KEY_DARK_MODE, false)
        binding.switchDarkMode.setOnCheckedChangeListener { _, isChecked ->
            prefs.saveBoolean(Constants.KEY_DARK_MODE, isChecked)
            androidx.appcompat.app.AppCompatDelegate.setDefaultNightMode(
                if (isChecked) androidx.appcompat.app.AppCompatDelegate.MODE_NIGHT_YES
                else androidx.appcompat.app.AppCompatDelegate.MODE_NIGHT_NO
            )
            requireActivity().recreate()
        }

        // Biometric toggle â€” only show if hardware is available
        val biometricManager = BiometricManager.from(requireContext())
        val biometricAvailable = biometricManager.canAuthenticate(
            BiometricManager.Authenticators.BIOMETRIC_WEAK or BiometricManager.Authenticators.DEVICE_CREDENTIAL
        ) == BiometricManager.BIOMETRIC_SUCCESS

        if (biometricAvailable) {
            binding.switchBiometric.visibility = View.VISIBLE
            binding.switchBiometric.isChecked = prefs.getBoolean(Constants.KEY_BIOMETRIC_ENABLED, false)
            binding.switchBiometric.setOnCheckedChangeListener { _, isChecked ->
                prefs.saveBoolean(Constants.KEY_BIOMETRIC_ENABLED, isChecked)
                val msg = if (isChecked) "Biometric login enabled" else "Biometric login disabled"
                Toast.makeText(requireContext(), msg, Toast.LENGTH_SHORT).show()
            }
        } else {
            binding.switchBiometric.visibility = View.GONE
        }

        binding.btnWhatsapp.setOnClickListener {
            val wa = prefs.getString(Constants.KEY_SUPPORT_WHATSAPP)
            if (wa.isNotEmpty()) {
                val intent = Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/$wa"))
                startActivity(intent)
            }
        }

        binding.btnEmailSupport.setOnClickListener {
            val email = prefs.getString(Constants.KEY_SUPPORT_EMAIL)
            val intent = Intent(Intent.ACTION_SENDTO, Uri.parse("mailto:$email"))
            startActivity(intent)
        }

        binding.btnLogout.setOnClickListener {
            prefs.clear()
            startActivity(Intent(requireContext(), LoginActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            })
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}

