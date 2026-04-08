<?php
include_once __DIR__ . '/../../Controllers/PostController.php';

if (isset($_GET['id'])) {
    $postController = new PostController();
    $postController->deletePost($_GET['id']);
}

header('Location: ListPosts.php');
exit;
