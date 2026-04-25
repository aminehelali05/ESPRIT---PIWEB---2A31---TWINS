<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/AssistantController.php');

if (!UserController::isAuthenticated()) {
    $_SESSION['flash_error'] = 'Please sign in to access the AI Agent.';
    header('Location: auth.php');
    exit;
}

$sessionUser    = UserController::currentUser() ?? [];
$currentUserId  = (int) ($sessionUser['id'] ?? 0);
$displayFirstName = trim((string) ($sessionUser['first_name'] ?? 'Member'));
$displayLastName  = trim((string) ($sessionUser['last_name']  ?? ''));
$displayName      = trim($displayFirstName . ' ' . $displayLastName);
$displayName      = $displayName !== '' ? $displayName : 'Member User';
$displayEmail     = (string) ($sessionUser['email'] ?? '');
$displayAvatarUrl = (string) ($sessionUser['avatar_url'] ?? '');
$initials         = strtoupper(substr($displayFirstName ?: 'M', 0, 1) . substr($displayLastName ?: 'U', 0, 1));

if ($displayAvatarUrl === '') {
    $seed = rawurlencode(trim($displayFirstName . '-' . $displayLastName . '-' . $displayEmail));
    $displayAvatarUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed={$seed}";
}

$assistantBootstrap = AssistantController::bootstrapConfig();
$currentModel = trim((string) ($assistantBootstrap['model'] ?? 'openai/gpt-5.2'));
if ($currentModel === '') { $currentModel = 'openai/gpt-5.2'; }

$modelOptions = array_values(array_unique(array_filter([
    $currentModel,
    'openai/gpt-5.2',
    'openai/gpt-5.1',
    'openai/gpt-4.1-mini',
    'openai/o4-mini',
    'anthropic/claude-3.7-sonnet',
], static fn ($v): bool => trim((string) $v) !== '')));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Diversity.is AI Agent — voice-first autonomous assistant. Say Hey Bro to begin.">
  <title>AI Agent — Diversity.is</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/ai-agent.css">
