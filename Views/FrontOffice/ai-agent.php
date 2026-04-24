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

$sessionUser = UserController::currentUser() ?? [];
$currentUserId = (int) ($sessionUser['id'] ?? 0);
$displayFirstName = trim((string) ($sessionUser['first_name'] ?? 'Member'));
$displayLastName = trim((string) ($sessionUser['last_name'] ?? ''));
$displayName = trim($displayFirstName . ' ' . $displayLastName);
$displayName = $displayName !== '' ? $displayName : 'Member User';
$displayEmail = (string) ($sessionUser['email'] ?? '');
$displayAvatarUrl = (string) ($sessionUser['avatar_url'] ?? '');
$initials = strtoupper(substr($displayFirstName ?: 'M', 0, 1) . substr($displayLastName ?: 'U', 0, 1));

if ($displayAvatarUrl === '') {
    $seed = rawurlencode(trim($displayFirstName . '-' . $displayLastName . '-' . $displayEmail));
    $displayAvatarUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed={$seed}";
}

$assistantBootstrap = AssistantController::bootstrapConfig();
$currentModel = trim((string) ($assistantBootstrap['model'] ?? 'openai/gpt-5.2'));
if ($currentModel === '') {
    $currentModel = 'openai/gpt-5.2';
}

$modelOptions = array_values(array_unique(array_filter([
    $currentModel,
    'openai/gpt-5.2',
    'openai/gpt-5.1',
    'openai/gpt-4.1-mini',
    'openai/o4-mini',
    'anthropic/claude-3.7-sonnet',
], static fn ($value): bool => trim((string) $value) !== '')));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Diversity.is AI Agent â€” a voice-first autonomous assistant for profile and browser tasks.">
  <title>AI Agent â€” Diversity.is</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <link rel="stylesheet" href="../../assets/css/ai-agent.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg home-page-body ai-agent-page">
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <canvas id="gradient-canvas"></canvas>

  <div class="ai-agent-ambient" aria-hidden="true">
    <span class="ai-agent-blob blob-one"></span>
    <span class="ai-agent-blob blob-two"></span>
    <span class="ai-agent-blob blob-three"></span>
    <span class="ai-agent-particles"></span>
  </div>

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
            <a href="messages.php" class="nav-dropdown-item"><i data-lucide="message-square" class="w-4 h-4"></i> Messages</a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <main class="ai-agent-main" id="main-content" tabindex="-1">
    <div class="container ai-agent-layout">
      <?php include __DIR__ . '/partials/global-sidebar.php'; ?>

      <section class="ai-agent-workspace" data-agent-shell data-agent-state="idle" data-settings-open="true">
        <article class="ai-agent-stage glass-card fade-in-section" aria-label="AI agent control center">
          <div class="ai-agent-stage-head">
            <div class="ai-agent-pill" aria-hidden="true">
              <span class="ai-agent-pill-dot"></span>
              Voice Activated
            </div>
            <button class="ai-agent-settings-toggle" type="button" data-agent-settings-toggle aria-label="Toggle settings panel">
              <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
            </button>
          </div>

          <div class="ai-agent-hero-copy">
            <p class="ai-agent-kicker">AI Assistant</p>
            <h1>Autonomous Profile Ops</h1>
            <p class="ai-agent-description">Voice, WhatsApp, and browser tasks powered by OpenRouter.</p>
            <div class="ai-agent-status-line">
              <span class="ai-agent-status-dot" data-agent-status-dot></span>
              <span data-agent-status-text>Online</span>
            </div>
          </div>

          <div class="ai-agent-orb-stack">
            <button class="ai-agent-orb" type="button" data-agent-orb aria-label="Start voice command">
              <span class="ai-agent-orb-ring ring-one"></span>
              <span class="ai-agent-orb-ring ring-two"></span>
              <span class="ai-agent-orb-ring ring-three"></span>
              <span class="ai-agent-orb-core">
                <i data-lucide="mic" class="w-9 h-9"></i>
              </span>
            </button>
            <div class="ai-agent-state-text" data-agent-state-text>Click to start</div>
            <div class="ai-agent-step-text" data-agent-step>Ready</div>
          </div>

          <div class="ai-agent-input-row" aria-label="Command input">
            <input class="ai-agent-input" type="text" placeholder="Type a command or speak..." data-agent-input>
            <button class="ai-agent-send-btn" type="button" data-agent-send>Send</button>
            <button class="ai-agent-voice-btn" type="button" data-agent-voice-toggle aria-label="Start voice input">
              <i data-lucide="mic" class="w-4 h-4"></i>
            </button>
          </div>

          <div class="ai-agent-shortcuts" aria-label="Quick commands">
            <button class="ai-agent-card" type="button" data-agent-command="Go to messages">
              <span class="ai-agent-card-title">Navigation</span>
              <span class="ai-agent-card-desc">Go to messages</span>
              <span class="ai-agent-card-desc">Open profile</span>
              <span class="ai-agent-card-desc">Go back</span>
            </button>
            <button class="ai-agent-card" type="button" data-agent-command="Search for users">
              <span class="ai-agent-card-title">Search</span>
              <span class="ai-agent-card-desc">Search for users</span>
              <span class="ai-agent-card-desc">Find topics</span>
              <span class="ai-agent-card-desc">Show results</span>
            </button>
            <button class="ai-agent-card" type="button" data-agent-command="What can you do?">
              <span class="ai-agent-card-title">Information</span>
              <span class="ai-agent-card-desc">What time is it?</span>
              <span class="ai-agent-card-desc">What can you do?</span>
              <span class="ai-agent-card-desc">Help</span>
            </button>
            <button class="ai-agent-card" type="button" data-agent-command="Stop listening">
              <span class="ai-agent-card-title">Controls</span>
              <span class="ai-agent-card-desc">Stop listening</span>
              <span class="ai-agent-card-desc">Deactivate</span>
              <span class="ai-agent-card-desc">Cancel</span>
            </button>
          </div>
        </article>

        <aside class="ai-agent-settings glass-card fade-in-section" data-agent-settings-panel aria-label="AI agent settings">
          <div class="ai-settings-head">
            <div>
              <p class="ai-settings-label">Settings</p>
              <h2>Control Surface</h2>
            </div>
            <span class="ai-settings-chip">Live</span>
          </div>

          <div class="ai-settings-group">
            <label class="ai-settings-field">
              <span>Model</span>
              <select data-agent-model-select>
                <?php foreach ($modelOptions as $modelOption): ?>
                  <option value="<?= htmlspecialchars($modelOption) ?>" <?= $modelOption === $currentModel ? 'selected' : '' ?>>
                    <?= htmlspecialchars($modelOption) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <p class="ai-settings-hint">Defaults come from the environment, and you can keep a local override for this session.</p>
          </div>

          <div class="ai-settings-group">
            <label class="ai-toggle-row">
              <input type="checkbox" data-agent-voice-enabled checked>
              <span>
                <strong>Voice on/off</strong>
                <small>Wake the orb with speech.</small>
              </span>
            </label>
            <label class="ai-toggle-row">
              <input type="checkbox" data-agent-auto-mode checked>
              <span>
                <strong>Execution mode</strong>
                <small>Auto mode is enabled.</small>
              </span>
            </label>
            <label class="ai-toggle-row">
              <input type="checkbox" data-agent-whatsapp-enabled checked>
              <span>
                <strong>WhatsApp integration</strong>
                <small>Relay commands through WhatsApp.</small>
              </span>
            </label>
          </div>

          <div class="ai-settings-note">
            Smooth, minimal, and state-driven. No logs, just the current mode.
          </div>
        </aside>
      </section>
    </div>
  </main>

  <script>
    window.aiAgentBootstrap = <?= json_encode([
        'currentUserId' => $currentUserId,
        'currentUserName' => $displayName,
        'currentUserAvatar' => $displayAvatarUrl,
        'model' => $currentModel,
        'page' => 'ai-agent',
        'theme' => 'ai-agent',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/ai-agent.js"></script>
</body>
</html>
