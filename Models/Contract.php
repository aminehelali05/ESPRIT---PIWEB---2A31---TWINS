<?php

class Contract
{
    private $id;
    private $job_offer_id;
    private $freelancer_id;
    private $client_id;
    private $terms;
    private $status;
    private $amount;
    private $signed_at;
    private $starts_at;
    private $ends_at;
    private $created_by_client_id;
    private $created_at;
    private $updated_at;
    private $rules = null;

    public function __construct($job_offer_id = null, $freelancer_id = null, $client_id = null, $terms = null, $status = null, $amount = null, $signed_at = null, $starts_at = null, $ends_at = null, $created_by_client_id = null, $created_at = null, $updated_at = null, $id = null)
    {
        $this->job_offer_id = $job_offer_id;
        $this->freelancer_id = $freelancer_id;
        $this->client_id = $client_id;
        $this->terms = $terms;
        $this->status = $status;
        $this->amount = $amount;
        $this->signed_at = $signed_at;
        $this->starts_at = $starts_at;
        $this->ends_at = $ends_at;
        $this->created_by_client_id = $created_by_client_id;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->id = $id;
    }

    public function getId() { return $this->id; }
    public function getJobOfferId() { return $this->job_offer_id; }
    public function getFreelancerId() { return $this->freelancer_id; }
    public function getClientId() { return $this->client_id; }
    public function getTerms() { return $this->terms; }
    public function getStatus() { return $this->status; }
    public function getAmount() { return $this->amount; }
    public function getSignedAt() { return $this->signed_at; }
    public function getStartsAt() { return $this->starts_at; }
    public function getEndsAt() { return $this->ends_at; }
    public function getCreatedByClientId() { return $this->created_by_client_id; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
    public function getRules() { return $this->rules; }

    public function setId($value) { $this->id = $value; }
    public function setJobOfferId($value) { $this->job_offer_id = $value; }
    public function setFreelancerId($value) { $this->freelancer_id = $value; }
    public function setClientId($value) { $this->client_id = $value; }
    public function setTerms($value) { $this->terms = $value; }
    public function setStatus($value) { $this->status = $value; }
    public function setAmount($value) { $this->amount = $value; }
    public function setSignedAt($value) { $this->signed_at = $value; }
    public function setStartsAt($value) { $this->starts_at = $value; }
    public function setEndsAt($value) { $this->ends_at = $value; }
    public function setCreatedByClientId($value) { $this->created_by_client_id = $value; }
    public function setCreatedAt($value) { $this->created_at = $value; }
    public function setUpdatedAt($value) { $this->updated_at = $value; }
    public function setRules($value) { $this->rules = $value; }
}
