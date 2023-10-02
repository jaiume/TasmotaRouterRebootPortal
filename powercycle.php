<?php
require_once 'config.php';
require_once 'db.php';
require_once 'mqtt.php'; // Assuming you have a mqtt.php for MQTT related functions

$device_id = $_GET['id'] ?? null;

if ($device_id) {
    $mysqli = db_connect();

    // Fetch the device to get the MQTT Device Name
    $stmt = $mysqli->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt->bind_param("i", $device_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if($device = $result->fetch_assoc()) {
            $mqtt_device_name = $device['mqtt_device_name'];
            
            // Publish MQTT message to power cycle the device
            $topic = "cmnd/{$mqtt_device_name}/event";
            $payload = "PowerCycle";
            mqtt_publish($topic, $payload); // Assuming you have a function mqtt_publish to publish MQTT messages
        }
    }
    $stmt->close();
    $mysqli->close();
}

// Redirect back to the device management page after performing the action
header('Location: index.php');
exit;
