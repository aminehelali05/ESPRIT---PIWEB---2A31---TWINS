<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once dirname(__DIR__, 3) . '/Controllers/UserController.php';

$sidebarUser = UserController::currentUser() ?? [];
$sidebarFirstName = trim((string) ($sidebarUser['first_name'] ?? 'Guest'));
$sidebarLastName = trim((string) ($sidebarUser['last_name'] ?? 'User'));
$sidebarDisplayName = trim($sidebarFirstName . ' ' . $sidebarLastName);
$sidebarDisplayName = $sidebarDisplayName !== '' ? $sidebarDisplayName : 'Guest User';
$sidebarInitials = strtoupper(substr($sidebarFirstName ?: 'G', 0, 1) . substr($sidebarLastName ?: 'U', 0, 1));
$sidebarEmail = trim((string) ($sidebarUser['email'] ?? ''));
$sidebarAvatarUrl = (string) ($sidebarUser['avatar_url'] ?? '');
if (!$sidebarAvatarUrl) {
    $seed = rawurlencode($sidebarFirstName . '-' . $sidebarLastName . '-' . $sidebarEmail);
    $sidebarAvatarUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed={$seed}";
}
$sidebarLevel = trim((string) ($sidebarLevel ?? ''));
$sidebarLevel = $sidebarLevel !== '' ? $sidebarLevel : 'Level 12';
$sidebarReputation = (int) ($sidebarReputation ?? 84);
$sidebarStreak = (int) ($sidebarStreak ?? 7);

$sidebarRoute = basename(parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$sidebarRouteMap = [
    'home.php' => 'home',
    'profile.php' => 'profile',
    'messages.php' => 'messages',
    'story.php' => 'story',
    'live.php' => 'live',
    'ai-agent.php' => 'ai-agent',
    'marketplace.php' => 'marketplace',
    'jobOffer.php' => 'job-offer',
    'JobOffer.php' => 'job-offer',
    'projects.php' => 'projects',
    'contracts.php' => 'contract',
];
$sidebarActive = $sidebarRouteMap[$sidebarRoute] ?? '';

$sidebarMode = (string) ($sidebarMode ?? '');
if ($sidebarMode === '') {
    $sidebarMode = 'fixed';
}

$sidebarClass = trim((string) ($sidebarClass ?? 'shared-sidebar glass-card global-left-sidebar'));
if ($sidebarClass === '') {
    $sidebarClass = 'shared-sidebar glass-card global-left-sidebar';
}
if (!str_contains($sidebarClass, 'shared-sidebar')) {
    $sidebarClass = 'shared-sidebar ' . $sidebarClass;
}
if (!str_contains($sidebarClass, 'glass-card')) {
    $sidebarClass .= ' glass-card';
}
if (!str_contains($sidebarClass, 'global-left-sidebar')) {
    $sidebarClass .= ' global-left-sidebar';
}

$sidebarLabel = (string) ($sidebarLabel ?? 'Primary navigation');

$sidebarIcon = static function (string $icon): string {
    $shared = 'width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    switch ($icon) {
        case 'home':
            return '<svg ' . $shared . '><path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h3.5V15a1.5 1.5 0 0 1 1.5-1.5h2a1.5 1.5 0 0 1 1.5 1.5v6H18a1 1 0 0 0 1-1V9.5"/></svg>';
        case 'profile':
            return '<svg ' . $shared . '><circle cx="12" cy="8" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg>';
        case 'messages':
            return '<svg ' . $shared . '><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
        case 'story':
            return '<svg ' . $shared . '><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><line x1="12" y1="3" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="21"/><line x1="3" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="21" y2="12"/></svg>';
        case 'live':
            return '<svg ' . $shared . '><circle cx="12" cy="12" r="2"/><path d="M8.5 8.5a5 5 0 0 0 0 7"/><path d="M15.5 15.5a5 5 0 0 0 0-7"/><path d="M5.8 5.8a9 9 0 0 0 0 12.4"/><path d="M18.2 5.8a9 9 0 0 1 0 12.4"/></svg>';
        case 'ai-agent':
            return '<svg ' . $shared . '><path d="M12 2a4 4 0 0 0-4 4v2a4 4 0 0 0 8 0V6a4 4 0 0 0-4-4z"/><path d="M16 10.5v.5a4 4 0 0 1-8 0v-.5"/><path d="M8 14s-3.5 1-3.5 4v1.5h15V18c0-3-3.5-4-3.5-4"/><circle cx="12" cy="6" r="1"/></svg>';
        case 'job-offer':
            return '<svg ' . $shared . '><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="12" y1="12" x2="12" y2="12.01"/><path d="M3 12a9.5 9.5 0 0 0 9 4 9.5 9.5 0 0 0 9-4"/></svg>';
        case 'marketplace':
            return '<svg ' . $shared . '><path d="M3 10h18"/><path d="M5 10v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8"/><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5V10H4Z"/><path d="M9 14h6"/></svg>';
        case 'wallet':
            return '<svg ' . $shared . '><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M12 11h8v2h-8z"/><path d="M16 8V4M8 8V4"/></svg>';
        case 'projects':
            return '<svg ' . $shared . '><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9h6"/><path d="M9 13h4"/><path d="M9 17l1.5 1.5L14 15"/></svg>';
        case 'contracts':
            return '<svg ' . $shared . '><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>';
        default:
            return '';
    }
};

$sidebarItems = [
    ['key' => 'home', 'href' => 'home.php', 'label' => 'Home', 'icon' => 'home'],
    ['key' => 'profile', 'href' => 'profile.php', 'label' => 'Profile', 'icon' => 'profile'],
    ['key' => 'messages', 'href' => 'messages.php', 'label' => 'Messages', 'icon' => 'messages'],
    ['key' => 'story', 'href' => 'story.php', 'label' => 'Story', 'icon' => 'story'],
    ['key' => 'live', 'href' => 'live.php', 'label' => 'Live Studio', 'icon' => 'live'],
    ['key' => 'ai-agent', 'href' => 'ai-agent.php', 'label' => 'AI Agent', 'icon' => 'ai-agent'],
    ['key' => 'projects', 'href' => 'projects.php', 'label' => 'Projects & Tasks', 'icon' => 'projects'],
    ['key' => 'marketplace', 'href' => 'marketplace.php', 'label' => 'Marketplace', 'icon' => 'marketplace'],
    ['key' => 'wallet', 'href' => 'wallet.php', 'label' => 'Wallet', 'icon' => 'wallet'],
    ['key' => 'job-offer', 'href' => 'jobOffer.php', 'label' => 'Job Offer', 'icon' => 'job-offer'],
    ['key' => 'contract', 'href' => 'contracts.php', 'label' => 'Contract', 'icon' => 'contracts'],
];
?>
<aside class="<?= htmlspecialchars($sidebarClass) ?>" data-sidebar-mode="<?= htmlspecialchars($sidebarMode) ?>" aria-label="<?= htmlspecialchars($sidebarLabel) ?>" id="globalSidebar">
    <div class="left-profile">
        <div class="left-avatar">
            <img src="<?= htmlspecialchars($sidebarAvatarUrl) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
        </div>
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

<!-- Global Blur Overlay System -->
<div class="global-blur-overlay" id="globalBlurOverlay"></div>

