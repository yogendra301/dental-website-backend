<?php
// CLI script to add database indexes safely
mysqli_report(MYSQLI_REPORT_STRICT);
$host = 'localhost';
$user = 'yogi';
$pass = 'Yogen@1234';
$db   = 'dental_clinic';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    die("Connect failed: " . $mysqli->connect_error . "\n");
}

$indexes = [
    "ALTER TABLE clinics ADD INDEX idx_username (username)",
    "ALTER TABLE clinics ADD INDEX idx_custom_domain (custom_domain)",
    "ALTER TABLE appointments ADD INDEX idx_clinic_date (clinic_id, date)",
    "ALTER TABLE appointments ADD INDEX idx_clinic_phone_apt (clinic_id, patient_phone)",
    "ALTER TABLE appointments ADD INDEX idx_clinic_date_status (clinic_id, date, status)",
    "ALTER TABLE appointments ADD INDEX idx_clinic_slot (clinic_id, date, time_slot)",
    "ALTER TABLE slots_blocked ADD INDEX idx_clinic_date_slot (clinic_id, date, time_slot)",
    "ALTER TABLE gallery ADD INDEX idx_clinic_id (clinic_id)",
    "ALTER TABLE leads ADD INDEX idx_clinic_id_leads (clinic_id)",
];

foreach ($indexes as $sql) {
    try {
        if ($mysqli->query($sql) === TRUE) {
            echo "OK: " . preg_replace('/ADD INDEX .+? \(/', 'ADD INDEX ', $sql) . "\n";
        } else {
            if (strpos($mysqli->error, 'Duplicate key name') !== false) {
                echo "SKIP: index already exists\n";
            } else {
                echo "ERR: " . $mysqli->error . "\n";
            }
        }
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "SKIP: index already exists\n";
        } else {
            echo "ERR: " . $e->getMessage() . "\n";
        }
    }
}

$mysqli->close();
echo "Done.\n";