<?php
/**
 * Legal Policies & Compliance Center
 * Interactive tabbed page for ToS, Privacy, Refunds, Disputes, AML and KYC guidelines.
 */

$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'terms';
$valid_tabs = ['terms', 'privacy', 'refunds', 'chargebacks', 'aml', 'licensing'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'terms';
}

$site_name = htmlspecialchars(get_platform_name());
$escrow_days = intval(get_setting('escrow_lock_days', 7));
$withdrawal_fee = htmlspecialchars(get_setting('withdrawal_charge', '5'));
?>
<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-8 select-none">
    
    <!-- Hero Header Banner -->
    <div class="relative rounded-2xl overflow-hidden bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 p-8 sm:p-12 shadow-xl border border-slate-700/50">
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#000_1px,transparent_1px),linear-gradient(to_bottom,#000_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-25"></div>
        <div class="relative z-10 space-y-4 max-w-2xl">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold tracking-wider text-emerald-400 bg-emerald-400/10 uppercase border border-emerald-400/20">
                🛡️ Trust & Security Suite
            </span>
            <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight leading-none">
                Platform Policies &amp; Compliance Center
            </h1>
            <p class="text-sm text-slate-300 leading-relaxed font-medium">
                Legal agreements, license classifications, refund processing, and financial protection guidelines built to safeguard buyers, sellers, and the CodeVault ecosystem.
            </p>
        </div>
    </div>

    <!-- Double Column Tab Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        <!-- Left Sidebar Navigation -->
        <div class="lg:col-span-1 space-y-2">
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-3 mb-3">Sections</p>
                
                <a href="<?php echo url('policies'); ?>?tab=terms" 
                   class="flex items-center gap-3 px-3.5 py-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'terms' ? 'bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-100/50' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                   <span class="text-base">📜</span> Terms of Service
                </a>
                
                <a href="<?php echo url('policies'); ?>?tab=licensing" 
                   class="flex items-center gap-3 px-3.5 py-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'licensing' ? 'bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-100/50' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                   <span class="text-base">🏷️</span> Licensing Terms
                </a>

                <a href="<?php echo url('policies'); ?>?tab=privacy" 
                   class="flex items-center gap-3 px-3.5 py-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'privacy' ? 'bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-100/50' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                   <span class="text-base">🔒</span> Privacy Policy
                </a>

                <a href="<?php echo url('policies'); ?>?tab=refunds" 
                   class="flex items-center gap-3 px-3.5 py-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'refunds' ? 'bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-100/50' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                   <span class="text-base">💵</span> Refund &amp; Disputes
                </a>

                <a href="<?php echo url('policies'); ?>?tab=chargebacks" 
                   class="flex items-center gap-3 px-3.5 py-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'chargebacks' ? 'bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-100/50' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                   <span class="text-base">🛡️</span> Chargebacks &amp; Fraud
                </a>

                <a href="<?php echo url('policies'); ?>?tab=aml" 
                   class="flex items-center gap-3 px-3.5 py-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'aml' ? 'bg-emerald-50 text-emerald-700 shadow-sm border border-emerald-100/50' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'; ?>">
                   <span class="text-base">👤</span> KYC &amp; AML Compliance
                </a>
            </div>
            
            <!-- Quick Helper Card -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-950 text-white rounded-xl p-5 shadow-md border border-slate-800 space-y-3 hidden lg:block">
                <h4 class="font-extrabold text-xs tracking-tight">Need legal assistance?</h4>
                <p class="text-[11px] text-slate-400 leading-relaxed">If you have questions regarding developer compliance, licensing issues, or corporate compliance audits, reach out directly to support.</p>
                <a href="mailto:support@<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'codevault.com'); ?>" class="inline-flex items-center text-[10px] font-bold text-emerald-400 hover:underline">
                    Contact Support &rarr;
                </a>
            </div>
        </div>

        <!-- Right Content Area -->
        <div class="lg:col-span-3 bg-white border border-gray-200 rounded-xl p-6 sm:p-8 shadow-sm">
            
            <!-- ---------------- TAB: TERMS OF SERVICE ---------------- -->
            <?php if ($active_tab === 'terms'): ?>
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-1">Terms of Service (ToS)</h2>
                        <p class="text-xs text-slate-500">Effective Date: June 5, 2026. Please read this agreement carefully.</p>
                    </div>

                    <div class="prose prose-slate max-w-none text-sm md:text-base text-slate-600 leading-relaxed space-y-4">
                        <p>Welcome to <strong><?php echo $site_name; ?></strong>. By accessing our platform, purchasing digital assets, listing scripts, or registering an account, you agree to be bound by these Terms of Service.</p>
                        
                        <div class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded">
                            <p class="font-bold text-amber-800 mb-1">💡 Key Agreement Summary</p>
                            <p class="text-[11px] text-amber-900">Sellers list digital files matching intellectual property guidelines. Buyers receive usage rights defined under specific License terms. The platform retains a flat commission fee on sales and provides escrow holding features to verify clean codes.</p>
                        </div>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">1. User Accounts and Verification</h3>
                        <p>User accounts are categorised as Buyers, Sellers, or Affiliates. Sellers are required to complete KYC identity verification to withdraw earnings. Users must safeguard passwords and are strictly prohibited from sharing accounts.</p>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">2. Seller Listing Rules &amp; Responsibilities</h3>
                        <p>As a seller, you represent and warrant that:</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>You are the sole author of the listed code or possess full commercial resale licenses.</li>
                            <li>Your products do not contain malware, spyware, backdoors, or obfuscated malicious scripts.</li>
                            <li>Your listings are described accurately, and you will provide technical support to buyers matching your promises.</li>
                        </ul>
                        <p>Platform administrators reserve the right to audit, reject, or permanently disable listings violating these standards.</p>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">3. Platform Commissions and Withdrawals</h3>
                        <p>The platform takes a standard commission from each sale. Payout withdrawals are held under a secure **<?php echo $escrow_days; ?>-day escrow lock period** to verify payment settlement and protect against fraud. Cashout settlements are subject to a **<?php echo $withdrawal_fee; ?>%** processing charge fee.</p>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">4. Prohibited Platform Abuse</h3>
                        <p>Users shall not engage in system manipulation, fake reviews, self-referrals through the affiliate program, spamming, or server DDoS activities. Any code exploit or unauthorized access attempts will trigger instant ban and IP-level reporting to legal authorities.</p>
                    </div>
                </div>

            <!-- ---------------- TAB: LICENSING TERMS ---------------- -->
            <?php elseif ($active_tab === 'licensing'): ?>
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-1">Licensing Terms</h2>
                        <p class="text-xs text-slate-500">Usage permissions granted for digital code purchases.</p>
                    </div>

                    <div class="prose prose-slate max-w-none text-sm md:text-base text-slate-600 leading-relaxed space-y-4">
                        <p>All source code, templates, and plugins purchased on <strong><?php echo $site_name; ?></strong> are subject to digital usage license agreements. Licensing rules are designed to protect developer intellectual property while granting buyers operational flexibility.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                            <div class="border border-gray-200 rounded-lg p-4 space-y-2">
                                <span class="px-2 py-0.5 rounded bg-emerald-50 border border-emerald-100 text-emerald-800 font-extrabold text-[9px] uppercase">Standard License</span>
                                <h4 class="font-extrabold text-xs text-slate-800">Single End Product Usage</h4>
                                <p class="text-[11px] text-slate-500 leading-relaxed">Allows the buyer to use the code to create <strong>one (1) single end product</strong> for themselves or for a client. The end product cannot be resold or distributed directly.</p>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4 space-y-2">
                                <span class="px-2 py-0.5 rounded bg-blue-50 border border-blue-100 text-blue-800 font-extrabold text-[9px] uppercase">Extended License</span>
                                <h4 class="font-extrabold text-xs text-slate-800">Multiple/Commercial End Products</h4>
                                <p class="text-[11px] text-slate-500 leading-relaxed">Allows the buyer to deploy the script in <strong>unlimited websites/projects</strong> and sell or charge end-users for access (e.g. as a SaaS platform).</p>
                            </div>
                        </div>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">1. Strict Ownership Limitations</h3>
                        <p>Purchasing digital products transfers a <strong>usage license</strong>, NOT ownership. Buyers are prohibited from re-licensing, redistributing, or listing the code on other competitor marketplaces under any license tier.</p>
                    </div>
                </div>

            <!-- ---------------- TAB: PRIVACY POLICY ---------------- -->
            <?php elseif ($active_tab === 'privacy'): ?>
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-1">Privacy Policy</h2>
                        <p class="text-xs text-slate-500">How we process and protect your developer and user data.</p>
                    </div>

                    <div class="prose prose-slate max-w-none text-sm md:text-base text-slate-600 leading-relaxed space-y-4">
                        <p>At <strong><?php echo $site_name; ?></strong>, your privacy and data security are our highest priority. This Privacy Policy details how we collect, store, and utilize personal details under GDPR/CCPA compliance guidelines.</p>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">1. Information We Collect</h3>
                        <ul class="list-disc pl-5 space-y-1">
                            <li><strong>Identity Data</strong>: Full name, profile avatar, biography, and email address.</li>
                            <li><strong>Financial Details</strong>: Settlement bank account details (for verified sellers), Paystack transaction references. We do NOT store full credit card details on our servers.</li>
                            <li><strong>Technical Logs</strong>: IP address, cookies, and referral identifiers to monitor security.</li>
                        </ul>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">2. Security Measures</h3>
                        <p>All database records are securely stored, and connections are encrypted using secure SSL channels. Admin settings and sensitive API keys are backtick-escaped and encrypted to prevent data extraction leaks.</p>
                    </div>
                </div>

            <!-- ---------------- TAB: REFUND & DISPUTES ---------------- -->
            <?php elseif ($active_tab === 'refunds'): ?>
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-1">Refund &amp; Dispute Resolution Policy</h2>
                        <p class="text-xs text-slate-500">Specific refund terms and dispute mediation timelines.</p>
                    </div>

                    <div class="prose prose-slate max-w-none text-sm md:text-base text-slate-600 leading-relaxed space-y-4">
                        <p>Because digital source code assets are fully downloadable and can be copied instantly upon purchase, we operate under a strict refund policy to protect sellers from fraud while ensuring buyers get working files.</p>
                        
                        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-3 rounded">
                            <p class="font-bold text-emerald-800 mb-1">💵 Escrow Resolution Window</p>
                            <p class="text-[11px] text-emerald-900">Refund requests and dispute support tickets <strong>must be filed before the <?php echo $escrow_days; ?>-day escrow lock period expires</strong>. Once the escrow matures and funds are moved to the seller's withdrawable balance, refund requests cannot be processed by the platform.</p>
                        </div>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">1. Valid Refund Criteria</h3>
                        <p>A buyer is eligible to request a refund only under the following conditions:</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li><strong>The item is broken</strong>: The code contains critical syntax errors or bugs making it unusable, and the seller fails to provide support or resolve the issue within 48 hours of contact.</li>
                            <li><strong>Misrepresentation</strong>: The file features do not match the listing description or demo system.</li>
                            <li><strong>Failure to Deliver</strong>: The download link fails to work, and support is unable to deliver the files.</li>
                        </ul>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">2. Invalid Refund Grounds</h3>
                        <p>Refunds will be rejected if the buyer purchased the item by mistake, lacks the technical skills to run the code, simply changed their mind, or is trying to retrieve files free of charge (fraudulent downloads).</p>
                    </div>
                </div>

            <!-- ---------------- TAB: CHARGEBACKS & FRAUD ---------------- -->
            <?php elseif ($active_tab === 'chargebacks'): ?>
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-1">Chargeback &amp; Fraud Prevention Policy</h2>
                        <p class="text-xs text-slate-500">Consequences of payment disputes and platform safety rules.</p>
                    </div>

                    <div class="prose prose-slate max-w-none text-sm md:text-base text-slate-600 leading-relaxed space-y-4">
                        <p>A chargeback is a payment dispute opened directly with a credit card company or banking institution. <strong><?php echo $site_name; ?></strong> maintains a strict <strong>Zero Tolerance Policy</strong> against unauthorized chargebacks.</p>
                        
                        <div class="bg-rose-50 border-l-4 border-rose-500 p-3 rounded">
                            <p class="font-bold text-rose-800 mb-1">⚠️ Strict Chargeback Penalties</p>
                            <p class="text-[11px] text-rose-950">If a buyer initiates an unauthorized chargeback or dispute, the platform will immediately:
                                <br>&bull; Permanently ban the buyer's account and blacklist their IP/email.
                                <br>&bull; Terminate download access and cancel all commercial licenses for all purchased scripts.
                                <br>&bull; Submit the downloader's log data (IP, browser headers, file download logs) directly to Paystack and banking fraud databases.
                            </p>
                        </div>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">1. Protecting Sellers against Chargebacks</h3>
                        <p>If a buyer initiates a dispute during the **<?php echo $escrow_days; ?>-day escrow lock period**, the pending funds are withheld in escrow until the dispute is resolved. If the chargeback is won by the buyer, the pending funds are revoked from the seller's wallet to prevent cash leakage of fraudulent money.</p>
                    </div>
                </div>

            <!-- ---------------- TAB: KYC & AML COMPLIANCE ---------------- -->
            <?php elseif ($active_tab === 'aml'): ?>
                <div class="space-y-6">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900 tracking-tight mb-1">KYC &amp; AML Compliance Policy</h2>
                        <p class="text-xs text-slate-500">Anti-Money Laundering and Know Your Customer developer guidelines.</p>
                    </div>

                    <div class="prose prose-slate max-w-none text-sm md:text-base text-slate-600 leading-relaxed space-y-4">
                        <p>As a global marketplace offering cashout settlements, we comply with strict Anti-Money Laundering (AML) and Know Your Customer (KYC) regulations to prevent financial abuse.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                            <div class="border border-gray-200 rounded-lg p-4 space-y-2">
                                <span class="px-2 py-0.5 rounded bg-emerald-50 border border-emerald-100 text-emerald-800 font-extrabold text-[9px] uppercase">KYC Verification</span>
                                <h4 class="font-extrabold text-xs text-slate-800">Identity Checks for Sellers</h4>
                                <p class="text-[11px] text-slate-500 leading-relaxed">Before a seller can submit a withdrawal request, they must upload a clear document image (Government ID, passport, or company registration document). The admin reviews and approves these requests manually to prevent identity theft.</p>
                            </div>
                            
                            <div class="border border-gray-200 rounded-lg p-4 space-y-2">
                                <span class="px-2 py-0.5 rounded bg-amber-50 border border-amber-100 text-amber-800 font-extrabold text-[9px] uppercase">AML Safeguards</span>
                                <h4 class="font-extrabold text-xs text-slate-800">Escrow Hold Limits</h4>
                                <p class="text-[11px] text-slate-500 leading-relaxed">Funds are held in escrow for **<?php echo $escrow_days; ?> days** to monitor transaction safety. Directly funding or withdrawing balances without real marketplace sales is strictly blocked to prevent platform use for money laundering.</p>
                            </div>
                        </div>

                        <h3 class="font-black text-slate-800 text-sm border-b pb-1 mt-4 uppercase">1. Reporting Financial Abuse</h3>
                        <p>Any suspicious billing activities, multiple credit cards linked to one account, or seller accounts with massive sales loops will be locked immediately. CodeVault will report audited data to financial crime agencies as legally required.</p>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
