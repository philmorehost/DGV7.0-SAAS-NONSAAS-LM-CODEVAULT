import Foundation

/// Single shared ObservableObject for the whole guest flow (Home -> Purchase -> Checkout ->
/// Receipt). The app has no login/session, so one app-scoped view model holding "current
/// transaction" state is simpler and safer here than threading state through five SwiftUI
/// destinations. Mirrors the Android app's GuestViewModel.kt 1:1 for cross-platform parity.
@MainActor
final class GuestViewModel: ObservableObject {

    private let api = GuestAPIService.shared

    // ---------- Navigation (mirrors the Android app's NavHost with a simple stack) ----------

    enum Screen: Equatable {
        case splash, home, services, purchase(String), checkout, receipt(String), history, support
    }

    @Published var screen: Screen = .splash
    private var navStack: [Screen] = []

    func navigate(to screen: Screen) {
        navStack.append(self.screen)
        self.screen = screen
    }

    func goBack() {
        screen = navStack.popLast() ?? .home
    }

    /// Switches a top-level tab (home/services/history/support) and clears the back stack,
    /// matching the Android app's setActiveTab() semantics.
    func setTab(_ screen: Screen) {
        self.screen = screen
        navStack = []
    }

    // ---------- Catalog (fetched once per service, cached for the session) ----------

    struct AirtimeNetwork: Identifiable, Hashable {
        var id: String { code }
        let code: String
        let label: String
        let discountPercent: String
    }

    struct ElectricProvider: Identifiable, Hashable {
        var id: String { code }
        let code: String
        let label: String
        let discountPercent: String
    }

    @Published var airtimeNetworks: [AirtimeNetwork] = []
    @Published var dataNetworks: [String: [DataPlan]] = [:]
    @Published var cableProviders: [String: [CablePlan]] = [:]
    @Published var electricProviders: [ElectricProvider] = []
    @Published var examPlans: [String: [ExamPlan]] = [:]
    @Published var bettingProviders: [BettingProvider] = []

    func loadCatalog(_ service: String) {
        Task {
            switch service {
            case "airtime":
                if case .success(let r) = await api.getAirtimeCatalog() {
                    airtimeNetworks = (r.airtimeVtu ?? [:]).map { label, info in
                        AirtimeNetwork(code: label.lowercased(), label: label, discountPercent: info.discountPercent)
                    }
                }
            case "data":
                if case .success(let r) = await api.getDataCatalog() {
                    dataNetworks = r.mobileNetwork ?? [:]
                }
            case "cable":
                if case .success(let r) = await api.getCableCatalog() {
                    cableProviders = r.cableSubscription ?? [:]
                }
            case "electricity":
                if case .success(let r) = await api.getElectricCatalog() {
                    electricProviders = (r.electricPayment ?? [:]).map { label, info in
                        ElectricProvider(code: label.lowercased(), label: label, discountPercent: info.discountPercent)
                    }
                }
            case "exam":
                if case .success(let r) = await api.getExamCatalog() {
                    examPlans = r.examPin ?? [:]
                }
            case "betting":
                if case .success(let r) = await api.getBettingCatalog() {
                    bettingProviders = r.bettingProviders ?? []
                }
            default: break
            }
        }
    }

    // ---------- Network auto-detect (airtime/data phone fields) ----------

    @Published var detectedNetwork: String? = nil

    func detectNetwork(phone: String) {
        guard phone.count == 11 else { detectedNetwork = nil; return }
        Task {
            if case .success(let r) = await api.identifyNetwork(phone: phone) {
                let net = r.network
                detectedNetwork = (net == nil || net == "Invalid") ? nil : net
            } else {
                detectedNetwork = nil
            }
        }
    }

    // ---------- Customer verification (cable/electric/betting) ----------

    enum VerifyState: Equatable {
        case idle
        case loading
        case verified(name: String, address: String?)
        case failed(String)
    }

    @Published var verifyState: VerifyState = .idle

    func resetVerify() { verifyState = .idle }

    func verifyCustomer(service: String, fields: [String: Any?]) {
        verifyState = .loading
        Task {
            let backendService = service == "electricity" ? "electric" : service
            var body = fields
            body["service"] = backendService
            switch await api.verifyCustomer(body: body) {
            case .success(let d):
                if d.status == "success" {
                    verifyState = .verified(name: d.customerName ?? "Verified customer", address: d.customerAddress)
                } else {
                    verifyState = .failed(d.desc ?? "Unable to verify customer")
                }
            case .failure(let message):
                verifyState = .failed(message)
            }
        }
    }

    // ---------- Checkout ----------

    enum CheckoutState: Equatable {
        case idle
        case loading
        case ready(reference: String, checkoutUrl: String, amount: Double)
        case failed(String)
    }

    @Published var checkoutState: CheckoutState = .idle

    struct PendingTransaction {
        let service: String
        let recipient: String
    }
    private(set) var pendingTransaction: PendingTransaction?

    func startCheckout(service: String, recipient: String, fields: [String: Any?]) {
        checkoutState = .loading
        pendingTransaction = PendingTransaction(service: service, recipient: recipient)
        Task {
            let backendService = service == "electricity" ? "electric" : service
            var body = fields
            body["service"] = backendService
            switch await api.initCheckout(body: body) {
            case .success(let d):
                if d.status == "success", let url = d.checkoutUrl, !url.isEmpty, let ref = d.reference {
                    checkoutState = .ready(reference: ref, checkoutUrl: url, amount: d.amount ?? 0)
                } else {
                    checkoutState = .failed(d.desc ?? "Could not start checkout")
                }
            case .failure(let message):
                checkoutState = .failed(message)
            }
        }
    }

    func resetCheckout() { checkoutState = .idle }

    // ---------- Order status polling (after the WebView reports the PayHub redirect) ----------

    enum ReceiptState: Equatable {
        case idle
        case polling
        case success(GuestOrderStatusResponse)
        case pending(GuestOrderStatusResponse)
        case failed(String)

        static func == (lhs: ReceiptState, rhs: ReceiptState) -> Bool {
            switch (lhs, rhs) {
            case (.idle, .idle), (.polling, .polling): return true
            case (.success(let a), .success(let b)): return a.ref == b.ref
            case (.pending(let a), .pending(let b)): return a.ref == b.ref
            case (.failed(let a), .failed(let b)): return a == b
            default: return false
            }
        }
    }

    @Published var receiptState: ReceiptState = .idle

    /// Polls status.php until the webhook-driven fulfillment settles (success/pending/failed),
    /// or gives up after ~20 tries (~40s) — the webhook is the source of truth; this just
    /// reflects it back to the guest without requiring a push/socket connection.
    func pollOrderStatus(reference: String) {
        receiptState = .polling
        Task {
            for _ in 0..<20 {
                switch await api.getOrderStatus(reference: reference) {
                case .success(let order):
                    switch order.status {
                    case "success": receiptState = .success(order); return
                    case "failed": receiptState = .failed(order.desc ?? "Transaction failed"); return
                    case "pending": receiptState = .pending(order); return
                    default: break // pending_payment / processing -> keep polling
                    }
                case .failure:
                    break // transient — keep polling
                }
                try? await Task.sleep(nanoseconds: 2_000_000_000)
            }
            if case .polling = receiptState {
                receiptState = .failed("We couldn't confirm your payment yet. Check your email/WhatsApp receipt, or contact support with your reference.")
            }
        }
    }

    func resetReceipt() { receiptState = .idle }
}
