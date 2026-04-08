<?php

class Reaction
{
    private $id;
    private $user_id;
    private $post_id;
    private $type;
    private $created_at;

    public function __construct(
        $user_id = null,
        $post_id = null,
        $type = null,
        $created_at = null
    ) {
        $this->user_id = $user_id;
        $this->post_id = $post_id;
        $this->type = $type;
        $this->created_at = $created_at;
    }

    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getPostId() { return $this->post_id; }
    public function getType() { return $this->type; }
    public function getCreatedAt() { return $this->created_at; }

    public function setId($id) { $this->id = $id; }
    public function setUserId($user_id) { $this->user_id = $user_id; }
    public function setPostId($post_id) { $this->post_id = $post_id; }
    public function setType($type) { $this->type = $type; }
    public function setCreatedAt($created_at) { $this->created_at = $created_at; }
}
