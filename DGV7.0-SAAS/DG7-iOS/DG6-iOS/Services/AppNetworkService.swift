import Foundation

class AppNetworkService {
    static let shared = AppNetworkService()

    private var baseURL: String {
        // In a real app, this would be in Info.plist. For this script, we use a default.
        return "https://dgv6.com/"
    }

    var apiKey: String? {
        get { UserDefaults.standard.string(forKey: "user_api_key") }
        set { UserDefaults.standard.set(newValue, forKey: "user_api_key") }
    }

    func request<T: Decodable>(_ endpoint: String, params: [String: Any], completion: @escaping (Result<T, Error>) -> Void) {
        guard let url = URL(string: baseURL + "web/api/" + endpoint) else {
            completion(.failure(NSError(domain: "Invalid URL", code: 0, userInfo: nil)))
            return
        }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"

        var finalParams = params
        if let apiKey = self.apiKey {
            finalParams["api_key"] = apiKey
        }

        var components = URLComponents()
        components.queryItems = finalParams.map { URLQueryItem(name: $0.key, value: "\($0.value)") }

        if let bodyString = components.query {
            request.httpBody = bodyString.data(using: .utf8)
        }

        request.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")

        URLSession.shared.dataTask(with: request) { data, response, error in
            if let error = error {
                DispatchQueue.main.async { completion(.failure(error)) }
                return
            }

            guard let data = data else {
                DispatchQueue.main.async { completion(.failure(NSError(domain: "No data", code: 0, userInfo: nil))) }
                return
            }

            do {
                let decoded = try JSONDecoder().decode(T.self, from: data)
                DispatchQueue.main.async { completion(.success(decoded)) }
            } catch {
                // If decoding fails, check if it's a string response (common in legacy APIs)
                if let stringResponse = String(data: data, encoding: .utf8) {
                    print("Raw Response: \(stringResponse)")
                }
                DispatchQueue.main.async { completion(.failure(error)) }
            }
        }.resume()
    }

    func requestJSON<T: Decodable>(_ rawPath: String, params: [String: Any], completion: @escaping (Result<T, Error>) -> Void) {
        guard let url = URL(string: baseURL + rawPath) else {
            completion(.failure(NSError(domain: "Invalid URL", code: 0, userInfo: nil)))
            return
        }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        
        if let apiKey = self.apiKey {
            request.setValue("Bearer \(apiKey)", forHTTPHeaderField: "Authorization")
        }

        request.httpBody = try? JSONSerialization.data(withJSONObject: params)

        URLSession.shared.dataTask(with: request) { data, response, error in
            if let error = error {
                DispatchQueue.main.async { completion(.failure(error)) }
                return
            }

            guard let data = data else {
                DispatchQueue.main.async { completion(.failure(NSError(domain: "No data", code: 0, userInfo: nil))) }
                return
            }

            do {
                let decoded = try JSONDecoder().decode(T.self, from: data)
                DispatchQueue.main.async { completion(.success(decoded)) }
            } catch {
                if let stringResponse = String(data: data, encoding: .utf8) {
                    print("Raw JSON Response: \(stringResponse)")
                }
                DispatchQueue.main.async { completion(.failure(error)) }
            }
        }.resume()
    }
}

struct APIResponse: Codable {
    let status: String
    let message: String?
    let desc: String?
}
