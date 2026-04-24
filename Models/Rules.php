<?php

class Rules
{
    private $id;
    private $contract_id;
    private $terms;
    private $deadline;
    private $payment_terms;
    private $penalties;

    public function __construct($contract_id = null, $terms = null, $deadline = null, $payment_terms = null, $penalties = null, $id = null)
    {
        $this->contract_id = $contract_id;
        $this->terms = $terms;
        $this->deadline = $deadline;
        $this->payment_terms = $payment_terms;
        $this->penalties = $penalties;
        $this->id = $id;
    }

    public function getId() { return $this->id; }
    public function getContractId() { return $this->contract_id; }
    public function getTerms() { return $this->terms; }
    public function getDeadline() { return $this->deadline; }
    public function getPaymentTerms() { return $this->payment_terms; }
    public function getPenalties() { return $this->penalties; }

    public function setId($value) { $this->id = $value; }
    public function setContractId($value) { $this->contract_id = $value; }
    public function setTerms($value) { $this->terms = $value; }
    public function setDeadline($value) { $this->deadline = $value; }
    public function setPaymentTerms($value) { $this->payment_terms = $value; }
    public function setPenalties($value) { $this->penalties = $value; }
}
