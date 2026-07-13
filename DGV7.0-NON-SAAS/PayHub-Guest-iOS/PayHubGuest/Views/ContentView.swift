import SwiftUI

struct ContentView: View {
    @StateObject private var viewModel = GuestViewModel()

    private var isTopLevel: Bool {
        switch viewModel.screen {
        case .home, .services, .history, .support: return true
        default: return false
        }
    }

    private var currentTab: GuestTab {
        switch viewModel.screen {
        case .services: return .services
        case .history: return .history
        case .support: return .support
        default: return .home
        }
    }

    var body: some View {
        ZStack(alignment: .bottom) {
            PHColor.bg.ignoresSafeArea()

            switch viewModel.screen {
            case .splash:
                SplashView { viewModel.screen = .home }
            case .home:
                HomeView(viewModel: viewModel)
            case .services:
                ServicesView(viewModel: viewModel)
            case .purchase(let service):
                PurchaseView(service: service, viewModel: viewModel)
            case .checkout:
                CheckoutView(
                    viewModel: viewModel,
                    onCancel: {
                        viewModel.resetCheckout()
                        viewModel.goBack()
                    },
                    onPaymentComplete: { reference in
                        viewModel.setTab(.home)
                        viewModel.navigate(to: .receipt(reference))
                    }
                )
            case .receipt(let reference):
                ReceiptView(reference: reference, viewModel: viewModel) {
                    viewModel.resetCheckout()
                    viewModel.resetReceipt()
                    viewModel.setTab(.home)
                }
            case .history:
                HistoryView()
            case .support:
                SupportView()
            }

            if isTopLevel {
                BottomNavBar(current: currentTab) { tab in
                    viewModel.setTab(tab.screen)
                }
                .padding(.horizontal, 20)
                .padding(.bottom, 16)
            }
        }
    }
}
