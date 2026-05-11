<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

if (!UserController::isAuthenticated()) {
    header('Location: auth.php');
    exit;
}

$eventController = new EventController();
$event = $eventController->getEventById($_GET['id'] ?? 0);

if (!$event) {
    header('Location: events.php');
    exit;
}

$sidebarUser = UserController::currentUser();
$isOwner = $sidebarUser['id'] == $event->getUserId();
$isAdmin = UserController::isAdmin();

if (!$isOwner && !$isAdmin) {
    header('Location: events.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Event — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- Leaflet for Map Location -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
    </div>
  </nav>

  <main class="profile-main">
    <div class="container" style="max-width: 800px; margin: 0 auto;">
      <div class="glass-card" style="padding: 40px; margin-top: 40px;">
        <div class="section-header" style="text-align: left;">
          <h1 class="text-h1">Edit <span class="text-gradient">Event</span></h1>
          <p class="text-body-lg">Update the details of your suggested event.</p>
        </div>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; color: #22c55e; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
        <?php endif; ?>

        <form id="eventEditForm" action="../../index.php?action=update_event" method="POST" class="fade-in-section" novalidate>
          <input type="hidden" name="id" value="<?= $event->getId() ?>">
          
          <div style="margin-bottom: 20px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Event Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($event->getTitle()) ?>" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
              <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Start Date & Time</label>
              <input type="datetime-local" name="start_date" value="<?= date('Y-m-d\TH:i', strtotime($event->getStartDate())) ?>" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
            </div>
            <div>
              <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">End Date & Time</label>
              <input type="datetime-local" name="end_date" value="<?= date('Y-m-d\TH:i', strtotime($event->getEndDate())) ?>" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
              <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Category</label>
              <select name="category" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
                <option value="Workshop" <?= $event->getCategory() == 'Workshop' ? 'selected' : '' ?>>Workshop</option>
                <option value="Networking" <?= $event->getCategory() == 'Networking' ? 'selected' : '' ?>>Networking</option>
                <option value="Webinar" <?= $event->getCategory() == 'Webinar' ? 'selected' : '' ?>>Webinar</option>
                <option value="Conference" <?= $event->getCategory() == 'Conference' ? 'selected' : '' ?>>Conference</option>
              </select>
            </div>
          </div>

          <div style="margin-bottom: 20px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Location</label>
            <div style="margin-bottom: 10px; font-size: 0.8rem; opacity: 0.8;">Click on the map to set the exact location.</div>
            <div id="eventLocationMap" style="height: 250px; border-radius: 12px; margin-bottom: 15px; z-index: 1;"></div>
            <input type="text" id="formLocation" name="location" value="<?= htmlspecialchars($event->getLocation()) ?>" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px; background: rgba(255,255,255,0.02); cursor: default;" readonly>
          </div>

          <div style="margin-bottom: 20px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Description</label>
            <textarea name="description" rows="5" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;"><?= htmlspecialchars($event->getDescription()) ?></textarea>
          </div>

          <?php if ($isAdmin): ?>
          <div style="margin-bottom: 30px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Status (Admin Only)</label>
            <select name="status" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
                <option value="EN_ATTENTE" <?= $event->getStatus() == 'EN_ATTENTE' ? 'selected' : '' ?>>EN_ATTENTE</option>
                <option value="ACCEPTE" <?= $event->getStatus() == 'ACCEPTE' ? 'selected' : '' ?>>ACCEPTE</option>
                <option value="REFUSE" <?= $event->getStatus() == 'REFUSE' ? 'selected' : '' ?>>REFUSE</option>
            </select>
          </div>
          <?php endif; ?>

          <div style="display: flex; gap: 15px;">
            <button type="submit" class="btn btn-primary" style="flex: 2;">Save Changes</button>
            <a href="event_details.php?id=<?= $event->getId() ?>" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/event-resource-validation.js"></script>
  <script>
    lucide.createIcons();

    // Map logic
    document.addEventListener("DOMContentLoaded", function() {
        let map = L.map('eventLocationMap').setView([36.8065, 10.1815], 11); // Default
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        let marker = L.marker([36.8065, 10.1815], {draggable: true}).addTo(map);

        // Try to search the current location string to place the marker
        const currentLoc = document.getElementById('formLocation').value;
        if (currentLoc && currentLoc !== "Remote") {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(currentLoc)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = data[0].lat;
                        const lon = data[0].lon;
                        map.setView([lat, lon], 13);
                        marker.setLatLng([lat, lon]);
                    }
                })
                .catch(e => console.log(e));
        }

        const updateLocation = async (lat, lng) => {
            const locInput = document.getElementById('formLocation');
            locInput.value = 'Fetching address...';
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
                const data = await res.json();
                locInput.value = data.display_name || `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
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
    });
  </script>
</body>
</html>
