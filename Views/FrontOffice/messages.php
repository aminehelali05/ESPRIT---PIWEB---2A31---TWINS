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
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    /* Small utility helpers used by migrated story scripts */
    .hidden { display: none !important; }
    .flex { display: flex !important; }
    .modal { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.6); }
    .modal .card { background: #fff; border-radius: 12px; padding: 18px; max-width: 96vw; max-height: 90vh; overflow: auto; }
    /* ─── Base ─────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; }
    body, body * { font-family: 'Poppins', sans-serif; }

    /* ─── Messages layout ───────────────────────────── */
    .messages-page-layout {
      display: grid;
      grid-template-columns: 260px 1fr;
      gap: 0;
      height: calc(100vh - 70px);
      max-height: calc(100vh - 70px);
      overflow: hidden;
    }

    /* ─── Thread sidebar ────────────────────────────── */
    .msg-sidebar {
      display: flex;
      flex-direction: column;
      background: var(--color-surface, #fff);
      border-right: 1px solid var(--color-border, rgba(0,0,0,0.07));
      height: 100%;
      overflow: hidden;
    }

    .msg-sidebar-head {
      padding: 20px 18px 14px;
      border-bottom: 1px solid var(--color-border, rgba(0,0,0,0.06));
      flex-shrink: 0;
    }

    .msg-sidebar-title {
      font-size: 16px;
      font-weight: 700;
      color: var(--color-text-heading, #111827);
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .msg-new-btn {
      width: 28px; height: 28px;
      border-radius: 8px;
      background: rgba(99,102,241,0.1);
      border: 1px solid rgba(99,102,241,0.18);
      color: #6366f1;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: all .15s;
    }
    .msg-new-btn:hover { background: rgba(99,102,241,0.2); }

    .msg-search-wrap { position: relative; }
    .msg-search-wrap input {
      width: 100%;
      padding: 8px 12px 8px 34px;
      border-radius: 10px;
      border: 1.5px solid var(--color-border, rgba(0,0,0,0.08));
      background: var(--color-surface-alt, rgba(0,0,0,0.025));
      font-family: 'Poppins', sans-serif;
      font-size: 12px;
      color: var(--color-text, #374151);
      outline: none;
      transition: border-color .2s;
    }
    .msg-search-wrap input:focus { border-color: rgba(99,102,241,0.5); }
    .msg-search-wrap svg {
      position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
      width: 14px; height: 14px; color: #9ca3af; pointer-events: none;
    }

    /* tabs */
    .msg-tabs {
      display: flex;
      gap: 0;
      padding: 10px 18px 0;
      border-bottom: 1px solid var(--color-border, rgba(0,0,0,0.06));
      flex-shrink: 0;
    }
    .msg-tab {
      flex: 1;
      padding: 8px 4px 10px;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .02em;
      color: var(--color-text-muted, #9ca3af);
      border-bottom: 2px solid transparent;
      cursor: pointer;
      background: none;
      border-top: none; border-left: none; border-right: none;
      transition: all .15s;
      text-align: center;
    }
    .msg-tab.is-active { color: #6366f1; border-bottom-color: #6366f1; }
    .msg-tab:hover:not(.is-active) { color: var(--color-text, #374151); }

    /* thread list */
    .msg-thread-list {
      flex: 1;
      overflow-y: auto;
      padding: 8px 8px;
    }
    .msg-thread-list::-webkit-scrollbar { width: 4px; }
    .msg-thread-list::-webkit-scrollbar-thumb { background: rgba(0,0,0,.1); border-radius: 2px; }

    .msg-thread-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 10px;
      border-radius: 12px;
      cursor: pointer;
      transition: background .15s;
      border: 1px solid transparent;
      width: 100%;
      text-align: left;
      background: none;
    }
    .msg-thread-item:hover { background: var(--color-surface-alt, rgba(0,0,0,0.03)); }
    .msg-thread-item:hover { box-shadow: 0 6px 18px rgba(15,23,42,0.06); }
    .msg-thread-item.is-active {
      background: rgba(99,102,241,0.08);
      border-color: rgba(99,102,241,0.15);
    }

    .msg-thread-avatar {
      width: 40px; height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      position: relative;
    }
    .msg-thread-avatar.is-group {
      border-radius: 12px;
      background: linear-gradient(135deg, #10b981, #059669);
    }
    .msg-thread-avatar .online-dot {
      position: absolute; bottom: 1px; right: 1px;
      width: 10px; height: 10px; border-radius: 50%;
      background: #10b981;
      border: 2px solid var(--color-surface, #fff);
    }

    .msg-thread-body { flex: 1; min-width: 0; }
    .msg-thread-name {
      font-size: 12px; font-weight: 600;
      color: var(--color-text-heading, #111827);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      margin-bottom: 2px;
    }
    .msg-thread-preview {
      font-size: 11px;
      color: var(--color-text-muted, #9ca3af);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .msg-thread-meta { flex-shrink: 0; text-align: right; }
    .msg-thread-time { font-size: 10px; color: var(--color-text-muted, #9ca3af); margin-bottom: 4px; }
    .msg-thread-badge {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 18px; height: 18px;
      border-radius: 9px;
      background: #6366f1;
      color: #fff;
      font-size: 9px; font-weight: 700;
      padding: 0 5px;
    }

    .msg-thread-empty {
      padding: 24px 12px;
      text-align: center;
      font-size: 12px;
      color: var(--color-text-muted, #9ca3af);
    }

    /* ─── Main chat area ────────────────────────────── */
    .msg-main {
      display: flex;
      flex-direction: column;
      height: 100%;
      overflow: hidden;
      background: var(--color-surface, #fafafa);
    }

    /* welcome state */
    .msg-welcome {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 14px;
      text-align: center;
      padding: 40px;
    }
    .msg-welcome-icon {
      width: 72px; height: 72px;
      border-radius: 22px;
      background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.08));
      border: 1px solid rgba(99,102,241,0.15);
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 4px;
    }
    .msg-welcome-icon svg { width: 32px; height: 32px; color: #6366f1; opacity: .8; }
    .msg-welcome h2 { font-size: 18px; font-weight: 700; color: var(--color-text-heading, #111827); margin: 0; }
    .msg-welcome p { font-size: 13px; color: var(--color-text-muted, #9ca3af); margin: 0; max-width: 28ch; line-height: 1.6; }
    .msg-welcome-btn {
      margin-top: 4px;
      padding: 9px 22px;
      border-radius: 10px;
      background: #6366f1;
      color: #fff;
      font-family: 'Poppins', sans-serif;
      font-size: 12px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: all .2s;
    }
    .msg-welcome-btn:hover { background: #4f46e5; transform: translateY(-1px); }

    /* chat header */
    .msg-chat-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 20px;
      border-bottom: 1px solid var(--color-border, rgba(0,0,0,0.07));
      background: var(--color-surface, #fff);
      flex-shrink: 0;
    }
    .msg-chat-header-left { display: flex; align-items: center; gap: 12px; }
    .msg-chat-header-avatar {
      width: 40px; height: 40px; border-radius: 50%;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: #fff; font-size: 14px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
    }
    .msg-chat-header-name { font-size: 14px; font-weight: 700; color: var(--color-text-heading, #111827); }
    .msg-chat-header-sub { font-size: 11px; color: var(--color-text-muted, #9ca3af); margin-top: 1px; }

    .msg-chat-actions { display: flex; gap: 6px; }
    .msg-action-btn {
      width: 34px; height: 34px;
      border-radius: 10px;
      border: 1.5px solid var(--color-border, rgba(0,0,0,0.08));
      background: var(--color-surface-alt, rgba(0,0,0,0.025));
      color: var(--color-text-secondary, #6b7280);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: all .15s;
    }
    .msg-action-btn:hover { background: rgba(99,102,241,0.08); color: #6366f1; border-color: rgba(99,102,241,0.2); }
    .msg-action-btn.is-primary { background: #6366f1; color: #fff; border-color: #6366f1; }
    .msg-action-btn.is-danger { background: rgba(239,68,68,0.08); color: #ef4444; border-color: rgba(239,68,68,0.2); }

    .msg-meeting-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 38px;
      padding: 0 14px 0 12px;
      border-radius: 12px;
      border: 1px solid rgba(99,102,241,0.22);
      background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(139,92,246,0.08));
      color: #4f46e5;
      font-family: 'Poppins', sans-serif;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: transform .18s ease, box-shadow .18s ease, background .18s ease, border-color .18s ease;
      white-space: nowrap;
    }
    .msg-meeting-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 24px rgba(99,102,241,0.14);
      border-color: rgba(99,102,241,0.32);
      background: linear-gradient(135deg, rgba(99,102,241,0.18), rgba(139,92,246,0.12));
    }
    .msg-meeting-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

    /* messages body */
    .msg-body {
      flex: 1;
      overflow-y: auto;
      padding: 20px 24px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .msg-body::-webkit-scrollbar { width: 5px; }
    .msg-body::-webkit-scrollbar-thumb { background: rgba(0,0,0,.1); border-radius: 3px; }

    .msg-bubble-row {
      display: flex;
      align-items: flex-end;
      gap: 8px;
    }
    .msg-bubble-row.is-outgoing { flex-direction: row-reverse; }

    .msg-bubble-avatar {
      width: 28px; height: 28px; border-radius: 50%;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: #fff; font-size: 10px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .msg-bubble {
      max-width: 68%;
      padding: 10px 14px;
      border-radius: 16px;
      font-size: 13px;
      line-height: 1.55;
    }
    .msg-bubble { transition: transform .12s ease, box-shadow .12s ease; }
    .msg-bubble:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(15,23,42,0.06); }
    .msg-bubble-row.is-incoming .msg-bubble {
      background: var(--color-surface, #fff);
      color: var(--color-text, #374151);
      border: 1px solid var(--color-border, rgba(0,0,0,0.07));
      border-bottom-left-radius: 4px;
    }
    .msg-bubble-row.is-outgoing .msg-bubble {
      background: linear-gradient(135deg, #6366f1, #7c3aed);
      color: #fff;
      border-bottom-right-radius: 4px;
    }
    .msg-bubble-meta {
      font-size: 10px;
      opacity: .55;
      margin-top: 4px;
    }
    .msg-bubble-row.is-outgoing .msg-bubble-meta { text-align: right; }

    .msg-date-divider {
      display: flex; align-items: center; gap: 10px;
      font-size: 10px; font-weight: 600;
      text-transform: uppercase; letter-spacing: .08em;
      color: var(--color-text-muted, #9ca3af);
    }
    .msg-date-divider::before, .msg-date-divider::after {
      content: ''; flex: 1;
      height: 1px; background: var(--color-border, rgba(0,0,0,.06));
    }

    /* composer */
    .msg-composer {
      padding: 12px 20px;
      border-top: 1px solid var(--color-border, rgba(0,0,0,0.07));
      background: var(--color-surface, #fff);
      flex-shrink: 0;
    }
    .msg-composer-wrap {
      display: flex;
      align-items: flex-end;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 14px;
      border: 1.5px solid var(--color-border, rgba(0,0,0,0.09));
      background: var(--color-surface-alt, rgba(0,0,0,0.025));
      transition: border-color .2s, box-shadow .2s;
    }
    .msg-composer-wrap:focus-within {
      border-color: rgba(99,102,241,0.55);
      box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
    }
    .msg-composer-actions { display: flex; gap: 2px; }
    .msg-composer-icon-btn {
      width: 30px; height: 30px; border-radius: 8px;
      border: none; background: none;
      color: var(--color-text-muted, #9ca3af);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: all .15s;
    }
    .msg-composer-icon-btn:hover { background: rgba(99,102,241,0.08); color: #6366f1; }
    .msg-composer-input {
      flex: 1;
      border: none; background: none; outline: none;
      font-family: 'Poppins', sans-serif;
      font-size: 13px;
      color: var(--color-text, #374151);
      resize: none;
      max-height: 100px;
      line-height: 1.5;
      padding: 4px 0;
    }
    .msg-composer-input::placeholder { color: var(--color-text-muted, #9ca3af); }
    .msg-send-btn {
      width: 34px; height: 34px; border-radius: 10px;
      background: #6366f1; color: #fff; border: none;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: all .2s; flex-shrink: 0;
    }
    .msg-send-btn:hover { background: #4f46e5; transform: scale(1.05); }
    .msg-send-btn:disabled { opacity: .4; transform: none; cursor: not-allowed; }

    /* ─── Call overlay ──────────────────────────────── */
    .call-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.75);
      backdrop-filter: blur(8px);
      z-index: 9000;
      display: flex; align-items: center; justify-content: center;
    }
    .call-overlay[hidden] { display: none !important; }

    .call-card {
      width: 860px; max-width: calc(100vw - 32px);
      border-radius: 24px;
      background: #0f0f1a;
      overflow: hidden;
      display: flex; flex-direction: column;
      box-shadow: 0 40px 80px rgba(0,0,0,0.6);
    }

    .call-videos {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 4px;
      background: #000;
      height: 380px;
      padding: 4px;
    }
    .call-video-slot {
      border-radius: 14px;
      overflow: hidden;
      background: #1a1a2e;
      display: flex; align-items: center; justify-content: center;
      position: relative;
    }
    .call-video-slot video { width: 100%; height: 100%; object-fit: cover; }
    .call-video-fallback {
      display: flex; flex-direction: column; align-items: center; gap: 10px;
      color: rgba(255,255,255,0.4);
    }
    .call-video-fallback svg { width: 36px; height: 36px; }
    .call-video-fallback span { font-size: 12px; font-weight: 500; }
    .call-video-label {
      position: absolute; bottom: 10px; left: 12px;
      font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.7);
      background: rgba(0,0,0,0.4);
      padding: 3px 8px; border-radius: 6px;
    }

    .call-footer {
      padding: 16px 20px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .call-info .call-title { font-size: 14px; font-weight: 700; color: #f8fafc; }
    .call-info .call-sub { font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 2px; }
    .call-code-badge {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 10px; border-radius: 8px;
      background: rgba(99,102,241,0.2); border: 1px solid rgba(99,102,241,0.3);
      font-size: 11px; font-weight: 700; color: #a5b4fc;
      letter-spacing: .08em; margin-top: 6px; cursor: pointer;
    }

    .call-controls { display: flex; gap: 8px; }
    .call-ctrl-btn {
      padding: 9px 16px;
      border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 11px; font-weight: 600;
      border: 1.5px solid rgba(255,255,255,0.12);
      background: rgba(255,255,255,0.07);
      color: rgba(255,255,255,0.8);
      display: flex; align-items: center; gap: 6px;
      cursor: pointer; transition: all .15s;
    }
    .call-ctrl-btn:hover { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.22); }
    .call-ctrl-btn.is-primary { background: #6366f1; border-color: #6366f1; color: #fff; }
    .call-ctrl-btn.is-danger { background: #ef4444; border-color: #ef4444; color: #fff; }
    .call-ctrl-btn.is-active { background: rgba(16,185,129,0.2); border-color: #10b981; color: #10b981; }
    .call-ctrl-btn svg { width: 14px; height: 14px; }

    /* ─── People panel (Add friend / directory) ─────── */
    .msg-people-panel {
      padding: 20px 24px;
      display: flex; flex-direction: column; gap: 16px;
    }
    .msg-people-header {
      font-size: 12px; font-weight: 700;
      letter-spacing: .08em; text-transform: uppercase;
      color: var(--color-text-muted, #9ca3af);
      margin-bottom: 2px;
    }
    .msg-people-search {
      position: relative;
    }
    .msg-people-search input {
      width: 100%;
      padding: 10px 12px 10px 36px;
      border-radius: 12px;
      border: 1.5px solid var(--color-border, rgba(0,0,0,0.08));
      background: var(--color-surface-alt, rgba(0,0,0,0.025));
      font-family: 'Poppins', sans-serif; font-size: 12px;
      color: var(--color-text, #374151); outline: none;
      transition: border-color .2s;
    }
    .msg-people-search input:focus { border-color: rgba(99,102,241,0.5); }
    .msg-people-search svg {
      position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
      width: 14px; height: 14px; color: #9ca3af; pointer-events: none;
    }

    .people-card {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 14px;
      border-radius: 14px;
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, rgba(0,0,0,0.06));
      transition: all .15s;
    }
    .people-card:hover { background: rgba(99,102,241,0.04); border-color: rgba(99,102,241,0.12); }
    .people-card-avatar {
      width: 42px; height: 42px; border-radius: 50%;
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: #fff; font-size: 14px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .people-card-info { flex: 1; min-width: 0; }
    .people-card-name { font-size: 13px; font-weight: 600; color: var(--color-text-heading, #111827); }
    .people-card-sub { font-size: 11px; color: var(--color-text-muted, #9ca3af); margin-top: 1px; }
    .people-connect-btn {
      padding: 6px 14px;
      border-radius: 8px;
      background: rgba(99,102,241,0.1);
      border: 1px solid rgba(99,102,241,0.2);
      color: #6366f1;
      font-family: 'Poppins', sans-serif;
      font-size: 11px; font-weight: 600;
      cursor: pointer; transition: all .15s; white-space: nowrap;
    }
    .people-connect-btn:hover { background: rgba(99,102,241,0.18); }
    .people-connect-btn.is-sent { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.2); color: #10b981; cursor: default; }

    /* friend request cards */
    .friend-req-card {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 14px; border-radius: 14px;
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, rgba(0,0,0,0.06));
      margin-bottom: 8px;
    }
    .friend-req-info { flex: 1; min-width: 0; }
    .friend-req-name { font-size: 13px; font-weight: 600; color: var(--color-text-heading, #111827); }
    .friend-req-msg { font-size: 11px; color: var(--color-text-muted, #9ca3af); margin-top: 2px; }
    .friend-req-btns { display: flex; gap: 6px; flex-shrink: 0; }
    .friend-req-btn {
      padding: 5px 12px; border-radius: 8px;
      font-family: 'Poppins', sans-serif;
      font-size: 11px; font-weight: 600;
      cursor: pointer; transition: all .15s;
      border: 1px solid;
    }
    .friend-req-btn.accept { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.25); color: #10b981; }
    .friend-req-btn.accept:hover { background: rgba(16,185,129,0.18); }
    .friend-req-btn.decline { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.2); color: #ef4444; }
    .friend-req-btn.decline:hover { background: rgba(239,68,68,0.15); }

    /* scrollable people area */
    .msg-people-scroll {
      flex: 1; overflow-y: auto;
      padding: 0 24px 20px;
    }
    .msg-people-scroll::-webkit-scrollbar { width: 4px; }
    .msg-people-scroll::-webkit-scrollbar-thumb { background: rgba(0,0,0,.1); border-radius: 2px; }

    /* emoji picker */
    .emoji-dropdown {
      position: absolute; bottom: calc(100% + 8px); left: 0;
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, rgba(0,0,0,0.1));
      border-radius: 14px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.15);
      padding: 12px;
      width: 260px;
      display: grid; grid-template-columns: repeat(8, 1fr);
      gap: 4px;
      z-index: 100;
    }
    .emoji-dropdown.is-hidden { display: none; }
    .emoji-btn {
      font-size: 18px;
      padding: 4px; border-radius: 6px;
      cursor: pointer; text-align: center;
      transition: background .1s; background: none; border: none;
    }
    .emoji-btn:hover { background: var(--color-surface-alt, rgba(0,0,0,0.05)); }
    .composer-emoji-wrap { position: relative; }

    /* group composer modal */
    .create-group-modal {
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.5); backdrop-filter: blur(6px);
      z-index: 8000;
      display: flex; align-items: center; justify-content: center;
    }
    .create-group-modal[hidden] { display: none !important; }
    .create-group-card {
      width: 460px; max-width: calc(100vw - 32px);
      background: var(--color-surface, #fff);
      border-radius: 20px;
      padding: 28px;
      box-shadow: 0 24px 60px rgba(0,0,0,0.2);
    }
    .create-group-card h3 { font-size: 16px; font-weight: 700; color: var(--color-text-heading, #111827); margin: 0 0 16px; }
    .create-group-input {
      width: 100%;
      padding: 10px 14px;
      border-radius: 10px;
      border: 1.5px solid var(--color-border, rgba(0,0,0,0.1));
      background: var(--color-surface-alt, rgba(0,0,0,0.025));
      font-family: 'Poppins', sans-serif; font-size: 13px;
      color: var(--color-text, #374151); outline: none;
      margin-bottom: 10px; transition: border-color .2s;
    }
    .create-group-input:focus { border-color: rgba(99,102,241,0.5); }
    .create-group-members { max-height: 200px; overflow-y: auto; margin-bottom: 14px; }
    .create-group-member-row {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 0; cursor: pointer;
      font-size: 13px; color: var(--color-text, #374151);
    }
    .create-group-member-row input[type="checkbox"] { accent-color: #6366f1; width: 15px; height: 15px; }
    .create-group-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 6px; }
    .cg-btn {
      padding: 9px 20px; border-radius: 10px;
      font-family: 'Poppins', sans-serif; font-size: 12px; font-weight: 600;
      cursor: pointer; transition: all .15s; border: 1.5px solid;
    }
    .cg-btn.cancel { background: none; border-color: var(--color-border, rgba(0,0,0,0.1)); color: var(--color-text-muted, #9ca3af); }
    .cg-btn.create { background: #6366f1; border-color: #6366f1; color: #fff; }
    .cg-btn.create:hover { background: #4f46e5; }

    /* ─── Dark mode ─────────────────────────────────── */
    [data-theme="dark"] .msg-sidebar { background: var(--color-surface-dark, #111827); border-color: rgba(255,255,255,0.07); }
    [data-theme="dark"] .msg-thread-item:hover { background: rgba(255,255,255,0.04); }
    [data-theme="dark"] .msg-thread-item.is-active { background: rgba(99,102,241,0.12); }
    [data-theme="dark"] .msg-bubble-row.is-incoming .msg-bubble { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.08); color: #e2e8f0; }
    [data-theme="dark"] .msg-chat-header { background: #111827; border-color: rgba(255,255,255,0.07); }
    [data-theme="dark"] .msg-composer { background: #111827; border-color: rgba(255,255,255,0.07); }
    [data-theme="dark"] .msg-composer-wrap { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.08); }
    [data-theme="dark"] .msg-main { background: #0d0d1a; }
    [data-theme="dark"] .people-card { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.07); }
    [data-theme="dark"] .friend-req-card { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.07); }
    [data-theme="dark"] .create-group-card { background: #1e293b; }
    [data-theme="dark"] .emoji-dropdown { background: #1e293b; border-color: rgba(255,255,255,0.1); }
    [data-theme="dark"] .msg-search-wrap input { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.08); color: #e2e8f0; }
    [data-theme="dark"] .msg-people-search input { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.08); color: #e2e8f0; }

    /* ─── Responsive ────────────────────────────────── */
    @media (max-width: 900px) {
      .profile-page-layout { grid-template-columns: 1fr !important; }
      .home-left { display: none; }
      .messages-page-layout { grid-template-columns: 1fr; }
      .msg-sidebar { position: fixed; left: 0; top: 70px; bottom: 0; z-index: 300; width: 280px; transform: translateX(-100%); transition: transform .25s; }
      .msg-sidebar.is-open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.15); }
      .msg-mobile-toggle { display: flex; }
    }
    .msg-mobile-toggle {
      display: none; align-items: center; justify-content: center;
      width: 34px; height: 34px; border-radius: 10px;
      background: rgba(99,102,241,0.1); border: 1px solid rgba(99,102,241,0.2);
      color: #6366f1; cursor: pointer;
    }

    .section-hidden { display: none !important; }
  </style>
</head>
<body class="grid-dot-bg home-page-body">

  <!-- ── Navbar ──────────────────────────────────────── -->
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

  <!-- ── Main layout ───────────────────────────────── -->
  <main style="padding-top: 70px; height: 100vh; overflow: hidden;">
    <div class="container profile-page-layout" style="gap: 0; height: 100%; max-height: calc(100vh - 70px);">

      <!-- Left sidebar (same as profile/home) -->
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
        <div class="messages-page-layout glass-card" style="border-radius: 16px; overflow: hidden; margin: 0; height: 100%;">

          <!-- ── Thread sidebar ─────────────────────── -->
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
              <button class="msg-tab" data-tab="requests">Requests <span id="reqBadge" class="msg-thread-badge" style="display:none;margin-left:4px;"></span></button>
              <button class="msg-tab" data-tab="people">People</button>
            </div>

            <!-- Chats list -->
            <div class="msg-thread-list" id="threadListChats">
              <div class="msg-thread-empty">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 8px;display:block;opacity:.4;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Loading conversations…
              </div>
            </div>

            <!-- Requests list -->
            <div class="msg-thread-list section-hidden" id="threadListRequests">
              <div class="msg-thread-empty">No pending requests.</div>
            </div>

            <!-- People list -->
            <div class="msg-thread-list section-hidden" id="threadListPeople">
              <div class="msg-thread-empty">Loading…</div>
            </div>
          </div>

          <!-- ── Chat main ──────────────────────────── -->
          <div class="msg-main" id="msgMain">

            <!-- Welcome state -->
            <div class="msg-welcome" id="msgWelcome">
              <div class="msg-welcome-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <h2>Your conversations</h2>
              <p>Select a thread on the left, or find new people to connect with.</p>
              <button class="msg-welcome-btn" id="msgFindPeopleBtn">Find people</button>
            </div>

            <!-- Chat view (hidden until thread selected) -->
            <div id="msgChatView" class="section-hidden" style="display:flex;flex-direction:column;height:100%;overflow:hidden;">

              <!-- Chat header -->
              <div class="msg-chat-header">
                <div class="msg-chat-header-left">
                  <button class="msg-mobile-toggle" id="msgMobileBack">
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
                </div>
              </div>

              <!-- Messages body -->
              <div class="msg-body" id="msgBody"></div>

              <!-- Composer -->
              <div class="msg-composer">
                <div class="msg-composer-wrap">
                  <div class="msg-composer-actions">
                    <div class="composer-emoji-wrap">
                      <button class="msg-composer-icon-btn" id="emojiToggleBtn" title="Emoji">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                      </button>
                      <div class="emoji-dropdown is-hidden" id="emojiDropdown"></div>
                    </div>
                    <button class="msg-composer-icon-btn" id="attachImageBtn" title="Send image URL">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </button>
                  </div>
                  <textarea class="msg-composer-input" id="composerInput" rows="1" placeholder="Type a message…"></textarea>
                  <button class="msg-send-btn" id="sendMsgBtn" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- People / find friends panel -->
            <div id="msgPeopleView" class="section-hidden" style="display:flex;flex-direction:column;height:100%;overflow:hidden;">
              <div class="msg-chat-header">
                <div class="msg-chat-header-left">
                  <div>
                    <div class="msg-chat-header-name">Find People</div>
                    <div class="msg-chat-header-sub">Connect &amp; start chatting</div>
                  </div>
                </div>
              </div>
              <div class="msg-people-panel" style="padding: 20px 24px 12px;">
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

        </div><!-- /messages-page-layout -->
      </section>
    </div>
  </main>

  <!-- ── Call overlay ──────────────────────────────── -->
  <div class="call-overlay" id="callOverlay" hidden>
    <div class="call-card">
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

  <!-- ── Create Group modal ──────────────────────── -->
  <div class="create-group-modal" id="createGroupModal" hidden>
    <div class="create-group-card">
      <h3>Create Group Chat</h3>
      <input class="create-group-input" id="groupNameInput" type="text" placeholder="Group name…" maxlength="120">
      <input class="create-group-input" id="groupDescInput" type="text" placeholder="Short description (optional)" maxlength="255">
      <p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-bottom:8px;">Select friends</p>
      <div class="create-group-members" id="groupMembersList">
        <p style="font-size:12px;color:#9ca3af;">Loading friends…</p>
      </div>
      <div class="create-group-actions">
        <button class="cg-btn cancel" id="cancelGroupBtn">Cancel</button>
        <button class="cg-btn create" id="confirmGroupBtn">Create Group</button>
      </div>
    </div>
  </div>

  <script>
    /* ═══════════════════════════════════════════════
       Bootstrap data from PHP
    ═══════════════════════════════════════════════ */
    window.msgBootstrap = {
      currentUserId:    <?= (int)($sessionUser['id'] ?? 0) ?>,
      currentUserName:  <?= json_encode($displayName) ?>,
      currentUserAvatar:<?= json_encode($displayAvatarUrl) ?>,
      currentInitials:  <?= json_encode($initials) ?>
    };

    /* ═══════════════════════════════════════════════
       Utilities
    ═══════════════════════════════════════════════ */
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const initials = (fn, ln) => (String(fn||'').charAt(0)+String(ln||'').charAt(0)).toUpperCase() || 'U';
    const displayName = (u) => [u?.first_name, u?.last_name].filter(Boolean).join(' ').trim() || u?.name || 'Member';
    const relTime = (d) => {
      const diff = Math.max(0, Math.floor((Date.now() - new Date(d).getTime()) / 1000));
      if (!diff || isNaN(diff)) return '';
      if (diff < 60) return 'just now';
      if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
      if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
      return `${Math.floor(diff/86400)}d ago`;
    };
    const preview = (t) => {
      if (!t) return { type:'private', id:0 };
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
    const post = (action, body) => fetch(`profile.php?action=${action}`, {
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
    }).then(r=>r.json());
    const get = (action, params={}) => {
      const q = new URLSearchParams({ action, ...Object.fromEntries(Object.entries(params).map(([k,v])=>[k,String(v)])) });
      return fetch(`profile.php?${q}`).then(r=>r.json());
    };

    /* ═══════════════════════════════════════════════
       State
    ═══════════════════════════════════════════════ */
    const S = {
      loaded: false,
      friends: [],
      privateConversations: [],
      groupChats: [],
      mapUsers: [],
      incomingRequests: [],
      outgoingRequests: [],
      activeType: null,
      activeId: 0,
      callStream: null,
      callScreenStream: null,
      callMicOn: true,
      callCameraOn: true,
      callOpen: false,
    };

    /* ═══════════════════════════════════════════════
       Elements
    ═══════════════════════════════════════════════ */
    const $ = (id) => document.getElementById(id);
    const tabs = { chats: $('threadListChats'), requests: $('threadListRequests'), people: $('threadListPeople') };
    const views = { welcome: $('msgWelcome'), chat: $('msgChatView'), people: $('msgPeopleView') };

    /* ═══════════════════════════════════════════════
       Tab switching
    ═══════════════════════════════════════════════ */
    document.querySelectorAll('.msg-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.msg-tab').forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        const t = btn.dataset.tab;
        Object.entries(tabs).forEach(([k,el]) => { el.classList.toggle('section-hidden', k !== t); });
        if (t === 'people') showView('people');
        else if (!S.activeId) showView('welcome');
        else showView('chat');
      });
    });

    function showView(v) {
      Object.entries(views).forEach(([k,el]) => {
        if (!el) return;
        el.classList.toggle('section-hidden', k !== v);
        if (k !== v) el.style.display = 'none';
        else el.style.display = 'flex';
      });
    }
    showView('welcome');

    /* ═══════════════════════════════════════════════
       Render thread list
    ═══════════════════════════════════════════════ */
    function renderThreads() {
      const q = String($('msgSearchInput')?.value || '').toLowerCase().trim();
      const both = [
        ...S.privateConversations.map(t=>({...t, _type:'private'})),
        ...S.groupChats.map(t=>({...t, _type:'group'}))
      ].sort((a,b) => (new Date(b.last_message_at||0)) - (new Date(a.last_message_at||0)));

      const filtered = both.filter(t => {
        const name = t._type==='private' ? displayName(t.peer||{}) : String(t.name||'');
        return !q || name.toLowerCase().includes(q);
      });

      if (!filtered.length) {
        $('threadListChats').innerHTML = '<div class="msg-thread-empty">No conversations yet. Find people to connect!</div>';
        return;
      }

      $('threadListChats').innerHTML = filtered.map(t => {
        const id = t.id;
        const type = t._type;
        const active = S.activeType===type && S.activeId===id;
        const name = type==='private' ? esc(displayName(t.peer||{})) : esc(String(t.name||'Group'));
        const ini = type==='private' ? initials(t.peer?.first_name, t.peer?.last_name) : String(t.name||'G').charAt(0).toUpperCase();
        const sub = esc(String(preview(t)||'').slice(0,60));
        const ts = esc(relTime(t.last_message_at));
        const unread = Math.max(0, Number(t.unread_count||0));
        const isGroup = type==='group';
        return `<button class="msg-thread-item${active?' is-active':''}" data-type="${type}" data-id="${id}">
          <div class="msg-thread-avatar${isGroup?' is-group':''}">${ini}</div>
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

    /* ═══════════════════════════════════════════════
       Render requests
    ═══════════════════════════════════════════════ */
    function renderRequests() {
      const inc = S.incomingRequests;
      const out = S.outgoingRequests;
      const badge = $('reqBadge');
      if (badge) { badge.textContent = inc.length; badge.style.display = inc.length ? 'inline-flex' : 'none'; }

      if (!inc.length && !out.length) {
        $('threadListRequests').innerHTML = '<div class="msg-thread-empty">No pending requests.</div>';
        return;
      }

      const incHtml = inc.map(r => {
        const u = r.user || {};
        return `<div class="friend-req-card" data-req-id="${r.id}">
          <div class="msg-thread-avatar" style="width:42px;height:42px;font-size:14px;">${initials(u.first_name,u.last_name)}</div>
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
        const u = r.user || {};
        return `<div class="friend-req-card">
          <div class="msg-thread-avatar" style="width:42px;height:42px;font-size:14px;background:linear-gradient(135deg,#10b981,#059669);">↗</div>
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
          try {
            await post('profile_friend_request', { mode: btn.dataset.reqAction, request_id: Number(btn.dataset.reqId) });
            await loadData(true);
          } catch(e) { toast(e.message,'error'); }
        });
      });
    }

    /* ═══════════════════════════════════════════════
       Render people directory
    ═══════════════════════════════════════════════ */
    function renderPeople() {
      const q = String($('peopleSearchInput')?.value||'').toLowerCase().trim();
      const meId = window.msgBootstrap.currentUserId;
      const friendIds = new Set(S.friends.map(f=>Number(f.id||0)));
      const outIds = new Set(S.outgoingRequests.map(r=>Number(r.user?.id||r.receiver_id||0)));

      const candidates = (S.mapUsers||[]).filter(u => {
        const uid = Number(u.id||0);
        if (!uid || uid===meId) return false;
        if (friendIds.has(uid)) return false;
        if (!q) return true;
        return displayName(u).toLowerCase().includes(q) || String(u.exact_location||u.country||'').toLowerCase().includes(q);
      }).slice(0, 40);

      if (!candidates.length) {
        $('peopleDirectoryList').innerHTML = '<div class="msg-thread-empty">No discoverable users right now.</div>';
        return;
      }

      $('peopleDirectoryList').innerHTML = candidates.map(u => {
        const uid = Number(u.id||0);
        const sent = outIds.has(uid);
        const isFriend = friendIds.has(uid);
        return `<div class="people-card" style="margin-bottom:10px;">
          <div class="people-card-avatar">${initials(u.first_name,u.last_name)}</div>
          <div class="people-card-info">
            <div class="people-card-name">${esc(displayName(u))}</div>
            <div class="people-card-sub">${esc(String(u.exact_location||u.country||'Unknown location'))}</div>
          </div>
          ${isFriend
            ? `<button class="people-connect-btn is-sent" disabled>Connected</button>`
            : sent
              ? `<button class="people-connect-btn is-sent" disabled>Sent</button>`
              : `<button class="people-connect-btn" data-connect-uid="${uid}">Connect</button>`
          }
        </div>`;
      }).join('');

      $('peopleDirectoryList').querySelectorAll('[data-connect-uid]').forEach(btn => {
        btn.addEventListener('click', async () => {
          btn.disabled = true; btn.textContent = 'Sending…';
          try {
            await post('profile_friend_request', { mode:'send', target_user_id:Number(btn.dataset.connectUid), request_message:'Let us connect on Diversity.is.' });
            toast('Friend request sent.');
            await loadData(true);
          } catch(e) { toast(e.message||'Could not send request.','error'); btn.disabled=false; btn.textContent='Connect'; }
        });
      });
    }

    /* ═══════════════════════════════════════════════
       Open thread
    ═══════════════════════════════════════════════ */
    async function openThread(type, id) {
      S.activeType = type; S.activeId = id;
      showView('chat');

      // Update header
      const src = type==='group' ? S.groupChats : S.privateConversations;
      const thread = src.find(t=>t.id===id);
      if (thread) {
        const name = type==='private' ? displayName(thread.peer||{}) : String(thread.name||'Group');
        const sub  = type==='private' ? String(thread.peer?.role||'member') : String(thread.description||'Group chat');
        const ini  = type==='private' ? initials(thread.peer?.first_name,thread.peer?.last_name) : name.charAt(0).toUpperCase();
        $('chatHeaderAvatar').textContent = ini;
        $('chatHeaderName').textContent = name;
        $('chatHeaderSub').textContent = sub;
        $('callRemoteLabel').textContent = name;
        thread.unread_count = 0;
      }

      renderThreads();
      await loadMessages();
      $('sendMsgBtn').disabled = false;
      $('composerInput').focus();
    }

    /* ═══════════════════════════════════════════════
       Load messages
    ═══════════════════════════════════════════════ */
    async function loadMessages() {
      if (!S.activeType || !S.activeId) return;
      try {
        const data = await get('profile_messages', { thread_type: S.activeType, thread_id: S.activeId });
        renderMessages(data.messages||[]);
      } catch(e) { $('msgBody').innerHTML = '<div class="msg-thread-empty">Could not load messages.</div>'; }
    }

    function renderMessages(msgs) {
      const body = $('msgBody'); if (!body) return;
      const meId = window.msgBootstrap.currentUserId;
      if (!msgs.length) { body.innerHTML = '<div class="msg-date-divider">No messages yet</div>'; return; }

      let lastDate = '';
      body.innerHTML = msgs.map(m => {
        const out = Number(m.sender_id||0) === meId;
        const sender = m.sender || {};
        const ini = initials(sender.first_name, sender.last_name);
        const ts = relTime(m.created_at);
        const day = m.created_at ? new Date(m.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric'}) : '';
        let divider = '';
        if (day && day !== lastDate) { divider = `<div class="msg-date-divider">${esc(day)}</div>`; lastDate = day; }

        const type = String(m.message_type||'text');
        let content = '';
        if (type==='image' && m.media_url) content = `<a href="${esc(m.media_url)}" target="_blank" rel="noopener"><img src="${esc(m.media_url)}" style="max-width:200px;border-radius:10px;display:block;" alt="img"></a>`;
        else if (type==='system') content = `<em style="opacity:.6;font-size:11px;">${esc(m.body||'System update')}</em>`;
        else content = `<p style="margin:0;">${esc(m.body||'')}</p>`;

        return `${divider}<div class="msg-bubble-row ${out?'is-outgoing':'is-incoming'}">
          ${!out?`<div class="msg-bubble-avatar">${ini}</div>`:''}
          <div>
            <div class="msg-bubble">${content}</div>
            <div class="msg-bubble-meta">${ts}</div>
          </div>
        </div>`;
      }).join('');

      body.scrollTop = body.scrollHeight;
    }

    /* ═══════════════════════════════════════════════
       Send message
    ═══════════════════════════════════════════════ */
    async function sendMessage(messageType='text', mediaUrl='') {
      const input = $('composerInput');
      const body  = String(input?.value||'').trim();
      if (!body && !mediaUrl) return;
      if (!S.activeType || !S.activeId) { toast('Select a conversation first.','warning'); return; }

      $('sendMsgBtn').disabled = true;
      try {
        const data = await post('profile_send_message', {
          thread_type: S.activeType, thread_id: S.activeId,
          message_type: messageType, body, media_url: mediaUrl
        });
        if (input) input.value = '';
        await loadMessages();
        await loadData(true);
      } catch(e) { toast(e.message||'Could not send.','error'); }
      finally { $('sendMsgBtn').disabled = false; input?.focus(); }
    }

    /* ═══════════════════════════════════════════════
       Load social data
    ═══════════════════════════════════════════════ */
    async function loadData(force=false) {
      if (S.loading && !force) return;
      S.loading = true;
      try {
        const d = await get('profile_social_data');
        S.friends            = d.friends||[];
        S.privateConversations = d.private_conversations||[];
        S.groupChats         = d.group_chats||[];
        S.mapUsers           = d.map_users||[];
        S.incomingRequests   = d.incoming_requests||[];
        S.outgoingRequests   = d.outgoing_requests||[];
        S.loaded = true;
        renderThreads();
        renderRequests();
        renderPeople();
      } catch(e) { console.warn('Could not load social data:', e); }
      finally { S.loading = false; }
    }

    /* ═══════════════════════════════════════════════
       Emoji picker
    ═══════════════════════════════════════════════ */
    const EMOJIS = ['😀','😂','🥰','😍','😎','🤔','😢','😡','🥺','🤩','👍','👎','❤️','🔥','✅','⭐','🎉','🙌','💯','🚀','💡','🎯','🏆','💬','📎','🖼️','📌','💼','🌍'];
    function buildEmojiPicker() {
      const el = $('emojiDropdown'); if (!el || el.children.length) return;
      EMOJIS.forEach(e => {
        const b = document.createElement('button');
        b.className = 'emoji-btn'; b.textContent = e; b.type = 'button';
        b.addEventListener('click', () => {
          const inp = $('composerInput'); if (inp) { inp.value += e; inp.focus(); }
          el.classList.add('is-hidden');
        });
        el.appendChild(b);
      });
    }

    /* ═══════════════════════════════════════════════
       Call overlay
    ═══════════════════════════════════════════════ */
    function genRoomCode() {
      return `R${S.activeId}-${Math.random().toString(36).slice(2,8).toUpperCase()}`;
    }

    async function openCall(mode='video') {
      if (!S.activeType || !S.activeId) { toast('Select a conversation first.','warning'); return; }
      const wantsVideo = mode !== 'audio';
      try {
        S.callStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: wantsVideo });
        const lv = $('callLocalVideo'); if (lv) lv.srcObject = S.callStream;
        const lf = $('callLocalFallback'); if (lf) lf.style.display = wantsVideo ? 'none' : 'flex';
        S.callMicOn = true; S.callCameraOn = wantsVideo; S.callOpen = true;
        const code = genRoomCode();
        $('callRoomCode').textContent = code;
        $('callTitle').textContent = mode==='audio' ? 'Audio Room' : 'Video Room';
        $('callSub').textContent = `Session with ${$('chatHeaderName')?.textContent||'…'}`;
        $('callOverlay').hidden = false;
        updateCallBtns();
      } catch(e) { toast(e.message||'Camera/mic access denied.','error'); }
    }

    function closeCall() {
      if (S.callStream) { S.callStream.getTracks().forEach(t=>t.stop()); S.callStream = null; }
      if (S.callScreenStream) { S.callScreenStream.getTracks().forEach(t=>t.stop()); S.callScreenStream = null; }
      S.callOpen = false;
      const lv = $('callLocalVideo'); if (lv) lv.srcObject = null;
      $('callOverlay').hidden = true;
      updateCallBtns();
    }

    function updateCallBtns() {
      const mb = $('callMicBtn'), cb = $('callCameraBtn');
      if (mb) mb.className = `call-ctrl-btn ${S.callMicOn?'is-active':''}`;
      if (cb) cb.className = `call-ctrl-btn ${S.callCameraOn?'is-active':''}`;
      if (window.lucide) lucide.createIcons();
    }

    /* ═══════════════════════════════════════════════
       Event listeners
    ═══════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', async () => {
      lucide.createIcons();

      // Search
      $('msgSearchInput')?.addEventListener('input', renderThreads);
      $('peopleSearchInput')?.addEventListener('input', renderPeople);

      // Composer
      const inp = $('composerInput');
      if (inp) {
        inp.addEventListener('input', function() {
          this.style.height = 'auto';
          this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });
        inp.addEventListener('keydown', e => {
          if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
        });
      }
      $('sendMsgBtn')?.addEventListener('click', () => sendMessage());

      // Emoji
      buildEmojiPicker();
      $('emojiToggleBtn')?.addEventListener('click', e => {
        e.stopPropagation();
        $('emojiDropdown')?.classList.toggle('is-hidden');
      });
      document.addEventListener('click', () => $('emojiDropdown')?.classList.add('is-hidden'));

      // Image attach
      $('attachImageBtn')?.addEventListener('click', async () => {
        const url = window.prompt('Paste image URL:'); if (!url?.trim()) return;
        await sendMessage('image', url.trim());
      });

      // Welcome → find people
      $('msgFindPeopleBtn')?.addEventListener('click', () => {
        document.querySelector('.msg-tab[data-tab="people"]')?.click();
      });

      // Call buttons
      $('startAudioCallBtn')?.addEventListener('click', () => openCall('audio'));
      $('startVideoCallBtn')?.addEventListener('click', () => openCall('video'));
      $('callEndBtn')?.addEventListener('click', closeCall);
      $('callOverlay')?.addEventListener('click', e => { if (e.target===$('callOverlay')) closeCall(); });

      $('callMicBtn')?.addEventListener('click', () => {
        if (!S.callStream) return;
        const t = S.callStream.getAudioTracks()[0]; if (!t) return;
        S.callMicOn = !S.callMicOn; t.enabled = S.callMicOn; updateCallBtns();
      });

      $('callCameraBtn')?.addEventListener('click', () => {
        if (!S.callStream) return;
        const t = S.callStream.getVideoTracks()[0]; if (!t) return;
        S.callCameraOn = !S.callCameraOn; t.enabled = S.callCameraOn;
        const lf = $('callLocalFallback'); if (lf) lf.style.display = S.callCameraOn ? 'none' : 'flex';
        updateCallBtns();
      });

      $('callScreenBtn')?.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getDisplayMedia) { toast('Screen share not supported.','warning'); return; }
        try {
          S.callScreenStream = await navigator.mediaDevices.getDisplayMedia({ video:true });
          const lv = $('callLocalVideo'); if (lv) lv.srcObject = S.callScreenStream;
          S.callScreenStream.getVideoTracks()[0].onended = () => {
            if (lv && S.callStream) lv.srcObject = S.callStream;
            S.callScreenStream = null;
          };
        } catch(e) { toast(e.message||'Screen share denied.','error'); }
      });

      $('shareScreenBtn')?.addEventListener('click', () => openCall('screen'));

      $('copyInviteBtn')?.addEventListener('click', async () => {
        const url = `${location.href.split('?')[0]}?thread_type=${S.activeType}&thread_id=${S.activeId}`;
        try { await navigator.clipboard.writeText(url); toast('Invite link copied!'); } catch { toast('Copy failed.','error'); }
      });

      $('openMeetingRoomBtn')?.addEventListener('click', () => {
        window.open('../../Projet-2A/dashboard/index.html#meetings', '_blank', 'noopener,noreferrer');
      });

      $('callCodeBadge')?.addEventListener('click', async () => {
        const code = $('callRoomCode')?.textContent;
        if (!code) return;
        try { await navigator.clipboard.writeText(code); toast('Room code copied!'); } catch {}
      });

      // Create group
      $('createGroupBtn')?.addEventListener('click', () => {
        $('groupNameInput').value = '';
        $('groupDescInput').value = '';
        $('groupMembersList').innerHTML = S.friends.length
          ? S.friends.map(f=>`<label class="create-group-member-row"><input type="checkbox" class="group-member-cb" value="${f.id}"> ${esc(displayName(f))}</label>`).join('')
          : '<p style="font-size:12px;color:#9ca3af;">No friends yet.</p>';
        $('createGroupModal').hidden = false;
      });
      $('cancelGroupBtn')?.addEventListener('click', () => { $('createGroupModal').hidden = true; });
      $('confirmGroupBtn')?.addEventListener('click', async () => {
        const name = $('groupNameInput')?.value.trim();
        if (!name) { toast('Group name is required.','warning'); return; }
        const members = [...document.querySelectorAll('.group-member-cb:checked')].map(c=>Number(c.value));
        try {
          const d = await post('profile_create_group_chat', { name, description: $('groupDescInput')?.value.trim()||'', members });
          toast('Group created!');
          $('createGroupModal').hidden = true;
          await loadData(true);
          if (d.group_chat_id) openThread('group', d.group_chat_id);
        } catch(e) { toast(e.message||'Could not create group.','error'); }
      });

      // Mobile back
      $('msgMobileBack')?.addEventListener('click', () => {
        $('msgSidebar').classList.toggle('is-open');
      });

      // Keyboard
      document.addEventListener('keydown', e => { if (e.key==='Escape' && S.callOpen) closeCall(); });

      // Load data
      await loadData();

      // URL params
      const params = new URLSearchParams(location.search);
      const tt = params.get('thread_type'), tid = Number(params.get('thread_id'));
      if (tt && tid) setTimeout(() => openThread(tt, tid), 400);

      // Poll for new messages
        setInterval(() => { if (S.activeType && S.activeId) loadMessages(); }, 8000);
      });
    </script>

    <!-- Story upload & viewer modals (needed by stories script) -->
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
        <button id="uploadStoryBtn" onclick="uploadStory()" disabled style="margin-top:12px;padding:10px 14px;border-radius:10px;background:#6366f1;color:#fff;border:none;"> <span id="uploadBtnText">Share Story</span></button>
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
        <button onclick="previousStory()" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);z-index:13;padding:10px;border-radius:999px;background:rgba(0,0,0,0.5);color:#fff;border:none;">‹</button>
        <button onclick="nextStory()" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);z-index:13;padding:10px;border-radius:999px;background:rgba(0,0,0,0.5);color:#fff;border:none;">›</button>
      </div>
    </div>

    <script>
      // expose bootstrap user vars expected by migrated story scripts
      window.currentUserName = (window.msgBootstrap && window.msgBootstrap.currentUserName) ? window.msgBootstrap.currentUserName : (window.msgBootstrap ? window.msgBootstrap.currentUserName : 'User');
      window.currentUserId = Number((window.msgBootstrap && window.msgBootstrap.currentUserId) ? window.msgBootstrap.currentUserId : (window.msgBootstrap ? window.msgBootstrap.currentUserId : 0));
    </script>
    <script src="../../assets/js/stories_messages_p2a.js"></script>

    <script src="../../assets/js/main.js"></script>
    <script>window.addEventListener('DOMContentLoaded',()=>{ if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons(); });</script>
  </body>
  </html>