<?php
/* ═══════════════════════════════════════════════════
   AUTH + USER DATA
═══════════════════════════════════════════════════ */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
if (!UserController::isAuthenticated()) {
  $_SESSION['flash_error'] = 'Please sign in.';
  header('Location: auth.php'); exit;
}
$sessionUser  = UserController::currentUser();
$userCtrl     = new UserController();
$userModel    = $userCtrl->getUserById((int)($sessionUser['id'] ?? 0));
$fName        = (string)($userModel ? $userModel->getFirstName() : ($sessionUser['first_name'] ?? ''));
$lName        = (string)($userModel ? $userModel->getLastName()  : ($sessionUser['last_name']  ?? ''));
$displayName  = trim($fName.' '.$lName) ?: 'Member User';
$displayEmail = (string)($userModel ? $userModel->getEmail() : ($sessionUser['email'] ?? ''));
$isAdmin      = strtolower(trim($displayEmail)) === 'admin@diversity.is';
$initials     = strtoupper(substr($fName ?: 'M',0,1).substr($lName ?: 'U',0,1));
$avatarUrl    = (string)($userModel ? ($userModel->getAvatarUrl() ?? '') : ($sessionUser['avatar_url'] ?? ''));
if (!$avatarUrl) {
  $seed = rawurlencode(trim($fName.'-'.$lName.'-'.$displayEmail));
  $avatarUrl = "https://api.dicebear.com/9.x/adventurer/svg?seed={$seed}";
}
$repScore  = 87;
$streak    = 7;
$profLevel = 'Level 12 Collaborator';

/* ═══════════════════════════════════════════════════
   JAMENDO SERVER-SIDE PROXY (avoids CORS)
═══════════════════════════════════════════════════ */
if (isset($_GET['jam'])) {
  header('Content-Type: application/json; charset=utf-8');
  $q   = urlencode(trim((string)($_GET['q'] ?? 'chill')));
  $lim = max(1, min(20,(int)($_GET['n'] ?? 12)));
  $ep  = "https://api.jamendo.com/v3.0/tracks/?client_id=15872b1e&format=json"
       . "&limit={$lim}&search={$q}&include=musicinfo&audioformat=mp32&order=relevance_desc";
  $ch  = curl_init($ep);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>false]);
  $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
  echo $err ? json_encode(['error'=>$err]) : ($res ?: json_encode(['error'=>'empty']));
  exit;
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
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/global.css">
<link rel="stylesheet" href="../../assets/css/home.css">
<link rel="stylesheet" href="../../assets/css/profile.css">
<link rel="stylesheet" href="../../assets/css/profile-light.css">
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<script src="https://unpkg.com/lucide@latest"></script>

<style>
/* ═══════════════════════════════════════════════════
   DESIGN TOKENS
═══════════════════════════════════════════════════ */
:root {
  --f-body:    'Poppins', system-ui, sans-serif;
  --f-display: 'Poppins', sans-serif;
  --f-mono:    'JetBrains Mono', monospace;

  --c-bg:       #f7f9fe;
  --c-shell:    #ffffff;
  --c-s2:       #fcfdfe;
  --c-s3:       #f0f4fd;
  --c-border:   rgba(30, 50, 120, 0.08);
  --c-border2:  rgba(30, 50, 120, 0.04);
  --c-tx:       #0f1523;
  --c-tx2:      #5b6b8a;
  --c-muted:    #9aaabf;
  --c-accent:   #4f52d9;
  --c-a2:       #3d40c7;
  --c-glow:     rgba(79, 82, 217, 0.08);
  --c-glow2:    rgba(79, 82, 217, 0.14);
  --c-cyan:     #0ea5e9;
  --c-green:    #10b981;
  --c-rose:     #e11d48;

  --canvas-bg:  #0b0f1a;
  --canvas-sh:  0 32px 80px rgba(15, 23, 42, 0.25), 0 0 0 1px rgba(255,255,255,0.08), 0 0 0 5px rgba(79, 82, 217, 0.05);

  --tw: 76px;
  --pw: 340px;

  --es: cubic-bezier(.34,1.56,.64,1);
  --eo: cubic-bezier(0,0,.2,1);
  --panel-ease: cubic-bezier(.22,1,.36,1);

  --panel-shadow: 0 16px 48px rgba(15, 25, 70, 0.08), 0 4px 12px rgba(15, 25, 70, 0.04);
  --glass-bg: rgba(255, 255, 255, 0.75);
  --glass-blur: blur(20px) saturate(160%);
}
[data-theme="dark"] {
  --c-bg:      #090b14;
  --c-shell:   #0f1120;
  --c-s2:      #0c0e1a;
  --c-s3:      #141727;
  --c-border:  rgba(255,255,255,.07);
  --c-border2: rgba(255,255,255,.04);
  --c-tx:      #f1f5f9;
  --c-tx2:     #94a3b8;
  --c-muted:   #475569;
  --c-accent:  #7c3aed;
  --c-glow:    rgba(124,58,237,.14);
  --c-glow2:   rgba(124,58,237,.22);
  --panel-shadow: 0 8px 32px rgba(0,0,0,.4), 0 2px 8px rgba(0,0,0,.2);
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0 }
body { font-family:var(--f-body); background:var(--c-bg); color:var(--c-tx); -webkit-font-smoothing:antialiased; overflow-y:scroll; min-height:100vh; transition:background .25s,color .25s }

/* ── profile-content-area override ── */
.profile-content-area.story-host {
  padding: 0 !important;
  overflow: hidden;
  height: calc(100vh - 120px);
  max-height: calc(100vh - 120px);
  display: flex;
  min-width: 0;
}

/* ═══════════════════════════════════════════════════
   STORY SHELL — flex row, NO layout shift on panel open
═══════════════════════════════════════════════════ */
.ss {
  display: flex;
  width: 100%;
  height: 100%;
  overflow: hidden;
  border-radius: 24px;
  border: 1px solid var(--c-border);
  background: var(--glass-bg);
  backdrop-filter: var(--glass-blur);
  -webkit-backdrop-filter: var(--glass-blur);
  box-shadow: 0 24px 64px rgba(0,0,0,.05), inset 0 1px 0 rgba(255,255,255,1);
  transition: background .4s, border-color .4s;
  position: relative;
  animation: ss-entrance 0.8s var(--panel-ease);
}
@keyframes ss-entrance {
  from { opacity: 0; transform: scale(0.97) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

/* ─── TOOLBAR ───────────────────────────────────── */
.ss-bar {
  width: var(--tw);
  background: var(--c-s2);
  border-right: 1px solid var(--c-border2);
  display: flex; flex-direction: column; align-items: center;
  padding: 18px 0 20px; gap: 4px; flex-shrink: 0; flex-grow: 0;
  transition: background .25s, border-color .25s; z-index: 30;
  position: relative;
}
.ss-logo {
  font-family: var(--f-display); font-size: 8.5px; font-weight: 800;
  letter-spacing: .18em; text-transform: uppercase; color: var(--c-muted);
  writing-mode: vertical-rl; margin-bottom: 16px; user-select: none;
}
.tb {
  position: relative; width: 54px; height: 54px; border-radius: 14px;
  border: 1.5px solid transparent; background: transparent; color: var(--c-tx2);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  cursor: pointer; gap: 5px; transition: all .28s var(--es); outline: none;
  -webkit-tap-highlight-color: transparent; flex-shrink: 0;
}
.tb svg { width: 19px; height: 19px; stroke-width: 1.8; flex-shrink: 0; transition: transform .3s var(--es); }
.tb-l { font-size: 8px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; line-height: 1; transition: color .2s; opacity: .8; }
.tb:hover { background: var(--c-s3); color: var(--c-tx); border-color: var(--c-border); transform: translateX(3px) scale(1.05); }
.tb:hover svg { transform: scale(1.18) rotate(5deg); }
.tb:hover .tb-l { opacity: 1; }
.tb.on {
  background: var(--c-glow2);
  color: var(--c-accent);
  border-color: rgba(79, 82, 217, 0.25);
  box-shadow: 0 8px 20px rgba(79, 82, 217, 0.12), inset 0 1px 0 rgba(255,255,255,0.4);
}
.tb.on .tb-l { opacity: 1; }
.tb.on::before {
  content: ''; position: absolute; left: -1px; top: 50%; transform: translateY(-50%);
  width: 4px; height: 26px; background: var(--c-accent); border-radius: 0 4px 4px 0;
  box-shadow: 0 0 14px var(--c-accent), 0 0 24px rgba(79, 82, 217, 0.35);
}
.tb.on svg { transform: scale(1.1); }
.tb-sep { width: 34px; height: 1px; background: var(--c-border2); margin: 8px 0; flex-shrink: 0; }

/* ─── CANVAS AREA — FIXED size, never reflowed ── */
.ss-ca {
  flex: 1; min-width: 0; display: flex; align-items: center; justify-content: center;
  background: var(--c-s3); position: relative; overflow: hidden;
  transition: background .25s;
}
.ss-ca::before {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  background-image: radial-gradient(circle, var(--c-border) 1px, transparent 1px);
  background-size: 24px 24px; opacity: .5;
}
.ss-cv {
  position: relative;
  width: min(calc((100vh - 140px) * 9/16), 340px);
  height: min(calc(100vh - 140px), 604px);
  border-radius: 24px; overflow: hidden;
  background: var(--canvas-bg);
  box-shadow: var(--canvas-sh);
  cursor: crosshair; user-select: none;
  transition: box-shadow .4s, transform .4s var(--es);
  flex-shrink: 0;
  animation: canvas-focus 1.2s var(--es);
}
@keyframes canvas-focus {
  0% { transform: scale(0.94); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
.ss-cv:hover { box-shadow: 0 40px 100px rgba(15, 23, 42, 0.35), 0 0 0 1px rgba(255,255,255,0.12); }
.ss-cv.tool-text { cursor: text; }
.ss-cv.tool-draw { cursor: none; }
.ss-cv.tool-move { cursor: grab; }
.ss-cv.tool-move:active { cursor: grabbing; }
.ss-cv::before {
  content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%);
  width: 64px; height: 17px; background: rgba(0,0,0,.6);
  border-radius: 0 0 12px 12px; z-index: 40; pointer-events: none;
}

.ss-bg { position: absolute; inset: 0; z-index: 0; transition: background .3s, filter .3s; }
.ss-bg img, .ss-bg video { width: 100%; height: 100%; object-fit: cover; display: none; }
.ss-bg.has-media img, .ss-bg.has-media video { display: block; }

#sCam { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 2; display: none; transform: scaleX(-1); }
.cam-live #sCam { display: block; }

#dCv { position: absolute; inset: 0; z-index: 10; pointer-events: none; }
.tool-draw #dCv { pointer-events: all; }

/* Gradient strip */
.gstrip {
  position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%);
  display: flex; gap: 5px; z-index: 22;
  background: rgba(0,0,0,.52); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  padding: 6px 9px; border-radius: 100px; border: 1px solid rgba(255,255,255,.1);
}
.gsw {
  width: 20px; height: 20px; border-radius: 50%; cursor: pointer;
  border: 2.5px solid transparent; transition: all .18s var(--es); flex-shrink: 0;
}
.gsw.on { border-color: #fff; transform: scale(1.3); box-shadow: 0 0 0 2px rgba(255,255,255,.22), 0 4px 12px rgba(0,0,0,.3); }
.gsw:hover:not(.on) { transform: scale(1.12); }

.dur-badge {
  position: absolute; top: 26px; right: 12px;
  background: rgba(0,0,0,.62); backdrop-filter: blur(10px); color: #fff;
  font-family: var(--f-mono); font-size: 11px; font-weight: 700;
  padding: 6px 12px; border-radius: 9999px; z-index: 22;
  border: 1px solid rgba(255,255,255,.12); cursor: pointer; transition: transform .16s, box-shadow .16s, background .16s;
  box-shadow: 0 6px 18px rgba(0,0,0,.28);
}
.dur-badge:hover { transform: translateY(-2px); background: linear-gradient(135deg, rgba(79,82,217,0.9), rgba(61,64,199,0.94)); box-shadow: 0 10px 30px rgba(79,82,217,0.26); border-color: rgba(255,255,255,.15); }

.cam-capture-btn {
  position: absolute;
  left: 50%;
  bottom: 18px;
  transform: translateX(-50%);
  z-index: 24;
  border: none;
  border-radius: 9999px;
  background: rgba(0, 0, 0, 0.62);
  color: #fff;
  font-family: var(--f-body);
  font-size: 0.76rem;
  font-weight: 700;
  letter-spacing: .02em;
  display: none;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  box-shadow: 0 10px 24px rgba(0,0,0,.3);
  cursor: pointer;
  transition: transform .16s var(--eo), background .16s var(--eo);
}
.ss-cv.cam-live .cam-capture-btn { display: inline-flex; }
.cam-capture-btn:hover { transform: translateX(-50%) translateY(-2px); }
.cam-capture-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #f43f5e;
  box-shadow: 0 0 0 0 rgba(244,63,94, .45);
}
.cam-capture-btn.rec .cam-capture-dot {
  animation: recPulse 1.1s infinite;
}
@keyframes recPulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(244,63,94, .45); }
  50% { box-shadow: 0 0 0 9px rgba(244,63,94, 0); }
}

