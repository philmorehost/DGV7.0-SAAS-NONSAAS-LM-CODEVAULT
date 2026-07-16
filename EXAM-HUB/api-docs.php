<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 py-16">
    <div class="text-center mb-16">
        <h1 class="text-4xl font-extrabold text-gray-900">Developer API Documentation</h1>
        <p class="mt-4 text-xl text-gray-600">Integrate EXAM-HUB into your application to automate PIN purchases.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
        <div class="flex flex-col md:flex-row">
            <!-- Sidebar -->
            <div class="w-full md:w-64 bg-gray-50 p-6 border-r border-gray-100">
                <nav class="space-y-2 sticky top-6">
                    <a href="#authentication"
                        class="block font-bold text-gray-900 hover:text-blue-600">Authentication</a>
                    <a href="#endpoints" class="block font-bold text-gray-900 hover:text-blue-600 mt-6">Endpoints</a>
                    <div class="pl-4 space-y-2 mt-2 border-l-2 border-blue-100">
                        <a href="#get-packages" class="block text-sm text-gray-600 hover:text-blue-600">Get Packages</a>
                        <a href="#buy-pins" class="block text-sm text-gray-600 hover:text-blue-600">Buy PINs</a>
                    </div>
                    <a href="#errors" class="block font-bold text-gray-900 hover:text-blue-600 mt-6">Error Codes</a>
                </nav>
            </div>

            <!-- Content -->
            <div class="flex-1 p-8 md:p-12 prose prose-blue max-w-none">

                <section id="authentication" class="mb-16">
                    <h2 class="text-2xl font-bold text-gray-900 border-b pb-4 mb-6">Authentication</h2>
                    <p>All API requests require a Bearer token in the Authorization header. You can obtain your API key
                        from the <a href="/user/api" class="text-blue-600 font-bold">API Access Dashboard</a>.</p>

                    <div class="bg-slate-900 rounded-xl p-4 my-4 font-mono text-sm overflow-x-auto text-gray-300">
                        Authorization: Bearer YOUR_API_KEY_HERE
                    </div>
                    <p class="text-sm text-red-600 mt-2 font-bold">Note: Requests must originate from the domain name
                        registered in your dashboard. Any requests originating elsewhere will be rejected or blocked.
                    </p>
                </section>

                <section id="endpoints" class="mb-16">
                    <h2 class="text-2xl font-bold text-gray-900 border-b pb-4 mb-6">Endpoints</h2>

                    <div id="get-packages" class="mb-12">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-4">
                            <span class="bg-green-100 text-green-700 px-3 py-1 rounded text-sm uppercase">GET</span>
                            /api/v1/packages.php
                        </h3>
                        <p>Returns a list of all active exam PIN products available for purchase, along with their IDs
                            and prices.</p>

                        <h4 class="font-bold text-gray-900 mt-6 mb-2">Example Response (200 OK)</h4>
                        <div class="bg-slate-900 rounded-xl p-4 font-mono text-sm overflow-x-auto text-green-400">
                            {
                            "status": true,
                            "data": [
                            {
                            "id": 1,
                            "name": "WAEC Result Checker",
                            "price": 3500.00,
                            "discounted_price": 3400.00
                            },
                            {
                            "id": 2,
                            "name": "NECO Result Token",
                            "price": 1200.00,
                            "discounted_price": 1150.00
                            }
                            ]
                            }
                        </div>
                    </div>

                    <div id="buy-pins" class="mb-12">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-4">
                            <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-sm uppercase">POST</span>
                            /api/v1/buy.php
                        </h3>
                        <p>Purchase exam PINs. The cost (minus your API discount) will be deducted directly from your
                            EXAM-HUB wallet.</p>

                        <h4 class="font-bold text-gray-900 mt-6 mb-2">Request Body (JSON)</h4>
                        <div class="bg-slate-900 rounded-xl p-4 font-mono text-sm overflow-x-auto text-blue-300">
                            {
                            "card_id": 1,
                            "quantity": 2,
                            "reference": "YOUR_UNIQUE_TX_REF"
                            }
                        </div>

                        <h4 class="font-bold text-gray-900 mt-6 mb-2">Example Response (200 OK)</h4>
                        <div class="bg-slate-900 rounded-xl p-4 font-mono text-sm overflow-x-auto text-green-400">
                            {
                            "status": true,
                            "message": "Purchase successful",
                            "reference": "YOUR_UNIQUE_TX_REF",
                            "total_charged": 6800.00,
                            "pins": [
                            {
                            "pin": "123456789012",
                            "serial_no": "WRC12345"
                            },
                            {
                            "pin": "987654321098",
                            "serial_no": "WRC98765"
                            }
                            ]
                            }
                        </div>
                    </div>
                </section>

                <section id="errors">
                    <h2 class="text-2xl font-bold text-gray-900 border-b pb-4 mb-6">Error Codes</h2>
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="py-2">HTTP Status</th>
                                <th class="py-2">Meaning</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="py-3 font-mono text-red-600 font-bold">401 Unauthorized</td>
                                <td class="py-3">Missing or invalid Bearer token, or API access is not active.</td>
                            </tr>
                            <tr>
                                <td class="py-3 font-mono text-red-600 font-bold">400 Bad Request</td>
                                <td class="py-3">Missing required parameters (e.g., card_id, quantity).</td>
                            </tr>
                            <tr>
                                <td class="py-3 font-mono text-red-600 font-bold">402 Payment Required</td>
                                <td class="py-3">Insufficient funds in your wallet.</td>
                            </tr>
                            <tr>
                                <td class="py-3 font-mono text-red-600 font-bold">404 Not Found</td>
                                <td class="py-3">The requested card_id does not exist or is out of stock.</td>
                            </tr>
                            <tr>
                                <td class="py-3 font-mono text-red-600 font-bold">422 Unprocessable Entity</td>
                                <td class="py-3">Provider API failed to generate the PINs.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>