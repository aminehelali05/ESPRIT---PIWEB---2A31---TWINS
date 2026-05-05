<?php

include_once(__DIR__ . '/../config.php');
require_once __DIR__ . '/../Models/Marketplace.php';
require_once __DIR__ . '/../services/MarketplaceService.php';

// Load Stripe SDK if available
$stripeAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($stripeAutoload)) {
    require_once $stripeAutoload;
} else {
    error_log("Stripe Autoload not found at: " . $stripeAutoload);
}

class MarketplaceController
{
    private PDO $db;
    private MarketplaceService $service;

    public function __construct(?PDO $pdo = null)
    {
        $this->db     = $pdo ?? config::getConnexion();
        $this->service = new MarketplaceService($this->db);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Read Endpoints
    // ─────────────────────────────────────────────────────────────────────
    public function index(): void
    {
        $filters    = [
            'category_id' => $_GET['category'] ?? null,
            'type'        => $_GET['type']     ?? null,
            'search'      => $_GET['q']        ?? null,
        ];
        $items      = $this->service->getItems($filters);
        $categories = $this->service->getCategories();
        include __DIR__ . '/../Views/FrontOffice/marketplace.php';
    }

    public function listItems(array $filters = []): array
    {
        return $this->service->getItems($filters);
    }

    public function getCategories(): array
    {
        return $this->service->getCategories();
    }

    public function getItemById(int $id): ?array
    {
        return $this->service->getItemById($id);
    }

    public function getItemRating(int $itemId): string
    {
        try {
            $q = $this->db->prepare('SELECT AVG(rating) as avg_rating FROM marketplace_reviews WHERE item_id = :id');
            $q->execute(['id' => $itemId]);
            $res = $q->fetch();
            return ($res && $res['avg_rating']) ? number_format((float)$res['avg_rating'], 1) : 'New';
        } catch (\Throwable $e) {
            return 'New';
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Write Endpoints
    // ─────────────────────────────────────────────────────────────────────
    public function createItem(int $userId, array $payload): ?int
    {
        $payload['user_id'] = $userId;
        return $this->service->createItem($payload);
    }

    public function updateItem(int $userId, int $itemId, array $payload, bool $isAdmin = false): bool
    {
        $item = $this->service->getItemById($itemId);
        if (!$item) return false;
        if (!$isAdmin && (int)$item['user_id'] !== $userId) return false;
        return $this->service->updateItem($itemId, $payload);
    }

    public function deleteItem(int $userId, int $itemId, bool $isAdmin = false): bool
    {
        $item = $this->service->getItemById($itemId);
        if (!$item) return false;
        if (!$isAdmin && (int)$item['user_id'] !== $userId) return false;
        return $this->service->deleteItem($itemId);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  STRIPE CHECKOUT — Create Session
    // ─────────────────────────────────────────────────────────────────────
    public function createStripeSession(int $buyerId, int $itemId): array
    {
        $secretKey = (string) config::get('STRIPE_SECRET_KEY', '');
        if ($secretKey === '' || !class_exists('\Stripe\Stripe')) {
            return ['success' => false, 'message' => 'Stripe is not configured on this server.'];
        }

        $item = $this->service->getItemById($itemId);
        if (!$item || $item['status'] !== 'active') {
            return ['success' => false, 'message' => 'Item not found or unavailable.'];
        }
        if (isset($item['quantity']) && (int)$item['quantity'] <= 0) {
            return ['success' => false, 'message' => 'This item is currently out of stock.'];
        }
        if ($buyerId === (int)$item['user_id']) {
            return ['success' => false, 'message' => 'You cannot purchase your own listing.'];
        }

        \Stripe\Stripe::setApiKey($secretKey);

        $baseUrl = config::getBaseUrl();
        $price   = (int) round((float)$item['price'] * 100); // Stripe uses cents

        try {
            // Create a pending order record first
            $stmt = $this->db->prepare(
                "INSERT INTO marketplace_orders (buyer_id, seller_id, item_id, amount, status, payment_method)
                 VALUES (:buyer_id, :seller_id, :item_id, :amount, 'pending', 'stripe')"
            );
            $stmt->execute([
                'buyer_id'  => $buyerId,
                'seller_id' => $item['user_id'],
                'item_id'   => $itemId,
                'amount'    => $item['price'],
            ]);
            $orderId = (int)$this->db->lastInsertId();

            $stripeImages = [];
            if (!empty($item['thumbnail_url'])) {
                // thumbnail_url is stored as '../../assets/uploads/...'
                // We need to convert it to a public absolute URL
                $cleanPath = str_replace('../', '', $item['thumbnail_url']);
                $cleanPath = ltrim($cleanPath, '/');
                $stripeImages[] = $baseUrl . '/' . $cleanPath;
            }

            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'product_data' => [
                            'name'        => $item['title'],
                            'description' => mb_substr($item['description'] ?? '', 0, 200),
                            'images'      => $stripeImages,
                        ],
                        'unit_amount' => $price,
                    ],
                    'quantity' => 1,
                ]],
                'mode'         => 'payment',
                'success_url'  => $baseUrl . '/index.php?action=stripe_success&session_id={CHECKOUT_SESSION_ID}&order_id=' . $orderId,
                'cancel_url'   => $baseUrl . '/Views/FrontOffice/marketplace.php?stripe_cancelled=1',
                'metadata'     => [
                    'order_id'  => $orderId,
                    'buyer_id'  => $buyerId,
                    'seller_id' => $item['user_id'],
                    'item_id'   => $itemId,
                ],
                'client_reference_id' => (string) $orderId,
            ]);

            // Store the session ID on the pending order
            $upd = $this->db->prepare("UPDATE marketplace_orders SET stripe_session_id = :sid WHERE id = :id");
            $upd->execute(['sid' => $session->id, 'id' => $orderId]);

            return [
                'success'    => true,
                'url'        => $session->url,
                'session_id' => $session->id,
                'order_id'   => $orderId,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Stripe session error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Stripe error: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            error_log("Stripe session general error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Server error. Please try again.'];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  STRIPE SUCCESS — Confirm Order After Redirect
    // ─────────────────────────────────────────────────────────────────────
    public function handleStripeSuccess(string $sessionId, int $orderId): bool
    {
        $secretKey = (string) config::get('STRIPE_SECRET_KEY', '');
        if ($secretKey === '' || !class_exists('\Stripe\Stripe')) {
            return false;
        }

        try {
            \Stripe\Stripe::setApiKey($secretKey);
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                return false;
            }

            // Verify session belongs to this order
            $check = $this->db->prepare("SELECT * FROM marketplace_orders WHERE id = :id AND stripe_session_id = :sid AND status = 'pending'");
            $check->execute(['id' => $orderId, 'sid' => $sessionId]);
            $order = $check->fetch();
            if (!$order) {
                // Already confirmed or mismatch
                $alreadyPaid = $this->db->prepare("SELECT id FROM marketplace_orders WHERE id = :id AND status = 'paid'");
                $alreadyPaid->execute(['id' => $orderId]);
                return (bool)$alreadyPaid->fetch();
            }

            $this->completeMarketplaceOrder($orderId, $session->payment_intent ?? '');
            return true;
        } catch (\Throwable $e) {
            error_log("Stripe success confirm error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ATOMIC ORDER COMPLETION:
     * 1. Update Order Status
     * 2. Decrement Item Quantity
     * 3. Credit Seller Wallet
     * 4. Log Transactions
     */
    public function completeMarketplaceOrder(int $orderId, string $paymentIntent = ''): void
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM marketplace_orders WHERE id = :id AND status = 'pending'");
            $stmt->execute(['id' => $orderId]);
            $order = $stmt->fetch();
            if (!$order) return;

            $this->db->beginTransaction();

            // 1. Mark Order as Paid
            $updOrder = $this->db->prepare("UPDATE marketplace_orders SET status = 'paid', stripe_payment_intent = :pi WHERE id = :id");
            $updOrder->execute(['pi' => $paymentIntent, 'id' => $orderId]);

            // 2. Decrement Item Quantity (if physical or limited)
            $item = $this->service->getItemById((int)$order['item_id']);
            if ($item && isset($item['quantity'])) {
                $newQty = max(0, (int)$item['quantity'] - 1);
                $updItem = $this->db->prepare("UPDATE marketplace_items SET quantity = :qty WHERE id = :id");
                $updItem->execute(['qty' => $newQty, 'id' => $order['item_id']]);
            }

            // 3. Credit Seller Wallet
            require_once __DIR__ . '/WalletController.php';
            $walletCtrl = new WalletController($this->db);
            
            $amount = (float)$order['amount'];
            $sellerId = (int)$order['seller_id'];
            $buyerId = (int)$order['buyer_id'];

            // Get item title for description
            $itemTitle = $item ? $item['title'] : "Item #{$order['item_id']}";

            // Seller Credit
            $walletCtrl->addTransaction(
                $sellerId,
                'marketplace_sale',
                $amount,
                "Sale: " . $itemTitle,
                'completed',
                'marketplace',
                $orderId,
                ['buyer_id' => $buyerId]
            );

            // Buyer Debit (Log only for Stripe payments)
            if ($order['payment_method'] === 'stripe') {
                $walletCtrl->addTransaction(
                    $buyerId,
                    'marketplace_purchase',
                    -$amount,
                    "Purchase: " . $itemTitle . " (via Stripe)",
                    'completed',
                    'marketplace',
                    $orderId,
                    ['seller_id' => $sellerId, 'payment_intent' => $paymentIntent]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Order completion error: " . $e->getMessage());
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  REVIEWS & SOCIAL
    // ─────────────────────────────────────────────────────────────────────

    public function getReviews(int $itemId): array
    {
        return $this->service->getReviewsByItem($itemId);
    }

    public function submitReview(int $userId, int $itemId, int $rating, string $comment): array
    {
        $orderId = $this->service->canUserReview($userId, $itemId);
        if (!$orderId) {
            return ['success' => false, 'message' => 'You must purchase this item before reviewing it.'];
        }

        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5.'];
        }

        if ($this->service->addReview($userId, $itemId, $rating, $comment, $orderId)) {
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Failed to save review.'];
    }

    public function canReview(int $userId, int $itemId): bool
    {
        return $this->service->canUserReview($userId, $itemId) !== null;
    }

    public function getRelatedItems(int $itemId, int $categoryId, int $limit = 6): array
    {
        return $this->service->getRelatedItems($itemId, $categoryId, $limit);
    }

    public function getChatUrl(int $currentUserId, int $sellerId): array
    {
        if ($currentUserId === $sellerId) {
            return ['success' => false, 'message' => 'You cannot chat with yourself.'];
        }

        $msgCtrl = new MessageController($this->db);
        $convId  = $msgCtrl->ensurePrivateConversationForPair($currentUserId, $sellerId);
        
        if ($convId) {
            return ['success' => true, 'url' => 'messages.php?conversation_id=' . $convId];
        }
        return ['success' => false, 'message' => 'Could not initialize conversation.'];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  STRIPE WEBHOOK — Async payment confirmation
    // ─────────────────────────────────────────────────────────────────────
    public function handleStripeWebhook(): void
    {
        $secretKey     = (string) config::get('STRIPE_SECRET_KEY', '');
        $webhookSecret = (string) config::get('STRIPE_WEBHOOK_SECRET', '');

        if (!class_exists('\Stripe\Stripe') || $secretKey === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Stripe not configured']);
            exit;
        }

        \Stripe\Stripe::setApiKey($secretKey);
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            if ($webhookSecret !== '') {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } else {
                $event = \Stripe\Event::constructFrom(json_decode($payload, true));
            }
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Webhook signature verification failed']);
            exit;
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderId = (int)($session->client_reference_id ?? 0);
            if ($orderId > 0) {
                try {
                    $this->completeMarketplaceOrder($orderId, $session->payment_intent ?? '');
                } catch (\Throwable $e) {
                    error_log("Webhook order update error: " . $e->getMessage());
                }
            }
        }

        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Order History
    // ─────────────────────────────────────────────────────────────────────
    public function getOrdersForUser(int $userId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT o.*, i.title, i.thumbnail_url, i.type, i.category_id,
                        i.delivery_option, i.estimated_delivery_time, i.shipping_cost,
                        i.rating, i.review_count,
                        c.name AS category_name,
                        u.first_name AS seller_first, u.last_name AS seller_last,
                        u.avatar_url AS seller_avatar, u.country AS seller_country,
                        u.exact_location AS seller_exact_location,
                        u.latitude AS seller_latitude, u.longitude AS seller_longitude,
                        u.xp AS seller_xp, u.role AS seller_role
                 FROM marketplace_orders o
                 JOIN marketplace_items i ON o.item_id = i.id
                 JOIN users u ON o.seller_id = u.id
                 LEFT JOIN marketplace_categories c ON i.category_id = c.id
                 WHERE o.buyer_id = :uid AND o.status = 'paid'
                 ORDER BY o.created_at DESC"
            );
            $stmt->execute(['uid' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function hasPurchased(int $buyerId, int $itemId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id FROM marketplace_orders WHERE buyer_id = :bid AND item_id = :iid AND status = 'paid' LIMIT 1"
            );
            $stmt->execute(['bid' => $buyerId, 'iid' => $itemId]);
            return (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Seller Stats
    // ─────────────────────────────────────────────────────────────────────
    public function getSellerStats(int $sellerId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as total_sales, COALESCE(SUM(amount), 0) as total_revenue
                 FROM marketplace_orders WHERE seller_id = :sid AND status = 'paid'"
            );
            $stmt->execute(['sid' => $sellerId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_sales' => 0, 'total_revenue' => 0];
        } catch (\Throwable $e) {
            return ['total_sales' => 0, 'total_revenue' => 0];
        }
    }

    public function getMyListings(int $userId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT i.*, c.name as category_name,
                        (SELECT COUNT(*) FROM marketplace_orders o WHERE o.item_id = i.id AND o.status = 'paid') as sales_count
                 FROM marketplace_items i
                 LEFT JOIN marketplace_categories c ON i.category_id = c.id
                 WHERE i.user_id = :uid
                 ORDER BY i.created_at DESC"
            );
            $stmt->execute(['uid' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
