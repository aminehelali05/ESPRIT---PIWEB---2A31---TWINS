<?php

class Idea
{
    private $id;
    private $brainstorming_id;
    private $user_id;
    private $content;
    private $created_at;
    private $updated_at;

    public function __construct(
        $brainstorming_id = null, $user_id = null, $content = null
    ) {
        $this->brainstorming_id = $brainstorming_id;
        $this->user_id = $user_id;
        $this->content = $content;
    }

    // --- GETTERS ---
    public function getId() { return $this->id; }
    public function getBrainstormingId() { return $this->brainstorming_id; }
    public function getUserId() { return $this->user_id; }
    public function getContent() { return $this->content; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    // --- SETTERS ---
    public function setId($id) { $this->id = $id; }
    public function setBrainstormingId($v) { $this->brainstorming_id = $v; }
    public function setUserId($v) { $this->user_id = $v; }
    public function setContent($v) { $this->content = $v; }
    public function setCreatedAt($v) { $this->created_at = $v; }
    public function setUpdatedAt($v) { $this->updated_at = $v; }
}
