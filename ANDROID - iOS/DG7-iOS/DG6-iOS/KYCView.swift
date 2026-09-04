//
//  KYCView.swift
//  DG7-iOS
//
//  VoveID KYC Verification View
//

import SwiftUI
import VoveSDK

struct KYCView: View {
    @StateObject private var viewModel = KYCViewModel()
    @Environment(\.dismiss) var dismiss
    
    var body: some View {
        NavigationView {
            ScrollView {
                VStack(spacing: 24) {
                    // Status Card
                    KYCStatusCard(viewModel: viewModel)
                    
                    // VoveID Verification Button
                    if viewModel.voveidEnabled {
                        VoveIDVerificationButton(viewModel: viewModel)
                    }
                    
                    // Traditional KYC Methods
                    TraditionalKYCSection(viewModel: viewModel)
                    
                    // AI Interview Section
                    AIInterviewSection(viewModel: viewModel)
                }
                .padding()
            }
            .navigationTitle("Identity Verification (KYC)")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .navigationBarLeading) {
                    Button("Close") { dismiss() }
                }
            }
            .onAppear {
                viewModel.loadKYCStatus()
            }
            .alert("Verification Result", isPresented: $viewModel.showResult) {
                Button("OK") { viewModel.showResult = false }
            } message: {
                Text(viewModel.resultMessage)
            }
        }
    }
}

struct KYCStatusCard: View {
    @ObservedObject var viewModel: KYCViewModel
    
    var body: some View {
        VStack(spacing: 16) {
            Image(systemName: statusIcon)
                .font(.system(size: 60))
                .foregroundColor(statusColor)
            
            Text(statusTitle)
                .font(.title2)
                .fontWeight(.bold)
            
            Text(statusMessage)
                .font(.subheadline)
                .foregroundColor(.secondary)
                .multilineTextAlignment(.center)
            
            if viewModel.kycStatus == 2 {
                Label("Fully Verified", systemImage: "checkmark.seal.fill")
                    .font(.caption)
                    .foregroundColor(.green)
            } else if viewModel.kycStatus == 1 {
                Label("Under Review", systemImage: "clock.fill")
                    .font(.caption)
                    .foregroundColor(.orange)
            }
        }
        .frame(maxWidth: .infinity)
        .padding()
        .background(Color(.systemGray6))
        .cornerRadius(20)
    }
    
    var statusIcon: String {
        switch viewModel.kycStatus {
        case 2: return "checkmark.seal.fill"
        case 1: return "clock.fill"
        default: return "shield.lefthalf.filled"
        }
    }
    
    var statusColor: Color {
        switch viewModel.kycStatus {
        case 2: return .green
        case 1: return .orange
        default: return .blue
        }
    }
    
    var statusTitle: String {
        switch viewModel.kycStatus {
        case 2: return "Fully Verified"
        case 1: return "Under Review"
        default: return "Unverified"
        }
    }
    
    var statusMessage: String {
        switch viewModel.kycStatus {
        case 2: return "Your identity has been confirmed. You have unrestricted access to all services."
        case 1: return "Your documents are being processed by our compliance team."
        default: return "Please complete the required steps below to secure your account."
        }
    }
}

struct VoveIDVerificationButton: View {
    @ObservedObject var viewModel: KYCViewModel
    
    var body: some View {
        VStack(spacing: 12) {
            HStack {
                Image(systemName: "shield.checkered")
                    .font(.title2)
                Text("VoveID Identity Verification")
                    .font(.headline)
                Spacer()
            }
            
            Text("Complete your KYC in minutes with VoveID's AI-powered verification. Secure, fast, and compliant.")
                .font(.subheadline)
                .foregroundColor(.secondary)
            
            Button(action: {
                viewModel.startVoveIDVerification()
            }) {
                HStack {
                    if viewModel.isLoading {
                        ProgressView()
                            .progressViewStyle(CircularProgressViewStyle(tint: .white))
                            .scaleEffect(0.8)
                    } else {
                        Image(systemName: "shield.lefthalf.filled")
                    }
                    Text(viewModel.isLoading ? "Starting..." : "Start VoveID Verification")
                        .fontWeight(.semibold)
                }
                .frame(maxWidth: .infinity)
                .padding()
                .background(Color.blue)
                .foregroundColor(.white)
                .cornerRadius(12)
            }
            .disabled(viewModel.isLoading)
            
            if !viewModel.voveidStatus.isEmpty {
                Text(viewModel.voveidStatus)
                    .font(.caption)
                    .foregroundColor(.secondary)
            }
        }
        .padding()
        .background(Color(.systemGray6))
        .cornerRadius(16)
    }
}

