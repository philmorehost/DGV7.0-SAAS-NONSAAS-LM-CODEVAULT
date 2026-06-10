import SwiftUI

struct LoginView: View {
    @State private var email = ""
    @State private var password = ""
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var logoRotation: Double = 0

    var onLoginSuccess: () -> Void

    var body: some View {
        ZStack {
            // Dark blue background matching PayHub branding
            Color(red: 0.13, green: 0.13, blue: 0.47)
                .ignoresSafeArea()

            VStack(spacing: 20) {
                Spacer()

                // PayHub spinning logo
                Image("payhub_logo")
                    .resizable()
                    .scaledToFit()
                    .frame(width: 110, height: 110)
                    .clipShape(RoundedRectangle(cornerRadius: 20))
                    .rotationEffect(.degrees(logoRotation))
                    .onAppear {
                        withAnimation(.linear(duration: 1.0)) {
                            logoRotation = 360
                        }
                    }

                Text("PayHub")
                    .font(.system(size: 32, weight: .bold))
                    .foregroundColor(.white)

                Text("Fast · Secure · Reliable")
                    .font(.subheadline)
                    .foregroundColor(.white.opacity(0.7))
                    .padding(.bottom, 12)

                if let error = errorMessage {
                    Text(error)
                        .foregroundColor(.red)
                        .font(.caption)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal)
                }

                // Card panel
                VStack(alignment: .leading, spacing: 16) {
                    Text("Welcome Back 👋")
                        .font(.title2)
                        .fontWeight(.bold)
                        .foregroundColor(.primary)

                    Text("Sign in to your account")
                        .font(.subheadline)
                        .foregroundColor(.secondary)

                    TextField("Username", text: $email)
                        .textFieldStyle(RoundedBorderTextFieldStyle())
                        .autocapitalization(.none)
                        .disableAutocorrection(true)

                    SecureField("Password", text: $password)
                        .textFieldStyle(RoundedBorderTextFieldStyle())

                    Button(action: login) {
                        if isLoading {
                            ProgressView()
                                .progressViewStyle(CircularProgressViewStyle(tint: .white))
                                .frame(maxWidth: .infinity)
                        } else {
                            Text("Login")
                                .fontWeight(.bold)
                                .frame(maxWidth: .infinity)
                        }
                    }
                    .padding()
                    .background(Color(red: 0.13, green: 0.13, blue: 0.47))
                    .foregroundColor(.white)
                    .cornerRadius(12)
                    .disabled(isLoading || email.isEmpty || password.isEmpty)
                }
                .padding(24)
                .background(Color(.systemBackground))
                .cornerRadius(20)
                .shadow(color: .black.opacity(0.15), radius: 10, x: 0, y: 4)
                .padding(.horizontal)

                Spacer()
                Spacer()
            }
            .padding()
        }
    }

    func login() {
        isLoading = true
        errorMessage = nil

        let params: [String: Any] = [
            "user": email,
            "pass": password
        ]

        AppNetworkService.shared.request("login.php", params: params) { (result: Result<LoginResponse, Error>) in
            isLoading = false
            switch result {
            case .success(let response):
                if response.status == "success", let data = response.data {
                    AppNetworkService.shared.apiKey = data.api_key
                    UserDefaults.standard.set(data.username, forKey: "saved_username")
                    onLoginSuccess()
                } else {
                    errorMessage = response.message ?? "Login failed"
                }
            case .failure(let error):
                errorMessage = error.localizedDescription
            }
        }
    }
}

struct LoginResponse: Codable {
    let status: String
    let message: String?
    let data: UserData?
}

struct UserData: Codable {
    let username: String
    let api_key: String
}
