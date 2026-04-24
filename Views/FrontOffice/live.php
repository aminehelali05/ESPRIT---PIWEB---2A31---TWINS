<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');

if (!UserController::isAuthenticated()) {
  $_SESSION['flash_error'] = 'Please sign in first.';
  header('Location: auth.php');
  exit;
}

$sessionUser    = UserController::currentUser() ?? [];
$currentUserId  = (int) ($sessionUser['id'] ?? 0);
$firstName      = (string) ($sessionUser['first_name'] ?? 'Member');
$lastName       = (string) ($sessionUser['last_name']  ?? '');
$displayName    = trim($firstName . ' ' . $lastName) ?: 'Member';
$displayEmail   = (string) ($sessionUser['email'] ?? '');
$isAdmin        = strtolower(trim($displayEmail)) === 'admin@diversity.is';
$avatarUrl      = (string) ($sessionUser['avatar_url'] ?? '');
$displayRole    = (string) ($sessionUser['role'] ?? 'user');
$initials       = strtoupper(substr($firstName ?: 'M', 0, 1) . substr($lastName ?: 'U', 0, 1));
$xp             = (int) ($sessionUser['xp'] ?? 0);
$reputationScore= 87;
$profileLevel   = 'Level 12 Collaborator';
$streakDays     = 7;

if ($avatarUrl === '') {
  $seed     = rawurlencode(trim($firstName . '-' . $lastName . '-' . $displayEmail));
  $avatarUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed={$seed}";
}

$mode     = strtolower(trim((string) ($_GET['mode'] ?? 'browse')));
if (!in_array($mode, ['browse','watch','broadcast'], true)) { $mode = 'browse'; }
$streamId = max(0, (int) ($_GET['stream'] ?? 0));

