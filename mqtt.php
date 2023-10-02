<?php
require_once 'config.php';	
require 'vendor/autoload.php'; // If you are using Composer, otherwise include the library manually.

use Bluerhinos\phpMQTT;

function mqtt_connect() {
    global $mqtt_server, $mqtt_port, $mqtt_client_id, $mqtt_username, $mqtt_password;
    
    $mqtt = new Bluerhinos\phpMQTT($mqtt_server, $mqtt_port, $mqtt_client_id);
    
    if (!$mqtt->connect(true, null, $mqtt_username, $mqtt_password)) {
        die("Failed to connect to MQTT server: $mqtt_server");
    }
    
    return $mqtt;
}

function mqtt_publish($topic, $message) {
    $mqtt = mqtt_connect();
    $mqtt->publish($topic, $message, 0);
    $mqtt->close();
}

function mqtt_subscribe($topics, $callback) {
    $mqtt = mqtt_connect();
    $mqtt->subscribe($topics, $callback);
    
    while ($mqtt->proc()) {
    }
    
    $mqtt->close();
}
?>
