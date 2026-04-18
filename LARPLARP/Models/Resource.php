<?php

class ResourceItem
{
    private $id;
    private $type;
    private $title;
    private $description;
    private $status;
    private $created_at;
    private $updated_at;

    public function __construct(
        $type = null, $title = null, $description = null, $status = 'active'
    ) {
        $this->type = $type;
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
    }

    // --- GETTERS ---
    public function getId() { return $this->id; }
    public function getType() { return $this->type; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    // --- SETTERS ---
    public function setId($id) { $this->id = $id; }
    public function setType($v) { $this->type = $v; }
    public function setTitle($v) { $this->title = $v; }
    public function setDescription($v) { $this->description = $v; }
    public function setStatus($v) { $this->status = $v; }
    public function setCreatedAt($v) { $this->created_at = $v; }
    public function setUpdatedAt($v) { $this->updated_at = $v; }
}
