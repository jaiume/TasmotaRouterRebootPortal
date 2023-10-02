<?php
require_once 'db.php';

function insert_new_device($mqtt_device_name) {
    $mysqli = db_connect();
    
    // Include friendly_name in the SQL query and bind the $mqtt_device_name to it
    $stmt = $mysqli->prepare("INSERT INTO devices (mqtt_device_name, friendly_name) VALUES (?, ?)");
    $stmt->bind_param("ss", $mqtt_device_name, $mqtt_device_name); // Bind $mqtt_device_name to friendly_name as well
    
    if (!$stmt->execute()) {
        error_log("Failed to insert new device: $mqtt_device_name. Error: " . $stmt->error);
    }
    
    $stmt->close();
}


function insert_device_log($device_id, $event_type, $details) {
    $mysqli = db_connect();
    
    $stmt = $mysqli->prepare("INSERT INTO device_logs (device_id, event_type, details) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $device_id, $event_type, $details);
    
    if (!$stmt->execute()) {
        error_log("Failed to insert log for device ID: $device_id, Event Type: $event_type. Error: " . $stmt->error);
    }
    
    $stmt->close();
}




function get_device_id_by_mqtt_name($mqtt_device_name) {
    $mysqli = db_connect();
    
    $device_id = null;
    $stmt = $mysqli->prepare("SELECT id FROM devices WHERE mqtt_device_name = ?");
    $stmt->bind_param("s", $mqtt_device_name);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $device_id = $row['id'];
        } else {
            error_log("Failed to find device ID for mqtt_device_name: $mqtt_device_name");
        }
    } else {
        error_log("Failed to execute query to get device ID for mqtt_device_name: $mqtt_device_name. Error: " . $stmt->error);
    }
    
    $stmt->close();
    return $device_id;
}

function insert_device_log_using_mqtt_name($mqtt_device_name, $event_type, $details) {
    $device_id = get_device_id_by_mqtt_name($mqtt_device_name);
    if ($device_id !== null) {
        insert_device_log($device_id, $event_type, $details);
    }
}


// New function to update the last_seen timestamp
function update_last_seen($device_id) {
    $mysqli = db_connect();
    
    $stmt = $mysqli->prepare("UPDATE devices SET last_seen = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("i", $device_id);
    
    if (!$stmt->execute()) {
        error_log("Failed to update last_seen for device ID: $device_id. Error: " . $stmt->error);
    }
    
    $stmt->close();
}

