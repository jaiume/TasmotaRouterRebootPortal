<?php
require_once 'config.php';
require_once 'db.php';

$device_id = $_GET['id'] ?? null;

if (!$device_id) {
    header('Location: index.php?error=No device ID provided');
    exit;
}

$mysqli = db_connect();

// Validate the device_id.
$stmt = $mysqli->prepare("SELECT 1 FROM devices WHERE id = ?");
$stmt->bind_param("i", $device_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    $mysqli->close();
    header('Location: index.php?error=Invalid device ID provided');
    exit;
}

$stmt->close();

// Delete logs associated with the device.
$stmt = $mysqli->prepare("DELETE FROM device_logs WHERE device_id = ?");
$stmt->bind_param("i", $device_id);
$stmt->execute();
$stmt->close();

// Delete the device.
$stmt = $mysqli->prepare("DELETE FROM devices WHERE id = ?");
$stmt->bind_param("i", $device_id);
$stmt->execute();
$stmt->close();

$mysqli->close();

// Redirect to the index page with a success message.
header('Location: index.php?success=Device deleted successfully');
exit;
