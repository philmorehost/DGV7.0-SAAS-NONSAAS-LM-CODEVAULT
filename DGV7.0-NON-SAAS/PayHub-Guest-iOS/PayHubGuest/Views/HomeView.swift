import SwiftUI

private struct QuickAction: Identifiable {
    let id = UUID()
    let label: String
    let icon: String
    let color: Color
    let onClick: () -> Void
}

struct HomeView: View {
    @ObservedObject var viewModel: GuestViewModel

    private var actions: [QuickAction] {
        [
            QuickAction(label: "Airtime", icon: "phone.fill", color: PHColor.airtime) { viewModel.navigate(to: .purchase("airtime")) },
            QuickAction(label: "Data", icon: "wifi", color: PHColor.data) { viewModel.navigate(to: .purchase("data")) },
            QuickAction(label: "Cable TV", icon: "tv.fill", color: PHColor.cable) { viewModel.navigate(to: .purchase("cable")) },
            QuickAction(label: "Electric", icon: "bolt.fill", color: PHColor.electric) { viewModel.navigate(to: .purchase("electricity")) },
            QuickAction(label: "Exam Pins", icon: "graduationcap.fill", color: PHColor.exam) { viewModel.navigate(to: .purchase("exam")) },
            QuickAction(label: "Betting", icon: "dice.fill", color: PHColor.betting) { viewModel.navigate(to: .purchase("betting")) },
            QuickAction(label: "History", icon: "clock.arrow.circlepath", color: PHColor.primary) { viewModel.setTab(.history) },
        ]
    }

    private let columns = Array(repeating: GridItem(.flexible(), spacing: 8), count: 4)

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                HStack {
                    HStack(spacing: 10) {
                        RoundedRectangle(cornerRadius: 11).fill(PHColor.primary).frame(width: 36, height: 36)
                            .overlay(Image(systemName: "wallet.pass.fill").foregroundColor(.white).font(.system(size: 14)))
                        Text("PayHub").font(.system(size: 18, weight: .heavy))
                    }
                    Spacer()
                    Circle().fill(Color.white).frame(width: 40, height: 40)
                        .overlay(Image(systemName: "bell").foregroundColor(PHColor.text2))
                        .shadow(color: .black.opacity(0.08), radius: 6)
                }
                .padding(.top, 24)

                VStack(alignment: .leading, spacing: 6) {
                    Text("Buy Airtime, Data & Bills — Instantly")
                        .font(.system(size: 18, weight: .bold)).foregroundColor(.white)
                    Text("Pay once, get instant delivery. No sign up, no wallet needed.")
                        .font(.system(size: 12)).foregroundColor(.white.opacity(0.85))
                    HStack(spacing: 6) {
                        trustChip("⚡ Instant Delivery")
                        trustChip("🛡 Secured by PayHub")
                    }
                    .padding(.top, 6)
                }
                .padding(20)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(LinearGradient(colors: [PHColor.primary, PHColor.primaryDark], startPoint: .leading, endPoint: .trailing))
                .cornerRadius(24)

                LazyVGrid(columns: columns, spacing: 16) {
                    ForEach(actions) { action in
                        VStack(spacing: 6) {
                            RoundedRectangle(cornerRadius: 16).fill(action.color).frame(width: 52, height: 52)
                                .overlay(Image(systemName: action.icon).foregroundColor(.white))
                            Text(action.label).font(.system(size: 11, weight: .semibold)).foregroundColor(PHColor.text)
                        }
                        .onTapGesture(perform: action.onClick)
                    }
                }
                .padding(.top, 8)
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 100)
        }
    }

    private func trustChip(_ text: String) -> some View {
        Text(text)
            .font(.system(size: 10, weight: .semibold))
            .foregroundColor(.white)
            .padding(.horizontal, 10)
            .padding(.vertical, 5)
            .background(Color.white.opacity(0.18))
            .clipShape(Capsule())
    }
}
