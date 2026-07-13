import SwiftUI

enum GuestTab: CaseIterable, Hashable {
    case home, services, history, support

    var icon: String {
        switch self {
        case .home: return "house.fill"
        case .services: return "square.grid.2x2.fill"
        case .history: return "clock.arrow.circlepath"
        case .support: return "headphones"
        }
    }

    var screen: GuestViewModel.Screen {
        switch self {
        case .home: return .home
        case .services: return .services
        case .history: return .history
        case .support: return .support
        }
    }
}

struct BottomNavBar: View {
    let current: GuestTab
    let onSelect: (GuestTab) -> Void

    var body: some View {
        HStack {
            ForEach(GuestTab.allCases, id: \.self) { tab in
                Spacer()
                ZStack {
                    if tab == current {
                        Circle().fill(PHColor.primary).frame(width: 44, height: 44)
                    }
                    Image(systemName: tab.icon)
                        .foregroundColor(tab == current ? .white : PHColor.text2)
                }
                .frame(width: 48, height: 48)
                .onTapGesture { onSelect(tab) }
                Spacer()
            }
        }
        .padding(.vertical, 8)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 28))
        .shadow(color: Color.black.opacity(0.08), radius: 12, y: 4)
    }
}
