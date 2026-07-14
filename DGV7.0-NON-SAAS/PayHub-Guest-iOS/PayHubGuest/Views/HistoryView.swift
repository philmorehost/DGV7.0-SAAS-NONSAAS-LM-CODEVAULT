import SwiftUI

/// Guest Mode has no server-side transaction history (no login means nothing to fetch by user) —
/// this list is the on-device cache written by ReceiptView (see GuestHistoryStore), not a fetch
/// from the backend. Still an empty state when the guest hasn't completed a purchase yet.
struct HistoryView: View {
    let history: [GuestReceipt]

    init(history: [GuestReceipt] = []) {
        self.history = history
    }

    var body: some View {
        if history.isEmpty {
            VStack(spacing: 8) {
                Text("Transaction History").font(.system(size: 20, weight: .bold)).padding(.bottom, 40)
                Image(systemName: "doc.text").font(.system(size: 40)).foregroundColor(PHColor.text2)
                Text("No saved history").font(.system(size: 15, weight: .bold)).foregroundColor(PHColor.text)
                Text("Your completed purchases will show up here, saved on this device only.")
                    .font(.system(size: 13))
                    .foregroundColor(PHColor.text2)
                    .multilineTextAlignment(.center)
                    .padding(.horizontal, 32)
            }
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        } else {
            ScrollView {
                VStack(alignment: .leading, spacing: 10) {
                    Text("Transaction History").font(.system(size: 20, weight: .bold)).padding(.vertical, 20)
                    ForEach(history) { receipt in
                        HistoryRow(receipt: receipt)
                    }
                }
                .padding(.horizontal, 20)
                .padding(.bottom, 100)
            }
        }
    }
}

private struct HistoryRow: View {
    let receipt: GuestReceipt

    private var entry: GuestServiceEntry? {
        GuestServiceCatalog.all.first { $0.key == receipt.service }
    }

    private var statusColor: Color {
        switch receipt.status {
        case "success": return PHColor.success
        case "pending": return Color(hex: 0xF59E0B)
        default: return PHColor.error
        }
    }

    private var statusLabel: String {
        switch receipt.status {
        case "success": return "Successful"
        case "pending": return "Pending"
        default: return "Failed"
        }
    }

    var body: some View {
        HStack(spacing: 12) {
            RoundedRectangle(cornerRadius: 13)
                .fill(entry?.bg ?? PHColor.text2.opacity(0.12))
                .frame(width: 44, height: 44)
                .overlay(
                    Group {
                        if let entry { Image(systemName: entry.icon).foregroundColor(entry.color) }
                    }
                )

            VStack(alignment: .leading, spacing: 2) {
                Text(entry?.title ?? receipt.service.capitalized).font(.system(size: 14, weight: .semibold)).foregroundColor(PHColor.text)
                Text(receipt.recipient.isEmpty ? receipt.reference : receipt.recipient).font(.system(size: 12)).foregroundColor(PHColor.text2)
                Text(receipt.date.formatted(date: .abbreviated, time: .shortened)).font(.system(size: 11)).foregroundColor(PHColor.text2)
            }
            Spacer()
            VStack(alignment: .trailing, spacing: 4) {
                Text(String(format: "₦%.0f", receipt.amountPaid)).font(.system(size: 14, weight: .bold)).foregroundColor(PHColor.text)
                Text(statusLabel)
                    .font(.system(size: 10, weight: .semibold))
                    .foregroundColor(statusColor)
                    .padding(.horizontal, 8).padding(.vertical, 3)
                    .background(statusColor.opacity(0.12))
                    .clipShape(Capsule())
            }
        }
        .padding(14)
        .background(Color.white)
        .cornerRadius(16)
    }
}
