<?php
// Database configuration
$servername = "localhost";
$username = "newburyhill_powercycle";
$password = "Password123#";
$dbname = "newburyhill_powercycle";

// MQTT configuration
$mqtt_server = "mqtt.newburyhill.com";
$mqtt_port = 1883;
$mqtt_client_id = "powercycle.dashboard";
$mqtt_username = "newburyhill";
$mqtt_password = "glencoe";

// Base URL of your project
$base_url = "https://powercycle.newburyhill.com";

// Admin credentials
$admin_username = 'admin';
$admin_password = 'Password123#';

// Logo filename
$logo_filename = "NHPLogo.jpg";

// Timezone
$timezone = 'America/Port_of_Spain';

// Service configuration
$service_name = "powercycle_mqtt_listener";
$service_definition_template = "service_template.txt";
$service_definition_file = "powercycle_mqtt_listener.service";

$allowance = 2;

// Default values for devices table
$DEFAULT_BOOT_UP_TIMER = 240;
$DEFAULT_ONGOING_TEST_TIMER = 30;
$DEFAULT_FAILURE_TEST_TIMER = 60;
$DEFAULT_FAILURE_THRESHOLD = 4;
$DEFAULT_POWER_OFF_DURATION = 200; // Specified in 10ths of a second
$DEFAULT_POWER_CYCLE_COUNT = 4;

//Tasmota Rule Templates
$rule1_template="rule1_template.txt";
$rule2_template="rule2_template.txt";
$rule3_template="rule3_template.txt";


?>
