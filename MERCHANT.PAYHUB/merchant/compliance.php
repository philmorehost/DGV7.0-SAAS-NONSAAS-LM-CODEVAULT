<?php
// php-version/merchant/compliance.php
require_once '../includes/functions.php';

if (!isLoggedIn()) redirect('../login.php');

$user = getAuthUser();
$db = Database::connect();

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_compliance') {
    $id_type = sanitize($_POST['id_type']);
    $id_expiry = sanitize($_POST['id_expiry_date'] ?? '');
    $bvn = sanitize($_POST['bvn'] ?? '');
    $address = sanitize($_POST['residential_address'] ?? '');
    $country = sanitize($_POST['country'] ?? 'Nigeria');

    // Basic logic for expiry date requirement
    $needs_expiry = in_array($id_type, ["Drivers License", "International Passport"]);
    
    // File Uploads
    $uploads = [];
    $files_to_handle = [
        'utility_bill', 'id_card'
    ];
    foreach ($files_to_handle as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);

            // If it's an image, we'll convert to jpg for better optimization
            $save_ext = $is_image ? 'jpg' : $ext;
            $filename = $field . '_' . $user['id'] . '_' . time() . '.' . $save_ext;

            if (!is_dir('../uploads')) mkdir('../uploads', 0755, true);

            $target = '../uploads/' . $filename;
            if ($is_image) {
                // resize_and_optimize_image uses copy internally for fallback,
                // but since this is an upload, we need to handle it properly.
                // We'll use the original extension for images but resize_and_optimize_image
                // will save as JPEG anyway. So .jpg is appropriate.
                resize_and_optimize_image($_FILES[$field]['tmp_name'], $target);
            } else {
                move_uploaded_file($_FILES[$field]['tmp_name'], $target);
            }
            $uploads[$field . '_path'] = $filename;
        }
    }

    // Handle Base64 Liveliness Snapshot
    if (!empty($_POST['liveliness_image'])) {
        $data = $_POST['liveliness_image'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]);
            $data = base64_decode($data);
            $filename = 'liveliness_' . $user['id'] . '_' . time() . '.' . $type;
            file_put_contents('../uploads/' . $filename, $data);
            $uploads['liveliness_path'] = $filename;
        }
    }

    try {
        $sql = "UPDATE users SET
            business_type = 'Starter',
            id_type = ?,
            id_expiry_date = ?,
            bvn = ?,
            residential_address = ?,
            country = ?,
            is_kyc_verified = 2";

        $params = [$id_type, $needs_expiry ? $id_expiry : null, $bvn, $address, $country];

        foreach ($uploads as $col => $val) {
            $sql .= ", $col = ?";
            $params[] = $val;
        }

        $sql .= " WHERE id = ?";
        $params[] = $user['id'];

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $success_msg = "Compliance documents submitted for review!";
        $user = getAuthUser();
    } catch (Exception $e) {
        $error_msg = "Submission failed: " . $e->getMessage();
    }
}

