<?php
session_start();
include_once(__DIR__ . "/../func/bc-config.php");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <script>
        Swal.fire({
            title: 'Payment Received!',
            text: 'Your vendor wallet has been funded successfully.',
            icon: 'success',
            confirmButtonText: 'Back to Dashboard'
        }).then(() => {
            window.parent.location.href = 'Dashboard.php';
        });
    </script>
</body>
</html>
