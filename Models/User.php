<?php

class User
{
    private $id;
    private $first_name;
    private $last_name;
    private $email;
    private $password;
    private $phone;
    private $role;
    private $status;
    private $created_at;

    public function __construct(
        $first_name = null,
        $last_name = null,
        $email = null,
        $password = null,
        $phone = null,
        $role = null,
        $status = 1,
        $created_at = null
    ) {
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->email = $email;
        $this->password = $password;
        $this->phone = $phone;
        $this->role = $role;
        $this->status = $status;
        $this->created_at = $created_at;
    }

    public function getId() { return $this->id; }
    public function getFirstName() { return $this->first_name; }
    public function getLastName() { return $this->last_name; }
    public function getEmail() { return $this->email; }
    public function getPassword() { return $this->password; }
    public function getPhone() { return $this->phone; }
    public function getRole() { return $this->role; }
    public function getStatus() { return $this->status; }
    public function getCreatedAt() { return $this->created_at; }

    public function setId($id) { $this->id = $id; }
    public function setFirstName($first_name) { $this->first_name = $first_name; }
    public function setLastName($last_name) { $this->last_name = $last_name; }
    public function setEmail($email) { $this->email = $email; }
    public function setPassword($password) { $this->password = $password; }
    public function setPhone($phone) { $this->phone = $phone; }
    public function setRole($role) { $this->role = $role; }
    public function setStatus($status) { $this->status = $status; }
    public function setCreatedAt($created_at) { $this->created_at = $created_at; }

    public function getFullName()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
