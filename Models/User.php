<?php

class User
{
    private $table;
    private $lastError;
    private $colCache = [];

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
        $this->table = null;
        $this->lastError = null;
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
<<<<<<< Updated upstream
=======
    public function getUpdatedAt() { return $this->updated_at; }
    public function getTableName(): string { return (string) $this->table; }
    public function getLastError() { return $this->lastError; }
    public function getColumnCache(): array { return (array) $this->colCache; }
    public function hasColumnInCache(string $column): bool { return array_key_exists($column, $this->colCache); }
    public function getColumnFromCache(string $column): bool { return (bool) ($this->colCache[$column] ?? false); }
>>>>>>> Stashed changes

    public function setId($id) { $this->id = $id; }
<<<<<<< Updated upstream
    public function setFirstName($first_name) { $this->first_name = $first_name; }
    public function setLastName($last_name) { $this->last_name = $last_name; }
    public function setEmail($email) { $this->email = $email; }
    public function setPassword($password) { $this->password = $password; }
    public function setPhone($phone) { $this->phone = $phone; }
    public function setRole($role) { $this->role = $role; }
    public function setStatus($status) { $this->status = $status; }
    public function setCreatedAt($created_at) { $this->created_at = $created_at; }
=======
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
    public function setTableName(string $v) { $this->table = $v; }
    public function setLastError($v) { $this->lastError = $v; }
    public function setColumnInCache(string $column, bool $exists) { $this->colCache[$column] = $exists; }
    public function clearColumnCache() { $this->colCache = []; }
>>>>>>> Stashed changes

    public function getFullName()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
