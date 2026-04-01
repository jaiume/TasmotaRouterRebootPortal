<?php
	require_once 'config.php';
	require_once 'mqtt.php';
	require_once 'db.php';
	require_once 'functions.php';
	
	$mqtt = mqtt_connect();
	
	// Subscribe to the topics where devices report their status
	$topics['stat/+/RESULT'] = array("qos" => 0, "function" => "procmsg");
	$topics['tele/+/LWT'] = array("qos" => 0, "function" => "proclwt");
	$topics['tele/+/STATE'] = array("qos" => 0, "function" => "procstate");
	$topics['stat/+/STATUS5'] = array("qos" => 0, "function" => "procstatus5");
	$topics['stat/+/STATUS7'] = array("qos" => 0, "function" => "procstatus7");
	$mqtt->subscribe($topics, 0);
	
	$nextCheckTime = time() + ($heartbeat*2);
	
	while ($mqtt->proc()) {
		// Check the device status every 30 seconds
		if (time() >= $nextCheckTime) { 
			checkDevicesStatus($allowance);
			$nextCheckTime = time() + ($heartbeat*2);
		}
	}
	
	function checkDevicesStatus($allowance) {
		global $timezone;
		
		date_default_timezone_set($timezone);
		$currentTimestamp = new DateTime();
		
		$mysqli = db_connect();
		$query = "SELECT id, mqtt_device_name, last_seen, last_state, IF(TIMESTAMPDIFF(SECOND, last_seen, CURRENT_TIMESTAMP) <= ongoing_test_timer + ?, 'online', 'offline') AS current_status FROM devices";
		$stmt = $mysqli->prepare($query);
		$stmt->bind_param("i", $allowance);
		
		if ($stmt->execute()) {
			$result = $stmt->get_result();
			while ($row = $result->fetch_assoc()) {
				$previousStatus = $row['last_state'];
				$currentStatus = $row['current_status'];
				//echo "Current Status =" . $currentStatus . " | Laste State =" . $previousStatus . "\n";
				
				if ($previousStatus !== $currentStatus) {
					// Update the last_state in the database
					$updateStmt = $mysqli->prepare("UPDATE devices SET last_state = ? WHERE id = ?");
					$updateStmt->bind_param("si", $currentStatus, $row['id']);
					$updateStmt->execute();
					$updateStmt->close();
					
					// Log the status change
					$logMessage = ($currentStatus === 'online') ? 'The device has come online based on received heart beat messages.' : 'The device has gone offline based on received heart beat messages.';
					insert_device_log($row['id'], '(Heart Beat)Device ' . ucfirst($currentStatus), $logMessage);
				}
			}
			$result->close();
		}
		
		$stmt->close();
		$mysqli->close();
	}
	
	
	
	function procstate($topic, $msg) {
		// Extract device name from the topic
		preg_match('/tele\/(.+?)\/STATE/', $topic, $matches);
		$mqtt_device_name = $matches[1] ?? null;
		
		if ($mqtt_device_name) {
			$device_id = get_device_id_by_mqtt_name($mqtt_device_name);
			
			if ($device_id === null) {
				// This is a new device, insert it into the database
				insert_new_device($mqtt_device_name);
				insert_device_log_using_mqtt_name($mqtt_device_name, 'New device discovered', 'A new device was discovered with MQTT name: ' . $mqtt_device_name);
				$device_id = get_device_id_by_mqtt_name($mqtt_device_name);
			}
			
			if ($device_id !== null) {
				$decoded = json_decode($msg, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					$ip = $decoded['IPAddress'] ?? null;
					if ($ip !== null && $ip !== '') {
						update_device_lan_ip($device_id, $ip);
					}
				}
			}
		}
	}
	
	
	function procstatus5($topic, $msg) {
		preg_match('/stat\/(.+?)\/STATUS5/', $topic, $matches);
		$mqtt_device_name = $matches[1] ?? null;
		
		if ($mqtt_device_name) {
			$device_id = get_device_id_by_mqtt_name($mqtt_device_name);
			
			if ($device_id !== null) {
				update_last_seen($device_id);
				$decoded = json_decode($msg, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
					$ip = $decoded['StatusNET']['IPAddress'] ?? null;
					if ($ip !== null && $ip !== '') {
						update_device_lan_ip($device_id, $ip);
					}
				}
			}
		}
	}
	
	
	function procstatus7($topic, $msg) {
        // Extract device name from the topic
        preg_match('/stat\/(.+?)\/STATUS7/', $topic, $matches);
        $mqtt_device_name = $matches[1] ?? null;
        
        if ($mqtt_device_name) {
            $device_id = get_device_id_by_mqtt_name($mqtt_device_name);
            
            if ($device_id !== null) {
                // Update the last_seen timestamp
                update_last_seen($device_id);
			}
		}
	}
	
	function procmsg($topic, $msg) {		
		// Extract device name from the topic
		preg_match('/stat\/(.+?)\/RESULT/', $topic, $matches);
		$mqtt_device_name = $matches[1] ?? null;
		
		if ($mqtt_device_name) {
			$device_id = get_device_id_by_mqtt_name($mqtt_device_name);
			
			if ($device_id === null) {
				// This is a new device, insert it into the database
				insert_new_device($mqtt_device_name);
				insert_device_log_using_mqtt_name($mqtt_device_name, 'New device discovered', 'A new device was discovered with MQTT name: ' . $mqtt_device_name);
				$device_id = get_device_id_by_mqtt_name($mqtt_device_name);
			}			
			
			if ($device_id !== null) {
				$decoded_msg = json_decode($msg, true);
				
				if (isset($decoded_msg['Restart']) && $decoded_msg['Restart'] === 'Restarting') {
					// Insert a log entry indicating that the device is restarting
					insert_device_log($device_id, 'Device Restarting', 'The device is restarting.');
				}
			}
		}
	}
	
	function proclwt($topic, $msg) {
		// Extract device name from the topic
		preg_match('/tele\/(.+?)\/LWT/', $topic, $matches);
		$mqtt_device_name = $matches[1] ?? null;
		
		if($mqtt_device_name) {
			$device_id = get_device_id_by_mqtt_name($mqtt_device_name);
			
			if ($device_id === null) {
				// This is a new device, insert it into the database
				insert_new_device($mqtt_device_name);
				insert_device_log_using_mqtt_name($mqtt_device_name, 'New device discovered', 'A new device was discovered with MQTT name: ' . $mqtt_device_name);
				$device_id = get_device_id_by_mqtt_name($mqtt_device_name);
			}
			
			
			if($device_id !== null) {
				// Process LWT message
				if($msg === 'Online') {
					// Handle when the device is Online
					// Log this event
					insert_device_log($device_id, '(MQTT) Device Online', 'The device has come online based on the MQTT Server Status.');
					} elseif($msg === 'Offline') {
					// Handle when the device is Offline
					// Log this event
					insert_device_log($device_id, '(MQTT) Device Offline', 'The device has gone offline based on the MQTT Server Status.');
				}
			}
		}
	}
	
	$mqtt->close();
?>
