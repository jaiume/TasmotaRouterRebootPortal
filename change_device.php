<?php
require_once 'config.php';
require_once 'db.php';
require_once 'header.php';

$device_id = $_GET['id'] ?? null;
$friendly_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_id = $_POST['device_id'];
    $friendly_name = $_POST['friendly_name'];
    
    $mysqli = db_connect();
    $stmt = $mysqli->prepare("UPDATE devices SET friendly_name = ? WHERE id = ?");
    $stmt->bind_param("si", $friendly_name, $device_id);
    $stmt->execute();
    $stmt->close();
    $mysqli->close();

    header('Location: index.php');
    exit;
} else if ($device_id) {
    $mysqli = db_connect();
    $stmt = $mysqli->prepare("SELECT friendly_name FROM devices WHERE id = ?");
    $stmt->bind_param("i", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $friendly_name = $row['friendly_name'];
    }
    $stmt->close();
    $mysqli->close();
}
?>

<div class="content-container">
    <h2>Change Device Friendly Name</h2>
    <form method="POST" action="change_device.php">
        <input type="hidden" name="device_id" value="<?php echo $device_id; ?>">
        <label for="friendly_name">Friendly Name:</label>
        <input type="text" name="friendly_name" value="<?php echo $friendly_name; ?>" required>
        <button type="submit">Change</button>
    </form>
</div>
