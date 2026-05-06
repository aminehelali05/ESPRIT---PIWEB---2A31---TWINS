<?php

class Rules
{
    private $id;
    private $contract_id;
    private $rule_type;
    private $title;
    private $description;
    private $due_date;
    private $penalty;
    private $sort_order;
    private $created_at;
    private $updated_at;

    public function __construct($contract_id = null, $rule_type = null, $title = null, $description = null, $due_date = null, $penalty = null, $sort_order = null, $created_at = null, $updated_at = null, $id = null)
    {
        $this->contract_id = $contract_id;
        $this->rule_type = $rule_type;
        $this->title = $title;
        $this->description = $description;
        $this->due_date = $due_date;
        $this->penalty = $penalty;
        $this->sort_order = $sort_order;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->id = $id;
    }

    public function getId() { return $this->id; }
    public function getContractId() { return $this->contract_id; }
    public function getRuleType() { return $this->rule_type; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getDueDate() { return $this->due_date; }
    public function getPenalty() { return $this->penalty; }
    public function getSortOrder() { return $this->sort_order; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    public function getTerms() { return $this->description; }
    public function getDeadline() { return $this->due_date; }
    public function getPaymentTerms() { return $this->rule_type === 'payment' ? $this->description : null; }
    public function getPenalties() { return $this->penalty; }

    public function setId($value) { $this->id = $value; }
    public function setContractId($value) { $this->contract_id = $value; }
    public function setRuleType($value) { $this->rule_type = $value; }
    public function setTitle($value) { $this->title = $value; }
    public function setDescription($value) { $this->description = $value; }
    public function setDueDate($value) { $this->due_date = $value; }
    public function setPenalty($value) { $this->penalty = $value; }
    public function setSortOrder($value) { $this->sort_order = $value; }
    public function setCreatedAt($value) { $this->created_at = $value; }
    public function setUpdatedAt($value) { $this->updated_at = $value; }

    public function setTerms($value) { $this->description = $value; }
    public function setDeadline($value) { $this->due_date = $value; }
    public function setPaymentTerms($value) { if ($this->rule_type === 'payment') { $this->description = $value; } }
    public function setPenalties($value) { $this->penalty = $value; }
}
