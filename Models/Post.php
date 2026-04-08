<?php

class Post
{
    private $id;
    private $user_id;
    private $contenu;
    private $media_url;
    private $created_at;
    private $updated_at;

    public function __construct(
        $user_id = null,
        $contenu = null,
        $media_url = null,
        $created_at = null,
        $updated_at = null
    ) {
        $this->user_id = $user_id;
        $this->contenu = $contenu;
        $this->media_url = $media_url;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
    }

    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getContenu() { return $this->contenu; }
    public function getMediaUrl() { return $this->media_url; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    public function setId($id) { $this->id = $id; }
    public function setUserId($user_id) { $this->user_id = $user_id; }
    public function setContenu($contenu) { $this->contenu = $contenu; }
    public function setMediaUrl($media_url) { $this->media_url = $media_url; }
    public function setCreatedAt($created_at) { $this->created_at = $created_at; }
    public function setUpdatedAt($updated_at) { $this->updated_at = $updated_at; }
}
