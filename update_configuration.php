<?php
require_once 'config.php';
require_once 'db.php';
require_once 'mqtt.php';
require_once 'functions.php';
require_once 'header.php';

$device_id = $_GET['id'] ?? null;
$device = $device_id ? fetch_device_by_id($device_id) : get_default_device_structure();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mysqli = db_connect();
    $device_id = $_POST['device_id'];
    
    // Update device configuration in the database
    update_device_configuration($mysqli, $_POST);
    
    // Publish new configuration to the device via MQTT
    publish_configuration_to_device($device_id, $_POST);
    
    $mysqli->close();

    header('Location: /');
    exit;
}

function get_default_device_structure() {
    global $DEFAULT_BOOT_UP_TIMER, $DEFAULT_ONGOING_TEST_TIMER, $DEFAULT_FAILURE_TEST_TIMER, $DEFAULT_FAILURE_THRESHOLD, $DEFAULT_POWER_OFF_DURATION, $DEFAULT_POWER_CYCLE_COUNT;
    
    return [
        'boot_up_timer' => $DEFAULT_BOOT_UP_TIMER,
        'ongoing_test_timer' => $DEFAULT_ONGOING_TEST_TIMER,
        'failure_test_timer' => $DEFAULT_FAILURE_TEST_TIMER,
        'failure_threshold' => $DEFAULT_FAILURE_THRESHOLD,
        'power_off_duration' => $DEFAULT_POWER_OFF_DURATION,
        'power_cycle_count' => $DEFAULT_POWER_CYCLE_COUNT
    ];
}

function fetch_device_by_id($device_id) {
    global $DEFAULT_BOOT_UP_TIMER, $DEFAULT_ONGOING_TEST_TIMER, $DEFAULT_FAILURE_TEST_TIMER, $DEFAULT_FAILURE_THRESHOLD, $DEFAULT_POWER_OFF_DURATION, $DEFAULT_POWER_CYCLE_COUNT;
    
    $mysqli = db_connect();
    $stmt = $mysqli->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt->bind_param("i", $device_id);
    
    if (!$stmt->execute()) {
        error_log("Failed to execute query to get device by ID: $device_id. Error: " . $stmt->error);
        $stmt->close();
        $mysqli->close();
        return get_default_device_structure();
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows == 0) {
        error_log("No device found with ID: $device_id");
        $mysqli->close();
        return get_default_device_structure();
    }
    
    $device = $result->fetch_assoc();
    $mysqli->close();

    // Assign default values if the values are not set or are 0.
    $device['boot_up_timer'] = $device['boot_up_timer'] ?: $DEFAULT_BOOT_UP_TIMER;
    $device['ongoing_test_timer'] = $device['ongoing_test_timer'] ?: $DEFAULT_ONGOING_TEST_TIMER;
    $device['failure_test_timer'] = $device['failure_test_timer'] ?: $DEFAULT_FAILURE_TEST_TIMER;
    $device['failure_threshold'] = $device['failure_threshold'] ?: $DEFAULT_FAILURE_THRESHOLD;
    $device['power_off_duration'] = $device['power_off_duration'] ?: $DEFAULT_POWER_OFF_DURATION;
    $device['power_cycle_count'] = $device['power_cycle_count'] ?: $DEFAULT_POWER_CYCLE_COUNT;

    return $device;
}

function update_device_configuration($mysqli, $postData) {
    $device_id = $postData['device_id'];
    
    // Convert power_off_duration to deciseconds before updating the database
    $power_off_duration_deciseconds = $postData['power_off_duration'] * 10;
    
    // Including is_initialized in the SQL query to update it to 1
    $stmt = $mysqli->prepare("UPDATE devices SET boot_up_timer = ?, ongoing_test_timer = ?, failure_test_timer = ?, failure_threshold = ?, power_off_duration = ?, power_cycle_count = ?, is_initialized = 1 WHERE id = ?");
    $stmt->bind_param("iiiiiii", $postData['boot_up_timer'], $postData['ongoing_test_timer'], $postData['failure_test_timer'], $postData['failure_threshold'], $power_off_duration_deciseconds, $postData['power_cycle_count'], $device_id);
    
    if (!$stmt->execute()) {
        error_log("Failed to update device configuration for device ID: $device_id. Error: " . $stmt->error);
    }
    $stmt->close();
}