.mus-badge {
  position: absolute; bottom: 44px; left: 50%; transform: translateX(-50%);
  background: rgba(0,0,0,.55); backdrop-filter: blur(10px); color: #fff;
  font-size: 10px; font-weight: 600; padding: 5px 14px; border-radius: 100px; z-index: 22;
  border: 1px solid rgba(255,255,255,.14);
  display: none; align-items: center; gap: 6px;
  white-space: nowrap; max-width: 82%; overflow: hidden; text-overflow: ellipsis;
}
.mus-badge.show { display: flex; animation: mBadgeIn .3s var(--es); }
.mus-note { display: inline-block; animation: noteSpin 2s linear infinite; }
@keyframes mBadgeIn { from{opacity:0;transform:translateX(-50%) translateY(10px)} to{opacity:1;transform:translateX(-50%) translateY(0)} }
@keyframes noteSpin { to{transform:rotate(360deg)} }

/* ─── LAYERS ────────────────────────────────────── */
.ly-txt { position: absolute; z-index: 15; cursor: move; touch-action: none; transform-origin: center center; }
.ly-txt textarea {
  background: none; border: 2px dashed transparent; outline: none;
  font-family: var(--f-display); font-size: 26px; font-weight: 700;
  color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,.55);
  resize: none; min-width: 56px; cursor: text;
  padding: 4px 8px; white-space: nowrap; overflow: hidden; line-height: 1.2;
  transition: border-color .15s, background .15s;
}
.ly-txt textarea:focus, .ly-txt.sel textarea { border-color: rgba(124,58,237,.7); border-radius: 8px; background: rgba(0,0,0,.14); }
.ly-stk { position: absolute; z-index: 15; cursor: move; font-size: 44px; line-height: 1; filter: drop-shadow(0 3px 7px rgba(0,0,0,.4)); transition: transform .12s var(--es); transform-origin: center center; }
.ly-stk:active { transform: scale(1.14); }
.layer-handle {
  position: absolute;
  top: -14px;
  left: 50%;
  transform: translateX(-50%);
  width: 26px;
  height: 16px;
  border-radius: 999px;
  border: 1px solid rgba(255,255,255,.24);
  background: rgba(2,6,23,.7);
  color: #dbeafe;
  display: none;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  line-height: 1;
  cursor: grab;
  z-index: 30;
  user-select: none;
}
.layer-resize {
  position: absolute;
  right: -10px;
  bottom: -10px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 1px solid rgba(255,255,255,.45);
  background: rgba(79,82,217,.9);
  color: #fff;
  display: none;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  cursor: nwse-resize;
  z-index: 30;
}
.l-del {
  position: absolute; top: -10px; right: -10px; width: 20px; height: 20px;
  border-radius: 50%; background: var(--c-rose); color: #fff;
  font-size: 11px; font-weight: 800; border: none; cursor: pointer;
  display: none; align-items: center; justify-content: center; z-index: 25;
  transition: transform .15s var(--es), box-shadow .15s;
}
.l-del:hover { transform: scale(1.22); box-shadow: 0 4px 12px rgba(244,63,94,.5); }
.ly-txt.sel .l-del, .ly-stk.sel .l-del,
.ly-txt.sel .layer-handle, .ly-stk.sel .layer-handle,
.ly-txt.sel .layer-resize, .ly-stk.sel .layer-resize { display: flex; animation: popIn .2s var(--es); }
@keyframes popIn { from{transform:scale(0)} to{transform:scale(1)} }

/* ─── DRAW CURSOR ───────────────────────────────── */
#dcur { position: fixed; pointer-events: none; border-radius: 50%; background: rgba(255,255,255,.85); transform: translate(-50%,-50%); z-index: 9999; display: none; mix-blend-mode: exclusion; transition: width .1s, height .1s; }

/* ═══════════════════════════════════════════════════
   RIGHT PANEL — FIXED WIDTH, NO REFLOW
   Tool panels slide in OVER content via absolute
═══════════════════════════════════════════════════ */
.ss-panel {
  width: var(--pw); flex-shrink: 0; flex-grow: 0;
  background: var(--c-shell); border-left: 1px solid var(--c-border2);
  display: flex; flex-direction: column; overflow: hidden;
  transition: background .25s, border-color .25s;
  position: relative; z-index: 20;
}
.ss-ph {
  padding: 20px 20px 18px; border-bottom: 1px solid var(--c-border);
  display: flex; align-items: center; justify-content: space-between;
  background: var(--c-s2); flex-shrink: 0; transition: background .25s;
}
.ss-ph h2 {
  font-family: var(--f-display); font-size: 1.1rem; font-weight: 700;
  color: var(--c-tx); letter-spacing: -.02em;
}
.ss-cl {
  width: 34px; height: 34px; border-radius: 10px; background: var(--c-s3);
  border: 1px solid var(--c-border); color: var(--c-tx2);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; text-decoration: none; transition: all .25s var(--eo);
}
.ss-cl:hover { background: rgba(225,29,72,.08); border-color: rgba(225,29,72,.2); color: var(--c-rose); transform: rotate(90deg) scale(1.08); }
.ss-cl svg { width: 15px; height: 15px; }

/* ── SCROLLABLE AREA ── */
.ss-scroll { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 14px; overflow-x: hidden; }
.ss-scroll::-webkit-scrollbar { width: 4px; }
.ss-scroll::-webkit-scrollbar-thumb { background: rgba(30, 50, 120, 0.10); border-radius: 10px; }
.ss-scroll::-webkit-scrollbar-track { background: transparent; }

/* ── PANEL SECTIONS — always mounted, shown via CSS ── */
.ps {
  border-radius: 18px; background: var(--c-s2); border: 1px solid var(--c-border2);
  padding: 18px; transition: all .3s var(--eo);
}
.ps:hover { border-color: var(--c-border); box-shadow: 0 4px 16px rgba(15, 25, 70, 0.04); }
/* Tool-specific sections are absolutely positioned to avoid layout reflow */
.tool-stack {
  position: relative;
  flex: 1;
  min-height: 250px;
  width: 100%;
}
.ps-tool {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  opacity: 0;
  pointer-events: none;
  transform: translateY(12px) scale(.98);
  filter: blur(8px);
  transition: opacity .4s var(--panel-ease), transform .4s var(--panel-ease), filter .4s var(--panel-ease);
}
.ps-tool.visible {
  opacity: 1;
  pointer-events: auto;
  transform: translateY(0) scale(1);
  filter: blur(0);
}

.pt {
  font-size: 0.68rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase;
  color: var(--c-muted); margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
}
.pt::after { content: ''; flex: 1; height: 1px; background: var(--c-border2); }
.pt svg { width: 13px; height: 13px; opacity: .55; }

