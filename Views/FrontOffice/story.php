<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
if (!UserController::isAuthenticated()) { $_SESSION['flash_error'] = 'Please sign in.'; header('Location: auth.php'); exit; }
$sessionUser = UserController::currentUser();
$userController = new UserController();
$userModel = $userController->getUserById((int)($sessionUser['id'] ?? 0));
$displayFirstName = (string)($userModel ? $userModel->getFirstName() : ($sessionUser['first_name'] ?? ''));
$displayLastName = (string)($userModel ? $userModel->getLastName() : ($sessionUser['last_name'] ?? ''));
$displayName = trim($displayFirstName.' '.$displayLastName) ?: 'Member User';
$displayEmail = (string)($userModel ? $userModel->getEmail() : ($sessionUser['email'] ?? ''));
$isAdminSidebar = strtolower(trim($displayEmail)) === 'admin@diversity.is';
$profileLevel = 'Level 12 Collaborator';
$reputationScore = 87;
$streakDays = 7;
$displayAvatarUrl = (string)($userModel ? ($userModel->getAvatarUrl() ?? '') : ($sessionUser['avatar_url'] ?? ''));
$initials = strtoupper(substr($displayFirstName ?: 'M', 0, 1).substr($displayLastName ?: 'U', 0, 1));
if (!$displayAvatarUrl) {
  $seed = rawurlencode(trim($displayFirstName.'-'.$displayLastName.'-'.$displayEmail));
  $displayAvatarUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed={$seed}";
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Story — Diversity.is</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/profile.css">
  <link rel="stylesheet" href="../../assets/css/profile-light.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    /* ─── Reset & base ──────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', sans-serif;
      background: #0a0a12;
      color: #fff;
      height: 100vh;
      overflow: hidden;
      user-select: none;
    }

    /* ─── Shell ─────────────────────────────────── */
    .story-shell {
      display: flex;
      height: calc(100vh - 70px);
      gap: 0;
    }

    /* ─── Left toolbar ──────────────────────────── */
    .story-toolbar {
      width: 64px;
      background: rgba(255,255,255,0.04);
      border-right: 1px solid rgba(255,255,255,0.07);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 16px 0;
      gap: 6px;
      z-index: 20;
    }

    .story-brand {
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .14em;
      color: rgba(255,255,255,0.4);
      text-transform: uppercase;
      writing-mode: vertical-rl;
      margin-bottom: 14px;
    }

    .tool-btn {
      width: 44px; height: 44px;
      border-radius: 12px;
      border: 1.5px solid transparent;
      background: none;
      color: rgba(255,255,255,0.55);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      cursor: pointer;
      transition: all .15s;
      gap: 3px;
    }
    .tool-btn svg { width: 18px; height: 18px; }
    .tool-btn .tool-label {
      font-size: 8px; font-weight: 600;
      letter-spacing: .04em; text-transform: uppercase;
      opacity: .7;
    }
    .tool-btn:hover { background: rgba(255,255,255,0.07); color: #fff; border-color: rgba(255,255,255,0.1); }
    .tool-btn.is-active { background: rgba(99,102,241,0.2); color: #a5b4fc; border-color: rgba(99,102,241,0.4); }

    .tool-sep { width: 28px; height: 1px; background: rgba(255,255,255,0.08); margin: 4px 0; }

    /* ─── Canvas area ───────────────────────────── */
    .story-canvas-area {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #0a0a12;
      position: relative;
      overflow: hidden;
    }

    /* 9:16 canvas */
    .story-canvas {
      position: relative;
      width: calc(100vh * 9 / 16);
      max-width: 390px;
      height: calc(100vh - 80px);
      max-height: calc(100vw * 16 / 9 - 200px);
      border-radius: 20px;
      overflow: hidden;
      background: #1a1a2e;
      box-shadow: 0 40px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.06);
      cursor: crosshair;
    }

    .story-canvas.tool-text { cursor: text; }
    .story-canvas.tool-move { cursor: grab; }
    .story-canvas.tool-move:active { cursor: grabbing; }

    /* Background layers */
    .story-bg {
      position: absolute; inset: 0;
      background: linear-gradient(160deg, #1a1a3e 0%, #0f0f1f 100%);
      z-index: 0;
    }
    .story-bg.has-media img,
    .story-bg.has-media video {
      width: 100%; height: 100%;
      object-fit: cover;
    }

    /* Camera feed */
    #storyCamera {
      position: absolute; inset: 0;
      width: 100%; height: 100%;
      object-fit: cover;
      z-index: 1;
      display: none;
    }
    .camera-active #storyCamera { display: block; }

    /* Canvas for drawing */
    #drawingCanvas {
      position: absolute; inset: 0;
      z-index: 10;
      pointer-events: none;
    }
    .tool-draw #drawingCanvas { pointer-events: all; }

    /* Text layers */
    .story-text-layer {
      position: absolute;
      z-index: 12;
      cursor: move;
      touch-action: none;
    }
    .story-text-layer textarea {
      background: none;
      border: 2px dashed transparent;
      outline: none;
      font-family: 'Poppins', sans-serif;
      font-size: 28px;
      font-weight: 700;
      color: #fff;
      text-shadow: 0 2px 8px rgba(0,0,0,0.6);
      resize: none;
      min-width: 60px;
      cursor: text;
      padding: 4px 8px;
      white-space: nowrap;
      overflow: hidden;
    }
    .story-text-layer textarea:focus { border-color: rgba(99,102,241,0.6); border-radius: 8px; }
    .story-text-layer.is-selected textarea { border-color: rgba(255,255,255,0.4); border-radius: 8px; }

    /* Sticker layers */
    .story-sticker-layer {
      position: absolute;
      z-index: 12;
      cursor: move;
      font-size: 48px;
      line-height: 1;
      filter: drop-shadow(0 2px 6px rgba(0,0,0,0.4));
      transition: transform .1s;
    }
    .story-sticker-layer:active { transform: scale(1.1); }

    /* delete handle */
    .layer-delete-btn {
      position: absolute;
      top: -10px; right: -10px;
      width: 20px; height: 20px;
      border-radius: 50%;
      background: #ef4444;
      color: #fff;
      font-size: 12px; font-weight: 700;
      display: none; align-items: center; justify-content: center;
      cursor: pointer; border: none; z-index: 20;
    }
    .story-text-layer.is-selected .layer-delete-btn,
    .story-sticker-layer.is-selected .layer-delete-btn { display: flex; }

    /* ─── Gradient palette (bg) ─────────────────── */
    .gradient-bg-strip {
      position: absolute;
      bottom: 16px; left: 50%;
      transform: translateX(-50%);
      display: flex; gap: 8px;
      z-index: 20;
      background: rgba(0,0,0,0.4);
      backdrop-filter: blur(8px);
      padding: 8px 12px;
      border-radius: 100px;
      border: 1px solid rgba(255,255,255,0.1);
    }
    .gradient-swatch {
      width: 28px; height: 28px; border-radius: 50%;
      cursor: pointer; border: 2px solid transparent;
      transition: all .15s; flex-shrink: 0;
    }
    .gradient-swatch.is-active { border-color: #fff; transform: scale(1.15); }

    /* ─── Right panel ───────────────────────────── */
    .story-panel {
      width: 300px;
      background: rgba(255,255,255,0.03);
      border-left: 1px solid rgba(255,255,255,0.07);
      display: flex; flex-direction: column;
      overflow: hidden;
    }

    /* Panel header */
    .story-panel-head {
      padding: 18px 18px 14px;
      border-bottom: 1px solid rgba(255,255,255,0.07);
      display: flex; align-items: center; justify-content: space-between;
    }
    .story-panel-head h2 { font-size: 15px; font-weight: 700; }

    .close-story-btn {
      width: 32px; height: 32px;
      border-radius: 10px;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.12);
      color: rgba(255,255,255,0.7);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: all .15s; text-decoration: none;
    }
    .close-story-btn:hover { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: #ef4444; }

    /* Panel sections */
    .story-panel-scroll {
      flex: 1; overflow-y: auto; padding: 16px;
    }
    .story-panel-scroll::-webkit-scrollbar { width: 4px; }
    .story-panel-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

    .panel-section { margin-bottom: 22px; }
    .panel-section-title {
      font-size: 9px; font-weight: 700;
      letter-spacing: .12em; text-transform: uppercase;
      color: rgba(255,255,255,0.35);
      margin-bottom: 10px;
      display: flex; align-items: center; gap: 6px;
    }
    .panel-section-title::after { content:''; flex:1; height:1px; background: rgba(255,255,255,0.06); }

    /* Upload zone */
    .upload-zone {
      border: 2px dashed rgba(255,255,255,0.15);
      border-radius: 14px;
      padding: 20px 16px;
      text-align: center;
      cursor: pointer;
      transition: all .2s;
      color: rgba(255,255,255,0.4);
      font-size: 12px;
    }
    .upload-zone:hover, .upload-zone.drag-over {
      border-color: rgba(99,102,241,0.6);
      background: rgba(99,102,241,0.07);
      color: rgba(255,255,255,0.7);
    }
    .upload-zone svg { width: 28px; height: 28px; margin: 0 auto 8px; display: block; opacity: .5; }
    #mediaFileInput { display: none; }

    /* Camera btn */
    .camera-toggle-btn {
      width: 100%;
      padding: 10px 14px;
      border-radius: 12px;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
      color: #fff;
      font-family: 'Poppins', sans-serif;
      font-size: 12px; font-weight: 600;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      cursor: pointer; transition: all .15s; margin-top: 8px;
    }
    .camera-toggle-btn:hover { background: rgba(99,102,241,0.12); border-color: rgba(99,102,241,0.3); }
    .camera-toggle-btn.is-active { background: rgba(99,102,241,0.2); border-color: rgba(99,102,241,0.5); color: #a5b4fc; }
    .camera-toggle-btn svg { width: 15px; height: 15px; }

    /* Font options */
    .font-options {
      display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px;
    }
    .font-opt-btn {
      padding: 5px 12px;
      border-radius: 8px;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
      color: rgba(255,255,255,0.6);
      font-family: 'Poppins', sans-serif;
      font-size: 11px; font-weight: 600;
      cursor: pointer; transition: all .15s;
    }
    .font-opt-btn.is-active { background: rgba(99,102,241,0.2); border-color: rgba(99,102,241,0.5); color: #a5b4fc; }
    .font-opt-btn:hover:not(.is-active) { border-color: rgba(255,255,255,0.2); color: #fff; }

    /* Color row */
    .color-row {
      display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
    }
    .color-swatch {
      width: 24px; height: 24px; border-radius: 50%;
      cursor: pointer; border: 2px solid transparent; transition: all .15s;
    }
    .color-swatch.is-active { border-color: #fff; transform: scale(1.15); }
    .color-custom-input {
      width: 30px; height: 24px;
      border-radius: 6px; border: 1px solid rgba(255,255,255,0.15);
      cursor: pointer; background: none; padding: 0;
    }

    /* Text size slider */
    .size-slider {
      -webkit-appearance: none; width: 100%; height: 4px;
      border-radius: 2px; background: rgba(255,255,255,0.15);
      outline: none; margin-top: 4px;
    }
    .size-slider::-webkit-slider-thumb {
      -webkit-appearance: none; width: 16px; height: 16px;
      border-radius: 50%; background: #6366f1; cursor: pointer;
      border: 2px solid #fff;
    }

    /* Brush options */
    .brush-options { display: flex; gap: 8px; margin-bottom: 10px; }
    .brush-opt-btn {
      flex: 1; padding: 8px 6px;
      border-radius: 10px;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
      color: rgba(255,255,255,0.6);
      font-family: 'Poppins', sans-serif;
      font-size: 11px; font-weight: 600;
      cursor: pointer; transition: all .15s;
      display: flex; flex-direction: column; align-items: center; gap: 5px;
    }
    .brush-opt-btn.is-active { background: rgba(99,102,241,0.2); border-color: rgba(99,102,241,0.5); color: #a5b4fc; }
    .brush-size-preview {
      width: 100%; height: 6px;
      border-radius: 3px;
      background: rgba(255,255,255,0.4);
      display: flex; align-items: center; justify-content: center;
    }
    .brush-size-preview span {
      border-radius: 50%;
      background: #fff; display: block;
    }

    /* Sticker grid */
    .sticker-grid {
      display: grid; grid-template-columns: repeat(6, 1fr);
      gap: 6px;
    }
    .sticker-item {
      font-size: 24px; text-align: center;
      padding: 6px; border-radius: 8px;
      cursor: pointer; transition: all .15s;
      border: 1px solid transparent;
      line-height: 1;
    }
    .sticker-item:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); transform: scale(1.15); }

    /* Music */
    .music-track-row {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 12px;
      background: rgba(255,255,255,0.05);
      border: 1px solid rgba(255,255,255,0.08);
      cursor: pointer; transition: all .15s;
      margin-bottom: 6px;
    }
    .music-track-row:hover { background: rgba(99,102,241,0.1); border-color: rgba(99,102,241,0.25); }
    .music-track-row.is-playing { background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.4); }
    .music-thumb {
      width: 36px; height: 36px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; flex-shrink: 0;
    }
    .music-info { flex: 1; min-width: 0; }
    .music-title { font-size: 12px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .music-artist { font-size: 10px; color: rgba(255,255,255,0.4); margin-top: 1px; }
    .music-play-btn {
      width: 28px; height: 28px; border-radius: 50%;
      background: rgba(99,102,241,0.3); border: none;
      color: #a5b4fc; font-size: 14px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; flex-shrink: 0; transition: all .15s;
    }
    .music-play-btn:hover { background: rgba(99,102,241,0.5); }

    /* Filters */
    .filter-strip {
      display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px;
    }
    .filter-strip::-webkit-scrollbar { height: 3px; }
    .filter-strip::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }
    .filter-btn {
      flex-shrink: 0;
      width: 60px;
      display: flex; flex-direction: column; align-items: center; gap: 5px;
      cursor: pointer;
    }
    .filter-preview {
      width: 60px; height: 78px;
      border-radius: 10px;
      border: 2px solid transparent;
      background: #222;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
      transition: all .15s;
      font-size: 10px; color: rgba(255,255,255,0.3);
    }
    .filter-btn:hover .filter-preview { border-color: rgba(255,255,255,0.3); }
    .filter-btn.is-active .filter-preview { border-color: #6366f1; }
    .filter-name { font-size: 9px; font-weight: 600; color: rgba(255,255,255,0.5); text-align: center; }
    .filter-btn.is-active .filter-name { color: #a5b4fc; }

    /* ─── Bottom publish bar ────────────────────── */
    .story-publish-bar {
      padding: 14px 16px;
      border-top: 1px solid rgba(255,255,255,0.07);
      display: flex; flex-direction: column; gap: 8px;
    }

    .publish-target-row {
      display: flex; gap: 8px;
    }
    .publish-target-btn {
      flex: 1;
      padding: 10px 8px;
      border-radius: 12px;
      font-family: 'Poppins', sans-serif;
      font-size: 11px; font-weight: 600;
      display: flex; align-items: center; justify-content: center; gap: 6px;
      cursor: pointer; transition: all .2s; border: 1.5px solid;
    }
    .publish-target-btn.public {
      background: linear-gradient(135deg, #6366f1, #7c3aed);
      border-color: transparent;
      color: #fff;
    }
    .publish-target-btn.public:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.35); }
    .publish-target-btn.friends {
      background: rgba(16,185,129,0.12);
      border-color: rgba(16,185,129,0.3);
      color: #34d399;
    }
    .publish-target-btn.friends:hover { background: rgba(16,185,129,0.2); }
    .publish-target-btn svg { width: 14px; height: 14px; }

    .story-discard-btn {
      width: 100%;
      padding: 9px;
      border-radius: 10px;
      border: 1px solid rgba(239,68,68,0.2);
      background: rgba(239,68,68,0.06);
      color: rgba(239,68,68,0.7);
      font-family: 'Poppins', sans-serif;
      font-size: 11px; font-weight: 600;
      cursor: pointer; transition: all .15s;
      display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .story-discard-btn:hover { background: rgba(239,68,68,0.12); color: #ef4444; border-color: rgba(239,68,68,0.35); }

    /* ─── Progress bar (publishing) ─────────────── */
    .publish-progress {
      height: 3px; border-radius: 2px;
      background: rgba(255,255,255,0.08);
      overflow: hidden; margin-bottom: 4px; display: none;
    }
    .publish-progress-fill {
      height: 100%; width: 0;
      background: linear-gradient(90deg, #6366f1, #8b5cf6);
      border-radius: 2px;
      transition: width .3s;
    }

    /* ─── Overlay toast ─────────────────────────── */
    .story-toast {
      position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
      background: rgba(15,15,25,0.92);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.12);
      color: #fff; font-size: 12px; font-weight: 500;
      padding: 10px 20px; border-radius: 100px;
      pointer-events: none; opacity: 0;
      transition: opacity .25s; z-index: 9999;
    }
    .story-toast.is-visible { opacity: 1; }

    /* ─── Cursor dot for draw tool ──────────────── */
    .story-canvas.tool-draw { cursor: none; }
    #drawCursor {
      position: fixed;
      pointer-events: none;
      border-radius: 50%;
      background: rgba(255,255,255,0.8);
      transform: translate(-50%,-50%);
      z-index: 9999;
      display: none;
    }
  </style>
</head>
<body class="grid-dot-bg home-page-body">

  <!-- custom draw cursor -->
  <div id="drawCursor" style="width:12px;height:12px;"></div>

  <div class="story-toast" id="storyToast"></div>

  <nav class="navbar" id="navbar" aria-label="Primary navigation">
    <div class="container">
      <a href="home.php" class="navbar-brand" aria-label="Diversity home">
        <span class="brand-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </span>
        Diversity.is
      </a>
      <div class="navbar-actions">
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
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
    </div>
  </nav>

  <main style="padding-top: 70px; height: 100vh; overflow: hidden;">
    <div class="container profile-page-layout" style="gap: 0; height: 100%; max-height: calc(100vh - 70px);">

      <aside class="home-left glass-card" aria-label="Navigation" style="overflow-y: auto;">
        <div class="left-profile">
          <div class="left-avatar"><?= htmlspecialchars($initials) ?></div>
          <div>
            <h4><?= htmlspecialchars($displayName) ?></h4>
            <p><?= htmlspecialchars($profileLevel) ?></p>
          </div>
        </div>
        <nav class="left-nav">
          <a href="home.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> Home Feed</a>
          <a href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg> Social</a>
          <a href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> Profile</a>
          <a href="messages.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7.5A5.5 5.5 0 0 0 14.5 2h-5A5.5 5.5 0 0 0 4 7.5v4A5.5 5.5 0 0 0 9.5 17H13l4 4v-4h.5A5.5 5.5 0 0 0 23 11.5v-4A5.5 5.5 0 0 0 20 7.5Z"/></svg> Messages</a>
          <a href="JobOffer.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg> Job Offers</a>
          <a href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg> Projects</a>
          <a href="reviews.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l2.8 5.7 6.3.9-4.6 4.4 1.1 6.3L12 17.3 6.4 20.3l1.1-6.3L2.9 9.6l6.3-.9L12 3z"/></svg> Contracts</a>
          <a href="challenges.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg> Challenges</a>
          <?php if ($isAdminSidebar): ?>
            <a href="../BackOffice/dashboardUser.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg> Dashboard</a>
          <?php endif; ?>
        </nav>
        <div class="left-gamification">
          <div class="mini-score"><span>Reputation</span><strong><?= (int)$reputationScore ?></strong></div>
          <div class="mini-score"><span>Daily Streak 🔥</span><strong><?= (int)$streakDays ?> days</strong></div>
        </div>
      </aside>

      <section class="profile-content-area" style="padding: 0; overflow: hidden; height: 100%;">
        <div class="story-shell">

    <!-- ── Left toolbar ─────────────────────────── -->
    <div class="story-toolbar">
      <div class="story-brand">Story</div>

      <button class="tool-btn" id="toolText" data-tool="text" title="Add text (T)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
        <span class="tool-label">Text</span>
      </button>

      <button class="tool-btn" id="toolDraw" data-tool="draw" title="Draw (D)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        <span class="tool-label">Draw</span>
      </button>

      <button class="tool-btn" id="toolStickers" data-tool="stickers" title="Stickers (S)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
        <span class="tool-label">Stick</span>
      </button>

      <button class="tool-btn" id="toolMusic" data-tool="music" title="Music (M)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
        <span class="tool-label">Music</span>
      </button>

      <button class="tool-btn" id="toolFilters" data-tool="filters" title="Filters (F)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg>
        <span class="tool-label">Filter</span>
      </button>

      <div class="tool-sep"></div>

      <button class="tool-btn" id="undoBtn" title="Undo (Ctrl+Z)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
        <span class="tool-label">Undo</span>
      </button>

      <button class="tool-btn" id="clearDrawBtn" title="Clear drawing">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
        <span class="tool-label">Clear</span>
      </button>
    </div>

    <!-- ── Canvas ────────────────────────────────── -->
    <div class="story-canvas-area">
      <div class="story-canvas" id="storyCanvas">
        <!-- Background -->
        <div class="story-bg" id="storyBg">
          <img id="bgImage" style="display:none;" alt="">
          <video id="bgVideo" style="display:none;" muted loop playsinline></video>
        </div>

        <!-- Camera -->
        <video id="storyCamera" autoplay muted playsinline></video>

        <!-- Drawing canvas -->
        <canvas id="drawingCanvas"></canvas>

        <!-- Text/sticker layers added dynamically -->

        <!-- Gradient swatches -->
        <div class="gradient-bg-strip" id="gradientStrip"></div>
      </div>
    </div>

    <!-- ── Right panel ───────────────────────────── -->
    <div class="story-panel">

      <div class="story-panel-head">
        <h2>Create Story</h2>
        <a href="profile.php" class="close-story-btn" id="closeStoryBtn" title="Back to profile">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
        </a>
      </div>

      <div class="story-panel-scroll" id="storyPanelScroll">

        <!-- MEDIA section (always visible) -->
        <div class="panel-section">
          <div class="panel-section-title">Media</div>
          <div class="upload-zone" id="uploadZone">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <strong style="display:block;margin-bottom:4px;font-size:12px;font-weight:600;color:rgba(255,255,255,.6);">Drop photo or video</strong>
            <span>or click to browse</span>
          </div>
          <input type="file" id="mediaFileInput" accept="image/*,video/*">
          <button class="camera-toggle-btn" id="cameraToggleBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            Open Camera
          </button>
        </div>

        <!-- TEXT options -->
        <div class="panel-section" id="panelText" style="display:none;">
          <div class="panel-section-title">Text</div>
          <div class="font-options" id="fontOptions">
            <button class="font-opt-btn is-active" data-font="Poppins" style="font-family:Poppins;">Classic</button>
            <button class="font-opt-btn" data-font="Georgia,serif" style="font-family:Georgia,serif;">Serif</button>
            <button class="font-opt-btn" data-font="'Courier New',monospace" style="font-family:Courier New;">Mono</button>
            <button class="font-opt-btn" data-font="Impact,sans-serif" style="font-family:Impact;">Strong</button>
          </div>
          <div class="color-row" id="textColorRow" style="margin-bottom:12px;"></div>
          <div style="margin-bottom:6px;">
            <label style="font-size:10px;color:rgba(255,255,255,.4);font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Size</label>
            <input type="range" class="size-slider" id="textSizeSlider" min="16" max="72" value="28">
          </div>
          <button class="camera-toggle-btn" id="addTextBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path d="M12 5v14M5 12h14"/></svg>
            Add Text to Canvas
          </button>
        </div>

        <!-- DRAW options -->
        <div class="panel-section" id="panelDraw" style="display:none;">
          <div class="panel-section-title">Draw</div>
          <div class="brush-options">
            <button class="brush-opt-btn is-active" data-brush="round" data-size="4">
              <div class="brush-size-preview"><span style="width:4px;height:4px;"></span></div>
              Thin
            </button>
            <button class="brush-opt-btn" data-brush="round" data-size="10">
              <div class="brush-size-preview"><span style="width:10px;height:10px;"></span></div>
              Medium
            </button>
            <button class="brush-opt-btn" data-brush="round" data-size="22">
              <div class="brush-size-preview"><span style="width:18px;height:18px;"></span></div>
              Thick
            </button>
            <button class="brush-opt-btn" data-brush="eraser" data-size="20">
              <div class="brush-size-preview"><span style="width:12px;height:12px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);"></span></div>
              Erase
            </button>
          </div>
          <div class="color-row" id="drawColorRow" style="margin-bottom:10px;"></div>
          <div>
            <label style="font-size:10px;color:rgba(255,255,255,.4);font-weight:600;text-transform:uppercase;letter-spacing:.08em;">Opacity</label>
            <input type="range" class="size-slider" id="drawOpacitySlider" min="10" max="100" value="100">
          </div>
        </div>

        <!-- STICKERS -->
        <div class="panel-section" id="panelStickers" style="display:none;">
          <div class="panel-section-title">Stickers</div>
          <div class="sticker-grid" id="stickerGrid"></div>
        </div>

        <!-- MUSIC -->
        <div class="panel-section" id="panelMusic" style="display:none;">
          <div class="panel-section-title">Music</div>
          <div id="musicList"></div>
          <p style="font-size:10px;color:rgba(255,255,255,.3);margin-top:8px;line-height:1.5;">Music is for preview only. Actual tracks require licensing.</p>
        </div>

        <!-- FILTERS -->
        <div class="panel-section" id="panelFilters" style="display:none;">
          <div class="panel-section-title">Filters</div>
          <div class="filter-strip" id="filterStrip"></div>
        </div>

      </div><!-- /scroll -->

      <!-- Publish bar -->
      <div class="story-publish-bar">
        <div class="publish-progress" id="publishProgress">
          <div class="publish-progress-fill" id="publishProgressFill"></div>
        </div>
        <div class="publish-target-row">
          <button class="publish-target-btn public" id="publishPublicBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            Your Story
          </button>
          <button class="publish-target-btn friends" id="publishFriendsBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Close Friends
          </button>
        </div>
        <button class="story-discard-btn" id="discardBtn">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
          Discard &amp; Go Back
        </button>
      </div>
    </div>

      </div><!-- /story-shell -->
      </section>
    </div>
  </main>

  <script>
  /* ═══════════════════════════════════════════════════
     Data constants
  ═══════════════════════════════════════════════════ */
  const GRADIENTS = [
    { label:'Midnight', css:'linear-gradient(160deg,#1a1a3e,#0f0f1f)' },
    { label:'Sunset',   css:'linear-gradient(160deg,#f093fb,#f5576c)' },
    { label:'Ocean',    css:'linear-gradient(160deg,#4facfe,#00f2fe)' },
    { label:'Forest',   css:'linear-gradient(160deg,#43e97b,#38f9d7)' },
    { label:'Peach',    css:'linear-gradient(160deg,#ffecd2,#fcb69f)' },
    { label:'Cosmic',   css:'linear-gradient(160deg,#a18cd1,#fbc2eb)' },
    { label:'Fire',     css:'linear-gradient(160deg,#f6d365,#fda085)' },
    { label:'Neon',     css:'linear-gradient(160deg,#30cfd0,#330867)' },
  ];

  const COLORS = ['#ffffff','#000000','#ef4444','#f97316','#eab308','#22c55e','#3b82f6','#8b5cf6','#ec4899','#06b6d4'];

  const STICKERS = [
    '❤️','🔥','⭐','🎉','👍','😂','🥰','😎','🤩','🙌','✨','💯',
    '🎯','🚀','🏆','💎','🌈','🦋','🌸','🍀','🎵','🎸','🎨','💡',
    '📍','🗺️','💬','📸','🎬','🌍','🌙','☀️','⚡','🌊','🎪','🧨',
  ];

  const FILTERS = [
    { name:'Normal',    css:'none' },
    { name:'Vivid',     css:'saturate(1.8) contrast(1.1)' },
    { name:'Muted',     css:'saturate(0.5) brightness(0.9)' },
    { name:'Warm',      css:'sepia(0.4) saturate(1.3) brightness(1.05)' },
    { name:'Cool',      css:'hue-rotate(20deg) saturate(1.2)' },
    { name:'B&W',       css:'grayscale(1) contrast(1.1)' },
    { name:'Fade',      css:'opacity(0.85) brightness(1.1) saturate(0.7)' },
    { name:'Vivid+',    css:'saturate(2.2) contrast(1.3) brightness(1.05)' },
  ];

  const MUSIC_TRACKS = [
    { emoji:'🎵', title:'Lofi Chill', artist:'Various Artists', bpm:80 },
    { emoji:'🎸', title:'Upbeat Pop',  artist:'Studio Collection', bpm:128 },
    { emoji:'🌙', title:'Night Drive', artist:'Synthwave Vol.1', bpm:98 },
    { emoji:'🌊', title:'Ocean Waves', artist:'Ambient Series', bpm:60 },
    { emoji:'⚡', title:'Hype Beat',   artist:'Energy Pack', bpm:145 },
    { emoji:'🎻', title:'Classical Mood', artist:'Orchestra Live', bpm:72 },
  ];

  /* ═══════════════════════════════════════════════════
     State
  ═══════════════════════════════════════════════════ */
  const ST = {
    activeTool: null,
    currentFont: 'Poppins',
    currentTextColor: '#ffffff',
    currentTextSize: 28,
    currentDrawColor: '#ffffff',
    currentBrushSize: 4,
    currentBrushMode: 'round',
    currentOpacity: 1,
    currentFilter: 'none',
    currentBg: GRADIENTS[0].css,
    cameraStream: null,
    cameraOn: false,
    isDrawing: false,
    lastX: 0, lastY: 0,
    drawHistory: [],
    layers: [],
    selectedLayer: null,
    playingTrack: null,
  };

  /* ═══════════════════════════════════════════════════
     DOM refs
  ═══════════════════════════════════════════════════ */
  const canvas     = document.getElementById('storyCanvas');
  const storyBg    = document.getElementById('storyBg');
  const bgImage    = document.getElementById('bgImage');
  const bgVideo    = document.getElementById('bgVideo');
  const storyCamera = document.getElementById('storyCamera');
  const drawCanvas = document.getElementById('drawingCanvas');
  const ctx        = drawCanvas.getContext('2d');
  const drawCursor = document.getElementById('drawCursor');

  /* ═══════════════════════════════════════════════════
     Toast
  ═══════════════════════════════════════════════════ */
  function showToast(msg) {
    const el = document.getElementById('storyToast');
    el.textContent = msg;
    el.classList.add('is-visible');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('is-visible'), 2500);
  }

  /* ═══════════════════════════════════════════════════
     Init canvas size
  ═══════════════════════════════════════════════════ */
  function resizeDrawCanvas() {
    drawCanvas.width  = canvas.offsetWidth;
    drawCanvas.height = canvas.offsetHeight;
  }
  window.addEventListener('resize', resizeDrawCanvas);

  /* ═══════════════════════════════════════════════════
     Gradient strip
  ═══════════════════════════════════════════════════ */
  function buildGradientStrip() {
    const strip = document.getElementById('gradientStrip');
    GRADIENTS.forEach((g, i) => {
      const sw = document.createElement('div');
      sw.className = 'gradient-swatch' + (i===0?' is-active':'');
      sw.style.background = g.css;
      sw.title = g.label;
      sw.addEventListener('click', () => {
        document.querySelectorAll('.gradient-swatch').forEach(s=>s.classList.remove('is-active'));
        sw.classList.add('is-active');
        ST.currentBg = g.css;
        applyBg();
      });
      strip.appendChild(sw);
    });
  }

  function applyBg() {
    storyBg.style.background = ST.currentBg;
    storyBg.classList.toggle('has-media', !!(bgImage.src && bgImage.style.display!=='none') || (bgVideo.src && bgVideo.style.display!=='none'));
  }

  /* ═══════════════════════════════════════════════════
     Color rows
  ═══════════════════════════════════════════════════ */
  function buildColorRow(containerId, onPick, initial='#ffffff') {
    const row = document.getElementById(containerId); if (!row) return;
    COLORS.forEach(c => {
      const sw = document.createElement('div');
      sw.className = 'color-swatch' + (c===initial?' is-active':'');
      sw.style.background = c;
      sw.title = c;
      sw.addEventListener('click', () => {
        row.querySelectorAll('.color-swatch').forEach(s=>s.classList.remove('is-active'));
        sw.classList.add('is-active');
        onPick(c);
      });
      row.appendChild(sw);
    });
    // custom
    const ci = document.createElement('input');
    ci.type = 'color'; ci.className = 'color-custom-input'; ci.value = initial;
    ci.addEventListener('input', () => {
      row.querySelectorAll('.color-swatch').forEach(s=>s.classList.remove('is-active'));
      onPick(ci.value);
    });
    row.appendChild(ci);
  }

  /* ═══════════════════════════════════════════════════
     Stickers grid
  ═══════════════════════════════════════════════════ */
  function buildStickerGrid() {
    const grid = document.getElementById('stickerGrid');
    STICKERS.forEach(s => {
      const el = document.createElement('div');
      el.className = 'sticker-item'; el.textContent = s;
      el.addEventListener('click', () => addStickerLayer(s));
      grid.appendChild(el);
    });
  }

  /* ═══════════════════════════════════════════════════
     Music list
  ═══════════════════════════════════════════════════ */
  function buildMusicList() {
    const list = document.getElementById('musicList');
    MUSIC_TRACKS.forEach((t, i) => {
      const row = document.createElement('div');
      row.className = 'music-track-row'; row.dataset.idx = i;
      row.innerHTML = `
        <div class="music-thumb" style="background:rgba(99,102,241,0.15);">${t.emoji}</div>
        <div class="music-info">
          <div class="music-title">${t.title}</div>
          <div class="music-artist">${t.artist} · ${t.bpm} BPM</div>
        </div>
        <button class="music-play-btn" data-idx="${i}">▶</button>
      `;
      row.addEventListener('click', () => toggleTrack(i, row));
      list.appendChild(row);
    });
  }

  function toggleTrack(idx, row) {
    const rows = document.querySelectorAll('.music-track-row');
    if (ST.playingTrack === idx) {
      ST.playingTrack = null;
      rows.forEach(r => { r.classList.remove('is-playing'); r.querySelector('.music-play-btn').textContent = '▶'; });
      showToast('Music removed');
    } else {
      ST.playingTrack = idx;
      rows.forEach(r => { r.classList.remove('is-playing'); r.querySelector('.music-play-btn').textContent = '▶'; });
      row.classList.add('is-playing');
      row.querySelector('.music-play-btn').textContent = '⏸';
      showToast(`🎵 ${MUSIC_TRACKS[idx].title} added`);
    }
  }

  /* ═══════════════════════════════════════════════════
     Filters
  ═══════════════════════════════════════════════════ */
  function buildFilterStrip() {
    const strip = document.getElementById('filterStrip');
    FILTERS.forEach((f, i) => {
      const btn = document.createElement('div');
      btn.className = 'filter-btn' + (i===0?' is-active':'');
      btn.innerHTML = `<div class="filter-preview" style="filter:${f.css};">
        <span>Aa</span>
      </div><div class="filter-name">${f.name}</div>`;
      btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('is-active'));
        btn.classList.add('is-active');
        ST.currentFilter = f.css;
        drawCanvas.style.filter = f.css;
        storyBg.style.filter = f.css;
      });
      strip.appendChild(btn);
    });
  }

  /* ═══════════════════════════════════════════════════
     Tool switching
  ═══════════════════════════════════════════════════ */
  const PANELS = { text:'panelText', draw:'panelDraw', stickers:'panelStickers', music:'panelMusic', filters:'panelFilters' };

  function activateTool(tool) {
    if (ST.activeTool === tool) {
      // toggle off
      ST.activeTool = null;
      document.querySelectorAll('.tool-btn[data-tool]').forEach(b=>b.classList.remove('is-active'));
      Object.values(PANELS).forEach(id => { const el=document.getElementById(id); if(el) el.style.display='none'; });
      canvas.className = 'story-canvas';
      drawCursor.style.display = 'none';
      return;
    }
    ST.activeTool = tool;
    document.querySelectorAll('.tool-btn[data-tool]').forEach(b => {
      b.classList.toggle('is-active', b.dataset.tool === tool);
    });
    Object.entries(PANELS).forEach(([key, id]) => {
      const el = document.getElementById(id);
      if (el) el.style.display = key===tool ? 'block' : 'none';
    });
    canvas.className = `story-canvas tool-${tool}`;
    if (tool==='draw') {
      drawCursor.style.display = 'block';
      updateDrawCursorSize();
    } else {
      drawCursor.style.display = 'none';
    }
  }

  document.querySelectorAll('.tool-btn[data-tool]').forEach(btn => {
    btn.addEventListener('click', () => activateTool(btn.dataset.tool));
  });

  /* ═══════════════════════════════════════════════════
     Drawing
  ═══════════════════════════════════════════════════ */
  function getCanvasPos(e) {
    const rect = drawCanvas.getBoundingClientRect();
    const src = e.touches ? e.touches[0] : e;
    return { x: src.clientX - rect.left, y: src.clientY - rect.top };
  }

  function updateDrawCursorSize() {
    const sz = ST.currentBrushMode === 'eraser' ? ST.currentBrushSize : ST.currentBrushSize;
    drawCursor.style.width  = sz + 'px';
    drawCursor.style.height = sz + 'px';
    drawCursor.style.background = ST.currentBrushMode === 'eraser' ? 'rgba(255,255,255,0.3)' : ST.currentDrawColor;
  }

  document.addEventListener('mousemove', e => {
    if (ST.activeTool === 'draw') {
      drawCursor.style.left = e.clientX + 'px';
      drawCursor.style.top  = e.clientY + 'px';
    }
  });

  drawCanvas.addEventListener('mousedown', startDraw);
  drawCanvas.addEventListener('mousemove', doDraw);
  drawCanvas.addEventListener('mouseup', endDraw);
  drawCanvas.addEventListener('mouseleave', endDraw);
  drawCanvas.addEventListener('touchstart', e => { e.preventDefault(); startDraw(e); }, { passive:false });
  drawCanvas.addEventListener('touchmove', e => { e.preventDefault(); doDraw(e); }, { passive:false });
  drawCanvas.addEventListener('touchend', endDraw);

  function startDraw(e) {
    if (ST.activeTool !== 'draw') return;
    ST.isDrawing = true;
    const pos = getCanvasPos(e);
    ST.lastX = pos.x; ST.lastY = pos.y;
    saveDrawState();
  }

  function doDraw(e) {
    if (!ST.isDrawing || ST.activeTool !== 'draw') return;
    const pos = getCanvasPos(e);
    ctx.globalAlpha = ST.currentOpacity;
    if (ST.currentBrushMode === 'eraser') {
      ctx.globalCompositeOperation = 'destination-out';
      ctx.strokeStyle = 'rgba(0,0,0,1)';
    } else {
      ctx.globalCompositeOperation = 'source-over';
      ctx.strokeStyle = ST.currentDrawColor;
    }
    ctx.lineWidth = ST.currentBrushSize;
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(ST.lastX, ST.lastY);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    ST.lastX = pos.x; ST.lastY = pos.y;
  }

  function endDraw() { ST.isDrawing = false; ctx.globalCompositeOperation = 'source-over'; }

  function saveDrawState() {
    ST.drawHistory.push(ctx.getImageData(0, 0, drawCanvas.width, drawCanvas.height));
    if (ST.drawHistory.length > 30) ST.drawHistory.shift();
  }

  document.getElementById('undoBtn')?.addEventListener('click', () => {
    if (ST.drawHistory.length > 0) {
      ctx.putImageData(ST.drawHistory.pop(), 0, 0);
    } else { showToast('Nothing to undo'); }
  });

  document.getElementById('clearDrawBtn')?.addEventListener('click', () => {
    saveDrawState();
    ctx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
    showToast('Drawing cleared');
  });

  /* ═══════════════════════════════════════════════════
     Text layers
  ═══════════════════════════════════════════════════ */
  function addTextLayer(text='Your text here') {
    const layer = document.createElement('div');
    layer.className = 'story-text-layer';
    layer.style.left = '15%'; layer.style.top = '40%';

    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.fontFamily = ST.currentFont;
    ta.style.fontSize = ST.currentTextSize + 'px';
    ta.style.color = ST.currentTextColor;
    ta.rows = 1;
    ta.addEventListener('input', () => { ta.style.width = Math.max(80, ta.scrollWidth) + 'px'; });

    const delBtn = document.createElement('button');
    delBtn.className = 'layer-delete-btn'; delBtn.textContent = '×';
    delBtn.addEventListener('click', () => { layer.remove(); ST.selectedLayer = null; });

    layer.appendChild(ta);
    layer.appendChild(delBtn);
    canvas.appendChild(layer);
    makeDraggable(layer);
    layer.addEventListener('click', e => {
      e.stopPropagation();
      selectLayer(layer);
    });
    selectLayer(layer);
    ta.focus();
    ta.select();
    ST.layers.push(layer);
  }

  document.getElementById('addTextBtn')?.addEventListener('click', () => addTextLayer());

  canvas.addEventListener('click', e => {
    if (ST.activeTool === 'text' && e.target === canvas) addTextLayer();
  });

  /* ═══════════════════════════════════════════════════
     Sticker layers
  ═══════════════════════════════════════════════════ */
  function addStickerLayer(emoji) {
    const layer = document.createElement('div');
    layer.className = 'story-sticker-layer';
    layer.textContent = emoji;
    layer.style.left = Math.random() * 60 + 10 + '%';
    layer.style.top  = Math.random() * 50 + 20 + '%';

    const delBtn = document.createElement('button');
    delBtn.className = 'layer-delete-btn'; delBtn.textContent = '×';
    delBtn.style.fontSize = '12px';
    delBtn.addEventListener('click', () => { layer.remove(); ST.selectedLayer = null; });
    layer.appendChild(delBtn);

    canvas.appendChild(layer);
    makeDraggable(layer);
    layer.addEventListener('click', e => { e.stopPropagation(); selectLayer(layer); });
    selectLayer(layer);
    ST.layers.push(layer);
    showToast(`${emoji} added`);
  }

  /* ═══════════════════════════════════════════════════
     Layer selection
  ═══════════════════════════════════════════════════ */
  function selectLayer(layer) {
    if (ST.selectedLayer && ST.selectedLayer !== layer) {
      ST.selectedLayer.classList.remove('is-selected');
    }
    ST.selectedLayer = layer;
    layer.classList.add('is-selected');
  }

  canvas.addEventListener('click', () => {
    if (ST.selectedLayer) {
      ST.selectedLayer.classList.remove('is-selected');
      ST.selectedLayer = null;
    }
  });

  /* ═══════════════════════════════════════════════════
     Drag layers
  ═══════════════════════════════════════════════════ */
  function makeDraggable(el) {
    let startX, startY, startLeft, startTop, dragging = false;
    el.addEventListener('mousedown', e => {
      if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'BUTTON') return;
      dragging = true;
      startX = e.clientX; startY = e.clientY;
      const rect = el.getBoundingClientRect();
      const cRect = canvas.getBoundingClientRect();
      startLeft = rect.left - cRect.left; startTop = rect.top - cRect.top;
      e.preventDefault();
    });
    document.addEventListener('mousemove', e => {
      if (!dragging) return;
      el.style.left = (startLeft + e.clientX - startX) + 'px';
      el.style.top  = (startTop  + e.clientY - startY) + 'px';
    });
    document.addEventListener('mouseup', () => { dragging = false; });

    // touch
    el.addEventListener('touchstart', e => {
      if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'BUTTON') return;
      const t = e.touches[0];
      dragging = true; startX = t.clientX; startY = t.clientY;
      const rect = el.getBoundingClientRect(), cRect = canvas.getBoundingClientRect();
      startLeft = rect.left - cRect.left; startTop = rect.top - cRect.top;
      e.preventDefault();
    }, { passive:false });
    document.addEventListener('touchmove', e => {
      if (!dragging) return;
      const t = e.touches[0];
      el.style.left = (startLeft + t.clientX - startX) + 'px';
      el.style.top  = (startTop  + t.clientY - startY) + 'px';
    });
    document.addEventListener('touchend', () => { dragging = false; });
  }

  /* ═══════════════════════════════════════════════════
     Media upload
  ═══════════════════════════════════════════════════ */
  document.getElementById('uploadZone').addEventListener('click', () => document.getElementById('mediaFileInput').click());

  document.getElementById('mediaFileInput').addEventListener('change', function() {
    const file = this.files[0]; if (!file) return;
    const url = URL.createObjectURL(file);
    if (file.type.startsWith('image/')) {
      bgImage.src = url; bgImage.style.display = 'block';
      bgVideo.style.display = 'none';
      storyBg.classList.add('has-media');
      showToast('📷 Photo added');
    } else if (file.type.startsWith('video/')) {
      bgVideo.src = url; bgVideo.style.display = 'block'; bgVideo.play();
      bgImage.style.display = 'none';
      storyBg.classList.add('has-media');
      showToast('🎥 Video added');
    }
    stopCamera();
  });

  // Drag and drop
  const ua = document.getElementById('uploadZone');
  ['dragenter','dragover'].forEach(ev => ua.addEventListener(ev, e => { e.preventDefault(); ua.classList.add('drag-over'); }));
  ['dragleave','drop'].forEach(ev => ua.addEventListener(ev, () => ua.classList.remove('drag-over')));
  ua.addEventListener('drop', e => {
    e.preventDefault();
    const file = e.dataTransfer?.files?.[0]; if (!file) return;
    const input = document.getElementById('mediaFileInput');
    const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
    input.dispatchEvent(new Event('change'));
  });

  /* ═══════════════════════════════════════════════════
     Camera
  ═══════════════════════════════════════════════════ */
  document.getElementById('cameraToggleBtn').addEventListener('click', toggleCamera);

  async function toggleCamera() {
    if (ST.cameraOn) { stopCamera(); return; }
    try {
      ST.cameraStream = await navigator.mediaDevices.getUserMedia({ video:{ facingMode:'user' }, audio:false });
      storyCamera.srcObject = ST.cameraStream;
      canvas.classList.add('camera-active');
      ST.cameraOn = true;
      const btn = document.getElementById('cameraToggleBtn');
      btn.classList.add('is-active');
      btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> Stop Camera`;
      bgImage.style.display = 'none'; bgVideo.style.display = 'none';
      showToast('📸 Camera on — take a photo!');
    } catch(e) { showToast('Camera access denied'); }
  }

  function stopCamera() {
    if (ST.cameraStream) { ST.cameraStream.getTracks().forEach(t=>t.stop()); ST.cameraStream = null; }
    canvas.classList.remove('camera-active');
    ST.cameraOn = false;
    const btn = document.getElementById('cameraToggleBtn');
    btn.classList.remove('is-active');
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> Open Camera`;
  }

  /* ═══════════════════════════════════════════════════
     Brush options
  ═══════════════════════════════════════════════════ */
  document.querySelectorAll('.brush-opt-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.brush-opt-btn').forEach(b=>b.classList.remove('is-active'));
      btn.classList.add('is-active');
      ST.currentBrushSize = Number(btn.dataset.size);
      ST.currentBrushMode = btn.dataset.brush;
      updateDrawCursorSize();
    });
  });

  document.getElementById('drawOpacitySlider')?.addEventListener('input', function() {
    ST.currentOpacity = Number(this.value) / 100;
  });

  /* ═══════════════════════════════════════════════════
     Font options
  ═══════════════════════════════════════════════════ */
  document.querySelectorAll('.font-opt-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.font-opt-btn').forEach(b=>b.classList.remove('is-active'));
      btn.classList.add('is-active');
      ST.currentFont = btn.dataset.font;
      if (ST.selectedLayer) {
        const ta = ST.selectedLayer.querySelector('textarea');
        if (ta) ta.style.fontFamily = ST.currentFont;
      }
    });
  });

  document.getElementById('textSizeSlider')?.addEventListener('input', function() {
    ST.currentTextSize = Number(this.value);
    if (ST.selectedLayer) {
      const ta = ST.selectedLayer.querySelector('textarea');
      if (ta) ta.style.fontSize = ST.currentTextSize + 'px';
    }
  });

  /* ═══════════════════════════════════════════════════
     Publish
  ═══════════════════════════════════════════════════ */
  async function publishStory(audience) {
    const prog = document.getElementById('publishProgress');
    const fill = document.getElementById('publishProgressFill');
    prog.style.display = 'block'; fill.style.width = '0%';

    // Animate progress
    let w = 0;
    const iv = setInterval(() => { w = Math.min(w + 5, 90); fill.style.width = w + '%'; }, 60);

    // Capture canvas to blob (simplified — real implementation uses html2canvas or canvas export)
    try {
      await new Promise(r => setTimeout(r, 1200)); // simulate upload
      clearInterval(iv); fill.style.width = '100%';
      setTimeout(() => {
        prog.style.display = 'none'; fill.style.width = '0%';
        if (audience === 'public') {
          showToast('✅ Story shared to Your Story!');
        } else {
          showToast('✅ Shared to Close Friends!');
        }
        setTimeout(() => { window.location.href = 'profile.php'; }, 1600);
      }, 400);
    } catch(e) {
      clearInterval(iv); prog.style.display = 'none';
      showToast('Failed to publish story');
    }
  }

  document.getElementById('publishPublicBtn')?.addEventListener('click', () => publishStory('public'));
  document.getElementById('publishFriendsBtn')?.addEventListener('click', () => publishStory('friends'));
  document.getElementById('discardBtn')?.addEventListener('click', () => {
    if (confirm('Discard story and go back?')) window.location.href = 'profile.php';
  });

  /* ═══════════════════════════════════════════════════
     Keyboard shortcuts
  ═══════════════════════════════════════════════════ */
  document.addEventListener('keydown', e => {
    if (e.target.tagName === 'TEXTAREA') return;
    if (e.key === 't' || e.key === 'T') activateTool('text');
    if (e.key === 'd' || e.key === 'D') activateTool('draw');
    if (e.key === 's' || e.key === 'S') activateTool('stickers');
    if (e.key === 'm' || e.key === 'M') activateTool('music');
    if (e.key === 'f' || e.key === 'F') activateTool('filters');
    if ((e.ctrlKey || e.metaKey) && e.key === 'z') document.getElementById('undoBtn')?.click();
    if (e.key === 'Escape' && ST.selectedLayer) { ST.selectedLayer.classList.remove('is-selected'); ST.selectedLayer = null; }
  });

  /* ═══════════════════════════════════════════════════
     Init
  ═══════════════════════════════════════════════════ */
  buildGradientStrip();
  buildColorRow('textColorRow', c => { ST.currentTextColor = c; if (ST.selectedLayer) { const ta=ST.selectedLayer.querySelector('textarea'); if(ta) ta.style.color = c; } }, '#ffffff');
  buildColorRow('drawColorRow', c => { ST.currentDrawColor = c; updateDrawCursorSize(); }, '#ffffff');
  buildStickerGrid();
  buildMusicList();
  buildFilterStrip();
  resizeDrawCanvas();
  applyBg();
  </script>
  <script>
    window.addEventListener('DOMContentLoaded', () => {
      if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
      }
    });
  </script>
  <script src="../../assets/js/main.js"></script>
</body>
</html>