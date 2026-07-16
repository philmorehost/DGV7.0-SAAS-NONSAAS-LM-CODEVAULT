<?php
// Affiliate Referral Program for CodeVault PHP
if (!is_logged_in()) {
    echo "<div class='bg-white rounded border border-gray-200 p-16 text-center shadow-sm max-w-xl mx-auto'><span class='text-4xl'>🔗</span><h3 class='font-black mt-4 text-xl tracking-tight text-slate-800'>Sign In to Continue</h3><p class='text-xs text-slate-500 mt-2 leading-relaxed'>Generate custom tracker codes and access your passive earnings ledger sheets securely by registering or logging in.</p><button onclick='openLoginModal()' class='mt-6 px-6 py-2.5 bg-[#5cb85c] text-white hover:bg-[#4cae4c] font-bold rounded text-xs transition-colors shadow'>Login Now</button></div>";
    return;
}

$user = get_logged_in_user();
$userId = $user['id'];

// Fetch referral log history
$ref_stmt = $db->prepare("SELECT ar.*, u.name as referred_name, u.created_at as joined_date FROM affiliate_referrals ar JOIN users u ON ar.referred_id = u.id WHERE ar.referrer_id = ? ORDER BY ar.id DESC");
$ref_stmt->execute([$userId]);
$referrals = $ref_stmt->fetchAll();

// Total commissions calculated
$total_commissions = 0.0;
foreach($referrals as $ref) {
    $total_commissions += floatval($ref['amount']);
}

// Generate base shareable url
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$req_uri = $_SERVER['REQUEST_URI'];
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$host" . explode('?', $req_uri)[0];
$referral_url = $base_url . "?ref=" . $userId;
?>

<!-- Breadcrumbs -->
<nav class="text-xs text-gray-500 mb-6 flex items-center gap-1 bg-white px-4 py-3 border rounded shadow-sm">
    <a href="index.php?page=marketplace" class="hover:text-slate-900 transition-colors">Marketplace</a>
    <span class="text-gray-400 font-bold">/</span>
    <span class="text-slate-700 font-semibold">Affiliate Program</span>
</nav>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column: Earnings & Tracking Code Generator -->
    <div class="lg:col-span-1 space-y-6">
        
        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm space-y-5">
            <div>
                <span class="text-[9px] font-bold uppercase tracking-widest text-[#5cb85c] bg-emerald-50 px-2 py-0.5 rounded border border-emerald-150">Affiliate System</span>
                <h3 class="font-extrabold text-slate-900 text-base mt-3">Referral Statistics</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">Earn <strong><?php echo htmlspecialchars(get_setting('affiliate_percentage', '10')); ?>%</strong> commission on the first purchase made by users who register using your referral link.</p>
            </div>

            <!-- Total Earnings -->
            <div class="p-5 bg-slate-50 border border-gray-150 rounded text-center">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Accrued Commissions</p>
                <p class="font-mono font-black text-slate-900 text-3xl my-2"><?php echo format_price($total_commissions); ?></p>
                <p class="text-[10px] text-[#5cb85c] font-bold flex items-center justify-center gap-0.5">✓ Settled inside available balance</p>
            </div>

            <!-- Tracker URL Copy -->
            <div class="space-y-2">
                <label class="text-[10px] font-bold text-slate-450 uppercase tracking-widest block">Your Tracking Link</label>
                <div class="flex p-1 bg-white border border-gray-350 rounded">
                    <input 
                        type="text" 
                        id="affiliate-tracker-url-raw" 
                        readonly 
                        value="<?php echo htmlspecialchars($referral_url); ?>" 
                        class="flex-1 bg-transparent border-none text-[10px] font-mono outline-none text-slate-600 font-semibold px-2 w-full"
                    >
                    <button 
                        onclick="copyAffiliateURL()"
                        class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-[10px] uppercase rounded transition-colors outline-none"
                    >
                        Copy
                    </button>
                </div>
            </div>
        </div>

        <!-- Terms guidelines -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-900 p-6 rounded text-white shadow space-y-2 relative overflow-hidden">
            <h4 class="font-bold text-sm tracking-tight leading-none mb-1">Affiliate Guidelines</h4>
            <p class="text-xs text-slate-300 leading-relaxed font-normal">Commissions are automatically credited to your wallet balance. CodeVault maintains a zero tolerance policy against spam, self-referrals, or fake account abuse.</p>
        </div>

    </div>

    <!-- Right Column: Referrals list -->
    <div class="lg:col-span-2 bg-white border border-gray-200 rounded p-6 shadow-sm space-y-4">
        <h3 class="font-extrabold text-sm text-slate-850 border-b pb-2">Referred Conversions Ledger</h3>
        
        <?php if (empty($referrals)): ?>
            <div class="text-center py-16 bg-slate-50/50 border border-dashed rounded text-xs text-slate-400 font-bold">
                No active referred users conversion recorded. Promote your tracking url to start earning.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto border border-gray-150 rounded">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b font-bold text-slate-500">
                            <th class="p-3">Referred User</th>
                            <th class="p-3">Joined Date</th>
                            <th class="p-3">Commission Earned</th>
                            <th class="p-3 text-right">Payout Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-slate-655">
                        <?php foreach($referrals as $ref): ?>
                            <tr>
                                <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($ref['referred_name']); ?></td>
                                <td class="p-3 font-mono font-semibold text-slate-450"><?php echo date('Y-m-d', strtotime($ref['joined_date'])); ?></td>
                                <td class="p-3 font-black font-mono text-slate-850"><?php echo format_price($ref['amount']); ?></td>
                                <td class="p-3 text-right">
                                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 font-bold text-[9px] border border-emerald-100 uppercase">Settled</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    function copyAffiliateURL() {
        const copyInput = document.getElementById('affiliate-tracker-url-raw');
        if (copyInput) {
            copyInput.select();
            copyInput.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyInput.value);
            alert("Success! Referral url copied to clipboard.");
        }
    }
</script>
