<?php
$pdo = get_db_connection();
$pending_transfers = $pdo->query("SELECT COUNT(*) FROM transactions WHERE payment_method='transfer' AND status='pending'")->fetchColumn();
$open_tickets = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
if ($current_page == 'index') $current_page = 'dashboard';

$site_logo = get_setting('site_logo') ?: '/assets/uploads/rectangular_logo_cropped.png';
$site_title = get_setting('site_title', 'EXAM-HUB');
$favicon_url = get_setting('site_favicon') ?: generate_favicon($site_title);
$display_logo = $site_logo ? $site_logo : $favicon_url;
?>
    <!-- Mobile Sidebar Backdrop -->
    <div id="sidebarBackdrop" class="fixed inset-0 z-40 bg-black/50 hidden md:hidden" onclick="toggleSidebar()"></div>
    
    <aside id="adminSidebar" class="fixed md:static inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white flex flex-col h-screen overflow-hidden shrink-0 transform transition-transform duration-300 -translate-x-full md:translate-x-0">
        <div class="h-16 flex items-center justify-between px-4 font-bold text-xl border-b border-slate-800 shrink-0">
            <a href="/admin/dashboard" class="block">
                <img src="<?= $display_logo ?>" class="h-10 w-auto object-contain" alt="Logo">
            </a>
            <button class="md:hidden text-gray-400 hover:text-white focus:outline-none" onclick="toggleSidebar()">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
        <a href="/admin/dashboard" class="block px-4 py-2 rounded font-medium <?= $current_page === 'dashboard' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">Dashboard</a>
        <a href="/admin/users" class="block px-4 py-2 rounded <?= $current_page === 'users' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">User Management</a>
        <a href="/admin/orders" class="block px-4 py-2 rounded <?= $current_page === 'orders' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">Orders</a>
        <a href="/admin/transfers" class="block px-4 py-2 rounded flex justify-between <?= $current_page === 'transfers' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">
            Transfers 
            <?php if($pending_transfers > 0): ?>
                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?= $pending_transfers ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/tickets" class="block px-4 py-2 rounded flex justify-between <?= $current_page === 'tickets' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">
            Support Tickets 
            <?php if($open_tickets > 0): ?>
                <span class="bg-yellow-500 text-slate-900 text-xs px-2 py-1 rounded-full font-bold"><?= $open_tickets ?></span>
            <?php endif; ?>
        </a>
        <a href="/admin/products" class="block px-4 py-2 rounded <?= $current_page === 'products' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">Product Mapping</a>
        <a href="/admin/pages" class="block px-4 py-2 rounded <?= $current_page === 'pages' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">Dynamic Pages</a>
        <a href="/admin/api_users" class="block px-4 py-2 rounded <?= $current_page === 'api_users' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">API Users</a>
        <a href="/admin/api_reference" class="block px-4 py-2 rounded <?= $current_page === 'api_reference' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">API Reference</a>
        
        <div class="pt-4 mt-4 border-t border-slate-800 space-y-2">
            <a href="/admin/security" class="block px-4 py-2 rounded <?= $current_page === 'security' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">Security</a>
            <a href="/admin/marketing" class="block px-4 py-2 rounded <?= $current_page === 'marketing' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">Email Marketing</a>
            <a href="/admin/settings" class="block px-4 py-2 rounded <?= $current_page === 'settings' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">Settings</a>
        </div>
        <div class="pt-4 mt-4 border-t border-slate-800 space-y-2">
            <a href="/admin/profile" class="block px-4 py-2 rounded <?= $current_page === 'profile' ? 'bg-blue-600 text-white' : 'hover:bg-slate-800 text-slate-300' ?>">Admin Profile</a>
            <a href="/logout" class="block px-4 py-2 hover:bg-red-900 rounded text-red-400">Logout</a>
        </div>
    </nav>
</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar.classList.toggle('-translate-x-full');
    backdrop.classList.toggle('hidden');
}
</script>
