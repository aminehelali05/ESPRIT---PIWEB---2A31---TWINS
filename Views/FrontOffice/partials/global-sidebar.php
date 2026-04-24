<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');

$sidebarUser = UserController::currentUser() ?? [];
$sidebarFirstName = trim((string) ($sidebarUser['first_name'] ?? 'Guest'));
$sidebarLastName = trim((string) ($sidebarUser['last_name'] ?? 'User'));
$sidebarDisplayName = trim($sidebarFirstName . ' ' . $sidebarLastName);
$sidebarDisplayName = $sidebarDisplayName !== '' ? $sidebarDisplayName : 'Guest User';
$sidebarInitials = strtoupper(substr($sidebarFirstName ?: 'G', 0, 1) . substr($sidebarLastName ?: 'U', 0, 1));
$sidebarLevel = trim((string) ($sidebarLevel ?? ''));
$sidebarLevel = $sidebarLevel !== '' ? $sidebarLevel : 'Level 12';
$sidebarReputation = (int) ($sidebarReputation ?? 84);
$sidebarStreak = (int) ($sidebarStreak ?? 7);

$sidebarRoute = basename(parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$sidebarRouteMap = [
    'home.php' => 'home',
    'profile.php' => 'profile',
    'messages.php' => 'messages',
    'live.php' => 'live',
    'ai-agent.php' => 'ai-agent',
    'jobOffer.php' => 'job-offer',
    'JobOffer.php' => 'job-offer',
    'projects.php' => 'projects',
    'contracts.php' => 'contracts',
    'reviews.php' => 'contracts',
];
$sidebarActive = $sidebarRouteMap[$sidebarRoute] ?? '';

$sidebarMode = (string) ($sidebarMode ?? '');
if ($sidebarMode === '') {
    $sidebarMode = $sidebarRoute === 'challenges.php' ? 'fixed' : 'sticky';
}

$sidebarClass = trim((string) ($sidebarClass ?? 'shared-sidebar glass-card'));
if ($sidebarClass === '') {
    $sidebarClass = 'shared-sidebar glass-card';
}

$sidebarLabel = (string) ($sidebarLabel ?? 'Primary navigation');

$sidebarIcon = static function (string $icon): string {
    $shared = 'width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    switch ($icon) {
        case 'home':
            return '<svg ' . $shared . '><path d="M3 11.5L12 4l9 7.5"/><path d="M5 10.5V20h14v-9.5"/><path d="M9 20v-5h6v5"/></svg>';
        case 'profile':
            return '<svg ' . $shared . '><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>';
        case 'messages':
            return '<svg ' . $shared . '><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7A2.5 2.5 0 0 1 17.5 16H11l-4.5 4v-4H6.5A2.5 2.5 0 0 1 4 13.5z"/></svg>';
        case 'live':
            return '<svg ' . $shared . '><circle cx="12" cy="12" r="2"/><path d="M8.5 8.5a5 5 0 0 1 0 7"/><path d="M15.5 15.5a5 5 0 0 0 0-7"/><path d="M5.8 5.8a9 9 0 0 1 0 12.4"/><path d="M18.2 5.8a9 9 0 0 1 0 12.4"/></svg>';
        case 'ai-agent':
            return '<svg ' . $shared . '><circle cx="12" cy="12" r="3.2"/><path d="M12 4.5v2.3"/><path d="M12 17.2v2.3"/><path d="M4.5 12h2.3"/><path d="M17.2 12h2.3"/><path d="M7.1 7.1l1.6 1.6"/><path d="M15.3 15.3l1.6 1.6"/><path d="M16.9 7.1l-1.6 1.6"/><path d="M8.7 15.3l-1.6 1.6"/></svg>';
        case 'job-offer':
            return '<svg ' . $shared . '><path d="M6 7.5A2.5 2.5 0 0 1 8.5 5h7A2.5 2.5 0 0 1 18 7.5V9H6z"/><path d="M4.5 9h15v7.5A2.5 2.5 0 0 1 17 19H7a2.5 2.5 0 0 1-2.5-2.5z"/><path d="M9 9V7.5"/><path d="M15 9V7.5"/></svg>';
        case 'projects':
            return '<svg ' . $shared . '><rect x="4" y="5" width="16" height="14" rx="3"/><path d="M8 9h8"/><path d="M8 13h3"/><path d="M8 16.5l1.5 1.5 3-3"/></svg>';
        case 'contracts':
            return '<svg ' . $shared . '><path d="M6 4h8l4 4v12H6z"/><path d="M14 4v4h4"/><path d="M8 12h8"/><path d="M8 15h6"/><path d="M8 18h5"/></svg>';
        default:
            return '';
    }
};

$sidebarItems = [
    ['key' => 'home', 'href' => 'home.php', 'label' => 'Home', 'icon' => 'home'],
    ['key' => 'profile', 'href' => 'profile.php', 'label' => 'Profile', 'icon' => 'profile'],
    ['key' => 'messages', 'href' => 'messages.php', 'label' => 'Messages', 'icon' => 'messages'],
    ['key' => 'live', 'href' => 'live.php', 'label' => 'Live Studio', 'icon' => 'live'],
    ['key' => 'ai-agent', 'href' => 'ai-agent.php', 'label' => 'AI Agent', 'icon' => 'ai-agent'],
    ['key' => 'job-offer', 'href' => 'jobOffer.php', 'label' => 'Job Offer', 'icon' => 'job-offer'],
    ['key' => 'projects', 'href' => 'projects.php', 'label' => 'Projects & Tasks', 'icon' => 'projects'],
    ['key' => 'contracts', 'href' => 'contracts.php', 'label' => 'Contracts', 'icon' => 'contracts'],
];
?>
<aside class="<?= htmlspecialchars($sidebarClass) ?>" data-sidebar-mode="<?= htmlspecialchars($sidebarMode) ?>" aria-label="<?= htmlspecialchars($sidebarLabel) ?>">
    <div class="left-profile">
        <div class="left-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
        <div>
            <h4><?= htmlspecialchars($sidebarDisplayName) ?></h4>
            <p><?= htmlspecialchars($sidebarLevel) ?></p>
        </div>
    </div>
    <nav class="left-nav" aria-label="Primary sidebar links">
        <?php foreach ($sidebarItems as $item): ?>
            <?php $isActive = $sidebarActive === $item['key']; ?>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $isActive ? 'active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                <?= $sidebarIcon((string) $item['icon']) ?>
                <span class="sidebar-link-text"><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="left-gamification">
        <div class="mini-score">
            <span>Reputation</span>
            <strong><?= (int) $sidebarReputation ?></strong>
        </div>
        <div class="mini-score">
            <span>Daily Streak</span>
            <strong><?= (int) $sidebarStreak ?> days</strong>
        </div>
    </div>
</aside>