struct TraditionalKYCSection: View {
    @ObservedObject var viewModel: KYCViewModel
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("Basic Verification")
                .font(.headline)
            
            VStack(spacing: 12) {
                if viewModel.bvnEnabled {
                    KYCInputField(
                        title: "Bank Verification Number (BVN)",
                        placeholder: "Enter 11-digit BVN",
                        value: $viewModel.bvnValue,
                        isSaved: !viewModel.bvnValue.isEmpty
                    )
                }
                
                if viewModel.ninEnabled {
                    KYCInputField(
                        title: "National Identity Number (NIN)",
                        placeholder: "Enter 11-digit NIN",
                        value: $viewModel.ninValue,
                        isSaved: !viewModel.ninValue.isEmpty
                    )
                }
            }
        }
        .padding()
        .background(Color(.systemGray6))
        .cornerRadius(16)
    }
}

struct KYCInputField: View {
    let title: String
    let placeholder: String
    @Binding var value: String
    let isSaved: Bool
    
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text(title)
                    .font(.subheadline)
                    .fontWeight(.medium)
                Spacer()
                if isSaved {
                    Label("Saved", systemImage: "checkmark.circle.fill")
                        .font(.caption)
                        .foregroundColor(.green)
                }
            }
            
            TextField(placeholder, text: $value)
                .textFieldStyle(RoundedBorderTextFieldStyle())
                .keyboardType(.numberPad)
        }
        .padding()
        .background(Color(.systemBackground))
        .cornerRadius(12)
        .shadow(color: .black.opacity(0.05), radius: 4, x: 0, y: 2)
    }
}

struct AIInterviewSection: View {
    @ObservedObject var viewModel: KYCViewModel
    
    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            Text("AI Compliance Interview")
                .font(.headline)
            
            Button(action: {
                viewModel.showAIInterview = true
            }) {
                HStack {
                    Image(systemName: "mic.fill")
                        .font(.title2)
                    VStack(alignment: .leading, spacing: 4) {
                        Text("Titanium AI Interview")
                            .font(.headline)
                        Text("Complete your KYC by talking to our AI Compliance Officer. No forms required.")
                            .font(.caption)
                            .foregroundColor(.secondary)
                    }
                    Spacer()
                    Image(systemName: "chevron.right")
                        .foregroundColor(.secondary)
                }
                .padding()
                .background(
                    LinearGradient(
                        colors: [Color.blue, Color.purple],
                        startPoint: .leading,
                        endPoint: .trailing
                    )
                )
                .foregroundColor(.white)
                .cornerRadius(16)
            }
        }
        .sheet(isPresented: $viewModel.showAIInterview) {
            AIInterviewView(viewModel: viewModel)
        }
    }
}

struct KYCViewModel: ObservableObject {
    @Published var kycStatus: Int = 0
    @Published var bvnValue: String = ""
    @Published var ninValue: String = ""
    @Published var bvnEnabled: Bool = false
    @Published var ninEnabled: Bool = false
    @Published var voveidEnabled: Bool = false
    @Published var voveidStatus: String = ""
    @Published var isLoading: Bool = false
    @Published var showResult: Bool = false
    @Published var resultMessage: String = ""
    @Published var showAIInterview: Bool = false
    
    func loadKYCStatus() {
        // Load KYC status from API
        // This would call the app API kyc.php with action=status
    }
    
    func startVoveIDVerification() {
        isLoading = true
        // Call API to create VoveID session
        // Then start VoveSDK with the session token
    }
}