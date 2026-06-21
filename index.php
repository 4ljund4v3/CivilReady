<?php

require_once 'includes/update_activity.php';
// Send the HTTP redirect header
header("Location: dashboard.php");

// Always call exit to stop script execution immediately
exit();
?>
