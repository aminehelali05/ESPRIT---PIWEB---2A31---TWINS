<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
if (!UserController::isAuthenticated()) {
  $_SESSION['flash_error'] = 'Please sign in.';
  header('Location: auth.php'); exit;
}
$sessionUser    = UserController::currentUser();
$userController = new UserController();
$userModel      = $userController->getUserById((int)($sessionUser['id'] ?? 0));
$displayFirstName = (string)($userModel ? $userModel->getFirstName() : ($sessionUser['first_name'] ?? ''));
$displayLastName  = (string)($userModel ? $userModel->getLastName()  : ($sessionUser['last_name']  ?? ''));
$displayName      = trim($displayFirstName.' '.$displayLastName) ?: 'Member User';
$displayEmail     = (string)($userModel ? $userModel->getEmail() : ($sessionUser['email'] ?? ''));
$isAdminSidebar   = strtolower(trim($displayEmail)) === 'admin@diversity.is';
$displayRole      = (string)($userModel ? ($userModel->getRole() ?? 'client') : ($sessionUser['role'] ?? 'client'));
$displayAvatarUrl = (string)($userModel ? ($userModel->getAvatarUrl() ?? '') : ($sessionUser['avatar_url'] ?? ''));
$displayExactLocation = (string)($userModel ? ($userModel->getExactLocation() ?? '') : '');
$initials = strtoupper(substr($displayFirstName ?: 'M', 0, 1).substr($displayLastName ?: 'U', 0, 1));
$profileLevel = 'Level 12 Collaborator';
$reputationScore = 87;
$streakDays = 7;
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
  <title>Messages — Diversity.is</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/profile.css">
  <link rel="stylesheet" href="../../assets/css/profile-light.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    /* ═══════════════════════════════════════════════
       UTILITY HELPERS
    ═══════════════════════════════════════════════ */
    .hidden { display: none !important; }
    .flex { display: flex !important; }
    .modal { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); }
    .modal .card { background: #fff; border-radius: 16px; padding: 24px; max-width: 96vw; max-height: 90vh; overflow: auto; }

    /* ═══════════════════════════════════════════════
       BASE RESET
    ═══════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body, body * { font-family: 'Poppins', sans-serif; }

    /* ═══════════════════════════════════════════════
       CSS VARIABLES — light & dark
    ═══════════════════════════════════════════════ */
    :root {
      --surf: #ffffff;
      --surf-alt: #f8faff;
      --surf-glass: rgba(255,255,255,0.72);
      --border: rgba(15,23,42,0.08);
      --border-strong: rgba(15,23,42,0.13);
      --text: #1e2a3a;
      --text-secondary: #4b5563;
      --text-muted: #8b95a6;
      --heading: #0f172a;
      --accent: #6366f1;
      --accent-2: #a855f7;
      --accent-soft: rgba(99,102,241,0.10);
      --accent-glow: rgba(99,102,241,0.22);
      --green: #10b981;
      --red: #ef4444;
      --radius-card: 18px;
      --radius-bubble: 22px;
      --shadow-sm: 0 2px 8px rgba(15,23,42,0.06);
      --shadow-md: 0 6px 24px rgba(15,23,42,0.10);
      --shadow-lg: 0 16px 48px rgba(15,23,42,0.13);
      --shadow-accent: 0 6px 20px rgba(99,102,241,0.20);
      --transition: 0.22s cubic-bezier(0.4,0,0.2,1);
    }
    [data-theme="dark"] {
      --surf: #111827;
      --surf-alt: #0d1220;
      --surf-glass: rgba(17,24,39,0.82);
      --border: rgba(255,255,255,0.07);
      --border-strong: rgba(255,255,255,0.13);
      --text: #e2e8f0;
      --text-secondary: #94a3b8;
      --text-muted: #64748b;
      --heading: #f1f5f9;
      --accent-soft: rgba(99,102,241,0.15);
    }

    /* ═══════════════════════════════════════════════
       MESSAGES PAGE LAYOUT
    ═══════════════════════════════════════════════ */
    .messages-page-layout {
      display: grid;
      grid-template-columns: 300px 1fr 290px;
      gap: 0;
      height: calc(100vh - 70px);
      max-height: calc(100vh - 70px);
      overflow: hidden;
      transition: grid-template-columns 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: var(--shadow-lg);
    }
    .messages-page-layout.right-panel-closed {
      grid-template-columns: 300px 1fr 0px;
    }

    /* ─── Outer container spacing fix ────────────── */
    .profile-content-area {
      padding: 0 !important;
      /* push away from left nav with a small gap */
    }
    .profile-page-layout {
      gap: 18px !important;
    }

    /* ═══════════════════════════════════════════════
       THREAD SIDEBAR
    ═══════════════════════════════════════════════ */
    .msg-sidebar {
      display: flex;
      flex-direction: column;
      background: var(--surf);
      border-right: 1.5px solid var(--border);
      height: 100%;
      overflow: hidden;
      position: relative;
    }
    /* Subtle top gradient accent on sidebar */
    .msg-sidebar::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--accent), var(--accent-2));
      border-radius: 0;
      z-index: 1;
    }

    /* ── Sidebar Head ── */
    .msg-sidebar-head {
      padding: 24px 20px 16px;
      border-bottom: 1.5px solid var(--border);
      flex-shrink: 0;
    }
    .msg-sidebar-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--heading);
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      letter-spacing: -0.02em;
    }
    .msg-new-btn {
      width: 32px; height: 32px;
      border-radius: 10px;
      background: var(--accent-soft);
      border: 1.5px solid var(--accent-glow);
      color: var(--accent);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: all var(--transition);
    }
    .msg-new-btn:hover {
      background: var(--accent);
      color: #fff;
      transform: rotate(90deg) scale(1.08);
      box-shadow: var(--shadow-accent);
    }
    .msg-search-wrap { position: relative; }
    .msg-search-wrap input {
      width: 100%;
      padding: 11px 14px 11px 38px;
      border-radius: 12px;
      border: 1.5px solid var(--border);
      background: var(--surf-alt);
      font-family: 'Poppins', sans-serif;
      font-size: 13px;
      color: var(--text);
      outline: none;
      transition: all var(--transition);
    }
    .msg-search-wrap input:focus {
      border-color: var(--accent);
      background: var(--surf);
      box-shadow: 0 0 0 3px var(--accent-soft);
    }
    .msg-search-wrap input::placeholder { color: var(--text-muted); }

    /* Media visuals */
    .msg-image { transition: transform 0.18s ease, opacity 0.18s ease; border-radius:12px; }
    .msg-image:hover { transform: scale(1.02); }
    .msg-audio-wrap { padding:6px 10px; border-radius:12px; background:var(--surf-alt); display:inline-flex; align-items:center; gap:8px; }
    .audio-play-btn { background:var(--accent); color:#fff; border:none; padding:6px 10px; border-radius:10px; cursor:pointer; }
    .audio-play-btn:active { transform:scale(0.98); }
    .msg-search-wrap svg {
      position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
      width: 15px; height: 15px; color: var(--text-muted); pointer-events: none;
    }

    /* ── Tabs ── */
    .msg-tabs {
      display: flex;
      gap: 0;
      padding: 0 20px;
      border-bottom: 1.5px solid var(--border);
      flex-shrink: 0;
      background: var(--surf);
    }
    .msg-tab {
      flex: 1;
      padding: 14px 6px 13px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.02em;
      color: var(--text-muted);
      border-bottom: 2.5px solid transparent;
      margin-bottom: -1.5px;
      cursor: pointer;
      background: none;
      border-top: none; border-left: none; border-right: none;
      transition: all var(--transition);
      text-align: center;
    }
    .msg-tab.is-active {
      color: var(--accent);
      border-bottom-color: var(--accent);
    }
    .msg-tab:hover:not(.is-active) {
      color: var(--text);
      background: var(--surf-alt);
    }

    /* ── Thread List ── */
    .msg-thread-list {
      flex: 1;
      overflow-y: auto;
      padding: 10px 10px;
      scrollbar-width: thin;
      scrollbar-color: var(--border) transparent;
    }
    .msg-thread-list::-webkit-scrollbar { width: 4px; }
    .msg-thread-list::-webkit-scrollbar-thumb {
      background: var(--border-strong);
      border-radius: 4px;
    }

    .msg-thread-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border-radius: 14px;
      cursor: pointer;
      transition: all var(--transition);
      border: 1.5px solid transparent;
      width: 100%;
      text-align: left;
      background: none;
      margin-bottom: 3px;
    }
    .msg-thread-item:hover {
      background: var(--surf-alt);
      border-color: var(--border);
      transform: translateX(3px);
      box-shadow: var(--shadow-sm);
    }
    .msg-thread-item.is-active {
      background: var(--accent-soft);
      border-color: var(--accent-glow);
      transform: translateX(3px);
      box-shadow: var(--shadow-accent);
    }
    .msg-thread-item.is-active .msg-thread-name { color: var(--accent); }

    .msg-thread-avatar {
      width: 46px; height: 46px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #fff;
      font-size: 15px;
      font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      position: relative;
      box-shadow: 0 4px 12px rgba(99,102,241,0.28);
      letter-spacing: -0.02em;
      overflow: hidden;
      background-size: cover;
      background-position: center;
      text-shadow: 0 1px 3px rgba(0,0,0,0.35);
    }
    .msg-thread-avatar.is-group {
      border-radius: 14px;
      background: linear-gradient(135deg, #10b981, #059669);
      box-shadow: 0 4px 12px rgba(16,185,129,0.25);
    }
    .msg-thread-avatar .online-dot {
      position: absolute; bottom: 2px; right: 2px;
      width: 11px; height: 11px; border-radius: 50%;
      background: #10b981;
      border: 2.5px solid var(--surf);
      box-shadow: 0 0 6px rgba(16,185,129,0.5);
    }
    .msg-thread-avatar.has-story-ring {
      box-shadow:
        0 0 0 2px rgba(255,255,255,0.95),
        0 0 0 5px rgba(99,102,241,0.75),
        0 6px 16px rgba(99,102,241,0.30);
    }

    .msg-thread-body { flex: 1; min-width: 0; }
    .msg-thread-name {
      font-size: 14px;
      font-weight: 600;
      color: var(--heading);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: 3px;
      transition: color var(--transition);
    }
    .msg-thread-preview {
      font-size: 12px;
      color: var(--text-muted);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.4;
    }

    .msg-thread-meta { flex-shrink: 0; text-align: right; }
    .msg-thread-time {
      font-size: 10px;
      color: var(--text-muted);
      margin-bottom: 5px;
      font-weight: 500;
    }
    .msg-thread-badge {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 20px; height: 20px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #fff;
      font-size: 10px; font-weight: 700;
      padding: 0 6px;
      box-shadow: var(--shadow-accent);
      animation: badgePop 0.3s cubic-bezier(0.34,1.56,0.64,1);
    }
    @keyframes badgePop {
      from { transform: scale(0); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }

    .msg-thread-empty {
      padding: 40px 20px;
      text-align: center;
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.7;
    }
    .msg-thread-empty svg { margin: 0 auto 12px; display: block; opacity: 0.35; }

    /* ═══════════════════════════════════════════════
       MAIN CHAT AREA
    ═══════════════════════════════════════════════ */
    .msg-main {
      display: flex;
      flex-direction: column;
      height: 100%;
      overflow: hidden;
      background: linear-gradient(160deg, #f8faff 0%, #f0f4ff 100%);
      position: relative;
    }
    [data-theme="dark"] .msg-main {
      background: linear-gradient(160deg, #0d1220 0%, #111827 100%);
    }
    /* Subtle radial glow in chat background */
    .msg-main::before {
      content: '';
      position: absolute;
      top: -80px; right: -80px;
      width: 400px; height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(99,102,241,0.06) 0%, transparent 70%);
      pointer-events: none;
      z-index: 0;
    }

    /* ── Welcome State ── */
    .msg-welcome {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 16px;
      text-align: center;
      padding: 60px 40px;
      position: relative;
      z-index: 1;
    }
    .msg-welcome-icon {
      width: 88px; height: 88px;
      border-radius: 28px;
      background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(168,85,247,0.08));
      border: 1.5px solid var(--accent-glow);
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 6px;
      box-shadow: var(--shadow-accent);
      animation: welcomeFloat 3s ease-in-out infinite;
    }
    @keyframes welcomeFloat {
      0%,100% { transform: translateY(0); }
      50% { transform: translateY(-8px); }
    }
    .msg-welcome-icon svg { width: 36px; height: 36px; color: var(--accent); opacity: .9; }
    .msg-welcome h2 {
      font-size: 22px;
      font-weight: 700;
      color: var(--heading);
      letter-spacing: -0.02em;
    }
    .msg-welcome p {
      font-size: 14px;
      color: var(--text-muted);
      max-width: 26ch;
      line-height: 1.7;
    }
    .msg-welcome-btn {
      margin-top: 6px;
      padding: 11px 28px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #fff;
      font-family: 'Poppins', sans-serif;
      font-size: 14px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1);
      box-shadow: var(--shadow-accent);
      letter-spacing: 0.01em;
    }
    .msg-welcome-btn:hover {
      transform: translateY(-3px) scale(1.03);
      box-shadow: 0 10px 30px rgba(99,102,241,0.35);
    }

    /* ── Chat Header ── */
    .msg-chat-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 24px;
      border-bottom: 1.5px solid var(--border);
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      flex-shrink: 0;
      position: relative;
      z-index: 2;
      box-shadow: 0 2px 12px rgba(15,23,42,0.05);
    }
    [data-theme="dark"] .msg-chat-header {
      background: rgba(17,24,39,0.85);
    }
    .msg-chat-header-left { display: flex; align-items: center; gap: 14px; }
    .msg-chat-header-avatar {
      width: 44px; height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #fff;
      font-size: 15px;
      font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      box-shadow: var(--shadow-accent);
      letter-spacing: -0.02em;
      flex-shrink: 0;
      overflow: hidden;
      background-size: cover;
      background-position: center;
      text-shadow: 0 1px 3px rgba(0,0,0,0.35);
    }
    .msg-chat-header-name {
      font-size: 16px;
      font-weight: 700;
      color: var(--heading);
      letter-spacing: -0.02em;
      line-height: 1.2;
    }
    .msg-chat-header-sub {
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 2px;
      font-weight: 500;
    }

    .msg-chat-actions { display: flex; align-items: center; gap: 6px; }
    .msg-action-btn {
      width: 38px; height: 38px;
      border-radius: 12px;
      border: 1.5px solid var(--border);
      background: var(--surf-alt);
      color: var(--text-secondary);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: all var(--transition);
    }
    .msg-action-btn:hover {
      background: var(--accent-soft);
      color: var(--accent);
      border-color: var(--accent-glow);
      transform: translateY(-2px);
      box-shadow: var(--shadow-accent);
    }
    .msg-action-btn.is-primary { background: var(--accent); color: #fff; border-color: var(--accent); }
    .msg-action-btn.is-danger { background: rgba(239,68,68,0.08); color: var(--red); border-color: rgba(239,68,68,0.2); }

    .msg-meeting-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 40px;
      padding: 0 16px 0 12px;
      border-radius: 12px;
      border: 1.5px solid var(--accent-glow);
      background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(168,85,247,0.08));
      color: var(--accent);
      font-family: 'Poppins', sans-serif;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.22s cubic-bezier(0.34,1.56,0.64,1);
      white-space: nowrap;
      letter-spacing: 0.01em;
    }
    .msg-meeting-btn:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 8px 24px rgba(99,102,241,0.22);
      border-color: var(--accent);
      background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(168,85,247,0.14));
    }
    .msg-meeting-btn svg { width: 15px; height: 15px; flex-shrink: 0; }

    /* ═══════════════════════════════════════════════
       MESSAGES BODY
    ═══════════════════════════════════════════════ */
    .msg-body {
      flex: 1;
      overflow-y: auto;
      padding: 28px 32px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      position: relative;
      z-index: 1;
      scrollbar-width: thin;
      scrollbar-color: var(--border) transparent;
    }
    .msg-body::-webkit-scrollbar { width: 5px; }
    .msg-body::-webkit-scrollbar-thumb {
      background: var(--border-strong);
      border-radius: 4px;
    }

    @keyframes msgSlideIn {
      from {
        opacity: 0;
        transform: translateY(16px) scale(0.96);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .msg-bubble-row {
      display: flex;
      align-items: flex-end;
      gap: 10px;
      animation: msgSlideIn 0.32s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }
    .msg-bubble-row.is-outgoing { flex-direction: row-reverse; }

    .msg-bubble-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(99,102,241,0.25);
      letter-spacing: -0.02em;
      overflow: hidden;
      background-size: cover;
      background-position: center;
      text-shadow: 0 1px 3px rgba(0,0,0,0.35);
    }

    .msg-bubble {
      max-width: min(74vw, 680px);
      min-width: min(140px, 42vw);
      padding: 14px 20px;
      border-radius: var(--radius-bubble);
      font-size: 14.5px;
      line-height: 1.6;
      font-weight: 400;
      transition: transform 0.2s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.2s ease;
      position: relative;
      overflow-wrap: anywhere;
      word-break: break-word;
      white-space: pre-wrap;
    }
    .msg-bubble:hover { transform: translateY(-2px); }

    .msg-bubble-row.is-incoming .msg-bubble {
      background: var(--surf);
      color: var(--text);
      border: 1.5px solid var(--border);
      border-bottom-left-radius: 6px;
      box-shadow: 0 4px 16px rgba(15,23,42,0.06);
    }
    .msg-bubble-row.is-incoming .msg-bubble:hover {
      box-shadow: 0 8px 24px rgba(15,23,42,0.10);
    }
    .msg-bubble-row.is-outgoing .msg-bubble {
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #ffffff;
      border-bottom-right-radius: 6px;
      box-shadow: 0 6px 20px rgba(99,102,241,0.30);
    }
    .msg-bubble-row.is-outgoing .msg-bubble:hover {
      box-shadow: 0 10px 28px rgba(99,102,241,0.40);
    }

    .msg-bubble p {
      margin: 0;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .msg-bubble-content {
      max-width: 100%;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .msg-bubble-meta {
      font-size: 11px;
      opacity: 0.55;
      margin-top: 6px;
      font-weight: 500;
      padding: 0 4px;
    }
    .msg-bubble-row.is-outgoing .msg-bubble-meta { text-align: right; }
    .msg-bubble-seen {
      margin-top: 2px;
      padding: 0 4px;
      font-size: 10px;
      font-weight: 600;
      color: var(--accent);
      text-align: right;
      opacity: 0.9;
    }

    .msg-edited-indicator {
      display: inline-block;
      margin-left: 6px;
      padding: 1px 6px;
      border-radius: 999px;
      border: 1px solid var(--border);
      font-size: 9.5px;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      opacity: 0.82;
      vertical-align: middle;
    }

    .msg-action-row {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 6px;
      padding: 0 2px;
    }
    .msg-bubble-row.is-outgoing .msg-action-row {
      justify-content: flex-end;
    }

    .msg-delete-btn {
      border: 1px solid var(--border);
      background: var(--surf-alt);
      color: var(--text-muted);
      border-radius: 8px;
      padding: 2px 8px;
      font-size: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all var(--transition);
    }
    .msg-delete-btn:hover {
      border-color: rgba(239,68,68,0.35);
      color: var(--red);
      background: rgba(239,68,68,0.08);
    }

    .msg-edit-btn {
      border: 1px solid rgba(59,130,246,0.24);
      background: rgba(59,130,246,0.08);
      color: #1d4ed8;
      border-radius: 8px;
      padding: 2px 8px;
      font-size: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all var(--transition);
    }
    .msg-edit-btn:hover {
      border-color: rgba(59,130,246,0.4);
      background: rgba(59,130,246,0.16);
      color: #1e40af;
    }

    @keyframes msgHighlightPulse {
      0% { box-shadow: 0 0 0 0 rgba(99,102,241,0.45); }
      100% { box-shadow: 0 0 0 14px rgba(99,102,241,0); }
    }

    .msg-bubble-row.is-highlight .msg-bubble {
      animation: msgHighlightPulse 1.1s ease;
    }

    .msg-date-divider {
      display: flex; align-items: center; gap: 14px;
      font-size: 11px; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.12em;
      color: var(--text-muted);
      margin: 20px 0;
    }
    .msg-date-divider::before, .msg-date-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    /* ── Typing indicator ── */
    .msg-typing-indicator {
      display: flex; align-items: center; gap: 10px;
      padding: 4px 32px 6px;
      font-size: 12px;
      color: var(--text-muted);
      font-weight: 500;
    }
    .typing-dots { display: flex; gap: 5px; }
    .typing-dots i {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: var(--accent);
      animation: typingBounce 1.1s ease-in-out infinite;
      opacity: 0.5;
      display: block;
    }
    .typing-dots i:nth-child(2) { animation-delay: 0.18s; }
    .typing-dots i:nth-child(3) { animation-delay: 0.36s; }
    @keyframes typingBounce {
      0%,60%,100% { transform: translateY(0); opacity: 0.5; }
      30% { transform: translateY(-6px); opacity: 1; }
    }

    /* ── Smart replies ── */
    .msg-smart-replies {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      padding: 0 32px 10px;
    }
    .msg-smart-replies::-webkit-scrollbar { display: none; }
    .smart-reply-chip {
      background: var(--surf);
      border: 1.5px solid var(--accent-glow);
      border-radius: 20px;
      padding: 7px 16px;
      font-size: 12px;
      font-weight: 600;
      color: var(--accent);
      font-family: 'Poppins', sans-serif;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.22s cubic-bezier(0.25,0.8,0.25,1);
      box-shadow: var(--shadow-sm);
    }
    .smart-reply-chip:hover {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
      transform: translateY(-3px);
      box-shadow: var(--shadow-accent);
    }

    /* ═══════════════════════════════════════════════
       COMPOSER
    ═══════════════════════════════════════════════ */
    .msg-composer {
      padding: 16px 24px 20px;
      border-top: 1.5px solid var(--border);
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      flex-shrink: 0;
      position: relative;
      z-index: 2;
    }
    [data-theme="dark"] .msg-composer { background: rgba(17,24,39,0.85); }

    .msg-composer-wrap {
      display: flex;
      align-items: flex-end;
      gap: 10px;
      padding: 8px 10px 8px 14px;
      border-radius: 20px;
      border: 1.5px solid var(--border);
      background: var(--surf);
      transition: all 0.3s cubic-bezier(0.22,1,0.36,1);
      box-shadow: var(--shadow-sm);
    }
    .msg-composer-wrap:focus-within {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-soft), var(--shadow-md);
      transform: translateY(-2px);
    }

    .msg-composer-actions { display: flex; gap: 2px; align-items: center; }
    .msg-composer-icon-btn {
      width: 36px; height: 36px;
      border-radius: 10px;
      border: none;
      background: none;
      color: var(--text-muted);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: all var(--transition);
    }
    .msg-composer-icon-btn:hover {
      background: var(--accent-soft);
      color: var(--accent);
      transform: scale(1.1);
    }

    .msg-composer-input {
      flex: 1;
      border: none;
      background: none;
      outline: none;
      font-family: 'Poppins', sans-serif;
      font-size: 14px;
      color: var(--text);
      resize: none;
      max-height: 120px;
      line-height: 1.55;
      padding: 8px 4px;
    }
    .msg-composer-input::placeholder { color: var(--text-muted); }

    .msg-send-btn {
      width: 44px; height: 44px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #fff;
      border: none;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1);
      flex-shrink: 0;
      box-shadow: var(--shadow-accent);
    }
    .msg-send-btn:hover {
      transform: scale(1.12) rotate(-10deg);
      box-shadow: 0 8px 24px rgba(99,102,241,0.40);
    }
    .msg-send-btn:active { transform: scale(0.95); }
    .msg-send-btn:disabled {
      opacity: 0.45;
      transform: scale(0.94);
      cursor: not-allowed;
      box-shadow: none;
      background: #cbd5e1;
    }

    /* emoji */
    .emoji-dropdown {
      position: absolute;
      bottom: calc(100% + 10px);
      left: 0;
      background: var(--surf);
      border: 1.5px solid var(--border);
      border-radius: 16px;
      box-shadow: var(--shadow-lg);
      padding: 14px;
      width: 280px;
      display: grid;
      grid-template-columns: repeat(8, 1fr);
      gap: 4px;
      z-index: 100;
      animation: dropIn 0.22s cubic-bezier(0.34,1.56,0.64,1);
    }
    @keyframes dropIn {
      from { opacity: 0; transform: translateY(10px) scale(0.95); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .emoji-dropdown.is-hidden { display: none; }
    .emoji-btn {
      font-size: 20px;
      padding: 5px;
      border-radius: 8px;
      cursor: pointer;
      text-align: center;
      transition: all 0.15s;
      background: none;
      border: none;
    }
    .emoji-btn:hover {
      background: var(--surf-alt);
      transform: scale(1.25);
    }
    .composer-emoji-wrap { position: relative; }

    /* ═══════════════════════════════════════════════
       CALL OVERLAY
    ═══════════════════════════════════════════════ */
    .call-overlay {
      position: fixed;
      inset: 0;
      padding: 20px;
      background:
        radial-gradient(circle at 16% 18%, rgba(59,130,246,0.28), transparent 44%),
        radial-gradient(circle at 84% 76%, rgba(168,85,247,0.26), transparent 42%),
        rgba(3, 7, 18, 0.78);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      z-index: 9000;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: fadeIn 0.28s ease;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .call-overlay[hidden] { display: none !important; }

    .call-card {
      width: min(1120px, calc(100vw - 24px));
      max-height: calc(100vh - 28px);
      border-radius: 30px;
      background: linear-gradient(165deg, rgba(15,23,42,0.96), rgba(30,41,59,0.94));
      overflow: hidden;
      display: grid;
      grid-template-rows: auto 1fr auto;
      box-shadow: 0 44px 110px rgba(2,6,23,0.72);
      border: 1px solid rgba(255,255,255,0.12);
    }
    .call-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      padding: 14px 18px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      background: linear-gradient(90deg, rgba(59,130,246,0.14), rgba(168,85,247,0.1));
    }
    .call-head-meta {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: rgba(226,232,240,0.92);
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.01em;
    }
    .call-head-dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: #22c55e;
      box-shadow: 0 0 0 6px rgba(34,197,94,0.18);
    }
    .call-head-tip {
      font-size: 11px;
      color: rgba(148,163,184,0.95);
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid rgba(148,163,184,0.3);
      background: rgba(15,23,42,0.46);
    }
    .call-videos {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      padding: 12px;
      background: linear-gradient(180deg, rgba(2,6,23,0.86), rgba(15,23,42,0.7));
      min-height: 360px;
      height: min(56vh, 520px);
    }
    .call-video-slot {
      border-radius: 20px;
      overflow: hidden;
      background: linear-gradient(145deg, #0b1220, #111827);
      display: flex; align-items: center; justify-content: center;
      position: relative;
      border: 1px solid rgba(255,255,255,0.08);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
    }
    .call-video-slot video { width: 100%; height: 100%; object-fit: cover; }
    .call-video-fallback {
      display: flex; flex-direction: column; align-items: center; gap: 12px;
      color: rgba(203,213,225,0.72);
    }
    .call-video-fallback svg { width: 40px; height: 40px; }
    .call-video-fallback span { font-size: 13px; font-weight: 500; letter-spacing: 0.02em; }
    .call-video-label {
      position: absolute; bottom: 12px; left: 14px;
      font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.92);
      background: rgba(2,6,23,0.56);
      padding: 4px 10px; border-radius: 8px;
      backdrop-filter: blur(4px);
    }
    .call-footer {
      padding: 16px 18px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      border-top: 1px solid rgba(255,255,255,0.08);
      background: rgba(15,23,42,0.64);
    }
    .call-info .call-title { font-size: 16px; font-weight: 700; color: #f8fafc; letter-spacing: -0.01em; }
    .call-info .call-sub { font-size: 12px; color: rgba(203,213,225,0.82); margin-top: 3px; }
    .call-code-badge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 5px 12px; border-radius: 10px;
      background: rgba(37,99,235,0.24); border: 1px solid rgba(125,211,252,0.4);
      font-size: 11px; font-weight: 700; color: #dbeafe;
      letter-spacing: 0.1em; margin-top: 8px; cursor: pointer;
      transition: background var(--transition);
    }
    .call-code-badge:hover { background: rgba(37,99,235,0.36); }
    .call-controls {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }
    .call-ctrl-btn {
      min-height: 42px;
      padding: 9px 16px;
      border-radius: 12px;
      font-family: 'Poppins', sans-serif;
      font-size: 12px; font-weight: 600;
      border: 1.5px solid rgba(148,163,184,0.38);
      background: rgba(15,23,42,0.5);
      color: rgba(241,245,249,0.94);
      display: flex; align-items: center; gap: 7px;
      cursor: pointer; transition: all var(--transition);
    }
    .call-ctrl-btn:hover {
      background: rgba(30,41,59,0.74);
      border-color: rgba(191,219,254,0.46);
      transform: translateY(-1px);
    }
    .call-ctrl-btn.is-primary { background: linear-gradient(135deg, #4f46e5, #7c3aed); border-color: #6366f1; color: #fff; }
    .call-ctrl-btn.is-danger { background: linear-gradient(135deg, #dc2626, #ef4444); border-color: #ef4444; color: #fff; }
    .call-ctrl-btn.is-active { background: rgba(37,99,235,0.3); border-color: #60a5fa; color: #dbeafe; }
    .call-ctrl-btn svg { width: 14px; height: 14px; }

    @media (max-width: 900px) {
      .call-overlay {
        padding: 8px;
      }
      .call-card {
        width: calc(100vw - 16px);
        max-height: calc(100vh - 16px);
        border-radius: 22px;
      }
      .call-videos {
        grid-template-columns: 1fr;
        height: auto;
        min-height: 320px;
      }
      .call-footer {
        flex-direction: column;
        align-items: flex-start;
      }
      .call-controls {
        width: 100%;
        justify-content: flex-start;
      }
    }

    /* ═══════════════════════════════════════════════
       PEOPLE / FIND FRIENDS
    ═══════════════════════════════════════════════ */
    .msg-people-panel {
      padding: 24px 28px 14px;
      display: flex; flex-direction: column; gap: 16px;
      flex-shrink: 0;
    }
    .msg-people-search { position: relative; }
    .msg-people-search input {
      width: 100%;
      padding: 12px 14px 12px 40px;
      border-radius: 14px;
      border: 1.5px solid var(--border);
      background: var(--surf);
      font-family: 'Poppins', sans-serif; font-size: 13.5px;
      color: var(--text); outline: none;
      transition: all var(--transition);
      box-shadow: var(--shadow-sm);
    }
    .msg-people-search input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-soft);
    }
    .msg-people-search input::placeholder { color: var(--text-muted); }
    .msg-people-search svg {
      position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
      width: 15px; height: 15px; color: var(--text-muted); pointer-events: none;
    }

    .people-card {
      display: flex; align-items: center; gap: 14px;
      padding: 14px 16px;
      border-radius: 16px;
      background: var(--surf);
      border: 1.5px solid var(--border);
      transition: all var(--transition);
      box-shadow: var(--shadow-sm);
      margin-bottom: 10px;
    }
    .people-card:hover {
      border-color: var(--accent-glow);
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }
    .people-card-avatar {
      position: relative;
      width: 46px; height: 46px;
      border-radius: 50%;
      overflow: hidden;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      box-shadow: var(--shadow-accent);
      border: 1.5px solid rgba(255,255,255,0.45);
      padding: 0;
      cursor: pointer;
    }
    .people-card-avatar:focus-visible,
    .people-card-info:focus-visible,
    .people-mini-btn:focus-visible {
      outline: 2px solid var(--accent);
      outline-offset: 2px;
    }
    .people-card-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .people-card-avatar-fallback {
      position: absolute;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 15px;
      font-weight: 700;
      letter-spacing: -0.02em;
      text-transform: uppercase;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }
    .people-card-avatar.is-fallback .people-card-avatar-fallback {
      display: flex;
    }
    .people-card-avatar.is-fallback img {
      opacity: 0;
    }
    .people-card-info { flex: 1; min-width: 0; }
    .people-card-info[role="button"] { cursor: pointer; }
    .people-card-name {
      font-size: 14px;
      font-weight: 600;
      color: var(--heading);
    }
    .people-card-sub {
      font-size: 12px;
      color: var(--text-muted);
      margin-top: 2px;
    }
    .people-connect-btn {
      padding: 8px 16px;
      border-radius: 10px;
      background: var(--accent-soft);
      border: 1.5px solid var(--accent-glow);
      color: var(--accent);
      font-family: 'Poppins', sans-serif;
      font-size: 12px; font-weight: 600;
      cursor: pointer; transition: all var(--transition); white-space: nowrap;
    }
    .people-connect-btn:hover {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
      transform: scale(1.04);
      box-shadow: var(--shadow-accent);
    }
    .people-connect-btn.is-sent {
      background: rgba(16,185,129,0.1);
      border-color: rgba(16,185,129,0.25);
      color: var(--green);
      cursor: default;
    }
    .people-mini-btn {
      padding: 8px 12px;
      border-radius: 10px;
      border: 1.5px solid rgba(148,163,184,0.3);
      background: rgba(148,163,184,0.08);
      color: var(--text);
      font-family: 'Poppins', sans-serif;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all var(--transition);
      white-space: nowrap;
      margin-right: 8px;
    }
    .people-mini-btn:hover {
      border-color: var(--accent-glow);
      background: var(--accent-soft);
      color: var(--accent);
    }

    .people-profile-modal {
      position: fixed;
      inset: 0;
      z-index: 1400;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(2, 6, 23, 0.58);
      backdrop-filter: blur(6px);
      padding: 18px;
    }
    .people-profile-modal[hidden] {
      display: none;
    }
    .people-profile-card {
      width: min(440px, 94vw);
      border-radius: 20px;
      border: 1.5px solid var(--border);
      background: var(--surf);
      box-shadow: var(--shadow-xl);
      overflow: hidden;
    }
    .people-profile-head {
      padding: 18px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--border);
      background: linear-gradient(135deg, rgba(59,130,246,0.14), rgba(14,165,233,0.12));
    }
    .people-profile-head h3 {
      margin: 0;
      font-size: 15px;
      font-weight: 700;
      color: var(--heading);
      letter-spacing: -0.01em;
    }
    .people-profile-close {
      width: 34px;
      height: 34px;
      border-radius: 10px;
      border: 1.5px solid var(--border);
      background: var(--surf-alt);
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all var(--transition);
    }
    .people-profile-close:hover {
      color: var(--text);
      border-color: var(--accent-glow);
      background: rgba(59,130,246,0.1);
    }
    .people-profile-body {
      padding: 18px 20px 20px;
      display: grid;
      gap: 14px;
    }
    .people-profile-identity {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .people-profile-avatar {
      width: 62px;
      height: 62px;
      border-radius: 50%;
      overflow: hidden;
      border: 2px solid rgba(59,130,246,0.35);
      position: relative;
      flex-shrink: 0;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }
    .people-profile-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
    .people-profile-avatar span {
      position: absolute;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-weight: 700;
      font-size: 19px;
      text-transform: uppercase;
      letter-spacing: -0.02em;
    }
    .people-profile-avatar.is-fallback span {
      display: flex;
    }
    .people-profile-avatar.is-fallback img {
      opacity: 0;
    }
    .people-profile-name {
      font-size: 17px;
      font-weight: 700;
      color: var(--heading);
      line-height: 1.25;
      margin: 0;
    }
    .people-profile-sub {
      font-size: 12.5px;
      color: var(--text-muted);
      margin: 3px 0 0;
    }
    .people-profile-meta {
      display: grid;
      gap: 7px;
      font-size: 12.5px;
      color: var(--text);
    }
    .people-profile-meta span {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .people-profile-bio {
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.55;
      padding: 12px;
      border-radius: 12px;
      border: 1.5px dashed var(--border);
      background: var(--surf-alt);
      min-height: 68px;
    }
    .people-profile-links {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .people-profile-links a,
    .people-profile-links span {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid var(--border);
      font-size: 11px;
      font-weight: 600;
      text-decoration: none;
      background: var(--surf-alt);
      color: var(--text);
    }
    .people-profile-links a:hover {
      border-color: var(--accent-glow);
      color: var(--accent);
    }
    .people-profile-footer {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      margin-top: 2px;
    }

    /* friend request cards */
    .friend-req-card {
      display: flex; align-items: center; gap: 14px;
      padding: 14px 16px; border-radius: 16px;
      background: var(--surf);
      border: 1.5px solid var(--border);
      margin-bottom: 10px;
      box-shadow: var(--shadow-sm);
      transition: all var(--transition);
    }
    .friend-req-card:hover { border-color: var(--accent-glow); box-shadow: var(--shadow-md); }
    .friend-req-info { flex: 1; min-width: 0; }
    .friend-req-name { font-size: 14px; font-weight: 600; color: var(--heading); }
    .friend-req-msg { font-size: 12px; color: var(--text-muted); margin-top: 3px; line-height: 1.4; }
    .friend-req-btns { display: flex; gap: 7px; flex-shrink: 0; }
    .friend-req-btn {
      padding: 7px 14px; border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 12px; font-weight: 600;
      cursor: pointer; transition: all var(--transition);
      border: 1.5px solid;
    }
    .friend-req-btn.accept {
      background: rgba(16,185,129,0.1);
      border-color: rgba(16,185,129,0.28);
      color: var(--green);
    }
    .friend-req-btn.accept:hover {
      background: var(--green);
      color: #fff;
      transform: scale(1.05);
    }
    .friend-req-btn.decline {
      background: rgba(239,68,68,0.08);
      border-color: rgba(239,68,68,0.22);
      color: var(--red);
    }
    .friend-req-btn.decline:hover {
      background: var(--red);
      color: #fff;
      transform: scale(1.05);
    }

    .msg-people-scroll {
      flex: 1; overflow-y: auto;
      padding: 0 28px 24px;
      scrollbar-width: thin;
      scrollbar-color: var(--border) transparent;
    }
    .msg-people-scroll::-webkit-scrollbar { width: 4px; }
    .msg-people-scroll::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }

    /* ═══════════════════════════════════════════════
       RIGHT CONTEXT PANEL
    ═══════════════════════════════════════════════ */
    .msg-right-panel {
      background: var(--surf);
      border-left: 1.5px solid var(--border);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      transition: all var(--transition);
    }
    .msg-right-header {
      padding: 20px 22px 16px;
      border-bottom: 1.5px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      flex-shrink: 0;
    }
    .msg-right-header h3 {
      font-size: 15px;
      font-weight: 700;
      margin: 0;
      color: var(--heading);
      letter-spacing: -0.01em;
    }
    .msg-right-close-btn {
      background: none; border: none; cursor: pointer;
      color: var(--text-muted);
      display: flex; align-items: center; justify-content: center;
      width: 32px; height: 32px; border-radius: 8px;
      transition: all var(--transition);
    }
    .msg-right-close-btn:hover {
      color: var(--red);
      background: rgba(239,68,68,0.08);
    }
    .msg-right-content {
      flex: 1; overflow-y: auto;
      padding: 20px; display: flex; flex-direction: column; gap: 24px;
      scrollbar-width: thin;
    }
    .msg-right-content::-webkit-scrollbar { width: 4px; }
    .msg-right-content::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }

    .right-section-title {
      font-size: 10px; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.12em;
      color: var(--text-muted); margin-bottom: 12px;
    }
    .right-member-row {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 10px;
      font-size: 13.5px;
      font-weight: 500;
      color: var(--text);
    }
    .right-member-avatar {
      width: 34px; height: 34px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700;
      letter-spacing: -0.02em;
      box-shadow: 0 3px 8px rgba(99,102,241,0.22);
      background-size: cover;
      background-position: center;
      overflow: hidden;
      text-shadow: 0 1px 3px rgba(0,0,0,0.35);
    }
    .right-action-btn {
      width: 100%; padding: 10px 14px;
      border-radius: 12px;
      font-family: 'Poppins', sans-serif;
      font-size: 13px; font-weight: 600;
      cursor: pointer; transition: all var(--transition);
      border: 1.5px solid transparent;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .right-action-btn.primary {
      background: var(--accent-soft);
      border-color: var(--accent-glow);
      color: var(--accent);
    }
    .right-action-btn.primary:hover {
      background: var(--accent);
      color: #fff;
      transform: translateY(-2px);
      box-shadow: var(--shadow-accent);
    }
    .right-action-btn.secondary {
      background: var(--surf-alt);
      border-color: var(--border);
      color: var(--text-secondary);
    }
    .right-action-btn.secondary:hover {
      background: var(--surf);
      border-color: var(--border-strong);
      transform: translateY(-2px);
      box-shadow: var(--shadow-sm);
    }

    /* ═══════════════════════════════════════════════
       CREATE GROUP MODAL
    ═══════════════════════════════════════════════ */
    .create-group-modal {
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.55);
      backdrop-filter: blur(8px);
      z-index: 8000;
      display: flex; align-items: center; justify-content: center;
      animation: fadeIn 0.2s ease;
    }
    .create-group-modal[hidden] { display: none !important; }
    .create-group-card {
      width: 480px; max-width: calc(100vw - 32px);
      background: var(--surf);
      border-radius: 24px;
      padding: 32px;
      box-shadow: var(--shadow-lg);
      border: 1.5px solid var(--border);
      animation: dropIn 0.28s cubic-bezier(0.34,1.56,0.64,1);
    }
    .create-group-card h3 {
      font-size: 18px; font-weight: 700;
      color: var(--heading);
      margin: 0 0 20px;
      letter-spacing: -0.02em;
    }
    .create-group-input {
      width: 100%;
      padding: 12px 16px;
      border-radius: 12px;
      border: 1.5px solid var(--border);
      background: var(--surf-alt);
      font-family: 'Poppins', sans-serif; font-size: 13.5px;
      color: var(--text); outline: none;
      margin-bottom: 12px; transition: all var(--transition);
    }
    .create-group-input:focus {
      border-color: var(--accent);
      background: var(--surf);
      box-shadow: 0 0 0 3px var(--accent-soft);
    }
    .create-group-members { max-height: 200px; overflow-y: auto; margin-bottom: 16px; }
    .create-group-avatar-picker {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
    }
    .create-group-avatar-btn {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      border: 1.5px dashed var(--border-strong);
      background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(168,85,247,0.08));
      color: var(--accent);
      font-size: 18px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }
    .create-group-avatar-btn:hover {
      border-color: var(--accent);
      box-shadow: var(--shadow-accent);
    }
    .create-group-avatar-btn img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: none;
    }
    .create-group-avatar-btn.has-image img { display: block; }
    .create-group-avatar-btn.has-image span { display: none; }
    .create-group-avatar-note {
      font-size: 12px;
      color: var(--text-muted);
      line-height: 1.5;
    }
    .create-group-member-row {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 0; cursor: pointer;
      font-size: 13.5px; color: var(--text);
      transition: color var(--transition);
    }
    .create-group-member-avatar {
      width: 30px;
      height: 30px;
      border-radius: 999px;
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background-size: cover;
      background-position: center;
      text-shadow: 0 1px 3px rgba(0,0,0,0.35);
      overflow: hidden;
      flex-shrink: 0;
    }
    .group-manage-actions {
      display: none;
      flex-direction: column;
      gap: 8px;
      margin-top: 10px;
    }
    .group-manage-actions.is-visible {
      display: flex;
    }
    .create-group-member-row:hover { color: var(--accent); }
    .create-group-member-row input[type="checkbox"] { accent-color: var(--accent); width: 16px; height: 16px; }
    .create-group-actions {
      display: flex; gap: 10px;
      justify-content: flex-end; margin-top: 8px;
    }
    .cg-btn {
      padding: 10px 22px; border-radius: 12px;
      font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 600;
      cursor: pointer; transition: all 0.22s cubic-bezier(0.34,1.56,0.64,1); border: 1.5px solid;
    }
    .cg-btn.cancel {
      background: none;
      border-color: var(--border);
      color: var(--text-muted);
    }
    .cg-btn.cancel:hover { border-color: var(--border-strong); color: var(--text); }
    .cg-btn.create {
      background: linear-gradient(135deg, var(--accent), var(--accent-2));
      border-color: var(--accent);
      color: #fff;
      box-shadow: var(--shadow-accent);
    }
    .cg-btn.create:hover {
      transform: translateY(-2px) scale(1.03);
      box-shadow: 0 10px 28px rgba(99,102,241,0.35);
    }

    /* ═══════════════════════════════════════════════
       DARK MODE OVERRIDES
    ═══════════════════════════════════════════════ */
    [data-theme="dark"] .msg-sidebar { background: var(--surf); }
    [data-theme="dark"] .msg-thread-item:hover { background: rgba(255,255,255,0.05); }
    [data-theme="dark"] .msg-thread-item.is-active { background: rgba(99,102,241,0.14); }
    [data-theme="dark"] .msg-bubble-row.is-incoming .msg-bubble {
      background: rgba(255,255,255,0.07);
      border-color: rgba(255,255,255,0.08);
      color: #e2e8f0;
    }
    [data-theme="dark"] .msg-chat-header { border-color: var(--border); }
    [data-theme="dark"] .msg-composer { border-color: var(--border); }
    [data-theme="dark"] .msg-composer-wrap {
      background: rgba(255,255,255,0.05);
      border-color: var(--border);
    }
    [data-theme="dark"] .msg-edited-indicator {
      border-color: rgba(148,163,184,0.28);
      color: #cbd5e1;
      background: rgba(148,163,184,0.12);
    }
    [data-theme="dark"] .msg-edit-btn {
      border-color: rgba(96,165,250,0.36);
      color: #93c5fd;
      background: rgba(30,64,175,0.2);
    }
    [data-theme="dark"] .msg-edit-btn:hover {
      border-color: rgba(147,197,253,0.55);
      background: rgba(30,64,175,0.34);
      color: #dbeafe;
    }
    [data-theme="dark"] .people-card { background: rgba(255,255,255,0.04); }
    [data-theme="dark"] .people-mini-btn {
      background: rgba(255,255,255,0.06);
      border-color: rgba(148,163,184,0.28);
      color: #e2e8f0;
    }
    [data-theme="dark"] .people-mini-btn:hover {
      background: rgba(99,102,241,0.22);
      border-color: rgba(99,102,241,0.48);
      color: #c7d2fe;
    }
    [data-theme="dark"] .people-profile-card {
      background: #111827;
      border-color: rgba(148,163,184,0.26);
    }
    [data-theme="dark"] .people-profile-head {
      border-color: rgba(148,163,184,0.2);
      background: linear-gradient(135deg, rgba(99,102,241,0.22), rgba(14,165,233,0.2));
    }
    [data-theme="dark"] .people-profile-bio {
      background: rgba(15,23,42,0.6);
      border-color: rgba(148,163,184,0.24);
      color: #cbd5e1;
    }
    [data-theme="dark"] .people-profile-links a,
    [data-theme="dark"] .people-profile-links span {
      background: rgba(255,255,255,0.05);
      border-color: rgba(148,163,184,0.3);
      color: #e2e8f0;
    }
    [data-theme="dark"] .friend-req-card { background: rgba(255,255,255,0.04); }
    [data-theme="dark"] .create-group-card { background: #1e293b; }
    [data-theme="dark"] .emoji-dropdown { background: #1e293b; border-color: var(--border); }
    [data-theme="dark"] .msg-search-wrap input { background: rgba(255,255,255,0.05); color: #e2e8f0; }
    [data-theme="dark"] .msg-people-search input { background: rgba(255,255,255,0.05); color: #e2e8f0; }
    [data-theme="dark"] .msg-right-panel { background: var(--surf); border-color: var(--border); }
    [data-theme="dark"] .msg-main::before { opacity: 0.5; }

    /* ═══════════════════════════════════════════════
       SECTION HIDDEN
    ═══════════════════════════════════════════════ */
    .section-hidden { display: none !important; }

    /* ═══════════════════════════════════════════════
       MOBILE
    ═══════════════════════════════════════════════ */
    @media (max-width: 900px) {
      .profile-page-layout { grid-template-columns: 1fr !important; }
      .home-left { display: none; }
      .messages-page-layout { grid-template-columns: 1fr; }
      .msg-sidebar {
        position: fixed; left: 0; top: 70px; bottom: 0;
        z-index: 300; width: 300px;
        transform: translateX(-100%);
        transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);
      }
      .msg-sidebar.is-open {
        transform: translateX(0);
        box-shadow: 8px 0 32px rgba(0,0,0,0.15);
      }
      .msg-mobile-toggle { display: flex !important; }
    }
    .msg-mobile-toggle {
      display: none; align-items: center; justify-content: center;
      width: 38px; height: 38px; border-radius: 12px;
      background: var(--accent-soft); border: 1.5px solid var(--accent-glow);
      color: var(--accent); cursor: pointer;
      transition: all var(--transition);
    }
    .msg-mobile-toggle:hover { background: var(--accent); color: #fff; }

    /* ═══════════════════════════════════════════════
       STAGGERED ANIMATION HELPERS
    ═══════════════════════════════════════════════ */
    .msg-bubble-row:nth-child(1)  { animation-delay: 0ms; }
    .msg-bubble-row:nth-child(2)  { animation-delay: 30ms; }
    .msg-bubble-row:nth-child(3)  { animation-delay: 60ms; }
    .msg-bubble-row:nth-child(4)  { animation-delay: 90ms; }
    .msg-bubble-row:nth-child(5)  { animation-delay: 120ms; }
    .msg-bubble-row:nth-child(n+6) { animation-delay: 0ms; }

    /* message reaction hover area (future-ready) */
    .msg-bubble::after {
      content: '';
      position: absolute;
      inset: 0;
      border-radius: inherit;
      opacity: 0;
      background: rgba(99,102,241,0.06);
      transition: opacity var(--transition);
      pointer-events: none;
    }
    .msg-bubble:hover::after { opacity: 1; }
    .msg-bubble-row.is-outgoing .msg-bubble:hover::after { background: rgba(255,255,255,0.06); }
  </style>
</head>
<body class="grid-dot-bg home-page-body with-global-left-sidebar">

  <!-- ── Navbar ────────────────────────────────────── -->
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

  <!-- ── Main layout ──────────────────────────────── -->
  <?php include __DIR__ . '/partials/global-sidebar.php'; ?>

  <main class="profile-main" id="main-content" tabindex="-1">
    <div class="container profile-page-layout" style="gap: 18px; height: 100%; max-height: calc(100vh - 70px);">

      <!-- Left sidebar -->
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
          <a class="active" href="messages.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Messages</a>
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

      <!-- Messages area -->
      <section class="profile-content-area" style="padding: 0; overflow: hidden; height: 100%;">
        <div class="messages-page-layout glass-card" style="border-radius: 20px; overflow: hidden; margin: 0; height: 100%;">

          <!-- ── Thread sidebar ────────────────────── -->
          <div class="msg-sidebar" id="msgSidebar">
            <div class="msg-sidebar-head">
              <div class="msg-sidebar-title">
                Messages
                <button class="msg-new-btn" id="createGroupBtn" title="Create group chat">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                </button>
              </div>
              <div class="msg-search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="msgSearchInput" placeholder="Search conversations…">
              </div>
            </div>

            <div class="msg-tabs">
              <button class="msg-tab is-active" data-tab="chats">Chats</button>
              <button class="msg-tab" data-tab="requests">Requests <span id="reqBadge" class="msg-thread-badge" style="display:none;margin-left:4px;font-size:9px;"></span></button>
              <button class="msg-tab" data-tab="people">People</button>
            </div>

            <div class="msg-thread-list" id="threadListChats">
              <div class="msg-thread-empty">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Loading conversations…
              </div>
            </div>
            <div class="msg-thread-list section-hidden" id="threadListRequests">
              <div class="msg-thread-empty">No pending requests.</div>
            </div>
            <div class="msg-thread-list section-hidden" id="threadListPeople">
              <div class="msg-thread-empty">Loading…</div>
            </div>
          </div>

          <!-- ── Chat main ─────────────────────────── -->
          <div class="msg-main" id="msgMain">

            <!-- Welcome state -->
            <div class="msg-welcome" id="msgWelcome">
              <div class="msg-welcome-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <h2>Your conversations</h2>
              <p>Select a thread on the left, or find new people to connect with.</p>
              <button class="msg-welcome-btn" id="msgFindPeopleBtn">Find people →</button>
            </div>

            <!-- Chat view -->
            <div id="msgChatView" class="section-hidden" style="display:flex;flex-direction:column;height:100%;overflow:hidden;">

              <div class="msg-chat-header">
                <div class="msg-chat-header-left">
                  <button class="msg-mobile-toggle" id="msgMobileBack" style="display:none;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                  </button>
                  <div class="msg-chat-header-avatar" id="chatHeaderAvatar">?</div>
                  <div>
                    <div class="msg-chat-header-name" id="chatHeaderName">Select a conversation</div>
                    <div class="msg-chat-header-sub" id="chatHeaderSub">—</div>
                  </div>
                </div>
                <div class="msg-chat-actions">
                  <button class="msg-action-btn" id="startAudioCallBtn" title="Audio call">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                  </button>
                  <button class="msg-action-btn" id="startVideoCallBtn" title="Video call">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                  </button>
                  <button class="msg-action-btn" id="shareScreenBtn" title="Share screen">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                  </button>
                  <button class="msg-action-btn" id="copyInviteBtn" title="Copy room invite">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                  </button>
                  <button class="msg-meeting-btn" id="openMeetingRoomBtn" title="Open Meeting Room">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7.5 4h9A3.5 3.5 0 0 1 20 7.5v6A3.5 3.5 0 0 1 16.5 17H11l-4 3v-3H7.5A3.5 3.5 0 0 1 4 13.5v-6A3.5 3.5 0 0 1 7.5 4Z"/><path d="M14.5 9.5 18 12l-3.5 2.5v-5Z"/></svg>
                    Meeting Room
                  </button>
                  <button class="msg-action-btn" id="toggleRightPanelBtn" title="Details panel">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                  </button>
                </div>
              </div>

              <div class="msg-body" id="msgBody"></div>

              <div class="msg-typing-indicator" id="typingIndicator" style="display:none;">
                <span id="typingName">Someone is typing</span>
                <div class="typing-dots"><i></i><i></i><i></i></div>
              </div>

              <div class="msg-composer">
                <div class="msg-smart-replies" id="smartReplies" style="display:none;">
                  <button class="smart-reply-chip" onclick="document.getElementById('composerInput').value='Yes, totally agree! ✨'; document.getElementById('composerInput').focus();">Yes, totally agree! ✨</button>
                  <button class="smart-reply-chip" onclick="document.getElementById('composerInput').value='Can we schedule a call?'; document.getElementById('composerInput').focus();">Can we schedule a call?</button>
                  <button class="smart-reply-chip" onclick="document.getElementById('composerInput').value='Let me check on that.'; document.getElementById('composerInput').focus();">Let me check on that.</button>
                </div>
                <div class="msg-composer-wrap">
                  <div class="msg-composer-actions">
                    <div class="composer-emoji-wrap">
                      <button class="msg-composer-icon-btn" id="emojiToggleBtn" title="Emoji">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                      </button>
                      <div class="emoji-dropdown is-hidden" id="emojiDropdown"></div>
                    </div>
                    <button class="msg-composer-icon-btn" id="attachImageBtn" title="Send image URL">
                      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </button>
                    <button class="msg-composer-icon-btn" id="attachFileBtn" title="Attach file">
                      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    </button>
                    <button class="msg-composer-icon-btn" id="recordVoiceBtn" title="Voice message">
                      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
                    </button>
                  </div>
                  <textarea class="msg-composer-input" id="composerInput" rows="1" placeholder="Type a message…"></textarea>
                  <button class="msg-send-btn" id="sendMsgBtn" disabled>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                  </button>
                </div>
              </div>
            </div><!-- /msgChatView -->

            <!-- Hidden inputs for media upload & lightbox preview -->
            <input type="file" id="imageFileInput" accept="image/*" class="hidden">
            <input type="file" id="audioFileInput" accept="audio/*" class="hidden">
            <input type="file" id="attachmentFileInput" accept="image/*,video/*,.pdf,.txt,.rtf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.7z,.csv" class="hidden">
            <div id="imageLightbox" class="modal" aria-hidden="true" style="display:none;">
              <div class="card" style="padding:12px;display:flex;flex-direction:column;align-items:center;">
                <img id="lightboxImage" src="" alt="Preview" style="max-width:90vw;max-height:80vh;border-radius:12px;" />
                <div style="margin-top:12px;"><button id="lightboxClose" type="button" class="msg-action-btn">Close</button></div>
              </div>
            </div>

            <!-- People view -->
            <div id="msgPeopleView" class="section-hidden" style="display:flex;flex-direction:column;height:100%;overflow:hidden;">
              <div class="msg-chat-header">
                <div class="msg-chat-header-left">
                  <div>
                    <div class="msg-chat-header-name">Find People</div>
                    <div class="msg-chat-header-sub">Connect &amp; start chatting</div>
                  </div>
                </div>
              </div>
              <div class="msg-people-panel">
                <div class="msg-people-search">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  <input type="text" id="peopleSearchInput" placeholder="Search by name or location…">
                </div>
              </div>
              <div class="msg-people-scroll" id="peopleDirectoryList">
                <div class="msg-thread-empty">Loading…</div>
              </div>
            </div>

          </div><!-- /msg-main -->

          <!-- ── Right Panel ─────────────────────────── -->
          <div class="msg-right-panel" id="msgRightPanel">
            <div class="msg-right-header">
              <h3>Details</h3>
              <button class="msg-right-close-btn" id="closeRightPanelBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
              </button>
            </div>
            <div class="msg-right-content">
              <div class="right-panel-section">
                <div class="right-section-title">Meeting &amp; Collaboration</div>
                <button class="right-action-btn primary" id="scheduleMeetingBtn">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                  Audio / Video Call
                </button>
                <div style="height:8px;"></div>
                <button class="right-action-btn secondary">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2z"/></svg>
                  Share Project
                </button>
              </div>

              <div class="right-panel-section">
                <div class="right-section-title">Members</div>
                <div class="right-member-row">
                  <div class="right-member-avatar" style="background-image:url('<?= htmlspecialchars($displayAvatarUrl !== '' ? $displayAvatarUrl : ('https://api.dicebear.com/9.x/adventurer/svg?seed=' . rawurlencode($displayName))) ?>');background-size:cover;background-position:center;"><?= htmlspecialchars($initials) ?></div>
                  <div><?= htmlspecialchars($displayName) ?></div>
                </div>
                <div id="rightPanelMembersList"></div>
              </div>

              <div class="right-panel-section" id="groupManageSection">
                <div class="right-section-title">Group Management</div>
                <div class="group-manage-actions" id="groupManageActions">
                  <button class="right-action-btn secondary" id="groupLeaveBtn">Leave Group</button>
                  <button class="right-action-btn secondary" id="groupReportBtn">Report Group</button>
                  <button class="right-action-btn" id="groupDeleteBtn" style="background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#b91c1c;">Delete Group</button>
                </div>
              </div>

              <div class="right-panel-section">
                <div class="right-section-title">Shared Media</div>
                <div style="font-size:12px;color:var(--text-muted);text-align:center;padding:20px 14px;border:1.5px dashed var(--border);border-radius:14px;background:var(--surf-alt);line-height:1.6;">
                  No shared files or media yet.
                </div>
              </div>
            </div>
          </div>

        </div><!-- /messages-page-layout -->
      </section>
    </div>
  </main>

  <!-- ── Call overlay ─────────────────────────────── -->
  <div class="call-overlay" id="callOverlay" hidden>
    <div class="call-card">
      <div class="call-head">
        <div class="call-head-meta">
          <span class="call-head-dot" aria-hidden="true"></span>
          <span id="callHeadStatus">Preparing secure connection</span>
        </div>
        <div class="call-head-tip" id="callHeadPeer">Participant</div>
      </div>
      <div class="call-videos">
        <div class="call-video-slot">
          <video id="callLocalVideo" autoplay muted playsinline></video>
          <div class="call-video-fallback" id="callLocalFallback">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>You</span>
          </div>
          <span class="call-video-label">You</span>
        </div>
        <div class="call-video-slot">
          <video id="callRemoteVideo" autoplay playsinline></video>
          <div class="call-video-fallback" id="callRemoteFallback">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Waiting…</span>
          </div>
          <span class="call-video-label" id="callRemoteLabel">Participant</span>
        </div>
      </div>
      <div class="call-footer">
        <div class="call-info">
          <div class="call-title" id="callTitle">Video Room</div>
          <div class="call-sub" id="callSub">Starting session…</div>
          <div class="call-code-badge" id="callCodeBadge" title="Click to copy">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            <span id="callRoomCode">—</span>
          </div>
        </div>
        <div class="call-controls">
          <button class="call-ctrl-btn is-active" id="callMicBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
            Mic
          </button>
          <button class="call-ctrl-btn is-active" id="callCameraBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
            Camera
          </button>
          <button class="call-ctrl-btn" id="callScreenBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
            Screen
          </button>
          <button class="call-ctrl-btn is-danger" id="callEndBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7 2 2 0 0 1 1.72 2v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"/><line x1="23" y1="1" x2="1" y2="23"/></svg>
            End
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Create Group modal ───────────────────────── -->
  <div class="create-group-modal" id="createGroupModal" hidden>
    <div class="create-group-card">
      <h3>Create Group Chat</h3>
      <div class="create-group-avatar-picker">
        <button class="create-group-avatar-btn" id="groupAvatarPickerBtn" type="button" title="Select group avatar">
          <img src="" alt="Group avatar preview" id="groupAvatarPreviewImg">
          <span id="groupAvatarPreviewFallback">G</span>
        </button>
        <div class="create-group-avatar-note">Add a group avatar (optional).<br>Supported: JPG, PNG, WEBP.</div>
      </div>
      <input type="file" id="groupAvatarFileInput" accept="image/*" class="hidden">
      <input class="create-group-input" id="groupNameInput" type="text" placeholder="Group name…" maxlength="120">
      <input class="create-group-input" id="groupDescInput" type="text" placeholder="Short description (optional)" maxlength="255">
      <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--text-muted);margin-bottom:10px;">Select friends</p>
      <div class="create-group-members" id="groupMembersList">
        <p style="font-size:13px;color:var(--text-muted);">Loading friends…</p>
      </div>
      <div class="create-group-actions">
        <button class="cg-btn cancel" id="cancelGroupBtn">Cancel</button>
        <button class="cg-btn create" id="confirmGroupBtn">Create Group</button>
      </div>
    </div>
  </div>

  <div class="people-profile-modal" id="peopleProfileModal" hidden>
    <div class="people-profile-card" role="dialog" aria-modal="true" aria-labelledby="peopleProfileName">
      <div class="people-profile-head">
        <h3>People Profile</h3>
        <button type="button" class="people-profile-close" id="peopleProfileCloseBtn" aria-label="Close mini profile">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>
      <div class="people-profile-body">
        <div class="people-profile-identity">
          <div class="people-profile-avatar" id="peopleProfileAvatarWrap">
            <img src="" alt="" id="peopleProfileAvatarImg">
            <span id="peopleProfileAvatarFallback">U</span>
          </div>
          <div>
            <h4 class="people-profile-name" id="peopleProfileName">Unknown User</h4>
            <p class="people-profile-sub" id="peopleProfileSub">No location set</p>
          </div>
        </div>
        <div class="people-profile-meta">
          <span><strong>Role:</strong> <em id="peopleProfileRole">Member</em></span>
          <span><strong>XP:</strong> <em id="peopleProfileXp">0</em></span>
          <span><strong>Phone:</strong> <em id="peopleProfilePhone">Not shared</em></span>
        </div>
        <div class="people-profile-bio" id="peopleProfileBio">No bio available.</div>
        <div class="people-profile-links" id="peopleProfileLinks"></div>
        <div class="people-profile-footer">
          <button type="button" class="people-connect-btn" id="peopleProfileConnectBtn">Connect</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Story modals (unchanged) -->
  <div id="storyUploadModal" class="modal hidden">
    <div class="card">
      <button onclick="closeStoryUploadModal()" style="position:absolute;right:12px;top:12px;border:none;background:transparent;color:#333;font-size:18px;">&times;</button>
      <h3 style="margin:0 0 12px;font-size:18px;">Create Story</h3>
      <div id="storyPreview" style="display:none;margin-bottom:12px;">
        <img id="storyPreviewImage" style="max-width:100%;border-radius:8px;display:none;" />
        <video id="storyPreviewVideo" style="max-width:100%;border-radius:8px;display:none;" controls></video>
      </div>
      <label style="display:block;cursor:pointer;border:1px dashed #d1d5db;padding:18px;border-radius:10px;text-align:center;">
        <div style="margin-bottom:6px;color:#6b7280;">Upload file (image or video)</div>
        <input id="storyFileInput" type="file" accept="image/*,video/*" style="display:none" onchange="previewStory(this)">
      </label>
      <button id="uploadStoryBtn" onclick="uploadStory()" disabled style="margin-top:12px;padding:10px 14px;border-radius:10px;background:#6366f1;color:#fff;border:none;"><span id="uploadBtnText">Share Story</span></button>
    </div>
  </div>
  <div id="storyViewerModal" class="modal hidden">
    <div style="position:relative;max-width:100%;width:960px;">
      <div id="storyProgressBars" style="position:absolute;left:12px;right:12px;top:12px;display:flex;gap:6px;z-index:12"></div>
      <button onclick="closeStoryViewer()" style="position:absolute;right:12px;top:12px;border:none;background:rgba(0,0,0,0.5);color:#fff;padding:8px;border-radius:999px;z-index:13">×</button>
      <div style="display:flex;align-items:center;gap:8px;position:absolute;left:12px;top:12px;z-index:13">
        <img id="storyViewerAvatar" src="" alt="" style="width:40px;height:40px;border-radius:999px;border:2px solid #fff;object-fit:cover">
        <span id="storyViewerName" style="color:#fff;font-weight:600"></span>
      </div>
      <div style="background:#000;display:flex;align-items:center;justify-content:center;max-height:80vh;border-radius:12px;overflow:hidden;">
        <img id="storyViewerImage" style="max-width:100%;max-height:80vh;display:none;object-fit:contain">
        <video id="storyViewerVideo" style="max-width:100%;max-height:80vh;display:none;object-fit:contain" autoplay muted></video>
      </div>
      <button onclick="previousStory()" class="btn btn-circle story-nav-btn" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);z-index:13;" aria-label="Previous story">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
      </button>
      <button onclick="nextStory()" class="btn btn-circle story-nav-btn" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);z-index:13;" aria-label="Next story">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
      </button>
    </div>
  </div>

  <script>
    /* ═══ Bootstrap ═══ */
    window.msgBootstrap = {
      currentUserId:    <?= (int)($sessionUser['id'] ?? 0) ?>,
      currentUserName:  <?= json_encode($displayName) ?>,
      currentUserAvatar:<?= json_encode($displayAvatarUrl) ?>,
      currentInitials:  <?= json_encode($initials) ?>
    };

    /* ═══ Utilities ═══ */
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const initials = (fn, ln) => (String(fn||'').charAt(0)+String(ln||'').charAt(0)).toUpperCase() || 'U';
    const displayName = (u) => [u?.first_name, u?.last_name].filter(Boolean).join(' ').trim() || u?.name || 'Member';
    const resolvePublicMediaUrl = (rawUrl) => {
      const raw = String(rawUrl || '').trim();
      if (!raw) return '';
      if (/^(https?:|data:|blob:)/i.test(raw)) return raw;
      const normalized = raw.replace(/\\/g, '/');
      if (normalized.startsWith('/')) return normalized;
      const cleaned = normalized.replace(/^(\.\/)+/, '').replace(/^(\.\.\/)+/, '');
      if (cleaned.startsWith('assets/')) return `../../${cleaned}`;
      if (cleaned.startsWith('uploads/')) return `../../assets/${cleaned}`;
      return `../../${cleaned}`;
    };
    const avatarFor = (u) => {
      const raw = resolvePublicMediaUrl(u?.avatar_url || '');
      if (raw) return raw;
      const seed = encodeURIComponent(`${displayName(u)}-${String(u?.id || 'user')}`);
      return `https://api.dicebear.com/9.x/adventurer/svg?seed=${seed}`;
    };
    const avatarBgStyle = (url) => {
      const safe = String(url || '').trim();
      if (!safe) return '';
      return `background-image:url('${esc(safe)}');`;
    };
    const formatSeenAt = (value) => {
      if (!value) return '';
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return '';
      const now = new Date();
      const sameDay = d.toDateString() === now.toDateString();
      if (sameDay) {
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      }
      return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    };
    const linkedAccountLabel = (account) => {
      const preferred = String(account?.account_label || '').trim();
      if (preferred) return preferred;
      const platform = String(account?.platform || '').trim();
      if (!platform) return 'Account';
      return platform.charAt(0).toUpperCase() + platform.slice(1);
    };
    const relTime = (d) => {
      const diff = Math.max(0, Math.floor((Date.now() - new Date(d).getTime()) / 1000));
      if (!diff || isNaN(diff)) return '';
      if (diff < 60) return 'just now';
      if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
      if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
      return `${Math.floor(diff/86400)}d ago`;
    };
    const preview = (t) => {
      if (!t) return '';
      const m = String(t?.last_message_type||'text');
      if (m==='image') return '📷 Image';
      if (m==='video') return '🎥 Video';
      if (m==='file')  return '📎 File';
      return String(t?.last_message_body||'').trim() || 'No messages yet';
    };
    const toast = (msg, type='success') => {
      if (window.Swal) {
        Swal.fire({ toast:true, position:'top-end', icon:type==='error'?'error':type==='warning'?'warning':'success',
          title:msg, showConfirmButton:false, timer:2800, timerProgressBar:true, background:'#0f172a', color:'#f8fafc' });
      }
    };
    const confirmDialog = async ({
      title = 'Are you sure?',
      text = '',
      confirmText = 'Confirm',
      cancelText = 'Cancel',
      icon = 'warning'
    } = {}) => {
      if (!window.Swal) return false;
      const result = await Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true,
        background: '#ffffff',
        color: '#0f172a',
        showClass: { popup: 'swal2-show swal2-backdrop-show' },
        hideClass: { popup: 'swal2-hide swal2-backdrop-hide' }
      });
      return Boolean(result.isConfirmed);
    };
    const promptTextDialog = async ({
      title = 'Enter a value',
      value = '',
      placeholder = '',
      confirmText = 'Save'
    } = {}) => {
      if (!window.Swal) return null;
      const result = await Swal.fire({
        title,
        input: 'textarea',
        inputValue: value,
        inputPlaceholder: placeholder,
        inputAttributes: { maxlength: '5000', autocapitalize: 'off', autocorrect: 'off' },
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancel',
        background: '#ffffff',
        color: '#0f172a',
        inputValidator: (inputValue) => {
          if (!String(inputValue || '').trim()) return 'This field cannot be empty.';
          return undefined;
        }
      });
      if (!result.isConfirmed) return null;
      return String(result.value || '').trim();
    };
    const post = async (action, body) => {
      const response = await fetch(`profile.php?action=${action}`, {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
      });
      const payload = await response.json();
      if (!response.ok || payload?.success === false) {
        throw new Error(String(payload?.message || `Request failed (${response.status})`));
      }
      return payload;
    };
    const get = async (action, params={}) => {
      const q = new URLSearchParams({ action, ...Object.fromEntries(Object.entries(params).map(([k,v])=>[k,String(v)])) });
      const response = await fetch(`profile.php?${q}`);
      const payload = await response.json();
      if (!response.ok || payload?.success === false) {
        throw new Error(String(payload?.message || `Request failed (${response.status})`));
      }
      return payload;
    };

    /* ═══ State ═══ */
    const S = {
      loaded:false, friends:[], privateConversations:[], groupChats:[],
      mapUsers:[], incomingRequests:[], outgoingRequests:[],
      peopleById:{},
      activeType:null, activeId:0,
      callStream:null, callScreenStream:null,
      callMicOn:true, callCameraOn:true, callOpen:false,
      callPc:null, callRemoteStream:null, callSessionId:0, callSignalCursor:0,
      callIsInitiator:false, callType:'video', callPollTimer:null,
      callIncomingSeen:{}, callIncomingPromptOpen:false,
      activeGroupRole:'member', pendingGroupAvatarData:'',
      pendingRemoteCandidates:[],
      highlightMessageId:0
    };

    const $ = (id) => document.getElementById(id);
    const tabs = { chats:$('threadListChats'), requests:$('threadListRequests'), people:$('threadListPeople') };
    const views = { welcome:$('msgWelcome'), chat:$('msgChatView'), people:$('msgPeopleView') };
    const peopleProfileModal = $('peopleProfileModal');
    const peopleProfileCloseBtn = $('peopleProfileCloseBtn');
    const peopleProfileAvatarWrap = $('peopleProfileAvatarWrap');
    const peopleProfileAvatarImg = $('peopleProfileAvatarImg');
    const peopleProfileAvatarFallback = $('peopleProfileAvatarFallback');
    const peopleProfileName = $('peopleProfileName');
    const peopleProfileSub = $('peopleProfileSub');
    const peopleProfileRole = $('peopleProfileRole');
    const peopleProfileXp = $('peopleProfileXp');
    const peopleProfilePhone = $('peopleProfilePhone');
    const peopleProfileBio = $('peopleProfileBio');
    const peopleProfileLinks = $('peopleProfileLinks');
    const peopleProfileConnectBtn = $('peopleProfileConnectBtn');
    let peopleProfileUserId = 0;

    /* ═══ Tab switching ═══ */
    document.querySelectorAll('.msg-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.msg-tab').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        const t = btn.dataset.tab;
        Object.entries(tabs).forEach(([k,el]) => el.classList.toggle('section-hidden', k !== t));
        if (t === 'people') showView('people');
        else if (!S.activeId) showView('welcome');
        else showView('chat');
      });
    });

    function showView(v) {
      Object.entries(views).forEach(([k,el]) => {
        if (!el) return;
        el.classList.toggle('section-hidden', k !== v);
        el.style.display = k !== v ? 'none' : 'flex';
      });
    }
    showView('welcome');

    /* ═══ Render threads ═══ */
    function renderThreads() {
      const q = String($('msgSearchInput')?.value || '').toLowerCase().trim();
      const both = [
        ...S.privateConversations.map(t=>({...t,_type:'private'})),
        ...S.groupChats.map(t=>({...t,_type:'group'}))
      ].sort((a,b) => (new Date(b.last_message_at||0)) - (new Date(a.last_message_at||0)));

      const filtered = both.filter(t => {
        const name = t._type==='private' ? displayName(t.peer||{}) : String(t.name||'');
        return !q || name.toLowerCase().includes(q);
      });

      if (!filtered.length) {
        $('threadListChats').innerHTML = '<div class="msg-thread-empty"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>No conversations yet.<br>Find people to connect with!</div>';
        return;
      }

      $('threadListChats').innerHTML = filtered.map(t => {
        const id = t.id;
        const type = t._type;
        const active = S.activeType===type && S.activeId===id;
        const name = type==='private' ? esc(displayName(t.peer||{})) : esc(String(t.name||'Group'));
        const ini  = type==='private' ? initials(t.peer?.first_name, t.peer?.last_name) : String(t.name||'G').charAt(0).toUpperCase();
        const isSeen = type==='private'
          && Number(t.last_message_sender_id || 0) === Number(window.msgBootstrap.currentUserId || 0)
          && String(t.last_message_seen_at || '').trim() !== '';
        const sub  = esc(`${String(preview(t) || '').slice(0,55)}${isSeen ? ' · Seen' : ''}`);
        const ts   = esc(relTime(t.last_message_at));
        const unread = Math.max(0, Number(t.unread_count||0));
        const isGroup = type==='group';
        const peerId = Number(t.peer?.id || 0);
        const peerMap = type==='private' ? (S.mapUsers || []).find((u) => Number(u?.id || 0) === peerId) : null;
        const hasStoryAura = type==='private' && Boolean(peerMap?.has_story || t.peer?.has_story);
        const avatarUrl = type==='private'
          ? avatarFor(t.peer || {})
          : (String(t.avatar_url || '').trim() || avatarFor({ id: `group-${id}`, name: t.name || 'Group' }));
        const avatarStyle = avatarBgStyle(avatarUrl);
        return `<button class="msg-thread-item${active?' is-active':''}" data-type="${type}" data-id="${id}">
          <div class="msg-thread-avatar${isGroup?' is-group':''}${hasStoryAura?' has-story-ring':''}" style="${avatarStyle}">${ini}${!isGroup?'<div class="online-dot"></div>':''}</div>
          <div class="msg-thread-body">
            <div class="msg-thread-name">${name}</div>
            <div class="msg-thread-preview">${sub}</div>
          </div>
          <div class="msg-thread-meta">
            <div class="msg-thread-time">${ts}</div>
            ${unread>0?`<span class="msg-thread-badge">${unread}</span>`:''}
          </div>
        </button>`;
      }).join('');

      $('threadListChats').querySelectorAll('.msg-thread-item').forEach(btn => {
        btn.addEventListener('click', () => openThread(btn.dataset.type, Number(btn.dataset.id)));
      });
    }

    /* ═══ Render requests ═══ */
    function renderRequests() {
      const inc = S.incomingRequests;
      const out = S.outgoingRequests;
      const badge = $('reqBadge');
      if (badge) { badge.textContent = inc.length; badge.style.display = inc.length ? 'inline-flex' : 'none'; }
      if (!inc.length && !out.length) {
        $('threadListRequests').innerHTML = '<div class="msg-thread-empty">No pending requests.</div>'; return;
      }
      const incHtml = inc.map(r => {
        const u = r.user||{};
        const avatar = avatarFor(u);
        return `<div class="friend-req-card" data-req-id="${r.id}">
          <div class="msg-thread-avatar" style="width:46px;height:46px;font-size:15px;${avatarBgStyle(avatar)}">${initials(u.first_name,u.last_name)}</div>
          <div class="friend-req-info">
            <div class="friend-req-name">${esc(displayName(u))}</div>
            <div class="friend-req-msg">${esc(r.request_message||'Wants to connect with you.')}</div>
          </div>
          <div class="friend-req-btns">
            <button class="friend-req-btn accept" data-req-action="accept" data-req-id="${r.id}">Accept</button>
            <button class="friend-req-btn decline" data-req-action="decline" data-req-id="${r.id}">Decline</button>
          </div>
        </div>`;
      }).join('');
      const outHtml = out.map(r => {
        const u = r.user||{};
        const avatar = avatarFor(u);
        return `<div class="friend-req-card">
          <div class="msg-thread-avatar" style="width:46px;height:46px;font-size:15px;${avatarBgStyle(avatar)}">${initials(u.first_name,u.last_name)}</div>
          <div class="friend-req-info">
            <div class="friend-req-name">${esc(displayName(u))}</div>
            <div class="friend-req-msg">Request sent ${esc(relTime(r.created_at))}</div>
          </div>
          <div class="friend-req-btns">
            <button class="friend-req-btn decline" data-req-action="cancel" data-req-id="${r.id}">Cancel</button>
          </div>
        </div>`;
      }).join('');
      $('threadListRequests').innerHTML = incHtml + outHtml;
      $('threadListRequests').querySelectorAll('[data-req-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
          btn.disabled = true;
          try { await post('profile_friend_request',{mode:btn.dataset.reqAction,request_id:Number(btn.dataset.reqId)}); await loadData(true); }
          catch(e) { toast(e.message,'error'); }
        });
      });
    }

    const isFriendUser = (userId) => S.friends.some((friend) => Number(friend?.id || 0) === Number(userId || 0));
    const hasOutgoingRequest = (userId) => S.outgoingRequests.some((req) => Number(req?.user?.id || req?.receiver_id || 0) === Number(userId || 0));

    function getPeopleUserById(userId) {
      const uid = Number(userId || 0);
      if (!uid) return null;
      if (S.peopleById && S.peopleById[uid]) return S.peopleById[uid];
      return (S.mapUsers || []).find((user) => Number(user?.id || 0) === uid) || null;
    }

    function closePeopleProfile() {
      peopleProfileUserId = 0;
      if (peopleProfileModal) {
        peopleProfileModal.hidden = true;
      }
    }

    function syncPeopleProfileConnectState(user) {
      if (!peopleProfileConnectBtn) return;
      const uid = Number(user?.id || 0);
      const meId = Number(window.msgBootstrap.currentUserId || 0);

      if (!uid || uid === meId) {
        peopleProfileConnectBtn.disabled = true;
        peopleProfileConnectBtn.textContent = 'Your profile';
        peopleProfileConnectBtn.classList.add('is-sent');
        peopleProfileConnectBtn.dataset.targetUid = '';
        return;
      }

      const alreadyFriend = isFriendUser(uid);
      const alreadySent = hasOutgoingRequest(uid);
      peopleProfileConnectBtn.dataset.targetUid = String(uid);

      if (alreadyFriend) {
        peopleProfileConnectBtn.disabled = true;
        peopleProfileConnectBtn.textContent = 'Connected';
        peopleProfileConnectBtn.classList.add('is-sent');
      } else if (alreadySent) {
        peopleProfileConnectBtn.disabled = true;
        peopleProfileConnectBtn.textContent = 'Sent';
        peopleProfileConnectBtn.classList.add('is-sent');
      } else {
        peopleProfileConnectBtn.disabled = false;
        peopleProfileConnectBtn.textContent = 'Connect';
        peopleProfileConnectBtn.classList.remove('is-sent');
      }
    }

    function openPeopleProfile(userId) {
      const user = getPeopleUserById(userId);
      if (!user || !peopleProfileModal) return;

      peopleProfileUserId = Number(user?.id || 0);
      const userName = displayName(user);
      const userInitials = initials(user?.first_name, user?.last_name);
      const avatarUrl = avatarFor(user);

      if (peopleProfileAvatarImg) {
        peopleProfileAvatarImg.src = avatarUrl;
        peopleProfileAvatarImg.alt = `${userName} avatar`;
      }
      if (peopleProfileAvatarFallback) {
        peopleProfileAvatarFallback.textContent = userInitials;
      }
      if (peopleProfileAvatarWrap) {
        peopleProfileAvatarWrap.classList.remove('is-fallback');
      }

      if (peopleProfileName) peopleProfileName.textContent = userName;
      if (peopleProfileSub) peopleProfileSub.textContent = String(user?.exact_location || user?.country || 'Unknown location');
      if (peopleProfileRole) peopleProfileRole.textContent = String(user?.role || 'member');
      if (peopleProfileXp) peopleProfileXp.textContent = String(Number(user?.xp || 0));
      if (peopleProfilePhone) peopleProfilePhone.textContent = String(user?.phone || 'Not shared');
      if (peopleProfileBio) peopleProfileBio.textContent = String(user?.bio || '').trim() || 'No bio available.';

      const links = Array.isArray(user?.linked_accounts) ? user.linked_accounts : [];
      if (peopleProfileLinks) {
        if (!links.length) {
          peopleProfileLinks.innerHTML = '<span>No linked accounts</span>';
        } else {
          peopleProfileLinks.innerHTML = links
            .slice(0, 6)
            .map((account) => {
              const url = String(account?.profile_url || '').trim();
              if (!url) return '';
              const label = linkedAccountLabel(account);
              const verified = Number(account?.verified || 0) === 1 ? ' ✓' : '';
              return `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer">${esc(label)}${verified}</a>`;
            })
            .filter(Boolean)
            .join('') || '<span>No linked accounts</span>';
        }
      }

      syncPeopleProfileConnectState(user);
      peopleProfileModal.hidden = false;
    }

    async function sendFriendRequest(targetUserId, triggerButton = null) {
      const uid = Number(targetUserId || 0);
      if (!uid) return false;

      if (triggerButton) {
        triggerButton.disabled = true;
        triggerButton.textContent = 'Sending…';
      }

      try {
        await post('profile_friend_request', {
          mode:'send',
          target_user_id: uid,
          request_message:'Let us connect on Diversity.is.'
        });
        if (triggerButton) {
          triggerButton.classList.add('is-sent');
          triggerButton.disabled = true;
          triggerButton.textContent = 'Sent';
        }
        toast('Friend request sent.');
        await loadData(true);
        return true;
      } catch (e) {
        toast(e.message || 'Could not send request.', 'error');
        return false;
      } finally {
        if (triggerButton && !triggerButton.classList.contains('is-sent')) {
          triggerButton.disabled = false;
          triggerButton.textContent = 'Connect';
        }
      }
    }

    /* ═══ Render people ═══ */
    function renderPeople() {
      const q = String($('peopleSearchInput')?.value||'').toLowerCase().trim();
      const meId = Number(window.msgBootstrap.currentUserId || 0);
      const friendIds = new Set(S.friends.map((f) => Number(f?.id || 0)));
      const outIds = new Set(S.outgoingRequests.map((r) => Number(r?.user?.id || r?.receiver_id || 0)));
      const candidates = (S.mapUsers||[]).filter((u) => {
        const uid = Number(u?.id || 0);
        if (!uid || uid === meId || friendIds.has(uid)) return false;
        return !q
          || displayName(u).toLowerCase().includes(q)
          || String(u?.exact_location || u?.country || '').toLowerCase().includes(q);
      }).slice(0, 40);

      S.peopleById = {};
      candidates.forEach((user) => {
        const uid = Number(user?.id || 0);
        if (uid > 0) {
          S.peopleById[uid] = user;
        }
      });

      if (!candidates.length) {
        $('peopleDirectoryList').innerHTML = '<div class="msg-thread-empty">No discoverable users right now.</div>';
        if (peopleProfileUserId) closePeopleProfile();
        return;
      }

      $('peopleDirectoryList').innerHTML = candidates.map((u) => {
        const uid = Number(u?.id || 0);
        const sent = outIds.has(uid);
        const isFriend = friendIds.has(uid);
        const userName = displayName(u);
        const avatarUrl = avatarFor(u);
        const userInitials = initials(u?.first_name, u?.last_name);
        const locationText = String(u?.exact_location || u?.country || 'Unknown location');

        return `<div class="people-card">
          <button type="button" class="people-card-avatar" data-open-profile-uid="${uid}" aria-label="View mini profile for ${esc(userName)}">
            <img src="${esc(avatarUrl)}" alt="${esc(userName)} avatar" loading="lazy" onerror="this.closest('.people-card-avatar').classList.add('is-fallback');">
            <span class="people-card-avatar-fallback">${esc(userInitials)}</span>
          </button>
          <div class="people-card-info" role="button" tabindex="0" data-open-profile-uid="${uid}" aria-label="Open ${esc(userName)} profile">
            <div class="people-card-name">${esc(userName)}</div>
            <div class="people-card-sub">${esc(locationText)}</div>
          </div>
          ${isFriend
            ? `<button class="people-connect-btn is-sent" disabled>Connected</button>`
            : sent
              ? `<button class="people-connect-btn is-sent" disabled>Sent</button>`
              : `<button class="people-connect-btn" data-connect-uid="${uid}">Connect</button>`}
        </div>`;
      }).join('');

      $('peopleDirectoryList').querySelectorAll('[data-open-profile-uid]').forEach((el) => {
        const uid = Number(el.dataset.openProfileUid || 0);
        if (!uid) return;
        el.addEventListener('click', () => openPeopleProfile(uid));
        if (el.tagName === 'BUTTON') {
          return;
        }
        el.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openPeopleProfile(uid);
          }
        });
      });

      $('peopleDirectoryList').querySelectorAll('[data-connect-uid]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          await sendFriendRequest(Number(btn.dataset.connectUid), btn);
        });
      });

      if (peopleProfileUserId) {
        const refreshed = getPeopleUserById(peopleProfileUserId);
        if (refreshed) {
          openPeopleProfile(peopleProfileUserId);
        } else {
          closePeopleProfile();
        }
      }
    }

    function renderRightPanelMembers(type, thread) {
      const list = $('rightPanelMembersList');
      if (!list) return;

      const meId = Number(window.msgBootstrap.currentUserId || 0);
      if (type === 'group') {
        const members = Array.isArray(thread?.members) ? thread.members : [];
        const canModerate = ['owner', 'admin'].includes(String(thread?.member_role || '').toLowerCase());
        const rows = members
          .filter((member) => Number(member?.user_id || 0) !== meId)
          .map((member) => {
            const user = member?.user || {};
            const uid = Number(member?.user_id || 0);
            const roleLabel = String(member?.role || 'member');
            return `<div class="right-member-row" style="justify-content:space-between;">
              <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                <div class="right-member-avatar" style="${avatarBgStyle(avatarFor(user))}">${initials(user.first_name,user.last_name)}</div>
                <div style="min-width:0;">
                  <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(displayName(user))}</div>
                  <div style="font-size:11px;color:var(--text-muted);text-transform:capitalize;">${esc(roleLabel)}</div>
                </div>
              </div>
              ${canModerate && uid > 0 ? `<button class="msg-delete-btn" data-remove-member="${uid}" style="margin:0;">Remove</button>` : ''}
            </div>`;
          });

        list.innerHTML = rows.join('') || '<div style="font-size:12px;color:var(--text-muted);">No other members.</div>';
        list.querySelectorAll('[data-remove-member]').forEach((btn) => {
          btn.addEventListener('click', async () => {
            const targetUserId = Number(btn.dataset.removeMember || 0);
            if (!targetUserId || !thread?.id) return;
            const ok = await confirmDialog({
              title: 'Remove member?',
              text: 'They will immediately lose access to this group chat.',
              confirmText: 'Remove',
              cancelText: 'Cancel',
              icon: 'warning'
            });
            if (!ok) return;
            btn.disabled = true;
            try {
              await post('profile_group_manage', {
                mode: 'remove_member',
                group_chat_id: Number(thread.id),
                target_user_id: targetUserId
              });
              await loadData(true);
              await loadMessages();
            } catch (e) {
              toast(e.message || 'Could not remove member.', 'error');
            } finally {
              btn.disabled = false;
            }
          });
        });
        return;
      }

      if (type === 'private' && thread?.peer) {
        const peer = thread.peer;
        list.innerHTML = `<div class="right-member-row"><div class="right-member-avatar" style="${avatarBgStyle(avatarFor(peer))}">${initials(peer.first_name,peer.last_name)}</div><div>${esc(displayName(peer))}</div></div>`;
        return;
      }

      list.innerHTML = '';
    }

    function updateGroupManageActions(type, thread) {
      const actions = $('groupManageActions');
      const section = $('groupManageSection');
      if (!actions) return;
      if (type !== 'group' || !thread) {
        actions.classList.remove('is-visible');
        if (section) section.style.display = 'none';
        return;
      }

      actions.classList.add('is-visible');
      if (section) section.style.display = '';
      const role = String(thread.member_role || 'member').toLowerCase();
      S.activeGroupRole = role;
      const canDelete = role === 'owner' || role === 'admin';
      if ($('groupDeleteBtn')) {
        $('groupDeleteBtn').style.display = canDelete ? 'flex' : 'none';
      }
    }

    /* ═══ Open thread ═══ */
    async function openThread(type, id) {
      S.activeType=type; S.activeId=id;
      showView('chat');
      const src = type==='group' ? S.groupChats : S.privateConversations;
      const thread = src.find(t=>t.id===id);
      if (thread) {
        const name = type==='private' ? displayName(thread.peer||{}) : String(thread.name||'Group');
        const sub  = type==='private' ? String(thread.peer?.role||'member') : String(thread.description||'Group chat');
        const ini  = type==='private' ? initials(thread.peer?.first_name,thread.peer?.last_name) : name.charAt(0).toUpperCase();
        const headerAvatarUrl = type==='private'
          ? avatarFor(thread.peer || {})
          : (String(thread.avatar_url || '').trim() || avatarFor({ id: `group-${thread.id}`, name }));
        $('chatHeaderAvatar').textContent = ini;
        $('chatHeaderAvatar').style.backgroundImage = `url('${headerAvatarUrl}')`;
        $('chatHeaderName').textContent = name;
        $('chatHeaderSub').textContent = sub;
        setCallPeerLabel(name);
        thread.unread_count = 0;
        renderRightPanelMembers(type, thread);
        updateGroupManageActions(type, thread);
      } else {
        updateGroupManageActions('', null);
      }
      renderThreads(); await loadMessages();
      $('sendMsgBtn').disabled=false; $('composerInput').focus();
    }

    /* ═══ Load & render messages ═══ */
    async function loadMessages() {
      if (!S.activeType||!S.activeId) return;
      try {
        const data = await get('profile_messages',{thread_type:S.activeType,thread_id:S.activeId});
        if (S.activeType === 'group') {
          const thread = S.groupChats.find((g) => Number(g.id) === Number(S.activeId));
          if (thread && Array.isArray(data.group_members)) {
            thread.members = data.group_members;
            renderRightPanelMembers('group', thread);
          }
        }
        renderMessages(data.messages||[]);
      } catch(e) { $('msgBody').innerHTML='<div class="msg-thread-empty">Could not load messages.</div>'; }
    }

    function renderMessages(msgs) {
      const body=$('msgBody'); if (!body) return;
      const meId=window.msgBootstrap.currentUserId;
      const activeGroupThread = S.activeType === 'group'
        ? S.groupChats.find((thread) => Number(thread.id) === Number(S.activeId))
        : null;
      const canModerateGroupMessages = activeGroupThread
        ? ['owner', 'admin'].includes(String(activeGroupThread.member_role || '').toLowerCase())
        : false;
      const ti=$('typingIndicator'); const sr=$('smartReplies');
      if (ti) ti.style.display='none';
      if (sr) sr.style.display=msgs.length>2&&Number(msgs[msgs.length-1]?.sender_id||0)!==meId?'flex':'none';
      if (!msgs.length) { body.innerHTML='<div class="msg-date-divider">No messages yet — say hello! 👋</div>'; return; }
      const messageById = new Map(msgs.map((entry) => [Number(entry?.id || 0), entry]));
      let lastDate='';
      body.innerHTML = msgs.map(m => {
        const messageId = Number(m.id || 0);
        const out = Number(m.sender_id||0)===meId;
        const sender=m.sender||{};
        const ini=initials(sender.first_name,sender.last_name);
        const avatarStyle = avatarBgStyle(avatarFor(sender));
        const ts=relTime(m.created_at);
        const day=m.created_at?new Date(m.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}):'';
        let divider='';
        if (day&&day!==lastDate) { divider=`<div class="msg-date-divider">${esc(day)}</div>`; lastDate=day; }
        const type=String(m.message_type||'text');
        const isDeleted = Number(m.is_deleted || 0) === 1;
        const mediaUrl = resolvePublicMediaUrl(m.media_url || '');
        const metadata = (m.metadata && typeof m.metadata === 'object') ? m.metadata : {};
        const attachmentName = String(metadata.original_name || metadata.file_name || '').trim();
        const attachmentMime = String(metadata.mime_type || '').trim();
        let content='';
        if (isDeleted) {
          content = '<div class="msg-bubble-content"><em style="opacity:.65;font-size:12px;">Message deleted</em></div>';
        } else if (type==='image' && mediaUrl) {
          content=`<div class="msg-bubble-content"><div class="msg-image-wrap"><img class="msg-image" src="${esc(mediaUrl)}" style="max-width:220px;border-radius:12px;display:block;margin-top:2px;cursor:pointer;" alt="img" data-src="${esc(mediaUrl)}"></div></div>`;
        } else if (type==='video' && mediaUrl) {
          content=`<div class="msg-bubble-content"><video class="msg-video" controls playsinline style="max-width:240px;border-radius:14px;display:block;background:#020617;" src="${esc(mediaUrl)}"></video></div>`;
        } else if (type==='audio' && mediaUrl) {
          const dur = metadata.duration ? Number(metadata.duration) : null;
          content=`<div class="msg-bubble-content"><div class="msg-audio-wrap" style="display:flex;flex-direction:column;gap:8px;min-width:min(260px,70vw);"><audio class="msg-audio-player" controls preload="metadata" src="${esc(mediaUrl)}"></audio>${dur?`<span class="audio-duration" style="font-size:11px;opacity:.7;">${esc(dur)}s voice note</span>`:''}</div></div>`;
        } else if (type==='file' && mediaUrl) {
          const fallbackLabel = attachmentName || mediaUrl.split('/').pop() || 'Attachment';
          const sizeLabel = Number(metadata.size || 0) > 0 ? ` · ${Math.max(1, Math.round(Number(metadata.size || 0) / 1024))} KB` : '';
          content=`<div class="msg-bubble-content"><a class="msg-file-card" href="${esc(mediaUrl)}" target="_blank" rel="noopener noreferrer" style="display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:14px;background:rgba(15,23,42,.06);border:1px solid rgba(15,23,42,.08);text-decoration:none;color:inherit;max-width:260px;"><span style="width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,rgba(99,102,241,.16),rgba(14,165,233,.16));display:flex;align-items:center;justify-content:center;font-size:18px;">📎</span><span style="display:flex;flex-direction:column;min-width:0;"><strong style="font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(fallbackLabel)}</strong><small style="font-size:11px;opacity:.68;">${esc(attachmentMime || 'File')}${esc(sizeLabel)}</small></span></a></div>`;
        } else if (type==='system') {
          content=`<div class="msg-bubble-content"><em style="opacity:.6;font-size:12px;">${esc(m.body||'System update')}</em></div>`;
        } else {
          content=`<div class="msg-bubble-content"><p>${esc(m.body||'')}</p></div>`;
        }

        const canDelete = !isDeleted && (out || canModerateGroupMessages);
        const canEdit = !isDeleted && out && type === 'text' && String(m.media_url || '').trim() === '';
        const seenLabel = out && String(m.seen_at || '').trim() !== ''
          ? `<div class="msg-bubble-seen">Seen ${esc(formatSeenAt(m.seen_at))}</div>`
          : '';
        const editedLabel = Number(m.is_edited || 0) === 1
          ? '<span class="msg-edited-indicator">edited</span>'
          : '';
        const actionButtons = `${canEdit ? `<button class="msg-edit-btn" data-edit-message-id="${messageId}">Edit</button>` : ''}${canDelete ? `<button class="msg-delete-btn" data-delete-message-id="${messageId}">Delete</button>` : ''}`;
        const highlightClass = messageId > 0 && Number(S.highlightMessageId || 0) === messageId ? ' is-highlight' : '';
        return `${divider}<div class="msg-bubble-row ${out?'is-outgoing':'is-incoming'}${highlightClass}" data-message-row-id="${messageId}">
          ${!out?`<div class="msg-bubble-avatar" style="${avatarStyle}">${ini}</div>`:''}
          <div>
            <div class="msg-bubble">${content}</div>
            <div class="msg-bubble-meta">${ts}${editedLabel}</div>
            ${seenLabel}
            ${actionButtons ? `<div class="msg-action-row">${actionButtons}</div>` : ''}
          </div>
        </div>`;
      }).join('');
      // attach interactions for images & audio
      const highlightedRow = Number(S.highlightMessageId || 0) > 0
        ? body.querySelector(`[data-message-row-id="${Number(S.highlightMessageId || 0)}"]`)
        : null;
      if (highlightedRow) {
        highlightedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(() => { S.highlightMessageId = 0; }, 1400);
      } else {
        body.scrollTo({ top: body.scrollHeight, behavior: 'smooth' });
      }
      // image lightbox
      Array.from(body.querySelectorAll('.msg-image')).forEach(img => {
        img.addEventListener('click', () => {
          const lb = $('imageLightbox'); const lbImg = $('lightboxImage');
          if (!lb || !lbImg) return; lbImg.src = img.dataset.src || img.src || '';
          lb.style.display = 'flex'; lb.setAttribute('aria-hidden','false');
        });
      });
      Array.from(body.querySelectorAll('[data-edit-message-id]')).forEach((btn) => {
        btn.addEventListener('click', async () => {
          const messageId = Number(btn.dataset.editMessageId || 0);
          if (!messageId) return;
          const message = messageById.get(messageId) || null;
          if (!message) return;

          const currentBody = String(message.body || '').trim();
          const nextBody = await promptTextDialog({
            title: 'Edit Message',
            value: currentBody,
            placeholder: 'Update your message…',
            confirmText: 'Save changes'
          });
          if (nextBody === null) return;
          if (!nextBody) {
            toast('Message cannot be empty.', 'warning');
            return;
          }
          if (nextBody === currentBody) {
            return;
          }

          btn.disabled = true;
          try {
            await post('profile_edit_message', { message_id: messageId, body: nextBody });
            S.highlightMessageId = messageId;
            await loadMessages();
            await loadData(true);
          } catch (e) {
            toast(e.message || 'Could not edit message.', 'error');
          } finally {
            btn.disabled = false;
          }
        });
      });

      Array.from(body.querySelectorAll('[data-delete-message-id]')).forEach((btn) => {
        btn.addEventListener('click', async () => {
          const messageId = Number(btn.dataset.deleteMessageId || 0);
          if (!messageId) return;
          const ok = await confirmDialog({
            title: 'Delete Message?',
            text: 'This message will be removed for everyone in the conversation.',
            confirmText: 'Delete',
            icon: 'warning'
          });
          if (!ok) return;
          btn.disabled = true;
          try {
            await post('profile_delete_message', { message_id: messageId });
            await loadMessages();
            await loadData(true);
          } catch (e) {
            toast(e.message || 'Could not delete message.', 'error');
          } finally {
            btn.disabled = false;
          }
        });
      });
    }

    /* ═══ Send message ═══ */
    async function sendMessage(messageType='text', mediaUrl='') {
      const input=$('composerInput');
      const body=String(input?.value||'').trim();
      if (!body&&!mediaUrl) return;
      if (!S.activeType||!S.activeId) { toast('Select a conversation first.','warning'); return; }
      $('sendMsgBtn').disabled=true;
      try {
        await post('profile_send_message',{thread_type:S.activeType,thread_id:S.activeId,message_type:messageType,body,media_url:mediaUrl});
        if (input) input.value='';
        input.style.height='auto';
        await loadMessages(); await loadData(true);
      } catch(e) { toast(e.message||'Could not send.','error'); }
      finally { $('sendMsgBtn').disabled=false; input?.focus(); }
    }

    /* ═══ Load data ═══ */
    async function loadData(force=false) {
      if (S.loading&&!force) return;
      S.loading=true;
      try {
        const d=await get('profile_social_data');
        S.friends=d.friends||[]; S.privateConversations=d.private_conversations||[];
        S.groupChats=d.group_chats||[]; S.mapUsers=d.map_users||[];
        S.incomingRequests=d.incoming_requests||[]; S.outgoingRequests=d.outgoing_requests||[];
        S.loaded=true; renderThreads(); renderRequests(); renderPeople();
        if (S.activeType && S.activeId) {
          const source = S.activeType === 'group' ? S.groupChats : S.privateConversations;
          const currentThread = source.find((entry) => Number(entry.id) === Number(S.activeId));
          if (currentThread) {
            renderRightPanelMembers(S.activeType, currentThread);
            updateGroupManageActions(S.activeType, currentThread);
          }
        }
      } catch(e) { console.warn('Could not load social data:',e); }
      finally { S.loading=false; }
    }

    /* ═══ Emoji picker ═══ */
    const EMOJIS=['😀','😂','🥰','😍','😎','🤔','😢','😡','🥺','🤩','👍','👎','❤️','🔥','✅','⭐','🎉','🙌','💯','🚀','💡','🎯','🏆','💬','📎','🖼️','📌','💼','🌍','✨','🤝','💪','🙏','🎊','🎁','💎','🔮'];
    function buildEmojiPicker() {
      const el=$('emojiDropdown'); if (!el||el.children.length) return;
      EMOJIS.forEach(e => {
        const b=document.createElement('button');
        b.className='emoji-btn'; b.textContent=e; b.type='button';
        b.addEventListener('click',()=>{
          const inp=$('composerInput'); if (inp){inp.value+=e;inp.focus();}
          el.classList.add('is-hidden');
        });
        el.appendChild(b);
      });
    }

    /* ═══ Call overlay ═══ */
    function genRoomCode(){return `R${S.activeType||'chat'}-${S.activeId||0}-${Math.random().toString(36).slice(2,6).toUpperCase()}`;}

    async function ensureLocalCallStream(wantsVideo=true) {
      if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('Camera/microphone are not available in this browser.');
      }

      if (S.callStream) {
        S.callStream.getTracks().forEach((track) => track.stop());
        S.callStream = null;
      }

      S.callStream = await navigator.mediaDevices.getUserMedia({
        audio: true,
        video: wantsVideo,
      });

      const lv = $('callLocalVideo');
      if (lv) lv.srcObject = S.callStream;

      const lf = $('callLocalFallback');
      if (lf) lf.style.display = wantsVideo ? 'none' : 'flex';

      S.callMicOn = true;
      S.callCameraOn = wantsVideo;
      updateCallBtns();
    }

    function setCallStatus(label = 'Connecting…') {
      const safe = String(label || 'Connecting…').trim() || 'Connecting…';
      if ($('callSub')) $('callSub').textContent = safe;
      if ($('callHeadStatus')) $('callHeadStatus').textContent = safe;
    }

    function setCallPeerLabel(label = 'Participant') {
      const safe = String(label || 'Participant').trim() || 'Participant';
      if ($('callRemoteLabel')) $('callRemoteLabel').textContent = safe;
      if ($('callHeadPeer')) $('callHeadPeer').textContent = safe;
    }

    function createPeerConnection() {
      if (!window.RTCPeerConnection) {
        throw new Error('WebRTC is not supported by this browser.');
      }

      if (S.callPc) {
        try { S.callPc.close(); } catch (e) {}
      }

      const pc = new RTCPeerConnection({
        iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
      });

      S.callRemoteStream = new MediaStream();
      const rv = $('callRemoteVideo');
      if (rv) rv.srcObject = S.callRemoteStream;

      pc.ontrack = (event) => {
        event.streams[0]?.getTracks().forEach((track) => {
          S.callRemoteStream?.addTrack(track);
        });
        const rf = $('callRemoteFallback');
        if (rf) rf.style.display = 'none';
      };

      pc.onicecandidate = async (event) => {
        if (!event.candidate || !S.callSessionId) return;
        try {
          await post('profile_call_relay', {
            mode: 'signal',
            session_id: S.callSessionId,
            signal_type: 'candidate',
            payload: event.candidate,
          });
        } catch (e) {
          console.warn('ICE candidate relay failed', e);
        }
      };

      pc.onconnectionstatechange = () => {
        if (!pc.connectionState) return;
        if (pc.connectionState === 'connected') setCallStatus('Connected');
        if (pc.connectionState === 'connecting') setCallStatus('Connecting media…');
        if (pc.connectionState === 'new') setCallStatus('Negotiating…');
        if (['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
          setCallStatus('Connection ended');
          if (S.callOpen) {
            closeCall(false);
          }
        }
      };

      if (S.callStream) {
        S.callStream.getTracks().forEach((track) => pc.addTrack(track, S.callStream));
      }

      S.callPc = pc;
      return pc;
    }

    function showCallOverlay(type='video', subtitle='Connecting…') {
      S.callOpen = true;
      $('callTitle').textContent = type === 'audio' ? 'Audio Call' : 'Video Call';
      setCallStatus(subtitle);
      $('callRoomCode').textContent = genRoomCode();
      setCallPeerLabel($('chatHeaderName')?.textContent || 'Participant');
      $('callOverlay').hidden = false;
      updateCallBtns();
    }

    async function applyIncomingOffer(signalPayload) {
      if (!S.callPc || !signalPayload) return;
      if (S.callPc.currentRemoteDescription) return;

      try {
        await S.callPc.setRemoteDescription(new RTCSessionDescription(signalPayload));
        const answer = await S.callPc.createAnswer();
        await S.callPc.setLocalDescription(answer);

        while (S.pendingRemoteCandidates.length) {
          const queued = S.pendingRemoteCandidates.shift();
          if (!queued) continue;
          try { await S.callPc.addIceCandidate(new RTCIceCandidate(queued)); } catch (e) {}
        }

        await post('profile_call_relay', {
          mode: 'answer',
          session_id: S.callSessionId,
          decision: 'accept',
          answer,
        });
      } catch (e) {
        console.warn('Could not apply incoming offer', e);
      }
    }

    async function processCallSignals() {
      if (!S.callSessionId) return;
      try {
        const signalResponse = await get('profile_call_relay', {
          mode: 'signals',
          session_id: S.callSessionId,
          last_signal_id: S.callSignalCursor,
        });
        const signalList = Array.isArray(signalResponse.signals) ? signalResponse.signals : [];
        for (const signal of signalList) {
          S.callSignalCursor = Math.max(S.callSignalCursor, Number(signal.id || 0));
          const signalType = String(signal.signal_type || 'candidate');
          const payload = signal.payload || null;
          if (!S.callPc) continue;

          if (signalType === 'offer' && payload) {
            await applyIncomingOffer(payload);
            continue;
          }

          if (signalType === 'answer' && payload) {
            if (!S.callPc.currentRemoteDescription) {
              await S.callPc.setRemoteDescription(new RTCSessionDescription(payload));
              while (S.pendingRemoteCandidates.length) {
                const queued = S.pendingRemoteCandidates.shift();
                if (!queued) continue;
                try { await S.callPc.addIceCandidate(new RTCIceCandidate(queued)); } catch (e) {}
              }
            }
            continue;
          }

          if (signalType === 'candidate' && payload) {
            if (!S.callPc.currentRemoteDescription) {
              S.pendingRemoteCandidates.push(payload);
              continue;
            }
            try {
              await S.callPc.addIceCandidate(new RTCIceCandidate(payload));
            } catch (e) {
              console.warn('Could not add ICE candidate', e);
            }
            continue;
          }

          if (signalType === 'bye') {
            closeCall(false);
          }
        }
      } catch (e) {
        console.warn('Could not fetch call signals', e);
      }
    }

    async function openCall(mode='video') {
      if (!S.activeType || !S.activeId) {
        toast('Select a conversation first.', 'warning');
        return;
      }

      try {
        if (S.callOpen) {
          await closeCall(true);
        }
        const wantsVideo = mode !== 'audio';
        await ensureLocalCallStream(wantsVideo);
        createPeerConnection();

        const offer = await S.callPc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: wantsVideo });
        await S.callPc.setLocalDescription(offer);

        const callStart = await post('profile_call_relay', {
          mode: 'start',
          thread_type: S.activeType,
          thread_id: S.activeId,
          call_type: wantsVideo ? 'video' : 'audio',
          offer,
        });

        S.callSessionId = Number(callStart?.session?.id || 0);
        S.callSignalCursor = 0;
        S.callIsInitiator = true;
        S.callType = wantsVideo ? 'video' : 'audio';
        S.pendingRemoteCandidates = [];
        if ($('callRemoteFallback')) $('callRemoteFallback').style.display = 'flex';
        showCallOverlay(S.callType, `Calling ${$('chatHeaderName')?.textContent || 'participant'}…`);
      } catch (e) {
        toast(e.message || 'Could not start call.', 'error');
      }
    }

    async function acceptIncomingCall(incoming) {
      const callType = String(incoming?.call_type || 'video');
      const wantsVideo = callType !== 'audio';
      S.callSessionId = Number(incoming?.id || 0);
      S.callSignalCursor = 0;
      S.callIsInitiator = false;
      S.callType = callType;
      S.pendingRemoteCandidates = [];

      try {
        await ensureLocalCallStream(wantsVideo);
        createPeerConnection();
        if ($('callRemoteFallback')) $('callRemoteFallback').style.display = 'flex';
        showCallOverlay(callType, 'Joining call…');

        // Accept immediately; if offer arrives later it will be processed in signal polling.
        await post('profile_call_relay', {
          mode: 'answer',
          session_id: S.callSessionId,
          decision: 'accept',
        });

        await processCallSignals();
      } catch (e) {
        toast(e.message || 'Could not accept incoming call.', 'error');
      }
    }

    async function rejectIncomingCall(incoming) {
      const sid = Number(incoming?.id || 0);
      if (!sid) return;
      try {
        await post('profile_call_relay', {
          mode: 'answer',
          session_id: sid,
          decision: 'reject',
        });
      } catch (e) {
        console.warn('Could not reject call', e);
      }
    }

    async function closeCall(notifyRemote = true) {
      if (notifyRemote && S.callSessionId) {
        try {
          await post('profile_call_relay', {
            mode: 'end',
            session_id: S.callSessionId,
            reason: 'Ended by participant',
          });
        } catch (e) {
          console.warn('Could not end call remotely', e);
        }
      }

      if (S.callPc) {
        try { S.callPc.close(); } catch (e) {}
        S.callPc = null;
      }
      if (S.callStream) {
        S.callStream.getTracks().forEach((track) => track.stop());
        S.callStream = null;
      }
      if (S.callScreenStream) {
        S.callScreenStream.getTracks().forEach((track) => track.stop());
        S.callScreenStream = null;
      }

      const lv = $('callLocalVideo'); if (lv) lv.srcObject = null;
      const rv = $('callRemoteVideo'); if (rv) rv.srcObject = null;
      const lf = $('callLocalFallback'); if (lf) lf.style.display = 'flex';
      const rf = $('callRemoteFallback'); if (rf) rf.style.display = 'flex';

      S.callRemoteStream = null;
      S.callOpen = false;
      S.callSessionId = 0;
      S.callSignalCursor = 0;
      S.callIsInitiator = false;
      S.pendingRemoteCandidates = [];
      setCallStatus('Call ended');
      $('callOverlay').hidden = true;
      updateCallBtns();
    }

    function updateCallBtns(){
      const mb=$('callMicBtn'),cb=$('callCameraBtn');
      if(mb)mb.className=`call-ctrl-btn ${S.callMicOn?'is-active':''}`;
      if(cb)cb.className=`call-ctrl-btn ${S.callCameraOn?'is-active':''}`;
    }

    async function pollCalls() {
      try {
        const relay = await get('profile_call_relay', { mode: 'poll' });
        const incoming = Array.isArray(relay.incoming) ? relay.incoming : [];
        const active = Array.isArray(relay.active) ? relay.active : [];

        if (S.callSessionId) {
          const stillActive = active.some((entry) => Number(entry?.id || 0) === Number(S.callSessionId));
          if (!stillActive && S.callOpen) {
            closeCall(false);
          }
        }

        if (!S.callOpen && !S.callIncomingPromptOpen && incoming.length) {
          const nextIncoming = incoming.find((entry) => !S.callIncomingSeen[String(entry.id)]);
          if (nextIncoming) {
            S.callIncomingSeen[String(nextIncoming.id)] = true;
            S.callIncomingPromptOpen = true;

            const callerName = displayName(nextIncoming.caller || {});
            const callLabel = String(nextIncoming.call_type || 'video') === 'audio' ? 'Audio call' : 'Video call';

            let accepted = false;
            if (window.Swal) {
              const res = await Swal.fire({
                title: `${callLabel} incoming`,
                text: `${callerName} is calling you.`,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Accept',
                cancelButtonText: 'Reject',
                allowOutsideClick: false,
              });
              accepted = !!res.isConfirmed;
            } else {
              accepted = await confirmDialog({
                title: `${callLabel} incoming`,
                text: `${callerName} is calling you.`,
                confirmText: 'Accept',
                cancelText: 'Reject',
                icon: 'info'
              });
            }

            if (accepted) {
              await openThread(String(nextIncoming.thread_type || 'private'), Number(nextIncoming.thread_id || 0));
              await acceptIncomingCall(nextIncoming);
            } else {
              await rejectIncomingCall(nextIncoming);
            }

            S.callIncomingPromptOpen = false;
          }
        }

        if (S.callSessionId && S.callOpen) {
          await processCallSignals();
        }
      } catch (e) {
        console.warn('Call polling failed', e);
      }
    }

    /* ═══ DOMContentLoaded ═══ */
    document.addEventListener('DOMContentLoaded', async () => {
      lucide.createIcons();

      // Input auto-grow + send enable
      const inp=$('composerInput');
      if (inp) {
        inp.addEventListener('input', function(){
          this.style.height='auto';
          this.style.height=Math.min(this.scrollHeight,120)+'px';
          $('sendMsgBtn').disabled=!this.value.trim();
        });
        inp.addEventListener('keydown', e=>{
          if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMessage();}
        });
      }
      $('sendMsgBtn')?.addEventListener('click',()=>sendMessage());

      // Search
      $('msgSearchInput')?.addEventListener('input',renderThreads);
      $('peopleSearchInput')?.addEventListener('input',renderPeople);

      if (peopleProfileAvatarImg) {
        peopleProfileAvatarImg.addEventListener('error', () => peopleProfileAvatarWrap?.classList.add('is-fallback'));
        peopleProfileAvatarImg.addEventListener('load', () => peopleProfileAvatarWrap?.classList.remove('is-fallback'));
      }

      peopleProfileCloseBtn?.addEventListener('click', closePeopleProfile);
      peopleProfileModal?.addEventListener('click', (event) => {
        if (event.target === peopleProfileModal) {
          closePeopleProfile();
        }
      });
      peopleProfileConnectBtn?.addEventListener('click', async () => {
        const uid = Number(peopleProfileConnectBtn.dataset.targetUid || 0);
        if (!uid) return;
        await sendFriendRequest(uid, peopleProfileConnectBtn);
      });

      // Emoji
      buildEmojiPicker();
      $('emojiToggleBtn')?.addEventListener('click',e=>{e.stopPropagation();$('emojiDropdown')?.classList.toggle('is-hidden');});
      document.addEventListener('click',()=>$('emojiDropdown')?.classList.add('is-hidden'));

      // Image attach — choose file from device and upload
      const _imageFileInput = $('imageFileInput');
      const _attachmentFileInput = $('attachmentFileInput');
      const _recordBtn = $('recordVoiceBtn');
      let _recState = {
        stream: null,
        mediaRecorder: null,
        chunks: [],
        startedAt: 0,
        stopping: false,
      };

      const normalizeMessageType = (type) => {
        switch (String(type || '').toLowerCase()) {
          case 'image':
          case 'video':
          case 'audio':
          case 'file':
            return String(type).toLowerCase();
          default:
            return 'file';
        }
      };

      const resetRecorderState = () => {
        if (_recState.stream) {
          _recState.stream.getTracks().forEach((track) => {
            try { track.stop(); } catch (e) {}
          });
        }
        _recState = {
          stream: null,
          mediaRecorder: null,
          chunks: [],
          startedAt: 0,
          stopping: false,
        };
        _recordBtn?.classList?.remove('is-recording');
      };

      async function uploadMediaFile(file){
        const fd = new FormData();
        fd.append('media', file);
        const res = await fetch('../../index.php?action=upload_message_media', { method: 'POST', body: fd });
        const txt = await res.text();
        try {
          return txt ? JSON.parse(txt) : {};
        } catch(e){
          return { success: false, message: 'Invalid server response' };
        }
      }

      const sendUploadedMessage = async (file, inputEl) => {
        if (!S.activeType || !S.activeId) {
          toast('Open a conversation first.', 'warning');
          if (inputEl) inputEl.value = '';
          return;
        }

        try {
          $('sendMsgBtn').disabled = true;
          const uploadResult = await uploadMediaFile(file);
          if (!uploadResult || !uploadResult.success) throw new Error((uploadResult && uploadResult.message) ? uploadResult.message : 'Upload failed');

          const messageType = normalizeMessageType(uploadResult.type || file.type?.split('/')?.[0] || 'file');
          const metadata = {
            original_name: String(uploadResult.original_name || file.name || ''),
            mime_type: String(uploadResult.mime_type || file.type || ''),
            size: Number(uploadResult.size || file.size || 0),
            extension: String(uploadResult.extension || ''),
          };
          if (uploadResult.duration) metadata.duration = Number(uploadResult.duration || 0);

          await post('profile_send_message', {
            thread_type: S.activeType,
            thread_id: S.activeId,
            message_type: messageType,
            body: '',
            media_url: uploadResult.media_url,
            metadata
          });
          await loadMessages();
          await loadData(true);
        } catch(err) {
          toast(err.message || 'Upload failed', 'error');
        } finally {
          $('sendMsgBtn').disabled = false;
          if (inputEl) inputEl.value = '';
        }
      };

      $('attachImageBtn')?.addEventListener('click', ()=> { _imageFileInput?.click(); });
      _imageFileInput?.addEventListener('change', async function(){
        const file = this.files && this.files[0];
        if (!file) return;
        await sendUploadedMessage(file, this);
      });
      $('attachFileBtn')?.addEventListener('click', ()=> { _attachmentFileInput?.click(); });
      _attachmentFileInput?.addEventListener('change', async function(){
        const file = this.files && this.files[0];
        if (!file) return;
        await sendUploadedMessage(file, this);
      });

      async function _startRecording(){
        if (!S.activeType || !S.activeId) { toast('Open a conversation first.','warning'); return; }
        if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) { toast('Microphone recording is not supported on this device.','warning'); return; }
        if (_recState.mediaRecorder && _recState.mediaRecorder.state !== 'inactive') return;

        try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          const mimeTypeCandidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
          const mimeType = mimeTypeCandidates.find((candidate) => {
            try { return window.MediaRecorder.isTypeSupported(candidate); } catch (e) { return false; }
          }) || '';
          const mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);

          _recState.stream = stream;
          _recState.mediaRecorder = mediaRecorder;
          _recState.chunks = [];
          _recState.startedAt = Date.now();
          _recState.stopping = false;

          mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size) _recState.chunks.push(event.data);
          };
          mediaRecorder.onerror = (event) => {
            resetRecorderState();
            toast(event?.error?.message || 'Could not record audio.','error');
          };
          mediaRecorder.onstop = async () => {
            const duration = Math.max(1, Math.round((Date.now() - Number(_recState.startedAt || Date.now())) / 1000));
            const chunks = Array.isArray(_recState.chunks) ? [..._recState.chunks] : [];
            const blobType = chunks[0]?.type || mediaRecorder.mimeType || 'audio/webm';
            const blob = new Blob(chunks, { type: blobType });
            const extension = blobType.includes('mp4') ? 'm4a' : 'webm';
            const file = new File([blob], `voice_${Date.now()}.${extension}`, { type: blobType });

            try {
              $('sendMsgBtn').disabled = true;
              const uploadResult = await uploadMediaFile(file);
              if (!uploadResult || !uploadResult.success) throw new Error((uploadResult && uploadResult.message) ? uploadResult.message : 'Upload failed');
              await post('profile_send_message', {
                thread_type: S.activeType,
                thread_id: S.activeId,
                message_type: 'audio',
                body: '',
                media_url: uploadResult.media_url,
                metadata: {
                  duration,
                  original_name: uploadResult.original_name || file.name,
                  mime_type: uploadResult.mime_type || blobType,
                  size: Number(uploadResult.size || file.size || 0),
                }
              });
              await loadMessages();
              await loadData(true);
            } catch(err){
              toast(err.message || 'Could not upload audio','error');
            } finally {
              $('sendMsgBtn').disabled = false;
              resetRecorderState();
            }
          };

          mediaRecorder.start();
          _recordBtn?.classList?.add('is-recording');
        } catch(e){
          resetRecorderState();
          toast(e.message || 'Microphone access was denied.','error');
        }
      }
      function _stopRecording(){
        if (!_recState.mediaRecorder || _recState.mediaRecorder.state === 'inactive' || _recState.stopping) return;
        _recState.stopping = true;
        _recordBtn?.classList?.remove('is-recording');
        try { _recState.mediaRecorder.stop(); } catch(e){ resetRecorderState(); }
      }
      _recordBtn?.addEventListener('click', ()=>{
        if (_recState.mediaRecorder && _recState.mediaRecorder.state !== 'inactive') {
          _stopRecording();
          return;
        }
        _startRecording();
      });

      // Welcome → people
      $('msgFindPeopleBtn')?.addEventListener('click',()=>document.querySelector('.msg-tab[data-tab="people"]')?.click());

      // Right panel
      const layoutEl=document.querySelector('.messages-page-layout');
      $('toggleRightPanelBtn')?.addEventListener('click',()=>layoutEl?.classList.toggle('right-panel-closed'));
      $('closeRightPanelBtn')?.addEventListener('click',()=>layoutEl?.classList.add('right-panel-closed'));

      // Calls
      $('startAudioCallBtn')?.addEventListener('click',()=>openCall('audio'));
      $('startVideoCallBtn')?.addEventListener('click',()=>openCall('video'));
      $('callEndBtn')?.addEventListener('click',()=>closeCall(true));
      $('callOverlay')?.addEventListener('click',e=>{if(e.target===$('callOverlay'))closeCall(true);});
      $('scheduleMeetingBtn')?.addEventListener('click',()=>{
        if (S.activeType === 'group' && S.activeId) {
          window.open(`meeting-room.php?room=${encodeURIComponent(`GROUP-${Number(S.activeId)}`)}`, '_blank','noopener,noreferrer');
          return;
        }
        openCall('video');
      });

      $('callMicBtn')?.addEventListener('click',()=>{
        if(!S.callStream)return;
        const t=S.callStream.getAudioTracks()[0];if(!t)return;
        S.callMicOn=!S.callMicOn;t.enabled=S.callMicOn;updateCallBtns();
      });
      $('callCameraBtn')?.addEventListener('click',()=>{
        if(!S.callStream)return;
        const t=S.callStream.getVideoTracks()[0];if(!t)return;
        S.callCameraOn=!S.callCameraOn;t.enabled=S.callCameraOn;
        const lf=$('callLocalFallback');if(lf)lf.style.display=S.callCameraOn?'none':'flex';
        updateCallBtns();
      });
      $('callScreenBtn')?.addEventListener('click',async()=>{
        if(!navigator.mediaDevices?.getDisplayMedia){toast('Screen share not supported.','warning');return;}
        try {
          S.callScreenStream=await navigator.mediaDevices.getDisplayMedia({video:true});
          const lv=$('callLocalVideo');if(lv)lv.srcObject=S.callScreenStream;
          const screenTrack = S.callScreenStream.getVideoTracks()[0];
          const sender = S.callPc?.getSenders()?.find((s) => s.track && s.track.kind === 'video');
          if (sender && screenTrack) {
            await sender.replaceTrack(screenTrack);
          }
          screenTrack.onended = async () => {
            try {
              const cameraTrack = S.callStream?.getVideoTracks?.()[0] || null;
              if (sender && cameraTrack) {
                await sender.replaceTrack(cameraTrack);
              }
            } catch (e) {}
            if(lv && S.callStream) lv.srcObject=S.callStream;
            S.callScreenStream=null;
          };
        } catch(e){toast(e.message||'Screen share denied.','error');}
      });
      $('shareScreenBtn')?.addEventListener('click',()=>openCall('video'));
      $('copyInviteBtn')?.addEventListener('click',async()=>{
        const url=`${location.href.split('?')[0]}?thread_type=${S.activeType}&thread_id=${S.activeId}`;
        try{await navigator.clipboard.writeText(url);toast('Invite link copied!');}catch{toast('Copy failed.','error');}
      });
      $('openMeetingRoomBtn')?.addEventListener('click',()=>{
        const roomSeed=[S.activeType||'chat',String(S.activeId||0),Math.random().toString(36).slice(2,8)].join('-');
        window.open(`meeting-room.php?room=${encodeURIComponent(roomSeed.toUpperCase())}`, '_blank','noopener,noreferrer');
      });
      $('callCodeBadge')?.addEventListener('click',async()=>{
        const code=$('callRoomCode')?.textContent;
        if(!code)return;
        try{await navigator.clipboard.writeText(code);toast('Room code copied!');}catch{}
      });

      // Create group
      const fileToDataUrl = (file) => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ''));
        reader.onerror = () => reject(new Error('Could not read file.'));
        reader.readAsDataURL(file);
      });

      $('groupAvatarPickerBtn')?.addEventListener('click', () => $('groupAvatarFileInput')?.click());
      $('groupAvatarFileInput')?.addEventListener('change', async function () {
        const file = this.files && this.files[0];
        if (!file) return;
        if (!String(file.type || '').startsWith('image/')) {
          toast('Please choose an image file.', 'warning');
          this.value = '';
          return;
        }
        try {
          const dataUrl = await fileToDataUrl(file);
          S.pendingGroupAvatarData = dataUrl;
          const previewImg = $('groupAvatarPreviewImg');
          const previewBtn = $('groupAvatarPickerBtn');
          if (previewImg) previewImg.src = dataUrl;
          previewBtn?.classList.add('has-image');
        } catch (e) {
          toast(e.message || 'Could not read image.', 'error');
        } finally {
          this.value = '';
        }
      });

      $('createGroupBtn')?.addEventListener('click',()=>{
        $('groupNameInput').value=''; $('groupDescInput').value='';
        S.pendingGroupAvatarData='';
        const previewBtn = $('groupAvatarPickerBtn');
        const previewImg = $('groupAvatarPreviewImg');
        if (previewImg) previewImg.src='';
        previewBtn?.classList.remove('has-image');
        $('groupMembersList').innerHTML=S.friends.length
          ?S.friends.map(f=>`<label class="create-group-member-row"><input type="checkbox" class="group-member-cb" value="${f.id}"><div class="create-group-member-avatar" style="${avatarBgStyle(avatarFor(f))}">${initials(f.first_name,f.last_name)}</div> ${esc(displayName(f))}</label>`).join('')
          :'<p style="font-size:13px;color:var(--text-muted);">No friends yet.</p>';
        $('createGroupModal').hidden=false;
      });
      $('cancelGroupBtn')?.addEventListener('click',()=>{$('createGroupModal').hidden=true;});
      $('confirmGroupBtn')?.addEventListener('click',async()=>{
        const name=$('groupNameInput')?.value.trim();
        if(!name){toast('Group name is required.','warning');return;}
        const members=[...$('groupMembersList').querySelectorAll('.group-member-cb:checked')].map(c=>Number(c.value));
        try {
          const d=await post('profile_create_group_chat',{
            name,
            description:$('groupDescInput')?.value.trim()||'',
            members,
            avatar_data: S.pendingGroupAvatarData || ''
          });
          toast('Group created!'); $('createGroupModal').hidden=true;
          await loadData(true); if(d.group_chat_id)openThread('group',d.group_chat_id);
        } catch(e){toast(e.message||'Could not create group.','error');}
      });

      // Group management
      $('groupLeaveBtn')?.addEventListener('click', async () => {
        if (S.activeType !== 'group' || !S.activeId) return;
        const shouldLeave = await confirmDialog({
          title: 'Leave group?',
          text: 'You will stop receiving new messages from this conversation.',
          confirmText: 'Leave',
          cancelText: 'Stay',
          icon: 'warning'
        });
        if (!shouldLeave) return;
        try {
          await post('profile_group_manage', { mode: 'leave', group_chat_id: S.activeId });
          S.activeType = null; S.activeId = 0;
          showView('welcome');
          await loadData(true);
          toast('You left the group.');
        } catch (e) {
          toast(e.message || 'Could not leave group.', 'error');
        }
      });

      $('groupDeleteBtn')?.addEventListener('click', async () => {
        if (S.activeType !== 'group' || !S.activeId) return;
        const shouldDelete = await confirmDialog({
          title: 'Delete group?',
          text: 'This will permanently remove the group for every member.',
          confirmText: 'Delete',
          cancelText: 'Cancel',
          icon: 'warning'
        });
        if (!shouldDelete) return;
        try {
          await post('profile_group_manage', { mode: 'delete', group_chat_id: S.activeId });
          S.activeType = null; S.activeId = 0;
          showView('welcome');
          await loadData(true);
          toast('Group deleted.');
        } catch (e) {
          toast(e.message || 'Could not delete group.', 'error');
        }
      });

      $('groupReportBtn')?.addEventListener('click', async () => {
        if (S.activeType !== 'group' || !S.activeId) return;
        const reason = await promptTextDialog({
          title: 'Report this group',
          label: 'Reason',
          value: 'Spam or abusive behavior',
          placeholder: 'Tell us what happened'
        });
        if (!reason) return;
        const details = await promptTextDialog({
          title: 'Add more details',
          label: 'Details',
          value: '',
          placeholder: 'Optional context for the moderation team'
        });
        try {
          await post('profile_group_manage', {
            mode: 'report',
            group_chat_id: S.activeId,
            reason,
            details: details || ''
          });
          toast('Report submitted.');
        } catch (e) {
          toast(e.message || 'Could not submit report.', 'error');
        }
      });

      // Mobile back
      $('msgMobileBack')?.addEventListener('click',()=>$('msgSidebar').classList.toggle('is-open'));

      // Escape key
      document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (peopleProfileModal && !peopleProfileModal.hidden) {
          closePeopleProfile();
          return;
        }
        if (S.callOpen) {
          closeCall(true);
        }
      });

      // Load
      await loadData();
      updateGroupManageActions('', null);

      // URL params
      const params=new URLSearchParams(location.search);
      const tt=params.get('thread_type'),tid=Number(params.get('thread_id'));
      if(tt&&tid)setTimeout(()=>openThread(tt,tid),400);

      // Poll
      setInterval(()=>{if(S.activeType&&S.activeId)loadMessages();},8000);
      setInterval(()=>{loadData(true);},12000);
      S.callPollTimer = setInterval(() => { pollCalls(); }, 2500);
      pollCalls();
    });
  </script>

  <script>
    window.currentUserName  = window.msgBootstrap?.currentUserName ?? 'User';
    window.currentUserId    = Number(window.msgBootstrap?.currentUserId ?? 0);
  </script>
  <script src="../../assets/js/stories_messages_p2a.js"></script>
  <script src="../../assets/js/main.js"></script>
  <script>window.addEventListener('DOMContentLoaded',()=>{if(window.lucide&&typeof window.lucide.createIcons==='function')window.lucide.createIcons();});</script>
</body>
</html>
