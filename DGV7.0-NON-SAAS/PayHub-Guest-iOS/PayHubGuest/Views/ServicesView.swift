import SwiftUI

private struct ServiceEntry: Identifiable {
    let id = UUID()
    let key: String
    let title: String
    let caption: String
    let icon: String
    let color: Color
    let bg: Color
}

private let SERVICES: [ServiceEntry] = [
    ServiceEntry(key: "airtime", title: "Airtime", caption: "Top up any network", icon: "phone.fill", color: PHColor.airtime, bg: PHColor.airtimeBg),
    ServiceEntry(key: "data", title: "Data Bundle", caption: "SME, Shared, CG & Direct", icon: "wifi", color: PHColor.data, bg: PHColor.dataBg),
    ServiceEntry(key: "cable", title: "Cable TV", caption: "DStv, GOtv & more", icon: "tv.fill", color: PHColor.cable, bg: PHColor.cableBg),
    ServiceEntry(key: "electricity", title: "Electricity", caption: "Prepaid & postpaid", icon: "bolt.fill", color: PHColor.electric, bg: PHColor.electricBg),
    ServiceEntry(key: "exam", title: "Exam Pins", caption: "WAEC, NECO & more", icon: "graduationcap.fill", color: PHColor.exam, bg: PHColor.examBg),
    ServiceEntry(key: "betting", title: "Betting", caption: "Fund your wallet", icon: "dice.fill", color: PHColor.betting, bg: PHColor.bettingBg),
]

struct ServicesView: View {
    @ObservedObject var viewModel: GuestViewModel
    private let columns = [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)]

    var body: some View {
        ScrollView {
            Text("All Services").font(.system(size: 20, weight: .bold)).padding(.vertical, 20)
                .frame(maxWidth: .infinity, alignment: .leading)

            LazyVGrid(columns: columns, spacing: 12) {
                ForEach(SERVICES) { s in
                    VStack(alignment: .leading, spacing: 4) {
                        RoundedRectangle(cornerRadius: 14).fill(s.color).frame(width: 44, height: 44)
                            .overlay(Image(systemName: s.icon).foregroundColor(.white))
                        Text(s.title).font(.system(size: 14, weight: .bold)).foregroundColor(PHColor.text).padding(.top, 6)
                        Text(s.caption).font(.system(size: 11)).foregroundColor(PHColor.text2)
                    }
                    .padding(16)
                    .frame(maxWidth: .infinity, minHeight: 130, alignment: .topLeading)
                    .background(s.bg)
                    .cornerRadius(20)
                    .onTapGesture { viewModel.navigate(to: .purchase(s.key)) }
                }
            }
            .padding(.bottom, 100)
        }
        .padding(.horizontal, 20)
    }
}
