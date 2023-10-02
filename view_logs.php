<?php
require_once 'config.php';
require_once 'db.php';
require_once 'header.php';

$device_id = $_GET['id'] ?? null;

if (!$device_id) {
    die('No device ID provided');
}

$mysqli = db_connect();

// Check if the form to clear logs has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['clear_logs'])) {
        $stmt = $mysqli->prepare("DELETE FROM device_logs WHERE device_id = ?");
        $stmt->bind_param("i", $device_id);
        $stmt->execute();
        $stmt->close();
        // Redirect back to the same page to refresh the logs view
        header("Location: view_logs.php?id=$device_id");
        exit;
    }
}

// Fetch the friendly name of the device.
$stmt = $mysqli->prepare("SELECT friendly_name FROM devices WHERE id = ?");
$stmt->bind_param("i", $device_id);
$stmt->execute();
$stmt->bind_result($friendly_name);
$stmt->fetch();
$stmt->close();

if (!$friendly_name) {
    $mysqli->close();
    die('Invalid device ID provided');
}

// Fetch the logs for the device.
$stmt = $mysqli->prepare("SELECT event_timestamp, event_type, details FROM device_logs WHERE device_id = ? ORDER BY event_timestamp DESC");
$stmt->bind_param("i", $device_id);
$stmt->execute();
$result = $stmt->get_result();

$logs = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$mysqli->close();
?>

<div class="content-container">
    <h2>Logs for <?php echo htmlspecialchars($friendly_name); ?></h2>
    
    <!-- Add the Clear Logs Button -->
    <div class="clear-logs-container">
        <form method="POST" onsubmit="return confirm('Are you sure you want to clear logs for this device?');">
            <button type="submit" name="clear_logs">Clear Logs</button>
        </form>
    </div>
    
    <table>
        <thead>
        <tr>
            <th>Timestamp</th>
            <th>Event Type</th>
            <th>Details</th>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach ($logs as $log) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($log['event_timestamp']) . "</td>";
            echo "<td>" . htmlspecialchars($log['event_type']) . "</td>";
            echo "<td>" . htmlspecialchars($log['details']) . "</td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
</div>
