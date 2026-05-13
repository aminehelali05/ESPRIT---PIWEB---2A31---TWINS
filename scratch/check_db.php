<?php
require_once 'config.php';
$pdo = config::getConnexion();

function describe($table) {
    global $pdo;
    echo "--- Table: $table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']} - {$row['Default']}\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

describe('marketplace_items');
describe('wallets');
describe('marketplace_orders');
describe('wallet_transactions');
