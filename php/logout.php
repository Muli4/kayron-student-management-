<?php
session_start();
session_unset();
session_destroy();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login and prevent back navigation
echo "<script>
    window.location.href = '../index.php';
    window.history.replaceState(null, null, '../index.php');
</script>";
exit();
?>
