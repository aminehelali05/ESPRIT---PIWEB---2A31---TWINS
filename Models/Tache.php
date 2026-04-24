<?php

class Tache
{
    private $id;
    private $projet_id;
    private $title;
    private $description;
    private $status;
    private $deadline;

    public function __construct($projet_id = null, $title = null, $description = null, $status = null, $deadline = null, $id = null)
    {
        $this->projet_id = $projet_id;
        $this->title = $title;
        $this->description = $description;
        $this->status = $status;
        $this->deadline = $deadline;
        $this->id = $id;
    }

    public function getId() { return $this->id; }
    public function getProjetId() { return $this->projet_id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getStatus() { return $this->status; }
    public function getDeadline() { return $this->deadline; }

    public function setId($value) { $this->id = $value; }
    public function setProjetId($value) { $this->projet_id = $value; }
    public function setTitle($value) { $this->title = $value; }
    public function setDescription($value) { $this->description = $value; }
    public function setStatus($value) { $this->status = $value; }
    public function setDeadline($value) { $this->deadline = $value; }
}
