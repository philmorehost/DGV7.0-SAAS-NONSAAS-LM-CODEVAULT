package com.payhub.guest.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.payhub.guest.data.model.BettingProvider
import com.payhub.guest.data.model.CablePlan
import com.payhub.guest.data.model.DataPlan
import com.payhub.guest.data.model.ExamPlan
import com.payhub.guest.data.model.GuestOrderStatusResponse
import com.payhub.guest.data.repository.ApiResult
import com.payhub.guest.data.repository.GuestRepository
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/**
 * Single shared ViewModel for the whole guest flow (Home -> Purchase -> Checkout -> Receipt).
 * The app has no login/session, so one activity-scoped ViewModel holding "current transaction"
 * state is simpler and safer here than threading arguments through five nav destinations.
 */
class GuestViewModel : ViewModel() {

    private val repo = GuestRepository()

    // ---------- Catalog (fetched once per service, cached for the session) ----------

    data class AirtimeNetwork(val code: String, val label: String, val discountPercent: String)
    data class ElectricProvider(val code: String, val label: String, val discountPercent: String)

    private val _airtimeNetworks = MutableStateFlow<List<AirtimeNetwork>>(emptyList())
    val airtimeNetworks: StateFlow<List<AirtimeNetwork>> = _airtimeNetworks.asStateFlow()

    private val _dataNetworks = MutableStateFlow<Map<String, List<DataPlan>>>(emptyMap())
    val dataNetworks: StateFlow<Map<String, List<DataPlan>>> = _dataNetworks.asStateFlow()

    private val _cableProviders = MutableStateFlow<Map<String, List<CablePlan>>>(emptyMap())
    val cableProviders: StateFlow<Map<String, List<CablePlan>>> = _cableProviders.asStateFlow()

    private val _electricProviders = MutableStateFlow<List<ElectricProvider>>(emptyList())
    val electricProviders: StateFlow<List<ElectricProvider>> = _electricProviders.asStateFlow()

    private val _examPlans = MutableStateFlow<Map<String, List<ExamPlan>>>(emptyMap())
    val examPlans: StateFlow<Map<String, List<ExamPlan>>> = _examPlans.asStateFlow()

    private val _bettingProviders = MutableStateFlow<List<BettingProvider>>(emptyList())
    val bettingProviders: StateFlow<List<BettingProvider>> = _bettingProviders.asStateFlow()

    fun loadCatalog(service: String) {
        viewModelScope.launch {
            when (service) {
                "airtime" -> when (val r = repo.getAirtimeCatalog()) {
                    is ApiResult.Success -> _airtimeNetworks.value = (r.data.airtimeVtu ?: emptyMap()).map { (label, info) ->
                        AirtimeNetwork(label.lowercase(), label, info.discountPercent)
                    }
                    is ApiResult.Error -> {}
                }
                "data" -> when (val r = repo.getDataCatalog()) {
                    is ApiResult.Success -> _dataNetworks.value = r.data.mobileNetwork ?: emptyMap()
                    is ApiResult.Error -> {}
                }
                "cable" -> when (val r = repo.getCableCatalog()) {
                    is ApiResult.Success -> _cableProviders.value = r.data.cableSubscription ?: emptyMap()
                    is ApiResult.Error -> {}
                }
                "electricity" -> when (val r = repo.getElectricCatalog()) {
                    is ApiResult.Success -> _electricProviders.value = (r.data.electricPayment ?: emptyMap()).map { (label, info) ->
                        ElectricProvider(label.lowercase(), label, info.discountPercent)
                    }
                    is ApiResult.Error -> {}
                }
                "exam" -> when (val r = repo.getExamCatalog()) {
                    is ApiResult.Success -> _examPlans.value = r.data.examPin ?: emptyMap()
                    is ApiResult.Error -> {}
                }
                "betting" -> when (val r = repo.getBettingCatalog()) {
                    is ApiResult.Success -> _bettingProviders.value = r.data.bettingProviders ?: emptyList()
                    is ApiResult.Error -> {}
                }
            }
        }
    }

    // ---------- Network auto-detect (airtime/data phone fields) ----------

    private val _detectedNetwork = MutableStateFlow<String?>(null)
    val detectedNetwork: StateFlow<String?> = _detectedNetwork.asStateFlow()

    fun detectNetwork(phone: String) {
        if (phone.length != 11) { _detectedNetwork.value = null; return }
        viewModelScope.launch {
            when (val r = repo.identifyNetwork(phone)) {
                is ApiResult.Success -> {
                    val net = r.data.network
                    _detectedNetwork.value = if (net.isNullOrBlank() || net == "Invalid") null else net
                }
                is ApiResult.Error -> _detectedNetwork.value = null
            }
        }
    }

    // ---------- Customer verification (cable/electric/betting) ----------

