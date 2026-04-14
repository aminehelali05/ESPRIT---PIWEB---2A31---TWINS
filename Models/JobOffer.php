<?php

class JobOffer
{
    private $id;
    private $title;
    private $description;
    private $budget;
    private $skills_required;
    private $location;
    private $experience_level;
    private $project_type;
    private $status;
    private $deadline_at;
    private $client_id;
    private $created_at;
    private $updated_at;

    public function getId() { return $this->id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getBudget() { return $this->budget; }
    public function getSkillsRequired() { return $this->skills_required; }
    public function getLocation() { return $this->location; }
    public function getExperienceLevel() { return $this->experience_level; }
    public function getProjectType() { return $this->project_type; }
    public function getStatus() { return $this->status; }
    public function getDeadlineAt() { return $this->deadline_at; }
    public function getClientId() { return $this->client_id; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    public function setId($value) { $this->id = $value; }
    public function setTitle($value) { $this->title = $value; }
    public function setDescription($value) { $this->description = $value; }
    public function setBudget($value) { $this->budget = $value; }
    public function setSkillsRequired($value) { $this->skills_required = $value; }
    public function setLocation($value) { $this->location = $value; }
    public function setExperienceLevel($value) { $this->experience_level = $value; }
    public function setProjectType($value) { $this->project_type = $value; }
    public function setStatus($value) { $this->status = $value; }
    public function setDeadlineAt($value) { $this->deadline_at = $value; }
    public function setClientId($value) { $this->client_id = $value; }
    public function setCreatedAt($value) { $this->created_at = $value; }
    public function setUpdatedAt($value) { $this->updated_at = $value; }
}
