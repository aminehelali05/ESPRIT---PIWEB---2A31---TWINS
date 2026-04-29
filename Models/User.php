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
    private $avatar_url;
    private $country;
    private $exact_location;
    private $latitude;
    private $longitude;
    private $bio;
    private $skills;
    private $xp;
    private $is_blocked;
    private $last_seen;
    private $face_images_path;
    private $badge;
    private $title;
    private $face_descriptor;
    private $face_enrolled;
    private $face_enrolled_at;
    private $created_at;
    private $updated_at;
    private $invitation_code;
    private $referred_by;
    private $linked_accounts = [];
    private $friends = [];
    private $friend_requests = [];
    private $private_conversations = [];
    private $group_chats = [];
    private $conversations = [];
    private $messages = [];
    private $stories = [];
    public const BIO_MAX_LENGTH = 1000;


    public function __construct(
        $first_name = null, $last_name = null, $email = null, $password = null,
        $phone = null, $role = 'user', $status = 1, $created_at = null
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
    public function getCountry() { return $this->country; }
    public function getExactLocation() { return $this->exact_location; }
    public function getLatitude() { return $this->latitude; }
    public function getLongitude() { return $this->longitude; }
    public function getBio() { return $this->bio; }
    public function getSkills() { return $this->skills; }
    public function getSkillsArray() { return $this->skills ? explode(',', $this->skills) : []; }
    public function getXp() { return $this->xp ?? 0; }
    public function getIsBlocked() { return $this->is_blocked ?? 0; }
    public function getLastSeen() { return $this->last_seen; }
    public function getFaceImagesPath() { return $this->face_images_path; }
    public function getBadge() { return $this->badge; }
    public function getTitle() { return $this->title; }
    public function getFaceDescriptor() { return $this->face_descriptor; }
    public function getFaceEnrolled() { return $this->face_enrolled ?? 0; }
    public function getFaceEnrolledAt() { return $this->face_enrolled_at; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
    public function getInvitationCode() { return $this->invitation_code; }
    public function getReferredBy() { return $this->referred_by; }
    public function getLinkedAccounts() { return is_array($this->linked_accounts) ? $this->linked_accounts : []; }
    public function getFriends() { return is_array($this->friends) ? $this->friends : []; }
    public function getFriendRequests() { return is_array($this->friend_requests) ? $this->friend_requests : []; }
    public function getPrivateConversations() { return is_array($this->private_conversations) ? $this->private_conversations : []; }
    public function getGroupChats() { return is_array($this->group_chats) ? $this->group_chats : []; }
    public function getStories() { return is_array($this->stories) ? $this->stories : []; }
    public function getTableName(): string { return (string) $this->table; }
    public function getLastError() { return $this->lastError; }
    public function getColumnCache(): array { return (array) $this->colCache; }
    public function hasColumnInCache(string $column): bool { return array_key_exists($column, $this->colCache); }
    public function getColumnFromCache(string $column): bool { return (bool) ($this->colCache[$column] ?? false); }

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
    public function setCountry($v) { $this->country = $v; }
    public function setExactLocation($v) { $this->exact_location = $v; }
    public function setLatitude($v) { $this->latitude = $v; }
    public function setLongitude($v) { $this->longitude = $v; }
    public function setBio($v) { $this->bio = $v; }
    public function setSkills($v) { $this->skills = $v; }
    public function setXp($v) { $this->xp = $v; }
    public function setIsBlocked($v) { $this->is_blocked = $v; }
    public function setLastSeen($v) { $this->last_seen = $v; }
    public function setFaceImagesPath($v) { $this->face_images_path = $v; }
    public function setBadge($v) { $this->badge = $v; }
    public function setTitle($v) { $this->title = $v; }
    public function setFaceDescriptor($v) { $this->face_descriptor = $v; }
    public function setFaceEnrolled($v) { $this->face_enrolled = $v; }
    public function setFaceEnrolledAt($v) { $this->face_enrolled_at = $v; }
    public function setCreatedAt($v) { $this->created_at = $v; }
    public function setUpdatedAt($v) { $this->updated_at = $v; }
    public function setInvitationCode($v) { $this->invitation_code = $v; }
    public function setReferredBy($v) { $this->referred_by = $v; }
    public function setLinkedAccounts($v) { $this->linked_accounts = is_array($v) ? $v : []; }
    public function setFriends($v) { $this->friends = is_array($v) ? $v : []; }
    public function setFriendRequests($v) { $this->friend_requests = is_array($v) ? $v : []; }
    public function setPrivateConversations($v) { $this->private_conversations = is_array($v) ? $v : []; }
    public function setGroupChats($v) { $this->group_chats = is_array($v) ? $v : []; }
    public function setStories($v) { $this->stories = is_array($v) ? $v : []; }
    public function setTableName(string $v) { $this->table = $v; }
    public function setLastError($v) { $this->lastError = $v; }
    public function setColumnInCache(string $column, bool $exists) { $this->colCache[$column] = $exists; }
    public function clearColumnCache() { $this->colCache = []; }

    // --- Conversation / Message helpers ---
    public function getConversations() { return is_array($this->conversations) ? $this->conversations : []; }
    public function setConversations($v) { $this->conversations = is_array($v) ? $v : []; }
    public function addConversation($conv) { $this->conversations[] = $conv; }

    public function getMessages() { return is_array($this->messages) ? $this->messages : []; }
    public function setMessages($v) { $this->messages = is_array($v) ? $v : []; }
    public function addMessage($m) { $this->messages[] = $m; }

    // --- Friends / Requests helpers ---
    public function isFriendWith($userId) {
        foreach (is_array($this->friends) ? $this->friends : [] as $f) {
            if (is_array($f) && isset($f['id']) && (int)$f['id'] === (int)$userId) return true;
            if (!is_array($f) && isset($f->id) && (int)$f->id === (int)$userId) return true;
        }
        return false;
    }

    public function hasPendingFriendRequestTo($userId) {
        foreach (is_array($this->friend_requests) ? $this->friend_requests : [] as $r) {
            $receiver = is_array($r) ? ($r['receiver_id'] ?? $r['user_id'] ?? null) : ($r->receiver_id ?? $r->user_id ?? null);
            $status = is_array($r) ? ($r['status'] ?? null) : ($r->status ?? null);
            if ($receiver && (int)$receiver === (int)$userId && ($status === null || $status === 'pending')) return true;
        }
        return false;
    }

    public function addFriendEntry($friend) { $this->friends[] = $friend; }
    public function removeFriendById($friendId) {
        $this->friends = array_values(array_filter(is_array($this->friends) ? $this->friends : [], function($f) use ($friendId) {
            if (is_array($f)) return (!isset($f['id']) || (int)$f['id'] !== (int)$friendId);
            if (is_object($f)) return (!isset($f->id) || (int)$f->id !== (int)$friendId);
            return true;
        }));
    }

    public function addFriendRequest($request) { $this->friend_requests[] = $request; }

    public function getFullName() {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitials() {
        $i = strtoupper(substr($this->first_name ?? '', 0, 1) . substr($this->last_name ?? '', 0, 1));
        return $i ?: '??';
    }
}
