<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserController.php';

class WalletController {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? config::getConnexion();
    }

    /**
     * Get or create user wallet
     */
    public function getWallet(int $userId): ?array {
        if ($userId <= 0) return null;
        
        $q = $this->db->prepare('SELECT * FROM wallets WHERE user_id = :uid');
        $q->execute(['uid' => $userId]);
        $wallet = $q->fetch(PDO::FETCH_ASSOC);
        
        if (!$wallet) {
            try {
                $ins = $this->db->prepare('INSERT INTO wallets (user_id, balance) VALUES (:uid, 0.00)');
                $ins->execute(['uid' => $userId]);
                return $this->getWallet($userId);
            } catch (Exception $e) {
                return null;
            }
        }
        
        return $wallet;
    }

    public function getBalance(int $userId): float {
        $wallet = $this->getWallet($userId);
        return (float) ($wallet['balance'] ?? 0.00);
    }

    /**
     * Fetch transaction history with pagination support
     */
    public function getTransactions(int $userId, int $limit = 50, int $offset = 0): array {
        $wallet = $this->getWallet($userId);
        if (!$wallet) return [];
        
        $q = $this->db->prepare("
            SELECT * FROM wallet_transactions 
            WHERE wallet_id = :wid 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        $q->bindValue(':wid', (int)$wallet['id'], PDO::PARAM_INT);
        $q->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $q->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $q->execute();
        
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Main entry point for ANY financial action.
     * Ensures atomicity between balance update and transaction logging.
     */
    public function addTransaction(
        int $userId, 
        string $type, 
        float $amount, 
        string $description = '', 
        string $status = 'completed', 
        ?string $sourceModule = null, 
        ?int $sourceId = null, 
        ?array $metadata = null
    ): bool {
        if ($userId <= 0 || $amount == 0) return false;

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }
            
            // Lock wallet row for update to prevent race conditions
            $q = $this->db->prepare('SELECT id, balance FROM wallets WHERE user_id = :uid FOR UPDATE');
            $q->execute(['uid' => $userId]);
            $wallet = $q->fetch(PDO::FETCH_ASSOC);
            
            if (!$wallet) {
                // Try to create it if missing
                $this->getWallet($userId);
                $q->execute(['uid' => $userId]);
                $wallet = $q->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$wallet) throw new Exception("Wallet could not be initialized.");
            
            // Validate sufficient balance for negative amounts (expenses)
            $currentBalance = (float)$wallet['balance'];
            $newBalance = $currentBalance + $amount;
            
            if ($newBalance < 0 && !in_array($type, ['deposit', 'transfer'])) {
                throw new Exception("Insufficient funds.");
            }
            
            // 1. Update Balance
            $upd = $this->db->prepare('UPDATE wallets SET balance = :bal, updated_at = NOW() WHERE id = :wid');
            $upd->execute(['bal' => $newBalance, 'wid' => $wallet['id']]);
            
            // 2. Log Transaction
            $ins = $this->db->prepare('
                INSERT INTO wallet_transactions (wallet_id, type, amount, status, description, source_module, source_id, metadata)
                VALUES (:wid, :type, :amount, :status, :desc, :mod, :sid, :meta)
            ');
            $ins->execute([
                'wid'    => $wallet['id'],
                'type'   => $type,
                'amount' => $amount,
                'status' => $status,
                'desc'   => $description,
                'mod'    => $sourceModule,
                'sid'    => $sourceId,
                'meta'   => $metadata ? json_encode($metadata) : null
            ]);

            // 3. Optional: Event Log for Audit
            $log = $this->db->prepare('INSERT INTO wallet_event_logs (wallet_id, event_type, message) VALUES (:wid, :etype, :msg)');
            $log->execute([
                'wid'   => $wallet['id'],
                'etype' => 'balance_update',
                'msg'   => "Balance changed from {$currentBalance} to {$newBalance} via {$type}"
            ]);
            
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Wallet Transaction Failed: " . $e->getMessage());
            return false;
        }
    }

    public function getPaymentMethods(int $userId): array {
        $q = $this->db->prepare('SELECT * FROM user_payment_methods WHERE user_id = :uid');
        $q->execute(['uid' => $userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getQuickStats(int $userId): array {
        $wallet = $this->getWallet($userId);
        if (!$wallet) return ['total_income' => 0, 'total_expense' => 0, 'pending' => 0];

        $wid = (int)$wallet['id'];
        
        // Total Income
        $q1 = $this->db->prepare("SELECT SUM(amount) as total FROM wallet_transactions WHERE wallet_id = :wid AND amount > 0 AND status = 'completed'");
        $q1->execute(['wid' => $wid]);
        $income = (float)($q1->fetch()['total'] ?? 0);

        // Total Expense
        $q2 = $this->db->prepare("SELECT SUM(ABS(amount)) as total FROM wallet_transactions WHERE wallet_id = :wid AND amount < 0 AND status = 'completed'");
        $q2->execute(['wid' => $wid]);
        $expense = (float)($q2->fetch()['total'] ?? 0);

        // Pending
        $q3 = $this->db->prepare("SELECT SUM(ABS(amount)) as total FROM wallet_transactions WHERE wallet_id = :wid AND status = 'pending'");
        $q3->execute(['wid' => $wid]);
        $pending = (float)($q3->fetch()['total'] ?? 0);

        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'pending' => $pending,
            'balance' => (float)$wallet['balance']
        ];
    }

    /**
     * Balance over time - last 6 months (for line chart)
     */
    public function getBalanceOverTime(int $userId): array {
        $wallet = $this->getWallet($userId);
        if (!$wallet) return [];

        $wid = (int)$wallet['id'];

        $q = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN amount > 0 AND status = 'completed' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 AND status = 'completed' THEN ABS(amount) ELSE 0 END) as expense,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as net
            FROM wallet_transactions 
            WHERE wallet_id = :wid 
              AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $q->execute(['wid' => $wid]);
        
        $data = $q->fetchAll(PDO::FETCH_ASSOC);
        
        // Build running balance
        $currentBalance = (float)$wallet['balance'];
        $totalNet = 0;
        foreach ($data as $row) {
            $totalNet += (float)$row['net'];
        }
        
        // Calculate starting balance
        $startingBalance = $currentBalance - $totalNet;
        $runningBalance = $startingBalance;
        
        $result = [];
        foreach ($data as $row) {
            $runningBalance += (float)$row['net'];
            $result[] = [
                'month' => $row['month'],
                'balance' => round($runningBalance, 2),
                'income' => round((float)$row['income'], 2),
                'expense' => round((float)$row['expense'], 2),
            ];
        }
        
        return $result;
    }

    /**
     * Earnings by source module (for pie/doughnut chart)
     */
    public function getEarningsSources(int $userId): array {
        $wallet = $this->getWallet($userId);
        if (!$wallet) return [];

        $wid = (int)$wallet['id'];

        $q = $this->db->prepare("
            SELECT 
                COALESCE(source_module, 'other') as source,
                SUM(amount) as total
            FROM wallet_transactions 
            WHERE wallet_id = :wid 
              AND amount > 0 
              AND status = 'completed'
            GROUP BY COALESCE(source_module, 'other')
            ORDER BY total DESC
        ");
        $q->execute(['wid' => $wid]);
        
        $result = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $label = match($row['source']) {
                'marketplace' => 'Marketplace',
                'payment_gateway' => 'Deposits',
                'community' => 'Donations',
                'bank_transfer' => 'Bank Transfer',
                'internal' => 'Transfers',
                default => ucfirst($row['source']),
            };
            $result[] = [
                'source' => $label,
                'amount' => round((float)$row['total'], 2),
            ];
        }
        
        return $result;
    }

    /**
     * Monthly income vs spending (for bar chart)
     */
    public function getMonthlyIncomeVsSpending(int $userId): array {
        $wallet = $this->getWallet($userId);
        if (!$wallet) return [];

        $wid = (int)$wallet['id'];

        $q = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN amount > 0 AND status = 'completed' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 AND status = 'completed' THEN ABS(amount) ELSE 0 END) as spending
            FROM wallet_transactions 
            WHERE wallet_id = :wid 
              AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ");
        $q->execute(['wid' => $wid]);
        
        $result = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[] = [
                'month' => $row['month'],
                'income' => round((float)$row['income'], 2),
                'spending' => round((float)$row['spending'], 2),
            ];
        }
        
        return $result;
    }

    /**
     * Send money to another user (Quick Send)
     */
    public function sendMoney(int $senderId, int $receiverId, float $amount, string $note = ''): array {
        if ($senderId <= 0 || $receiverId <= 0 || $amount <= 0) {
            return ['success' => false, 'message' => 'Invalid parameters.'];
        }
        if ($senderId === $receiverId) {
            return ['success' => false, 'message' => 'Cannot send money to yourself.'];
        }

        // Check sender balance
        $senderBalance = $this->getBalance($senderId);
        if ($senderBalance < $amount) {
            return ['success' => false, 'message' => 'Insufficient funds. Your balance is $' . number_format($senderBalance, 2)];
        }

        try {
            $this->db->beginTransaction();

            // Get receiver info for description
            $q = $this->db->prepare('SELECT first_name, last_name FROM users WHERE id = :id');
            $q->execute(['id' => $receiverId]);
            $receiver = $q->fetch(PDO::FETCH_ASSOC);
            if (!$receiver) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Recipient not found.'];
            }

            $receiverName = trim($receiver['first_name'] . ' ' . $receiver['last_name']);
            $desc = $note ? "Transfer to {$receiverName}: {$note}" : "Transfer to {$receiverName}";

            // Deduct from sender
            $senderWallet = $this->getWallet($senderId);
            $newSenderBalance = (float)$senderWallet['balance'] - $amount;
            
            $upd = $this->db->prepare('UPDATE wallets SET balance = :bal, updated_at = NOW() WHERE id = :wid');
            $upd->execute(['bal' => $newSenderBalance, 'wid' => $senderWallet['id']]);

            $ins = $this->db->prepare('INSERT INTO wallet_transactions (wallet_id, type, amount, status, description, source_module, source_id) VALUES (:wid, :type, :amount, :status, :desc, :mod, :sid)');
            $ins->execute([
                'wid' => $senderWallet['id'],
                'type' => 'transfer',
                'amount' => -$amount,
                'status' => 'completed',
                'desc' => $desc,
                'mod' => 'internal',
                'sid' => $receiverId
            ]);

            // Add to receiver
            $receiverWallet = $this->getWallet($receiverId);
            $newReceiverBalance = (float)$receiverWallet['balance'] + $amount;
            
            $upd->execute(['bal' => $newReceiverBalance, 'wid' => $receiverWallet['id']]);

            // Get sender name
            $q->execute(['id' => $senderId]);
            $sender = $q->fetch(PDO::FETCH_ASSOC);
            $senderName = trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));

            $ins->execute([
                'wid' => $receiverWallet['id'],
                'type' => 'deposit',
                'amount' => $amount,
                'status' => 'completed',
                'desc' => "Transfer from {$senderName}",
                'mod' => 'internal',
                'sid' => $senderId
            ]);

            $this->db->commit();
            return [
                'success' => true, 
                'message' => 'Successfully sent $' . number_format($amount, 2) . ' to ' . $receiverName,
                'new_balance' => $newSenderBalance
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Send Money Failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Transaction failed. Please try again.'];
        }
    }

    /**
     * Get list of users for Quick Send (friends or recent contacts)
     */
    public function getQuickSendUsers(int $userId): array {
        try {
            // Get users who have wallets and are friends or have recent transactions with
            $q = $this->db->prepare("
                SELECT DISTINCT u.id, u.first_name, u.last_name, u.avatar_url
                FROM users u
                WHERE u.id != :uid 
                  AND u.id IN (
                    SELECT CASE WHEN user_id = :uid THEN friend_id ELSE user_id END
                    FROM friends 
                    WHERE status = 'accepted' AND (user_id = :uid OR friend_id = :uid)
                    UNION
                    SELECT CASE WHEN user_one_id = :uid THEN user_two_id ELSE user_one_id END
                    FROM friends 
                    WHERE status = 'accepted' AND (user_one_id = :uid OR user_two_id = :uid)
                  )
                ORDER BY u.first_name ASC
                LIMIT 10
            ");
            $q->execute(['uid' => $userId]);
            return $q->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}
