<?php
define('APP_ACCESS', true);
require_once 'includes/config.php';

$sql = "SHOW COLUMNS FROM prehospital_forms WHERE Field IN ('form_date', 'departure_time', 'arrival_time', 'date_of_birth', 'ob_lmp', 'ob_edc', 'ob_delivery_time', 'endorsement_datetime', 'incident_time', 'call_arrival_time', 'initial_time', 'followup_time', 'arrival_scene_time', 'departure_scene_time', 'arrival_hospital_time', 'departure_hospital_time', 'arrival_station_time')";
$stmt = $pdo->query($sql);

echo "DATE AND TIME COLUMNS SCHEMA:\n";
echo str_repeat("=", 100) . "\n";
printf("%-30s | %-20s | %-8s | %-15s\n", "Field", "Type", "Null?", "Default");
echo str_repeat("=", 100) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("%-30s | %-20s | %-8s | %-15s\n",
        $row['Field'],
        $row['Type'],
        $row['Null'],
        $row['Default'] ?? 'NULL'
    );
}
