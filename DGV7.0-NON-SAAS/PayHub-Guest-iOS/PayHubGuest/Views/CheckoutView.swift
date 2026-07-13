import SwiftUI
import WebKit

/// Loads the PayHub-hosted checkout URL returned by checkout-init.php directly — no local
/// gateway-SDK HTML/JS bridge is needed since Guest Mode uses PayHub exclusively and
/// checkout-init.php already returns a ready-to-load hosted page. Completion is detected
/// purely by URL: once the WKWebView navigates to web/guest-payment-complete.php (the
/// callback_url passed at checkout-init time), payment is done from PayHub's perspective and
/// we hand off to the Receipt screen, which polls status.php for the authoritative,
/// webhook-driven fulfillment result.
struct CheckoutView: View {
    @ObservedObject var viewModel: GuestViewModel
    let onCancel: () -> Void
    let onPaymentComplete: (String) -> Void

    var body: some View {
        ZStack(alignment: .bottom) {
            LinearGradient(colors: [PHColor.primary, PHColor.primaryDark], startPoint: .top, endPoint: .bottom)
                .ignoresSafeArea()

            if case .ready(let reference, let checkoutUrl, _) = viewModel.checkoutState, let url = URL(string: checkoutUrl) {
                PayHubWebView(url: url) { loadedUrl in
                    if loadedUrl.absoluteString.contains("guest-payment-complete.php") {
                        onPaymentComplete(reference)
                    }
                }
            } else {
                VStack(spacing: 16) {
                    ProgressView().tint(.white)
                    Text("Redirecting you to PayHub secure checkout…")
                        .font(.system(size: 15, weight: .bold))
                        .foregroundColor(.white)
                }
            }

            Button("Cancel and go back") { onCancel() }
                .font(.system(size: 13))
                .foregroundColor(.white.opacity(0.85))
                .padding(.bottom, 24)
        }
    }
}

private struct PayHubWebView: UIViewRepresentable {
    let url: URL
    let onNavigate: (URL) -> Void

    func makeCoordinator() -> Coordinator { Coordinator(onNavigate: onNavigate) }

    func makeUIView(context: Context) -> WKWebView {
        let webView = WKWebView()
        webView.navigationDelegate = context.coordinator
        webView.load(URLRequest(url: url))
        return webView
    }

    func updateUIView(_ uiView: WKWebView, context: Context) {}

    final class Coordinator: NSObject, WKNavigationDelegate {
        let onNavigate: (URL) -> Void
        init(onNavigate: @escaping (URL) -> Void) { self.onNavigate = onNavigate }

        func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
            if let url = webView.url { onNavigate(url) }
        }
    }
}
