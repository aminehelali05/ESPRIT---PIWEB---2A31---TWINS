<?php

include_once(__DIR__ . '/UserController.php');
include_once(__DIR__ . '/ProjectController.php');
include_once(__DIR__ . '/MarketplaceController.php');

class ExportController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
    }

    public function getProfileExportData(int $userId): array
    {
        $userController = new UserController($this->pdo);
        $projectController = new ProjectController($this->pdo);
        $marketplaceController = new MarketplaceController($this->pdo);

        $user = $userController->getUserById($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        // Gather Projects
        $projects = $projectController->listProjectsByUserId($userId);

        // Gather Marketplace Activity
        $marketplaceItems = $marketplaceController->listItems(['user_id' => $userId]);

        // Reputation & Stats
        $stats = $this->getUserStats($userId);

        return [
            'success' => true,
            'summary' => [
                'user' => [
                    'full_name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
                    'email' => $user['email'] ?? '',
                    'role' => $user['role'] ?? 'member',
                    'title' => $user['title'] ?? 'Digital Professional',
                    'bio' => $user['bio'] ?? '',
                    'skills' => $user['skills'] ?? '',
                    'location' => ($user['city'] ?? '') . ', ' . ($user['country'] ?? ''),
                ],
                'stats' => $stats,
                'projects' => $projects,
                'marketplace' => $marketplaceItems,
                'reputation' => [
                    'score' => $user['xp'] ?? 0,
                    'level' => $this->calculateLevel($user['xp'] ?? 0),
                ]
            ]
        ];
    }

    private function getUserStats(int $userId): array
    {
        try {
            $stats = [];
            
            // Friends count
            $q = $this->pdo->prepare('SELECT COUNT(*) FROM friends WHERE user_id = :uid OR friend_id = :uid');
            $q->execute(['uid' => $userId]);
            $stats['friends'] = (int)$q->fetchColumn();

            // Messages count
            $q = $this->pdo->prepare('SELECT COUNT(*) FROM messages WHERE sender_id = :uid');
            $q->execute(['uid' => $userId]);
            $stats['messages'] = (int)$q->fetchColumn();

            return $stats;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function calculateLevel(int $xp): string
    {
        $level = floor($xp / 100) + 1;
        return "Level $level Collaborator";
    }
}
