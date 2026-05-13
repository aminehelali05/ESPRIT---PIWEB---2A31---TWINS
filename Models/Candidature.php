<?php

class Candidature
{
    private $id;
    private $job_offer_id;
    private $offre_id;
    private $freelancer_id;
    private $message;
    private $created_at;
    private $status;

    public function __construct($job_offer_id = null, $freelancer_id = null, $message = null, $created_at = null, $id = null, $status = null)
    {
        $this->job_offer_id = $job_offer_id;
        $this->offre_id = $job_offer_id;
        $this->freelancer_id = $freelancer_id;
        $this->message = $message;
        $this->created_at = $created_at;
        $this->id = $id;
        $this->status = $status;
    }

    public function getId() { return $this->id; }
    public function getJobOfferId() { return $this->job_offer_id; }
    public function getOffreId() { return $this->offre_id; }
    public function getFreelancerId() { return $this->freelancer_id; }
    public function getMessage() { return $this->message; }
    public function getCreatedAt() { return $this->created_at; }
    public function getStatus() { return $this->status; }

    public function setId($value) { $this->id = $value; }
    public function setJobOfferId($value) { $this->job_offer_id = $value; $this->offre_id = $value; }
    public function setOffreId($value) { $this->setJobOfferId($value); }
    public function setFreelancerId($value) { $this->freelancer_id = $value; }
    public function setMessage($value) { $this->message = $value; }
    public function setCreatedAt($value) { $this->created_at = $value; }
    public function setStatus($value) { $this->status = $value; }
}
