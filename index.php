<?php
	require_once 'config.php';
	require_once 'db.php';
	require_once 'mqtt.php';
	require_once 'header.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="120"> <!-- Refresh the page every 2 minutes -->
    <title>Device Management</title>
</head>
<body>
<!-- Render Table with Placeholders -->
<div class="content-container">
    <h2>Device Management</h2>
    <table>
        <thead>
            <tr>
                <th>Friendly Name</th>
                <th>Connectivity Status</th>
                <th>Actions</th>
			</tr>
		</thead>
        <tbody>
            <?php
				$mysqli = db_connect();
				$result = $mysqli->query("SELECT * FROM devices");
				
				while ($row = $result->fetch_assoc()) {
					echo "<tr id='device-row-{$row['id']}'>";
					echo "<td>" . htmlspecialchars($row['friendly_name']) . "</td>";
					if ($row['is_initialized'] == 0) {
						echo "<td>Not Initialized</td>";
						echo "<td>";
						echo "<a href='update_configuration.php?id={$row['id']}'>Update Configuration</a> | ";
						echo "<a href='#' onclick='confirmDelete({$row['id']})'>Delete</a>"; // Delete Action
						echo "</td>";
						} else {
						echo "<td><span id='status-{$row['id']}'>Checking...</span> <span id='details-{$row['id']}'></span></td>";
						echo "<td>";
						echo "<a href='powercycle.php?id={$row['id']}'>Power Cycle</a> | ";
						echo "<a href='update_configuration.php?id={$row['id']}'>Update Configuration</a> | ";
						echo "<a href='change_device.php?id={$row['id']}'>Change Device</a> | ";
						echo "<a href='view_logs.php?id={$row['id']}'>View Logs</a> | "; // View Logs Action
						echo "<a href='#' onclick='confirmDelete({$row['id']})'>Delete</a>"; // Delete Action
						echo "</td>";
					}
					echo "</tr>";
				}
				$mysqli->close();
			?>
		</tbody>
	</table>
</div>

<!-- JavaScript to Update Status -->
<script>
    var countdowns = {};
	
	function updateDeviceStatus() {
		fetch('get_device_status.php')
        .then(response => response.json())
        .then(data => {
            for (let id in data) {
                let statusElem = document.getElementById('status-' + id);
                let detailsElem = document.getElementById('details-' + id);
				
                countdowns[id] = data[id];
				
                if (countdowns[id] >= 0) {
                    statusElem.textContent = 'Online';
                    detailsElem.textContent = ''; // Clear details when online
					} else {
                    statusElem.textContent = 'Offline';
                    let absoluteValue = Math.abs(countdowns[id]);
                    let timeText = absoluteValue > 60
					? `${Math.floor(absoluteValue / 60)}m ${absoluteValue % 60}s`
					: `${absoluteValue}s`;
                    detailsElem.textContent = `(${'Offline for'} : ${timeText})`; // Add brackets and details when offline
				}
			}
		})
        .catch(error => console.error('Error fetching device status:', error));
	}
	
	function confirmDelete(deviceId) {
    var userConfirmed = confirm("Are you sure you want to delete this device?\n\n" +
                            "Deleting will remove device and logs.\n" +
                            "Also Note: device will be immediately rediscovered if it is still sending MQTT messages.\n\n" +
                            "This action cannot be undone.");
    if (userConfirmed) {
        window.location.href = 'delete_device.php?id=' + deviceId;
    }
}
	
	
	// Remove the updateCountdowns function and its setInterval if you only want to update when calling get_device_status.php
	
	// Update device status every 10 seconds
	setInterval(updateDeviceStatus, 10000);
	
	// Initial status update
	updateDeviceStatus();
	
</script>
</body>
</html>



