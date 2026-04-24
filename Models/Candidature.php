<?php

class Candidature
{
    private $id;
    private $offre_id;
    private $freelancer_id;
    private $message;
    private $created_at;

    public function __construct($offre_id = null, $freelancer_id = null, $message = null, $created_at = null, $id = null)
    {
        $this->offre_id = $offre_id;
        $this->freelancer_id = $freelancer_id;
        $this->message = $message;
        $this->created_at = $created_at;
        $this->id = $id;
    }

    public function getId() { return $this->id; }
    public function getOffreId() { return $this->offre_id; }
    public function getFreelancerId() { return $this->freelancer_id; }
    public function getMessage() { return $this->message; }
    public function getCreatedAt() { return $this->created_at; }

    public function setId($value) { $this->id = $value; }
    public function setOffreId($value) { $this->offre_id = $value; }
    public function setFreelancerId($value) { $this->freelancer_id = $value; }
    public function setMessage($value) { $this->message = $value; }
    public function setCreatedAt($value) { $this->created_at = $value; }
}
