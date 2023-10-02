<?php
	require_once 'config.php';
	require_once 'mqtt.php';
	require_once 'db.php';
	require_once 'functions.php';
	
	$mqtt = mqtt_connect();
	
	// Subscribe to the topics where devices report their status
	$topics['stat/+/RESULT'] = array("qos" => 0, "function" => "procmsg");
	$topics['tele/+/LWT'] = array("qos" => 0, "function" => "proclwt"); // Subscribing to the new topic
	$topics['tele/+/STATE'] = array("qos" => 0, "function" => "procstate");
	$topics['stat/+/STATUS7'] = array("qos" => 0, "function" => "procstatus7");
	$mqtt->subscribe($topics, 0);
	
	while ($mqtt->proc()) {
		// The proc function will loop indefinitely, processing MQTT messages as they are received.
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
			insert_device_log($device_id, 'Device Online', 'The device has come online.');
			} elseif($msg === 'Offline') {
			// Handle when the device is Offline
			// Log this event
			insert_device_log($device_id, 'Device Offline', 'The device has gone offline.');
			}
			}
			}
			}
			
			$mqtt->close();
			?>
						