<?php
// CodeVault Help Center Page
?>
<div class="max-w-4xl mx-auto py-12 px-4 select-none text-left">
    
    <!-- Hero / Search Section -->
    <div class="text-center py-16 bg-gradient-to-br from-[#1c2229] to-[#2a323d] rounded-2xl text-white shadow-xl mb-12 px-6">
        <h1 class="text-3xl sm:text-4xl font-black tracking-tight mb-3">How can we help you today?</h1>
        <p class="text-slate-400 text-sm max-w-lg mx-auto mb-8 font-medium">Find instant answers to billing queries, license validation, seller payouts, or submit a custom support ticket.</p>
        
        <div class="max-w-md mx-auto relative">
            <span class="absolute left-4 top-3 text-slate-400 text-base">🔍</span>
            <input type="text" id="faq-search" onkeyup="searchFAQs()" placeholder="Search help articles..." class="w-full pl-11 pr-4 py-3 bg-white/10 backdrop-blur-md border border-white/10 rounded-lg text-sm text-white placeholder-slate-400 outline-none focus:bg-white focus:text-slate-900 focus:placeholder-slate-500 transition-all shadow-inner">
        </div>
    </div>

    <!-- Help Categories & FAQs -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Sidebar Navigation -->
        <div class="md:col-span-1 space-y-2">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pl-3 mb-2">Help Categories</h3>
            <button onclick="switchHelpCategory('all')" id="help-btn-all" class="help-nav-btn w-full text-left px-4 py-3 rounded-lg text-xs font-bold bg-[#5cb85c] text-white transition-all shadow-sm">
                📁 All Help Articles
            </button>
            <button onclick="switchHelpCategory('buyers')" id="help-btn-buyers" class="help-nav-btn w-full text-left px-4 py-3 rounded-lg text-xs font-bold hover:bg-slate-100 text-slate-650 transition-all">
                📥 Buyer Guide & Purchasing
            </button>
            <button onclick="switchHelpCategory('licensing')" id="help-btn-licensing" class="help-nav-btn w-full text-left px-4 py-3 rounded-lg text-xs font-bold hover:bg-slate-100 text-slate-650 transition-all">
                🔑 Product Licensing
            </button>
            <button onclick="switchHelpCategory('sellers')" id="help-btn-sellers" class="help-nav-btn w-full text-left px-4 py-3 rounded-lg text-xs font-bold hover:bg-slate-100 text-slate-650 transition-all">
                💼 Selling & Payouts
            </button>
            
            <!-- Ticket CTA Box -->
            <div class="border border-slate-200 rounded-xl p-6 bg-slate-50/50 mt-6 space-y-4">
                <h4 class="font-extrabold text-sm text-slate-800 leading-tight">Can't find what you need?</h4>
                <p class="text-[11px] text-slate-500 leading-relaxed">Our support desk is online 24/7. Open a ticket for specialized technical assistance.</p>
                <a href="<?php echo is_logged_in() ? 'index.php?page=dashboard&tab=support_tickets' : '#'; ?>" <?php echo !is_logged_in() ? 'onclick="openLoginModal()"' : ''; ?> class="block text-center py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold rounded-lg text-xs shadow transition-all border-b-2 border-slate-950">
                    🎫 Submit Support Ticket
                </a>
            </div>
        </div>

        <!-- FAQ Articles Area -->
        <div class="md:col-span-2 space-y-4" id="faq-container">
            
            <!-- Category: Buyers -->
            <div class="faq-group space-y-4" data-cat="buyers">
                <h3 class="font-black text-slate-800 text-base border-b pb-2 mb-3">Buyer Guide & Purchasing</h3>
                
                <div class="faq-item bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <button class="w-full text-left px-6 py-4 flex items-center justify-between font-bold text-slate-800 text-xs focus:outline-none" onclick="toggleFAQ(this)">
                        <span>How do I download my purchased products?</span>
                        <span class="faq-icon text-slate-400 transition-transform font-bold">+</span>
                    </button>
                    <div class="faq-answer hidden px-6 pb-5 text-xs text-slate-500 leading-relaxed border-t pt-3 bg-slate-50/20">
                        Once checkout is completed successfully, you can download your scripts immediately under your dashboard in the **My Purchases** tab. You will also receive an email receipt with direct download links.
                    </div>
                </div>

                <div class="faq-item bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <button class="w-full text-left px-6 py-4 flex items-center justify-between font-bold text-slate-800 text-xs focus:outline-none" onclick="toggleFAQ(this)">
                        <span>What is the platform refund policy?</span>
                        <span class="faq-icon text-slate-400 transition-transform font-bold">+</span>
                    </button>
                    <div class="faq-answer hidden px-6 pb-5 text-xs text-slate-500 leading-relaxed border-t pt-3 bg-slate-50/20">
                        Refunds are granted in cases of defective scripts, missing core features, or when the file contains malicious/malfunctioning codes that the seller fails to patch. Review our Refund Policy under the Policies footer tab for full terms.
                    </div>
                </div>
            </div>

            <!-- Category: Licensing -->
            <div class="faq-group space-y-4" data-cat="licensing">
                <h3 class="font-black text-slate-800 text-base border-b pb-2 mb-3">Product Licensing</h3>
                
                <div class="faq-item bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <button class="w-full text-left px-6 py-4 flex items-center justify-between font-bold text-slate-800 text-xs focus:outline-none" onclick="toggleFAQ(this)">
                        <span>What is the difference between Standard and Extended Licenses?</span>
                        <span class="faq-icon text-slate-400 transition-transform font-bold">+</span>
                    </button>
                    <div class="faq-answer hidden px-6 pb-5 text-xs text-slate-500 leading-relaxed border-t pt-3 bg-slate-50/20">
                        A **Standard License** permits usage on a single domain/installation for personal or client projects. An **Extended License** allows usage across multiple domains, commercial redistribution parameters, or inclusion in SaaS platforms.
                    </div>
                </div>

                <div class="faq-item bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <button class="w-full text-left px-6 py-4 flex items-center justify-between font-bold text-slate-800 text-xs focus:outline-none" onclick="toggleFAQ(this)">
                        <span>How can I upgrade my standard license key?</span>
                        <span class="faq-icon text-slate-400 transition-transform font-bold">+</span>
                    </button>
                    <div class="faq-answer hidden px-6 pb-5 text-xs text-slate-500 leading-relaxed border-t pt-3 bg-slate-50/20">
                        Navigate to **My Purchases** under your user dashboard, locate the script, and click "Upgrade to Extended". Pay the price delta, and your key will automatically elevate inside the system.
                    </div>
                </div>
            </div>

            <!-- Category: Sellers -->
            <div class="faq-group space-y-4" data-cat="sellers">
                <h3 class="font-black text-slate-800 text-base border-b pb-2 mb-3">Selling & Payouts</h3>
                
                <div class="faq-item bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <button class="w-full text-left px-6 py-4 flex items-center justify-between font-bold text-slate-800 text-xs focus:outline-none" onclick="toggleFAQ(this)">
                        <span>How are seller payouts processed and is there an escrow lock?</span>
                        <span class="faq-icon text-slate-400 transition-transform font-bold">+</span>
                    </button>
                    <div class="faq-answer hidden px-6 pb-5 text-xs text-slate-500 leading-relaxed border-t pt-3 bg-slate-50/20">
                        Earnings are held in pending clearance balances for the platform-configured escrow period (standard is 7 days) to secure payments. Once matured, funds transfer to your withdrawable balance. Cashouts are processed directly via secure Paystack channels.
                    </div>
                </div>

                <div class="faq-item bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <button class="w-full text-left px-6 py-4 flex items-center justify-between font-bold text-slate-800 text-xs focus:outline-none" onclick="toggleFAQ(this)">
                        <span>What are the platform commission charges?</span>
                        <span class="faq-icon text-slate-400 transition-transform font-bold">+</span>
                    </button>
                    <div class="faq-answer hidden px-6 pb-5 text-xs text-slate-500 leading-relaxed border-t pt-3 bg-slate-50/20">
                        CodeVault charges a flat 15% transaction fee on catalog purchases. For cashouts, a platform-configured withdrawal fee applies (e.g. 5% admin charge) covering payment gateway conversions.
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function toggleFAQ(button) {
    const answer = button.nextElementSibling;
    const icon = button.querySelector('.faq-icon');
    
    if (answer.classList.contains('hidden')) {
        answer.classList.remove('hidden');
        icon.innerText = '−';
        icon.classList.add('rotate-180');
    } else {
        answer.classList.add('hidden');
        icon.innerText = '+';
        icon.classList.remove('rotate-180');
    }
}

function switchHelpCategory(cat) {
    // Reset buttons styles
    document.querySelectorAll('.help-nav-btn').forEach(btn => {
        btn.classList.remove('bg-[#5cb85c]', 'text-white');
        btn.classList.add('hover:bg-slate-100', 'text-slate-650');
    });
    
    // Set active button style
    const activeBtn = document.getElementById('help-btn-' + cat);
    if (activeBtn) {
        activeBtn.classList.remove('hover:bg-slate-100', 'text-slate-650');
        activeBtn.classList.add('bg-[#5cb85c]', 'text-white');
    }
    
    // Hide/Show sections
    document.querySelectorAll('.faq-group').forEach(group => {
        if (cat === 'all' || group.getAttribute('data-cat') === cat) {
            group.classList.remove('hidden');
        } else {
            group.classList.add('hidden');
        }
    });
}

function searchFAQs() {
    const query = document.getElementById('faq-search').value.toLowerCase();
    
    document.querySelectorAll('.faq-item').forEach(item => {
        const text = item.innerText.toLowerCase();
        if (text.includes(query)) {
            item.classList.remove('hidden');
        } else {
            item.classList.add('hidden');
        }
    });
}
</script>
