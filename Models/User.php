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
    private $avatar_url;
    private $badge;
    private $country;
    private $bio;
    private $title;
    private $skills;
    private $xp;
    private $is_blocked;
    private $last_seen;
    private $face_descriptor;
    private $face_images_path;
    private $face_enrolled;
    private $face_enrolled_at;
    private $created_at;
    private $updated_at;

    public function __construct(
        $first_name = null, $last_name = null, $email = null, $password = null,
        $phone = null, $role = 'user', $status = 1, $created_at = null
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

    // --- GETTERS ---
    public function getId() { return $this->id; }
    public function getFirstName() { return $this->first_name; }
    public function getLastName() { return $this->last_name; }
    public function getEmail() { return $this->email; }
    public function getPassword() { return $this->password; }
    public function getPhone() { return $this->phone; }
    public function getRole() { return $this->role; }
    public function getStatus() { return $this->status; }
    public function getAvatarUrl() { return $this->avatar_url; }
    public function getBadge() { return $this->badge; }
    public function getCountry() { return $this->country; }
    public function getBio() { return $this->bio; }
    public function getTitle() { return $this->title; }
    public function getSkills() { return $this->skills; }
    public function getSkillsArray() { return $this->skills ? explode(',', $this->skills) : []; }
    public function getXp() { return $this->xp ?? 0; }
    public function getIsBlocked() { return $this->is_blocked ?? 0; }
    public function getLastSeen() { return $this->last_seen; }
    public function getFaceDescriptor() { return $this->face_descriptor; }
    public function getFaceImagesPath() { return $this->face_images_path; }
    public function getFaceEnrolled() { return $this->face_enrolled ?? 0; }
    public function getFaceEnrolledAt() { return $this->face_enrolled_at; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    // --- SETTERS ---
    public function setId($id) { $this->id = $id; }
    public function setFirstName($v) { $this->first_name = $v; }
    public function setLastName($v) { $this->last_name = $v; }
    public function setEmail($v) { $this->email = $v; }
    public function setPassword($v) { $this->password = $v; }
    public function setPhone($v) { $this->phone = $v; }
    public function setRole($v) { $this->role = $v; }
    public function setStatus($v) { $this->status = $v; }
    public function setAvatarUrl($v) { $this->avatar_url = $v; }
    public function setBadge($v) { $this->badge = $v; }
    public function setCountry($v) { $this->country = $v; }
    public function setBio($v) { $this->bio = $v; }
    public function setTitle($v) { $this->title = $v; }
    public function setSkills($v) { $this->skills = $v; }
    public function setXp($v) { $this->xp = $v; }
    public function setIsBlocked($v) { $this->is_blocked = $v; }
    public function setLastSeen($v) { $this->last_seen = $v; }
    public function setFaceDescriptor($v) { $this->face_descriptor = $v; }
    public function setFaceImagesPath($v) { $this->face_images_path = $v; }
    public function setFaceEnrolled($v) { $this->face_enrolled = $v; }
    public function setFaceEnrolledAt($v) { $this->face_enrolled_at = $v; }
    public function setCreatedAt($v) { $this->created_at = $v; }
    public function setUpdatedAt($v) { $this->updated_at = $v; }

    public function getFullName() {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitials() {
        $i = strtoupper(substr($this->first_name ?? '', 0, 1) . substr($this->last_name ?? '', 0, 1));
        return $i ?: '??';
    }
}