    sealed class VerifyState {
        object Idle : VerifyState()
        object Loading : VerifyState()
        data class Verified(val name: String, val address: String?) : VerifyState()
        data class Failed(val message: String) : VerifyState()
    }

    private val _verifyState = MutableStateFlow<VerifyState>(VerifyState.Idle)
    val verifyState: StateFlow<VerifyState> = _verifyState.asStateFlow()

    fun resetVerify() { _verifyState.value = VerifyState.Idle }

    fun verifyCustomer(service: String, fields: Map<String, Any?>) {
        _verifyState.value = VerifyState.Loading
        viewModelScope.launch {
            val backendService = if (service == "electricity") "electric" else service
            val body = fields + ("service" to backendService)
            when (val r = repo.verifyCustomer(body)) {
                is ApiResult.Success -> {
                    val d = r.data
                    _verifyState.value = if (d.status == "success") {
                        VerifyState.Verified(d.customerName ?: "Verified customer", d.customerAddress)
                    } else {
                        VerifyState.Failed(d.desc ?: "Unable to verify customer")
                    }
                }
                is ApiResult.Error -> _verifyState.value = VerifyState.Failed(r.message)
            }
        }
    }

    // ---------- Checkout ----------

    sealed class CheckoutState {
        object Idle : CheckoutState()
        object Loading : CheckoutState()
        data class Ready(val reference: String, val checkoutUrl: String, val amount: Double) : CheckoutState()
        data class Failed(val message: String) : CheckoutState()
    }

    private val _checkoutState = MutableStateFlow<CheckoutState>(CheckoutState.Idle)
    val checkoutState: StateFlow<CheckoutState> = _checkoutState.asStateFlow()

    /** Snapshot of what's being purchased, kept for the Receipt screen after payment completes. */
    data class PendingTransaction(val service: String, val recipient: String)
    var pendingTransaction: PendingTransaction? = null
        private set

    fun startCheckout(service: String, recipient: String, fields: Map<String, Any?>) {
        _checkoutState.value = CheckoutState.Loading
        pendingTransaction = PendingTransaction(service, recipient)
        viewModelScope.launch {
            val backendService = if (service == "electricity") "electric" else service
            val body = fields + ("service" to backendService)
            when (val r = repo.initCheckout(body)) {
                is ApiResult.Success -> {
                    val d = r.data
                    _checkoutState.value = if (d.status == "success" && !d.checkoutUrl.isNullOrBlank() && d.reference != null) {
                        CheckoutState.Ready(d.reference, d.checkoutUrl, d.amount ?: 0.0)
                    } else {
                        CheckoutState.Failed(d.desc ?: "Could not start checkout")
                    }
                }
                is ApiResult.Error -> _checkoutState.value = CheckoutState.Failed(r.message)
            }
        }
    }

    fun resetCheckout() { _checkoutState.value = CheckoutState.Idle }

    // ---------- Order status polling (after the WebView reports the PayHub redirect) ----------

    sealed class ReceiptState {
        object Idle : ReceiptState()
        object Polling : ReceiptState()
        data class Success(val order: GuestOrderStatusResponse) : ReceiptState()
        data class Pending(val order: GuestOrderStatusResponse) : ReceiptState()
        data class Failed(val message: String) : ReceiptState()
    }

    private val _receiptState = MutableStateFlow<ReceiptState>(ReceiptState.Idle)
    val receiptState: StateFlow<ReceiptState> = _receiptState.asStateFlow()

    /** Polls status.php until the webhook-driven fulfillment settles (success/pending/failed),
     *  or gives up after ~20 tries (~40s) — the webhook is the source of truth; this just
     *  reflects it back to the guest without requiring a push/socket connection. */
    fun pollOrderStatus(reference: String) {
        _receiptState.value = ReceiptState.Polling
        viewModelScope.launch {
            repeat(20) { _ ->
                when (val r = repo.getOrderStatus(reference)) {
                    is ApiResult.Success -> {
                        val order = r.data
                        when (order.status) {
                            "success" -> { _receiptState.value = ReceiptState.Success(order); return@launch }
                            "failed" -> { _receiptState.value = ReceiptState.Failed(order.desc ?: "Transaction failed"); return@launch }
                            "pending" -> { _receiptState.value = ReceiptState.Pending(order); return@launch }
                            // pending_payment / processing -> keep polling, the webhook hasn't landed yet
                        }
                    }
                    is ApiResult.Error -> { /* transient — keep polling */ }
                }
                delay(2000)
            }
            if (_receiptState.value is ReceiptState.Polling) {
                _receiptState.value = ReceiptState.Failed("We couldn't confirm your payment yet. Check your email/WhatsApp receipt, or contact support with your reference.")
            }
        }
    }

    fun resetReceipt() { _receiptState.value = ReceiptState.Idle }
}
