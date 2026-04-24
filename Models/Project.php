<?php

class Project
{
    private $id;
    private $title;
    private $description;
    private $cover_image;
    private $short_description;
    private $technologies;
    private $status;
    private $progress_percent;
    private $budget;
    private $due_date;
    private $owner_id;
    private $visibility;
    private $created_at;
    private $updated_at;
    private $taches = [];

    public function __construct($title = null, $description = null, $cover_image = null, $short_description = null, $technologies = null, $status = null, $progress_percent = null, $budget = null, $due_date = null, $owner_id = null, $visibility = null, $created_at = null, $updated_at = null, $id = null)
    {
        $this->title = $title;
        $this->description = $description;
        $this->cover_image = $cover_image;
        $this->short_description = $short_description;
        $this->technologies = $technologies;
        $this->status = $status;
        $this->progress_percent = $progress_percent;
        $this->budget = $budget;
        $this->due_date = $due_date;
        $this->owner_id = $owner_id;
        $this->visibility = $visibility;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->id = $id;
    }

    public function getId() { return $this->id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getCoverImage() { return $this->cover_image; }
    public function getShortDescription() { return $this->short_description; }
    public function getTechnologies() { return $this->technologies; }
    public function getStatus() { return $this->status; }
    public function getProgressPercent() { return $this->progress_percent; }
    public function getBudget() { return $this->budget; }
    public function getDueDate() { return $this->due_date; }
    public function getOwnerId() { return $this->owner_id; }
    public function getVisibility() { return $this->visibility; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
    public function getTaches() { return is_array($this->taches) ? $this->taches : []; }

    public function setId($value) { $this->id = $value; }
    public function setTitle($value) { $this->title = $value; }
    public function setDescription($value) { $this->description = $value; }
    public function setCoverImage($value) { $this->cover_image = $value; }
    public function setShortDescription($value) { $this->short_description = $value; }
    public function setTechnologies($value) { $this->technologies = $value; }
    public function setStatus($value) { $this->status = $value; }
    public function setProgressPercent($value) { $this->progress_percent = $value; }
    public function setBudget($value) { $this->budget = $value; }
    public function setDueDate($value) { $this->due_date = $value; }
    public function setOwnerId($value) { $this->owner_id = $value; }
    public function setVisibility($value) { $this->visibility = $value; }
    public function setCreatedAt($value) { $this->created_at = $value; }
    public function setUpdatedAt($value) { $this->updated_at = $value; }
    public function setTaches($value) { $this->taches = is_array($value) ? $value : []; }
}
