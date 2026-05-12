<?php
require_once 'config.php';
$pdo = config::getConnexion();
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    $stmt2 = $pdo->query("DESCRIBE $table");
    while ($row = $stmt2->fetch()) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
    echo "\n";
}