/* ── UPLOAD ── */
.upz {
  border: 2px dashed rgba(79, 82, 217, 0.18); border-radius: 16px; padding: 28px 18px;
  text-align: center; cursor: pointer; transition: all .28s var(--eo); color: var(--c-muted);
  background: linear-gradient(135deg, var(--c-glow), rgba(79, 82, 217, 0.02)); font-size: 0.8rem;
}
.upz:hover, .upz.drag { border-color: var(--c-accent); background: rgba(79, 82, 217, 0.06); color: var(--c-tx); transform: scale(1.015); }
.upz svg { width: 32px; height: 32px; margin: 0 auto 10px; display: block; opacity: .35; transition: all .3s var(--es); }
.upz:hover svg { opacity: .7; transform: translateY(-4px) scale(1.1); }
.upz strong { display: block; margin-bottom: 4px; font-size: 0.85rem; font-weight: 600; color: var(--c-tx2); }
.upz span { font-size: 0.75rem !important; }
#mIn { display: none; }
.cam-btn {
  width: 100%; padding: 12px 16px; border-radius: 12px; background: var(--c-s3);
  border: 1px solid var(--c-border); color: var(--c-tx2); font-family: var(--f-body);
  font-size: 0.82rem; font-weight: 600; display: flex; align-items: center; justify-content: center;
  gap: 10px; cursor: pointer; transition: all .25s var(--eo); margin-top: 12px;
}
.cam-btn:hover { background: var(--c-glow); border-color: rgba(79, 82, 217, 0.2); color: var(--c-tx); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(79, 82, 217, 0.08); }
.cam-btn.on { background: var(--c-glow2); border-color: rgba(79, 82, 217, 0.35); color: var(--c-accent); }
.cam-btn svg { width: 16px; height: 16px; }
.cam-help {
  margin-top: 8px;
  font-size: 0.72rem;
  color: var(--c-muted);
  line-height: 1.5;
}

/* ── DURATION ── */
.dur-row { display: flex; gap: 12px; align-items: center; justify-content: flex-start; padding: 6px 0; }
.db {
  flex: 0 0 auto; min-width: 60px; padding: 8px 14px; border-radius: 9999px; background: var(--c-s3);
  border: 1px solid var(--c-border); color: var(--c-tx2); font-family: var(--f-mono);
  font-size: 0.88rem; font-weight: 700; letter-spacing: 0.02em; cursor: pointer; transition: transform .16s var(--eo), box-shadow .18s var(--eo), background .16s;
  text-align: center; display:inline-flex; align-items:center; justify-content:center;
}
.db.on { background: linear-gradient(135deg, var(--c-accent) 0%, var(--c-a2) 70%); border-color: rgba(var(--color-accent-rgb),0.18); color: #fff; font-weight: 800; box-shadow: 0 8px 28px rgba(var(--color-accent-rgb), 0.22); transform: translateY(-2px); }
.db:hover:not(.on) { color: var(--c-tx); background: var(--c-s3); transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.06); }

/* ── FONT BUTTONS ── */
.font-row { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 14px; }
.fb {
  padding: 7px 14px; border-radius: 10px; background: var(--c-s3);
  border: 1.5px solid var(--c-border); color: var(--c-tx2); font-family: var(--f-body);
  font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all .22s var(--eo);
}
.fb.on { background: var(--c-glow2); border-color: rgba(79, 82, 217, 0.28); color: var(--c-accent); box-shadow: 0 4px 10px rgba(79, 82, 217, 0.1); }
.fb:hover:not(.on) { color: var(--c-tx); transform: translateY(-2px); box-shadow: 0 2px 8px rgba(0,0,0,.04); }

/* ── COLOR ROW ── */
.cr { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
.csw { width: 26px; height: 26px; border-radius: 50%; cursor: pointer; border: 2.5px solid transparent; transition: all .2s var(--es); }
.csw.on { border-color: var(--c-tx); transform: scale(1.25); box-shadow: 0 0 0 3px rgba(0,0,0,.06); }
.csw:hover:not(.on) { transform: scale(1.15); }
.ccol { width: 26px; height: 26px; border-radius: 50%; cursor: pointer; border: 1.5px solid var(--c-border); overflow: hidden; padding: 0; background: none; }
input[type="color"].ccol { padding: 2px; }

/* ── RANGE ── */
.rr { margin-bottom: 8px; }
.rl { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--c-muted); display: flex; justify-content: space-between; margin-bottom: 8px; }
.rng { -webkit-appearance: none; width: 100%; height: 5px; border-radius: 3px; background: var(--c-s3); outline: none; cursor: pointer; }
.rng::-webkit-slider-thumb { -webkit-appearance: none; width: 18px; height: 18px; border-radius: 50%; background: var(--c-accent); cursor: pointer; border: 3px solid var(--c-shell); box-shadow: 0 2px 8px rgba(79, 82, 217, 0.3); transition: transform .18s var(--es); }
.rng::-webkit-slider-thumb:hover { transform: scale(1.3); }

/* ── ADD TEXT BUTTON ── */
.atb {
  width: 100%; padding: 14px 18px; border-radius: 14px; margin-top: 14px;
  background: linear-gradient(135deg, var(--c-accent) 0%, var(--c-a2) 100%); border: none;
  color: #fff; font-family: var(--f-body); font-size: 0.85rem; font-weight: 600;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  cursor: pointer; transition: all .28s var(--eo); box-shadow: 0 8px 24px rgba(79, 82, 217, 0.22);
  position: relative; overflow: hidden;
}
.atb::after {
  content: ''; position: absolute; inset: 0; background: linear-gradient(110deg, transparent 20%, rgba(255,255,255,0.2) 50%, transparent 80%);
  transform: translateX(-150%); transition: transform 0.6s var(--eo);
}
.atb:hover { transform: translateY(-3px); box-shadow: 0 14px 32px rgba(79, 82, 217, 0.32); }
.atb:hover::after { transform: translateX(150%); }
.atb:active { transform: translateY(-1px); }
.atb svg { width: 16px; height: 16px; }

/* ── BRUSH ROW ── */
.br-row { display: flex; gap: 8px; margin-bottom: 14px; }
.brb {
  flex: 1; padding: 10px 6px; border-radius: 12px; background: var(--c-s3);
  border: 1.5px solid var(--c-border); color: var(--c-tx2); font-family: var(--f-body);
  font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all .22s var(--eo);
  display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.brb.on { background: var(--c-glow2); border-color: rgba(79, 82, 217, 0.28); color: var(--c-accent); box-shadow: 0 4px 12px rgba(79, 82, 217, 0.1); }
.brb:hover:not(.on) { transform: translateY(-2px); box-shadow: 0 2px 8px rgba(0,0,0,.03); }
.brp { width: 100%; height: 6px; border-radius: 3px; display: flex; align-items: center; justify-content: center; }
.brp span { border-radius: 50%; background: currentColor; display: block; }

/* ── STICKER GRID ── */
.sg { display: grid; grid-template-columns: repeat(6,1fr); gap: 6px; }
.si {
  font-size: 24px; text-align: center; padding: 8px; border-radius: 12px;
  cursor: pointer; border: 1.5px solid transparent; line-height: 1;
  transition: all .2s var(--es);
}
.si:hover { background: var(--c-s3); border-color: var(--c-border); transform: scale(1.25); box-shadow: 0 4px 12px rgba(0,0,0,.06); }
.si:active { transform: scale(1.1); }

/* ── MUSIC ── */
.mw { position: relative; margin-bottom: 14px; }
.ms {
  width: 100%; padding: 12px 14px 12px 38px; border-radius: 12px;
  background: var(--c-s3); border: 1.5px solid var(--c-border);
  color: var(--c-tx); font-family: var(--f-body); font-size: 0.82rem; outline: none;
  transition: all .25s var(--eo);
}
.ms:focus { border-color: rgba(79, 82, 217, 0.4); background: var(--c-shell); box-shadow: 0 0 0 4px rgba(79, 82, 217, 0.08); }
.ms::placeholder { color: var(--c-muted); font-size: 0.78rem; }
.mi { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: var(--c-muted); pointer-events: none; }
.mskel { display: flex; flex-direction: column; gap: 8px; }
.mskel-r { height: 56px; border-radius: 12px; background: linear-gradient(90deg, var(--c-s3) 25%, var(--c-shell) 50%, var(--c-s3) 75%); background-size: 200% 100%; animation: shim 1.3s infinite; }
@keyframes shim { to{background-position:-200% 0} }
.ml { display: flex; flex-direction: column; gap: 8px; }
.mt {
  display: flex; align-items: center; gap: 12px; padding: 10px 12px;
  border-radius: 12px; background: var(--c-s3); border: 1.5px solid transparent;
  cursor: pointer; transition: all .22s var(--eo);
}
.mt:hover { background: var(--c-glow); border-color: var(--c-border); transform: translateX(3px); }
.mt.sel { background: var(--c-glow2); border-color: rgba(79, 82, 217, 0.28); box-shadow: 0 4px 12px rgba(79, 82, 217, 0.1); }
.mth { width: 40px; height: 40px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: var(--c-shell); display: flex; align-items: center; justify-content: center; font-size: 18px; }
.mth img { width: 100%; height: 100%; object-fit: cover; }
.minf { flex: 1; min-width: 0; }
.mtit { font-size: 0.82rem; font-weight: 600; color: var(--c-tx); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mart { font-size: 0.72rem; color: var(--c-muted); margin-top: 2px; }
.macts { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.mdur { font-family: var(--f-mono); font-size: 0.72rem; color: var(--c-muted); }
.mpb {
  width: 32px; height: 32px; border-radius: 50%; background: var(--c-glow);
  border: 1px solid rgba(79, 82, 217, 0.25); color: var(--c-accent);
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  transition: all .2s var(--eo); font-size: 12px;
}
.mpb:hover { background: var(--c-accent); color: #fff; border-color: transparent; transform: scale(1.12); box-shadow: 0 4px 12px rgba(79, 82, 217, 0.25); }
.mpb.playing { background: var(--c-accent); color: #fff; border-color: transparent; animation: mpulse 1.1s infinite; }
@keyframes mpulse { 0%,100%{box-shadow:0 0 0 0 rgba(79, 82, 217,.35)}50%{box-shadow:0 0 0 8px rgba(79, 82, 217,0)} }
.mnote { font-size: 0.72rem; color: var(--c-muted); margin-top: 8px; line-height: 1.6; }
.mempty { font-size: 0.82rem; color: var(--c-muted); padding: 12px 0; text-align: center; }

/* ── FILTERS ── */
.fstrip { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; }
.fstrip::-webkit-scrollbar { height: 3px; }
.fstrip::-webkit-scrollbar-thumb { background: var(--c-border); border-radius: 2px; }
.fbtn { flex-shrink: 0; width: 66px; display: flex; flex-direction: column; align-items: center; gap: 6px; cursor: pointer; }
.fprev { width: 66px; height: 84px; border-radius: 12px; border: 2px solid var(--c-border2); background: var(--c-s3); display: flex; align-items: center; justify-content: center; overflow: hidden; transition: all .22s var(--eo); font-family: var(--f-display); font-size: 0.78rem; font-weight: 600; color: var(--c-tx2); }
.fbtn:hover .fprev { border-color: var(--c-border); transform: scale(1.06) translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.08); }
.fbtn.on .fprev { border-color: var(--c-accent); box-shadow: 0 0 0 3px var(--c-glow), 0 6px 18px rgba(79, 82, 217, 0.15); }
.fname { font-size: 0.65rem; font-weight: 600; color: var(--c-muted); transition: color .2s; }
.fbtn.on .fname { color: var(--c-accent); }

/* ═══════════════════════════════════════════════════
   PUBLISH BAR — Premium, consistent with profile.php
═══════════════════════════════════════════════════ */
.ss-pub {
  padding: 18px 20px 20px; border-top: 1px solid var(--c-border);
  display: flex; flex-direction: column; gap: 10px;
  background: var(--c-s2); flex-shrink: 0; transition: background .25s;
  position: relative;
}
.ss-pub::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(79, 82, 217, 0.25), transparent);
}

/* Progress bar */
.pp { height: 4px; border-radius: 3px; background: var(--c-s3); overflow: hidden; display: none; margin-bottom: 4px; }
.ppf { height: 100%; width: 0; background: linear-gradient(90deg, var(--c-accent), var(--c-cyan)); border-radius: 3px; transition: width .3s; }

/* Primary + secondary CTA row */
.prow { display: flex; gap: 10px; }

/* YOUR STORY — Primary CTA, highly prominent */
.pbtn-pub {
  flex: 1.6; padding: 14px 12px; border-radius: 14px;
  background: linear-gradient(135deg, var(--c-accent) 0%, #6366f1 50%, #818cf8 100%);
  background-size: 200% 100%;
  border: none; color: #fff;
  font-family: var(--f-body); font-size: 0.85rem; font-weight: 600;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  cursor: pointer; transition: all .28s var(--eo); letter-spacing: .01em;
  box-shadow: 0 6px 20px rgba(79, 82, 217, 0.28), inset 0 1px 0 rgba(255,255,255,.2);
  position: relative; overflow: hidden;
}
.pbtn-pub::before {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,.18), transparent 55%);
  border-radius: inherit;
}
.pbtn-pub::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(110deg, transparent 25%, rgba(255,255,255,.15) 50%, transparent 75%);
  transform: translateX(-150%); transition: transform 0.7s var(--eo);
}
.pbtn-pub:hover {
  background-position: 100% 0;
  transform: translateY(-3px);
  box-shadow: 0 12px 32px rgba(79, 82, 217, 0.35), inset 0 1px 0 rgba(255,255,255,.25);
}
.pbtn-pub:hover::after { transform: translateX(120%); }
.pbtn-pub:active { transform: translateY(0) scale(0.98); transition-duration: 100ms; }
.pbtn-pub svg { width: 14px; height: 14px; flex-shrink: 0; display:block }

