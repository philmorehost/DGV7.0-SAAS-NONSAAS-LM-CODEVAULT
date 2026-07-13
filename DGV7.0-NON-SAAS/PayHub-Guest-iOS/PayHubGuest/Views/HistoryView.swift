import SwiftUI

/// Guest Mode intentionally stores no server-side transaction history (confirmed product
/// decision — see the approved mockup's History screen copy). This is always an empty state
/// pointing the guest to their receipt, never a fabricated/sample transaction list.
struct HistoryView: View {
    var body: some View {
        VStack(spacing: 8) {
            Text("Transaction History").font(.system(size: 20, weight: .bold)).padding(.bottom, 40)
            Image(systemName: "doc.text").font(.system(size: 40)).foregroundColor(PHColor.text2)
            Text("No saved history").font(.system(size: 15, weight: .bold)).foregroundColor(PHColor.text)
            Text("Guest transactions aren't stored on our servers. Download or email your receipt right after payment to keep a copy!")
                .font(.system(size: 13))
                .foregroundColor(PHColor.text2)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 32)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
    }
}
