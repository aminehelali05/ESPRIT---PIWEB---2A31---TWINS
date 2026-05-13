<?php

class Message
{
    private int $id = 0;
    private int $conversationId = 0;
    private int $senderId = 0;
    private string $body = '';
    private string $messageType = 'text';
    private string $mediaUrl = '';
    private $metadata = null;
    private int $isEdited = 0;
    private int $isDeleted = 0;
    private string $createdAt = '';
    private string $updatedAt = '';

    public function __construct(array $row = [])
    {
        $this->id = (int) ($row['id'] ?? 0);
        $this->conversationId = (int) ($row['conversation_id'] ?? 0);
        $this->senderId = (int) ($row['sender_id'] ?? 0);
        $this->body = (string) ($row['body'] ?? '');
        $this->messageType = (string) ($row['message_type'] ?? 'text');
        $this->mediaUrl = (string) ($row['media_url'] ?? '');
        $this->metadata = $row['metadata'] ?? null;
        $this->isEdited = (int) ($row['is_edited'] ?? 0);
        $this->isDeleted = (int) ($row['is_deleted'] ?? 0);
        $this->createdAt = (string) ($row['created_at'] ?? '');
        $this->updatedAt = (string) ($row['updated_at'] ?? '');
    }

    public function getId(): int { return $this->id; }
    public function getConversationId(): int { return $this->conversationId; }
    public function getSenderId(): int { return $this->senderId; }
    public function getBody(): string { return $this->body; }
    public function getMessageType(): string { return $this->messageType; }
    public function getMediaUrl(): string { return $this->mediaUrl; }
    public function getMetadata() { return $this->metadata; }
    public function getIsEdited(): int { return $this->isEdited; }
    public function getIsDeleted(): int { return $this->isDeleted; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }
}
