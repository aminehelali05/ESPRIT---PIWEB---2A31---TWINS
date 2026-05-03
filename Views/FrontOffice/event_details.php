<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');
include_once(__DIR__ . '/../../Controllers/ResourceController.php');

$eventController = new EventController();
$resourceController = new ResourceController();
$event = $eventController->getEventById($_GET['id'] ?? 0);
$allResources = $resourceController->getResourcesByEvent($_GET['id'] ?? 0);

if (!$event) {
    header('Location: events.php');
    exit;
}

$userController = new UserController();
$creator = $userController->getUserById($event->getUserId());
$creatorName = $creator ? trim($creator->getFirstName() . ' ' . $creator->getLastName()) : 'Unknown User';
if (empty($creatorName)) $creatorName = 'Community Member';

$sidebarUser = UserController::currentUser() ?? [];
$currentUserId = $sidebarUser['id'] ?? null;

// Only the creator can edit/delete their event — admin cannot
$isOwner = $currentUserId && $currentUserId == $event->getUserId();

// ── Resource search/sort (client-side, but prepare PHP vars) ──
$resSearch = trim($_GET['res_search'] ?? '');
$resSort   = $_GET['res_sort'] ?? 'date_desc';

// Filter resources by search
$resources = array_filter($allResources, function($r) use ($resSearch) {
    if ($resSearch === '') return true;
    return stripos($r['title'], $resSearch) !== false
        || stripos($r['type'], $resSearch) !== false;
});

