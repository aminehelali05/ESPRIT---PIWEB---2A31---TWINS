<?php

include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/Message.php');

class MessageController
{
    private PDO $pdo;
    private array $columnCache = [];
    private static bool $schemaReady = false;
    private static bool $legacySynced = false;
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        }

    private function normalizeMessageType(string $messageType): string
    {
        $messageType = strtolower(trim($messageType));
        return in_array($messageType, ['text', 'image', 'video', 'audio', 'file', 'system'], true)
            ? $messageType
            : 'text';
    }

    private function encodeMetadata($metadata): ?string
    {
        if ($metadata === null || $metadata === '') {
            return null;
        }
        if (is_string($metadata)) {
            $trimmed = trim($metadata);
            if ($trimmed === '') {
                return null;
            }
            json_decode($trimmed, true);
            return json_last_error() === JSON_ERROR_NONE
                ? $trimmed
                : json_encode(['value' => $trimmed], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_array($metadata) || is_object($metadata)) {
            $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $encoded === false ? null : $encoded;
        }
        return json_encode(['value' => $metadata], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function hydrateMessage(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'conversation_id' => (int) ($row['conversation_id'] ?? 0),
            'sender_id' => (int) ($row['sender_id'] ?? 0),
            'message_type' => (string) ($row['message_type'] ?? 'text'),
            'body' => (string) ($row['body'] ?? ''),
            'media_url' => (string) ($row['media_url'] ?? ''),
            'metadata' => isset($row['metadata']) && $row['metadata'] !== null ? json_decode((string) $row['metadata'], true) : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'edited_at' => (string) ($row['edited_at'] ?? ''),
            'is_edited' => (int) ($row['is_edited'] ?? 0),
            'is_deleted' => (int) ($row['is_deleted'] ?? 0),
            'seen_at' => (string) ($row['seen_at'] ?? ''),
            'sender' => [
                'id' => (int) ($row['sender_id'] ?? 0),
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'avatar_url' => (string) ($row['avatar_url'] ?? ''),
                'role' => (string) ($row['role'] ?? 'user'),
            ],
        ];
    }

    public function listMessages(int $currentUserId, int $conversationId, int $afterId = 0, int $limit = 200): array
    {
        $this->syncLegacyThreads();
        $conversation = $this->getConversationForUser($conversationId, $currentUserId);
        if (!$conversation) {
            throw new RuntimeException('Conversation not found.');
        }

        $limit = max(1, min(300, $limit));
        $afterId = max(0, $afterId);
        $params = ['conversation_id' => $conversationId];
        $where = 'm.conversation_id = :conversation_id';
        if ($afterId > 0) {
            $where .= ' AND m.id > :after_id';
            $params['after_id'] = $afterId;
        }

        $editedAtSelect = $this->tableHasColumn('messages', 'edited_at') ? ', m.edited_at' : ', NULL AS edited_at';

        $q = $this->pdo->prepare(
            "SELECT m.id, m.conversation_id, m.sender_id, m.message_type, m.body, m.media_url, m.metadata,
                    m.created_at, m.updated_at, m.is_edited, m.is_deleted{$editedAtSelect},
                    u.first_name, u.last_name, u.avatar_url, u.role
             FROM messages m
             INNER JOIN users u ON u.id = m.sender_id
             WHERE {$where}
             ORDER BY m.id ASC
             LIMIT {$limit}"
        );
        $q->execute($params);

        $messages = [];
        foreach ((array) $q->fetchAll() as $row) {
            $messages[] = $this->hydrateMessage($row);
        }

        $this->markRead($currentUserId, $conversationId);

        if ((string) ($conversation['type'] ?? '') === 'private' && !empty($messages)) {
            $this->attachPrivateSeenAt($messages, $conversationId, $currentUserId);
        }

        $latestMessageId = 0;
        foreach ($messages as $message) {
            $latestMessageId = max($latestMessageId, (int) ($message['id'] ?? 0));
        }

        if ($latestMessageId === 0) {
            try {
                $latest = $this->pdo->prepare('SELECT MAX(id) AS latest_id FROM messages WHERE conversation_id = :conversation_id');
                $latest->execute(['conversation_id' => $conversationId]);
                $latestMessageId = (int) (($latest->fetch())['latest_id'] ?? 0);
            } catch (Throwable $exception) {
            }
        }

        return [
            'messages' => $messages,
            'conversation' => $conversation,
            'group_members' => (string) ($conversation['type'] ?? '') === 'group' ? $this->getGroupMembers($conversationId) : [],
            'cursor' => [
                'latest_message_id' => $latestMessageId,
                'returned_count' => count($messages),
            ],
        ];
    }

    public function sendMessage(int $currentUserId, int $conversationId, string $messageType, string $body, string $mediaUrl = '', $metadata = null): array
    {
        $this->syncLegacyThreads();
        $conversation = $this->getConversationForUser($conversationId, $currentUserId);
        if (!$conversation) {
            throw new RuntimeException('Conversation not found.');
        }

        $messageType = $this->normalizeMessageType($messageType);
        $body = trim($body);
        $mediaUrl = trim($mediaUrl);
        if ($body === '' && $mediaUrl === '') {
            throw new RuntimeException('Message content is required.');
        }

        $q = $this->pdo->prepare(
            'INSERT INTO messages (conversation_id, sender_id, message_type, body, media_url, metadata)
             VALUES (:conversation_id, :sender_id, :message_type, :body, :media_url, :metadata)'
        );
        $q->execute([
            'conversation_id' => $conversationId,
            'sender_id' => $currentUserId,
            'message_type' => $messageType,
            'body' => $body !== '' ? substr($body, 0, 5000) : null,
            'media_url' => $mediaUrl !== '' ? substr($mediaUrl, 0, 1024) : null,
            'metadata' => $this->encodeMetadata($metadata),
        ]);

        $messageId = (int) $this->pdo->lastInsertId();
        $this->touchConversation($conversationId);

        return $this->getMessageById($messageId, $currentUserId) ?: [
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_id' => $currentUserId,
            'message_type' => $messageType,
            'body' => $body,
            'media_url' => $mediaUrl,
            'metadata' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'is_edited' => 0,
            'is_deleted' => 0,
            'seen_at' => '',
            'sender' => ['id' => $currentUserId],
        ];
    }

    public function getMessageById(int $messageId, int $viewerId = 0): ?array
    {
        if ($messageId <= 0) {
            return null;
        }

        $editedAtSelect = $this->tableHasColumn('messages', 'edited_at') ? ', m.edited_at' : ', NULL AS edited_at';
        try {
            $q = $this->pdo->prepare(
                "SELECT m.id, m.conversation_id, m.sender_id, m.message_type, m.body, m.media_url, m.metadata,
                        m.created_at, m.updated_at, m.is_edited, m.is_deleted{$editedAtSelect},
                        u.first_name, u.last_name, u.avatar_url, u.role
                 FROM messages m
                 INNER JOIN users u ON u.id = m.sender_id
                 WHERE m.id = :id
                 LIMIT 1"
            );
            $q->execute(['id' => $messageId]);
            $row = $q->fetch();
            return is_array($row) ? $this->hydrateMessage($row) : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    public function deleteMessage(int $currentUserId, int $messageId): bool
    {
        $this->syncLegacyThreads();
        if ($currentUserId <= 0 || $messageId <= 0) {
            return false;
        }

        try {
            $q = $this->pdo->prepare(
                'SELECT id, sender_id, conversation_id
                 FROM messages
                 WHERE id = :id
                 LIMIT 1'
            );
            $q->execute(['id' => $messageId]);
            $row = $q->fetch();
            if (!$row) {
                return false;
            }

            $conversationId = (int) ($row['conversation_id'] ?? 0);
            $conversation = $this->getConversationForUser($conversationId, $currentUserId);
            if (!$conversation) {
                return false;
            }

            $canDelete = (int) ($row['sender_id'] ?? 0) === $currentUserId;
            if (!$canDelete && (string) ($conversation['type'] ?? '') === 'group') {
                $canDelete = $this->isGroupModerator($conversationId, $currentUserId);
            }
            if (!$canDelete) {
                return false;
            }

            $upd = $this->pdo->prepare(
                'UPDATE messages
                 SET body = :body,
                     media_url = NULL,
                     metadata = NULL,
                     is_deleted = 1,
                     is_edited = 0,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute(['id' => $messageId, 'body' => 'Message deleted']);
            $this->touchConversation($conversationId);
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function editMessage(int $currentUserId, int $messageId, string $body): ?array
    {
        $body = trim($body);
        if ($currentUserId <= 0 || $messageId <= 0 || $body === '') {
            return null;
        }

        $hasEditedAt = $this->tableHasColumn('messages', 'edited_at');
        $setEditedAt = $hasEditedAt ? ', edited_at = NOW()' : '';
        try {
            $q = $this->pdo->prepare(
                'SELECT id, sender_id, conversation_id, message_type, is_deleted
                 FROM messages
                 WHERE id = :id
                 LIMIT 1'
            );
            $q->execute(['id' => $messageId]);
            $message = $q->fetch();
            if (!$message || (int) ($message['sender_id'] ?? 0) !== $currentUserId) {
                return null;
            }
            if ((int) ($message['is_deleted'] ?? 0) === 1 || (string) ($message['message_type'] ?? 'text') === 'system') {
                return null;
            }

            $upd = $this->pdo->prepare(
                "UPDATE messages
                 SET body = :body,
                     is_edited = 1,
                     updated_at = NOW(){$setEditedAt}
                 WHERE id = :id"
            );
            $upd->execute(['body' => substr($body, 0, 5000), 'id' => $messageId]);
            $this->touchConversation((int) ($message['conversation_id'] ?? 0));
            return $this->getMessageById($messageId, $currentUserId);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function markRead(int $currentUserId, int $conversationId): void
    {
        if ($currentUserId <= 0 || $conversationId <= 0) {
            return;
        }

        try {
            $q = $this->pdo->prepare(
                'INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
                 SELECT m.id, :user_id, NOW()
                 FROM messages m
                 LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :user_id
                 WHERE m.conversation_id = :conversation_id
                   AND m.sender_id <> :user_id
                   AND mr.id IS NULL'
            );
            $q->execute(['user_id' => $currentUserId, 'conversation_id' => $conversationId]);
        } catch (Throwable $exception) {
        }
    }

    private function attachPrivateSeenAt(array &$messages, int $conversationId, int $currentUserId): void
    {
        $peer = $this->getPrivatePeer($conversationId, $currentUserId);
        $peerId = (int) ($peer['id'] ?? 0);
        if ($peerId <= 0) {
            return;
        }

        $ids = [];
        foreach ($messages as $message) {
            $id = (int) ($message['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if (empty($ids)) {
            return;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $q = $this->pdo->prepare(
                "SELECT message_id, MAX(read_at) AS seen_at
                 FROM message_reads
                 WHERE user_id = ?
                   AND message_id IN ({$placeholders})
                 GROUP BY message_id"
            );
            $q->execute(array_merge([$peerId], $ids));
            $seen = [];
            foreach ((array) $q->fetchAll() as $row) {
                $seen[(int) ($row['message_id'] ?? 0)] = (string) ($row['seen_at'] ?? '');
            }
            foreach ($messages as &$message) {
                $message['seen_at'] = $seen[(int) ($message['id'] ?? 0)] ?? '';
            }
            unset($message);
        } catch (Throwable $exception) {
        }
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function tableExists(string $table): bool
    {
        try {
            $q = $this->pdo->prepare('SHOW TABLES LIKE :table_name');
            $q->execute(['table_name' => $table]);
            return (bool) $q->fetch();
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function tableHasColumn(string $table, string $column): bool
    {
        $key = strtolower($table . ':' . $column);
        if (array_key_exists($key, $this->columnCache)) {
            return (bool) $this->columnCache[$key];
        }

        try {
            $q = $this->pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $this->pdo->quote($column));
            $this->columnCache[$key] = (bool) ($q && $q->fetch());
        } catch (Throwable $exception) {
            $this->columnCache[$key] = false;
        }

        return (bool) $this->columnCache[$key];
    }

    private function execQuietly(string $sql): void
    {
        try {
            $this->pdo->exec($sql);
        } catch (Throwable $exception) {
        }
    }

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        // Schema is managed via schema_fix.sql
        // We only keep basic checks here if absolutely necessary
        
        self::$schemaReady = true;
    }

    public function syncLegacyThreads(): void
    {
        // Legacy synchronization disabled - unified system is now the source of truth
    }

    public function ensurePrivateConversationForPair(int $firstUserId, int $secondUserId): ?int
    {
        $this->ensureSchema();
        if ($firstUserId <= 0 || $secondUserId <= 0 || $firstUserId === $secondUserId) {
            return null;
        }

        $userOne = min($firstUserId, $secondUserId);
        $userTwo = max($firstUserId, $secondUserId);

        try {
            $q = $this->pdo->prepare(
                'SELECT c.id
                 FROM conversations c
                 INNER JOIN conversation_members cm1 ON cm1.conversation_id = c.id AND cm1.user_id = :user_one
                 INNER JOIN conversation_members cm2 ON cm2.conversation_id = c.id AND cm2.user_id = :user_two
                 WHERE c.type = "private"

                 LIMIT 1'
            );
            $q->execute(['user_one' => $userOne, 'user_two' => $userTwo]);
            $existingId = (int) ($q->fetch()['id'] ?? 0);
            if ($existingId > 0) {
                return $existingId;
            }

            $this->pdo->beginTransaction();
            $ins = $this->pdo->prepare(
                'INSERT INTO conversations (type, created_by, created_at, updated_at)
                 VALUES ("private", :created_by, NOW(), NOW())'
            );
            $ins->execute(['created_by' => $firstUserId]);
            $conversationId = (int) $this->pdo->lastInsertId();

            $member = $this->pdo->prepare(
                'INSERT IGNORE INTO conversation_members (conversation_id, user_id, role)
                 VALUES (:conversation_id, :user_id, "member")'
            );
            $member->execute(['conversation_id' => $conversationId, 'user_id' => $userOne]);
            $member->execute(['conversation_id' => $conversationId, 'user_id' => $userTwo]);
            $this->pdo->commit();

            return $conversationId > 0 ? $conversationId : null;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return null;
        }
    }

    public function getConversationForUser(int $conversationId, int $userId, ?string $type = null): ?array
    {
        $this->syncLegacyThreads();
        if ($conversationId <= 0 || $userId <= 0) {
            return null;
        }

        $sql =
            'SELECT c.*, cm.role AS member_role
             FROM conversations c
             INNER JOIN conversation_members cm ON cm.conversation_id = c.id
             WHERE c.id = :conversation_id
               AND cm.user_id = :user_id

              ';
        $params = ['conversation_id' => $conversationId, 'user_id' => $userId];

        if ($type !== null && in_array($type, ['private', 'group'], true)) {
            $sql .= ' AND c.type = :type';
            $params['type'] = $type;
        }

        $sql .= ' LIMIT 1';

        try {
            $q = $this->pdo->prepare($sql);
            $q->execute($params);
            $row = $q->fetch();
            return is_array($row) ? $row : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    public function getPrivatePeer(int $conversationId, int $currentUserId): ?array
    {
        try {
            $q = $this->pdo->prepare(
                'SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.role, u.email, u.country, u.exact_location
                 FROM conversation_members cm
                 INNER JOIN users u ON u.id = cm.user_id
                 WHERE cm.conversation_id = :conversation_id
                   AND cm.user_id <> :current_user_id

                 ORDER BY cm.joined_at ASC
                 LIMIT 1'
            );
            $q->execute([
                'conversation_id' => $conversationId,
                'current_user_id' => $currentUserId,
            ]);
            $row = $q->fetch();
            return is_array($row) ? $row : null;
        } catch (Throwable $exception) {
            return null;
        }
    }

    public function getGroupMembers(int $conversationId): array
    {
        $this->ensureSchema();
        try {
            $q = $this->pdo->prepare(
                'SELECT cm.conversation_id, cm.user_id, cm.role, cm.joined_at,
                        u.first_name, u.last_name, u.avatar_url, u.role AS user_role
                 FROM conversation_members cm
                 INNER JOIN users u ON u.id = cm.user_id
                 WHERE cm.conversation_id = :conversation_id

                 ORDER BY CASE cm.role WHEN "admin" THEN 0 ELSE 1 END ASC,
                          cm.joined_at ASC,
                          u.first_name ASC'
            );
            $q->execute(['conversation_id' => $conversationId]);
            $members = [];
            foreach ((array) $q->fetchAll() as $row) {
                $members[] = [
                    'group_chat_id' => $conversationId,
                    'conversation_id' => $conversationId,
                    'user_id' => (int) ($row['user_id'] ?? 0),
                    'role' => (string) ($row['role'] ?? 'member'),
                    'joined_at' => (string) ($row['joined_at'] ?? ''),
                    'user' => [
                        'id' => (int) ($row['user_id'] ?? 0),
                        'first_name' => (string) ($row['first_name'] ?? ''),
                        'last_name' => (string) ($row['last_name'] ?? ''),
                        'avatar_url' => (string) ($row['avatar_url'] ?? ''),
                        'role' => (string) ($row['user_role'] ?? 'user'),
                    ],
                ];
            }
            return $members;
        } catch (Throwable $exception) {
            return [];
        }
    }

    public function resolveMemberRole(int $conversationId, int $userId): ?string
    {
        $this->ensureSchema();
        if ($conversationId <= 0 || $userId <= 0) {
            return null;
        }

        try {
            $q = $this->pdo->prepare(
                'SELECT role
                 FROM conversation_members
                 WHERE conversation_id = :conversation_id
                   AND user_id = :user_id

                 LIMIT 1'
            );
            $q->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
            $row = $q->fetch();
            if (!$row) {
                return null;
            }
            return strtolower((string) ($row['role'] ?? 'member')) ?: 'member';
        } catch (Throwable $exception) {
            return null;
        }
    }

    public function isGroupModerator(int $conversationId, int $userId): bool
    {
        return $this->resolveMemberRole($conversationId, $userId) === 'admin';
    }

    public function createGroupConversation(int $creatorId, string $name, string $description = '', array $memberIds = [], string $avatarUrl = ''): ?array
    {
        $this->ensureSchema();
        $name = trim($name);
        if ($creatorId <= 0 || $name === '') {
            return null;
        }

        $cleanMemberIds = [];
        foreach ($memberIds as $memberId) {
            $memberId = (int) $memberId;
            if ($memberId > 0 && $memberId !== $creatorId) {
                $cleanMemberIds[$memberId] = $memberId;
            }
        }

        try {
            $this->pdo->beginTransaction();
            $ins = $this->pdo->prepare(
                'INSERT INTO conversations (type, name, description, avatar_url, created_by, created_at, updated_at)
                 VALUES ("group", :name, :description, :avatar_url, :created_by, NOW(), NOW())'
            );
            $ins->execute([
                'name' => substr($name, 0, 255),
                'description' => trim($description) !== '' ? substr(trim($description), 0, 2000) : null,
                'avatar_url' => trim($avatarUrl) !== '' ? substr(trim($avatarUrl), 0, 1024) : null,
                'created_by' => $creatorId,
            ]);
            $conversationId = (int) $this->pdo->lastInsertId();

            $member = $this->pdo->prepare(
                'INSERT IGNORE INTO conversation_members (conversation_id, user_id, role)
                 VALUES (:conversation_id, :user_id, :role)'
            );
            $member->execute(['conversation_id' => $conversationId, 'user_id' => $creatorId, 'role' => 'admin']);
            foreach ($cleanMemberIds as $memberId) {
                $member->execute(['conversation_id' => $conversationId, 'user_id' => $memberId, 'role' => 'member']);
            }

            $this->insertSystemMessage($conversationId, $creatorId, 'Group chat created.');
            $this->pdo->commit();

            return [
                'id' => $conversationId,
                'conversation_id' => $conversationId,
                'group_id' => $conversationId,
                'group_chat_id' => $conversationId,
                'name' => substr($name, 0, 255),
                'description' => trim($description),
                'avatar_url' => trim($avatarUrl),
                'member_role' => 'admin',
                'members' => $this->getGroupMembers($conversationId),
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return null;
        }
    }

    public function insertSystemMessage(int $conversationId, int $senderId, string $body): ?int
    {
        if ($conversationId <= 0 || $senderId <= 0 || trim($body) === '') {
            return null;
        }

        try {
            $q = $this->pdo->prepare(
                'INSERT INTO messages (conversation_id, sender_id, message_type, body)
                 VALUES (:conversation_id, :sender_id, "system", :body)'
            );
            $q->execute([
                'conversation_id' => $conversationId,
                'sender_id' => $senderId,
                'body' => substr(trim($body), 0, 1000),
            ]);
            $this->touchConversation($conversationId);
            return (int) $this->pdo->lastInsertId();
        } catch (Throwable $exception) {
            return null;
        }
    }

    public function touchConversation(int $conversationId): void
    {
        if ($conversationId <= 0) {
            return;
        }
        $this->execQuietly('UPDATE conversations SET updated_at = NOW() WHERE id = ' . (int) $conversationId);
    }

    public function listForUser(int $userId): array
    {
        $this->syncLegacyThreads();
        if ($userId <= 0) {
            return ['private_conversations' => [], 'group_chats' => [], 'unread_total' => 0];
        }

        $privateConversations = $this->listPrivateConversations($userId);
        $groupChats = $this->listGroupConversations($userId);
        $unreadTotal = 0;
        foreach (array_merge($privateConversations, $groupChats) as $thread) {
            $unreadTotal += (int) ($thread['unread_count'] ?? 0);
        }

        return [
            'private_conversations' => $privateConversations,
            'group_chats' => $groupChats,
            'unread_total' => $unreadTotal,
        ];
    }

    private function listPrivateConversations(int $userId): array
    {
        try {
            $q = $this->pdo->prepare(
                'SELECT c.id,
                        c.updated_at,
                        c.created_at,
                        peer.id AS peer_id,
                        peer.first_name AS peer_first_name,
                        peer.last_name AS peer_last_name,
                        peer.avatar_url AS peer_avatar_url,
                        peer.role AS peer_role,
                        peer.country AS peer_country,
                        peer.exact_location AS peer_exact_location,
                        lm.id AS last_message_id,
                        lm.body AS last_message_body,
                        lm.message_type AS last_message_type,
                        lm.created_at AS last_message_created_at,
                        lm.sender_id AS last_message_sender_id,
                        COALESCE(unread.unread_count, 0) AS unread_count,
                        (
                            SELECT MAX(mr_peer.read_at)
                            FROM message_reads mr_peer
                            WHERE lm.id IS NOT NULL
                              AND mr_peer.message_id = lm.id
                              AND mr_peer.user_id = peer.id
                        ) AS last_message_seen_at
                 FROM conversations c
                 INNER JOIN conversation_members cm_me
                    ON cm_me.conversation_id = c.id
                   AND cm_me.user_id = :uid

                 INNER JOIN conversation_members cm_peer
                    ON cm_peer.conversation_id = c.id
                   AND cm_peer.user_id <> :uid

                 INNER JOIN users peer ON peer.id = cm_peer.user_id
                 LEFT JOIN messages lm ON lm.id = (
                    SELECT m2.id
                    FROM messages m2
                    WHERE m2.conversation_id = c.id
                    ORDER BY m2.created_at DESC, m2.id DESC
                    LIMIT 1
                 )
                 LEFT JOIN (
                    SELECT m.conversation_id, COUNT(*) AS unread_count
                    FROM messages m
                    LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid
                    WHERE m.sender_id <> :uid
                      AND mr.id IS NULL
                    GROUP BY m.conversation_id
                 ) unread ON unread.conversation_id = c.id
                 WHERE c.type = "private"

                 ORDER BY COALESCE(lm.created_at, c.updated_at, c.created_at) DESC
                 LIMIT 120'
            );
            $q->execute(['uid' => $userId]);

            $threads = [];
            foreach ((array) $q->fetchAll() as $row) {
                $peerId = (int) ($row['peer_id'] ?? 0);
                $threads[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'conversation_id' => (int) ($row['id'] ?? 0),
                    'last_message_at' => (string) ($row['last_message_created_at'] ?? ($row['updated_at'] ?? $row['created_at'] ?? '')),
                    'last_message_body' => (string) ($row['last_message_body'] ?? ''),
                    'last_message_type' => (string) ($row['last_message_type'] ?? 'text'),
                    'last_message_sender_id' => (int) ($row['last_message_sender_id'] ?? 0),
                    'last_message_seen_at' => (string) ($row['last_message_seen_at'] ?? ''),
                    'unread_count' => (int) ($row['unread_count'] ?? 0),
                    'peer' => [
                        'id' => $peerId,
                        'first_name' => (string) ($row['peer_first_name'] ?? ''),
                        'last_name' => (string) ($row['peer_last_name'] ?? ''),
                        'avatar_url' => (string) ($row['peer_avatar_url'] ?? ''),
                        'role' => (string) ($row['peer_role'] ?? 'user'),
                        'country' => (string) ($row['peer_country'] ?? ''),
                        'exact_location' => (string) ($row['peer_exact_location'] ?? ''),
                    ],
                ];
            }
            return $threads;
        } catch (Throwable $exception) {
            return [];
        }
    }

    private function listGroupConversations(int $userId): array
    {
        try {
            $q = $this->pdo->prepare(
                'SELECT c.id,
                        c.name,
                        c.description,
                        c.avatar_url,
                        c.updated_at,
                        c.created_at,
                        cm_me.role AS member_role,
                        lm.id AS last_message_id,
                        lm.body AS last_message_body,
                        lm.message_type AS last_message_type,
                        lm.created_at AS last_message_created_at,
                        lm.sender_id AS last_message_sender_id,
                        COALESCE(unread.unread_count, 0) AS unread_count
                 FROM conversations c
                 INNER JOIN conversation_members cm_me
                    ON cm_me.conversation_id = c.id
                   AND cm_me.user_id = :uid

                 LEFT JOIN messages lm ON lm.id = (
                    SELECT m2.id
                    FROM messages m2
                    WHERE m2.conversation_id = c.id
                    ORDER BY m2.created_at DESC, m2.id DESC
                    LIMIT 1
                 )
                 LEFT JOIN (
                    SELECT m.conversation_id, COUNT(*) AS unread_count
                    FROM messages m
                    LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid
                    WHERE m.sender_id <> :uid
                      AND mr.id IS NULL
                    GROUP BY m.conversation_id
                 ) unread ON unread.conversation_id = c.id
                 WHERE c.type = "group"

                 ORDER BY COALESCE(lm.created_at, c.updated_at, c.created_at) DESC
                 LIMIT 120'
            );
            $q->execute(['uid' => $userId]);

            $threads = [];
            foreach ((array) $q->fetchAll() as $row) {
                $conversationId = (int) ($row['id'] ?? 0);
                $threads[] = [
                    'id' => $conversationId,
                    'conversation_id' => $conversationId,
                    'group_id' => $conversationId,
                    'group_chat_id' => $conversationId,
                    'name' => (string) ($row['name'] ?? 'Group'),
                    'description' => (string) ($row['description'] ?? ''),
                    'avatar_url' => (string) ($row['avatar_url'] ?? ''),
                    'member_role' => (string) ($row['member_role'] ?? 'member'),
                    'last_message_at' => (string) ($row['last_message_created_at'] ?? ($row['updated_at'] ?? $row['created_at'] ?? '')),
                    'last_message_body' => (string) ($row['last_message_body'] ?? ''),
                    'last_message_type' => (string) ($row['last_message_type'] ?? 'text'),
                    'last_message_sender_id' => (int) ($row['last_message_sender_id'] ?? 0),
                    'unread_count' => (int) ($row['unread_count'] ?? 0),
                    'members' => $this->getGroupMembers($conversationId),
                ];
            }
            return $threads;
        } catch (Throwable $exception) {
            return [];
        }
    }

    public function getFriendIdsForUser(int $userId): array
    {
        if ($userId <= 0 || !$this->tableExists('friends')) {
            return [];
        }

        $hasPairColumns = $this->tableHasColumn('friends', 'user_one_id') && $this->tableHasColumn('friends', 'user_two_id');
        $hasNormalizedColumns = $this->tableHasColumn('friends', 'user_id') && $this->tableHasColumn('friends', 'friend_id');
        $clauses = [];
        if ($hasNormalizedColumns) {
            $clauses[] = 'SELECT CASE WHEN user_id = :uid THEN friend_id ELSE user_id END AS friend_id
                         FROM friends
                         WHERE status = "accepted" AND (user_id = :uid OR friend_id = :uid)';
        }
        if ($hasPairColumns) {
            $clauses[] = 'SELECT CASE WHEN user_one_id = :uid THEN user_two_id ELSE user_one_id END AS friend_id
                         FROM friends
                         WHERE status = "accepted" AND (user_one_id = :uid OR user_two_id = :uid)';
        }
        if (empty($clauses)) {
            return [];
        }

        try {
            $q = $this->pdo->prepare(implode(' UNION ', $clauses));
            $q->execute(['uid' => $userId]);
            $ids = [];
            foreach ((array) $q->fetchAll() as $row) {
                $friendId = (int) ($row['friend_id'] ?? 0);
                if ($friendId > 0 && $friendId !== $userId) {
                    $ids[$friendId] = $friendId;
                }
            }
            return array_values($ids);
        } catch (Throwable $exception) {
            return [];
        }
    }

    public function getFriendsForUser(int $userId): array
    {
        $friendIds = $this->getFriendIdsForUser($userId);
        if (empty($friendIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($friendIds), '?'));
            $q = $this->pdo->prepare(
                "SELECT id, first_name, last_name, avatar_url, email, role, last_seen, country, exact_location, phone, xp, bio
                 FROM users
                 WHERE id IN ({$placeholders})
                 ORDER BY first_name ASC, last_name ASC"
            );
            $q->execute($friendIds);
            return (array) $q->fetchAll();
        } catch (Throwable $exception) {
            return [];
        }
    }

    public function validateFriendMembers(int $currentUserId, array $memberIds): array
    {
        $allowed = array_flip($this->getFriendIdsForUser($currentUserId));
        $valid = [];
        foreach ($memberIds as $memberId) {
            $memberId = (int) $memberId;
            if ($memberId > 0 && isset($allowed[$memberId])) {
                $valid[$memberId] = $memberId;
            }
        }
        return array_values($valid);
    }

    public function leaveGroup(int $conversationId, int $userId): bool
    {
        if ($conversationId <= 0 || $userId <= 0) {
            return false;
        }
        try {
            $upd = $this->pdo->prepare(
                'DELETE FROM conversation_members WHERE conversation_id = :conversation_id AND user_id = :user_id'
            );
            $upd->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);
            if ((int) $upd->rowCount() <= 0) {
                return false;
            }
            $this->insertSystemMessage($conversationId, $userId, 'A member left the group.');
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function deleteGroup(int $conversationId, int $userId): bool
    {
        if ($conversationId <= 0 || $userId <= 0) {
            return false;
        }
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare(
                'DELETE FROM conversation_members WHERE conversation_id = :conversation_id'
            )->execute(['conversation_id' => $conversationId]);
            $this->pdo->prepare(
                'UPDATE conversations
                 SET name = CASE WHEN name LIKE "[Deleted] %" THEN name ELSE CONCAT("[Deleted] ", COALESCE(name, "Group")) END, updated_at = NOW()
                 WHERE id = :conversation_id'
            )->execute(['conversation_id' => $conversationId]);
            $this->pdo->prepare(
                'INSERT INTO messages (conversation_id, sender_id, message_type, body)
                 VALUES (:conversation_id, :sender_id, "system", :body)'
            )->execute([
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'body' => 'This group has been deleted by a moderator.',
            ]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    public function removeMember(int $conversationId, int $moderatorId, int $targetUserId): bool
    {
        if ($conversationId <= 0 || $moderatorId <= 0 || $targetUserId <= 0 || $moderatorId === $targetUserId) {
            return false;
        }
        try {
            $upd = $this->pdo->prepare(
                'DELETE FROM conversation_members WHERE conversation_id = :conversation_id AND user_id = :target_user_id'
            );
            $upd->execute(['conversation_id' => $conversationId, 'target_user_id' => $targetUserId]);
            if ((int) $upd->rowCount() <= 0) {
                return false;
            }
            $this->insertSystemMessage($conversationId, $moderatorId, 'A member has been removed by a moderator.');
            return true;
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function setMemberRole(int $conversationId, int $targetUserId, string $role): bool
    {
        $role = strtolower(trim($role));
        if (!in_array($role, ['member', 'admin'], true)) {
            $role = 'member';
        }
        if ($conversationId <= 0 || $targetUserId <= 0) {
            return false;
        }

        try {
            $q = $this->pdo->prepare(
                'UPDATE conversation_members
                 SET role = :role
                 WHERE conversation_id = :conversation_id
                   AND user_id = :user_id
                  '
            );
            $q->execute([
                'role' => $role,
                'conversation_id' => $conversationId,
                'user_id' => $targetUserId,
            ]);
            return (int) $q->rowCount() > 0;
        } catch (Throwable $exception) {
            return false;
        }
    }

}


