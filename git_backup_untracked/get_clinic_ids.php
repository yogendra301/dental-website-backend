<?php
require 'application/config/database.php';

$mysqli = new mysqli($db['default']['hostname'], $db['default']['username'], $db['default']['password'], $db['default']['database']);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$result = $mysqli->query('SELECT id, username FROM clinics WHERE username LIKE "%003%"');
while ($row = $result->fetch_assoc()) {
    echo $row['id'] . ' - ' . $row['username'] . PHP_EOL;
}

$mysqli->close();
