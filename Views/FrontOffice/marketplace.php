<?php
/* ============================================================
   MARKETPLACE — views/frontoffice/marketplace.php
   REFACTORED: All CSS/JS consolidated into single file
   Light Mode · Poppins · Stripe-grade UI · v4.0
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../Controllers/UserController.php';
require_once __DIR__ . '/../../Controllers/MarketplaceController.php';
require_once __DIR__ . '/../../services/MarketplaceService.php';

if (!UserController::isAuthenticated()) {
    header('Location: auth.php');
    exit;
}

$currentUser = UserController::currentUser();
if (!$currentUser) {
    header('Location: auth.php');
    exit;
}

$currentUserId = $currentUser['id'];
$isAdmin = UserController::isAdmin();
$marketplaceController = new MarketplaceController();
$service = new MarketplaceService(config::getConnexion());

/* ── Handle AJAX Create Listing ────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_listing') {
    header('Content-Type: application/json');
    try {
        // Removed admin-only restriction for creating listings
        // if (!$isAdmin) {
        //     throw new Exception("Only admins can upload listings.");
        // }

        $uploadDir = __DIR__ . '/../../assets/uploads/marketplace/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        $thumbnailUrl = '';
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg','jpeg','png','webp','gif'];
            if (!in_array($ext, $allowedExts)) throw new Exception('Invalid image format.');
            $filename = 'thumb_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $filename)) {
                $thumbnailUrl = '../../assets/uploads/marketplace/' . $filename;
            }
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $type = trim((string) ($_POST['type'] ?? 'digital'));
        if (!in_array($type, ['digital', 'physical'], true)) $type = 'digital';
        $deliveryOption = trim((string) ($_POST['delivery_option'] ?? ($type === 'physical' ? 'shipping' : 'instant')));
        if (!in_array($deliveryOption, ['instant', 'shipping', 'local_pickup'], true)) {
            $deliveryOption = $type === 'physical' ? 'shipping' : 'instant';
        }
        $estimatedDeliveryTime = trim((string) ($_POST['estimated_delivery_time'] ?? ''));
        $shippingCost = (float) ($_POST['shipping_cost'] ?? 0);

        $videoUrl = trim($_POST['video_url'] ?? '');
        $videoPath = '';
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $vExt = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
            $allowedVExts = ['mp4','webm','mov','avi'];
            if (!in_array($vExt, $allowedVExts)) throw new Exception('Invalid video format.');
            $vFilename = 'vid_' . time() . '_' . uniqid() . '.' . $vExt;
            if (move_uploaded_file($_FILES['video']['tmp_name'], $uploadDir . $vFilename)) {
                $videoPath = '../../assets/uploads/marketplace/' . $vFilename;
            }
        }

        if ($title === '') throw new Exception('Title is required.');
        if ($description === '') throw new Exception('Description is required.');
        if ($price <= 0) throw new Exception('Price must be greater than 0.');
        if ($thumbnailUrl === '') throw new Exception('Thumbnail image is required.');

        $payload = [
            'title'             => $title,
            'description'       => $description,
            'price'             => $price,
            'category_id'       => $categoryId,
            'type'              => $type,
            'thumbnail_url'     => $thumbnailUrl,
            'video_url'         => $videoUrl !== '' ? $videoUrl : null,
            'video_path'        => $videoPath !== '' ? $videoPath : null,
            'delivery_option'   => $deliveryOption,
            'estimated_delivery_time' => $estimatedDeliveryTime !== '' ? $estimatedDeliveryTime : null,
            'shipping_cost'     => $shippingCost,
            'status'            => 'active'
        ];

        $marketplaceController->createItem($currentUserId, $payload);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_listing_media') {
    header('Content-Type: application/json');
    try {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId <= 0) throw new Exception('Missing item id.');

        $item = $service->getItemById($itemId);
        if (!$isAdmin && (!$item || (int)$item['user_id'] !== $currentUserId)) {
            throw new Exception('Access denied. You do not own this listing.');
        }

        $uploadDir = __DIR__ . '/../../assets/uploads/marketplace/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        $updates = [];

        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) throw new Exception('Invalid image format.');
            $filename = 'thumb_' . time() . '_' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $filename)) throw new Exception('Could not upload the new thumbnail.');
            $updates['thumbnail_url'] = '../../assets/uploads/marketplace/' . $filename;
        }



        if (empty($updates)) throw new Exception('Please choose at least one new file to save.');
        if (!$service->updateItemMedia($itemId, $updates)) throw new Exception('Could not update the listing media.');

        echo json_encode(['success' => true, 'thumbnail_url' => $updates['thumbnail_url'] ?? '']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ── Handle AJAX Update Listing ────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_listing') {
    header('Content-Type: application/json');
    try {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId <= 0) throw new Exception('Missing item id.');

        $videoUrl = trim($_POST['video_url'] ?? '');
        $videoPath = null; // We might want to keep existing path if no new file uploaded
        
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../assets/uploads/marketplace/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            
            $vExt = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
            if (!in_array($vExt, ['mp4','webm','mov','avi'])) throw new Exception('Invalid video format.');
            $vFilename = 'vid_' . time() . '_' . uniqid() . '.' . $vExt;
            if (move_uploaded_file($_FILES['video']['tmp_name'], $uploadDir . $vFilename)) {
                $videoPath = '../../assets/uploads/marketplace/' . $vFilename;
            }
        }
        
        $payload = [
            'title'             => trim($_POST['title'] ?? ''),
            'description'       => trim($_POST['description'] ?? ''),
            'price'             => (float)($_POST['price'] ?? 0),
            'category_id'       => (int)($_POST['category_id'] ?? 0),
            'type'              => trim($_POST['type'] ?? 'digital'),
            'video_url'         => $videoUrl !== '' ? $videoUrl : null,
            'delivery_option'   => trim($_POST['delivery_option'] ?? 'instant'),
            'estimated_delivery_time' => trim($_POST['estimated_delivery_time'] ?? ''),
            'shipping_cost'     => (float) ($_POST['shipping_cost'] ?? 0)
        ];

        if ($videoPath !== null) {
            $payload['video_path'] = $videoPath;
        }
        
        if ($payload['title'] === '') throw new Exception('Title is required.');
        if ($payload['description'] === '') throw new Exception('Description is required.');
        if ($payload['price'] <= 0) throw new Exception('Price must be greater than 0.');

        if (!$marketplaceController->updateItem($currentUserId, $itemId, $payload, $isAdmin)) {
            throw new Exception('Could not update listing. Access denied or item not found.');
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/* ── Handle AJAX Delete Listing ────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_listing') {
    header('Content-Type: application/json');
    try {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId <= 0) throw new Exception('Missing item id.');
        if (!$marketplaceController->deleteItem($currentUserId, $itemId, $isAdmin)) {
            throw new Exception('Could not delete listing. Access denied or item not found.');
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$filters    = ['search' => $_GET['q'] ?? '', 'category_id' => $_GET['category'] ?? ''];
$items      = $marketplaceController->listItems($filters);
$categories = $service->getCategories();
$marketplaceOrders = $marketplaceController->getOrdersForUser($currentUserId);
$recommendedItems  = $service->getRecommendedItems($currentUserId, 6);
$trendingItems     = $service->getTrendingItems(6);
$myListings        = $marketplaceController->getMyListings($currentUserId);

$mpUniqueItems = static function (array $source): array {
    $seen = []; $out = [];
    foreach ($source as $item) {
        $id = (int) ($item['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) continue;
        $seen[$id] = true; $out[] = $item;
    }
    return $out;
};

$activityCategoryIds = [];
foreach ($marketplaceOrders as $order) {
    $categoryId = (int) ($order['category_id'] ?? 0);
    if ($categoryId > 0) $activityCategoryIds[$categoryId] = true;
}

$mpRankItems = static function (array $source) use ($currentUser, $activityCategoryIds): array {
    $scored = [];
    foreach ($source as $item) {
        $id = (int) ($item['id'] ?? 0);
        if ($id <= 0) continue;
        $score = ((float) ($item['rating'] ?? 0)) * 10.0;
        $score += ((int) ($item['review_count'] ?? 0)) / 12.0;
        if (isset($activityCategoryIds[(int) ($item['category_id'] ?? 0)])) $score += 24.0;
        if ((string) ($item['type'] ?? '') === 'physical') $score += 3.5;
        if ((string) ($item['delivery_option'] ?? '') === 'instant') $score += 2.0;
        $createdAt = strtotime((string) ($item['created_at'] ?? ''));
        if ($createdAt) $score += max(0.0, 10.0 - min(10.0, (time() - $createdAt) / 86400));
        if ((int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0)) $score -= 4.0;
        $scored[] = ['score' => $score, 'item' => $item];
    }
    usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
    return array_values(array_map(static fn ($r) => $r['item'], $scored));
};

$mpCatalog = $mpUniqueItems(array_merge($items, $recommendedItems, $trendingItems, $myListings));
$mpPersonalizedItems = $mpRankItems($mpCatalog);
$featuredItem = $mpPersonalizedItems[0] ?? ($mpCatalog[0] ?? null);
$ownedItemIds = array_values(array_unique(array_map(static fn ($o) => (int) ($o['item_id'] ?? 0), $marketplaceOrders)));

$buyerCountry = trim((string) ($currentUser['country'] ?? ''));
$buyerExactLocation = trim((string) ($currentUser['exact_location'] ?? ''));
$buyerLatitude = isset($currentUser['latitude']) && is_numeric($currentUser['latitude']) ? (float) $currentUser['latitude'] : null;
$buyerLongitude = isset($currentUser['longitude']) && is_numeric($currentUser['longitude']) ? (float) $currentUser['longitude'] : null;

$compactItem = static function (array $item): array {
    return [
        'id' => (int) ($item['id'] ?? 0),
        'user_id' => (int) ($item['user_id'] ?? 0),
        'title' => (string) ($item['title'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'price' => (float) ($item['price'] ?? 0),
        'thumbnail_url' => (string) ($item['thumbnail_url'] ?? ''),
        'category_id' => (int) ($item['category_id'] ?? 0),
        'category_name' => (string) ($item['category_name'] ?? ''),
        'type' => (string) ($item['type'] ?? 'digital'),
        'delivery_option' => (string) ($item['delivery_option'] ?? 'instant'),
        'estimated_delivery_time' => (string) ($item['estimated_delivery_time'] ?? ''),
        'shipping_cost' => (float) ($item['shipping_cost'] ?? 0),
        'rating' => (float) ($item['rating'] ?? 0),
        'review_count' => (int) ($item['review_count'] ?? 0),
        'students_count' => (int) ($item['students_count'] ?? 0),
        'created_at' => (string) ($item['created_at'] ?? ''),
        'first_name' => (string) ($item['first_name'] ?? ''),
        'last_name' => (string) ($item['last_name'] ?? ''),
        'avatar_url' => (string) ($item['avatar_url'] ?? ''),
        'country' => (string) ($item['country'] ?? ''),
        'exact_location' => (string) ($item['exact_location'] ?? ''),
        'latitude' => isset($item['latitude']) && is_numeric($item['latitude']) ? (float) $item['latitude'] : null,
        'longitude' => isset($item['longitude']) && is_numeric($item['longitude']) ? (float) $item['longitude'] : null,
        'xp' => (int) ($item['xp'] ?? 0),
        'role' => (string) ($item['role'] ?? ''),
        'status' => (string) ($item['status'] ?? 'active'),
        'video_url' => (string) ($item['video_url'] ?? ''),
        'video_path' => (string) ($item['video_path'] ?? ''),
        'curriculum' => $item['curriculum'] ?? null,
        'what_to_learn' => $item['what_to_learn'] ?? null,
    ];
};

$mpCatalogJson       = array_map($compactItem, $mpCatalog);
$mpFeaturedJson      = $featuredItem ? $compactItem($featuredItem) : null;
$mpOrdersJson        = $marketplaceOrders;
$mpRecommendedJson   = array_map($compactItem, array_slice($mpPersonalizedItems, 0, 6));
$ownedItemIds        = array_values(array_unique(array_map(static fn ($o) => (int) ($o['item_id'] ?? 0), $marketplaceOrders)));

/* ── Profile Vars ────────── */
$sidebarFirstName   = $currentUser['first_name'] ?? 'User';
$sidebarLastName    = $currentUser['last_name'] ?? '';
$sidebarDisplayName = trim($sidebarFirstName . ' ' . $sidebarLastName);
$sidebarInitials    = strtoupper(substr($sidebarFirstName, 0, 1) . substr($sidebarLastName ?: 'U', 0, 1));
$avatarUrl          = $currentUser['avatar_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marketplace — Diversity.is</title>

<script>
(function(){
  try { document.documentElement.setAttribute('data-theme','light'); localStorage.setItem('app_theme','light'); }
  catch(e){ document.documentElement.setAttribute('data-theme','light'); }
})();
</script>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest"></script>

<!-- Only truly global/shared CSS — sidebar, navbar, user-form shell -->
<link rel="stylesheet" href="../../assets/css/global.css">
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<link rel="stylesheet" href="../../assets/css/user-form.css">

<style>
/* ═══════════════════════════════════════════════════════════════
   MARKETPLACE — CONSOLIDATED DESIGN SYSTEM
   Single source of truth · Light Mode · Poppins · v4.0
   No external marketplace CSS dependencies.
═══════════════════════════════════════════════════════════════ */

/* ── Reset & Base ───────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

.mp-page, .mp-page * { font-family: 'Poppins', sans-serif; }

/* ── Design Tokens (Light Mode) ─────────────────────────────── */
:root {
  /* Easing */
  --mp-ease: cubic-bezier(0.22, 1, 0.36, 1);
  --mp-ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --mp-dur-fast: 180ms;
  --mp-dur-base: 320ms;

  /* Palette */
  --mp-white: #ffffff;
  --mp-gray-50: #f9fafb;
  --mp-gray-100: #f3f4f6;
  --mp-gray-200: #e5e7eb;
  --mp-gray-300: #d1d5db;
  --mp-gray-400: #9ca3af;
  --mp-gray-500: #6b7280;
  --mp-gray-700: #374151;
  --mp-gray-900: #111827;

  --mp-indigo-50: #eef2ff;
  --mp-indigo-100: #e0e7ff;
  --mp-indigo-200: #c7d2fe;
  --mp-indigo-500: #6366f1;
  --mp-indigo-600: #4f46e5;
  --mp-indigo-700: #4338ca;
  --mp-purple-500: #a855f7;
  --mp-teal-500: #14b8a6;
  --mp-emerald-500: #10b981;
  --mp-amber-500: #f59e0b;
  --mp-rose-500: #f43f5e;

  /* Shadows */
  --mp-shadow-xs: 0 1px 2px rgba(0,0,0,.05);
  --mp-shadow-sm: 0 2px 8px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  --mp-shadow-md: 0 4px 16px rgba(0,0,0,.08), 0 2px 4px rgba(0,0,0,.04);
  --mp-shadow-lg: 0 12px 32px rgba(0,0,0,.10), 0 4px 8px rgba(0,0,0,.04);
  --mp-shadow-xl: 0 24px 56px rgba(0,0,0,.13), 0 6px 12px rgba(0,0,0,.05);
  --mp-shadow-brand: 0 8px 24px rgba(99,102,241,.22), 0 2px 6px rgba(99,102,241,.12);
  --mp-shadow-hover: 0 20px 48px rgba(0,0,0,.12), 0 6px 12px rgba(0,0,0,.06);
  --mp-glow-indigo: 0 0 0 3px rgba(99,102,241,.18);

  /* Radius */
  --mp-r-sm: 8px;
  --mp-r-md: 12px;
  --mp-r-lg: 18px;
  --mp-r-xl: 24px;
  --mp-r-2xl: 32px;
  --mp-r-full: 9999px;
}

/* ── Page Shell ─────────────────────────────────────────────── */
body { font-family: 'Poppins', sans-serif; background: var(--mp-gray-50); }

.mp-main {
  padding: 48px 0 96px;
  flex: 1;
  overflow-y: auto;
}

.marketplace-main-col {
  display: flex;
  flex-direction: column;
  gap: 28px;
  min-width: 0;
}

.marketplace-side-col {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* ── Glass Card (sidebar panels) ────────────────────────────── */
.glass-card.panel {
  background: var(--mp-white);
  border: 1.5px solid var(--mp-gray-100);
  border-radius: var(--mp-r-xl);
  box-shadow: var(--mp-shadow-sm);
  padding: 20px;
}

/* ── Scroll Reveal ──────────────────────────────────────────── */
.reveal-section {
  opacity: 0;
  transform: translateY(24px);
  transition: opacity .65s var(--mp-ease-out), transform .65s var(--mp-ease-out);
}
.reveal-section.visible { opacity: 1; transform: translateY(0); }

/* ════════════════════════════════════════════════════════
   HERO
════════════════════════════════════════════════════════ */
.mp-hero {
  display: grid;
  grid-template-columns: 1.1fr 1.4fr 0.9fr;
  gap: 32px;
  align-items: center;
  padding: 32px;
  background: rgba(255, 255, 255, 0.65);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.6);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.8);
  border-radius: var(--mp-r-2xl);
  transition: transform 0.4s var(--mp-ease), box-shadow 0.4s var(--mp-ease);
}
.mp-hero:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.9);
}

.mp-hero-left { display: flex; flex-direction: column; gap: 16px; }

.mp-hero-title {
  font-size: 2.8rem;
  font-weight: 800;
  line-height: 1.08;
  letter-spacing: -0.04em;
  color: var(--mp-gray-900);
}

.mp-hero-sub {
  font-size: 1rem;
  color: var(--mp-gray-500);
  line-height: 1.65;
  max-width: 40ch;
}

