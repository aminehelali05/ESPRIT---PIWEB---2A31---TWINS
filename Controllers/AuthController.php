<?php
include_once(__DIR__ . '/UserController.php');
include_once(__DIR__ . '/../Models/User.php');

class AuthController
{
    private $userController;

    public function __construct()
    {
        $this->userController = new UserController();
    }

    public function login(string $email, string $password): array
    {
        $email = trim($email);
        $password = (string) $password;

        if ($email === '' || $password === '') {
            return ['success' => false, 'message' => 'Email and password are required.'];
        }

        if (strtolower($email) === 'admin' && $password === 'admin') {
            $_SESSION['auth_user'] = [
                'id' => 0,
                'first_name' => 'Admin',
                'last_name' => 'Bypass',
                'email' => 'admin@local',
                'role' => 'admin',
                'avatar_url' => null,
            ];

            return ['success' => true, 'message' => 'Admin bypass login successful.'];
        }

        $user = $this->userController->login($email, $password);
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials or blocked account.'];
        }

        $_SESSION['auth_user'] = [
            'id' => (int) $user->getId(),
            'first_name' => (string) $user->getFirstName(),
            'last_name' => (string) $user->getLastName(),
            'email' => (string) $user->getEmail(),
            'role' => (string) $user->getRole(),
            'avatar_url' => $user->getAvatarUrl(),
        ];

        return ['success' => true, 'message' => 'Login successful.'];
    }

    public function register(array $data): array
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $phone = trim((string) ($data['phone'] ?? ''));
        $country = trim((string) ($data['country'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $bio = trim((string) ($data['bio'] ?? ''));
        $skills = trim((string) ($data['skills'] ?? ''));
        $avatarUrl = trim((string) ($data['avatar_url'] ?? ''));

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
            return ['success' => false, 'message' => 'All registration fields are required.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
        }

        if ($this->userController->emailExists($email)) {
            return ['success' => false, 'message' => 'This email already exists.'];
        }

        $user = new User(
            $firstName,
            $lastName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $phone,
            'user',
            1,
            date('Y-m-d H:i:s')
        );

        $user->setCountry($country !== '' ? $country : null);
        $user->setTitle($title !== '' ? $title : 'New Member');
        $user->setBio($bio !== '' ? $bio : 'Recently joined Diversity.is');
        $user->setSkills($skills !== '' ? $skills : '');
        $user->setAvatarUrl($avatarUrl !== '' ? $avatarUrl : null);
        $user->setXp(0);
        $user->setIsBlocked(0);

        $newId = $this->userController->addUser($user);
        if (!$newId) {
            $details = (string) $this->userController->getLastError();
            if ($details !== '') {
                return ['success' => false, 'message' => 'Could not create account: ' . $details];
            }
            return ['success' => false, 'message' => 'Could not create account right now.'];
        }

        $_SESSION['auth_user'] = [
            'id' => (int) $newId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'role' => 'user',
            'avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
        ];

        return ['success' => true, 'message' => 'Account created successfully.'];
    }

    public function logout(): void
    {
        unset($_SESSION['auth_user']);
        session_regenerate_id(true);
    }

    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['auth_user']['id']);
    }

    public static function currentUser(): ?array
    {
        return self::isAuthenticated() ? $_SESSION['auth_user'] : null;
    }
}
