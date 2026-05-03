<?php

class Idea
{
    private $id;
    private $brainstorming_id;
    private $user_id;
    private $title;
    private $content;
    private $type;
    private $status;
    private $created_at;
    private $updated_at;

    public function __construct(
        $brainstorming_id = null, $user_id = null, $title = null, $content = null, $status = 'PROPOSED', $type = 'Standard'
    ) {
        $this->brainstorming_id = $brainstorming_id;
        $this->user_id = $user_id;
        $this->title = $title;
        $this->content = $content;
        $this->type = $type;
        $this->status = $status;
    }

    // --- GETTERS ---
    public function getId() { return $this->id; }
    public function getBrainstormingId() { return $this->brainstorming_id; }
    public function getUserId() { return $this->user_id; }
    public function getTitle() { return $this->title; }
    public function getContent() { return $this->content; }
    public function getType() { return $this->type; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    // --- SETTERS ---
    public function setId($id) { $this->id = $id; }
    public function setBrainstormingId($v) { $this->brainstorming_id = $v; }
    public function setUserId($v) { $this->user_id = $v; }
    public function setTitle($v) { $this->title = $v; }
    public function setContent($v) { $this->content = $v; }
    public function setType($v) { $this->type = $v; }
    public function setStatus($v) { $this->status = $v; }
    public function setCreatedAt($v) { $this->created_at = $v; }
    public function setUpdatedAt($v) { $this->updated_at = $v; }
}
