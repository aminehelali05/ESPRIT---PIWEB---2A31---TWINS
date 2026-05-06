<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../Controllers/UserController.php';
require_once __DIR__ . '/../../Controllers/MarketplaceController.php';

header('Content-Type: application/json');

if (!UserController::isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$currentUser = UserController::currentUser();
$currentUserId = (int)$currentUser['id'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$controller = new MarketplaceController();

switch ($action) {
    case 'get_details':
        $id = (int)($_GET['id'] ?? 0);
        $service = new MarketplaceService(config::getConnexion());
        $item = $service->getItemById($id);
        if ($item) {
            echo json_encode(['success' => true, 'item' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
        }
        break;

    case 'buy':
        $itemId = (int)($_POST['item_id'] ?? 0);
        $orderId = $controller->placeOrder($currentUserId, $itemId);
        
        if ($orderId) {
            echo json_encode(['success' => true, 'order_id' => $orderId, 'message' => 'Purchase successful!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Purchase failed. Ensure you have sufficient balance and the item is available.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
