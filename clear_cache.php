<?php
include_once(__DIR__ . '/config.php');
$pdo = config::getConnexion();
$pdo->exec('TRUNCATE TABLE ai_results');
echo "AI Cache cleared successfully.\n";