function publish_configuration_to_device($device_id, $postData) {
    global $rule1_template, $rule2_template, $rule3_template;
    $device = fetch_device_by_id($device_id);
    $mqtt_device_name = $device['mqtt_device_name'];
    
    // Load the templates from the files
    $rule1_template_content = file_get_contents($rule1_template);
    $rule2_template_content = file_get_contents($rule2_template);
	$rule3_template_content = file_get_contents($rule3_template);
    
    // Replace the placeholders in the loaded rule1 template with the actual values from the form
    $rule1 = str_replace(
        ['{boot_up_timer}', '{ongoing_test_timer}', '{failure_test_timer}', '{failure_threshold}', '{power_off_duration}', '{power_cycle_count}'],
        [$postData['boot_up_timer'], $postData['ongoing_test_timer'], $postData['failure_test_timer'], $postData['failure_threshold'], $postData['power_off_duration']*10, $postData['power_cycle_count']],
        $rule1_template_content
    );
    
    // Replace the placeholder in the loaded rule2 template
    $rule2 = str_replace('{power_off_duration}', $postData['power_off_duration']*10, $rule2_template_content);
	
	$rule3 = $rule3_template_content;
    
    // Publish the rules via MQTT to the device
    mqtt_publish("cmnd/{$mqtt_device_name}/rule1", $rule1);
    mqtt_publish("cmnd/{$mqtt_device_name}/rule1", "1"); // Activates Rule 1
    mqtt_publish("cmnd/{$mqtt_device_name}/rule2", $rule2);
    mqtt_publish("cmnd/{$mqtt_device_name}/rule2", "1"); // Activates Rule 2
	mqtt_publish("cmnd/{$mqtt_device_name}/rule3", $rule3);
    mqtt_publish("cmnd/{$mqtt_device_name}/rule3", "1"); // Activates Rule 3
    mqtt_publish("cmnd/{$mqtt_device_name}/restart", "1"); // Restarts Tasmota on the device
	
	// Combine Rule1 and Rule2 text and Insert a log entry indicating that the rules were updated
    $rules_text = "Rule1: $rule1\nRule2: $rule2";
    insert_device_log($device_id, 'Rules Updated', "The device rules were updated. Updated Rules:\n$rules_text");
}



$rule1_template_content = file_get_contents($rule1_template); // assuming $rule1_template is the file name
$rule2_template_content = file_get_contents($rule2_template); // assuming $rule2_template is the file name
$rule3_template_content = file_get_contents($rule3_template); // assuming $rule2_template is the file name

?>

<div class="content-container">
    <h2>Update Device Configuration</h2>
    <p><strong>Friendly Name: </strong><?php echo htmlspecialchars($device['friendly_name']); ?></p>
    <p><strong>Tasmota Device Name: </strong><?php echo htmlspecialchars($device['mqtt_device_name']); ?></p>
    <form id="configForm" method="POST" action="update_configuration.php">
        <input type="hidden" name="device_id" value="<?php echo $device_id; ?>">
        
        <div class="form-group">
            <label for="boot_up_timer">Boot Up Timer:</label>
            <input type="number" name="boot_up_timer" value="<?php echo $device['boot_up_timer']; ?>" required>
        </div>

        <div class="form-group">
            <label for="ongoing_test_timer">Ongoing Test Timer:</label>
            <input type="number" name="ongoing_test_timer" value="<?php echo $device['ongoing_test_timer']; ?>" required>
        </div>

        <div class="form-group">
            <label for="failure_test_timer">Failure Test Timer:</label>
            <input type="number" name="failure_test_timer" value="<?php echo $device['failure_test_timer']; ?>" required>
        </div>

        <div class="form-group">
            <label for="failure_threshold">Failure Threshold:</label>
            <input type="number" name="failure_threshold" value="<?php echo $device['failure_threshold']; ?>" required>
        </div>

        <div class="form-group">
            <label for="power_off_duration">Power Off Duration:</label>
            <input type="number" name="power_off_duration" value="<?php echo $device['power_off_duration']/10; ?>" required>
        </div>

        <div class="form-group">
            <label for="power_cycle_count">Power Cycle Count:</label>
            <input type="number" name="power_cycle_count" value="<?php echo $device['power_cycle_count']; ?>" required>
        </div>

        <button type="submit">Update Configuration<br> (Will Push New Rules to Device and Reboot Tasmota)</button>
    </form>
    <div id="rulePreview">
        <h3>Rule Preview</h3>
		Rule 1 <br>
        <pre id="rule1Preview"></pre>
		Rule 2 <br>
        <pre id="rule2Preview"></pre>
		Rule 3 <br>
		<pre id="rule3Preview"></pre>
    </div>
</div>

<style>
    .form-group {
        margin-bottom: 1em;
    }
</style>

<script>
    var rule1Template = <?php echo json_encode($rule1_template_content); ?>;
    var rule2Template = <?php echo json_encode($rule2_template_content); ?>;
	var rule3 = <?php echo json_encode($rule3_template_content); ?>;
    
    function updateRulePreview() {
    var form = document.getElementById('configForm');
    
    var boot_up_timer = form.elements['boot_up_timer'].value;
    var ongoing_test_timer = form.elements['ongoing_test_timer'].value;
    var failure_test_timer = form.elements['failure_test_timer'].value;
    var failure_threshold = form.elements['failure_threshold'].value;
    var power_off_duration = form.elements['power_off_duration'].value * 10;
    var power_cycle_count = form.elements['power_cycle_count'].value;
    
    var rule1 = rule1Template
        .split('{boot_up_timer}').join(boot_up_timer)
        .split('{ongoing_test_timer}').join(ongoing_test_timer)
        .split('{failure_test_timer}').join(failure_test_timer)
        .split('{failure_threshold}').join(failure_threshold)
        .split('{power_off_duration}').join(power_off_duration)
        .split('{power_cycle_count}').join(power_cycle_count);
    
    var rule2 = rule2Template.split('{power_off_duration}').join(power_off_duration);
    
    document.getElementById('rule1Preview').textContent = rule1;
    document.getElementById('rule2Preview').textContent = rule2;
	document.getElementById('rule3Preview').textContent = rule3;
	
}

    
    document.getElementById('configForm').addEventListener('input', updateRulePreview);
    
    // Initialize rule preview on page load
    document.addEventListener('DOMContentLoaded', updateRulePreview);
</script>


