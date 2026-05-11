<?php

class MarketplaceService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getCategories(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM marketplace_categories ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getItems(array $filters = []): array
    {
        try {
            $sql = "SELECT i.*, u.first_name, u.last_name, u.avatar_url, u.country, u.exact_location, u.latitude, u.longitude, u.xp, u.role, c.name as category_name 
                    FROM marketplace_items i
                    JOIN users u ON i.user_id = u.id
                    LEFT JOIN marketplace_categories c ON i.category_id = c.id
                    WHERE i.status = 'active'";
            
            $params = [];
            if (!empty($filters['category_id'])) {
                $sql .= " AND i.category_id = :category_id";
                $params['category_id'] = $filters['category_id'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (i.title LIKE :search OR i.description LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search OR c.name LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            $sql .= " ORDER BY i.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getItemById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT i.*, u.first_name, u.last_name, u.avatar_url, u.country, u.exact_location, u.latitude, u.longitude, u.xp, u.role, c.name as category_name
                                        FROM marketplace_items i
                                        JOIN users u ON i.user_id = u.id
                                        LEFT JOIN marketplace_categories c ON i.category_id = c.id
                                        WHERE i.id = :id");
            $stmt->execute(['id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            return $item ?: null;
        } catch (Exception $e) {
            error_log("getItemById error: " . $e->getMessage());
            return null;
        }
    }

    public function createItem(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO marketplace_items (
                                        user_id, title, description, price, quantity, type, category_id,
                                        thumbnail_url, video_url, video_path,
                                        delivery_option, estimated_delivery_time, shipping_cost
                                    ) VALUES (
                                        :user_id, :title, :description, :price, :quantity, :type, :category_id,
                                        :thumbnail_url, :video_url, :video_path,
                                        :delivery_option, :estimated_delivery_time, :shipping_cost
                                    )");
        $stmt->execute([
            'user_id'           => $data['user_id'],
            'title'             => $data['title'],
            'description'       => $data['description'],
            'price'             => $data['price'],
            'quantity'          => $data['quantity'] ?? 1,
            'type'              => $data['type'] ?? 'digital',
            'category_id'       => $data['category_id'],
            'thumbnail_url'     => $data['thumbnail_url'],
            'video_url'         => $data['video_url'] ?? null,
            'video_path'        => $data['video_path'] ?? null,
            'delivery_option'   => $data['delivery_option'] ?? 'instant',
            'estimated_delivery_time' => $data['estimated_delivery_time'] ?? null,
            'shipping_cost'     => $data['shipping_cost'] ?? 0
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getRecommendedItems(int $userId, int $limit = 4): array
    {
        try {
            $sql = "SELECT i.*, u.first_name, u.last_name, u.avatar_url, u.country, u.exact_location, u.latitude, u.longitude, u.xp, u.role, c.name as category_name 
                    FROM marketplace_items i
                    JOIN users u ON i.user_id = u.id
                    LEFT JOIN marketplace_categories c ON i.category_id = c.id
                    WHERE i.status = 'active'
                    ORDER BY i.rating DESC, i.review_count DESC, i.created_at DESC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getTrendingItems(int $limit = 4): array
    {
        try {
            $sql = "SELECT i.*, u.first_name, u.last_name, u.avatar_url, u.country, u.exact_location, u.latitude, u.longitude, u.xp, u.role, c.name as category_name 
                    FROM marketplace_items i
                    JOIN users u ON i.user_id = u.id
                    LEFT JOIN marketplace_categories c ON i.category_id = c.id
                    WHERE i.status = 'active'
                    ORDER BY i.review_count DESC, i.rating DESC, i.created_at DESC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getTopSellers(int $limit = 4): array
    {
        try {
            // Get users who have sold items in the marketplace
            $sql = "SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.role, COUNT(o.id) as sales_count
                    FROM users u
                    JOIN marketplace_orders o ON u.id = o.seller_id
                    WHERE o.status IN ('paid', 'fulfilled')
                    GROUP BY u.id
                    ORDER BY sales_count DESC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fallback if no orders exist yet
            if (empty($sellers)) {
                $sql = "SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.role 
                        FROM users u 
                        JOIN marketplace_items i ON u.id = i.user_id 
                        WHERE i.status = 'active'
                        GROUP BY u.id
                        LIMIT :limit";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $sellers;
        } catch (Exception $e) {
            return [];
        }
    }

    public function updateItem(int $id, array $data): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE marketplace_items SET 
                                            title = :title, 
                                            description = :description, 
                                            price = :price, 
                                            quantity = :quantity,
                                            category_id = :category_id, 
                                            type = :type, 
                                            video_url = :video_url,
                                            video_path = :video_path,
                                            delivery_option = :delivery_option, 
                                            estimated_delivery_time = :estimated_delivery_time, 
                                            shipping_cost = :shipping_cost,
                                            updated_at = NOW() 
                                        WHERE id = :id");
            return $stmt->execute([
                'id'            => $id,
                'title'         => $data['title'],
                'description'   => $data['description'],
                'price'         => $data['price'],
                'quantity'      => $data['quantity'] ?? 1,
                'category_id'   => $data['category_id'],
                'type'          => $data['type'] ?? 'digital',
                'video_url'     => $data['video_url'] ?? null,
                'video_path'    => $data['video_path'] ?? null,
                'delivery_option' => $data['delivery_option'] ?? 'instant',
                'estimated_delivery_time' => $data['estimated_delivery_time'] ?: null,
                'shipping_cost' => $data['shipping_cost'] ?? 0
            ]);
        } catch (Exception $e) {
            error_log("updateItem error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteItem(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE marketplace_items SET status = 'deleted', updated_at = NOW() WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (Exception $e) {
            error_log("deleteItem error: " . $e->getMessage());
            return false;
        }
    }

    public function updateItemMedia(int $itemId, array $data): bool
    {
        try {
            $fields = [];
            $params = ['id' => $itemId];

            if (array_key_exists('thumbnail_url', $data)) {
                $fields[] = 'thumbnail_url = :thumbnail_url';
                $params['thumbnail_url'] = $data['thumbnail_url'];
            }

            if (empty($fields)) {
                return false;
            }

            $sql = 'UPDATE marketplace_items SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\Throwable $e) {
            error_log("updateItemMedia error: " . $e->getMessage());
            return false;
        }
    }

    public function getAllActiveItems(): array
    {
        return $this->getItems([]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  REVIEW SYSTEM
    // ─────────────────────────────────────────────────────────────────────

    public function getReviewsByItem(int $itemId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT r.*, u.first_name, u.last_name, u.avatar_url 
                                        FROM marketplace_reviews r
                                        JOIN users u ON r.user_id = u.id
                                        WHERE r.item_id = :item_id
                                        ORDER BY r.created_at DESC");
            $stmt->execute(['item_id' => $itemId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function addReview(int $userId, int $itemId, int $rating, string $comment, int $orderId): bool
    {
        try {
            $this->db->beginTransaction();

            // Insert review
            $stmt = $this->db->prepare("INSERT INTO marketplace_reviews (item_id, order_id, user_id, rating, comment) 
                                        VALUES (:item_id, :order_id, :user_id, :rating, :comment)");
            $stmt->execute([
                'item_id'  => $itemId,
                'order_id' => $orderId,
                'user_id'  => $userId,
                'rating'   => $rating,
                'comment'  => $comment
            ]);

            // Update item aggregate stats
            $stmt = $this->db->prepare("UPDATE marketplace_items i
                                        SET i.rating = (SELECT AVG(rating) FROM marketplace_reviews WHERE item_id = :item_id),
                                            i.review_count = (SELECT COUNT(*) FROM marketplace_reviews WHERE item_id = :item_id)
                                        WHERE i.id = :item_id");
            $stmt->execute(['item_id' => $itemId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function canUserReview(int $userId, int $itemId): ?int
    {
        try {
            // Check if user has a paid order for this item and hasn't reviewed it yet
            $stmt = $this->db->prepare("SELECT o.id 
                                        FROM marketplace_orders o
                                        LEFT JOIN marketplace_reviews r ON o.id = r.order_id
                                        WHERE o.buyer_id = :user_id 
                                          AND o.item_id = :item_id 
                                          AND o.status = 'paid'
                                          AND r.id IS NULL
                                        LIMIT 1");
            $stmt->execute(['user_id' => $userId, 'item_id' => $itemId]);
            $res = $stmt->fetch();
            return $res ? (int)$res['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getRelatedItems(int $itemId, int $categoryId, int $limit = 6): array
    {
        try {
            // 1. Try to find items in same category
            $stmt = $this->db->prepare("SELECT i.*, u.first_name, u.last_name, u.avatar_url, c.name as category_name
                                        FROM marketplace_items i
                                        JOIN users u ON i.user_id = u.id
                                        LEFT JOIN marketplace_categories c ON i.category_id = c.id
                                        WHERE i.category_id = :cat_id AND i.id != :item_id AND i.status = 'active'
                                        ORDER BY i.rating DESC, i.created_at DESC
                                        LIMIT :limit");
            $stmt->bindValue(':cat_id', $categoryId, PDO::PARAM_INT);
            $stmt->bindValue(':item_id', $itemId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $related = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. If not enough, fallback to latest active items
            if (count($related) < $limit) {
                $needed = $limit - count($related);
                $excludeIds = array_column($related, 'id');
                $excludeIds[] = $itemId;
                $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
                
                $sql = "SELECT i.*, u.first_name, u.last_name, u.avatar_url, c.name as category_name
                        FROM marketplace_items i
                        JOIN users u ON i.user_id = u.id
                        LEFT JOIN marketplace_categories c ON i.category_id = c.id
                        WHERE i.id NOT IN ($placeholders) AND i.status = 'active'
                        ORDER BY i.created_at DESC
                        LIMIT ?";
                
                $stmtFallback = $this->db->prepare($sql);
                foreach ($excludeIds as $idx => $id) {
                    $stmtFallback->bindValue($idx + 1, $id, PDO::PARAM_INT);
                }
                $stmtFallback->bindValue(count($excludeIds) + 1, $needed, PDO::PARAM_INT);
                $stmtFallback->execute();
                
                $fallback = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
                $related = array_merge($related, $fallback);
            }

            return $related;
        } catch (Exception $e) {
            error_log("getRelatedItems error: " . $e->getMessage());
            return [];
        }
    }
}
