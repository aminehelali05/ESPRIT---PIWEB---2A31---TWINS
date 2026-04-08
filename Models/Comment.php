<?php

class Comment
{
    private $id;
    private $post_id;
    private $user_id;
    private $contenu;
    private $created_at;

    public function __construct(
        $post_id = null,
        $user_id = null,
        $contenu = null,
        $created_at = null
    ) {
        $this->post_id = $post_id;
        $this->user_id = $user_id;
        $this->contenu = $contenu;
        $this->created_at = $created_at;
    }

    public function getId() { return $this->id; }
    public function getPostId() { return $this->post_id; }
    public function getUserId() { return $this->user_id; }
    public function getContenu() { return $this->contenu; }
    public function getCreatedAt() { return $this->created_at; }

    public function setId($id) { $this->id = $id; }
    public function setPostId($post_id) { $this->post_id = $post_id; }
    public function setUserId($user_id) { $this->user_id = $user_id; }
    public function setContenu($contenu) { $this->contenu = $contenu; }
    public function setCreatedAt($created_at) { $this->created_at = $created_at; }
}