// Sort
usort($resources, function($a, $b) use ($resSort) {
    return match($resSort) {
        'date_asc'  => strtotime($a['created_at']) - strtotime($b['created_at']),
        'type_asc'  => strcmp($a['type'], $b['type']),
        'type_desc' => strcmp($b['type'], $a['type']),
        default     => strtotime($b['created_at']) - strtotime($a['created_at']),
    };
});
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event->getTitle()) ?> — Diversity.is</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/projects.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .progress-container { margin: 25px 0; background: rgba(255,255,255,0.03); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); }
        .progress-bar-bg { background: rgba(255,255,255,0.1); height: 12px; border-radius: 6px; overflow: hidden; margin-top: 10px; }
        .progress-bar-fill { height: 100%; transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
        .qr-section { margin-top: 40px; text-align: center; padding: 30px; background: white; border-radius: 20px; color: black; }
        .qr-image { width: 200px; height: 200px; margin: 0 auto 20px; display: block; border: 10px solid #f8fafc; border-radius: 10px; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-open { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid #10b981; }
        .badge-full { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; }

        /* ── Resource toolbar ── */
        .res-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }
        .res-search-wrap {
            flex: 1;
            min-width: 180px;
            position: relative;
        }
        .res-search-wrap svg {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            opacity: 0.4;
            pointer-events: none;
        }
        .res-search-input {
            width: 100%;
            padding: 9px 12px 9px 34px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: inherit;
            font-size: 0.82rem;
            font-family: inherit;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .res-search-input:focus { border-color: rgba(99,102,241,0.5); }
        .res-sort-select {
            padding: 9px 30px 9px 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: inherit;
            font-size: 0.82rem;
            font-family: inherit;
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .res-sort-select:focus { border-color: rgba(99,102,241,0.5); }

        /* ── Resource card header row ── */
        .res-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .res-card-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .res-owner-actions {
            display: flex;
            gap: 6px;
        }
        .res-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
        }
        .res-action-edit {
            background: rgba(99,102,241,0.12);
            color: #818cf8;
            border: 1px solid rgba(99,102,241,0.25);
        }
        .res-action-edit:hover { background: rgba(99,102,241,0.2); }
        .res-action-delete {
            background: rgba(239,68,68,0.08);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.2);
        }
        .res-action-delete:hover { background: rgba(239,68,68,0.16); }

        /* no-results */
        .res-empty {
            text-align: center;
            padding: 40px;
            opacity: 0.4;
            font-size: 0.88rem;
        }
    </style>
</head>
<body class="grid-dot-bg">
    <canvas id="gradient-canvas"></canvas>

    <nav class="navbar">
        <div class="container">
            <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
            <div class="navbar-nav">
                <a href="events.php">Back to Events</a>
            </div>
        </div>
    </nav>

    <main class="profile-main">
        <div class="container" style="max-width: 900px;">
            <div class="glass-card" style="padding: 40px; margin-top: 40px;">

                <!-- Flash messages -->
                <?php if (isset($_SESSION['flash_error'])): ?>
                <div style="background: rgba(239,68,68,0.1); border: 1px solid #ef4444; color: #ef4444; padding: 14px; border-radius: 12px; margin-bottom: 20px; font-size: 0.85rem;">
                    <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
                </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['flash_success'])): ?>
                <div style="background: rgba(16,185,129,0.1); border: 1px solid #10b981; color: #10b981; padding: 14px; border-radius: 12px; margin-bottom: 20px; font-size: 0.85rem;">
                    <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
                </div>
                <?php endif; ?>

                <!-- Event Header — Edit/Delete for owner ONLY -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div>
                        <span class="project-status status-active"><?= htmlspecialchars($event->getCategory()) ?></span>
                        <h1 class="text-h1" style="margin-top: 10px;"><?= htmlspecialchars($event->getTitle()) ?></h1>
                    </div>
                    <?php if ($isOwner): ?>
                    <div style="display: flex; gap: 10px; flex-shrink: 0;">
                        <a href="event_edit.php?id=<?= $event->getId() ?>" class="btn btn-secondary btn-sm">
                            <i data-lucide="edit-3"></i> Edit
                        </a>
                        <a href="#" class="btn btn-secondary btn-sm" style="color: var(--color-error);"
                           onclick="confirmDeleteEvent(<?= $event->getId() ?>); return false;">
                            <i data-lucide="trash-2"></i> Delete
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Date + Location -->
                <div class="grid grid-2" style="gap: 30px; margin-bottom: 30px;">
                    <div class="glass-card" style="padding: 20px; background: rgba(255,255,255,0.05);">
                        <p class="text-small" style="font-weight: 600; color: var(--color-accent); margin-bottom: 10px;"><i data-lucide="calendar"></i> DATE & TIME</p>
                        <p class="text-body-lg">
                            <?= date('F j, Y — H:i', strtotime($event->getStartDate())) ?>
                            <br><span style="opacity: 0.6; font-size: 0.8em;">to</span><br>
                            <?= date('F j, Y — H:i', strtotime($event->getEndDate())) ?>
                        </p>
                    </div>
                    <div class="glass-card" style="padding: 20px; background: rgba(255,255,255,0.05);">
                        <p class="text-small" style="font-weight: 600; color: var(--color-accent); margin-bottom: 10px;"><i data-lucide="map-pin"></i> LOCATION</p>
                        <p class="text-body-lg"><?= htmlspecialchars($event->getLocation()) ?></p>
                    </div>
                </div>

                <!-- Description -->
                <div style="margin-bottom: 40px;">
                    <h3 class="text-h3" style="margin-bottom: 15px;">About this event</h3>
                    <p class="text-body-lg" style="white-space: pre-wrap;"><?= htmlspecialchars($event->getDescription()) ?></p>
                </div>

                <!-- Organizer -->
                <div style="margin-bottom: 40px;">
                    <p class="text-small" style="color: var(--color-accent); font-weight: 600; margin-bottom: 5px;">ORGANIZED BY</p>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(99,102,241,0.1); color: #6366f1; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                            <?= $creator ? strtoupper(substr($creator->getFirstName(), 0, 1) . substr($creator->getLastName(), 0, 1)) : 'U' ?>
                        </div>
                        <p class="text-body-lg" style="font-weight: 600; margin: 0;"><?= htmlspecialchars($creatorName) ?></p>
                    </div>
                </div>

                <!-- Registration Progress -->
                <?php
                $percent = $event->getCapaciteMax() > 0 ? ($event->getNbInscrits() / $event->getCapaciteMax()) * 100 : 0;
                $barColor = $percent < 50 ? '#10b981' : ($percent < 80 ? '#f59e0b' : '#ef4444');
                ?>
                <div class="progress-container">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p class="text-small" style="font-weight: 600; opacity: 0.7;">REGISTRATION STATUS</p>
                            <h4 class="text-h4" style="margin-top: 5px;"><?= $event->getNbInscrits() ?> / <?= $event->getCapaciteMax() ?> Attending</h4>
                        </div>
                        <span class="badge <?= $event->getStatutInscription() === 'COMPLET' ? 'badge-full' : 'badge-open' ?>">
                            <?= $event->getStatutInscription() ?>
                        </span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: <?= min(100, $percent) ?>%; background: <?= $barColor ?>;"></div>
                    </div>
                    <p class="text-small" style="margin-top: 10px; text-align: right; color: <?= $barColor ?>; font-weight: 600;"><?= round($percent) ?>% Filled</p>
                </div>

                <!-- ═══════════════════════════════════════════════════ -->
                <!-- EVENT RESOURCES SECTION                            -->
                <!-- ═══════════════════════════════════════════════════ -->
                <?php if (!empty($allResources)): ?>
                <div style="margin-top: 50px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        <h3 class="text-h3" style="margin: 0;">
                            <i data-lucide="layers" style="vertical-align: middle; margin-right: 10px;"></i>Event Resources
                            <span style="font-size: 0.7rem; opacity: 0.5; margin-left: 8px; font-weight: 400;"><?= count($allResources) ?> resource<?= count($allResources) > 1 ? 's' : '' ?></span>
                        </h3>
                        <?php if ($isOwner): ?>
                        <a href="event_resources.php?event_id=<?= $event->getId() ?>" class="btn btn-secondary btn-sm">
                            <i data-lucide="plus"></i> Add Resources
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Search & Sort Toolbar -->
                    <form method="GET" action="" id="resFilterForm">
                        <input type="hidden" name="id" value="<?= $event->getId() ?>">
                        <div class="res-toolbar">
                            <div class="res-search-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                <input type="text" name="res_search" class="res-search-input"
                                       placeholder="Search resources..." value="<?= htmlspecialchars($resSearch) ?>"
                                       id="resSearchInput">
                            </div>
                            <select name="res_sort" class="res-sort-select" id="resSortSelect">
                                <option value="date_desc" <?= $resSort === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                                <option value="date_asc"  <?= $resSort === 'date_asc'  ? 'selected' : '' ?>>Oldest First</option>
                                <option value="type_asc"  <?= $resSort === 'type_asc'  ? 'selected' : '' ?>>Type A→Z</option>
                                <option value="type_desc" <?= $resSort === 'type_desc' ? 'selected' : '' ?>>Type Z→A</option>
                            </select>
                        </div>
                    </form>

                    <?php if (empty($resources)): ?>
                        <div class="res-empty">
                            <i data-lucide="search-x" style="display: block; margin: 0 auto 10px; width: 36px; height: 36px; opacity: 0.3;"></i>
                            No resources match "<?= htmlspecialchars($resSearch) ?>".
                        </div>
                    <?php else: ?>

                    <?php foreach ($resources as $res): ?>
                    <?php
                        $resType   = $res['type'];
                        $resDesc   = $res['description'];
                        $decoded   = json_decode($resDesc, true);
                        $isJson    = is_array($decoded);
                        $resId     = $res['id'];
                        $canManage = $currentUserId && $currentUserId == $res['user_id'];

                        // border colours per type
                        $borderColors = [
                            'planning' => 'rgba(99,102,241,0.2)',
                            'regles'   => 'rgba(245,158,11,0.2)',
                            'materiel' => 'rgba(16,185,129,0.2)',
                        ];
                        $border = $borderColors[$resType] ?? 'rgba(255,255,255,0.1)';
                    ?>

                    <div class="glass-card" style="padding: 24px; background: rgba(255,255,255,0.03); border-color: <?= $border ?>; margin-bottom: 20px;" data-res-type="<?= $resType ?>" data-res-id="<?= $resId ?>">

                        <!-- Resource card header -->
                        <div class="res-card-header">
                            <div class="res-card-title-row">
                                <?php if ($resType === 'planning'): ?>
                                    <span style="font-size: 22px;">📅</span><h4 class="text-h4" style="margin: 0;">Planning</h4>
                                <?php elseif ($resType === 'regles'): ?>
                                    <span style="font-size: 22px;">📜</span><h4 class="text-h4" style="margin: 0;">Event Rules</h4>
                                <?php elseif ($resType === 'materiel'): ?>
                                    <span style="font-size: 22px;">🔧</span><h4 class="text-h4" style="margin: 0;">Materials & Equipment</h4>
                                <?php else: ?>
                                    <span style="font-size: 22px;">📄</span><h4 class="text-h4" style="margin: 0;"><?= htmlspecialchars($res['title']) ?></h4>
                                <?php endif; ?>
                            </div>

                            <?php if ($canManage): ?>
                            <div class="res-owner-actions">
                                <a href="event_resources.php?event_id=<?= $event->getId() ?>&edit_res=<?= $resId ?>"
                                   class="res-action-btn res-action-edit">
                                    <i data-lucide="pencil" style="width:12px;height:12px;"></i> Edit
                                </a>
                                <button class="res-action-btn res-action-delete"
                                        onclick="confirmDeleteResource(<?= $resId ?>)">
                                    <i data-lucide="trash-2" style="width:12px;height:12px;"></i> Delete
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Resource content -->
                        <?php if ($resType === 'planning' && $isJson): ?>
                            <?php foreach ($decoded as $day): ?>
                                <?php if (!empty($day['slots'])): ?>
                                <div style="margin-bottom: 14px; padding: 14px; background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid rgba(255,255,255,0.06);">
                                    <p style="font-weight: 700; font-size: 0.88rem; margin: 0 0 10px; color: #818cf8;">
                                        Day <?= $day['day'] ?> — <?= htmlspecialchars($day['label'] ?? '') ?>
                                    </p>
                                    <?php foreach ($day['slots'] as $slot): ?>
                                    <div style="display: flex; align-items: center; gap: 12px; padding: 8px 12px; margin-bottom: 5px; background: rgba(99,102,241,0.06); border-radius: 8px;">
                                        <span style="font-size: 0.78rem; font-weight: 600; color: #818cf8; min-width: 110px;">
                                            <?= htmlspecialchars($slot['start'] ?? '') ?> — <?= htmlspecialchars($slot['end'] ?? '') ?>
                                        </span>
                                        <span style="font-size: 0.85rem;"><?= htmlspecialchars($slot['title'] ?? '') ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                        <?php elseif ($resType === 'regles' && $isJson): ?>
                            <ol style="padding: 0; margin: 0; list-style: none;">
                                <?php foreach ($decoded as $i => $rule): ?>
                                <li style="display: flex; align-items: flex-start; gap: 12px; padding: 10px 14px; margin-bottom: 6px; background: rgba(245,158,11,0.05); border-radius: 8px;">
                                    <span style="width: 26px; height: 26px; border-radius: 8px; background: rgba(245,158,11,0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex-shrink: 0;"><?= $i + 1 ?></span>
                                    <span style="font-size: 0.85rem; line-height: 1.5;"><?= htmlspecialchars($rule) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ol>

                        <?php elseif ($resType === 'materiel' && $isJson): ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <?php foreach ($decoded as $mat): ?>
                                <span style="padding: 7px 13px; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); border-radius: 10px; font-size: 0.8rem; font-weight: 500;">
                                    <?= htmlspecialchars($mat) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>

                        <?php else: ?>
                            <p class="text-body" style="font-size: 14px; opacity: 0.8; line-height: 1.5;"><?= htmlspecialchars($resDesc) ?></p>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>

                    <?php endif; /* empty resources after filter */ ?>
                </div>
                <?php elseif ($isOwner): ?>
                <!-- No resources yet — prompt owner to add some -->
                <div style="margin-top: 40px; text-align: center; padding: 30px; border: 2px dashed rgba(255,255,255,0.1); border-radius: 16px;">
                    <p style="opacity: 0.4; margin-bottom: 12px;">No resources configured for this event yet.</p>
                    <a href="event_resources.php?event_id=<?= $event->getId() ?>" class="btn btn-secondary btn-sm">
                        <i data-lucide="plus"></i> Add Resources
                    </a>
                </div>
                <?php endif; ?>

                <!-- Attend / Status -->
                <div style="margin-top: 40px; text-align: center;">
                    <?php if ($event->getStatus() === 'ACCEPTE'): ?>
                        <?php if ($event->getStatutInscription() === 'COMPLET'): ?>
                            <button class="btn btn-secondary" style="padding: 15px 40px; cursor: not-allowed;" disabled>Event is Full</button>
                        <?php else: ?>
                            <a href="../../index.php?action=join_event&id=<?= $event->getId() ?>" class="btn btn-primary" style="padding: 15px 40px;">Attend Event</a>
                        <?php endif; ?>

                        <?php if ($event->getQrCode()): ?>
                        <div class="qr-section">
                            <h4 class="text-h4" style="margin-bottom: 20px; color: #1e293b;">Event Access Pass</h4>
                            <img src="<?= $event->getQrCode() ?>" alt="Event QR Code" class="qr-image" id="qrImage">
                            <p class="text-small" style="color: #64748b; margin-bottom: 20px;">Scan this code at the entrance or share it with your guests.</p>
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <button onclick="downloadQR()" class="btn btn-secondary btn-sm"><i data-lucide="download" class="w-4 h-4"></i> Save Image</button>
                                <button onclick="downloadPDF()" class="btn btn-primary btn-sm"><i data-lucide="file-text" class="w-4 h-4"></i> Export PDF Pass</button>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="glass-card" style="padding: 20px; border-color: var(--color-accent-secondary);">
                            <p class="text-body" style="color: var(--color-accent-secondary);"><i data-lucide="clock" class="w-4 h-4" style="vertical-align: middle;"></i> This event is currently under review by our team.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <script src="../../assets/js/main.js"></script>
    <script>
        lucide.createIcons();

        // ── Auto-submit filter form on change ──────────────────────────
        document.getElementById('resSortSelect')?.addEventListener('change', () => {
            document.getElementById('resFilterForm').submit();
        });
        // Submit on Enter in search box
        document.getElementById('resSearchInput')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); document.getElementById('resFilterForm').submit(); }
        });
        // Live search with debounce
        let searchTimer;
        document.getElementById('resSearchInput')?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => document.getElementById('resFilterForm').submit(), 400);
        });

        // ── Delete Event ──────────────────────────────────────────────
        function confirmDeleteEvent(id) {
            Swal.fire({
                title: 'Delete this event?',
                text: 'This will permanently remove the event and all its resources.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#475569',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php?action=delete_event&id=' + id;
                }
            });
        }

        // ── Delete Resource ───────────────────────────────────────────
        function confirmDeleteResource(id) {
            Swal.fire({
                title: 'Delete this resource?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#475569',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php?action=delete_resource&id=' + id;
                }
            });
        }

        function downloadQR() {
            const link = document.createElement('a');
            link.href = document.getElementById('qrImage').src;
            link.download = 'event-qrcode-<?= $event->getId() ?>.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        async function downloadPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Add some design
            doc.setFillColor(99, 102, 241);
            doc.rect(0, 0, 210, 40, 'F');
            
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(22);
            doc.text("Event Access Pass", 105, 25, { align: 'center' });
            
            doc.setTextColor(30, 41, 59);
            doc.setFontSize(28);
            doc.setFont("helvetica", "bold");
            doc.text("<?= addslashes($event->getTitle()) ?>", 105, 60, { align: 'center' });
            
            doc.setFontSize(14);
            doc.setFont("helvetica", "normal");
            doc.text("Location: <?= addslashes($event->getLocation()) ?>", 105, 75, { align: 'center' });
            doc.text("Date: <?= date('F j, Y - H:i', strtotime($event->getStartDate())) ?>", 105, 85, { align: 'center' });
            
            // Add QR Code
            const qrImg = document.getElementById('qrImage');
            doc.addImage(qrImg.src, 'PNG', 55, 110, 100, 100);
            
            doc.setFontSize(10);
            doc.setTextColor(148, 163, 184);
            doc.text("Present this pass at the entrance. Generated by Diversity.is", 105, 230, { align: 'center' });
            
            doc.save('Event_Pass_<?= $event->getId() ?>.pdf');
        }
    </script>
</body>
</html>