include '../includes/dashboard-head.php';
?>
<body class="bg-slate-50 text-slate-900 flex h-screen overflow-hidden" x-data>
    <?php include '../includes/sidebar.php'; ?>
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden" x-data="{
        idType: '<?php echo $user['id_type']; ?>',
        country: '<?php echo $user['country'] ?: 'Nigeria'; ?>',
        snapshot: null,
        showCamera: false,
        get needsExpiry() { return ['Drivers License', 'International Passport'].includes(this.idType) },
        get isNigerian() { return this.country === 'Nigeria' },
        startCamera() {
            this.showCamera = true;
            navigator.mediaDevices.getUserMedia({ video: true }).then(stream => {
                this.$refs.video.srcObject = stream;
            });
        },
        takeSnapshot() {
            const canvas = document.createElement('canvas');
            canvas.width = this.$refs.video.videoWidth;
            canvas.height = this.$refs.video.videoHeight;
            canvas.getContext('2d').drawImage(this.$refs.video, 0, 0);
            this.snapshot = canvas.toDataURL('image/jpeg');
            this.stopCamera();
        },
        stopCamera() {
            let stream = this.$refs.video.srcObject;
            if (stream) stream.getTracks().forEach(track => track.stop());
            this.showCamera = false;
        }
    }">
        <?php include '../includes/topbar.php'; ?>
        <div class="flex-1 overflow-y-auto p-4 sm:p-8">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 mb-2">Compliance & KYC</h1>
                    <p class="text-slate-500">Provide required documents to verify your business and increase limits</p>
                </div>

                <?php if ($success_msg): ?>
                    <div class="mb-6 p-4 bg-emerald-50 text-emerald-700 rounded-2xl border border-emerald-100 font-medium"><?php echo $success_msg; ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="mb-6 p-4 bg-red-50 text-red-700 rounded-2xl border border-red-100 font-medium"><?php echo $error_msg; ?></div>
                <?php endif; ?>

                <div class="grid lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">
                            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="update_compliance">
                                <input type="hidden" name="liveliness_image" :value="snapshot">

                                <div class="grid md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Country</label>
                                        <select name="country" x-model="country" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20 font-medium">
                                            <option value="Nigeria">Nigeria</option>
                                            <option value="Ghana">Ghana</option>
                                            <option value="Kenya">Kenya</option>
                                            <option value="South Africa">South Africa</option>
                                            <option value="Other">Other International</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <h4 class="font-bold text-slate-900 border-b border-slate-100 pb-2">Identity Information</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Government ID Type</label>
                                            <select name="id_type" x-model="idType" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500/20">
                                                <option value="">Select ID Type</option>
                                                <option value="NIN Slip" x-show="isNigerian">NIN Slip</option>
                                                <option value="Drivers License">Drivers License</option>
                                                <option value="Voters Card" x-show="isNigerian">Voters Card</option>
                                                <option value="International Passport">International Passport</option>
                                                <option value="National ID" x-show="!isNigerian">National ID Card</option>
                                            </select>
                                        </div>
                                        <div x-show="needsExpiry">
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">ID Expiry Date</label>
                                            <input type="date" name="id_expiry_date" value="<?php echo $user['id_expiry_date']; ?>" :required="needsExpiry" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div x-show="isNigerian">
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">BVN / NIN Number</label>
                                            <input type="text" name="bvn" value="<?php echo $user['bvn']; ?>" placeholder="222********" :required="isNigerian" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Residential Address</label>
                                        <input type="text" name="residential_address" value="<?php echo $user['residential_address']; ?>" placeholder="123 Main St, City" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none">
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <h4 class="font-bold text-slate-900 border-b border-slate-100 pb-2">Document Uploads</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="p-6 border-2 border-dashed border-slate-200 rounded-3xl text-center relative hover:border-indigo-400 transition-colors">
                                            <input type="file" name="id_card" required class="absolute inset-0 opacity-0 cursor-pointer">
                                            <i data-lucide="credit-card" class="text-slate-400 mb-2"></i>
                                            <p class="text-xs font-bold text-slate-900 uppercase">Government ID</p>
                                            <p class="text-[9px] text-slate-400 mt-1">Upload front and back in one image/PDF</p>
                                        </div>
                                        <div class="p-6 border-2 border-dashed border-slate-200 rounded-3xl text-center relative hover:border-indigo-400 transition-colors">
                                            <input type="file" name="utility_bill" required class="absolute inset-0 opacity-0 cursor-pointer">
                                            <i data-lucide="file-text" class="text-slate-400 mb-2"></i>
                                            <p class="text-xs font-bold text-slate-900 uppercase">Utility Bill</p>
                                            <p class="text-[9px] text-slate-400 mt-1">Proof of address (last 3 months)</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-6">
                                    <h4 class="font-bold text-slate-900 border-b border-slate-100 pb-2">Liveliness Check</h4>
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="w-full max-w-sm aspect-video bg-slate-100 rounded-3xl overflow-hidden relative border-2 border-slate-200">
                                            <video x-ref="video" autoplay playsinline class="w-full h-full object-cover" x-show="showCamera"></video>
                                            <img :src="snapshot" class="w-full h-full object-cover" x-show="snapshot && !showCamera">
                                            <div class="absolute inset-0 flex items-center justify-center" x-show="!showCamera && !snapshot">
                                                <i data-lucide="camera" class="text-slate-300 w-12 h-12"></i>
                                            </div>
                                        </div>
                                        <div class="flex gap-3">
                                            <button type="button" @click="startCamera()" x-show="!showCamera" class="bg-slate-900 text-white px-6 py-2.5 rounded-xl font-bold text-sm">Start Camera</button>
                                            <button type="button" @click="takeSnapshot()" x-show="showCamera" class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm">Capture Snapshot</button>
                                            <button type="button" @click="snapshot = null" x-show="snapshot" class="bg-rose-50 text-rose-600 px-6 py-2.5 rounded-xl font-bold text-sm">Retake</button>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="w-full bg-indigo-600 text-white py-4 rounded-xl font-bold shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all">Submit Compliance Documents</button>
                            </form>
                        </div>
                    </div>

                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-indigo-900 p-6 rounded-[2rem] text-white shadow-xl relative overflow-hidden">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                            <h4 class="font-bold mb-4 relative z-10">Verification Status</h4>
                            <div class="space-y-4 relative z-10">
                                <div class="p-4 bg-white/10 rounded-2xl border border-white/10 text-center">
                                    <p class="text-[10px] font-bold text-indigo-300 uppercase tracking-widest mb-1">Status</p>
                                    <p class="text-sm font-bold capitalize"><?php echo $user['is_kyc_verified'] == 1 ? 'Verified' : ($user['is_kyc_verified'] == 2 ? 'Under Review' : 'Action Required'); ?></p>
                                </div>
                                <p class="text-xs text-indigo-200 leading-relaxed text-center italic"><?php echo $user['kyc_notes'] ? "Admin: " . $user['kyc_notes'] : "Your documents will be reviewed by our team."; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php include "../includes/merchant-quick-actions.php"; ?>
</main>
<script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
    <script src="../assets/js/kyc-preview.js"></script>
</body>
</html>