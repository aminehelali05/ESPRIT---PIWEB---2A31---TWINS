<?php

class Event
{
    private $id;
    private $user_id;
    private $title;
    private $description;
    private $event_date;
    private $location;
    private $category;
    private $status;
    private $created_at;
    private $updated_at;

    public function __construct(
        $user_id = null, $title = null, $description = null, $event_date = null,
        $location = null, $category = null, $status = 'EN_ATTENTE'
    ) {
        $this->user_id = $user_id;
        $this->title = $title;
        $this->description = $description;
        $this->event_date = $event_date;
        $this->location = $location;
        $this->category = $category;
        $this->status = $status;
    }

    // --- GETTERS ---
    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getEventDate() { return $this->event_date; }
    public function getLocation() { return $this->location; }
    public function getCategory() { return $this->category; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    // --- SETTERS ---
    public function setId($id) { $this->id = $id; }
    public function setUserId($v) { $this->user_id = $v; }
    public function setTitle($v) { $this->title = $v; }
    public function setDescription($v) { $this->description = $v; }
    public function setEventDate($v) { $this->event_date = $v; }
    public function setLocation($v) { $this->location = $v; }
    public function setCategory($v) { $this->category = $v; }
    public function setStatus($v) { $this->status = $v; }
    public function setCreatedAt($v) { $this->created_at = $v; }
    public function setUpdatedAt($v) { $this->updated_at = $v; }
}
