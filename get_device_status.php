<?php
// Simplified example, you'll need to adapt it to your actual database fetching logic.
header('Content-Type: application/json');
require_once 'db.php';

$mysqli = db_connect();
$result = $mysqli->query("SELECT id, last_seen, ongoing_test_timer FROM devices");

$data = [];
while ($row = $result->fetch_assoc()) {
    $lastSeen = strtotime($row['last_seen']);
    $currentTime = time();
    $expectedContact = ($lastSeen + $row['ongoing_test_timer']+ $allowance) - $currentTime;
    $data[$row['id']] = $expectedContact;
}

echo json_encode($data);
?>