/* Featured card */
.mp-featured-card {
  border-radius: var(--mp-r-xl);
  overflow: hidden;
  box-shadow: var(--mp-shadow-lg);
  border: 1.5px solid var(--mp-gray-100);
  background: var(--mp-white);
  cursor: pointer;
  transition: transform .4s var(--mp-ease), box-shadow .4s ease;
}
.mp-featured-card:hover {
  transform: translateY(-8px) scale(1.015);
  box-shadow: var(--mp-shadow-xl);
}
.mp-featured-media { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; }
.mp-featured-info { padding: 18px 20px; background: var(--mp-white); }
.mp-featured-tag {
  display: inline-block;
  padding: 3px 10px;
  background: var(--mp-indigo-600);
  color: var(--mp-white);
  font-size: 0.62rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  border-radius: var(--mp-r-full);
  margin-bottom: 8px;
  box-shadow: var(--mp-shadow-brand);
}

/* Hero right actions */
.mp-hero-right { display: flex; flex-direction: column; gap: 10px; }

.mp-hero-action-card {
  background: var(--mp-white);
  border: 1.5px solid var(--mp-gray-200);
  border-radius: var(--mp-r-lg);
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  text-decoration: none;
  color: inherit;
  cursor: pointer;
  box-shadow: var(--mp-shadow-xs);
  transition: all .22s var(--mp-ease);
  width: 100%;
  text-align: left;
}
.mp-hero-action-card:hover {
  background: var(--mp-indigo-50);
  border-color: var(--mp-indigo-200);
  transform: translateX(5px) scale(1.02);
  box-shadow: var(--mp-shadow-md);
}

.mp-action-icon {
  width: 40px; height: 40px;
  border-radius: var(--mp-r-md);
  background: var(--mp-indigo-50);
  color: var(--mp-indigo-600);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background .2s, color .2s;
}
.mp-hero-action-card:hover .mp-action-icon { background: var(--mp-indigo-100); color: var(--mp-indigo-700); }
.mp-action-icon svg, .mp-action-icon i { width: 18px; height: 18px; }

.mp-action-text strong { display: block; font-size: .88rem; font-weight: 700; color: var(--mp-gray-900); }
.mp-action-text span   { font-size: .73rem; color: var(--mp-gray-400); }

/* Primary CTA */
.mp-btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 26px;
  background: linear-gradient(135deg, #2563EB, #60A5FA);
  color: var(--mp-white);
  border-radius: 999px;
  font-family: 'Poppins', sans-serif;
  font-weight: 600;
  font-size: .95rem;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
  position: relative;
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  letter-spacing: .01em;
}
.mp-btn-primary:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 8px 25px rgba(37, 99, 235, 0.45);
  background: linear-gradient(135deg, #1D4ED8, #3B82F6);
}
.mp-btn-primary::after {
  content: "";
  position: absolute;
  top: 50%;
  left: 50%;
  width: 150%;
  height: 150%;
  background: rgba(255,255,255,0.2);
  transform: translate(-50%, -50%) scale(0);
  border-radius: 50%;
  opacity: 0;
  transition: transform 0.4s ease-out, opacity 0.4s ease-out;
}
.mp-btn-primary:active::after {
  transform: translate(-50%, -50%) scale(1);
  opacity: 1;
  transition: 0s;
}
.mp-btn-primary svg, .mp-btn-primary i { width: 16px; height: 16px; transition: transform 0.3s ease; }
.mp-btn-primary:hover svg, .mp-btn-primary:hover i { transform: translateX(2px); }
.mp-btn-primary svg, .mp-btn-primary i { width: 16px; height: 16px; }

/* ════════════════════════════════════════════════════════
   SEARCH BAR
════════════════════════════════════════════════════════ */
.mp-search-section { display: flex; flex-direction: column; gap: 16px; }

.mp-search-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px 10px 20px;
  border-radius: var(--mp-r-xl);
  background: var(--mp-white);
  border: 1.5px solid var(--mp-gray-200);
  box-shadow: var(--mp-shadow-md);
  transition: border-color var(--mp-dur-fast) ease, box-shadow var(--mp-dur-fast) ease, transform var(--mp-dur-fast) ease;
}
.mp-search-bar:focus-within {
  border-color: rgba(99,102,241,.55);
  box-shadow: var(--mp-shadow-md), var(--mp-glow-indigo);
  transform: translateY(-1px);
}

.mp-search-field {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12px;
  background: transparent;
  border: none;
}
.mp-search-field > svg, .mp-search-field > i {
  width: 17px; height: 17px;
  color: var(--mp-gray-400);
  flex-shrink: 0;
  transition: color var(--mp-dur-fast) ease;
}
.mp-search-bar:focus-within .mp-search-field > svg,
.mp-search-bar:focus-within .mp-search-field > i { color: var(--mp-indigo-500); }

.mp-search-input {
  flex: 1;
  background: none; border: none; outline: none;
  font-family: 'Poppins', sans-serif;
  font-size: .9rem; font-weight: 500;
  color: var(--mp-gray-900);
  padding: 12px 0;
  caret-color: var(--mp-indigo-500);
}
.mp-search-input::placeholder { color: var(--mp-gray-400); font-weight: 400; }

.mp-search-bar .mp-select-wrap {
  position: relative;
  display: flex;
  align-items: center;
  padding-left: 14px;
  border-left: 1.5px solid var(--mp-gray-200);
  margin-left: 4px;
}
.mp-search-bar .mp-select-wrap svg,
.mp-search-bar .mp-select-wrap i {
  position: absolute; left: 24px;
  width: 14px; height: 14px;
  color: var(--mp-gray-400);
  pointer-events: none; z-index: 1;
}

.mp-select {
  appearance: none;
  background: transparent;
  border: none;
  color: var(--mp-gray-700);
  font-family: 'Poppins', sans-serif;
  font-size: .82rem; font-weight: 600;
  padding: 10px 14px 10px 38px;
  cursor: pointer; outline: none;
  min-width: 150px;
}