/* FRIENDS — Secondary CTA */
.pbtn-fri {
  flex: 1; padding: 10px 10px; border-radius: 12px;
  background: rgba(16,185,129,.06);
  border: 1.5px solid rgba(16,185,129,.2); color: var(--c-green);
  font-family: var(--f-body); font-size: 0.82rem; font-weight: 600;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  cursor: pointer; transition: all .22s var(--eo); letter-spacing: .01em;
}

/* Unified story action — smaller, tighter to match profile tokens */
.story-action {
  font-family: var(--f-body);
  font-weight: 600;
  font-size: 0.86rem;
  border-radius: var(--radius-full);
  padding: 0.45rem 0.9rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  cursor: pointer;
  transition: transform 0.18s var(--eo), box-shadow 0.18s var(--eo);
  box-shadow: 0 6px 18px rgba(var(--color-accent-rgb), 0.22), inset 0 1px 0 rgba(255,255,255,0.12);
  color: #fff;
}
.story-action svg { width: 14px; height: 14px; flex-shrink: 0; display:block }
.story-action:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 10px 26px rgba(var(--color-accent-rgb), 0.30); }
.story-action:active { transform: translateY(0) scale(0.98); box-shadow: 0 6px 14px rgba(var(--color-accent-rgb), 0.18); transition-duration: 120ms; }

