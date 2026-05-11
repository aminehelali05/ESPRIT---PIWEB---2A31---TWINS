<?php

class Marketplace
{
    private int $id = 0;
    private int $userId = 0;
    private string $title = '';
    private string $description = '';
    private float $price = 0.0;
    private string $type = 'digital';
    private ?int $categoryId = null;
    private string $thumbnailUrl = '';
    private string $status = 'active';
    private string $createdAt = '';
    private string $updatedAt = '';

    public function __construct(array $row = [])
    {
        $this->id = (int) ($row['id'] ?? 0);
        $this->userId = (int) ($row['user_id'] ?? 0);
        $this->title = (string) ($row['title'] ?? '');
        $this->description = (string) ($row['description'] ?? '');
        $this->price = (float) ($row['price'] ?? 0.0);
        $this->type = (string) ($row['type'] ?? 'digital');
        $this->categoryId = isset($row['category_id']) ? (int) $row['category_id'] : null;
        $this->thumbnailUrl = (string) ($row['thumbnail_url'] ?? '');
        $this->status = (string) ($row['status'] ?? 'active');
        $this->createdAt = (string) ($row['created_at'] ?? '');
        $this->updatedAt = (string) ($row['updated_at'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'type' => $this->type,
            'category_id' => $this->categoryId,
            'thumbnail_url' => $this->thumbnailUrl,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): string { return $this->description; }
    public function getPrice(): float { return $this->price; }
    public function getType(): string { return $this->type; }
    public function getCategoryId(): ?int { return $this->categoryId; }
    public function getThumbnailUrl(): string { return $this->thumbnailUrl; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }
}
