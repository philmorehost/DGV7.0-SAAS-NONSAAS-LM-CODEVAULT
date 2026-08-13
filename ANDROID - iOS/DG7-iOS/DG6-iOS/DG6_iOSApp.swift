import SwiftUI

@main
struct DG6App: App {
    @State private var isLoggedIn = AppNetworkService.shared.apiKey != nil

    var body: some Scene {
        WindowGroup {
            if isLoggedIn {
                DashboardView()
                    .environmentObject(SessionManager(isLoggedIn: $isLoggedIn))
            } else {
                LoginView(onLoginSuccess: {
                    isLoggedIn = true
                })
            }
        }
    }
}

class SessionManager: ObservableObject {
    @Binding var isLoggedIn: Bool

    init(isLoggedIn: Binding<Bool>) {
        self._isLoggedIn = isLoggedIn
    }

    func logout() {
        AppNetworkService.shared.apiKey = nil
        isLoggedIn = false
    }
}
