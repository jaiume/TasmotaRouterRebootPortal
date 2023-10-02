<?php
require_once 'config.php';

function db_connect() {
    global $servername, $username, $password, $dbname;
    
    $mysqli = new mysqli($servername, $username, $password, $dbname);
    
    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }
    
    return $mysqli;
}
?>
