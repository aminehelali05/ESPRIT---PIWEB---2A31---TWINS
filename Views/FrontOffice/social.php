<?php
session_start();
include_once __DIR__ . '/../../Controllers/PostController.php';
include_once __DIR__ . '/../../Controllers/CommentController.php';
include_once __DIR__ . '/../../Controllers/ReactionController.php';

$postController = new PostController();
$commentController = new CommentController();
$reactionController = new ReactionController();

$posts = $postController->listPosts();

// Current User simulation
$current_user_id = $_SESSION['user_id'] ?? 1; 
$current_user_name = $_SESSION['user_name'] ?? 'Admin Root';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Diversity.is Social Feed — Share updates, discuss ideas, and engage with the community.">
  <title>Social Feed — Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/social.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    .post-action-btn.liked { color: var(--accent-primary); fill: var(--accent-primary); }
    .comments-section { display: none; }
    .comments-section.expanded { display: block; margin-top: var(--space-md); }
  </style>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="TemplateHome.php" class="navbar-brand"><div class="brand-icon">↑</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="TemplateHome.php">Home</a>
        <a href="auth.php">Sign In</a>
        <a href="social.php" class="active">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php">Projects</a>
        <a href="reviews.php">Reviews</a>
        <a href="challenges.php">Challenges</a>
      </div>
      <div class="navbar-actions">
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>

        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu">
            <div class="nav-avatar"><?= strtoupper(substr($current_user_name, 0, 2)) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($current_user_name) ?></strong>
              <span>user@diversity.is</span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="../BackOffice/ListUsers.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="auth.php" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <section class="section-half" style="padding-top:100px">
    <div class="container">
      <div class="section-header fade-in-section" style="text-align:left;margin-bottom:var(--space-2xl)">
        <span class="section-tag">Community</span>
        <h1 class="text-h1">Social <span class="text-gradient">Feed</span></h1>
        <p class="text-body-lg">See what the community is sharing on Diversity.</p>
      </div>
    </div>
  </section>

  <section class="feed-section">
    <div class="container">
      <div class="feed-layout">
        <div class="feed-main">
          <!-- Create Post Form -->
          <div class="create-post glass-card fade-in-section">
            <form action="../../Controllers/SocialActions.php" method="POST">
              <input type="hidden" name="action" value="add_post">
              <div class="create-post-row">
                <div class="avatar"><span><?= strtoupper(substr($current_user_name, 0, 2)) ?></span></div>
                <textarea name="contenu" class="form-input create-post-input" placeholder="Share something with the community..." required style="resize:none;height:60px;background:transparent;border:none;color:white;width:100%"></textarea>
              </div>
              <div class="create-post-actions">
                <input type="text" name="media_url" class="form-input" placeholder="Media URL (optional)" style="width:200px;font-size:0.8rem">
                <button type="submit" class="btn btn-sm btn-primary">Post</button>
              </div>
            </form>
          </div>

          <!-- Dynamic Post Cards -->
          <?php foreach ($posts as $post): 
              $comments = $commentController->listCommentsByPost($post['id']);
              $reactionCounts = $reactionController->getReactionsByPost($post['id']);
              $totalLikes = 0;
              foreach ($reactionCounts as $rc) if ($rc['type'] == 'like') $totalLikes = $rc['count'];
          ?>
          <div class="post-card glass-card fade-in-section stagger-item" id="post-<?= $post['id'] ?>">
            <div class="post-header">
              <div class="avatar" style="background:var(--accent-primary)"><span><?= strtoupper(substr($post['first_name'], 0, 1) . substr($post['last_name'], 0, 1)) ?></span></div>
              <div>
                <p class="post-author"><?= htmlspecialchars($post['first_name'] . ' ' . $post['last_name']) ?></p>
                <p class="text-small"><?= htmlspecialchars($post['created_at']) ?></p>
              </div>
              <?php if ($post['user_id'] == $current_user_id): ?>
                <div style="margin-left:auto">
                   <a href="../../Controllers/SocialActions.php?action=delete_post&post_id=<?= $post['id'] ?>" class="text-danger" onclick="return confirm('Delete post?')"><i data-lucide="trash-2" class="w-4 h-4"></i></a>
                </div>
              <?php endif; ?>
            </div>
            <div class="post-body">
              <p><?= nl2br(htmlspecialchars($post['contenu'])) ?></p>
              <?php if ($post['media_url']): ?>
              <div class="post-media">
                <img src="<?= htmlspecialchars($post['media_url']) ?>" alt="Post Media" style="max-width:100%; border-radius:var(--radius-lg); margin-top:var(--space-md)">
              </div>
              <?php endif; ?>
            </div>
            <div class="post-stats">
              <span><i data-lucide="heart" class="w-3 h-3"></i> <?= $totalLikes ?> likes</span>
              <span class="post-comment-toggle" data-post="<?= $post['id'] ?>" style="cursor:pointer"><i data-lucide="message-circle" class="w-3 h-3"></i> <?= count($comments) ?> comments</span>
            </div>
            <div class="post-actions">
              <a href="../../Controllers/SocialActions.php?action=toggle_reaction&post_id=<?= $post['id'] ?>&type=like" class="post-action-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                Like
              </a>
              <button class="post-action-btn post-comment-toggle" data-post="<?= $post['id'] ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Comment
              </button>
            </div>

            <!-- Comments Section -->
            <div class="comments-section" id="comments-<?= $post['id'] ?>">
              <?php foreach ($comments as $comment): ?>
              <div class="comment-item" style="margin-bottom:var(--space-md)">
                <div class="avatar" style="width:32px;height:32px;font-size:0.7rem"><span><?= strtoupper(substr($comment['first_name'], 0, 1) . substr($comment['last_name'], 0, 1)) ?></span></div>
                <div class="comment-body">
                  <p class="comment-author"><?= htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']) ?></p>
                  <p class="comment-text"><?= htmlspecialchars($comment['contenu']) ?></p>
                  <p class="text-small"><?= htmlspecialchars($comment['created_at']) ?></p>
                </div>
              </div>
              <?php endforeach; ?>
              
              <form action="../../Controllers/SocialActions.php" method="POST" class="comment-input-row" style="display:flex;gap:var(--space-sm)">
                <input type="hidden" name="action" value="add_comment">
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                <div class="avatar" style="width:32px;height:32px;font-size:0.7rem"><span><?= strtoupper(substr($current_user_name, 0, 2)) ?></span></div>
                <input name="contenu" class="form-input" placeholder="Write a comment..." required style="flex:1;padding:0.6rem 1rem;font-size:0.85rem">
                <button type="submit" class="btn btn-sm btn-primary">Send</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <aside class="feed-sidebar">
          <div class="glass-card sidebar-card fade-in-section">
            <h3 class="panel-title">Trending Topics</h3>
            <div class="trending-list">
              <div class="trending-item"><span class="trending-hash">#</span><div><p class="trending-topic">Diversity</p><p class="text-small">Welcome home</p></div></div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/mouse-tracking.js"></script>
  <script src="../assets/js/social.js"></script>
</body>
</html>
