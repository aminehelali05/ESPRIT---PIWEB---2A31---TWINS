<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/User.php');

class UserController
{
    private $table;

    public function __construct()
    {
        $this->table = $this->resolveTableName();
    }

    private function resolveTableName()
    {
        $db = config::getConnexion();
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'users'");
            if ($stmt && $stmt->fetch()) {
                return 'users';
            }
        } catch (Exception $e) {
        }
        return 'user';
    }

    public function getTableName()
    {
        return $this->table;
    }

    private function hydrateUser(array $row): User
    {
        $user = new User(
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['password'],
            $row['phone'] ?? '',
            $row['role'] ?? 'user',
            $row['status'] ?? 1,
            $row['created_at'] ?? null
        );
        $user->setId($row['id']);
        $user->setAvatarUrl($row['avatar_url'] ?? null);
        $user->setBadge($row['badge'] ?? null);
        $user->setCountry($row['country'] ?? null);
        $user->setBio($row['bio'] ?? null);
        $user->setTitle($row['title'] ?? null);
        $user->setSkills($row['skills'] ?? null);
        $user->setXp($row['xp'] ?? 0);
        $user->setIsBlocked($row['is_blocked'] ?? 0);
        $user->setLastSeen($row['last_seen'] ?? null);
        $user->setFaceDescriptor($row['face_descriptor'] ?? null);
        $user->setFaceImagesPath($row['face_images_path'] ?? null);
        $user->setFaceEnrolled($row['face_enrolled'] ?? 0);
        $user->setFaceEnrolledAt($row['face_enrolled_at'] ?? null);
        $user->setUpdatedAt($row['updated_at'] ?? null);
        return $user;
    }

    public function addUser(User $user)
    {
        $sql = "INSERT INTO {$this->table} (first_name, last_name, email, password, phone, role, status,
                avatar_url, badge, country, bio, title, skills, xp, is_blocked, created_at)
                VALUES (:first_name, :last_name, :email, :password, :phone, :role, :status,
                :avatar_url, :badge, :country, :bio, :title, :skills, :xp, :is_blocked, :created_at)";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'first_name'  => $user->getFirstName(),
                'last_name'   => $user->getLastName(),
                'email'       => $user->getEmail(),
                'password'    => $user->getPassword(),
                'phone'       => $user->getPhone(),
                'role'        => $user->getRole(),
                'status'      => $user->getStatus(),
                'avatar_url'  => $user->getAvatarUrl(),
                'badge'       => $user->getBadge(),
                'country'     => $user->getCountry(),
                'bio'         => $user->getBio(),
                'title'       => $user->getTitle(),
                'skills'      => $user->getSkills(),
                'xp'          => $user->getXp(),
                'is_blocked'  => $user->getIsBlocked(),
                'created_at'  => $user->getCreatedAt() ?: date('Y-m-d H:i:s')
            ]);
            return $db->lastInsertId();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return false;
        }
    }

    public function updateUser(User $user, $id)
    {
        $sql = "UPDATE {$this->table} SET first_name=:first_name, last_name=:last_name, email=:email,
                password=:password, phone=:phone, role=:role, status=:status,
                avatar_url=:avatar_url, badge=:badge, country=:country, bio=:bio,
            title=:title, skills=:skills, xp=:xp, is_blocked=:is_blocked,
            face_descriptor=:face_descriptor, face_images_path=:face_images_path,
            face_enrolled=:face_enrolled, face_enrolled_at=:face_enrolled_at
                WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'first_name'  => $user->getFirstName(),
                'last_name'   => $user->getLastName(),
                'email'       => $user->getEmail(),
                'password'    => $user->getPassword(),
                'phone'       => $user->getPhone(),
                'role'        => $user->getRole(),
                'status'      => $user->getStatus(),
                'avatar_url'  => $user->getAvatarUrl(),
                'badge'       => $user->getBadge(),
                'country'     => $user->getCountry(),
                'bio'         => $user->getBio(),
                'title'       => $user->getTitle(),
                'skills'      => $user->getSkills(),
                'xp'          => $user->getXp(),
                'is_blocked'  => $user->getIsBlocked(),
                'face_descriptor' => $user->getFaceDescriptor(),
                'face_images_path' => $user->getFaceImagesPath(),
                'face_enrolled' => $user->getFaceEnrolled(),
                'face_enrolled_at' => $user->getFaceEnrolledAt(),
                'id'          => $id
            ]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function deleteUser($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function listUsers($search = '')
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        if ($search !== '') {
            $sql .= " WHERE first_name LIKE :kw OR last_name LIKE :kw OR email LIKE :kw";
            $params['kw'] = '%' . $search . '%';
        }
        $sql .= " ORDER BY id DESC";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute($params);
            $rows = $query->fetchAll();
            $users = [];
            foreach ($rows as $row) {
                $users[] = $this->hydrateUser($row);
            }
            return $users;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function searchUsers($keyword)
    {
        return $this->listUsers(trim((string) $keyword));
    }

    public function getUserById($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();
            if ($row) {
                return $this->hydrateUser($row);
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getUserByEmail($email)
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['email' => $email]);
            $row = $query->fetch();
            return $row ? $this->hydrateUser($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function login($email, $password)
    {
        $user = $this->getUserByEmail($email);
        if (!$user || (int) $user->getIsBlocked() === 1) {
            return null;
        }
        if (!password_verify($password, $user->getPassword())) {
            return null;
        }

        $db = config::getConnexion();
        try {
            $up = $db->prepare("UPDATE {$this->table} SET last_seen = NOW() WHERE id = :id");
            $up->execute(['id' => $user->getId()]);
        } catch (Exception $e) {
        }
        return $this->getUserById($user->getId());
    }

    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT id FROM {$this->table} WHERE email = :email";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $params = ['email' => $email];
            if ($excludeId !== null) {
                $params['exclude_id'] = $excludeId;
            }
            $query->execute($params);
            return (bool) $query->fetch();
        } catch (Exception $e) {
            return false;
        }
    }

    public function countUsers($search = '')
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$this->table}";
        $params = [];
        if ($search !== '') {
            $sql .= " WHERE first_name LIKE :kw OR last_name LIKE :kw OR email LIKE :kw";
            $params['kw'] = '%' . $search . '%';
        }
        $db = config::getConnexion();
        $query = $db->prepare($sql);
        $query->execute($params);
        $row = $query->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    public function getUsersPage($page = 1, $perPage = 8, $search = '')
    {
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = " WHERE first_name LIKE :kw OR last_name LIKE :kw OR email LIKE :kw";
            $params['kw'] = '%' . $search . '%';
        }

        $db = config::getConnexion();
        $totalQuery = $db->prepare("SELECT COUNT(*) as cnt FROM {$this->table}{$where}");
        $totalQuery->execute($params);
        $total = (int) (($totalQuery->fetch())['cnt'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = "SELECT * FROM {$this->table}{$where} ORDER BY id DESC LIMIT {$offset}, {$perPage}";
        $query = $db->prepare($sql);
        $query->execute($params);
        $rows = $query->fetchAll();
        $users = [];
        foreach ($rows as $row) {
            $users[] = $this->hydrateUser($row);
        }

        return [
            'users' => $users,
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
            'perPage' => $perPage,
            'search' => $search
        ];
    }

    public function countByRole($role)
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$this->table} WHERE role = :role";
        $db = config::getConnexion();
        $query = $db->prepare($sql);
        $query->execute(['role' => $role]);
        $row = $query->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    public function countActiveUsers()
    {
        $sql = "SELECT COUNT(*) as cnt FROM {$this->table} WHERE status = 1";
        $db = config::getConnexion();
        $query = $db->prepare($sql);
        $query->execute();
        $row = $query->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    public function toggleBlock($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        $user = $this->getUserById($id);
        if (!$user) {
            return false;
        }

        $user->setIsBlocked((int) $user->getIsBlocked() === 1 ? 0 : 1);
        $this->updateUser($user, $id);

        return (int) $user->getIsBlocked();
    }

    public function updateProfile($id, array $data)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }

        $sql = "UPDATE {$this->table}
                SET first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone,
                    country = :country,
                    bio = :bio,
                    title = :title,
                    skills = :skills,
                    avatar_url = :avatar_url,
                    updated_at = NOW()
                WHERE id = :id";

        $db = config::getConnexion();

        try {
            $query = $db->prepare($sql);
            return $query->execute([
                'first_name' => trim((string) ($data['first_name'] ?? '')),
                'last_name' => trim((string) ($data['last_name'] ?? '')),
                'email' => trim((string) ($data['email'] ?? '')),
                'phone' => trim((string) ($data['phone'] ?? '')),
                'country' => trim((string) ($data['country'] ?? '')),
                'bio' => trim((string) ($data['bio'] ?? '')),
                'title' => trim((string) ($data['title'] ?? '')),
                'skills' => trim((string) ($data['skills'] ?? '')),
                'avatar_url' => trim((string) ($data['avatar_url'] ?? '')) ?: null,
                'id' => $id,
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function changePassword($id, $newPassword)
    {
        $id = (int) $id;
        $newPassword = (string) $newPassword;

        if ($id <= 0 || strlen($newPassword) < 6) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET password = :password, updated_at = NOW() WHERE id = :id";
        $db = config::getConnexion();

        try {
            $query = $db->prepare($sql);
            return $query->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $id,
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
}