</head>
<body class="grid-dot-bg home-page-body with-global-left-sidebar ai-agent-page">
  <a class="skip-link" href="#main-content">Skip to main content</a>

  <!-- Ambient Background -->
  <div class="ai-agent-ambient" aria-hidden="true">
    <span class="ai-agent-blob blob-one"></span>
    <span class="ai-agent-blob blob-two"></span>
    <span class="ai-agent-blob blob-three"></span>
    <span class="ai-agent-particles"></span>
  </div>

  <!-- Navbar -->
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
        <button class="theme-toggle" id="themeToggleBtn" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu" aria-haspopup="true" aria-expanded="false">
            <div class="nav-avatar"><?= htmlspecialchars($initials) ?></div>
          </button>
          <div class="nav-dropdown" role="menu">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($displayName) ?></strong>
              <span><?= htmlspecialchars($displayEmail) ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item" role="menuitem">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
              My Profile
            </a>
            <a href="messages.php" class="nav-dropdown-item" role="menuitem">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
              Messages
            </a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger" role="menuitem">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              Sign Out
            </a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <!-- Main -->
  <main class="ai-agent-main" id="main-content" tabindex="-1">
    <div class="container ai-agent-layout">
      <?php include __DIR__ . '/partials/global-sidebar.php'; ?>

      <section class="ai-agent-workspace"
               data-agent-shell
               data-agent-state="idle"
               data-settings-open="false"
               data-history-open="false"
               aria-label="AI Agent control interface">

        <!-- ━━━━━━━━━━━━━━ STAGE (Main Orb Panel) ━━━━━━━━━━━━━━ -->
        <article class="ai-agent-stage glass-card fade-in-section" aria-label="Voice AI control">

          <!-- Stage Header -->
          <header class="ai-agent-stage-head">
            <div class="ai-agent-pill" aria-live="polite" aria-atomic="true">
              <span class="ai-agent-pill-dot" aria-hidden="true"></span>
              <span data-agent-status-text>Online</span>
            </div>
            <div class="ai-agent-head-actions">
              <!-- History toggle -->
              <button class="ai-agent-history-toggle"
                      type="button"
                      id="historyToggleBtn"
                      data-agent-history-toggle
                      aria-controls="aiAgentHistoryPanel"
                      aria-expanded="false"
                      aria-label="Toggle command history"
                      title="History">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                  <path d="M3 3v5h5"/>
                  <path d="M12 7v5l4 2"/>
                </svg>
              </button>
              <!-- Settings toggle -->
              <button class="ai-agent-settings-toggle"
                      type="button"
                      id="settingsToggleBtn"
                      data-agent-settings-toggle
                      aria-label="Toggle settings"
                      title="Settings">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="3"/>
                  <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
              </button>
            </div>
          </header>

          <!-- Hero Copy (minimal) -->
          <div class="ai-agent-hero-copy">
            <p class="ai-agent-kicker">Voice Interface</p>
            <h1>Your AI Agent</h1>
            <p class="ai-agent-description">Say <strong>"Hey Bro"</strong> or tap the orb to take control hands-free.</p>
          </div>

          <!-- ━━━ ORB ━━━ -->
          <div class="ai-agent-orb-stack">
            <button class="ai-agent-orb"
                    type="button"
                    id="agentOrb"
                    data-agent-orb
                    aria-label="Start voice command">
              <span class="ai-agent-orb-ring ring-one"  aria-hidden="true"></span>
              <span class="ai-agent-orb-ring ring-two"  aria-hidden="true"></span>
              <span class="ai-agent-orb-ring ring-three" aria-hidden="true"></span>
              <span class="ai-agent-orb-core" aria-hidden="true">
                <!-- Mic icon -->
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="9" y="2" width="6" height="11" rx="3"/>
                  <path d="M5 10a7 7 0 0 0 14 0"/>
                  <line x1="12" y1="19" x2="12" y2="22"/>
                  <line x1="8" y1="22" x2="16" y2="22"/>
                </svg>
              </span>
            </button>

            <div class="ai-agent-state-text" data-agent-state-text aria-live="polite">Tap to begin</div>
            <div class="ai-agent-step-text"  data-agent-step     aria-live="polite">Ready</div>
          </div>

          <!-- ━━━ Pipeline Visualization ━━━ -->
          <div class="ai-pipeline" id="aiPipeline" data-agent-pipeline aria-label="Execution pipeline" aria-hidden="true">
            <!-- Intent -->
            <div class="ai-pipeline-step" data-pipeline-step="intent">
              <div class="ai-pipeline-dot">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M12 5v2M12 17v2M5 12H3M21 12h-2"/></svg>
              </div>
              <span class="ai-pipeline-label">Intent</span>
            </div>
            <!-- Planning -->
            <div class="ai-pipeline-step" data-pipeline-step="planning">
              <div class="ai-pipeline-dot">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              </div>
              <span class="ai-pipeline-label">Planning</span>
            </div>
            <!-- Execution -->
            <div class="ai-pipeline-step" data-pipeline-step="executing">
              <div class="ai-pipeline-dot">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              </div>
              <span class="ai-pipeline-label">Execution</span>
            </div>
            <!-- Done -->
            <div class="ai-pipeline-step" data-pipeline-step="done">
              <div class="ai-pipeline-dot">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <span class="ai-pipeline-label">Done</span>
            </div>
          </div>

          <!-- ━━━ Command Input ━━━ -->
          <div class="ai-agent-input-row" aria-label="Command input">
            <input class="ai-agent-input"
                   id="agentCommandInput"
                   type="text"
                   placeholder="Type or speak a command..."
                   data-agent-input
                   autocomplete="off"
                   spellcheck="false">
            <button class="ai-agent-voice-btn"
                    type="button"
                    id="agentVoiceBtn"
                    data-agent-voice-toggle
                    aria-label="Start voice input"
                    title="Speak">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="2" width="6" height="11" rx="3"/>
                <path d="M5 10a7 7 0 0 0 14 0"/>
                <line x1="12" y1="19" x2="12" y2="22"/>
              </svg>
            </button>
            <button class="ai-agent-send-btn"
                    type="button"
                    id="agentSendBtn"
                    data-agent-send>
              Send
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="22" y1="2" x2="11" y2="13"/>
                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
              </svg>
            </button>
          </div>

          <!-- ━━━ Quick Command Cards ━━━ -->
          <div class="ai-agent-shortcuts" aria-label="Quick commands">
            <button class="ai-agent-card" type="button" id="cmdNavigation" data-agent-command="Go to messages">
              <span class="ai-agent-card-icon nav-icon" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              </span>
              <span class="ai-agent-card-title">Navigate</span>
              <span class="ai-agent-card-desc">"Go to messages"</span>
              <span class="ai-agent-card-desc">"Open profile"</span>
            </button>
            <button class="ai-agent-card" type="button" id="cmdSearch" data-agent-command="Search for peace">
              <span class="ai-agent-card-icon search-icon" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </span>
              <span class="ai-agent-card-title">Search</span>
              <span class="ai-agent-card-desc">"Search for peace"</span>
              <span class="ai-agent-card-desc">"Find [topic]"</span>
            </button>
            <button class="ai-agent-card" type="button" id="cmdInfo" data-agent-command="What can you do?">
              <span class="ai-agent-card-icon info-icon" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              </span>
              <span class="ai-agent-card-title">Info</span>
              <span class="ai-agent-card-desc">"What can you do?"</span>
              <span class="ai-agent-card-desc">"What time is it?"</span>
            </button>
            <button class="ai-agent-card" type="button" id="cmdStop" data-agent-command="Stop listening">
              <span class="ai-agent-card-icon control-icon" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
              </span>
              <span class="ai-agent-card-title">Stop</span>
              <span class="ai-agent-card-desc">"Stop listening"</span>
              <span class="ai-agent-card-desc">"Cancel"</span>
            </button>
          </div>
        </article>

        <!-- ━━━━━━━━━━━━━━ SETTINGS PANEL ━━━━━━━━━━━━━━ -->
        <aside class="ai-agent-settings glass-card fade-in-section"
               id="agentSettingsPanel"
               data-agent-settings-panel
               hidden
               aria-label="Agent settings">
          <header class="ai-settings-head">
            <div>
              <p class="ai-settings-label">Configuration</p>
              <h2>Settings</h2>
            </div>
            <span class="ai-settings-chip">Live</span>
          </header>

          <div class="ai-settings-group">
            <label class="ai-settings-field">
              <span>AI Model</span>
              <select id="agentModelSelect" data-agent-model-select>
                <?php foreach ($modelOptions as $opt): ?>
                  <option value="<?= htmlspecialchars($opt) ?>" <?= $opt === $currentModel ? 'selected' : '' ?>>
                    <?= htmlspecialchars($opt) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="ai-settings-group">
            <label class="ai-toggle-row">
              <input type="checkbox" id="agentVoiceEnabled" data-agent-voice-enabled checked>
              <span>
                <strong>Voice Input</strong>
                <small>Wake with "Hey Bro" or tap the orb.</small>
              </span>
            </label>
            <label class="ai-toggle-row">
              <input type="checkbox" id="agentAutoMode" data-agent-auto-mode checked>
              <span>
                <strong>Auto Execute</strong>
                <small>Run commands automatically.</small>
              </span>
            </label>
            <label class="ai-toggle-row">
              <input type="checkbox" id="agentWhatsappEnabled" data-agent-whatsapp-enabled checked>
              <span>
                <strong>WhatsApp Relay</strong>
                <small>Route commands through WhatsApp.</small>
              </span>
            </label>
          </div>

          <p class="ai-settings-note">Voice-first. Minimal. No logs — just the current state.</p>
        </aside>

        <!-- ━━━━━━━━━━━━━━ HISTORY PANEL ━━━━━━━━━━━━━━ -->
        <aside class="ai-agent-history glass-card fade-in-section"
               id="aiAgentHistoryPanel"
               data-agent-history-panel
               hidden
               aria-label="Recent commands">
          <header class="ai-history-head">
            <div>
              <p class="ai-settings-label">Activity Log</p>
              <h2>Recent Commands</h2>
            </div>
            <span class="ai-settings-chip" data-agent-history-count>0</span>
          </header>
          <div class="ai-history-list" data-agent-history-list role="list">
            <div class="ai-history-empty">No commands yet. Tap the orb or say "Hey Bro" to start.</div>
          </div>
        </aside>

      </section><!-- /ai-agent-workspace -->
    </div>
  </main>

  <script>
    window.aiAgentBootstrap = <?= json_encode([
        'currentUserId'   => $currentUserId,
        'currentUserName' => $displayName,
        'currentUserAvatar' => $displayAvatarUrl,
        'model'           => $currentModel,
        'modelOptions'    => $modelOptions,
        'page'            => 'ai-agent',
        'theme'           => 'ai-agent',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/ai-agent.js"></script>
</body>
</html>
