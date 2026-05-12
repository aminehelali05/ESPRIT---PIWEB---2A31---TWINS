<?php

class ResourceItem
{
    private $id;
    private $event_id;
    private $user_id;
    private $type;
    private $title;
    private $description;
    private $status;
    private $created_at;
    private $updated_at;

    public function __construct(
        $user_id = null, $type = null, $title = null, $description = null, $status = 'active', $event_id = null
    ) {
        $this->user_id = $user_id;
        $this->type = $type;
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
        $this->event_id = $event_id;
    }

    // --- GETTERS ---
    public function getId() { return $this->id; }
    public function getEventId() { return $this->event_id; }
    public function getUserId() { return $this->user_id; }
    public function getType() { return $this->type; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    // --- SETTERS ---
    public function setId($id) { $this->id = $id; }
    public function setEventId($v) { $this->event_id = $v; }
    public function setUserId($v) { $this->user_id = $v; }
    public function setType($v) { $this->type = $v; }
    public function setTitle($v) { $this->title = $v; }
    public function setDescription($v) { $this->description = $v; }
    public function setStatus($v) { $this->status = $v; }
    public function setCreatedAt($v) { $this->created_at = $v; }
    public function setUpdatedAt($v) { $this->updated_at = $v; }
}
