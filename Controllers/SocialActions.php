<?php
session_start();
include_once __DIR__ . '/PostController.php';
include_once __DIR__ . '/CommentController.php';
include_once __DIR__ . '/ReactionController.php';

// Simulate a logged-in user for the demo if none exists
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Admin user from User.sql
    $_SESSION['user_name'] = 'Admin Root';
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$postController = new PostController();
$commentController = new CommentController();
$reactionController = new ReactionController();

if ($action === 'add_post') {
    $contenu = $_POST['contenu'] ?? '';
    $media_url = $_POST['media_url'] ?? '';
    if (!empty($contenu)) {
        $post = new Post($_SESSION['user_id'], $contenu, $media_url);
        $postController->addPost($post);
    }
    header('Location: ../Views/FrontOffice/social.php');
    exit;
}

if ($action === 'add_comment') {
    $post_id = $_POST['post_id'] ?? '';
    $contenu = $_POST['contenu'] ?? '';
    if (!empty($post_id) && !empty($contenu)) {
        $comment = new Comment($post_id, $_SESSION['user_id'], $contenu);
        $commentController->addComment($comment);
    }
    header('Location: ../Views/FrontOffice/social.php#post-' . $post_id);
    exit;
}

if ($action === 'toggle_reaction') {
    $post_id = $_GET['post_id'] ?? '';
    $type = $_GET['type'] ?? 'like';
    if (!empty($post_id)) {
        $reaction = new Reaction($_SESSION['user_id'], $post_id, $type);
        $reactionController->toggleReaction($reaction);
    }
    header('Location: ../Views/FrontOffice/social.php#post-' . $post_id);
    exit;
}

if ($action === 'delete_post') {
    $post_id = $_GET['post_id'] ?? '';
    $post = $postController->getPostById($post_id);
    if ($post && $post->getUserId() == $_SESSION['user_id']) {
        $postController->deletePost($post_id);
    }
    header('Location: ../Views/FrontOffice/social.php');
    exit;
}