/* Circular nav buttons (shared) */
.btn-circle.story-nav-btn {
  background: linear-gradient(135deg, var(--c-accent) 0%, var(--c-a2) 60%, #7c7cf8 100%);
  color: #fff;
  width: 36px; height: 36px; padding: 0; border-radius: 999px;
  display:inline-flex; align-items:center; justify-content:center;
  box-shadow: 0 8px 22px rgba(var(--color-accent-rgb), 0.30);
  border: none; cursor: pointer; transition: transform 0.18s var(--eo), box-shadow 0.18s var(--eo);
}
.btn-circle.story-nav-btn svg { width: 16px; height: 16px; display:block }
.btn-circle.story-nav-btn:hover { transform: scale(1.08); box-shadow: 0 12px 36px rgba(var(--color-accent-rgb), 0.40); }
.btn-circle.story-nav-btn:active { transform: scale(0.96); box-shadow: 0 6px 14px rgba(var(--color-accent-rgb), 0.22); }
.pbtn-fri:hover {
  background: rgba(16,185,129,.12);
  border-color: rgba(16,185,129,.35);
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(16,185,129,.12);
}
.pbtn-fri:active { transform: translateY(0); }
.pbtn-fri svg { width: 15px; height: 15px; flex-shrink: 0; }

/* DISCARD — Tertiary / danger */
.dbtn {
  width: 100%; padding: 12px; border-radius: 12px;
  border: 1.5px solid rgba(225, 29, 72, 0.08);
  background: rgba(225, 29, 72, 0.03); color: var(--c-rose);
  font-family: var(--f-body); font-size: 0.8rem; font-weight: 500; cursor: pointer;
  transition: all .25s var(--eo); display: flex; align-items: center; justify-content: center; gap: 8px;
}
.dbtn:hover { background: rgba(225, 29, 72, 0.07); border-color: rgba(225, 29, 72, 0.18); transform: translateY(-2px); }
.dbtn svg { width: 15px; height: 15px; }

/* ─── TOAST ─────────────────────────────────────── */
.toast {
  position: fixed; top: 80px; left: 50%; transform: translateX(-50%) translateY(-14px);
  background: var(--c-tx); color: var(--c-shell); font-size: 0.82rem; font-weight: 600;
  padding: 12px 24px; border-radius: 100px; pointer-events: none; opacity: 0;
  transition: opacity .25s var(--eo), transform .3s var(--es); z-index: 9999;
  white-space: nowrap; box-shadow: 0 12px 36px rgba(0,0,0,.18); max-width: 340px;
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

/* ─── BACKGROUND ANIMATION ─── */
.bg-blobs {
  position: fixed; inset: 0; z-index: -1;
  background: var(--c-bg); overflow: hidden;
}
.blob {
  position: absolute; width: 40vw; height: 40vw;
  filter: blur(80px); opacity: 0.15; border-radius: 50%;
  animation: blob-float 20s infinite alternate;
}
.blob-1 { background: #4f52d9; top: -10%; left: -10%; animation-delay: 0s; }
.blob-2 { background: #0ea5e9; bottom: -10%; right: -10%; animation-delay: -5s; }
.blob-3 { background: #7c3aed; top: 40%; right: 20%; width: 30vw; height: 30vw; animation-duration: 25s; }

@keyframes blob-float {
  0%   { transform: translate(0, 0) rotate(0deg) scale(1); }
  33%  { transform: translate(10vw, 5vh) rotate(120deg) scale(1.1); }
  66%  { transform: translate(-5vw, 15vh) rotate(240deg) scale(0.9); }
  100% { transform: translate(0, 0) rotate(360deg) scale(1); }
}

/* ─── RESPONSIVE ────────────────────────────────── */
@media(max-width:1360px){:root{--pw:310px}}
@media(max-width:1160px){:root{--pw:280px;--tw:68px}.ss-cv{width:min(calc((100vh - 140px) * 9/16), 300px);height:min(calc(100vh - 140px), 533px)}}
@media(max-width:980px) {:root{--pw:260px;--tw:60px}.ss-cv{width:min(calc((100vh - 140px) * 9/16), 260px);height:min(calc(100vh - 140px), 462px)}}
</style>
</head>
<body class="grid-dot-bg home-page-body with-global-left-sidebar">

<div class="bg-blobs">
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>
</div>

<div id="dcur"></div>
<div class="toast" id="toast"></div>

<!-- ── Navbar ─────────────────────────────────── -->
<nav class="navbar" id="navbar" aria-label="Primary navigation">
  <div class="container">
    <a href="home.php" class="navbar-brand">
      <span class="brand-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></span>
      Diversity.is
    </a>
    <div class="navbar-actions">
      <button class="theme-toggle" aria-label="Toggle theme">
        <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
      <div class="nav-profile">
        <button class="nav-profile-btn" aria-label="User menu">
          <div class="nav-avatar">
            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
          </div>
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

<!-- ── Main ──────────────────────────────────── -->
<?php include __DIR__ . '/partials/global-sidebar.php'; ?>

<main class="profile-main" id="profile-main-content" tabindex="-1">
  <div class="container profile-page-layout">

    <!-- ░░░ STORY EDITOR ░░░ -->
    <section class="profile-content-area story-host">
      <div class="ss">

        <!-- ── Toolbar ───────────────────────────── -->
        <div class="ss-bar">
          <span class="ss-logo">Story</span>

          <button class="tb" data-tool="text"     title="Text [T]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
            <span class="tb-l">Text</span>
          </button>
          <button class="tb" data-tool="draw"     title="Draw [D]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            <span class="tb-l">Draw</span>
          </button>
          <button class="tb" data-tool="stickers" title="Stickers [S]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
            <span class="tb-l">Stick</span>
          </button>
          <button class="tb" data-tool="music"    title="Music [M]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
            <span class="tb-l">Music</span>
          </button>
          <button class="tb" data-tool="filters"  title="Filters [F]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg>
            <span class="tb-l">Filter</span>
          </button>

          <div class="tb-sep"></div>

          <button class="tb" id="undoBtn"  title="Undo [Ctrl+Z]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 00-4-4H4"/></svg>
            <span class="tb-l">Undo</span>
          </button>
          <button class="tb" id="clearBtn" title="Clear drawing">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M19 6l-1 14H6L5 6M9 6V4h6v2M3 6h18"/></svg>
            <span class="tb-l">Clear</span>
          </button>
        </div>

        <!-- ── Canvas ────────────────────────────── -->
        <div class="ss-ca">
          <div class="ss-cv" id="sCV">
            <div class="ss-bg" id="sBg">
              <img id="bImg" alt="">
              <video id="bVid" muted loop playsinline></video>
            </div>
            <video id="sCam" autoplay muted playsinline></video>
            <canvas id="dCv"></canvas>
            <div class="dur-badge" id="durBadge">5s ⏱</div>
            <div class="mus-badge" id="musBadge"><span class="mus-note">♪</span><span id="musBadgeTxt"></span></div>
            <button class="cam-capture-btn" id="camCaptureBtn" type="button" aria-label="Capture story media">
              <span class="cam-capture-dot"></span>
              <span id="camCaptureLabel">Capture</span>
            </button>
            <div class="gstrip" id="gStrip"></div>
          </div>
        </div>

        <!-- ── Right Panel ─────────────────────── -->
        <div class="ss-panel">
          <div class="ss-ph">
            <h2>Create Story</h2>
            <a href="profile.php" class="ss-cl" title="Back to Profile">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </a>
          </div>

          <div class="ss-scroll">

            <!-- MEDIA — always visible -->
            <div class="ps">
              <div class="pt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                Media
              </div>
              <div class="upz" id="upZ">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <strong>Drop photo or video</strong>
                <span style="font-size:10px">or click to browse</span>
              </div>
              <input type="file" id="mIn" accept="image/*,video/*">
              <button class="cam-btn" id="camBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                Open Camera
              </button>
              <p class="cam-help">Tap capture for photo. Press and hold capture to record video.</p>
            </div>

            <!-- DURATION — always visible -->
            <div class="ps">
              <div class="pt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Duration
              </div>
                <div class="dur-row" id="durRow" role="tablist" aria-label="Story duration">
                  <button class="db on" data-d="5" aria-pressed="true" role="tab">5s</button>
                  <button class="db" data-d="10" aria-pressed="false" role="tab">10s</button>
                  <button class="db" data-d="15" aria-pressed="false" role="tab">15s</button>
                  <button class="db" data-d="30" aria-pressed="false" role="tab">30s</button>
                </div>
            </div>

            <div class="tool-stack">
            <!-- TEXT TOOL PANEL -->
            <div class="ps ps-tool" id="pTxt">
              <div class="pt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
                Text
              </div>
              <div class="font-row" id="fontRow"></div>
              <div class="cr" id="txtCR"></div>
              <div class="rr">
                <div class="rl"><span>Font Size</span><span id="szV">28px</span></div>
                <input type="range" class="rng" id="szR" min="14" max="80" value="28">
              </div>
              <button class="atb" id="addTxtBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                Add Text to Canvas
              </button>
            </div>

            <!-- DRAW TOOL PANEL -->
            <div class="ps ps-tool" id="pDrw">
              <div class="pt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Draw
              </div>
              <div class="br-row">
                <button class="brb on" data-br="pen" data-sz="3"><div class="brp"><span style="width:3px;height:3px"></span></div>Fine</button>
                <button class="brb" data-br="pen" data-sz="8"><div class="brp"><span style="width:8px;height:8px"></span></div>Mid</button>
                <button class="brb" data-br="pen" data-sz="20"><div class="brp"><span style="width:14px;height:14px"></span></div>Bold</button>
                <button class="brb" data-br="eraser" data-sz="24"><div class="brp"><span style="width:11px;height:11px;background:rgba(148,163,184,.4);border:1px solid rgba(148,163,184,.5)"></span></div>Erase</button>
              </div>
              <div class="cr" id="drwCR"></div>
              <div class="rr">
                <div class="rl"><span>Opacity</span><span id="opV">100%</span></div>
                <input type="range" class="rng" id="opR" min="10" max="100" value="100">
              </div>
            </div>

            <!-- STICKERS PANEL -->
            <div class="ps ps-tool" id="pStk">
              <div class="pt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                Stickers
              </div>
              <div class="sg" id="stkG"></div>
            </div>

            <!-- MUSIC PANEL -->
            <div class="ps ps-tool" id="pMus">
              <div class="pt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                Music
              </div>
              <div class="mw">
                <svg class="mi" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" class="ms" id="mSrch" placeholder="Search (chill, upbeat, jazz…)" autocomplete="off">
              </div>
              <div class="ml" id="mList"></div>
              <p class="mnote">Powered by Jamendo · Preview only</p>
            </div>

            <!-- FILTERS PANEL -->
            <div class="ps ps-tool" id="pFlt">
              <div class="pt">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg>
                Filters
              </div>
              <div class="fstrip" id="fStrip"></div>
            </div>
            </div><!-- /tool-stack -->

          </div><!-- /ss-scroll -->

          <!-- ── Publish Bar ────────────────────── -->
          <div class="ss-pub">
            <div class="pp" id="pubP"><div class="ppf" id="pubF"></div></div>
            <div class="prow">
              <button class="pbtn-pub btn btn-primary story-action" id="pubPublic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h3l2-3h6l2 3h3a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                Post
              </button>
            </div>
          </div>
        </div><!-- /ss-panel -->

      </div><!-- /ss -->
    </section>

  </div><!-- /container -->
</main>

<script>
/* ═══════════════════════════════════════════════════
   DATA
═══════════════════════════════════════════════════ */
const GS=[
  {l:'Dark',  v:'linear-gradient(160deg,#12131f,#0a0b14)'},
  {l:'Cosmos',v:'linear-gradient(160deg,#0f0c29,#302b63,#24243e)'},
  {l:'Sunset',v:'linear-gradient(160deg,#f093fb,#f5576c)'},
  {l:'Ocean', v:'linear-gradient(160deg,#0652c5,#11abe9)'},
  {l:'Forest',v:'linear-gradient(160deg,#134e5e,#71b280)'},
  {l:'Peach', v:'linear-gradient(160deg,#ffd3a5,#fd6585)'},
  {l:'Neon',  v:'linear-gradient(160deg,#12c2e9,#c471ed,#f64f59)'},
  {l:'Ember', v:'linear-gradient(160deg,#c0392b,#f39c12)'},
];
const PAL=['#ffffff','#000000','#ef4444','#f97316','#eab308','#22c55e','#3b82f6','#7c3aed','#ec4899','#06b6d4','#f8fafc','#1e293b'];
const STKS=[
  '❤️','🔥','⭐','🎉','👍','😂','🥰','😎','🤩','🙌','✨','💯',
  '🎯','🚀','🏆','💎','🌈','🦋','🌸','🍀','🎵','🎸','🎨','💡',
  '📍','🌍','💬','📸','🎬','⚡','🌊','🎪','🧨','🦄','🌙','☀️',
];
const FILTS=[
  {n:'Normal',c:'none'},{n:'Vivid',c:'saturate(1.9) contrast(1.1)'},
  {n:'Muted',c:'saturate(.5) brightness(.92)'},{n:'Warm',c:'sepia(.35) saturate(1.4) brightness(1.05)'},
  {n:'Cool',c:'hue-rotate(18deg) saturate(1.3)'},{n:'B&W',c:'grayscale(1) contrast(1.12)'},
  {n:'Fade',c:'brightness(1.1) saturate(.65) opacity(.9)'},{n:'Chrome',c:'saturate(2) contrast(1.25) brightness(1.05)'},
];
const FONTS=[
  {l:'Poppins',v:'"Poppins",sans-serif'},{l:'Modern',v:'system-ui,sans-serif'},
  {l:'Serif',v:'Georgia,serif'},{l:'Mono',v:'"JetBrains Mono",monospace'},
  {l:'Impact',v:'Impact,Haettenschweiler,sans-serif'},
];

/* STATE */
const S={
  tool:null, font:FONTS[0].v, txc:'#ffffff', txs:28,
  dwc:'#ffffff', brs:3, brm:'pen', opa:1,
  flt:'none', bg:GS[0].v, dur:5,
  cam:null, camOn:false, drawing:false, lx:0, ly:0,
  camRecorder:null, camChunks:[], camPressTimer:null, camRecording:false,
  hist:[], layers:[], sel:null, musT:null, musLoaded:false,
  zTop:18,
};

/* DOM */
const $=id=>document.getElementById(id);
const cv=$('sCV'),bg=$('sBg'),bImg=$('bImg'),bVid=$('bVid');
const sCam=$('sCam'),dc=$('dCv'),ctx=dc.getContext('2d');
const dcur=$('dcur');
const camCaptureBtn=$('camCaptureBtn');
const camCaptureLabel=$('camCaptureLabel');

/* TOOL → PANEL MAP */
const PMAP={ text:'pTxt', draw:'pDrw', stickers:'pStk', music:'pMus', filters:'pFlt' };

/* ── TOAST ── */
let _tt;
const toast=m=>{
  const e=$('toast');
  e.textContent=m;
  e.classList.add('show');
  clearTimeout(_tt);
  _tt=setTimeout(()=>e.classList.remove('show'),2600);
};

/* ── CANVAS RESIZE ── */
function rdc(){
  const pw=dc.width, ph=dc.height;
  const keep = (pw>0 && ph>0) ? ctx.getImageData(0,0,pw,ph) : null;
  dc.width=cv.offsetWidth; dc.height=cv.offsetHeight;
  if(keep){ ctx.putImageData(keep,0,0); }
}
window.addEventListener('resize',rdc);

/* ── GRADIENTS ── */
function buildGS(){
  const s=$('gStrip');
  GS.forEach((g,i)=>{
    const sw=document.createElement('div');
    sw.className='gsw'+(i===0?' on':'');
    sw.style.background=g.v; sw.title=g.l;
    sw.onclick=()=>{
      s.querySelectorAll('.gsw').forEach(x=>x.classList.remove('on'));
      sw.classList.add('on'); S.bg=g.v; bg.style.background=S.bg;
    };
    s.appendChild(sw);
  });
}

/* ── COLOR ROW ── */
function buildCR(id,cb,init='#ffffff'){
  const r=$(id); if(!r) return;
  PAL.forEach(c=>{
    const sw=document.createElement('div');
    sw.className='csw'+(c===init?' on':'');
    sw.style.background=c;
    if(c==='#ffffff') sw.style.border='2.5px solid rgba(148,163,184,.4)';
    sw.onclick=()=>{
      r.querySelectorAll('.csw').forEach(x=>x.classList.remove('on'));
      sw.classList.add('on'); cb(c);
    };
    r.appendChild(sw);
  });
  const ci=document.createElement('input');
  ci.type='color'; ci.className='ccol'; ci.value=init;
  ci.oninput=()=>{ r.querySelectorAll('.csw').forEach(x=>x.classList.remove('on')); cb(ci.value); };
  r.appendChild(ci);
}

/* ── FONTS ── */
function buildFonts(){
  const r=$('fontRow');
  FONTS.forEach((f,i)=>{
    const b=document.createElement('button');
    b.className='fb'+(i===0?' on':'');
    b.textContent=f.l; b.style.fontFamily=f.v;
    b.onclick=()=>{
      r.querySelectorAll('.fb').forEach(x=>x.classList.remove('on'));
      b.classList.add('on'); S.font=f.v;
      if(S.sel){ const ta=S.sel.querySelector('textarea'); if(ta) ta.style.fontFamily=S.font; }
    };
    r.appendChild(b);
  });
}

/* ── STICKERS ── */
function buildStks(){
  const g=$('stkG');
  STKS.forEach(s=>{ const el=document.createElement('div'); el.className='si'; el.textContent=s; el.onclick=()=>addStk(s); g.appendChild(el); });
}

/* ── FILTERS ── */
function buildFilts(){
  const s=$('fStrip');
  FILTS.forEach((f,i)=>{
    const b=document.createElement('div');
    b.className='fbtn'+(i===0?' on':'');
    b.innerHTML=`<div class="fprev" style="filter:${f.c}">Aa</div><div class="fname">${f.n}</div>`;
    b.onclick=()=>{
      s.querySelectorAll('.fbtn').forEach(x=>x.classList.remove('on'));
      b.classList.add('on'); S.flt=f.c; bg.style.filter=f.c; dc.style.filter=f.c;
    };
    s.appendChild(b);
  });
}

/* ── DURATION ── */
$('durRow').addEventListener('click',e=>{
  const b=e.target.closest('.db'); if(!b) return;
  document.querySelectorAll('.db').forEach(x=>{ x.classList.remove('on'); x.setAttribute('aria-pressed','false'); });
  b.classList.add('on'); b.setAttribute('aria-pressed','true'); S.dur=Number(b.dataset.d);
  $('durBadge').textContent=S.dur+'s ⏱'; toast(`⏱ ${S.dur}s`);
});

/* ═══════════════════════════════════════════════════
   TOOL SWITCHING — no layout shift
   Panels use .ps-tool with CSS display:none / .visible
   Canvas cursor changes via class, no reflow
═══════════════════════════════════════════════════ */
function activeTool(t){
  const isSame = S.tool === t;

  /* Close all tool panels */
  Object.values(PMAP).forEach(id=>{
    const el=$(id);
    if(el){ el.classList.remove('visible'); }
  });

  /* Deactivate all toolbar buttons */
  document.querySelectorAll('.tb[data-tool]').forEach(b=>b.classList.remove('on'));

  if(isSame){
    /* Toggle OFF */
    S.tool = null;
    cv.className = 'ss-cv';
    dcur.style.display = 'none';
    return;
  }

  /* Activate new tool */
  S.tool = t;
  document.querySelectorAll('.tb[data-tool]').forEach(b=>b.classList.toggle('on', b.dataset.tool===t));

  const panelId = PMAP[t];
  if(panelId){
    const el=$(panelId);
    if(el){
      /* Force reflow for animation restart */
      el.classList.remove('visible');
      void el.offsetWidth;
      el.classList.add('visible');
    }
  }

  /* Canvas cursor — only class change, no geometry */
  cv.className = `ss-cv tool-${t}`;

  if(t === 'draw'){
    dcur.style.display = 'block'; updCur();
  } else {
    dcur.style.display = 'none';
  }

  if(t === 'music' && !S.musLoaded) searchJam('chill');
}

document.querySelectorAll('.tb[data-tool]').forEach(b=>b.addEventListener('click',()=>activeTool(b.dataset.tool)));

/* ── DRAWING ── */
function gp(e){ const r=dc.getBoundingClientRect(),s=e.touches?e.touches[0]:e; return{x:s.clientX-r.left,y:s.clientY-r.top}; }
function updCur(){ const z=Math.max(S.brs,4); dcur.style.width=z+'px'; dcur.style.height=z+'px'; dcur.style.background=S.brm==='eraser'?'rgba(255,255,255,.28)':S.dwc; }
document.addEventListener('mousemove',e=>{ if(S.tool==='draw'){ dcur.style.left=e.clientX+'px'; dcur.style.top=e.clientY+'px'; } });
dc.addEventListener('mousedown',sd); dc.addEventListener('mousemove',dd); dc.addEventListener('mouseup',ed); dc.addEventListener('mouseleave',ed);
dc.addEventListener('touchstart',e=>{e.preventDefault();sd(e)},{passive:false});
dc.addEventListener('touchmove',e=>{e.preventDefault();dd(e)},{passive:false});
dc.addEventListener('touchend',ed);
function sd(e){ if(S.tool!=='draw') return; S.drawing=true; const p=gp(e); S.lx=p.x; S.ly=p.y; sh(); }
function dd(e){
  if(!S.drawing||S.tool!=='draw') return;
  const p=gp(e);
  ctx.globalAlpha=S.opa;
  if(S.brm==='eraser'){ ctx.globalCompositeOperation='destination-out'; ctx.strokeStyle='rgba(0,0,0,1)'; }
  else{ ctx.globalCompositeOperation='source-over'; ctx.strokeStyle=S.dwc; }
  ctx.lineWidth=S.brs; ctx.lineCap='round'; ctx.lineJoin='round';
  ctx.beginPath(); ctx.moveTo(S.lx,S.ly); ctx.lineTo(p.x,p.y); ctx.stroke();
  S.lx=p.x; S.ly=p.y;
}
function ed(){ S.drawing=false; ctx.globalCompositeOperation='source-over'; }
function sh(){ S.hist.push(ctx.getImageData(0,0,dc.width,dc.height)); if(S.hist.length>40) S.hist.shift(); }
$('undoBtn').addEventListener('click',()=>{ if(S.hist.length>0){ ctx.putImageData(S.hist.pop(),0,0); } else toast('Nothing to undo'); });
$('clearBtn').addEventListener('click',()=>{ sh(); ctx.clearRect(0,0,dc.width,dc.height); toast('✓ Drawing cleared'); });
document.querySelectorAll('.brb').forEach(b=>b.addEventListener('click',()=>{
  document.querySelectorAll('.brb').forEach(x=>x.classList.remove('on'));
  b.classList.add('on'); S.brs=Number(b.dataset.sz); S.brm=b.dataset.br; updCur();
}));
$('opR').addEventListener('input',function(){ S.opa=this.value/100; $('opV').textContent=this.value+'%'; });

/* ── LAYER UTILS / TEXT / STICKERS ── */
const clamp=(v,min,max)=>Math.min(max,Math.max(min,v));
const num=(v,f=0)=>{ const n=parseFloat(v); return Number.isFinite(n)?n:f; };
const nextLayerZ=()=>{ S.zTop=Math.max(S.zTop+1,20); return S.zTop; };
function applyLayerTransform(el){
  const sc=clamp(num(el.dataset.scale,1),0.3,4);
  const rt=num(el.dataset.rot,0);
  el.dataset.scale=String(sc);
  el.dataset.rot=String(rt);
  el.style.transform=`scale(${sc}) rotate(${rt}deg)`;
}
function setLayerScale(el,delta){
  el.dataset.scale=String(clamp(num(el.dataset.scale,1)+delta,0.3,4));
  applyLayerTransform(el);
}
function setLayerRotation(el,delta){
  el.dataset.rot=String(num(el.dataset.rot,0)+delta);
  applyLayerTransform(el);
}
function bringToFront(el){
  const z=nextLayerZ();
  el.dataset.z=String(z);
  el.style.zIndex=String(z);
}
function sendBackward(el){
  const z=clamp(num(el.dataset.z,20)-1,12,9999);
  el.dataset.z=String(z);
  el.style.zIndex=String(z);
}
function removeLayer(el){
  el.remove();
  S.layers=S.layers.filter(x=>x!==el);
  if(S.sel===el) S.sel=null;
}
function selLyr(l){
  if(S.sel&&S.sel!==l) S.sel.classList.remove('sel');
  S.sel=l;
  if(l){
    l.classList.add('sel');
    bringToFront(l);
  }
}
cv.addEventListener('click',()=>{ if(S.sel){ S.sel.classList.remove('sel'); S.sel=null; } });

function attachLayerUi(el){
  const del=document.createElement('button');
  del.className='l-del';
  del.type='button';
  del.textContent='×';
  del.onclick=(e)=>{ e.stopPropagation(); removeLayer(el); };

  const handle=document.createElement('button');
  handle.className='layer-handle';
  handle.type='button';
  handle.textContent='↕';
  handle.title='Drag layer';

  const rz=document.createElement('button');
  rz.className='layer-resize';
  rz.type='button';
  rz.textContent='⤢';
  rz.title='Resize / rotate layer';

  el.appendChild(handle);
  el.appendChild(rz);
  el.appendChild(del);

  makeDrag(el, handle);
  makeResizeRotate(el, rz);
}

function makeDrag(el, dragHandle){
  let drag=false, sx=0, sy=0, sl=0, st=0;
  const onDown=(x,y)=>{
    const r=el.getBoundingClientRect();
    const cr=cv.getBoundingClientRect();
    sx=x; sy=y; sl=r.left-cr.left; st=r.top-cr.top;
    drag=true;
    bringToFront(el);
  };
  const onMove=(x,y)=>{
    if(!drag) return;
    el.style.left=(sl+x-sx)+'px';
    el.style.top=(st+y-sy)+'px';
  };
  const onStop=()=>{ drag=false; };

  dragHandle.addEventListener('pointerdown',e=>{
    e.preventDefault();
    e.stopPropagation();
    selLyr(el);
    onDown(e.clientX,e.clientY);
  });
  window.addEventListener('pointermove',e=>onMove(e.clientX,e.clientY));
  window.addEventListener('pointerup',onStop);
}

function makeResizeRotate(el, knob){
  let active=false, startScale=1, startRot=0, startDist=1, startAngle=0, centerX=0, centerY=0;
  knob.addEventListener('pointerdown',e=>{
    e.preventDefault(); e.stopPropagation();
    selLyr(el);
    const r=el.getBoundingClientRect();
    centerX=r.left+r.width/2; centerY=r.top+r.height/2;
    const dx=e.clientX-centerX, dy=e.clientY-centerY;
    startDist=Math.max(6,Math.hypot(dx,dy));
    startAngle=Math.atan2(dy,dx);
    startScale=num(el.dataset.scale,1);
    startRot=num(el.dataset.rot,0);
    active=true;
  });
  window.addEventListener('pointermove',e=>{
    if(!active) return;
    const dx=e.clientX-centerX, dy=e.clientY-centerY;
    const dist=Math.max(6,Math.hypot(dx,dy));
    const angle=Math.atan2(dy,dx);
    const scale=clamp(startScale*(dist/startDist),0.3,4);
    const rot=startRot+((angle-startAngle)*180/Math.PI);
    el.dataset.scale=String(scale);
    el.dataset.rot=String(rot);
    applyLayerTransform(el);
  });
  window.addEventListener('pointerup',()=>{ active=false; });
}

$('szR').addEventListener('input',function(){
  S.txs=Number(this.value); $('szV').textContent=this.value+'px';
  if(S.sel){ const ta=S.sel.querySelector('textarea'); if(ta) ta.style.fontSize=S.txs+'px'; }
});

function addTxt(t='Your text'){
  const l=document.createElement('div');
  l.className='ly-txt';
  l.style.left='10%';
  l.style.top='36%';
  l.dataset.scale='1';
  l.dataset.rot='0';
  const ta=document.createElement('textarea');
  ta.value=t;
  ta.style.fontFamily=S.font;
  ta.style.fontSize=S.txs+'px';
  ta.style.color=S.txc;
  ta.rows=1;
  ta.addEventListener('input',()=>{ ta.style.width=Math.max(56,ta.scrollWidth+4)+'px'; });
  ta.addEventListener('pointerdown',e=>e.stopPropagation());
  l.appendChild(ta);
  attachLayerUi(l);
  cv.appendChild(l);
  applyLayerTransform(l);
  l.addEventListener('click',e=>{ e.stopPropagation(); selLyr(l); });
  selLyr(l);
  setTimeout(()=>{ ta.focus(); ta.select(); },40);
  S.layers.push(l);
}

function addStk(emoji){
  const l=document.createElement('div');
  l.className='ly-stk';
  l.style.left=Math.random()*55+10+'%';
  l.style.top=Math.random()*45+20+'%';
  l.dataset.scale='1';
  l.dataset.rot='0';
  const sp=document.createElement('span');
  sp.textContent=emoji;
  sp.style.pointerEvents='none';
  l.appendChild(sp);
  attachLayerUi(l);
  cv.appendChild(l);
  applyLayerTransform(l);
  l.addEventListener('click',e=>{ e.stopPropagation(); selLyr(l); });
  selLyr(l);
  S.layers.push(l);
  toast(emoji+' added');
}

$('addTxtBtn').addEventListener('click',()=>addTxt());
cv.addEventListener('click',e=>{ if(S.tool==='text'&&e.target===cv) addTxt(); });

cv.addEventListener('wheel',e=>{
  if(!S.sel) return;
  if(!(e.ctrlKey||e.shiftKey||e.altKey)) return;
  e.preventDefault();
  if(e.shiftKey){ setLayerRotation(S.sel, e.deltaY>0?4:-4); }
  else { setLayerScale(S.sel, e.deltaY>0?-0.05:0.05); }
},{passive:false});

/* ── MEDIA ── */
$('upZ').addEventListener('click',()=>$('mIn').click());
$('mIn').addEventListener('change',function(){
  const f=this.files[0]; if(!f) return;
  const u=URL.createObjectURL(f);
  if(f.type.startsWith('image/')){
    bImg.src=u; bImg.style.display='block'; bVid.style.display='none'; bg.classList.add('has-media'); toast('📷 Photo added');
  } else {
    bVid.src=u; bVid.style.display='block'; bVid.play(); bImg.style.display='none'; bg.classList.add('has-media'); toast('🎥 Video added');
  }
  stopCam();
});
const uz=$('upZ');
['dragenter','dragover'].forEach(ev=>uz.addEventListener(ev,e=>{ e.preventDefault(); uz.classList.add('drag'); }));
['dragleave','drop'].forEach(ev=>uz.addEventListener(ev,()=>uz.classList.remove('drag')));
uz.addEventListener('drop',e=>{
  e.preventDefault();
  const f=e.dataTransfer?.files?.[0]; if(!f) return;
  const dt=new DataTransfer(); dt.items.add(f); $('mIn').files=dt.files; $('mIn').dispatchEvent(new Event('change'));
});

/* ── CAMERA ── */
$('camBtn').addEventListener('click',toggleCam);
async function toggleCam(){
  if(S.camOn){ stopCam(); return; }
  try{
    S.cam=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false});
    sCam.srcObject=S.cam; cv.classList.add('cam-live'); S.camOn=true;
    const b=$('camBtn'); b.classList.add('on');
    b.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:12px;height:12px"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>Stop Camera';
    camCaptureLabel.textContent='Capture';
    camCaptureBtn.classList.remove('rec');
    bImg.style.display='none'; bVid.style.display='none'; bg.classList.remove('has-media'); toast('📸 Camera on');
  } catch(e){ toast('⚠️ Camera denied'); }
}
function stopCam(){
  if (S.camRecording && S.camRecorder && S.camRecorder.state !== 'inactive') {
    try { S.camRecorder.stop(); } catch(_e) {}
  }
  if(S.cam){ S.cam.getTracks().forEach(t=>t.stop()); S.cam=null; }
  cv.classList.remove('cam-live'); S.camOn=false;
  const b=$('camBtn'); b.classList.remove('on');
  b.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width:12px;height:12px"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>Open Camera';
  camCaptureBtn.classList.remove('rec');
  camCaptureLabel.textContent='Capture';
  S.camRecording=false;
}

function setMediaFileFromBlob(blob, fileName){
  if (!blob) return;
  const file = new File([blob], fileName, { type: blob.type || 'application/octet-stream' });
  const dt = new DataTransfer();
  dt.items.add(file);
  $('mIn').files = dt.files;
  $('mIn').dispatchEvent(new Event('change', { bubbles: true }));
}

function captureCameraPhoto(){
  if(!S.camOn) return;
  const tmpCanvas = document.createElement('canvas');
  tmpCanvas.width = sCam.videoWidth || 1080;
  tmpCanvas.height = sCam.videoHeight || 1920;
  const c2 = tmpCanvas.getContext('2d');
  c2.translate(tmpCanvas.width, 0);
  c2.scale(-1, 1);
  c2.drawImage(sCam, 0, 0, tmpCanvas.width, tmpCanvas.height);
  tmpCanvas.toBlob((blob)=>{
    if (!blob) return;
    setMediaFileFromBlob(blob, `camera_${Date.now()}.jpg`);
    toast('📷 Photo captured');
  }, 'image/jpeg', 0.92);
}

function startCameraRecording(){
  if(!S.camOn || S.camRecording || !S.cam) return;
  if(typeof MediaRecorder === 'undefined'){ toast('Video recording is not supported on this browser.'); return; }
  try {
    const opts = MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus')
      ? { mimeType:'video/webm;codecs=vp9,opus' }
      : (MediaRecorder.isTypeSupported('video/webm') ? { mimeType:'video/webm' } : undefined);
    S.camChunks = [];
    S.camRecorder = new MediaRecorder(S.cam, opts);
    S.camRecorder.ondataavailable = (e)=>{ if (e.data && e.data.size) S.camChunks.push(e.data); };
    S.camRecorder.onstop = ()=>{
      const blob = new Blob(S.camChunks, { type: S.camChunks[0]?.type || 'video/webm' });
      if (blob.size > 0) {
        setMediaFileFromBlob(blob, `camera_${Date.now()}.webm`);
        toast('🎥 Video captured');
      }
      S.camChunks = [];
    };
    S.camRecorder.start();
    S.camRecording = true;
    camCaptureBtn.classList.add('rec');
    camCaptureLabel.textContent='Recording…';
  } catch(_e){
    toast('Could not start video recording');
  }
}

function stopCameraRecording(){
  if(!S.camRecording) return;
  S.camRecording = false;
  camCaptureBtn.classList.remove('rec');
  camCaptureLabel.textContent='Capture';
  if (S.camRecorder && S.camRecorder.state !== 'inactive') {
    try { S.camRecorder.stop(); } catch(_e) {}
  }
}

function onCameraCapturePressStart(e){
  if(!S.camOn) return;
  e.preventDefault();
  clearTimeout(S.camPressTimer);
  S.camPressTimer = setTimeout(()=>{
    S.camPressTimer = null;
    startCameraRecording();
  }, 280);
}

function onCameraCapturePressEnd(e){
  if(!S.camOn) return;
  e.preventDefault();
  if (S.camPressTimer) {
    clearTimeout(S.camPressTimer);
    S.camPressTimer = null;
    captureCameraPhoto();
    return;
  }
  if (S.camRecording) stopCameraRecording();
}

camCaptureBtn?.addEventListener('mousedown', onCameraCapturePressStart);
camCaptureBtn?.addEventListener('mouseup', onCameraCapturePressEnd);
camCaptureBtn?.addEventListener('mouseleave', ()=>{ if(S.camRecording) stopCameraRecording(); });
camCaptureBtn?.addEventListener('touchstart', onCameraCapturePressStart, { passive:false });
camCaptureBtn?.addEventListener('touchend', onCameraCapturePressEnd, { passive:false });

/* ── JAMENDO ── */
let _st2,_pa=null,_pid=null;
$('mSrch').addEventListener('input',function(){
  clearTimeout(_st2);
  const q=this.value.trim();
  _st2=setTimeout(()=>searchJam(q||'chill'),480);
});
function fmtD(s){ const m=Math.floor(s/60),se=s%60; return m+':'+String(se).padStart(2,'0'); }
async function searchJam(q='chill'){
  S.musLoaded=true;
  const w=$('mList');
  w.innerHTML='<div class="mskel">'+'<div class="mskel-r"></div>'.repeat(4)+'</div>';
  try{
    const r=await fetch(`?jam=1&q=${encodeURIComponent(q)}&n=12`);
    const d=await r.json();
    renderTracks(d.results||[]);
  } catch(e){ w.innerHTML='<p class="mempty">Could not load tracks.</p>'; }
}
function renderTracks(tracks){
  const w=$('mList');
  if(!tracks.length){ w.innerHTML='<p class="mempty">No tracks found.</p>'; return; }
  w.innerHTML='';
  tracks.forEach(t=>{
    const row=document.createElement('div');
    row.className='mt'+(S.musT&&S.musT.id===t.id?' sel':'');
    const img=t.album_image||t.image||'';
    const dur=t.duration?fmtD(Number(t.duration)):'–';
    const th=img?`<img src="${img}" alt="" loading="lazy" onerror="this.style.display='none'">`:t.name.slice(0,2);
    row.innerHTML=`<div class="mth">${th}</div><div class="minf"><div class="mtit">${eh(t.name||'—')}</div><div class="mart">${eh(t.artist_name||'')}${t.album_name?' · '+eh(t.album_name):''}</div></div><div class="macts"><span class="mdur">${dur}</span><button class="mpb" data-url="${t.audio||t.audiodownload||''}" title="Preview">${_pid===t.id?'⏸':'▶'}</button></div>`;
    row.querySelector('.mpb').addEventListener('click',e=>{ e.stopPropagation(); tPrev(t,e.currentTarget); });
    row.addEventListener('click',()=>selTrack(t,row));
    w.appendChild(row);
  });
}
function tPrev(t,btn){
  if(_pa&&_pid===t.id){ _pa.pause(); _pa=null; _pid=null; btn.textContent='▶'; btn.classList.remove('playing'); return; }
  if(_pa){ _pa.pause(); _pa=null; }
  document.querySelectorAll('.mpb').forEach(b=>{ b.textContent='▶'; b.classList.remove('playing'); });
  const u=t.audio||t.audiodownload; if(!u){ toast('No preview'); return; }
  _pa=new Audio(u); _pa.volume=.7; _pa.play().catch(()=>toast('Preview unavailable'));
  _pid=t.id; btn.textContent='⏸'; btn.classList.add('playing');
  _pa.addEventListener('ended',()=>{ btn.textContent='▶'; btn.classList.remove('playing'); _pa=null; _pid=null; });
}
function selTrack(t,row){
  document.querySelectorAll('.mt').forEach(r=>r.classList.remove('sel'));
  if(S.musT&&S.musT.id===t.id){
    S.musT=null; $('musBadge').classList.remove('show'); toast('Music removed');
  } else {
    S.musT=t; row.classList.add('sel');
    $('musBadgeTxt').textContent=`${t.name} — ${t.artist_name}`;
    $('musBadge').classList.add('show'); toast(`🎵 "${t.name}" selected`);
  }
}
function eh(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ── PUBLISH ── */
async function publish(visibility){
  const pp=$('pubP'),pf=$('pubF');
  pp.style.display='block'; pf.style.width='0%';

  // Collect story data
  const fd = new FormData();
  fd.append('visibility', visibility);
  fd.append('duration', S.dur);

  // Media file
  const fileInput = $('mIn');
  if(fileInput.files.length > 0){
    fd.append('media', fileInput.files[0]);
    fd.append('story_type', fileInput.files[0].type.startsWith('video/') ? 'video' : 'image');
  } else if(S.camOn){
    // Capture camera frame
    const tmpCanvas = document.createElement('canvas');
    tmpCanvas.width = sCam.videoWidth || 360;
    tmpCanvas.height = sCam.videoHeight || 640;
    tmpCanvas.getContext('2d').drawImage(sCam, 0, 0);
    const blob = await new Promise(r => tmpCanvas.toBlob(r, 'image/jpeg', 0.9));
    if(blob) fd.append('media', blob, 'camera_' + Date.now() + '.jpg');
    fd.append('story_type', 'image');
  } else {
    fd.append('story_type', 'text');
    fd.append('gradient_bg', S.bg);
  }

  // Drawing data
  if(dc){
    const dd = dc.toDataURL('image/png');
    if(dd !== 'data:,') fd.append('drawing_data', dd);
  }

  // Text layers
  const textLayers = [];
  cv.querySelectorAll('.ly-txt').forEach(el => {
    const ta = el.querySelector('textarea');
    if(ta && ta.value.trim()){
      textLayers.push({
        text: ta.value,
        x: el.style.left,
        y: el.style.top,
        scale: num(el.dataset.scale, 1),
        rotation: num(el.dataset.rot, 0),
        z_index: num(el.dataset.z, 0),
        color: ta.style.color || '#fff',
        fontSize: ta.style.fontSize || '18px',
        fontFamily: ta.style.fontFamily || 'Poppins'
      });
    }
  });
  if(textLayers.length) fd.append('text_layers', JSON.stringify(textLayers));

  // Sticker layers
  const stickerLayers = [];
  cv.querySelectorAll('.ly-stk').forEach(el => {
    const sp = el.querySelector('span');
    if(sp){
      stickerLayers.push({
        emoji: sp.textContent,
        x: el.style.left,
        y: el.style.top,
        scale: num(el.dataset.scale, 1),
        rotation: num(el.dataset.rot, 0),
        z_index: num(el.dataset.z, 0)
      });
    }
  });
  if(stickerLayers.length) fd.append('sticker_layers', JSON.stringify(stickerLayers));

  // Filter
  if(S.flt && S.flt !== 'none') fd.append('filter_css', S.flt);

  // Music
  if(S.musT){
    fd.append('music_url', S.musT.audio || S.musT.audiodownload || '');
    fd.append('music_title', (S.musT.name || '') + ' — ' + (S.musT.artist_name || ''));
  }

  // Caption from first text layer
  if(textLayers.length) fd.append('caption', textLayers[0].text.substring(0, 280));

  // Animate progress
  let w=0; const iv=setInterval(()=>{ w=Math.min(w+4,88); pf.style.width=w+'%'; },55);

  try {
    const res = await fetch('../../index.php?action=create_story', { method:'POST', body: fd });
    const json = await res.json();
    clearInterval(iv); pf.style.width='100%';
    setTimeout(()=>{
      pp.style.display='none'; pf.style.width='0%';
      if(json.success){
        toast(visibility==='public' ? '✅ Story shared!' : '✅ Shared with Close Friends!');
        setTimeout(()=>window.location.href='profile.php', 1500);
      } else {
        toast('❌ Failed to publish story');
      }
    },350);
  } catch(e){
    clearInterval(iv); pp.style.display='none';
    toast('❌ Network error');
    console.error(e);
  }
}
$('pubPublic').addEventListener('click',()=>publish('public'));
const _pf = $('pubFriends'); if(_pf) _pf.addEventListener('click',()=>publish('friends'));
const _db = $('discardBtn'); if(_db) _db.addEventListener('click',()=>{ if(confirm('Discard story and go back?')) window.location.href='profile.php'; });

/* ── KEYBOARD ── */
document.addEventListener('keydown',e=>{
  if(e.target.tagName==='TEXTAREA'||e.target.tagName==='INPUT') return;
  const k=e.key.toLowerCase();
  if(k==='t') activeTool('text');
  if(k==='d') activeTool('draw');
  if(k==='s') activeTool('stickers');
  if(k==='m') activeTool('music');
  if(k==='f') activeTool('filters');
  if((e.ctrlKey||e.metaKey)&&k==='z'){ e.preventDefault(); $('undoBtn').click(); }
  if(S.sel){
    if(e.key==='ArrowUp'){ e.preventDefault(); S.sel.style.top=(num(S.sel.style.top,0)-2)+'px'; }
    if(e.key==='ArrowDown'){ e.preventDefault(); S.sel.style.top=(num(S.sel.style.top,0)+2)+'px'; }
    if(e.key==='ArrowLeft'){ e.preventDefault(); S.sel.style.left=(num(S.sel.style.left,0)-2)+'px'; }
    if(e.key==='ArrowRight'){ e.preventDefault(); S.sel.style.left=(num(S.sel.style.left,0)+2)+'px'; }
    if(e.key===']'){ e.preventDefault(); bringToFront(S.sel); }
    if(e.key==='['){ e.preventDefault(); sendBackward(S.sel); }
    if(e.key==='='||e.key==='+'){ e.preventDefault(); setLayerScale(S.sel,0.06); }
    if(e.key==='-'||e.key==='_'){ e.preventDefault(); setLayerScale(S.sel,-0.06); }
    if(e.key==='>'||e.key==='.') { e.preventDefault(); setLayerRotation(S.sel,6); }
    if(e.key==='<'||e.key===',') { e.preventDefault(); setLayerRotation(S.sel,-6); }
    if((e.key==='Backspace'||e.key==='Delete')&&!e.metaKey&&!e.ctrlKey){
      e.preventDefault();
      removeLayer(S.sel);
    }
  }
  if(e.key==='Escape'&&S.sel){ S.sel.classList.remove('sel'); S.sel=null; }
});

/* ── INIT ── */
buildGS();
buildCR('txtCR',c=>{ S.txc=c; if(S.sel){ const ta=S.sel.querySelector('textarea'); if(ta) ta.style.color=c; } });
buildCR('drwCR',c=>{ S.dwc=c; updCur(); });
buildFonts(); buildStks(); buildFilts(); rdc();
bg.style.background=S.bg;
</script>

<script>
window.addEventListener('DOMContentLoaded',()=>{
  if(window.lucide&&typeof window.lucide.createIcons==='function') window.lucide.createIcons();
});
</script>
<script src="../../assets/js/main.js"></script>
</body>
</html>