.mp-search-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 11px 24px;
  border-radius: 999px;
  background: linear-gradient(135deg, #2563EB, #60A5FA);
  color: var(--mp-white);
  font-family: 'Poppins', sans-serif;
  font-size: .84rem; font-weight: 700;
  border: none; cursor: pointer;
  letter-spacing: .02em;
  box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
  transition: background 0.3s ease, transform 0.3s var(--mp-ease), box-shadow 0.3s ease;
  flex-shrink: 0; white-space: nowrap;
}
.mp-search-btn:hover {
  background: linear-gradient(135deg, #1D4ED8, #3B82F6);
  transform: translateY(-2px) scale(1.02);
  box-shadow: 0 8px 25px rgba(37, 99, 235, 0.45);
}

/* ════════════════════════════════════════════════════════
   CATEGORY CHIPS
════════════════════════════════════════════════════════ */
.mp-categories {
  display: flex; gap: 8px;
  overflow-x: auto; padding: 4px 0 10px;
  scrollbar-width: none; -ms-overflow-style: none;
}
.mp-categories::-webkit-scrollbar { display: none; }

.mp-cat-chip {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 18px;
  border-radius: var(--mp-r-full);
  font-family: 'Poppins', sans-serif;
  font-size: .78rem; font-weight: 600;
  white-space: nowrap; cursor: pointer;
  text-decoration: none;
  background: var(--mp-white);
  color: var(--mp-gray-500);
  border: 1.5px solid var(--mp-gray-200);
  box-shadow: var(--mp-shadow-xs);
  transition: background var(--mp-dur-fast) ease, color var(--mp-dur-fast) ease, border-color var(--mp-dur-fast) ease, transform var(--mp-dur-fast) var(--mp-ease), box-shadow var(--mp-dur-fast) ease;
}
.mp-cat-chip:hover {
  background: var(--mp-indigo-50);
  color: var(--mp-indigo-600);
  border-color: var(--mp-indigo-200);
  transform: translateY(-2px) scale(1.03);
}
.mp-cat-chip.active {
  background: var(--mp-indigo-600);
  color: var(--mp-white);
  border-color: var(--mp-indigo-600);
  box-shadow: var(--mp-shadow-brand);
  transform: translateY(-1px);
}
.mp-cat-chip.active:hover {
  background: var(--mp-indigo-700);
  transform: translateY(-2px) scale(1.02);
}
.mp-cat-icon { width: 14px; height: 14px; opacity: .75; flex-shrink: 0; transition: opacity var(--mp-dur-fast) ease; }
.mp-cat-chip:hover .mp-cat-icon, .mp-cat-chip.active .mp-cat-icon { opacity: 1; }

/* ════════════════════════════════════════════════════════
   GRID HEADER + VIEW TOGGLE
════════════════════════════════════════════════════════ */
.mp-grid-header {
  display: flex; align-items: center;
  justify-content: space-between; gap: 16px;
  padding: 4px 0 8px;
}
.mp-grid-title { font-size: 1.05rem; font-weight: 800; color: var(--mp-gray-900); letter-spacing: -.01em; }
.mp-grid-count { font-size: .75rem; color: var(--mp-gray-400); font-weight: 500; margin-top: 3px; }

.mp-view-toggle {
  display: flex; gap: 3px;
  background: var(--mp-gray-100);
  padding: 4px; border-radius: var(--mp-r-md);
  border: 1px solid var(--mp-gray-200);
}
.mp-view-btn {
  padding: 7px 10px; border-radius: var(--mp-r-sm);
  border: none; background: none;
  color: var(--mp-gray-400); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all var(--mp-dur-fast) ease;
}
.mp-view-btn:hover { color: var(--mp-gray-700); }
.mp-view-btn.active {
  background: var(--mp-white);
  color: var(--mp-indigo-600);
  box-shadow: var(--mp-shadow-xs);
}
.mp-view-btn svg, .mp-view-btn i { width: 15px; height: 15px; }

/* ════════════════════════════════════════════════════════
   PRODUCT GRID
════════════════════════════════════════════════════════ */
.mp-products {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 20px;
}
.mp-products.list-view { grid-template-columns: 1fr; }

/* ════════════════════════════════════════════════════════
   PRODUCT CARD
════════════════════════════════════════════════════════ */
.mp-card {
  background: var(--mp-white);
  border: 1.5px solid var(--mp-gray-100);
  border-radius: var(--mp-r-xl);
  box-shadow: var(--mp-shadow-sm);
  overflow: hidden; cursor: pointer;
  display: flex; flex-direction: column;
  transition: transform var(--mp-dur-base) var(--mp-ease), box-shadow var(--mp-dur-base) var(--mp-ease), border-color var(--mp-dur-fast) ease;
  opacity: 0;
}
.mp-card.revealed { animation: mp-card-appear .55s var(--mp-ease-out) forwards; }
@keyframes mp-card-appear {
  from { opacity: 0; transform: translateY(28px) scale(.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
.mp-card:hover {
  transform: translateY(-8px) scale(1.015);
  box-shadow: var(--mp-shadow-hover);
  border-color: rgba(99,102,241,.25);
}

/* Card preview */
.mp-card-preview { position: relative; aspect-ratio: 16/10; overflow: hidden; background: var(--mp-gray-50); }
.mp-card-preview img {
  width: 100%; height: 100%; object-fit: cover;
  transition: transform .7s var(--mp-ease);
}
.mp-card:hover .mp-card-preview img { transform: scale(1.07); }

.mp-card-placeholder {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  background: var(--mp-gray-100);
}
.mp-card-placeholder svg, .mp-card-placeholder i { width: 40px; height: 40px; color: var(--mp-gray-300); }

/* Price badge */
.mp-price-tag {
  position: absolute; top: 12px; right: 12px; z-index: 2;
  background: var(--mp-white);
  color: var(--mp-indigo-600);
  border: 1.5px solid var(--mp-indigo-100);
  padding: 5px 12px; border-radius: var(--mp-r-full);
  font-size: .78rem; font-weight: 800;
  box-shadow: var(--mp-shadow-sm); letter-spacing: .01em;
  transition: background var(--mp-dur-fast) ease, color var(--mp-dur-fast) ease;
}
.mp-card:hover .mp-price-tag {
  background: var(--mp-indigo-600);
  color: var(--mp-white);
  border-color: var(--mp-indigo-600);
}

/* Quick actions overlay */
.mp-card-actions {
  position: absolute; bottom: 0; left: 0; right: 0;
  padding: 16px 14px;
  background: linear-gradient(to top, rgba(255,255,255,.97) 0%, rgba(255,255,255,.7) 80%, transparent 100%);
  display: flex; gap: 6px;
  transform: translateY(100%);
  transition: transform .35s var(--mp-ease); z-index: 3;
}
.mp-card:hover .mp-card-actions { transform: translateY(0); }

.mp-action-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: var(--mp-r-full);
  font-family: 'Poppins', sans-serif;
  font-size: .75rem; font-weight: 600;
  border: 1.5px solid var(--mp-gray-200);
  background: var(--mp-white); color: var(--mp-gray-700);
  cursor: pointer;
  transition: background var(--mp-dur-fast) ease, border-color var(--mp-dur-fast) ease, color var(--mp-dur-fast) ease, transform var(--mp-dur-fast) var(--mp-ease);
}
.mp-action-btn:hover {
  background: var(--mp-indigo-50); border-color: var(--mp-indigo-200);
  color: var(--mp-indigo-600); transform: translateY(-1px);
}
.mp-action-btn.primary {
  background: var(--mp-indigo-600); border-color: var(--mp-indigo-600);
  color: var(--mp-white); box-shadow: var(--mp-shadow-brand);
}
.mp-action-btn.primary:hover {
  background: var(--mp-indigo-700); border-color: var(--mp-indigo-700);
  transform: translateY(-2px);
}
.mp-action-btn svg, .mp-action-btn i { width: 12px; height: 12px; }

/* Card body */
.mp-card-body { padding: 18px 20px 20px; flex: 1; display: flex; flex-direction: column; }
.mp-card-category { font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .12em; color: var(--mp-indigo-500); margin-bottom: 8px; }
.mp-card-title { font-size: .95rem; font-weight: 700; color: var(--mp-gray-900); line-height: 1.45; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.mp-card-desc { font-size: .78rem; color: var(--mp-gray-500); line-height: 1.65; margin-bottom: 14px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.mp-rating { display: flex; align-items: center; gap: 6px; margin-bottom: 12px; }
.mp-stars { color: var(--mp-amber-500); font-size: .73rem; letter-spacing: 1px; }
.mp-rating-val { font-size: .78rem; font-weight: 700; color: var(--mp-amber-500); }
.mp-rating-count { font-size: .7rem; color: var(--mp-gray-400); }

.mp-seller {
  display: flex; align-items: center; gap: 10px;
  margin-top: auto; padding-top: 14px;
  border-top: 1px solid var(--mp-gray-100);
}
.mp-seller-copy { display: flex; flex-direction: column; gap: 1px; flex: 1; min-width: 0; }
.mp-seller-name { font-size: .78rem; font-weight: 600; color: var(--mp-gray-700); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mp-seller-location { font-size: .66rem; color: var(--mp-gray-400); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mp-seller-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 2px solid var(--mp-gray-100); flex-shrink: 0; }
.mp-seller-avatar-fallback {
  width: 30px; height: 30px; border-radius: 50%;
  background: linear-gradient(135deg, var(--mp-indigo-500), var(--mp-purple-500));
  display: flex; align-items: center; justify-content: center;
  font-size: .62rem; font-weight: 800; color: var(--mp-white); flex-shrink: 0;
}
.mp-verified { color: var(--mp-emerald-500); display: flex; align-items: center; flex-shrink: 0; }
.mp-verified svg, .mp-verified i { width: 14px; height: 14px; }

/* List view adjustments */
.mp-products.list-view .mp-card { flex-direction: row; max-height: 160px; }
.mp-products.list-view .mp-card-preview { aspect-ratio: 4/3; width: 200px; flex-shrink: 0; border-radius: var(--mp-r-lg) 0 0 var(--mp-r-lg); overflow: hidden; }
.mp-products.list-view .mp-card-actions { flex-direction: column; bottom: auto; right: 12px; top: 50%; left: auto; transform: translateX(20px) translateY(-50%); background: none; width: auto; }
.mp-products.list-view .mp-card:hover .mp-card-actions { transform: translateX(0) translateY(-50%); }

/* ════════════════════════════════════════════════════════
   EMPTY STATE
════════════════════════════════════════════════════════ */
.mp-empty { grid-column: 1/-1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 80px 20px; text-align: center; }
.mp-empty-icon { width: 80px; height: 80px; border-radius: var(--mp-r-xl); background: var(--mp-indigo-50); border: 1.5px solid var(--mp-indigo-100); display: flex; align-items: center; justify-content: center; }
.mp-empty-icon svg, .mp-empty-icon i { width: 36px; height: 36px; color: var(--mp-indigo-500); }
.mp-empty h3 { font-size: 1.2rem; font-weight: 800; color: var(--mp-gray-900); letter-spacing: -.02em; }
.mp-empty p { font-size: .88rem; color: var(--mp-gray-400); max-width: 36ch; line-height: 1.65; }

/* ════════════════════════════════════════════════════════
   SIDEBAR COMPONENTS
════════════════════════════════════════════════════════ */
.mp-section-title { font-size: .9rem; font-weight: 700; color: var(--mp-gray-900); margin-bottom: 14px; }

.mp-mini-purchase-card,
.mp-mini-recommend-card,
.mp-mini-view-card {
  display: flex; align-items: center; gap: 12px;
  cursor: pointer;
  padding: 8px;
  border-radius: var(--mp-r-md);
  transition: background var(--mp-dur-fast) ease;
}
.mp-mini-purchase-card:hover,
.mp-mini-recommend-card:hover,
.mp-mini-view-card:hover { background: var(--mp-gray-50); }

/* ════════════════════════════════════════════════════════
   OVERLAYS / MODALS — Unified system
════════════════════════════════════════════════════════ */
.mp-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,.38);
  backdrop-filter: blur(12px) saturate(160%);
  -webkit-backdrop-filter: blur(12px) saturate(160%);
  z-index: 9000;
  display: flex; align-items: flex-start; justify-content: center;
  padding: 48px 24px;
  opacity: 0; pointer-events: none;
  transition: opacity .35s ease;
  overflow-y: auto;
}
.mp-overlay.open { opacity: 1; pointer-events: auto; }

/* ── Detail Modal ──────────────────────────────────────── */
.mp-detail-modal {
  width: 100%; max-width: 1100px;
  margin: auto;
  background: var(--mp-white);
  border-radius: var(--mp-r-2xl);
  display: grid;
  grid-template-columns: 1.15fr 0.85fr;
  grid-template-rows: 1fr auto;
  grid-template-areas: "media sidebar" "bottom bottom";
  overflow: hidden;
  box-shadow: var(--mp-shadow-xl);
  transform: translateY(28px) scale(.97);
  transition: transform .45s var(--mp-ease-out);
}
.mp-overlay.open .mp-detail-modal { transform: translateY(0) scale(1); }

/* Detail left — media */
.mp-detail-modal-left {
  grid-area: media;
  display: flex; flex-direction: column;
  background: var(--mp-gray-50);
  border-right: 1px solid var(--mp-gray-100);
  overflow: hidden;
}
.mp-detail-hero-media {
  flex: 1; min-height: 340px;
  position: relative; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: var(--mp-gray-100);
}
.mp-detail-hero-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
.mp-detail-media-placeholder { display: flex; flex-direction: column; align-items: center; gap: 12px; color: var(--mp-gray-300); }
.mp-detail-media-placeholder svg, .mp-detail-media-placeholder i { width: 52px; height: 52px; }
.mp-detail-media-placeholder span { font-size: .82rem; font-weight: 500; }

.mp-detail-gallery-rail {
  display: flex; gap: 10px;
  padding: 14px 18px;
  background: var(--mp-white);
  border-top: 1px solid var(--mp-gray-100);
  overflow-x: auto; scrollbar-width: none;
  min-height: 80px; align-items: center;
}
.mp-detail-gallery-rail::-webkit-scrollbar { display: none; }

.mp-gallery-thumb {
  width: 68px; height: 68px;
  border-radius: var(--mp-r-md);
  border: 2px solid var(--mp-gray-200);
  background: var(--mp-gray-50);
  overflow: hidden; cursor: pointer; flex-shrink: 0; padding: 0;
  transition: border-color .2s ease, transform .2s var(--mp-ease), box-shadow .2s ease;
}
.mp-gallery-thumb:hover { transform: scale(1.06); }
.mp-gallery-thumb.active { border-color: var(--mp-indigo-500); box-shadow: 0 0 0 3px rgba(99,102,241,.18); }
.mp-gallery-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* Detail right — sidebar */
.mp-detail-modal-right {
  grid-area: sidebar;
  display: flex; flex-direction: column;
  padding: 32px 28px;
  overflow-y: auto; position: relative;
  background: var(--mp-white); gap: 18px;
}
.mp-detail-modal-right::-webkit-scrollbar { width: 5px; }
.mp-detail-modal-right::-webkit-scrollbar-thumb { background: var(--mp-gray-200); border-radius: var(--mp-r-full); }

/* Close button */
.mp-modal-close {
  position: absolute; top: 18px; right: 18px;
  width: 34px; height: 34px;
  border-radius: var(--mp-r-md);
  border: 1.5px solid var(--mp-gray-200);
  background: var(--mp-white); color: var(--mp-gray-500);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  z-index: 10; box-shadow: var(--mp-shadow-xs);
  transition: background .2s ease, color .2s ease, border-color .2s ease, transform .2s var(--mp-ease);
}
.mp-modal-close:hover { background: #fff1f2; border-color: #fecdd3; color: var(--mp-rose-500); transform: rotate(90deg); }
.mp-modal-close svg, .mp-modal-close i { width: 15px; height: 15px; }

/* Info header */
.mp-detail-info-header { padding-right: 40px; }
.mp-detail-badge {
  display: inline-block; padding: 4px 12px;
  background: var(--mp-indigo-50); color: var(--mp-indigo-600);
  border: 1px solid var(--mp-indigo-100); border-radius: var(--mp-r-full);
  font-size: .65rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; margin-bottom: 10px;
}

/* ── Video Field Styles ────────────────────────────────────── */
.mp-video-preview-container {
  margin-top: 12px;
  border-radius: var(--mp-r-md);
  overflow: hidden;
  background: var(--mp-gray-900);
  aspect-ratio: 16/9;
  display: none;
  border: 1px solid var(--mp-gray-200);
  position: relative;
}
.mp-video-preview-container.visible { display: block; }
.mp-video-preview-container video { width: 100%; height: 100%; display: block; }

.mp-video-type-toggle {
  display: flex;
  background: var(--mp-gray-100);
  padding: 4px;
  border-radius: var(--mp-r-md);
  margin-bottom: 12px;
  gap: 4px;
}
.mp-video-type-btn {
  flex: 1;
  border: none;
  background: none;
  padding: 6px;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--mp-gray-500);
  border-radius: var(--mp-r-sm);
  cursor: pointer;
  transition: all 0.2s ease;
}
.mp-video-type-btn.active {
  background: var(--mp-white);
  color: var(--mp-indigo-600);
  box-shadow: var(--mp-shadow-xs);
}
.mp-detail-main-title { font-size: 1.4rem; font-weight: 800; color: var(--mp-gray-900); line-height: 1.3; letter-spacing: -.025em; margin: 0; }

/* Price box */
.mp-detail-price-box {
  padding: 16px 18px;
  background: var(--mp-gray-50); border: 1px solid var(--mp-gray-100); border-radius: var(--mp-r-lg);
}
.mp-detail-price-label { font-size: .65rem; text-transform: uppercase; letter-spacing: .12em; font-weight: 700; color: var(--mp-gray-400); margin-bottom: 6px; }
.mp-detail-price-tag { font-size: 2.5rem; font-weight: 900; color: var(--mp-gray-900); letter-spacing: -.05em; line-height: 1; margin-bottom: 8px; }

/* Seller strip */
.mp-detail-seller-strip {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px;
  background: var(--mp-gray-50); border: 1px solid var(--mp-gray-100); border-radius: var(--mp-r-lg);
}
.mp-detail-seller-avatar { width: 42px; height: 42px; border-radius: var(--mp-r-md); object-fit: cover; border: 2px solid var(--mp-gray-200); flex-shrink: 0; }

/* Action stack */
.mp-detail-actions-stack { display: flex; flex-direction: column; gap: 10px; margin-top: auto; }

.mp-main-buy-btn {
  width: 100%; padding: 16px; border-radius: var(--mp-r-lg);
  background: var(--mp-indigo-600); color: var(--mp-white);
  font-family: 'Poppins', sans-serif; font-size: .95rem; font-weight: 800;
  border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 10px;
  box-shadow: var(--mp-shadow-brand); letter-spacing: .01em;
  position: relative; overflow: hidden;
  transition: background var(--mp-dur-fast) ease, transform var(--mp-dur-fast) var(--mp-ease), box-shadow var(--mp-dur-fast) ease;
}
.mp-main-buy-btn::before {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,.18) 50%, transparent 100%);
  transform: translateX(-100%); transition: transform .6s ease;
}
.mp-main-buy-btn:hover::before { transform: translateX(100%); }
.mp-main-buy-btn:hover { background: var(--mp-indigo-700); transform: translateY(-2px); box-shadow: 0 12px 32px rgba(99,102,241,.35); }
.mp-main-buy-btn:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }
.mp-main-buy-btn svg, .mp-main-buy-btn i { width: 18px; height: 18px; }

.mp-secondary-btn {
  flex: 1; padding: 12px 16px; border-radius: var(--mp-r-lg);
  background: var(--mp-white); color: var(--mp-gray-700);
  border: 1.5px solid var(--mp-gray-200);
  font-family: 'Poppins', sans-serif; font-size: .82rem; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all var(--mp-dur-fast) var(--mp-ease); box-shadow: var(--mp-shadow-xs);
}
.mp-secondary-btn:hover {
  background: var(--mp-indigo-50); border-color: var(--mp-indigo-200);
  color: var(--mp-indigo-600); transform: translateY(-1px); box-shadow: var(--mp-shadow-sm);
}

/* Trust badges */
.mp-trust-badges { display: flex; flex-direction: column; gap: 7px; padding-top: 18px; border-top: 1px solid var(--mp-gray-100); }
.mp-trust-badge { display: flex; align-items: center; gap: 9px; font-size: .75rem; color: var(--mp-gray-500); font-weight: 500; }
.mp-trust-badge svg, .mp-trust-badge i { width: 15px; height: 15px; color: var(--mp-emerald-500); flex-shrink: 0; }

/* Detail bottom — tabs */
.mp-detail-bottom { grid-area: bottom; border-top: 1px solid var(--mp-gray-100); background: var(--mp-white); }

.mp-detail-tabs-nav {
  display: flex; gap: 0;
  padding: 0 32px;
  border-bottom: 1px solid var(--mp-gray-100);
  background: var(--mp-white);
}
.mp-detail-tab-btn {
  padding: 16px 20px;
  font-family: 'Poppins', sans-serif;
  font-size: .8rem; font-weight: 600;
  color: var(--mp-gray-400); border: none; background: none;
  cursor: pointer; border-bottom: 2px solid transparent;
  transition: color var(--mp-dur-fast) ease;
  position: relative;
}
.mp-detail-tab-btn::after {
  content: ''; position: absolute; bottom: -1px; left: 0; right: 0;
  height: 2px; background: var(--mp-indigo-500);
  transform: scaleX(0); transition: transform .25s var(--mp-ease); transform-origin: center;
}
.mp-detail-tab-btn:hover { color: var(--mp-gray-700); }
.mp-detail-tab-btn.active { color: var(--mp-indigo-600); }
.mp-detail-tab-btn.active::after { transform: scaleX(1); }

.mp-detail-content-area { padding: 28px 32px 36px; min-height: 200px; }
.mp-detail-tab-content { display: none; }
.mp-detail-tab-content.active { 
  display: block; 
  animation: mpFadeUp .3s var(--mp-ease) forwards;
}

@keyframes mpFadeUp {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ── Overview cards (JS-rendered) ── */
.mp-detail-overview-card {
  background: var(--mp-white); border: 1.5px solid var(--mp-gray-100);
  border-radius: var(--mp-r-xl); padding: 20px 22px; margin-bottom: 14px;
  transition: border-color .2s ease, box-shadow .2s ease;
}
.mp-detail-overview-card:hover { border-color: rgba(99,102,241,.2); box-shadow: var(--mp-shadow-sm); }
.mp-detail-overview-head { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px; gap: 12px; }
.mp-detail-overview-head h3 { font-size: 1rem; font-weight: 700; color: var(--mp-gray-900); margin: 0; }
.mp-detail-overview-kicker { font-size: .65rem; text-transform: uppercase; letter-spacing: .12em; font-weight: 700; color: var(--mp-indigo-500); display: block; margin-bottom: 5px; }
.mp-detail-overview-stat { font-size: .73rem; font-weight: 600; color: var(--mp-gray-400); white-space: nowrap; }
.mp-detail-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 9px; }
.mp-detail-list li { display: flex; align-items: flex-start; gap: 9px; font-size: .85rem; color: #4b5563; line-height: 1.6; }
.mp-detail-list li svg, .mp-detail-list li i { width: 14px; height: 14px; color: var(--mp-emerald-500); flex-shrink: 0; margin-top: 3px; }
.mp-detail-overview-split { display: grid; grid-template-columns: auto 1fr; gap: 22px; align-items: start; }
.mp-detail-seller-mini { display: flex; align-items: center; gap: 12px; }
.mp-detail-seller-mini img { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; border: 2px solid var(--mp-gray-100); }
.mp-detail-seller-mini strong { display: block; font-size: .9rem; font-weight: 700; color: var(--mp-gray-900); margin-bottom: 2px; }
.mp-detail-seller-mini p { font-size: .73rem; color: var(--mp-gray-400); margin: 0; }
.mp-detail-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 11px; }
.mp-detail-meta-grid > div span { display: block; font-size: .62rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: var(--mp-gray-400); margin-bottom: 3px; }
.mp-detail-meta-grid > div strong { font-size: .85rem; font-weight: 600; color: #1f2937; }
.mp-detail-copy { font-size: .85rem; color: var(--mp-gray-500); line-height: 1.75; margin: 0; }

/* ── Reviews panel ── */
.mp-review-summary { display: flex; align-items: center; gap: 22px; padding: 18px; background: var(--mp-indigo-50); border: 1px solid var(--mp-indigo-100); border-radius: var(--mp-r-lg); margin-bottom: 18px; }
.mp-review-score { font-size: 2.8rem; font-weight: 900; color: var(--mp-indigo-600); letter-spacing: -.05em; line-height: 1; }
.mp-review-count { font-size: .8rem; font-weight: 600; color: #4b5563; margin-bottom: 5px; }
.mp-review-note { font-size: .75rem; color: var(--mp-gray-400); line-height: 1.6; }
.mp-review-empty { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 10px; padding: 28px 16px; color: var(--mp-gray-400); }
.mp-review-empty svg, .mp-review-empty i { width: 34px; height: 34px; opacity: .4; }
.mp-review-empty h4 { font-size: .9rem; font-weight: 600; color: #4b5563; margin: 0; }
.mp-review-empty p { font-size: .78rem; color: var(--mp-gray-400); max-width: 30ch; line-height: 1.65; margin: 0; }

/* ── Related grid ── */
.mp-related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(195px, 1fr)); gap: 14px; }
.mp-related-card { display: flex; flex-direction: column; border-radius: var(--mp-r-lg); border: 1.5px solid var(--mp-gray-100); background: var(--mp-white); overflow: hidden; cursor: pointer; text-align: left; transition: all .28s var(--mp-ease); box-shadow: var(--mp-shadow-xs); }
.mp-related-card:hover { transform: translateY(-4px); box-shadow: var(--mp-shadow-md); border-color: rgba(99,102,241,.2); }
.mp-related-card img { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; transition: transform .5s ease; }
.mp-related-card:hover img { transform: scale(1.05); }
.mp-related-card-placeholder { width: 100%; aspect-ratio: 16/9; background: var(--mp-gray-50); display: flex; align-items: center; justify-content: center; color: var(--mp-gray-300); }
.mp-related-card-copy { padding: 10px 13px; }
.mp-related-card-cat { display: block; font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--mp-indigo-500); margin-bottom: 4px; }
.mp-related-card-copy strong { display: block; font-size: .82rem; font-weight: 700; color: var(--mp-gray-900); margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mp-related-card-meta { display: flex; justify-content: space-between; align-items: center; font-size: .7rem; color: var(--mp-gray-400); }
.mp-related-card-meta span:first-child { font-weight: 700; color: var(--mp-indigo-600); }

/* ── Fulfillment panel ── */
.mp-fulfillment-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.mp-fulfillment-kicker { font-size: .62rem; text-transform: uppercase; font-weight: 800; color: var(--mp-gray-400); letter-spacing: .1em; margin-bottom: 3px; }
.mp-fulfillment-head strong { font-size: .9rem; color: #1f2937; }
.mp-fulfillment-status { padding: 4px 12px; border-radius: var(--mp-r-full); font-size: .7rem; font-weight: 800; letter-spacing: .04em; white-space: nowrap; }
.mp-fulfillment-status.instant, .mp-fulfillment-status.delivered { background: #d1fae5; color: #059669; }
.mp-fulfillment-status.processing { background: #fef3c7; color: #d97706; }
.mp-fulfillment-status.shipped, .mp-fulfillment-status.shipping { background: #dbeafe; color: #2563eb; }
.mp-fulfillment-status.pickup { background: #f3e8ff; color: #7c3aed; }
.mp-fulfillment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
.mp-fulfillment-grid > div { background: var(--mp-gray-50); padding: 10px 13px; border-radius: var(--mp-r-md); border: 1px solid var(--mp-gray-100); }
.mp-fulfillment-grid span { display: block; font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--mp-gray-400); margin-bottom: 3px; }
.mp-fulfillment-grid strong { font-size: .83rem; font-weight: 700; color: #1f2937; }
.mp-mini-map { display: flex; gap: 12px; padding: 14px 16px; background: var(--mp-indigo-50); border: 1px solid var(--mp-indigo-100); border-radius: var(--mp-r-lg); align-items: flex-start; margin-bottom: 14px; }
.mp-mini-map i, .mp-mini-map svg { color: var(--mp-indigo-500); width: 15px; height: 15px; flex-shrink: 0; margin-top: 2px; }
.mp-mini-map strong { display: block; font-size: .83rem; font-weight: 700; color: #1f2937; margin-bottom: 3px; }
.mp-mini-map p { font-size: .75rem; color: var(--mp-gray-500); margin: 0; line-height: 1.55; }
.mp-fulfillment-foot { display: flex; flex-wrap: wrap; gap: 8px; font-size: .7rem; color: var(--mp-gray-400); font-weight: 600; }
.mp-fulfillment-foot > span { padding: 3px 10px; background: var(--mp-gray-50); border: 1px solid var(--mp-gray-200); border-radius: var(--mp-r-full); }

.mp-inline-empty { padding: 24px 16px; text-align: center; font-size: .82rem; color: var(--mp-gray-400); border-radius: var(--mp-r-lg); background: var(--mp-gray-50); border: 1px dashed var(--mp-gray-200); }

/* ════════════════════════════════════════════════════════
   CREATE / EDIT LISTING MODAL
════════════════════════════════════════════════════════ */
.uf-overlay {
  position: fixed; inset: 0;
  background: rgba(15,23,42,.45);
  backdrop-filter: blur(12px) saturate(160%);
  -webkit-backdrop-filter: blur(12px) saturate(160%);
  z-index: 9500;
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
  opacity: 0; pointer-events: none;
  transition: opacity .3s ease;
}
.uf-overlay.open { opacity: 1; pointer-events: auto; }
.uf-overlay .uf-card { transform: translateY(24px) scale(.97); transition: transform .45s var(--mp-ease-out); }
.uf-overlay.open .uf-card { transform: translateY(0) scale(1); }

/* ── Upload areas ── */
.mp-upload-area {
  border: 2px dashed var(--mp-gray-200);
  border-radius: var(--mp-r-lg);
  padding: 24px; text-align: center;
  cursor: pointer; position: relative; overflow: hidden;
  background: var(--mp-gray-50);
  transition: border-color var(--mp-dur-fast) ease, background var(--mp-dur-fast) ease;
}
.mp-upload-area:hover, .mp-upload-area.drag-over {
  border-color: rgba(99,102,241,.5);
  background: rgba(99,102,241,.04);
}
.mp-upload-area input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.mp-upload-icon { width: 44px; height: 44px; border-radius: 14px; background: rgba(99,102,241,.12); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; }
.mp-upload-icon svg, .mp-upload-icon i { width: 22px; height: 22px; color: var(--mp-indigo-600); }
.mp-upload-title { font-size: .85rem; font-weight: 700; color: var(--mp-gray-700); margin-bottom: 4px; }
.mp-upload-sub { font-size: .75rem; color: var(--mp-gray-400); }
.mp-upload-preview { margin-top: 12px; border-radius: 10px; overflow: hidden; max-height: 160px; display: none; }
.mp-upload-preview img { width: 100%; height: 160px; object-fit: cover; border-radius: 10px; }
.mp-upload-preview.visible { display: block; }

/* ── Form error states ── */
.mp-field-err { font-size: .72rem; color: var(--mp-rose-500); font-weight: 500; display: none; margin-top: 4px; }
.uf-group.is-invalid .mp-field-err { display: block; }
.uf-group.is-invalid .uf-input { border-color: rgba(244,63,94,.55) !important; }

/* ── Management Section & Danger states ── */
#manageListingSection { animation: slideUp .4s ease; }
.mp-action-btn.danger { background: rgba(225,29,72,.1); color: var(--mp-rose-600); }
.mp-action-btn.danger:hover { background: var(--mp-rose-600); color: #fff; }
.mp-secondary-btn#deleteListingBtn:hover { background: var(--mp-rose-600) !important; color: #fff !important; }

/* ── Form input stability — prevent text disappearing ── */
.uf-input,
.uf-input:focus,
.uf-input:active,
.uf-input:hover {
  color: #0f172a !important;
  opacity: 1 !important;
  -webkit-text-fill-color: #0f172a !important;
}
.uf-input::placeholder { color: transparent !important; -webkit-text-fill-color: transparent !important; }
.uf-input:-webkit-autofill,
.uf-input:-webkit-autofill:focus {
  -webkit-text-fill-color: #0f172a !important;
  transition: background-color 9999s ease-in-out 0s;
}
.uf-select, .uf-select:focus { color: #0f172a !important; opacity: 1 !important; }

/* ── Admin image edit overlay ── */
.mp-admin-edit-badge {
  position: absolute; bottom: 12px; right: 12px;
  background: rgba(37,99,235,.85); color: #fff;
  font-size: .68rem; font-weight: 700;
  padding: 5px 12px; border-radius: 999px;
  display: flex; align-items: center; gap: 5px;
  pointer-events: none; opacity: 0;
  transition: opacity .25s ease;
  backdrop-filter: blur(6px);
  z-index: 5;
}
.mp-detail-hero-media:hover .mp-admin-edit-badge { opacity: 1; }
.mp-detail-hero-media.admin-editable { cursor: pointer; }

/* Media editor modal */
.mp-media-editor-card { max-width: 580px !important; }
.mp-media-current-preview { border-radius: var(--mp-r-lg); overflow: hidden; border: 1.5px solid var(--mp-gray-100); max-height: 200px; display: flex; align-items: center; justify-content: center; background: var(--mp-gray-50); }
.mp-media-current-preview.is-empty { min-height: 80px; }
.mp-media-current-preview img { width: 100%; height: 200px; object-fit: cover; }

/* ── Steps indicator ── */
.mp-steps { display: flex; gap: 0; margin-bottom: 20px; }
.mp-step { flex: 1; display: flex; align-items: center; gap: 8px; font-size: .75rem; font-weight: 600; color: var(--mp-gray-400); position: relative; }
.mp-step::after { content: ''; flex: 1; height: 1px; background: var(--mp-gray-200); margin: 0 8px; }
.mp-step:last-child::after { display: none; }
.mp-step-num { width: 24px; height: 24px; border-radius: 50%; background: var(--mp-gray-100); border: 1px solid var(--mp-gray-200); display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 800; flex-shrink: 0; }
.mp-step.active { color: var(--mp-indigo-600); }
.mp-step.active .mp-step-num { background: var(--mp-indigo-50); border-color: var(--mp-indigo-200); color: var(--mp-indigo-700); }

/* ════════════════════════════════════════════════════════
   TOAST NOTIFICATIONS
════════════════════════════════════════════════════════ */
.mp-toast-stack { position: fixed; bottom: 28px; right: 28px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; max-width: 340px; }
.mp-toast {
  padding: 14px 18px; border-radius: var(--mp-r-lg);
  background: var(--mp-white); border: 1.5px solid var(--mp-gray-200);
  box-shadow: var(--mp-shadow-xl); font-size: .82rem; font-weight: 600;
  color: #1f2937; display: flex; align-items: center; gap: 10px;
  pointer-events: auto;
  animation: mp-toast-in .4s var(--mp-ease-out) both;
}
.mp-toast.out { animation: mp-toast-out .3s ease forwards; }
.mp-toast svg, .mp-toast i { width: 16px; height: 16px; flex-shrink: 0; }
.mp-toast.success { border-color: #d1fae5; }
.mp-toast.success svg, .mp-toast.success i { color: var(--mp-emerald-500); }
.mp-toast.error { border-color: #fee2e2; }
.mp-toast.error svg, .mp-toast.error i { color: var(--mp-rose-500); }
.mp-toast.info { border-color: var(--mp-indigo-100); }
.mp-toast.info svg, .mp-toast.info i { color: var(--mp-indigo-500); }
@keyframes mp-toast-in { from { opacity: 0; transform: translateY(12px) scale(.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes mp-toast-out { to { opacity: 0; transform: translateX(16px) scale(.95); } }

/* ════════════════════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════════════════════ */
@media (max-width: 1100px) {
  .mp-hero { grid-template-columns: 1fr 1fr; }
  .mp-hero .mp-hero-right { grid-column: span 2; flex-direction: row; flex-wrap: wrap; }
}

@media (max-width: 900px) {
  .container.profile-page-layout { grid-template-columns: 1fr; }
  .marketplace-side-col { display: none; }
  .mp-hero { grid-template-columns: 1fr; padding: 28px 22px; }
  .mp-detail-modal { grid-template-columns: 1fr; grid-template-areas: "media" "sidebar" "bottom"; }
  .mp-detail-modal-left { border-right: none; border-bottom: 1px solid var(--mp-gray-100); }
  .mp-detail-modal-right { padding: 22px 20px; }
  .mp-detail-tabs-nav { padding: 0 20px; }
  .mp-detail-content-area { padding: 20px 20px 30px; }
  .mp-detail-overview-split { grid-template-columns: 1fr; }
}

@media (max-width: 640px) {
  .mp-search-bar { flex-direction: column; align-items: stretch; gap: 10px; padding: 14px; border-radius: var(--mp-r-lg); }
  .mp-search-bar .mp-select-wrap { padding-left: 0; border-left: none; border-top: 1px solid var(--mp-gray-100); padding-top: 10px; margin-left: 0; }
  .mp-search-btn { width: 100%; justify-content: center; }
  .mp-products { grid-template-columns: 1fr; }
  .mp-hero-title { font-size: 2rem; }
}

/* ════════════════════════════════════════════════════════
   SKELETON SHIMMER (loading state)
════════════════════════════════════════════════════════ */
@keyframes mp-shimmer { 0% { background-position: -600px 0; } 100% { background-position: 600px 0; } }
.mp-skeleton { background: linear-gradient(90deg, var(--mp-gray-100) 25%, var(--mp-gray-50) 50%, var(--mp-gray-100) 75%); background-size: 1200px 100%; animation: mp-shimmer 1.4s infinite linear; border-radius: var(--mp-r-md); }
/* ── Review Panel ────────────────────────────────── */
.mp-review-summary {
  display: flex; align-items: center; justify-content: space-between;
  background: var(--mp-white); padding: 22px 24px;
  border-radius: var(--mp-r-xl); border: 1.5px solid var(--mp-gray-100);
  margin-bottom: 24px; box-shadow: var(--mp-shadow-sm);
}
.mp-review-score { font-size: 2.2rem; font-weight: 800; color: var(--mp-indigo-600); line-height: 1; letter-spacing: -1px; }
.mp-review-count { font-size: .82rem; font-weight: 600; color: var(--mp-gray-400); margin-top: 4px; }
.mp-review-note { font-size: .78rem; color: var(--mp-gray-400); max-width: 20ch; text-align: right; line-height: 1.5; }

.mp-review-form-card {
  background: var(--mp-indigo-50); padding: 24px;
  border-radius: var(--mp-r-xl); border: 1.5px solid var(--mp-indigo-100);
  margin-bottom: 24px;
}
.mp-review-form-card h4 { font-size: .95rem; font-weight: 700; color: var(--mp-indigo-900); margin-bottom: 16px; }

.mp-star-rating {
  display: flex; flex-direction: row-reverse; justify-content: flex-end;
  gap: 6px; margin-bottom: 16px;
}
.mp-star-rating input { display: none; }
.mp-star-rating label { cursor: pointer; color: var(--mp-gray-300); transition: color .15s ease; }
.mp-star-rating label i { width: 22px; height: 22px; fill: currentColor; }
.mp-star-rating input:checked ~ label,
.mp-star-rating label:hover,
.mp-star-rating label:hover ~ label { color: var(--mp-amber-400); }

.mp-review-list { display: flex; flex-direction: column; gap: 16px; }
.mp-review-item {
  background: var(--mp-white); padding: 18px 20px;
  border-radius: var(--mp-r-lg); border: 1.5px solid var(--mp-gray-100);
  transition: transform .2s ease;
}
.mp-review-item:hover { transform: translateX(4px); border-color: var(--mp-gray-200); }
.mp-review-item-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.mp-review-item-head img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid var(--mp-indigo-50); }
.mp-review-item-head strong { font-size: .85rem; font-weight: 700; color: var(--mp-gray-900); display: block; }
.mp-review-item-date { font-size: .7rem; color: var(--mp-gray-400); font-weight: 500; }
.mp-review-item-rating { margin-left: auto; color: var(--mp-amber-400); font-size: .8rem; letter-spacing: 1px; }
.mp-review-item-comment { font-size: .84rem; color: var(--mp-gray-600); line-height: 1.6; }

.mp-review-empty, .mp-review-loading {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 60px 24px; text-align: center; color: var(--mp-gray-400);
}
.mp-review-empty i, .mp-review-loading i { width: 42px; height: 42px; margin-bottom: 16px; opacity: .4; }
.mp-review-empty h4 { font-size: 1rem; font-weight: 700; color: var(--mp-gray-900); margin-bottom: 8px; }
.mp-review-empty p { font-size: .82rem; line-height: 1.6; max-width: 30ch; }

.mp-spin { animation: mp-spin 1s linear infinite; }
@keyframes mp-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ── Related Items Loading ───────────────────────── */
.mp-related-loading { display: flex; align-items: center; justify-content: center; padding: 40px; color: var(--mp-gray-300); }

/* Double click indicator for admin */
.admin-editable { position: relative; cursor: pointer; }
.mp-admin-edit-badge {
  position: absolute; bottom: 12px; right: 12px;
  background: rgba(15,23,42,.75); color: #fff;
  padding: 6px 12px; border-radius: 99px;
  font-size: .65rem; font-weight: 600;
  backdrop-filter: blur(4px); display: flex; align-items: center; gap: 6px;
  opacity: 0; transition: opacity .2s; pointer-events: none;
}
.admin-editable:hover .mp-admin-edit-badge { opacity: 1; }
</style>

</head>
<body class="grid-dot-bg home-page-body with-global-left-sidebar" data-theme="light">
  <a class="skip-link" href="#profile-main-content">Skip to main content</a>
  <canvas id="gradient-canvas"></canvas>

  <!-- ── Navbar ─────────────────────────────────────── -->
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
            <div class="nav-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($sidebarDisplayName) ?></strong>
              <span><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="../BackOffice/dashboardUser.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

<!-- Global sidebar -->
<?php include __DIR__ . '/partials/global-sidebar.php'; ?>

<main class="profile-main" id="profile-main-content">
  <div class="container profile-page-layout">

    <!-- ══ MAIN COLUMN ══ -->
    <div class="marketplace-main-col">

      <!-- ── Hero ── -->
      <section class="mp-hero reveal-section">
        <div class="mp-hero-left">
          <h1 class="mp-hero-title">The Digital<br>Marketplace</h1>
          <p class="mp-hero-sub">Discover premium digital assets, services, and physical goods vetted for quality and professional use.</p>
          <div>
            <button class="mp-btn-primary" onclick="document.getElementById('mp-catalog').scrollIntoView({behavior:'smooth'})">
              Browse Catalog <i data-lucide="chevron-right"></i>
            </button>
          </div>
        </div>

        <div class="mp-hero-center">
          <?php if ($featuredItem): ?>
          <div class="mp-featured-card" onclick='openDetail(<?= json_encode($compactItem($featuredItem), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
            <?php if (!empty($featuredItem['thumbnail_url'])): ?>
              <img src="<?= htmlspecialchars($featuredItem['thumbnail_url']) ?>" alt="" class="mp-featured-media">
            <?php else: ?>
              <div class="mp-card-placeholder" style="aspect-ratio:16/9;"><i data-lucide="sparkles"></i></div>
            <?php endif; ?>
            <div class="mp-featured-info">
              <span class="mp-featured-tag">Featured</span>
              <h3 style="font-size:1.05rem;font-weight:800;margin-bottom:6px;"><?= htmlspecialchars($featuredItem['title']) ?></h3>
              <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-weight:800;color:var(--mp-indigo-600);">$<?= number_format($featuredItem['price'], 2) ?></span>
                <span style="font-size:.75rem;color:var(--mp-gray-400);">By <?= htmlspecialchars($featuredItem['first_name'] . ' ' . $featuredItem['last_name']) ?></span>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <div class="mp-hero-right">
          <button class="mp-hero-action-card" onclick="openCreateModal()">
            <div class="mp-action-icon"><i data-lucide="plus-circle"></i></div>
            <div class="mp-action-text"><strong>Sell an item</strong><span>List your products</span></div>
          </button>
          <a href="#mp-catalog" class="mp-hero-action-card">
            <div class="mp-action-icon"><i data-lucide="search"></i></div>
            <div class="mp-action-text"><strong>Browse all</strong><span>Explore categories</span></div>
          </a>
          <button class="mp-hero-action-card" onclick="openLastViewedItem()">
            <div class="mp-action-icon"><i data-lucide="history"></i></div>
            <div class="mp-action-text"><strong>Continue</strong><span>Resume browsing</span></div>
          </button>
        </div>
      </section>

      <!-- ── Search + Categories ── -->
      <section class="mp-search-section reveal-section">
        <form method="GET" action="marketplace.php" class="mp-search-bar" aria-label="Search marketplace">
          <div class="mp-search-field">
            <i data-lucide="search"></i>
            <input type="text" name="q" class="mp-search-input"
              placeholder="Search listings, sellers, or categories..."
              value="<?= htmlspecialchars($filters['search']) ?>" aria-label="Search query">
          </div>
          <div class="mp-select-wrap">
            <i data-lucide="filter"></i>
            <select name="category" class="mp-select" aria-label="Category filter">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= $filters['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="mp-search-btn">Search</button>
        </form>

        <nav class="mp-categories" aria-label="Category quick filters">
          <a href="marketplace.php" class="mp-cat-chip <?= $filters['category_id'] === '' ? 'active' : '' ?>">
            <i class="mp-cat-icon" data-lucide="grid-2x2"></i><span>All Items</span>
          </a>
          <?php foreach ($categories as $cat): ?>
            <a href="marketplace.php?category=<?= (int)$cat['id'] ?>" class="mp-cat-chip <?= $filters['category_id'] == $cat['id'] ? 'active' : '' ?>">
              <?php if (!empty($cat['icon'])): ?>
                <i class="mp-cat-icon" data-lucide="<?= htmlspecialchars($cat['icon']) ?>"></i>
              <?php else: ?>
                <i class="mp-cat-icon" data-lucide="tag"></i>
              <?php endif; ?>
              <span><?= htmlspecialchars($cat['name']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>

        <div class="mp-grid-header" id="mp-catalog">
          <div style="flex:1;">
            <div class="mp-grid-title">
              <?= ($filters['search'] !== '' || $filters['category_id'] !== '') ? 'Search Results' : 'Featured Listings' ?>
            </div>
            <div class="mp-grid-count"><?= count($items) ?> active listings</div>
          </div>
          <div class="mp-view-toggle" role="group" aria-label="View mode">
            <button class="mp-view-btn active" id="gridViewBtn" title="Grid view" type="button" aria-pressed="true">
              <i data-lucide="grid-2x2"></i>
            </button>
            <button class="mp-view-btn" id="listViewBtn" title="List view" type="button" aria-pressed="false">
              <i data-lucide="list"></i>
            </button>
          </div>
        </div>
      </section>

      <!-- ── Products Grid ── -->
      <div class="mp-products" id="mpGrid" role="list">
        <?php if (empty($items)): ?>
          <div class="mp-empty" role="listitem">
            <div class="mp-empty-icon"><i data-lucide="package-search"></i></div>
            <h3>Nothing here yet</h3>
            <p>Try adjusting your search or category filter, or be the first to list something amazing.</p>
            <button class="mp-btn-primary" onclick="openCreateModal()">
              <i data-lucide="plus"></i> Create First Listing
            </button>
          </div>
        <?php else: ?>
          <?php foreach ($items as $i => $item):
            $sellerName  = htmlspecialchars(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')));
            $sellerAvatar = $item['avatar_url'] ?? '';
            $initials    = strtoupper(substr($item['first_name'] ?? 'U', 0, 1) . substr($item['last_name'] ?? 'S', 0, 1));
            $ratingValue = (float) ($item['rating'] ?? 0);
            $reviewCount = (int) ($item['review_count'] ?? 0);
            $delay       = ($i % 12) * 60;
          ?>
          <div class="mp-card reveal-section" role="listitem"
               style="animation-delay:<?= $delay ?>ms"
               onclick='openDetail(<?= json_encode($compactItem($item), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>

            <div class="mp-card-preview">
              <?php if (!empty($item['thumbnail_url'])): ?>
                <img src="<?= htmlspecialchars($item['thumbnail_url']) ?>" alt="" loading="lazy">
              <?php else: ?>
                <div class="mp-card-placeholder"><i data-lucide="image"></i></div>
              <?php endif; ?>
              <div class="mp-price-tag">$<?= number_format($item['price'], 2) ?></div>
              <div class="mp-card-actions">
                <button class="mp-action-btn primary" title="Quick buy"
                  onclick="event.stopPropagation(); selectedItemId=<?= (int)$item['id'] ?>; initCheckout()">
                  <i data-lucide="shopping-cart"></i> Buy
                </button>
                <?php if ($isAdmin || (int)$item['user_id'] === $currentUserId): ?>
                <button class="mp-action-btn" title="Edit"
                  onclick="event.stopPropagation(); openEditModal(<?= json_encode($compactItem($item), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">
                  <i data-lucide="edit-3"></i>
                </button>
                <button class="mp-action-btn danger" title="Delete"
                  onclick="event.stopPropagation(); deleteListing(<?= (int)$item['id'] ?>)">
                  <i data-lucide="trash-2"></i>
                </button>
                <?php else: ?>
                <button class="mp-action-btn" title="Save"
                  onclick="event.stopPropagation(); saveItem(<?= (int)$item['id'] ?>)">
                  <i data-lucide="heart"></i>
                </button>
                <?php endif; ?>
              </div>
            </div>

            <div class="mp-card-body">
              <div class="mp-card-category"><?= htmlspecialchars($item['category_name'] ?? '') ?></div>
              <h3 class="mp-card-title"><?= htmlspecialchars($item['title']) ?></h3>
              <p class="mp-card-desc"><?= htmlspecialchars($item['description']) ?></p>
              <div class="mp-rating">
                <div class="mp-stars">★★★★★</div>
                <span class="mp-rating-val"><?= $ratingValue > 0 ? $ratingValue : '4.9' ?></span>
                <span class="mp-rating-count">(<?= $reviewCount > 0 ? $reviewCount : '12' ?>)</span>
              </div>
              <div class="mp-seller">
                <?php if ($sellerAvatar !== ''): ?>
                  <img src="<?= htmlspecialchars($sellerAvatar) ?>" alt="" class="mp-seller-avatar">
                <?php else: ?>
                  <div class="mp-seller-avatar-fallback"><?= $initials ?></div>
                <?php endif; ?>
                <div class="mp-seller-copy">
                  <span class="mp-seller-name"><?= $sellerName ?></span>
                  <span class="mp-seller-location"><?= htmlspecialchars($item['country'] ?? '') ?></span>
                </div>
                <div class="mp-verified" title="Verified Seller"><i data-lucide="check-circle"></i></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div><!-- /marketplace-main-col -->

    <!-- ══ SIDEBAR ══ -->
    <aside class="marketplace-side-col">

      <!-- Quick Actions -->
      <div class="glass-card panel">
        <h3 class="mp-section-title">Marketplace Actions</h3>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php if ($isAdmin): ?>
          <button class="mp-hero-action-card" onclick="openCreateModal()">
            <div class="mp-action-icon"><i data-lucide="plus-circle"></i></div>
            <div class="mp-action-text"><strong>Sell an item</strong><span>List your products</span></div>
          </button>
          <?php endif; ?>
          <a href="#mp-catalog" class="mp-hero-action-card">
            <div class="mp-action-icon"><i data-lucide="search"></i></div>
            <div class="mp-action-text"><strong>Browse all</strong><span>Explore categories</span></div>
          </a>
          <button class="mp-hero-action-card" onclick="openLastViewedItem()">
            <div class="mp-action-icon"><i data-lucide="history"></i></div>
            <div class="mp-action-text"><strong>Continue</strong><span>Resume browsing</span></div>
          </button>
        </div>
      </div>

      <!-- Recent Purchases -->
      <?php if (!empty($marketplaceOrders)): ?>
      <div class="glass-card panel">
        <h3 class="mp-section-title">Recent Purchases</h3>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach (array_slice($marketplaceOrders, 0, 5) as $order): ?>
          <div class="mp-mini-purchase-card" onclick='openDetail(<?= json_encode([
              "id" => (int)($order["item_id"] ?? 0),
              "title" => (string)($order["title"] ?? ""),
              "price" => (float)($order["amount"] ?? 0),
              "thumbnail_url" => (string)($order["thumbnail_url"] ?? ""),
              "category_name" => "Recent purchase",
              "type" => (string)($order["type"] ?? "digital")
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
            <img src="<?= htmlspecialchars($order['thumbnail_url'] ?: '../../assets/img/placeholder.png') ?>"
                 alt="" style="width:44px;height:44px;border-radius:8px;object-fit:cover;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
              <div style="font-size:.75rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($order['title']) ?></div>
              <div style="font-size:.65rem;color:var(--mp-gray-400);">Ordered <?= date('M d', strtotime($order['created_at'])) ?></div>
            </div>
            <i data-lucide="check-circle" style="width:14px;height:14px;color:var(--mp-emerald-500);flex-shrink:0;"></i>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="wallet.php" style="display:block;margin-top:14px;text-align:center;font-size:.75rem;color:var(--mp-indigo-600);text-decoration:none;font-weight:600;">View in Wallet</a>
      </div>
      <?php endif; ?>

      <!-- Recommendations -->
      <?php if (!empty($recommendedItems)): ?>
      <div class="glass-card panel">
        <h3 class="mp-section-title">Recommended</h3>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php foreach (array_slice($recommendedItems, 0, 4) as $item): ?>
          <div class="mp-mini-recommend-card" onclick='openDetail(<?= json_encode($compactItem($item), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
            <img src="<?= htmlspecialchars($item['thumbnail_url'] ?: '../../assets/img/placeholder.png') ?>"
                 alt="" style="width:44px;height:44px;border-radius:8px;object-fit:cover;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
              <div style="font-size:.75rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($item['title']) ?></div>
              <div style="font-size:.7rem;color:var(--mp-indigo-600);font-weight:700;">$<?= number_format($item['price'], 2) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Last Viewed -->
      <div class="glass-card panel" id="recently-viewed-sidebar" style="display:none;">
        <h3 class="mp-section-title">Last Viewed</h3>
        <div id="mpContinueSidebar" style="display:flex;flex-direction:column;gap:12px;"></div>
      </div>

    </aside>
  </div><!-- /container -->
</main>

<!-- ═══════════════════════════════════════════════════════
     DETAIL MODAL
════════════════════════════════════════════════════════ -->
<div class="mp-overlay" id="detailOverlay" role="dialog" aria-modal="true" aria-label="Product details"
     onclick="closeOverlay('detailOverlay')">
  <div class="mp-detail-modal" onclick="event.stopPropagation()">

    <!-- LEFT: Media -->
    <div class="mp-detail-modal-left">
      <div class="mp-detail-hero-media" id="detailMedia"></div>
      <div class="mp-detail-gallery-rail" id="detailGalleryRail"></div>
    </div>

    <!-- RIGHT: Info + Actions -->
    <div class="mp-detail-modal-right">
      <button class="mp-modal-close" onclick="closeOverlay('detailOverlay')" aria-label="Close">
        <i data-lucide="x"></i>
      </button>

      <div class="mp-detail-info-header">
        <div class="mp-detail-badge" id="detailCategoryBadge">Digital Asset</div>
        <h1 class="mp-detail-main-title" id="detailTitle">Product Title</h1>
      </div>

      <div class="mp-detail-price-box">
        <div class="mp-detail-price-label">Price</div>
        <div class="mp-detail-price-tag" id="detailPrice">$0.00</div>
        <p style="font-size:.75rem;color:var(--mp-gray-400);">Secure transaction via Stripe</p>
      </div>

      <div class="mp-detail-seller-strip">
        <img id="detailSellerAvatar" class="mp-detail-seller-avatar" src="../../assets/img/default-avatar.png" alt="">
        <div style="flex:1;">
          <div style="font-size:.9rem;font-weight:700;" id="detailSellerName">Seller Name</div>
          <div style="font-size:.75rem;color:var(--mp-gray-400);display:flex;align-items:center;gap:4px;">
            <i data-lucide="map-pin" style="width:12px;height:12px;"></i>
            <span id="detailSellerLocation">Location</span>
          </div>
        </div>
        <i data-lucide="check-circle" style="width:18px;height:18px;color:var(--mp-emerald-500);"></i>
      </div>

      <div id="detailFulfillmentPanel"></div>

      <div class="mp-detail-actions-stack">
        <button class="mp-main-buy-btn" id="buyBtn" onclick="initCheckout()">
          <i data-lucide="credit-card"></i> Purchase with Stripe
        </button>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <button class="mp-secondary-btn" id="saveBtn"><i data-lucide="bookmark"></i> Save</button>
          <button class="mp-secondary-btn" id="shareBtn"><i data-lucide="share-2"></i> Share</button>
        </div>
      </div>

      <div class="mp-trust-badges">
        <div class="mp-trust-badge"><i data-lucide="shield-check"></i> 100% Secure Transaction</div>
        <div class="mp-trust-badge"><i data-lucide="clock"></i> 24/7 Support for buyers</div>
      </div>

      <!-- Manage Listing (Owners/Admins) -->
      <div id="manageListingSection" style="display:none;margin-top:20px;padding-top:20px;border-top:1.5px solid var(--mp-gray-100);">
        <div style="font-size:.75rem;font-weight:700;color:var(--mp-gray-400);margin-bottom:12px;text-transform:uppercase;letter-spacing:.02em;">Listing Management</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <button class="mp-secondary-btn" id="editListingBtn" style="color:var(--mp-indigo-600);background:rgba(79,70,229,.05);">
            <i data-lucide="edit-3"></i> Edit Details
          </button>
          <button class="mp-secondary-btn" id="deleteListingBtn" style="color:var(--mp-rose-600);background:rgba(225,29,72,.05);">
            <i data-lucide="trash-2"></i> Delete Item
          </button>
        </div>
      </div>
    </div>

    <!-- BOTTOM: Tabs -->
    <div class="mp-detail-bottom">
      <div class="mp-detail-tabs-nav" role="tablist">
        <button class="mp-detail-tab-btn active" data-tab="overview" data-target="#tab-overview" role="tab" aria-selected="true">Overview</button>
        <button class="mp-detail-tab-btn" data-tab="reviews" data-target="#tab-reviews" role="tab" aria-selected="false">Reviews</button>
        <button class="mp-detail-tab-btn" data-tab="related" data-target="#tab-related" role="tab" aria-selected="false">Related Items</button>
      </div>
      <div class="mp-detail-content-area">
        <div class="mp-detail-tab-content active" id="tab-overview">
          <p id="detailDesc" style="font-size:.95rem;line-height:1.8;color:var(--mp-gray-500);"></p>
          <div id="detailOverviewStack" style="margin-top:20px;"></div>
        </div>
        <div class="mp-detail-tab-content" id="tab-reviews">
          <div id="detailReviewsPanel"></div>
        </div>
        <div class="mp-detail-tab-content" id="tab-related">
          <div id="detailRelatedGrid" class="mp-related-grid"></div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     CREATE LISTING MODAL
════════════════════════════════════════════════════════ -->
<div class="uf-overlay" id="createOverlay" role="dialog" aria-modal="true" aria-label="Create new listing"
     onclick="closeOverlay('createOverlay')">
  <div class="uf-card" onclick="event.stopPropagation()">
    <div class="uf-header">
      <div class="uf-header-left">
        <p class="uf-title">Create New Listing</p>
        <p class="uf-subtitle">Fill in the details to publish your asset.</p>
      </div>
      <button class="uf-close" onclick="closeOverlay('createOverlay')" aria-label="Close">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="uf-body">
      <form id="createListingForm" autocomplete="off" onsubmit="handleCreateSubmit(event)" novalidate>
        <div class="mp-steps" role="list">
          <div class="mp-step active" role="listitem"><div class="mp-step-num">1</div><span>Details</span></div>
          <div class="mp-step" role="listitem"><div class="mp-step-num">2</div><span>Pricing</span></div>
          <div class="mp-step" role="listitem"><div class="mp-step-num">3</div><span>Media</span></div>
        </div>
        <div class="uf-grid">
          <div class="uf-group uf-span-2" id="field-title">
            <label class="uf-label" for="lTitle">Title <span class="required">*</span></label>
            <input type="text" id="lTitle" name="title" class="uf-input" placeholder=" " maxlength="120">
            <span class="mp-field-err">Title is required (min 5 characters).</span>
          </div>
          <div class="uf-group uf-span-2 is-textarea" id="field-description">
            <label class="uf-label" for="lDesc">Description <span class="required">*</span></label>
            <textarea id="lDesc" name="description" class="uf-input uf-textarea" placeholder=" " rows="4" maxlength="1000"></textarea>
            <span class="mp-field-err">Description is required (min 20 characters).</span>
          </div>
          <div class="uf-group" id="field-price">
            <label class="uf-label" for="lPrice">Price (USD) <span class="required">*</span></label>
            <input type="number" id="lPrice" name="price" class="uf-input" placeholder=" " min="0.50" step="0.01">
            <span class="mp-field-err">Enter a valid price (min $0.50).</span>
          </div>
          <div class="uf-group">
            <label class="uf-label" for="lCategory">Category</label>
            <select id="lCategory" name="category_id" class="uf-input uf-select">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="uf-group uf-span-2">
            <label class="uf-label" for="lType">Product Type</label>
            <select id="lType" name="type" class="uf-input uf-select">
              <option value="digital">Digital</option>
              <option value="physical">Physical</option>
            </select>
          </div>
          <div class="uf-group uf-span-2" id="field-delivery">
            <label class="uf-label" for="lDelivery">Delivery Mode</label>
            <select id="lDelivery" name="delivery_option" class="uf-input uf-select">
              <option value="instant">Instant</option>
              <option value="shipping">Shipping</option>
              <option value="local_pickup">Local pickup</option>
            </select>
          </div>
          <div class="uf-group" id="field-delivery-time">
            <label class="uf-label" for="lDeliveryTime">Estimated delivery date</label>
            <input type="date" id="lDeliveryTime" name="estimated_delivery_time" class="uf-input">
            <span class="mp-field-err">Date must be in the future.</span>
          </div>
          <div class="uf-group" id="field-shipping-cost">
            <label class="uf-label" for="lShippingCost">Shipping cost (USD)</label>
            <input type="number" id="lShippingCost" name="shipping_cost" class="uf-input" placeholder="0.00" min="0" step="0.01">
          </div>
          <div class="uf-group uf-span-2" id="field-thumbnail">
            <label class="uf-label">Thumbnail Image <span class="required">*</span></label>
            <div class="mp-upload-area" id="thumbUploadArea">
              <input type="file" name="thumbnail" id="lThumbnail" accept="image/*" onchange="previewFile(this,'thumbPreview')">
              <div class="mp-upload-icon"><i data-lucide="image-plus"></i></div>
              <div class="mp-upload-title">Drop image here or click to upload</div>
              <div class="mp-upload-sub">PNG, JPG, WebP — max 8MB</div>
              <div class="mp-upload-preview" id="thumbPreview">
                <img id="thumbPreviewImg" src="" alt="Thumbnail preview">
              </div>
            </div>
            <span class="mp-field-err">Thumbnail image is required.</span>
          </div>

          <!-- Video Section -->
          <div class="uf-group uf-span-2" id="field-video">
            <label class="uf-label">Product Video (Optional)</label>
            <div class="mp-video-type-toggle">
              <button type="button" class="mp-video-type-btn active" onclick="switchVideoType('create', 'upload')">Upload File</button>
              <button type="button" class="mp-video-type-btn" onclick="switchVideoType('create', 'url')">Video URL</button>
              <button type="button" class="mp-video-type-btn" onclick="generateAISuggestedVideo()">AI Generation</button>
            </div>

            <div id="createVideoUploadSection">
              <div class="mp-upload-area" id="videoUploadArea">
                <input type="file" name="video" id="lVideo" accept="video/*" onchange="previewFile(this,'videoPreview')">
                <div class="mp-upload-icon"><i data-lucide="video"></i></div>
                <div class="mp-upload-title">Drop video here or click to upload</div>
                <div class="mp-upload-sub">MP4, WebM — max 50MB</div>
              </div>
            </div>

            <div id="createVideoUrlSection" style="display:none;">
              <input type="url" name="video_url" id="lVideoUrl" class="uf-input" placeholder="https://youtube.com/... or direct link" oninput="previewVideoUrl('create')">
            </div>

            <div class="mp-video-preview-container" id="videoPreview">
              <video id="videoPreviewEl" controls></video>
            </div>
          </div>
        </div>
      </form>
    </div>
    <div class="uf-actions">
      <button type="button" class="uf-btn uf-btn-ghost" onclick="closeOverlay('createOverlay')">Cancel</button>
      <button type="submit" form="createListingForm" class="uf-btn uf-btn-primary" id="submitListingBtn">
        <i data-lucide="send"></i> Publish Listing
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT LISTING MODAL
════════════════════════════════════════════════════════ -->
<div class="uf-overlay" id="editOverlay" role="dialog" aria-modal="true" aria-label="Edit listing"
     onclick="closeOverlay('editOverlay')">
  <div class="uf-card" onclick="event.stopPropagation()">
    <div class="uf-header">
      <div class="uf-header-left">
        <p class="uf-title">Edit Listing</p>
        <p class="uf-subtitle">Update your asset details.</p>
      </div>
      <button class="uf-close" onclick="closeOverlay('editOverlay')" aria-label="Close">
        <i data-lucide="x"></i>
      </button>
    </div>
    <div class="uf-body">
      <form id="editListingForm" autocomplete="off" onsubmit="handleEditSubmit(event)" novalidate>
        <input type="hidden" id="eItemId" name="item_id">
        <div class="uf-grid">
          <div class="uf-group uf-span-2" id="efield-title">
            <label class="uf-label" for="eTitle">Title <span class="required">*</span></label>
            <input type="text" id="eTitle" name="title" class="uf-input" placeholder=" " maxlength="120">
            <span class="mp-field-err">Title is required (min 5 characters).</span>
          </div>
          <div class="uf-group uf-span-2 is-textarea" id="efield-description">
            <label class="uf-label" for="eDesc">Description <span class="required">*</span></label>
            <textarea id="eDesc" name="description" class="uf-input uf-textarea" placeholder=" " rows="4" maxlength="1000"></textarea>
            <span class="mp-field-err">Description is required (min 20 characters).</span>
          </div>
          <div class="uf-group" id="efield-price">
            <label class="uf-label" for="ePrice">Price (USD) <span class="required">*</span></label>
            <input type="number" id="ePrice" name="price" class="uf-input" placeholder=" " min="0.50" step="0.01">
            <span class="mp-field-err">Enter a valid price (min $0.50).</span>
          </div>
          <div class="uf-group">
            <label class="uf-label" for="eCategory">Category</label>
            <select id="eCategory" name="category_id" class="uf-input uf-select">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="uf-group uf-span-2">
            <label class="uf-label" for="eType">Product Type</label>
            <select id="eType" name="type" class="uf-input uf-select">
              <option value="digital">Digital</option>
              <option value="physical">Physical</option>
            </select>
          </div>
          <div class="uf-group uf-span-2" id="efield-delivery">
            <label class="uf-label" for="eDelivery">Delivery Mode</label>
            <select id="eDelivery" name="delivery_option" class="uf-input uf-select">
              <option value="instant">Instant</option>
              <option value="shipping">Shipping</option>
              <option value="local_pickup">Local pickup</option>
            </select>
          </div>
          <div class="uf-group" id="efield-delivery-time">
            <label class="uf-label" for="eDeliveryTime">Estimated delivery date</label>
            <input type="date" id="eDeliveryTime" name="estimated_delivery_time" class="uf-input">
          </div>
          <div class="uf-group" id="efield-shipping-cost">
            <label class="uf-label" for="eShippingCost">Shipping cost (USD)</label>
            <input type="number" id="eShippingCost" name="shipping_cost" class="uf-input" placeholder="0.00" min="0" step="0.01">
          </div>

          <!-- Edit Video Section -->
          <div class="uf-group uf-span-2" id="efield-video">
            <label class="uf-label">Product Video (Optional)</label>
            <div class="mp-video-type-toggle">
              <button type="button" class="mp-video-type-btn active" id="btnEditVidUpload" onclick="switchVideoType('edit', 'upload')">Upload File</button>
              <button type="button" class="mp-video-type-btn" id="btnEditVidUrl" onclick="switchVideoType('edit', 'url')">Video URL</button>
            </div>

            <div id="editVideoUploadSection">
              <div class="mp-upload-area" id="editVideoUploadArea">
                <input type="file" name="video" id="eVideo" accept="video/*" onchange="previewFile(this,'editVideoPreview')">
                <div class="mp-upload-icon"><i data-lucide="video"></i></div>
                <div class="mp-upload-title">Drop new video or click to change</div>
                <div class="mp-upload-sub">MP4, WebM — max 50MB</div>
              </div>
            </div>

            <div id="editVideoUrlSection" style="display:none;">
              <input type="url" name="video_url" id="eVideoUrl" class="uf-input" placeholder="https://youtube.com/... or direct link" oninput="previewVideoUrl('edit')">
            </div>

            <div class="mp-video-preview-container" id="editVideoPreview">
              <video id="editVideoPreviewEl" controls></video>
            </div>
          </div>
        </div>
      </form>
    </div>
    <div class="uf-actions">
      <button type="button" class="uf-btn uf-btn-ghost" onclick="closeOverlay('editOverlay')">Cancel</button>
      <button type="submit" form="editListingForm" class="uf-btn uf-btn-primary" id="submitEditBtn">
        <i data-lucide="save"></i> Save Changes
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     MEDIA EDITOR MODAL
════════════════════════════════════════════════════════ -->
<div class="uf-overlay" id="mediaEditorOverlay" role="dialog" aria-modal="true" aria-label="Update listing media"
     onclick="closeOverlay('mediaEditorOverlay')">
  <div class="uf-card mp-media-editor-card" onclick="event.stopPropagation()">
    <div class="uf-header">
      <div class="uf-header-left">
        <p class="uf-title">Update Listing Media</p>
        <p class="uf-subtitle">Replace the thumbnail and review before saving.</p>
      </div>
      <button class="uf-close" onclick="closeOverlay('mediaEditorOverlay')" aria-label="Close"><i data-lucide="x"></i></button>
    </div>
    <div class="uf-body">
      <form id="mediaEditorForm" autocomplete="off" onsubmit="handleMediaEditSubmit(event)" novalidate enctype="multipart/form-data">
        <input type="hidden" name="item_id" id="mediaItemId">
        <div class="uf-grid">
          <div class="uf-group uf-span-2">
            <label class="uf-label">Current thumbnail</label>
            <div class="mp-media-current-preview"><img id="mediaCurrentThumb" src="" alt="Current thumbnail"></div>
          </div>
          <div class="uf-group uf-span-2">
            <label class="uf-label">New thumbnail</label>
            <div class="mp-upload-area">
              <input type="file" name="thumbnail" id="mediaThumbnailInput" accept="image/*" onchange="previewFile(this,'mediaThumbPreview')">
              <div class="mp-upload-icon"><i data-lucide="image-plus"></i></div>
              <div class="mp-upload-title">Drop image here or click to upload</div>
              <div class="mp-upload-sub">PNG, JPG, WebP — max 8MB</div>
              <div class="mp-upload-preview" id="mediaThumbPreview">
                <img id="mediaThumbPreviewImg" src="" alt="New thumbnail preview">
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
    <div class="uf-actions">
      <button type="button" class="uf-btn uf-btn-ghost" onclick="closeOverlay('mediaEditorOverlay')">Cancel</button>
      <button type="submit" form="mediaEditorForm" class="uf-btn uf-btn-primary" id="submitMediaBtn">
        <i data-lucide="save"></i> Save media
      </button>
    </div>
  </div>
</div>

<!-- Toast stack -->
<div class="mp-toast-stack" id="mpToastStack" aria-live="polite" aria-atomic="false"></div>

<!-- ═══════════════════════════════════════════════════════
     JAVASCRIPT — All marketplace logic in one block
════════════════════════════════════════════════════════ -->
<script>
(function () {
  'use strict';

  /* ── State ─────────────────────────────────────────── */
  window.selectedItemId = null;
  let isSubmitting   = false;

  const marketplaceData = {
    catalog: <?= json_encode($mpCatalogJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    featured: <?= json_encode($mpFeaturedJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    orders: <?= json_encode($mpOrdersJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    ownedItemIds: <?= json_encode($ownedItemIds, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    buyer: <?= json_encode([
      'country'        => $buyerCountry,
      'exact_location' => $buyerExactLocation,
      'latitude'       => $buyerLatitude,
      'longitude'      => $buyerLongitude,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  };

  const recentViewsKey = 'mp_recent_views_<?= (int) $currentUserId ?>';

  /* Build fast lookup maps */
  const catalogMap = {};
  const orderMap   = {};
  marketplaceData.catalog.forEach(item => { if (item && item.id != null) catalogMap[String(item.id)] = item; });
  marketplaceData.orders.forEach(order => {
    if (!order) return;
    const key = String(order.item_id || order.id || '');
    if (!key) return;
    const cur = orderMap[key];
    if (!cur || (Date.parse(order.created_at || '') || 0) > (Date.parse(cur.created_at || '') || 0)) orderMap[key] = order;
  });

  /* ── Init ──────────────────────────────────────────── */
  function initMarketplace() {
    try {
      if (window.lucide) lucide.createIcons();
      initScrollReveal();
      initViewToggle();
      initOverlayDismiss();
      initDragDrop();
      initDeliveryToggle();
      initRealTimeValidation();
      renderRecentViewsSidebar();
      initStaggeredReveal();
      initDetailTabs();
    } catch (err) {
      console.error('Marketplace Init Error:', err);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMarketplace);
  } else {
    initMarketplace();
  }

  /* ── Tab System ────────────────────────────────────── */
  function initDetailTabs() {
    const btns = document.querySelectorAll('.mp-detail-tab-btn');
    btns.forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        const tabId = btn.getAttribute('data-tab');
        if (tabId) switchDetailTab(tabId);
      });
    });
  }

  const switchDetailTab = window.switchDetailTab = function(tabId) {
    const overlay = document.getElementById('detailOverlay');
    if (!overlay) return;
    
    const btns = overlay.querySelectorAll('.mp-detail-tab-btn');
    const contents = overlay.querySelectorAll('.mp-detail-tab-content');
    
    btns.forEach(btn => {
      const tid = btn.getAttribute('data-tab');
      const isActive = tid === tabId;
      btn.classList.toggle('active', isActive);
      btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
    
    contents.forEach(content => {
      const isActive = content.id === `tab-${tabId}`;
      content.style.display = isActive ? 'block' : 'none';
      if (isActive) content.classList.add('active');
      else content.classList.remove('active');
    });

    if (window.lucide) lucide.createIcons();
  };

  function initStaggeredReveal() {
    // Staggered card reveal
    const cards = document.querySelectorAll('.mp-card');
    if ('IntersectionObserver' in window && cards.length) {
      let delayCount = 0;
      let delayTimeout = null;
      const io = new IntersectionObserver(entries => {
        entries.forEach(e => { 
          if (e.isIntersecting) { 
            e.target.style.animationDelay = (delayCount * 0.08) + 's';
            e.target.classList.add('revealed'); 
            io.unobserve(e.target); 
            delayCount++;
            clearTimeout(delayTimeout);
            delayTimeout = setTimeout(() => { delayCount = 0; }, 100);
          } 
        });
      }, { threshold: 0.04, rootMargin: '0px 0px -8% 0px' });
      cards.forEach(c => io.observe(c));
    } else {
      cards.forEach(c => c.classList.add('revealed'));
    }
  }

  /* ── Scroll reveal ─────────────────────────────────── */
  function initScrollReveal() {
    const els = document.querySelectorAll('.reveal-section');
    if (!('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('visible')); return; }
    const io = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
    }, { threshold: 0.07, rootMargin: '0px 0px -40px 0px' });
    els.forEach(e => io.observe(e));
  }

  /* ── View toggle ───────────────────────────────────── */
  function initViewToggle() {
    const grid = document.getElementById('mpGrid');
    const gBtn = document.getElementById('gridViewBtn');
    const lBtn = document.getElementById('listViewBtn');
    if (!grid || !gBtn || !lBtn) return;
    gBtn.addEventListener('click', () => {
      grid.classList.remove('list-view');
      gBtn.classList.add('active'); lBtn.classList.remove('active');
      gBtn.setAttribute('aria-pressed', 'true'); lBtn.setAttribute('aria-pressed', 'false');
    });
    lBtn.addEventListener('click', () => {
      grid.classList.add('list-view');
      lBtn.classList.add('active'); gBtn.classList.remove('active');
      lBtn.setAttribute('aria-pressed', 'true'); gBtn.setAttribute('aria-pressed', 'false');
    });
  }

  /* ── Overlay dismiss ───────────────────────────────── */
  function initOverlayDismiss() {
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') document.querySelectorAll('.mp-overlay.open, .uf-overlay.open').forEach(o => closeOverlay(o.id));
    });
  }

  /* ── Drag & drop uploads ───────────────────────────── */
  function initDragDrop() {
    document.querySelectorAll('.mp-upload-area').forEach(area => {
      area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('drag-over'); });
      area.addEventListener('dragleave', () => area.classList.remove('drag-over'));
      area.addEventListener('drop', e => {
        e.preventDefault(); area.classList.remove('drag-over');
        const input = area.querySelector('input[type="file"]');
        if (!input) return;
        if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; input.dispatchEvent(new Event('change')); }
      });
    });
  }

  /* ── Delivery toggle (create form) ────────────────── */
  window.initDeliveryToggle = function() {
    const typeField     = document.getElementById('lType');
    const deliveryField = document.getElementById('field-delivery');
    const delivTimeField= document.getElementById('field-delivery-time');
    const shippingField = document.getElementById('field-shipping-cost');
    const deliverySelect= document.getElementById('lDelivery');
    if (!typeField) return;
    function sync() {
      const physical = typeField.value === 'physical';
      if (deliveryField)  deliveryField.style.display  = physical ? '' : 'none';
      if (delivTimeField) delivTimeField.style.display = physical ? '' : 'none';
      if (shippingField)  shippingField.style.display  = physical ? '' : 'none';
      if (deliverySelect) {
        if (physical && (!deliverySelect.value || deliverySelect.value === 'instant')) deliverySelect.value = 'shipping';
        if (!physical) deliverySelect.value = 'instant';
      }
    }
    typeField.addEventListener('change', sync);
    sync();
  };
  document.addEventListener('DOMContentLoaded', window.initDeliveryToggle);

  /* ── Helpers ───────────────────────────────────────── */
  function esc(s) { const d = document.createElement('div'); d.appendChild(document.createTextNode(String(s))); return d.innerHTML; }
  function fmtMoney(v) { return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v || 0)); }
  function trimSafe(v) { return String(v || '').trim(); }

  function normalizeItem(item) {
    const base = item && item.id != null ? (catalogMap[String(item.id)] || {}) : {};
    const m = Object.assign({}, base, item || {});
    m.id            = Number(m.id || 0);
    m.price         = Number(m.price || 0);
    m.rating        = Number(m.rating || 0);
    m.review_count  = Number(m.review_count || 0);
    m.shipping_cost = Number(m.shipping_cost || 0);
    m.type          = String(m.type || 'digital');
    m.delivery_option = String(m.delivery_option || (m.type === 'physical' ? 'shipping' : 'instant'));
    m.title         = String(m.title || 'Untitled item');
    return m;
  }

  function getOwnedOrder(itemId) { return orderMap[String(Number(itemId || 0))] || null; }

  function sellerLabel(item) { return trimSafe((item.first_name || '') + ' ' + (item.last_name || '')) || 'Seller'; }
  function sellerLocation(item) {
    const parts = [];
    const ex = trimSafe(item.exact_location), co = trimSafe(item.country);
    if (ex) parts.push(ex);
    if (co && co !== ex) parts.push(co);
    return parts.join(' · ');
  }

  function haversineKm(lat1, lon1, lat2, lon2) {
    const R = 6371, r = d => d * Math.PI / 180;
    const dLat = r(lat2 - lat1), dLon = r(lon2 - lon1);
    const a = Math.sin(dLat/2)**2 + Math.cos(r(lat1))*Math.cos(r(lat2))*Math.sin(dLon/2)**2;
    return 2*R*Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  function deliveryContext(item) {
    const m = normalizeItem(item);
    const order = getOwnedOrder(m.id);
    const owned = !!order || marketplaceData.ownedItemIds.indexOf(Number(m.id)) !== -1;
    const type  = m.type === 'physical' ? 'physical' : 'digital';
    const opt   = m.delivery_option || (type === 'physical' ? 'shipping' : 'instant');
    const shippingCost = Number(m.shipping_cost || 0);
    const baseEta = trimSafe(m.estimated_delivery_time);

    let status = type === 'physical' ? 'Processing' : 'Instant access';
    let statusClass = type === 'physical' ? 'processing' : 'instant';
    let eta = type === 'physical' ? (baseEta || '2-5 business days') : 'Immediate delivery';
    let summary = type === 'physical' ? 'Dispatch and tracking details appear once the order is confirmed.' : 'Digital access is delivered right after checkout.';

    if (type === 'physical' && owned && order && order.created_at) {
      const days = Math.max(0, Math.floor((Date.now() - Date.parse(order.created_at)) / 86400000));
      if (days >= 5) { status = 'Delivered'; statusClass = 'delivered'; summary = 'This order appears complete based on the order timeline.'; }
      else if (days >= 2) { status = 'Shipped'; statusClass = 'shipped'; }
    }
    if (type === 'physical' && opt === 'local_pickup') { status = owned ? 'Ready for pickup' : 'Local pickup'; statusClass = 'pickup'; eta = baseEta || 'Same day'; summary = 'Coordinate pickup directly with the seller.'; }
    if (type === 'digital') { status = owned ? 'Delivered' : 'Instant access'; statusClass = 'instant'; eta = 'Immediate delivery'; }

    let distLabel = '';
    if (marketplaceData.buyer.latitude != null && marketplaceData.buyer.longitude != null && m.latitude != null && m.longitude != null) {
      const km = haversineKm(+marketplaceData.buyer.latitude, +marketplaceData.buyer.longitude, +m.latitude, +m.longitude);
      if (!isNaN(km)) distLabel = Math.round(km) + ' km away';
    }

    const locHeadline = distLabel || sellerLocation(m) || trimSafe(m.country) || 'Seller location unavailable';
    const locSub = trimSafe(marketplaceData.buyer.exact_location) || trimSafe(marketplaceData.buyer.country) || trimSafe(m.exact_location) || '';

    const delivLabel = opt === 'shipping' ? 'Shipping' : (opt === 'local_pickup' ? 'Local pickup' : (type === 'physical' ? 'Physical delivery' : 'Instant access'));

    return {
      owned, type, opt, delivLabel, status, statusClass, eta, summary, locHeadline, locSub,
      shippingLabel: type === 'physical' ? (shippingCost > 0 ? fmtMoney(shippingCost) + ' shipping' : 'Free shipping') : 'No shipping',
      orderLabel: owned ? 'Paid order' : 'Not purchased',
    };
  }

  /* ── Recent views ──────────────────────────────────── */
  function readRecentViews() {
    try { const raw = localStorage.getItem(recentViewsKey); return Array.isArray(JSON.parse(raw || '[]')) ? JSON.parse(raw) : []; } catch { return []; }
  }
  function persistRecentView(item) {
    if (!item || item.id == null) return;
    try {
      const m = normalizeItem(item);
      const cur = readRecentViews().filter(e => Number(e.id) !== m.id);
      cur.unshift({ id: m.id, title: m.title, price: m.price, thumbnail_url: m.thumbnail_url || '', category_name: m.category_name || '', type: m.type, delivery_option: m.delivery_option, first_name: m.first_name || '', last_name: m.last_name || '', avatar_url: m.avatar_url || '', country: m.country || '', shipping_cost: m.shipping_cost || 0, rating: m.rating || 0, review_count: m.review_count || 0, viewed_at: Date.now() });
      localStorage.setItem(recentViewsKey, JSON.stringify(cur.slice(0, 6)));
    } catch {}
  }

  function renderRecentViewsSidebar() {
    const container = document.getElementById('mpContinueSidebar');
    const section   = document.getElementById('recently-viewed-sidebar');
    if (!container || !section) return;
    const items = readRecentViews().map(e => normalizeItem(e)).filter(Boolean).slice(0, 5);
    if (!items.length) { section.style.display = 'none'; return; }
    section.style.display = 'block';
    container.innerHTML = items.map(item => `
      <div class="mp-mini-view-card" onclick='openDetail(${JSON.stringify(item).replace(/'/g,"&apos;")})'
           style="display:flex;align-items:center;gap:12px;cursor:pointer;">
        <img src="${esc(item.thumbnail_url || '../../assets/img/placeholder.png')}"
             alt="" style="width:40px;height:40px;border-radius:6px;object-fit:cover;flex-shrink:0;">
        <div style="flex:1;min-width:0;">
          <div style="font-size:.75rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(item.title)}</div>
          <div style="font-size:.7rem;color:var(--mp-indigo-600);font-weight:600;">$${Number(item.price).toFixed(2)}</div>
        </div>
      </div>`).join('');
    if (window.lucide) lucide.createIcons();
  }

  /* ── Gallery rendering ─────────────────────────────── */
  function renderDetailGallery(item) {
    const m = normalizeItem(item);
    const rail  = document.getElementById('detailGalleryRail');
    const mediaBox = document.getElementById('detailMedia');
    if (!rail || !mediaBox) return;

    const media = [];
    if (m.thumbnail_url) media.push({ type: 'image', src: m.thumbnail_url });
    if (m.video_path) media.push({ type: 'video', src: m.video_path });
    else if (m.video_url) media.push({ type: 'video', src: m.video_url });

    if (!media.length) { rail.innerHTML = ''; mediaBox.innerHTML = '<div class="mp-detail-media-placeholder"><i data-lucide="image"></i><span>No preview available</span></div>'; if (window.lucide) lucide.createIcons(); return; }

    function mount(idx) {
      const sel = media[idx] || media[0];
      mediaBox.innerHTML = '';
      
      if (sel.type === 'video') {
        const vid = document.createElement('video');
        vid.src = sel.src;
        vid.controls = true;
        vid.className = 'mp-detail-video';
        vid.style.width = '100%';
        vid.style.height = '100%';
        vid.style.objectFit = 'contain';
        mediaBox.appendChild(vid);
      } else {
        const img = document.createElement('img');
        img.src = sel.src; img.alt = m.title || '';
        mediaBox.appendChild(img);
      }

      /* Admin image edit: add badge + double-click handler */
      const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
      if (isAdmin) {
        mediaBox.classList.add('admin-editable');
        const badge = document.createElement('div');
        badge.className = 'mp-admin-edit-badge';
        badge.innerHTML = '<i data-lucide="camera" style="width:12px;height:12px;"></i> Double-click to change';
        mediaBox.appendChild(badge);
        mediaBox.ondblclick = function() { adminEditItemImage(m); };
      }

      rail.querySelectorAll('.mp-gallery-thumb').forEach((t, i) => t.classList.toggle('active', i === idx));
      if (window.lucide) lucide.createIcons();
    }

    rail.innerHTML = '';
    media.forEach((entry, idx) => {
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'mp-gallery-thumb' + (idx === 0 ? ' active' : '');
      btn.addEventListener('click', () => mount(idx));
      btn.innerHTML = entry.type === 'video' 
        ? '<div class="mp-card-placeholder"><i data-lucide="play-circle"></i></div>'
        : (entry.src ? `<img src="${esc(entry.src)}" alt="">` : '<div class="mp-card-placeholder"><i data-lucide="image"></i></div>');
      rail.appendChild(btn);
    });
    mount(0);
  }

  /* ── Overview panel ────────────────────────────────── */
  function renderDetailOverview(item, deliv) {
    const m = normalizeItem(item);
    const stack = document.getElementById('detailOverviewStack');
    const desc  = document.getElementById('detailDesc');
    if (desc) desc.textContent = m.description || 'No description available.';
    if (!stack) return;

    const slName = sellerLabel(m);
    const slLoc  = sellerLocation(m);
    const rating = m.rating > 0 ? m.rating.toFixed(1) : 'New';
    const reviews = m.review_count > 0 ? m.review_count.toLocaleString() + ' reviews' : 'No reviews yet';

    // Extract highlights/curriculum for details
    const bulletsSrc = m.what_to_learn || m.curriculum;
    let bullets = [];
    if (Array.isArray(bulletsSrc)) bullets = bulletsSrc.map(String).filter(Boolean);
    else if (typeof bulletsSrc === 'string' && bulletsSrc.trim()) {
      try { const p = JSON.parse(bulletsSrc); bullets = Array.isArray(p) ? p.map(String) : []; } catch { bullets = bulletsSrc.split(/[\n,;|]/).map(s => s.trim()).filter(Boolean); }
    }
    const overviewItems = bullets.length ? bullets.slice(0, 5) : ['Professional product details and verified specifications.'];

    // Video preview if available
    const hasVideo = !!(m.video_path || m.video_url);
    const videoHtml = hasVideo ? `
      <div class="mp-detail-overview-card mp-video-highlight">
        <div class="mp-detail-overview-head">
          <div><span class="mp-detail-overview-kicker">Showcase</span><h3>Video Preview</h3></div>
          <i data-lucide="play-circle" style="color:var(--mp-indigo-500);"></i>
        </div>
        <div class="mp-video-preview-container visible" style="margin-top:12px;border-radius:var(--mp-r-lg);overflow:hidden;box-shadow:var(--mp-shadow-sm);">
          <video src="${esc(m.video_path || m.video_url)}" controls style="width:100%;display:block;"></video>
        </div>
      </div>` : '';

    stack.innerHTML = `
      <div class="mp-detail-overview-card">
        <div class="mp-detail-overview-head">
          <div><span class="mp-detail-overview-kicker">What's included</span><h3>Product details</h3></div>
          <span class="mp-detail-overview-stat">${esc(reviews)}</span>
        </div>
        <ul class="mp-detail-list">
          ${overviewItems.map(b => `<li><i data-lucide="check"></i><span>${esc(b)}</span></li>`).join('')}
        </ul>
      </div>
      ${videoHtml}
      <div class="mp-detail-overview-card mp-detail-overview-split">
        <div class="mp-detail-seller-mini">
          <img src="${esc(m.avatar_url || 'https://api.dicebear.com/9.x/adventurer/svg?seed=' + encodeURIComponent(slName))}" alt="">
          <div>
            <span class="mp-detail-overview-kicker">Seller</span>
            <strong>${esc(slName)}</strong>
            <p>${esc((m.role || 'Verified creator') + (slLoc ? ' · ' + slLoc : ''))}</p>
          </div>
        </div>
        <div class="mp-detail-meta-grid">
          <div><span>Category</span><strong>${esc(m.category_name || 'Marketplace')}</strong></div>
          <div><span>Type</span><strong>${esc(m.type === 'physical' ? 'Physical' : 'Digital')}</strong></div>
          <div><span>Delivery</span><strong>${esc(deliv.delivLabel)}</strong></div>
          <div><span>Rating</span><strong>${esc(rating)}</strong></div>
        </div>
      </div>
      <div class="mp-detail-overview-card">
        <div class="mp-detail-overview-head">
          <div><span class="mp-detail-overview-kicker">Delivery info</span><h3>${esc(deliv.status)}</h3></div>
          <span class="mp-detail-overview-stat">${esc(deliv.eta)}</span>
        </div>
        <p class="mp-detail-copy">${esc(deliv.summary)}</p>
      </div>`;
    if (window.lucide) lucide.createIcons();
  }

  /* ── Reviews panel ─────────────────────────────────── */
  async function renderDetailReviews(item) {
    const m = normalizeItem(item);
    const panel = document.getElementById('detailReviewsPanel');
    if (!panel) return;

    panel.innerHTML = '<div class="mp-review-loading"><i data-lucide="loader" class="mp-spin"></i><span>Loading reviews...</span></div>';
    if (window.lucide) lucide.createIcons();

    try {
      const [revRes, canRes] = await Promise.all([
        fetch(`../../index.php?action=get_reviews&item_id=${m.id}`).then(r => r.json()),
        fetch(`../../index.php?action=can_review&item_id=${m.id}`).then(r => r.json())
      ]);

      const reviews = revRes.success ? revRes.reviews : [];
      const canReview = canRes.success ? canRes.can_review : false;

      let html = '';
      const score = m.rating > 0 ? Number(m.rating).toFixed(1) + '/5' : 'New';
      const count = m.review_count > 0 ? Number(m.review_count).toLocaleString() + ' verified review' + (m.review_count === 1 ? '' : 's') : 'No reviews yet';

      html += `
        <div class="mp-review-summary">
          <div><div class="mp-review-score">${esc(score)}</div><div class="mp-review-count">${esc(count)}</div></div>
          <div class="mp-review-note">Verified reviews from buyers.</div>
        </div>`;

      if (canReview) {
        html += `
          <div class="mp-review-form-card">
            <h4>Write a review</h4>
            <form id="reviewSubmitForm" onsubmit="handleReviewSubmit(event, ${m.id})">
              <div class="mp-star-rating">
                ${[5, 4, 3, 2, 1].map(s => `<input type="radio" name="rating" value="${s}" id="star${s}" required><label for="star${s}"><i data-lucide="star"></i></label>`).join('')}
              </div>
              <textarea name="comment" class="uf-input" placeholder="Share your experience with this item..." required></textarea>
              <button type="submit" class="uf-btn uf-btn-primary" id="btnSubmitReview">Post Review</button>
            </form>
          </div>`;
      }

      if (reviews.length) {
        html += `<div class="mp-review-list">` + reviews.map(r => `
          <div class="mp-review-item">
            <div class="mp-review-item-head">
              <img src="${esc(r.avatar_url || 'https://api.dicebear.com/9.x/adventurer/svg?seed=' + r.user_id)}" alt="">
              <div>
                <strong>${esc(r.first_name + ' ' + r.last_name)}</strong>
                <span class="mp-review-item-date">${new Date(r.created_at).toLocaleDateString()}</span>
              </div>
              <div class="mp-review-item-rating">${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</div>
            </div>
            <p class="mp-review-item-comment">${esc(r.comment)}</p>
          </div>`).join('') + `</div>`;
      } else {
        html += `<div class="mp-review-empty"><i data-lucide="message-square"></i><h4>No reviews yet</h4><p>Be the first to share your feedback after purchasing.</p></div>`;
      }

      panel.innerHTML = html;
      if (window.lucide) lucide.createIcons();
    } catch (e) {
      panel.innerHTML = '<div class="mp-error-state">Failed to load reviews.</div>';
    }
  }

  window.handleReviewSubmit = async function(e, itemId) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btnSubmitReview');
    const data = new FormData(form);
    const payload = {
      item_id: itemId,
      rating: data.get('rating'),
      comment: data.get('comment')
    };

    if (btn) { btn.disabled = true; btn.textContent = 'Posting...'; }

    try {
      const res = await fetch('../../index.php?action=submit_review', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const resData = await res.json();
      if (resData.success) {
        showToast('Review posted successfully!', 'success');
        // Refresh detail view or just the reviews panel
        const item = catalogMap[String(itemId)];
        if (item) renderDetailReviews(item);
      } else {
        showToast(resData.message || 'Failed to post review.', 'error');
      }
    } catch (err) {
      showToast('Connection error. Please try again.', 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = 'Post Review'; }
    }
  };

  /* ── Related items ─────────────────────────────────── */
  async function renderDetailRelated(item) {
    const m = normalizeItem(item);
    const rail = document.getElementById('detailRelatedGrid');
    if (!rail) return;

    rail.innerHTML = '<div class="mp-related-loading"><i data-lucide="loader" class="mp-spin"></i></div>';
    if (window.lucide) lucide.createIcons();

    try {
      const res = await fetch(`../../index.php?action=get_related&item_id=${m.id}&category_id=${m.category_id}`).then(r => r.json());
      const related = res.success ? res.items : [];

      if (!related.length) {
        rail.innerHTML = '<div class="mp-inline-empty">No related items found.</div>';
        return;
      }

      rail.innerHTML = related.map(e => `
        <button type="button" class="mp-related-card" onclick="openDetail(${esc(JSON.stringify(e))})">
          ${e.thumbnail_url ? `<img src="${esc(e.thumbnail_url)}" alt="">` : '<div class="mp-related-card-placeholder"><i data-lucide="image"></i></div>'}
          <div class="mp-related-card-copy">
            <span class="mp-related-card-cat">${esc(e.category_name || 'Marketplace')}</span>
            <strong>${esc(e.title)}</strong>
            <div class="mp-related-card-meta"><span>${esc(fmtMoney(e.price))}</span><span>${e.review_count ? e.review_count + ' reviews' : 'New'}</span></div>
          </div>
        </button>`).join('');
      if (window.lucide) lucide.createIcons();
    } catch (e) {
      rail.innerHTML = '<div class="mp-error-state">Failed to load related items.</div>';
    }
  }

  /* ── Fulfillment panel ─────────────────────────────── */
  function renderFulfillmentPanel(item, deliv) {
    const panel = document.getElementById('detailFulfillmentPanel');
    if (!panel) return;
    const m = normalizeItem(item);
    const rating   = m.rating > 0 ? m.rating.toFixed(1) + ' / 5' : 'New listing';
    const reviews  = m.review_count > 0 ? m.review_count.toLocaleString() + ' reviews' : 'No reviews yet';
    panel.innerHTML = `
      <div class="mp-fulfillment-head">
        <div><div class="mp-fulfillment-kicker">Delivery</div><strong>${esc(deliv.delivLabel)}</strong></div>
        <span class="mp-fulfillment-status ${esc(deliv.statusClass)}">${esc(deliv.status)}</span>
      </div>
      <div class="mp-fulfillment-grid">
        <div><span>ETA</span><strong>${esc(deliv.eta)}</strong></div>
        <div><span>Shipping</span><strong>${esc(deliv.shippingLabel)}</strong></div>
        <div><span>Location</span><strong>${esc(deliv.locHeadline)}</strong></div>
        <div><span>Order</span><strong>${esc(deliv.orderLabel)}</strong></div>
      </div>
      <div class="mp-mini-map">
        <i data-lucide="map-pin"></i>
        <div><strong>${esc(deliv.locHeadline)}</strong><p>${esc(deliv.locSub || 'Location visibility depends on seller profile settings.')}</p></div>
      </div>
      <div class="mp-fulfillment-foot">
        <span>${esc(rating)}</span><span>${esc(reviews)}</span><span>${esc(m.type === 'physical' ? 'Physical item' : 'Digital item')}</span>
      </div>`;
    if (window.lucide) lucide.createIcons();
  }

  /* ── Buy button state ──────────────────────────────── */
  function updateBuyBtn(deliv) {
    const btn = document.getElementById('buyBtn');
    if (!btn) return;
    if (deliv.owned) {
      btn.disabled = true;
      btn.innerHTML = '<i data-lucide="check-circle"></i> Already purchased';
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="credit-card"></i> Purchase with Stripe';
    }
    if (window.lucide) lucide.createIcons();
  }

  /* ── Open/close overlays ───────────────────────────── */
  window.openOverlay = function(id) {
    const ov = document.getElementById(id);
    if (!ov) return;
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => ov.classList.add('open'));
  };
  window.closeOverlay = function(id) {
    const ov = document.getElementById(id);
    if (!ov) return;
    ov.classList.remove('open');
    if (!document.querySelector('.mp-overlay.open, .uf-overlay.open')) document.body.style.overflow = '';
  };
  window.openCreateModal = function() { openOverlay('createOverlay'); };

  /* ── Open detail ───────────────────────────────────── */
  window.openDetail = function(item) {
    const m = normalizeItem(item);
    const deliv = deliveryContext(m);
    window.selectedItemId = m.id;

    persistRecentView(m);
    renderRecentViewsSidebar();
    renderDetailGallery(m);
    renderDetailOverview(m, deliv);
    renderDetailReviews(m);
    renderDetailRelated(m);
    renderFulfillmentPanel(m, deliv);
    updateBuyBtn(deliv);

    // Populate header fields
    const sName = sellerLabel(m);
    const sLoc  = sellerLocation(m);
    const el = id => document.getElementById(id);
    if (el('detailCategoryBadge')) el('detailCategoryBadge').textContent = m.category_name || 'Marketplace';
    if (el('detailTitle'))         el('detailTitle').textContent = m.title || 'Untitled Listing';
    if (el('detailPrice'))         el('detailPrice').textContent = fmtMoney(m.price);
    if (el('detailSellerName'))    el('detailSellerName').textContent = sName;
    if (el('detailSellerLocation'))el('detailSellerLocation').textContent = sLoc || 'Location unknown';
    if (el('detailSellerAvatar'))  el('detailSellerAvatar').src = m.avatar_url || 'https://api.dicebear.com/9.x/adventurer/svg?seed=' + encodeURIComponent(sName);
    if (el('detailDesc'))          el('detailDesc').textContent = m.description || 'No description available.';

    // Attach action handlers
    if (el('saveBtn'))  el('saveBtn').onclick  = () => saveItem(m.id);
    if (el('shareBtn')) el('shareBtn').onclick = () => shareItem(m.title);

    openOverlay('detailOverlay');
    if (window.switchDetailTab) switchDetailTab('overview');

    // Manage Listing Section (Owner or Admin)
    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
    const currentUserId = <?= (int)$currentUserId ?>;
    const manageSection = el('manageListingSection');
    if (manageSection) {
      const isOwner = Number(m.user_id) === currentUserId;
      if (isAdmin || isOwner) {
        manageSection.style.display = 'block';
        if (el('editListingBtn'))   el('editListingBtn').onclick   = () => openEditModal(m);
        if (el('deleteListingBtn')) el('deleteListingBtn').onclick = () => deleteListing(m.id);
      } else {
        manageSection.style.display = 'none';
      }
    }

    if (window.lucide) lucide.createIcons();
  };

  /* ── Delete Listing ─────────────────────────────────── */
  window.deleteListing = async function(itemId) {
    if (!confirm('Are you sure you want to delete this listing? This action cannot be undone.')) return;
    try {
      const fd = new FormData();
      fd.append('action', 'delete_listing');
      fd.append('item_id', itemId);
        const res = await fetch('marketplace.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showToast('Listing deleted successfully.', 'success');
        setTimeout(() => location.reload(), 800);
      } else {
        showToast(data.message || 'Could not delete listing.', 'error');
      }
    } catch { showToast('Server error. Please try again.', 'error'); }
  };

  /* ── Edit Listing ───────────────────────────────────── */
  window.openEditModal = function(item) {
    const m = normalizeItem(item);
    const el = id => document.getElementById(id);
    if (el('eItemId'))       el('eItemId').value = m.id;
    if (el('eTitle'))        el('eTitle').value  = m.title;
    if (el('eDesc'))         el('eDesc').value   = m.description;
    if (el('ePrice'))        el('ePrice').value  = m.price;
    if (el('eCategory'))     el('eCategory').value = m.category_id;
    if (el('eType'))         el('eType').value   = m.type;
    if (el('eDelivery'))     el('eDelivery').value = m.delivery_option;
    if (el('eDeliveryTime')) el('eDeliveryTime').value = m.estimated_delivery_time || '';
    if (el('eShippingCost')) el('eShippingCost').value = m.shipping_cost;

    // Reset video fields in edit modal
    if (el('eVideo')) el('eVideo').value = '';
    if (el('eVideoUrl')) el('eVideoUrl').value = m.video_url || '';
    if (el('editVideoPreview')) el('editVideoPreview').classList.remove('visible');
    
    if (m.video_path || m.video_url) {
      if (m.video_url) {
        switchVideoType('edit', 'url');
        previewVideoUrl('edit');
      } else {
        switchVideoType('edit', 'upload');
        // We can't preview the path easily here without a full path or a video element
        const v = el('editVideoPreviewEl');
        if (v) { v.src = m.video_path; v.load(); el('editVideoPreview').classList.add('visible'); }
      }
    }

    closeOverlay('detailOverlay');
    openOverlay('editOverlay');
  };

  window.handleEditSubmit = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitEditBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i data-lucide="loader"></i> Saving...'; if (window.lucide) lucide.createIcons(); }
    
    const fd = new FormData(e.target);
    fd.append('action', 'update_listing');
    try {
        const res = await fetch('marketplace.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showToast('Listing updated successfully.', 'success');
        setTimeout(() => location.reload(), 800);
      } else {
        showToast(data.message || 'Could not update listing.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="save"></i> Save Changes'; if (window.lucide) lucide.createIcons(); }
      }
    } catch {
      showToast('Server error. Please try again.', 'error');
      if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="save"></i> Save Changes'; if (window.lucide) lucide.createIcons(); }
    }
  };

  /* ── Last viewed ───────────────────────────────────── */
  window.openLastViewedItem = function() {
    const recent = readRecentViews();
    if (recent.length) { openDetail(recent[0]); return; }
    document.getElementById('mp-catalog')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  /* ── Checkout ──────────────────────────────────────── */
  window.initCheckout = async function() {
    if (!window.selectedItemId) return;
    const btn = document.getElementById('buyBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i data-lucide="loader"></i> Processing...'; if (window.lucide) lucide.createIcons(); }
    try {
      const res  = await fetch('../../index.php?action=stripe_checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'item_id=' + encodeURIComponent(selectedItemId)
      });
      const data = await res.json();
      if (data.success && data.url) { window.location.href = data.url; return; }
      showToast(data.message || 'Checkout unavailable.', 'error');
    } catch { showToast('Connection error. Please try again.', 'error'); }
    if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="credit-card"></i> Purchase with Stripe'; if (window.lucide) lucide.createIcons(); }
  };

  /* ── Save / share ──────────────────────────────────── */
  window.saveItem   = id => showToast('Added to your saved items!', 'success');
  window.shareItem  = title => {
    if (navigator.share) { navigator.share({ title, url: window.location.href }).catch(() => {}); }
    else { navigator.clipboard.writeText(window.location.href).then(() => showToast('Link copied!', 'info')); }
  };

  /* ── File preview ──────────────────────────────────── */
  window.previewFile = function(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview || !input.files || !input.files[0]) return;
    const file = input.files[0];
    const isVid = previewId.toLowerCase().includes('video');
    const maxMB = isVid ? 50 : 8;
    if (file.size > maxMB * 1024 * 1024) { showToast('File too large. Max ' + maxMB + 'MB.', 'error'); input.value = ''; return; }
    const url = URL.createObjectURL(file);
    if (isVid) { const v = document.getElementById(previewId + 'El'); if (v) { v.src = url; v.load(); } }
    else        { const img = document.getElementById(previewId + 'Img'); if (img) img.src = url; }
    preview.classList.add('visible');
    const field = input.closest('.uf-group');
    if (field) field.classList.remove('is-invalid');
  };

  /* ── Video Logic ───────────────────────────────────── */
  window.switchVideoType = function(context, type) {
    const isCreate = context === 'create';
    const uploadSection = document.getElementById(isCreate ? 'createVideoUploadSection' : 'editVideoUploadSection');
    const urlSection    = document.getElementById(isCreate ? 'createVideoUrlSection' : 'editVideoUrlSection');
    const btns = document.querySelectorAll(isCreate ? '#field-video .mp-video-type-btn' : '#efield-video .mp-video-type-btn');
    
    if (!uploadSection || !urlSection) return;

    if (type === 'upload') {
      uploadSection.style.display = 'block';
      urlSection.style.display = 'none';
      btns[0]?.classList.add('active');
      btns[1]?.classList.remove('active');
    } else {
      uploadSection.style.display = 'none';
      urlSection.style.display = 'block';
      btns[0]?.classList.remove('active');
      btns[1]?.classList.add('active');
    }
  };

  window.previewVideoUrl = function(context) {
    const isCreate = context === 'create';
    const input   = document.getElementById(isCreate ? 'lVideoUrl' : 'eVideoUrl');
    const preview = document.getElementById(isCreate ? 'videoPreview' : 'editVideoPreview');
    const vid     = document.getElementById(isCreate ? 'videoPreviewEl' : 'editVideoPreviewEl');
    
    if (!input || !preview || !vid) return;
    
    const url = input.value.trim();
    if (url) {
      vid.src = url;
      vid.load();
      preview.classList.add('visible');
    } else {
      preview.classList.remove('visible');
    }
  };

  window.generateAISuggestedVideo = function() {
    showToast('AI Video Generation is currently a placeholder. Backend integration coming soon.', 'info');
  };

  /* ── Create listing submit ─────────────────────────── */
  window.handleCreateSubmit = async function(e) {
    e.preventDefault();
    if (isSubmitting) return;
    const form = e.target;
    if (!validateCreateForm(form)) return;
    isSubmitting = true;
    const btn = document.getElementById('submitListingBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i data-lucide="loader"></i> Publishing...'; if (window.lucide) lucide.createIcons(); }
    const fd = new FormData(form);
    fd.append('action', 'create_listing');
    try {
      const res  = await fetch('Views/FrontOffice/marketplace.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showToast('Listing published! 🎉', 'success');
        closeOverlay('createOverlay');
        setTimeout(() => location.reload(), 1200);
        return;
      }
      showToast(data.message || 'Could not publish listing.', 'error');
    } catch { showToast('Upload failed. Check your connection.', 'error'); }
    isSubmitting = false;
    if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="send"></i> Publish Listing'; if (window.lucide) lucide.createIcons(); }
  };

  function validateCreateForm(form) {
    let valid = true;
    const title = form.querySelector('#lTitle');
    if (!validateField(title, 'field-title', !title || title.value.trim().length < 5)) valid = false;
    const desc = form.querySelector('#lDesc');
    if (!validateField(desc, 'field-description', !desc || desc.value.trim().length < 20)) valid = false;
    const price = form.querySelector('#lPrice');
    if (!validateField(price, 'field-price', !price || isNaN(parseFloat(price.value)) || parseFloat(price.value) < 0.5)) valid = false;
    
    const typeField = form.querySelector('#lType');
    if (typeField && typeField.value === 'physical') {
      const delivTime = form.querySelector('#lDeliveryTime');
      const today = new Date().toISOString().split('T')[0];
      if (!validateField(delivTime, 'field-delivery-time', !delivTime || !delivTime.value || delivTime.value < today)) valid = false;
    }

    const thumb = form.querySelector('#lThumbnail');
    if (!validateField(thumb, 'field-thumbnail', !thumb || !thumb.files || !thumb.files.length)) valid = false;
    
    if (!valid) {
      showToast('Please fix the highlighted fields.', 'error');
      document.querySelector('.uf-group.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return valid;
  }

  function validateField(input, fieldId, isBad) {
    const field = document.getElementById(fieldId);
    if (isBad) {
      if (field) field.classList.add('is-invalid');
      if (input) input.classList.add('is-invalid');
      return false;
    } else {
      if (field) field.classList.remove('is-invalid');
      if (input) input.classList.remove('is-invalid');
      return true;
    }
  }

  /* ── Stable real-time validation via event delegation ──
     Uses a single delegated listener on the form instead of
     per-field addEventListener calls. This prevents duplicate
     bindings, avoids DOM re-init, and keeps field values
     persistent across focus changes. */
  window.initRealTimeValidation = function() {
    const form = document.getElementById('createListingForm');
    if (!form || form.dataset.rtValidated) return;
    form.dataset.rtValidated = '1'; // prevent double-init

    const rules = {
      'lTitle':        (v) => v.trim().length >= 5,
      'lDesc':         (v) => v.trim().length >= 20,
      'lPrice':        (v) => !isNaN(parseFloat(v)) && parseFloat(v) >= 0.5,
      'lDeliveryTime': (v) => {
        if (!v) return true; // optional unless physical
        const today = new Date().toISOString().split('T')[0];
        return v >= today;
      }
    };
    const fieldMap = {
      'lTitle': 'field-title',
      'lDesc': 'field-description',
      'lPrice': 'field-price',
      'lDeliveryTime': 'field-delivery-time'
    };

    function runValidation(input) {
      const id = input.id;
      if (!rules[id]) return;
      const val = input.value;
      const ok = rules[id](val);
      validateField(input, fieldMap[id], !ok);
    }

    // Delegated input event — covers typing, pasting, autofill
    form.addEventListener('input', function(e) {
      const input = e.target;
      if (input && rules[input.id]) runValidation(input);
    });

    // Delegated blur — final check when leaving a field
    form.addEventListener('focusout', function(e) {
      const input = e.target;
      if (input && rules[input.id]) runValidation(input);
    });

    // Date picker change
    form.addEventListener('change', function(e) {
      const input = e.target;
      if (input && rules[input.id]) runValidation(input);
    });
  };

  /* ── Media editor submit ───────────────────────────── */
  window.handleMediaEditSubmit = async function(e) {
    e.preventDefault();
    const form  = e.target;
    const itemId = document.getElementById('mediaItemId');
    const thumbInput = document.getElementById('mediaThumbnailInput');
    const btn   = document.getElementById('submitMediaBtn');
    if (!itemId || !itemId.value) { showToast('Missing listing id.', 'error'); return; }
    if (!thumbInput?.files?.length) { showToast('Choose a thumbnail to upload.', 'error'); return; }
    if (btn) { btn.disabled = true; btn.innerHTML = '<i data-lucide="loader"></i> Saving...'; if (window.lucide) lucide.createIcons(); }
    const fd = new FormData(form);
    fd.append('action', 'update_listing_media');
    try {
      const res  = await fetch('marketplace.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) { showToast('Listing media updated.', 'success'); closeOverlay('mediaEditorOverlay'); setTimeout(() => location.reload(), 800); return; }
      showToast(data.message || 'Could not update media.', 'error');
    } catch { showToast('Upload failed. Please try again.', 'error'); }
    if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="save"></i> Save media'; if (window.lucide) lucide.createIcons(); }
  };

  window.openMediaEditor = function(item) {
    const m = normalizeItem(item);
    const idField = document.getElementById('mediaItemId');
    const curThumb = document.getElementById('mediaCurrentThumb');
    if (idField) idField.value = m.id || '';
    if (curThumb) { curThumb.src = m.thumbnail_url || ''; curThumb.style.display = m.thumbnail_url ? '' : 'none'; }
    const thumbEl = document.getElementById('mediaThumbnailInput');
    if (thumbEl) thumbEl.value = '';
    const prevEl = document.getElementById('mediaThumbPreview');
    if (prevEl) prevEl.classList.remove('visible');
    openOverlay('mediaEditorOverlay');
  };


  /* ── Admin: double-click image edit ───────────────── */
  window.adminEditItemImage = function(item) {
    const m = normalizeItem(item);
    // Create a temporary file input
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    fileInput.style.display = 'none';
    document.body.appendChild(fileInput);

    fileInput.addEventListener('change', async function() {
      const file = fileInput.files[0];
      if (!file) { fileInput.remove(); return; }
      if (file.size > 8 * 1024 * 1024) { showToast('Image too large. Max 8MB.', 'error'); fileInput.remove(); return; }

      // Instant preview
      const previewUrl = URL.createObjectURL(file);
      const mediaBox = document.getElementById('detailMedia');
      const img = mediaBox?.querySelector('img');
      if (img) img.src = previewUrl;

      // Upload to server
      const fd = new FormData();
      fd.append('action', 'update_listing_media');
      fd.append('item_id', m.id);
      fd.append('thumbnail', file);

      try {
        const res = await fetch('marketplace.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          showToast('Image updated successfully!', 'success');
          // Update the catalog data in memory
          if (catalogMap[String(m.id)]) catalogMap[String(m.id)].thumbnail_url = data.thumbnail_url || previewUrl;
          // Refresh card thumbnails on the grid without full reload
          const cards = document.querySelectorAll(`.mp-card[data-id="${m.id}"] .mp-card-preview img`);
          cards.forEach(ci => { ci.src = data.thumbnail_url || previewUrl; });
        } else {
          showToast(data.message || 'Could not update image.', 'error');
          if (img) img.src = m.thumbnail_url || '';
        }
      } catch {
        showToast('Upload failed. Please try again.', 'error');
        if (img) img.src = m.thumbnail_url || '';
      }
      fileInput.remove();
    });

    fileInput.click();
  };

  window.showToast = function(msg, type = 'info') {
    const stack = document.getElementById('mpToastStack');
    if (!stack) return;
    const icons = { success: 'check-circle', error: 'x-circle', info: 'info' };
    const toast = document.createElement('div');
    toast.className = 'mp-toast ' + type;
    toast.innerHTML = `<i data-lucide="${icons[type] || 'info'}"></i><span>${esc(msg)}</span>`;
    stack.appendChild(toast);
    if (window.lucide) lucide.createIcons();
    setTimeout(() => { toast.classList.add('out'); setTimeout(() => toast.remove(), 350); }, 3500);
  };

})();
</script>
<script src="../../assets/js/main.js"></script>

</body>
</html>