$bootstrap = [
  'currentUser' => [
    'id'         => $currentUserId,
    'first_name' => $firstName,
    'last_name'  => $lastName,
    'avatar_url' => $avatarUrl,
    'full_name'  => $displayName,
  ],
  'mode'     => $mode,
  'streamId' => $streamId,
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Diversity.is Live Studio — broadcast or watch live streams with your network.">
  <title>Live Studio — Diversity.is</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/live.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg home-page-body with-global-left-sidebar">
  <a class="skip-link" href="#live-main-content">Skip to main content</a>

  <!-- ── Navbar ───────────────────────────────────── -->
  <nav class="navbar" id="navbar" aria-label="Primary navigation">
    <div class="container">
      <a href="home.php" class="navbar-brand" aria-label="Diversity home">
        <span class="brand-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </span>
        Diversity.is
      </a>
      <div class="navbar-nav" id="navbar-nav" aria-hidden="true"></div>
      <div class="navbar-actions">
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu">
            <div class="nav-avatar"><?= htmlspecialchars($initials) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($displayName) ?></strong>
              <span><?= htmlspecialchars($displayEmail) ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="home.php"    class="nav-dropdown-item"><i data-lucide="home" class="w-4 h-4"></i> Home</a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <!-- ── Main ─────────────────────────────────────── -->
  <?php include __DIR__ . '/partials/global-sidebar.php'; ?>

  <main class="live-main-page" id="live-main-content" tabindex="-1">
    <div class="container live-page-layout">

      <!-- ── Left sidebar ─────────────────────────── -->
      <aside class="home-left glass-card" aria-label="Navigation sidebar">
        <div class="left-profile">
          <div class="left-avatar"><?= htmlspecialchars($initials) ?></div>
          <div>
            <h4><?= htmlspecialchars($displayName) ?></h4>
            <p><?= htmlspecialchars($profileLevel) ?></p>
          </div>
        </div>
        <nav class="left-nav">
          <a href="home.php">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Home Feed
          </a>
          <a href="social.php">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            Social
          </a>
          <a href="profile.php">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Profile
          </a>
          <a class="active" href="live.php">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49m11.31-2.82a10 10 0 010 14.14m-14.14 0a10 10 0 010-14.14"/></svg>
            Live Studio
          </a>
          <a href="messages.php">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Messages
          </a>
          <a href="JobOffer.php">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            Job Offers
          </a>
          <?php if ($isAdmin): ?>
          <div style="margin:12px 0 6px 12px;font-size:.7rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.08em;">BackOffice</div>
          <a href="../BackOffice/dashboardUser.php">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Dashboard
          </a>
          <?php endif; ?>
        </nav>
        <div class="left-gamification">
          <div class="mini-score"><span>Reputation</span><strong><?= (int) $reputationScore ?></strong></div>
          <div class="mini-score"><span>Daily Streak 🔥</span><strong><?= (int) $streakDays ?> days</strong></div>
        </div>
      </aside>

      <!-- ── Live content area ─────────────────────── -->
      <section class="live-content-area" aria-label="Live streaming workspace">

        <!-- Header strip -->
        <header class="live-header-strip" aria-label="Stream status">
          <div class="live-brand-cluster">
            <span class="live-indicator-dot is-offline" id="lsLiveDot" aria-hidden="true"></span>
            <span class="live-brand-name">Live Studio</span>
            <span id="lsHeaderStreamName" style="font-size:.72rem;color:#64748b;font-weight:500;"></span>
          </div>
          <div class="live-badges-row">
            <span class="live-pill" id="lsHeaderLivePill">○ Not Live</span>
            <span class="live-pill" id="lsHeaderRolePill">Role: Idle</span>
            <span class="live-pill">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <span id="lsHeaderViewerNum">0</span> viewers
            </span>
          </div>
          <nav class="live-header-nav" aria-label="Quick links">
            <a href="home.php">Home</a>
            <a href="profile.php">Profile</a>
            <a href="messages.php">Messages</a>
          </nav>
        </header>

        <!-- Main workspace -->
        <div class="live-workspace">

          <!-- ── Stage column ──────────────────────── -->
          <section class="live-stage-col" aria-label="Video stage">

            <!-- Video stage -->
            <div class="live-video-stage" role="region" aria-label="Live video">
              <!-- Main video (screen share / remote stream) -->
              <video id="lsMainVideo" autoplay playsinline hidden
                     style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;background:#020617;"></video>

              <!-- PiP camera (shown when screen sharing) -->
              <div class="live-pip-cam is-hidden" id="lsPipCam" title="Face cam (drag to reposition)">
                <video id="lsLocalVideo" autoplay playsinline muted
                       style="width:100%;height:100%;object-fit:cover;display:block;background:#020617;"></video>
              </div>

              <!-- Stage overlays -->
              <div class="live-stage-overlay" aria-hidden="true">
                <div class="live-stage-top-row">
                  <span class="live-stage-badge-live" id="lsStageLiveBadge" style="display:none;">
                    <span class="dot"></span> LIVE
                  </span>
                  <div class="live-stage-badge-viewers" id="lsStageViewerBadge" style="display:none;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <span id="lsStageViewerNum">0</span>
                  </div>
                </div>
                <div class="live-stage-bottom-row">
                  <span class="live-stage-stream-title" id="lsStageTitle" style="display:none;"></span>
                </div>
              </div>

              <!-- Placeholder -->
              <div class="live-stage-placeholder" id="lsPlaceholder">
                <div class="placeholder-icon">
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49m11.31-2.82a10 10 0 010 14.14m-14.14 0a10 10 0 010-14.14"/></svg>
                </div>
                <h3>No stream selected</h3>
                <p>Pick a live stream from the list below, or start your own broadcast.</p>
              </div>
            </div>

            <!-- Controls dock -->
            <div class="live-controls-dock" role="toolbar" aria-label="Stream controls">
              <div class="live-dock-group">
                <button type="button" class="live-ctrl-btn is-start" id="lsStartBtn" aria-label="Start broadcasting">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49"/></svg>
                  <span>Start Live</span>
                </button>
                <button type="button" class="live-ctrl-btn is-end" id="lsEndBtn" aria-label="End broadcast">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                  <span>End Live</span>
                </button>
              </div>

              <div class="live-dock-divider"></div>

              <div class="live-dock-group">
                <button type="button" class="live-ctrl-btn" id="lsJoinBtn" aria-label="Join selected stream">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                  <span>Join Stream</span>
                </button>
                <button type="button" class="live-ctrl-btn" id="lsLeaveBtn" aria-label="Leave stream">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                  <span>Leave</span>
                </button>
              </div>

              <div class="live-dock-divider"></div>

              <div class="live-dock-group">
                <button type="button" class="live-ctrl-btn" id="lsMuteBtn" aria-label="Mute/unmute microphone">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                  <span>Mute</span>
                </button>
                <button type="button" class="live-ctrl-btn" id="lsCamBtn" aria-label="Toggle camera">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                  <span>Cam Off</span>
                </button>
                <button type="button" class="live-ctrl-btn" id="lsScreenBtn" aria-label="Share screen">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                  <span>Share Screen</span>
                </button>
              </div>
            </div>

            <!-- Status strip -->
            <div class="live-status-strip" id="lsStatusStrip" aria-live="polite">
              <span class="live-status-dot"></span>
              <span>Select a stream or click Start Live to begin broadcasting.</span>
            </div>

            <!-- Broadcast setup panel -->
            <div class="live-setup-panel">
              <div class="live-setup-header">
                <h3>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49"/></svg>
                  Broadcast Setup
                </h3>
                <button type="button" class="live-setup-toggle" id="lsSetupToggle" aria-label="Toggle setup panel">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
              </div>
              <div class="live-setup-body" id="lsSetupBody">
                <div class="live-field full">
                  <label for="lsTitleInput">Stream title</label>
                  <input id="lsTitleInput" type="text" maxlength="180"
                         placeholder="What are you sharing today?"
                         value="<?= htmlspecialchars($displayName) ?>'s live stream">
                </div>
                <div class="live-field full">
                  <label for="lsDescInput">Description</label>
                  <textarea id="lsDescInput" maxlength="500" placeholder="Add context for your viewers…"></textarea>
                </div>
                <div class="live-field">
                  <label for="lsCategoryInput">Category</label>
                  <input id="lsCategoryInput" type="text" maxlength="80" value="General">
                </div>
                <div class="live-field">
                  <label for="lsVisibilityInput">Visibility</label>
                  <select id="lsVisibilityInput">
                    <option value="public">Public</option>
                    <option value="friends">Friends only</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Streams list (inside stage col for compact layout) -->
            <div class="live-streams-panel">
              <div class="live-streams-head">
                <h3>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49"/></svg>
                  Live Now
                </h3>
                <div style="display:flex;align-items:center;gap:8px;">
                  <span class="live-streams-count" id="lsStreamsCount">0</span>
                  <button type="button" class="live-ctrl-btn" id="lsRefreshBtn"
                          style="padding:5px 10px;font-size:.65rem;" aria-label="Refresh stream list">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    <span>Refresh</span>
                  </button>
                </div>
              </div>
              <div class="live-streams-list" id="lsStreamsList" aria-live="polite" aria-label="Active live streams">
                <div class="live-empty-state">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 010 8.49m-8.48-.01a6 6 0 010-8.49"/></svg>
                  <p>Loading streams…</p>
                </div>
              </div>
            </div>

          </section><!-- /live-stage-col -->

          <!-- ── Side panel (viewers + chat) ──────── -->
          <aside class="live-side-col" aria-label="Viewers and chat">

            <!-- Viewers panel -->
            <div class="live-viewers-panel">
              <div class="live-panel-head">
                <h3>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                  Viewers
                </h3>
                <span class="live-viewer-count-badge" id="lsViewerCount">0</span>
              </div>
              <div class="live-viewers-list" id="lsViewersList" aria-live="polite" aria-label="Current viewers">
                <span style="font-size:.66rem;color:#475569;padding:4px;">No viewers yet</span>
              </div>
            </div>

            <!-- Chat panel -->
            <div class="live-chat-panel">
              <div class="live-panel-head">
                <h3>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                  Live Chat
                </h3>
              </div>
              <div class="live-chat-log" id="lsChatLog" role="log" aria-live="polite" aria-label="Chat messages">
                <div class="live-chat-empty">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                  <p>Chat will appear after joining a stream.</p>
                </div>
              </div>
              <div class="live-chat-compose">
                <input id="lsChatInput" class="live-chat-input" type="text" maxlength="500"
                       placeholder="Say something…" aria-label="Chat message">
                <button type="button" class="live-chat-send-btn" id="lsChatSendBtn" aria-label="Send message">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
              </div>
            </div>

          </aside><!-- /live-side-col -->
        </div><!-- /live-workspace -->
      </section><!-- /live-content-area -->
    </div><!-- /live-page-layout -->
  </main>

  <!-- ── Join notification ─────────────────────────── -->
  <div class="live-join-notification" id="lsJoinNotification" role="status" aria-live="polite">
    <div class="live-join-toast">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/><line x1="20" y1="8" x2="20" y2="14"/></svg>
      <span id="lsJoinToastText">Someone joined the stream</span>
    </div>
  </div>

  <!-- ── Toast stack ───────────────────────────────── -->
  <div class="live-toast-stack" id="lsToastStack" aria-live="assertive"></div>

  <!-- ── Bootstrap data ───────────────────────────── -->
  <script>
    window.liveBootstrap = <?php echo json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/live.js"></script>
  <script>
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
    // Draggable PiP cam
    (function () {
      const pip = document.getElementById('lsPipCam');
      if (!pip) return;
      let ox = 0, oy = 0, dragging = false;
      pip.addEventListener('mousedown', e => {
        dragging = true;
        ox = e.clientX - pip.getBoundingClientRect().left;
        oy = e.clientY - pip.getBoundingClientRect().top;
        pip.style.transition = 'none';
        e.preventDefault();
      });
      window.addEventListener('mousemove', e => {
        if (!dragging) return;
        const stage = pip.closest('.live-video-stage');
        if (!stage) return;
        const sr = stage.getBoundingClientRect();
        const pr = pip.getBoundingClientRect();
        let nx = e.clientX - sr.left - ox;
        let ny = e.clientY - sr.top  - oy;
        nx = Math.max(8, Math.min(sr.width  - pr.width  - 8, nx));
        ny = Math.max(8, Math.min(sr.height - pr.height - 8, ny));
        pip.style.right  = 'auto';
        pip.style.bottom = 'auto';
        pip.style.left   = nx + 'px';
        pip.style.top    = ny + 'px';
      });
      window.addEventListener('mouseup', () => { dragging = false; pip.style.transition = ''; });
    })();
  </script>
</body>
</html>
