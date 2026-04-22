<?php
require __DIR__ . '/config.php';
$db = config::getConnexion();
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
  echo "TABLE:$table`n";
  foreach ($db->query("SHOW COLUMNS FROM `$table`") as $col) {
    echo '  ' . $col['Field'] . ' | ' . $col['Type'] . "`n";
  }
}
