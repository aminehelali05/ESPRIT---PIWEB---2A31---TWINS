<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Test your knowledge with daily quizzes and coding challenges on Diversity.is.">
  <title>Challenges — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/challenges.css">
  <script src="https://unpkg.com/lucide@latest"></script>
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
        <a href="reviews.php">Reviews</a>
        <a href="challenges.php" class="active">Challenges</a>
      </div>
      <div class="navbar-actions">
        <!-- Theme Toggle -->
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>

        <!-- Profile Dropdown -->
        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu">
            <div class="nav-avatar">AD</div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong>Admin User</strong>
              <span>admin@diversity.is</span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="../BackOffice/dashboard.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="auth.php" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <section class="section-half" style="padding-top:100px">
    <div class="container">
      <div class="section-header fade-in-section" style="text-align:center">
        <span class="section-tag">Level Up</span>
        <h1 class="text-h1">Challenges & <span class="text-gradient">Daily Quiz</span></h1>
        <p class="text-body-lg">Push your limits, test your knowledge, and earn rewards.</p>
      </div>
    </div>
  </section>

  <!-- Challenge Cards -->
  <section class="challenges-grid-section">
    <div class="container">
      <h2 class="text-h2 fade-in-section" style="margin-bottom:var(--space-2xl)">Active <span class="text-gradient">Challenges</span></h2>
      <div class="grid grid-3 fade-in-section">
        <div class="challenge-card glass-card neon-card stagger-item">
          <div class="challenge-difficulty diff-easy">Easy</div>
          <div class="challenge-icon"><i data-lucide="puzzle" class="w-6 h-6"></i></div>
          <h3 class="challenge-title">Algorithm Basics</h3>
          <p class="text-small">Solve 5 sorting algorithm challenges within 30 minutes.</p>
          <div class="challenge-reward">
            <span class="reward-xp">+150 XP</span>
            <span class="reward-badge"><i data-lucide="award" class="w-3.5 h-3.5"></i> Badge</span>
          </div>
          <div class="challenge-stats">
            <span class="text-small"><i data-lucide="users" class="w-3 h-3"></i> 234 completed</span>
            <span class="text-small"><i data-lucide="clock" class="w-3 h-3"></i> 30 min</span>
          </div>
          <button class="btn btn-primary btn-sm challenge-start">Start Challenge</button>
        </div>

        <div class="challenge-card glass-card neon-card neon-cyan stagger-item">
          <div class="challenge-difficulty diff-medium">Medium</div>
          <div class="challenge-icon"><i data-lucide="zap" class="w-6 h-6"></i></div>
          <h3 class="challenge-title">API Design Sprint</h3>
          <p class="text-small">Design a RESTful API for an e-commerce platform with proper error handling.</p>
          <div class="challenge-reward">
            <span class="reward-xp">+300 XP</span>
            <span class="reward-badge"><i data-lucide="award" class="w-3.5 h-3.5"></i> Badge</span>
          </div>
          <div class="challenge-stats">
            <span class="text-small"><i data-lucide="users" class="w-3 h-3"></i> 128 completed</span>
            <span class="text-small"><i data-lucide="clock" class="w-3 h-3"></i> 45 min</span>
          </div>
          <button class="btn btn-primary btn-sm challenge-start">Start Challenge</button>
        </div>

        <div class="challenge-card glass-card neon-card neon-purple stagger-item">
          <div class="challenge-difficulty diff-hard">Hard</div>
          <div class="challenge-icon"><i data-lucide="flame" class="w-6 h-6"></i></div>
          <h3 class="challenge-title">System Design</h3>
          <p class="text-small">Design a distributed messaging system handling 10M+ messages per day.</p>
          <div class="challenge-reward">
            <span class="reward-xp">+500 XP</span>
            <span class="reward-badge"><i data-lucide="trophy" class="w-3.5 h-3.5"></i> Trophy</span>
          </div>
          <div class="challenge-stats">
            <span class="text-small"><i data-lucide="users" class="w-3 h-3"></i> 42 completed</span>
            <span class="text-small"><i data-lucide="clock" class="w-3 h-3"></i> 60 min</span>
          </div>
          <button class="btn btn-primary btn-sm challenge-start">Start Challenge</button>
        </div>
      </div>
    </div>
  </section>

  <!-- Daily Quiz -->
  <section class="section quiz-section">
    <div class="container">
      <div class="section-header fade-in-section" style="text-align:center">
        <span class="section-tag">Daily Challenge</span>
        <h2 class="text-h1">Quick <span class="text-gradient">Quiz</span></h2>
        <p class="text-body-lg">5 questions, 60 seconds each. How well do you know your stuff?</p>
      </div>

      <div class="quiz-container glass-card fade-in-section" id="quiz-container">
        <!-- Quiz Start Screen -->
        <div class="quiz-start" id="quiz-start">
          <div class="quiz-start-icon"><i data-lucide="brain" class="w-10 h-10"></i></div>
          <h3 class="text-h2">Ready to Test Your Knowledge?</h3>
          <p class="text-body-lg">Today's topic: <span class="text-gradient">JavaScript Fundamentals</span></p>
          <div class="quiz-info-row">
            <div class="quiz-info-item">
              <span class="quiz-info-num">5</span>
              <span class="text-small">Questions</span>
            </div>
            <div class="quiz-info-item">
              <span class="quiz-info-num">60s</span>
              <span class="text-small">Per Question</span>
            </div>
            <div class="quiz-info-item">
              <span class="quiz-info-num">+50</span>
              <span class="text-small">XP Each</span>
            </div>
          </div>
          <button class="btn btn-primary btn-lg" id="quiz-start-btn">Start Quiz</button>
        </div>

        <!-- Quiz Active -->
        <div class="quiz-active" id="quiz-active" style="display:none">
          <div class="quiz-header">
            <div class="quiz-progress-bar">
              <div class="quiz-progress-fill" id="quiz-progress"></div>
            </div>
            <div class="quiz-meta">
              <span class="quiz-question-num" id="quiz-question-num">Question 1/5</span>
              <div class="quiz-timer" id="quiz-timer">
                <svg class="timer-svg" viewBox="0 0 36 36">
                  <path class="timer-track" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/>
                  <path class="timer-fill" id="timer-fill" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--color-accent)" stroke-width="2" stroke-dasharray="100, 100"/>
                </svg>
                <span class="timer-text" id="timer-text">60</span>
              </div>
            </div>
          </div>
          <div class="quiz-question" id="quiz-question"></div>
          <div class="quiz-choices" id="quiz-choices"></div>
        </div>

        <!-- Quiz Result -->
        <div class="quiz-result" id="quiz-result" style="display:none">
          <div class="result-icon" id="result-icon"><i data-lucide="party-popper" class="w-12 h-12"></i></div>
          <h3 class="text-h2" id="result-title">Great Job!</h3>
          <p class="text-body-lg" id="result-subtitle">You scored</p>
          <div class="result-score" id="result-score">0/5</div>
          <div class="result-xp" id="result-xp">+0 XP earned</div>
          <button class="btn btn-primary btn-lg" id="quiz-restart-btn">Try Again</button>
        </div>
      </div>
    </div>
  </section>

  <div style="height:var(--space-3xl)"></div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/challenges.js"></script>
</body>
</html>
