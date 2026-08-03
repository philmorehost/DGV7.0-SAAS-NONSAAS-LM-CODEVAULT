import SwiftUI

struct DashboardView: View {
    @EnvironmentObject var session: SessionManager
    @State private var balance: Double = 0.0
    @State private var isLoading = false
    @State private var showAIAssistant = false

    var body: some View {
        NavigationView {
            ZStack(alignment: .bottomTrailing) {
                ScrollView {
                    VStack(spacing: 20) {
                        // Balance Card
                        VStack {
                            Text("Wallet Balance")
                                .font(.subheadline)
                                .foregroundColor(.white.opacity(0.8))
                            Text("₦\(String(format: "%.2f", balance))")
                                .font(.system(size: 34, weight: .bold))
                                .foregroundColor(.white)

                            Button(action: fetchBalance) {
                                Image(systemName: "arrow.clockwise")
                                    .foregroundColor(.white)
                            }
                            .padding(.top, 5)
                        }
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 30)
                        .background(LinearGradient(gradient: Gradient(colors: [Color.blue, Color.purple]), startPoint: .topLeading, endPoint: .bottomTrailing))
                        .cornerRadius(20)
                        .padding(.horizontal)

                        // Service Grid
                        LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible()), GridItem(.flexible())], spacing: 20) {
                            NavigationLink(destination: AirtimeView()) {
                                ServiceItem(icon: "phone.fill", label: "Airtime")
                            }
                            NavigationLink(destination: DataView()) {
                                ServiceItem(icon: "wifi", label: "Data")
                            }
                            NavigationLink(destination: BillPaymentView(serviceType: "electric")) {
                                ServiceItem(icon: "bolt.fill", label: "Electric")
                            }
                            NavigationLink(destination: BillPaymentView(serviceType: "cable")) {
                                ServiceItem(icon: "tv.fill", label: "Cable")
                            }
                            NavigationLink(destination: BulkOperationsView()) {
                                ServiceItem(icon: "square.grid.3x3.fill", label: "Bulk Ops")
                            }
                            NavigationLink(destination: PrintHubView()) {
                                ServiceItem(icon: "printer.fill", label: "Print Hub")
                            }
                            NavigationLink(destination: AIBudgetingView()) {
                                ServiceItem(icon: "chart.pie.fill", label: "AI Budget")
                            }
                            NavigationLink(destination: VirtualCardView()) {
                                ServiceItem(icon: "creditcard.fill", label: "Cards")
                            }
                            NavigationLink(destination: TransactionHistoryView()) {
                                ServiceItem(icon: "list.bullet.rectangle", label: "History")
                            }
                        }
                        .padding()

                        Spacer()
                    }
                }

                // AI Assistant Floating Action Button
                Button(action: {
                    showAIAssistant = true
                }) {
                    Image(systemName: "bubble.right.fill")
                        .font(.system(size: 24, weight: .bold))
                        .foregroundColor(.white)
                        .padding(16)
                        .background(Color.blue)
                        .clipShape(Circle())
                        .shadow(color: Color.black.opacity(0.2), radius: 5, x: 0, y: 4)
                }
                .padding(20)
            }
            .sheet(isPresented: $showAIAssistant) {
                AIAssistantView()
            }
            .navigationTitle("Dashboard")
            .toolbar {
                ToolbarItem(placement: .navigationBarTrailing) {
                    Button("Logout") {
                        session.logout()
                    }
                }
            }
            .onAppear(perform: fetchBalance)
        }
    }

    func fetchBalance() {
        isLoading = true
        AppNetworkService.shared.request("profile.php", params: [:]) { (result: Result<ProfileResponse, Error>) in
            isLoading = false
            if case .success(let response) = result {
                if let data = response.data {
                    self.balance = Double(data.balance) ?? 0.0
                }
            }
        }
    }
}

struct ProfileResponse: Codable {
    let status: String
    let data: ProfileData?
}

struct ProfileData: Codable {
    let balance: String
}

struct ServiceItem: View {
    let icon: String
    let label: String

    var body: some View {
        VStack {
            Image(systemName: icon)
                .font(.title)
                .foregroundColor(.blue)
                .frame(width: 60, height: 60)
                .background(Color.blue.opacity(0.1))
                .cornerRadius(15)
            Text(label)
                .font(.caption)
                .fontWeight(.medium)
                .foregroundColor(.primary)
        }
    }
}
