<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/User.php');

class UserController
{
    public function addUser(User $user)
    {
        $sql = "INSERT INTO user (first_name, last_name, email, password, phone, role, status, created_at)
                VALUES (:first_name, :last_name, :email, :password, :phone, :role, :status, :created_at)";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'phone' => $user->getPhone(),
                'role' => $user->getRole(),
                'status' => $user->getStatus(),
                'created_at' => $user->getCreatedAt() ?: date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function updateUser(User $user, $id)
    {
        $sql = "UPDATE user SET first_name=:first_name, last_name=:last_name, email=:email,
                password=:password, phone=:phone, role=:role, status=:status WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'phone' => $user->getPhone(),
                'role' => $user->getRole(),
                'status' => $user->getStatus(),
                'id' => $id
            ]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function deleteUser($id)
    {
        $sql = "DELETE FROM user WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function listUsers()
    {
        $sql = "SELECT * FROM user ORDER BY id DESC";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute();
            $usersData = $query->fetchAll();

            $users = [];
            foreach ($usersData as $row) {
                $user = new User(
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['password'],
                    $row['phone'],
                    $row['role'],
                    $row['status'],
                    $row['created_at']
                );
                $user->setId($row['id']);
                $users[] = $user;
            }
            return $users;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function getUserById($id)
    {
        $sql = "SELECT * FROM user WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();

            if ($row) {
                $user = new User(
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['password'],
                    $row['phone'],
                    $row['role'],
                    $row['status'],
                    $row['created_at']
                );
                $user->setId($row['id']);
                return $user;
            }
            return null;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return null;
        }
    }

    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT id FROM user WHERE email = :email";
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
            echo 'Error: ' . $e->getMessage();
            return false;
        }
    }
}
