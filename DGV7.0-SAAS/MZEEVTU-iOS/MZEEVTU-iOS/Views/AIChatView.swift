import SwiftUI
import Speech
import AVFoundation

struct AIChatView: View {
    @State private var prompt: String = ""
    @State private var chatHistory: [ChatMessage] = []
    @State private var isLoading: Bool = false
    @Environment(\.presentationMode) var presentationMode
    
    // Speech Recognition properties
    @State private var isRecording = false
    private let speechRecognizer = SFSpeechRecognizer(locale: Locale(identifier: "en-US"))
    @State private var recognitionRequest: SFSpeechAudioBufferRecognitionRequest?
    @State private var recognitionTask: SFSpeechRecognitionTask?
    private let audioEngine = AVAudioEngine()
    
    struct ChatMessage: Identifiable {
        let id = UUID()
        let isUser: Bool
        let text: String
    }
    
    var body: some View {
        NavigationView {
            VStack {
                ScrollViewReader { proxy in
                    ScrollView {
                        VStack(spacing: 12) {
                            ForEach(chatHistory) { message in
                                HStack {
                                    if message.isUser { Spacer() }
                                    Text(message.text)
                                        .padding(12)
                                        .background(message.isUser ? Color.blue : Color(UIColor.secondarySystemBackground))
                                        .foregroundColor(message.isUser ? .white : .primary)
                                        .cornerRadius(16)
                                    if !message.isUser { Spacer() }
                                }
                                .padding(.horizontal)
                                .id(message.id)
                            }
                        }
                        .padding(.vertical)
                    }
                    .onChange(of: chatHistory.count) { _ in
                        withAnimation {
                            if let last = chatHistory.last {
                                proxy.scrollTo(last.id, anchor: .bottom)
                            }
                        }
                    }
                }
                
                HStack {
                    TextField(isRecording ? "Listening..." : "Ask me anything...", text: $prompt)
                        .padding(12)
                        .background(Color(UIColor.systemGray6))
                        .cornerRadius(20)
                        .disabled(isLoading)
                    
                    Button(action: toggleRecording) {
                        Image(systemName: isRecording ? "mic.fill" : "mic")
                            .foregroundColor(.white)
                            .padding(12)
                            .background(isRecording ? Color.red : Color.gray)
                            .clipShape(Circle())
                    }
                    .disabled(isLoading)
                    
                    Button(action: sendMessage) {
                        if isLoading {
                            ProgressView()
                                .padding()
                        } else {
                            Image(systemName: "paperplane.fill")
                                .foregroundColor(.white)
                                .padding()
                                .background(prompt.isEmpty ? Color.gray : Color.blue)
                                .clipShape(Circle())
                        }
                    }
                    .disabled(prompt.isEmpty || isLoading)
                }
                .padding()
            }
            .navigationTitle("AI Assistant")
            .navigationBarTitleDisplayMode(.inline)
            .navigationBarItems(trailing: Button("Close") {
                if isRecording { stopRecording() }
                presentationMode.wrappedValue.dismiss()
            })
            .onAppear {
                SFSpeechRecognizer.requestAuthorization { authStatus in
                    // Handle authorization if needed
                }
            }
        }
    }
    
    func sendMessage() {
        if isRecording { stopRecording() }
        let userMsg = prompt.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !userMsg.isEmpty else { return }
        
        chatHistory.append(ChatMessage(isUser: true, text: userMsg))
        prompt = ""
        isLoading = true
        
        let params: [String: Any] = ["prompt": userMsg, "page_context": "ios_app"]
        
        AppNetworkService.shared.request("app-backend/ai-handler", params: params) { (result: Result<APIResponse, Error>) in
            DispatchQueue.main.async {
                self.isLoading = false
                switch result {
                case .success(let response):
                    if response.success == true, let aiResponse = response.response {
                        self.chatHistory.append(ChatMessage(isUser: false, text: aiResponse))
                    } else {
                        self.chatHistory.append(ChatMessage(isUser: false, text: "Error: \(response.error ?? "Unknown error")"))
                    }
                case .failure(let error):
                    self.chatHistory.append(ChatMessage(isUser: false, text: "Network Error: \(error.localizedDescription)"))
                }
            }
        }
    }
    
    func toggleRecording() {
        if isRecording {
            stopRecording()
            // Auto send after recording stops
            if !prompt.isEmpty {
                sendMessage()
            }
        } else {
            do {
                try startRecording()
            } catch {
                print("Audio engine error: \(error.localizedDescription)")
            }
        }
    }
    
    func startRecording() throws {
        recognitionTask?.cancel()
        self.recognitionTask = nil
        
        let audioSession = AVAudioSession.sharedInstance()
        try audioSession.setCategory(.record, mode: .measurement, options: .duckOthers)
        try audioSession.setActive(true, options: .notifyOthersOnDeactivation)
        
        let inputNode = audioEngine.inputNode
        recognitionRequest = SFSpeechAudioBufferRecognitionRequest()
        
        guard let recognitionRequest = recognitionRequest else {
            fatalError("Unable to create an SFSpeechAudioBufferRecognitionRequest object")
        }
        recognitionRequest.shouldReportPartialResults = true
        
        recognitionTask = speechRecognizer?.recognitionTask(with: recognitionRequest, resultHandler: { (result, error) in
            var isFinal = false
            
            if let result = result {
                self.prompt = result.bestTranscription.formattedString
                isFinal = result.isFinal
            }
            
            if error != nil || isFinal {
                self.audioEngine.stop()
                inputNode.removeTap(onBus: 0)
                self.recognitionRequest = nil
                self.recognitionTask = nil
                self.isRecording = false
            }
        })
        
        let recordingFormat = inputNode.outputFormat(forBus: 0)
        inputNode.installTap(onBus: 0, bufferSize: 1024, format: recordingFormat) { (buffer, when) in
            self.recognitionRequest?.append(buffer)
        }
        
        audioEngine.prepare()
        try audioEngine.start()
        isRecording = true
    }
    
    func stopRecording() {
        audioEngine.stop()
        recognitionRequest?.endAudio()
        isRecording = false
    }
}
