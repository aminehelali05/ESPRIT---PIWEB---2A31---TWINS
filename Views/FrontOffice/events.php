<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

$sidebarUser = UserController::currentUser() ?? [];
$sidebarFirstName = trim((string) ($sidebarUser['first_name'] ?? 'Guest'));
$sidebarLastName = trim((string) ($sidebarUser['last_name'] ?? 'User'));
$sidebarDisplayName = trim($sidebarFirstName . ' ' . $sidebarLastName);
$sidebarDisplayName = $sidebarDisplayName !== '' ? $sidebarDisplayName : 'Guest User';
$sidebarInitials = strtoupper(substr($sidebarFirstName ?: 'G', 0, 1) . substr($sidebarLastName ?: 'U', 0, 1));
$isAdminSidebar = strtolower(trim((string) ($sidebarUser['email'] ?? ''))) === 'admin@diversity.is';

$eventController = new EventController();
$search = $_GET['search'] ?? '';
$filters = [
    'status' => 'ACCEPTE', // Users only see accepted events by default
    'category' => $_GET['category'] ?? ''
];
$events = $eventController->listEvents($search, $filters);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Community events and meetups on Diversity.is.">
  <title>Events — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <link rel="stylesheet" href="../../assets/css/user-form.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- FullCalendar -->
  <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
  <!-- Leaflet for Map Location -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <style>
    /* View Toggle Switch (Pill) */
    .view-toggle { 
        display: flex; 
        background: rgba(99, 102, 241, 0.05); 
        border: 1px solid rgba(99, 102, 241, 0.1); 
        border-radius: 50px; 
        padding: 4px; 
        gap: 4px; 
        margin-bottom: 25px; 
        width: fit-content; 
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }
    .toggle-btn { 
        padding: 8px 20px; 
        border-radius: 50px; 
        border: none; 
        background: transparent; 
        color: var(--color-text-muted); 
        cursor: pointer; 
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
        font-weight: 500; 
        display: flex; 
        align-items: center; 
        gap: 8px; 
    }
    .toggle-btn.active { 
        background: #6366f1; 
        color: #ffffff; 
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); 
    }
    
    /* Animated Event Cards with Hover Lift */
    .project-card.tilt-card {
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        border: 1px solid rgba(0,0,0,0.05); /* very soft border */
    }
    .project-card.tilt-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.06);
    }

    /* Fade-In Animation on Event Cards */
    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .card-animated {
        animation: fadeSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        opacity: 0;
    }

    /* Category Badges with Icons */
    .project-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.75rem;
    }

    /* Bookmark / Save for Later */
    .fav-btn { 
        position: absolute; 
        top: 15px; 
        right: 15px; 
        background: rgba(0, 0, 0, 0.05); 
        border: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: 50%; 
        width: 36px; 
        height: 36px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        cursor: pointer; 
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
        z-index: 10; 
        color: var(--color-text-muted); 
    }
    .fav-btn:hover { 
        transform: scale(1.1); 
        background: rgba(0,0,0,0.1); 
    }
    .fav-btn.active { 
        color: #f59e0b; /* Gold */
        background: rgba(245, 158, 11, 0.1); 
        border-color: rgba(245, 158, 11, 0.2);
        animation: heart-pop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .fav-btn.active i { fill: #f59e0b; }
    
    @keyframes heart-pop {
        0% { transform: scale(1); }
        50% { transform: scale(1.3); }
        100% { transform: scale(1); }
    }

    /* Empty State */
    .empty-state-block {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        background: rgba(0,0,0,0.02);
        border-radius: 16px;
        border: 1px dashed rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
    }
    .empty-state-icon {
        color: var(--color-text-muted);
        opacity: 0.5;
        width: 48px;
        height: 48px;
    }
    
    .progress-container { margin: 15px 0; }
    .progress-bar-bg { background: rgba(0,0,0,0.05); height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 5px; }
    .progress-bar-fill { height: 100%; transition: width 0.5s ease; }

    #calendar-view { display: none; background: #ffffff; padding: 20px; border-radius: 20px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
    .fc { --fc-border-color: rgba(0,0,0,0.05); --fc-today-bg-color: rgba(99, 102, 241, 0.05); }
    .fc-event { cursor: pointer; padding: 2px 5px; border-radius: 4px; border: none !important; }
    
    .event-badge { font-size: 10px; padding: 2px 8px; border-radius: 20px; font-weight: bold; text-transform: uppercase; }
    .badge-full { background: #ef4444; color: white; }
  </style>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="auth.php">Sign In</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php">Projects</a>
        <a href="brainstormings.php">Brainstorming</a>
        <a href="events.php" class="active">Events</a>

      </div>
      <div class="navbar-actions">
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>

        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu">
            <div class="nav-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($sidebarDisplayName) ?></strong>
              <span><?= htmlspecialchars($sidebarUser['email'] ?? '') ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <?php if ($isAdminSidebar): ?>
            <a href="../BackOffice/dashboard.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <?php endif; ?>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <main class="profile-main" id="main-content" tabindex="-1">
    <div class="container profile-page-layout">
      <aside class="home-left glass-card">
        <div class="left-profile">
          <div class="left-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
          <div>
            <h4><?= htmlspecialchars($sidebarDisplayName) ?></h4>
            <p><?= htmlspecialchars($sidebarUser['role'] ?? 'User') ?></p>
          </div>
        </div>
        <nav class="left-nav">
          <a href="home.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg><span>Home Feed</span></a>
          <a href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg><span>Social</span></a>
          <a href="brainstormings.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6m-5 3h4M12 2a7 7 0 00-7 7c0 2.5 1.5 4.5 3 6v1a2 2 0 002 2h4a2 2 0 002-2v-1c1.5-1.5 3-3.5 3-6a7 7 0 00-7-7z" /></svg><span>Brainstorming</span></a>
          <a class="active" href="events.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg><span>Events</span></a>

          <a href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg><span>Profile</span></a>
        </nav>
      </aside>

      <section class="profile-content-area">
        <div class="section-header fade-in-section">
          <span class="section-tag">Explore</span>
          <h1 class="text-h1">Community <span class="text-gradient">Events</span></h1>
          <p class="text-body-lg">Discover and join events tailored for freelancers and professionals.</p>
        </div>

        <div class="projects-toolbar fade-in-section">
          <form action="" method="GET" class="toolbar-filters" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>" class="glass-input" style="padding: 8px 12px; border-radius: 8px;">
            <select name="category" class="glass-input" style="padding: 8px 12px; border-radius: 8px;">
              <option value="">All Categories</option>
              <option value="Workshop" <?= ($filters['category'] ?? '') == 'Workshop' ? 'selected' : '' ?>>Workshop</option>
              <option value="Networking" <?= ($filters['category'] ?? '') == 'Networking' ? 'selected' : '' ?>>Networking</option>
              <option value="Webinar" <?= ($filters['category'] ?? '') == 'Webinar' ? 'selected' : '' ?>>Webinar</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
          </form>
          <button type="button" class="btn btn-primary btn-sm" id="openEventModalBtn">+ Suggest Event</button>
        </div>

        <div class="view-toggle">
            <button class="toggle-btn active" id="show-list"><i data-lucide="layout-grid"></i> List View</button>
            <button class="toggle-btn" id="show-calendar"><i data-lucide="calendar"></i> Calendar View</button>
        </div>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 15px; border-radius: 12px; margin: 20px 0;">
                <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; padding: 15px; border-radius: 12px; margin: 20px 0;">
                <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <div id="list-view" class="grid grid-3 fade-in-section">
          <?php 
          $userController = new UserController();
          $index = 0;
          foreach ($events as $e_row): 
            $ev = $eventController->getEventById($e_row['id']);
            $percent = $ev->getCapaciteMax() > 0 ? ($ev->getNbInscrits() / $ev->getCapaciteMax()) * 100 : 0;
            $barColor = $percent < 50 ? '#10b981' : ($percent < 80 ? '#f59e0b' : '#ef4444');
            $isFav = $eventController->isFavorite($sidebarUser['id'] ?? 0, $ev->getId());
            $isFreelancer = strtolower($sidebarUser['title'] ?? '') === 'freelancer' || strtolower($sidebarUser['role'] ?? '') === 'freelancer';
            
            $creator = $userController->getUserById($ev->getUserId());
            $creatorName = $creator ? trim($creator->getFirstName() . ' ' . $creator->getLastName()) : 'Unknown User';
            if (empty($creatorName)) $creatorName = 'Community Member';
            
            $catIcon = 'calendar';
            if ($ev->getCategory() === 'Workshop') $catIcon = 'hammer';
            elseif ($ev->getCategory() === 'Networking') $catIcon = 'users';
            elseif ($ev->getCategory() === 'Webinar') $catIcon = 'monitor-play';
          ?>
          <div class="project-card glass-card tilt-card card-animated" style="position: relative; animation-delay: <?= $index * 0.1 ?>s;">
            <?php if (!empty($sidebarUser['id'])): ?>
            <button class="fav-btn <?= $isFav ? 'active' : '' ?>" data-id="<?= $ev->getId() ?>" onclick="toggleFav(this)">
                <i data-lucide="bookmark" class="w-5 h-5"></i>
            </button>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div class="project-status status-active">
                    <i data-lucide="<?= $catIcon ?>" class="w-3 h-3"></i> <?= htmlspecialchars($ev->getCategory()) ?>
                </div>
                <?php if ($ev->getStatutInscription() === 'COMPLET'): ?>
                    <span class="event-badge badge-full">Complet</span>
                <?php endif; ?>
            </div>

            <h3 class="project-title"><?= htmlspecialchars($ev->getTitle()) ?></h3>
            <p class="text-small" style="color: var(--color-accent); font-weight: 500; margin-bottom: 8px; font-size: 0.75rem;">By <?= htmlspecialchars($creatorName) ?></p>
            <p class="text-small project-desc"><?= htmlspecialchars(substr($ev->getDescription(), 0, 100)) ?>...</p>
            
            <div class="progress-container">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span class="text-small"><?= $ev->getNbInscrits() ?> / <?= $ev->getCapaciteMax() ?> places</span>
                    <span class="text-small" style="color: <?= $barColor ?>; font-weight: 600;"><?= round($percent) ?>%</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?= min(100, $percent) ?>%; background: <?= $barColor ?>;"></div>
                </div>
            </div>

            <div class="project-meta" style="flex-direction: column; align-items: flex-start; gap: 8px;">
              <span class="text-small" style="display: flex; align-items: center; gap: 6px;">
                <i data-lucide="calendar" class="w-3 h-3"></i> 
                <?= date('M d', strtotime($ev->getStartDate())) ?> • <?= date('H:i', strtotime($ev->getStartDate())) ?> - <?= date('H:i', strtotime($ev->getEndDate())) ?>
              </span>
              <span class="text-small" style="display: flex; align-items: center; gap: 6px;">
                <i data-lucide="map-pin" class="w-3 h-3"></i> <?= htmlspecialchars($ev->getLocation()) ?>
              </span>
            </div>
            <div style="margin-top: 15px;">
                <a href="event_details.php?id=<?= $ev->getId() ?>" class="btn btn-secondary btn-sm" style="width: 100%;">View Details</a>
            </div>
          </div>
          <?php $index++; endforeach; ?>
          <?php if (empty($events)): ?>
            <div class="empty-state-block card-animated">
                <i data-lucide="calendar-search" class="empty-state-icon"></i>
                <p class="text-body-lg" style="margin: 0; opacity: 0.7;">No events match — explore new horizons.</p>
            </div>
          <?php endif; ?>
        </div>

        <div id="calendar-view" class="fade-in-section">
            <div id="calendar"></div>
        </div>
      </section>
    </div>
  </main>

    </div>
  </main>

  <!-- Suggest Event Modal -->
  <div class="uf-overlay" id="eventModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="uf-card" style="max-width: 600px;">
      <div class="uf-header">
        <div class="uf-header-left">
          <p class="uf-title" id="modalTitle">Suggest an Event</p>
          <p class="uf-subtitle">Community events are reviewed by our platform administrators.</p>
        </div>
        <button type="button" id="closeEventModal" class="uf-close" aria-label="Close">
          <i data-lucide="x"></i>
        </button>
      </div>

      <div class="uf-body">
        <form id="eventForm" action="../../index.php?action=create_event" method="POST" autocomplete="off" novalidate>
          <div class="uf-grid">
            <div class="uf-group uf-span-2">
              <label class="uf-label" for="formTitle">Event Title</label>
              <input class="uf-input" id="formTitle" name="title" type="text" placeholder="e.g. Modern Web Development Workshop">
            </div>
            
            <div class="uf-group">
                <label class="uf-label" for="start_date">Start Date & Time</label>
                <input type="datetime-local" name="start_date" id="start_date" required class="uf-input">
            </div>
            <div class="uf-group">
                <label class="uf-label" for="end_date">End Date & Time</label>
                <input type="datetime-local" name="end_date" id="end_date" required class="uf-input">
            </div>

            <div class="uf-group">
              <label class="uf-label" for="formCategory">Category</label>
              <select class="uf-input uf-select" id="formCategory" name="category">
                <option value="Workshop">Workshop</option>
                <option value="Networking">Networking</option>
                <option value="Webinar">Webinar</option>
                <option value="Conference">Conference</option>
              </select>
            </div>

            <div class="uf-group uf-span-2">
              <label class="uf-label" for="formCapacite">Max Capacity</label>
              <input class="uf-input" id="formCapacite" name="capacite_max" type="number" min="1" value="20">
            </div>

            <div class="uf-group uf-span-2">
              <label class="uf-label" for="formLocation">Location</label>
              <div style="margin-bottom: 10px; font-size: 0.8rem; opacity: 0.8;">Click on the map to set the exact location.</div>
              <div id="eventLocationMap" style="height: 250px; border-radius: 12px; margin-bottom: 15px; z-index: 1;"></div>
              <input class="uf-input" id="formLocation" name="location" type="text" placeholder="e.g. Remote (Zoom), or Physical Address" readonly style="background: rgba(255,255,255,0.02); cursor: default;">
            </div>

            <div class="uf-group uf-span-2 is-textarea">
              <label class="uf-label" for="formDescription">Description</label>
              <textarea class="uf-input uf-textarea" id="formDescription" name="description" rows="4" placeholder="Details about the event..."></textarea>
            </div>
          </div>

          <div style="margin-top: 30px; display: flex; gap: 12px;">
            <button type="submit" class="btn btn-primary" style="flex: 1;">Submit Event</button>
            <button type="button" class="btn btn-secondary" id="cancelEventModal" style="flex: 1;">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/event-resource-validation.js"></script>
  <script>
    lucide.createIcons();

    const eventModal = document.getElementById('eventModal');
    const openBtn = document.getElementById('openEventModalBtn');
    const closeBtn = document.getElementById('closeEventModal');
    const cancelBtn = document.getElementById('cancelEventModal');

    let map, marker;
    const initMap = () => {
        if (!map) {
            map = L.map('eventLocationMap').setView([36.8065, 10.1815], 11); // Default: Tunis
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(map);

            marker = L.marker([36.8065, 10.1815], {draggable: true}).addTo(map);

            const updateLocation = async (lat, lng) => {
                const locInput = document.getElementById('formLocation');
                locInput.value = 'Fetching address...';
                try {
                    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
                    const data = await res.json();
                    locInput.value = data.display_name || `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                    locInput.parentElement.classList.add('has-value');
                } catch (e) {
                    locInput.value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                }
            };

            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateLocation(e.latlng.lat, e.latlng.lng);
            });
            marker.on('dragend', function(e) {
                const pos = marker.getLatLng();
                updateLocation(pos.lat, pos.lng);
            });
        } else {
            setTimeout(() => map.invalidateSize(), 100);
        }
    };

    const toggleModal = (show) => {
        if (show) {
            eventModal.classList.add('open');
            document.body.style.overflow = 'hidden';
            initMap();
        } else {
            eventModal.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    openBtn?.addEventListener('click', () => toggleModal(true));
    [closeBtn, cancelBtn].forEach(b => b?.addEventListener('click', () => toggleModal(false)));

    // View Switching
    const listView = document.getElementById('list-view');
    const calendarView = document.getElementById('calendar-view');
    const btnList = document.getElementById('show-list');
    const btnCalendar = document.getElementById('show-calendar');

    btnList.addEventListener('click', () => {
        listView.style.display = 'grid';
        calendarView.style.display = 'none';
        btnList.classList.add('active');
        btnCalendar.classList.remove('active');
    });

    btnCalendar.addEventListener('click', () => {
        listView.style.display = 'none';
        calendarView.style.display = 'block';
        btnList.classList.remove('active');
        btnCalendar.classList.add('active');
        calendar.render();
    });

    // FullCalendar Init
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        themeSystem: 'standard',
        events: '../../index.php?action=get_calendar_events',
        eventClick: function(info) {
            Swal.fire({
                title: info.event.title,
                html: `<b>Category:</b> ${info.event.extendedProps.category}<br><b>Location:</b> ${info.event.extendedProps.location}`,
                showCancelButton: true,
                confirmButtonText: 'View Details',
                confirmButtonColor: '#6366f1',
                cancelButtonText: 'Close'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `event_details.php?id=${info.event.id}`;
                }
            });
        },
        eventDidMount: function(info) {
            const cat = info.event.extendedProps.category;
            const colors = {
                'Workshop': '#6366f1',
                'Networking': '#10b981',
                'Webinar': '#f59e0b'
            };
            info.el.style.backgroundColor = colors[cat] || '#8b5cf6';
        }
    });

    // Favorites Logic
    async function toggleFav(btn) {
        const id = btn.dataset.id;
        try {
            const params = new URLSearchParams();
            params.append('event_id', id);
            
            const resp = await fetch('../../index.php?action=toggle_favorite', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            });
            const res = await resp.json();
            
            if (res.status === 'added') {
                btn.classList.add('active');
                Swal.fire({ icon: 'success', title: 'Added to favorites!', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
            } else if (res.status === 'removed') {
                btn.classList.remove('active');
                Swal.fire({ icon: 'info', title: 'Removed from favorites.', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
            } else {
                Swal.fire({ icon: 'error', title: 'Status: ' + (res.status || 'unknown'), text: res.message || 'Could not update favorites.', confirmButtonColor: '#ef4444' });
            }
        } catch (e) {
            console.error('Favorite error:', e);
            Swal.fire({ icon: 'error', title: 'Network Error', text: 'Please check your connection.', confirmButtonColor: '#ef4444' });
        }
    }

    document.querySelectorAll('.uf-input').forEach(input => {
        input.addEventListener('input', () => {
            if (input.value.trim() !== '') {
                input.parentElement.classList.add('has-value');
            } else {
                input.parentElement.classList.remove('has-value');
            }
        });
    });
  </script>
</body>
</html>
