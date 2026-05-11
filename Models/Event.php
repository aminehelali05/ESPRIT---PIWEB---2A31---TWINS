<?php

class Event
{
    private $id;
    private $user_id;
    private $title;
    private $description;
    private $start_date;
    private $end_date;
    private $location;
    private $category;
    private $status;
    private $capacite_max;
    private $nb_inscrits;
    private $statut_inscription;
    private $qr_code;
    private $created_at;
    private $updated_at;

    public function __construct(
        $user_id = null, $title = null, $description = null, $start_date = null, $end_date = null,
        $location = null, $category = null, $status = 'EN_ATTENTE',
        $capacite_max = 0, $nb_inscrits = 0, $statut_inscription = 'OUVERT', $qr_code = null
    ) {
        $this->user_id = $user_id;
        $this->title = $title;
        $this->description = $description;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->location = $location;
        $this->category = $category;
        $this->status = $status;
        $this->capacite_max = $capacite_max;
        $this->nb_inscrits = $nb_inscrits;
        $this->statut_inscription = $statut_inscription;
        $this->qr_code = $qr_code;
    }

    // --- GETTERS ---
    public function getId() { return $this->id; }
    public function getUserId() { return $this->user_id; }
    public function getTitle() { return $this->title; }
    public function getDescription() { return $this->description; }
    public function getStartDate() { return $this->start_date; }
    public function getEndDate() { return $this->end_date; }
    public function getLocation() { return $this->location; }
    public function getCategory() { return $this->category; }
    public function getStatus() { return $this->status; }
    public function getCapaciteMax() { return $this->capacite_max; }
    public function getNbInscrits() { return $this->nb_inscrits; }
    public function getStatutInscription() { return $this->statut_inscription; }
    public function getQrCode() { return $this->qr_code; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    // --- SETTERS ---
    public function setId($id) { $this->id = $id; }
    public function setUserId($v) { $this->user_id = $v; }
    public function setTitle($v) { $this->title = $v; }
    public function setDescription($v) { $this->description = $v; }
    public function setStartDate($v) { $this->start_date = $v; }
    public function setEndDate($v) { $this->end_date = $v; }
    public function setLocation($v) { $this->location = $v; }
    public function setCategory($v) { $this->category = $v; }
    public function setStatus($v) { $this->status = $v; }
    public function setCapaciteMax($v) { $this->capacite_max = $v; }
    public function setNbInscrits($v) { $this->nb_inscrits = $v; }
    public function setStatutInscription($v) { $this->statut_inscription = $v; }
    public function setQrCode($v) { $this->qr_code = $v; }
    public function setCreatedAt($v) { $this->created_at = $v; }
    public function setUpdatedAt($v) { $this->updated_at = $v; }
}
