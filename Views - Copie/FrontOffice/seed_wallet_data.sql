
-- ENSURE WALLETS EXIST
INSERT IGNORE INTO `wallets` (`user_id`, `balance`, `currency`, `created_at`, `updated_at`) VALUES
(1, 2450.75, 'USD', NOW() - INTERVAL 60 DAY, NOW()),
(3, 150.00, 'USD', NOW() - INTERVAL 60 DAY, NOW()),
(10, 890.00, 'USD', NOW() - INTERVAL 60 DAY, NOW()),
(13, 1240.50, 'USD', NOW() - INTERVAL 60 DAY, NOW()),
(15, 45.00, 'USD', NOW() - INTERVAL 60 DAY, NOW()),
(27, 0.00, 'USD', NOW() - INTERVAL 60 DAY, NOW()),
(31, 3200.00, 'USD', NOW() - INTERVAL 60 DAY, NOW());

-- ENSURE PAYMENT METHODS FOR ADMIN (USER 1)
INSERT IGNORE INTO `user_payment_methods` (`user_id`, `provider`, `provider_customer_id`, `last_four`, `is_default`, `created_at`) VALUES
(1, 'stripe', 'cus_PqW123456789', '4242', 1, NOW() - INTERVAL 50 DAY),
(1, 'paypal', 'PP-ID-998877', NULL, 0, NOW() - INTERVAL 45 DAY),
(1, 'bank', 'BANK-XX-1122', '1122', 0, NOW() - INTERVAL 40 DAY);

-- CLEANUP OLD TEST DATA FOR USER 1 (Optional, but good for consistent demo)
-- DELETE FROM wallet_transactions WHERE wallet_id IN (SELECT id FROM wallets WHERE user_id = 1);

-- INSERT DYNAMIC SAMPLE TRANSACTIONS FOR USER 1
-- We'll assume user 1's wallet ID is the one mapped to user_id 1
SET @wid = (SELECT id FROM wallets WHERE user_id = 1);

INSERT INTO `wallet_transactions` (`wallet_id`, `type`, `amount`, `status`, `description`, `reference_id`, `source_module`, `source_id`, `created_at`) VALUES
(@wid, 'deposit', 5000.00, 'completed', 'Initial funding via Stripe', 'ch_1Mabc123', 'payment_gateway', 1, NOW() - INTERVAL 30 DAY),
(@wid, 'marketplace_purchase', -1200.00, 'completed', 'Purchase of "Premium UI Kit"', 'ord_9901', 'marketplace', 101, NOW() - INTERVAL 25 DAY),
(@wid, 'marketplace_sale', 450.00, 'completed', 'Sale of "Custom Icon Pack"', 'ord_9905', 'marketplace', 202, NOW() - INTERVAL 20 DAY),
(@wid, 'transfer', -50.00, 'completed', 'Transfer to Noah Tanaka', 'trx_881', 'internal', 3, NOW() - INTERVAL 18 DAY),
(@wid, 'marketplace_sale', 800.00, 'completed', 'Sale of "React Dashboard Template"', 'ord_9910', 'marketplace', 205, NOW() - INTERVAL 15 DAY),
(@wid, 'withdrawal', -1000.00, 'completed', 'Withdrawal to Bank Account', 'wd_771', 'bank_transfer', 1, NOW() - INTERVAL 12 DAY),
(@wid, 'marketplace_purchase', -250.00, 'completed', 'Purchase of "SEO Checklist"', 'ord_9920', 'marketplace', 110, NOW() - INTERVAL 10 DAY),
(@wid, 'marketplace_sale', 1200.00, 'completed', 'Sale of "Advanced Go Course"', 'ord_9925', 'marketplace', 210, NOW() - INTERVAL 8 DAY),
(@wid, 'donation_out', -100.00, 'completed', 'Donation to Open Source Fund', 'don_111', 'community', 5, NOW() - INTERVAL 6 DAY),
(@wid, 'marketplace_sale', 650.00, 'completed', 'Sale of "Docker Best Practices"', 'ord_9930', 'marketplace', 215, NOW() - INTERVAL 4 DAY),
(@wid, 'withdrawal', -1500.00, 'pending', 'Withdrawal to PayPal', 'wd_775', 'payment_gateway', 2, NOW() - INTERVAL 2 DAY),
(@wid, 'deposit', 1000.00, 'completed', 'Top-up via PayPal', 'ch_1Mzxy987', 'payment_gateway', 2, NOW() - INTERVAL 1 DAY),
(@wid, 'marketplace_purchase', -49.25, 'completed', 'Purchase of "Email Templates"', 'ord_9940', 'marketplace', 115, NOW() - INTERVAL 5 HOUR);

-- UPDATE FINAL BALANCE TO MATCH SUM (roughly)
UPDATE wallets SET balance = (SELECT SUM(amount) FROM wallet_transactions WHERE wallet_id = @wid AND status = 'completed') WHERE id = @wid;

-- ADD SOME EVENT LOGS
INSERT INTO `wallet_event_logs` (`wallet_id`, `event_type`, `message`, `created_at`) VALUES
(@wid, 'security_audit', 'New payment method added: Visa ending in 4242', NOW() - INTERVAL 50 DAY),
(@wid, 'balance_threshold', 'Balance exceeded $4000', NOW() - INTERVAL 30 DAY),
(@wid, 'withdrawal_request', 'Pending withdrawal of $1500 initiated', NOW() - INTERVAL 2 DAY);
