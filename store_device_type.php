<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['device_type'])) {
    $_SESSION['sell_device_type'] = $_POST['device_type'];
    $_SESSION['sell_redirect'] = true;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
