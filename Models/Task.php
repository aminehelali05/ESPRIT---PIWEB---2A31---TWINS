<?php

class Task
{
    private $id;
    private $project_id;
    private $title;
    private $description;
    private $status;
    private $deadline;

    public function __construct($project_id = null, $title = null, $description = null, $status = null, $deadline = null, $id = null)
    {
        $this->project_id = $project_id;
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
        $this->deadline = $deadline;
        $this->id = $id;
    }

    public function getId() { return $this->id; }
    public function getProjectId() { return $this->project_id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getStatus() { return $this->status; }
    public function getDeadline() { return $this->deadline; }

    public function setId($value) { $this->id = $value; }
    public function setProjectId($value) { $this->project_id = $value; }
    public function setTitle($value) { $this->title = $value; }
    public function setDescription($value) { $this->description = $value; }
    public function setStatus($value) { $this->status = $value; }
    public function setDeadline($value) { $this->deadline = $value; }
}
