<?php

class Brainstorming
{
    private $id;
    private $user_id;
    private $title;
    private $description;
    private $topic;
    private $type;
    private $status;
    private $created_at;
    private $updated_at;

    public function __construct(
        $user_id = null, $title = null, $description = null, $topic = 'General', $status = 'EN_ATTENTE', $type = 'Standard'
    ) {
        $this->user_id = $user_id;
        $this->title = $title;
        $this->description = $description;
        $this->topic = $topic;
        $this->type = $type;
        $this->status = $status;
    }

    // --- GETTERS ---
    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getTopic() { return $this->topic; }
    public function getType() { return $this->type; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    // --- SETTERS ---
    public function setId($id) { $this->id = $id; }
    public function setUserId($v) { $this->user_id = $v; }
    public function setTitle($v) { $this->title = $v; }
    public function setDescription($v) { $this->description = $v; }
    public function setTopic($v) { $this->topic = $v; }
    public function setType($v) { $this->type = $v; }
    public function setStatus($v) { $this->status = $v; }
    public function setCreatedAt($v) { $this->created_at = $v; }
    public function setUpdatedAt($v) { $this->updated_at = $v; }
}
