<?php
require __DIR__ . '/../RebuildUsers/profile.php';
return;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Your professional profile on Diversity.is — manage your identity, skills, and activity.">
  <title>Profile — Diversity.is</title>
  <link rel="stylesheet" href="../styles/global.css">
  <link rel="stylesheet" href="../styles/profile.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="../index.html" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="../index.html">Home</a>
        <a href="auth.html">Sign In</a>
        <a href="social.html">Feed</a>
        <a href="skills.html">Skills</a>
        <a href="projects.html">Projects</a>
        <a href="reviews.html">Reviews</a>
        <a href="challenges.html">Challenges</a>
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
            <a href="profile.html" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="dashboard.html" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="auth.html" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <main class="profile-main">
    <div class="container">

      <!-- Profile Header Card -->
      <div class="profile-header glass-card fade-in-section">
        <div class="profile-header-glow"></div>
        <div class="profile-header-inner">
          <!-- Avatar -->
          <div class="profile-avatar-wrap">
            <div class="profile-avatar">
              <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=JohnDoe" alt="Profile" id="profileAvatar">
              <button class="avatar-upload-btn" id="avatarUploadBtn" title="Change Photo">
                <i data-lucide="camera" class="w-5 h-5"></i>
              </button>
              <input type="file" id="avatarInput" accept="image/*" hidden>
            </div>
            <button class="avatar-random-btn" id="randomAvatarBtn" title="Random Avatar">
              <i data-lucide="dices" class="w-3.5 h-3.5"></i>
            </button>
            <div class="avatar-verified" title="Verified Account">
              <i data-lucide="badge-check" class="w-4 h-4"></i>
            </div>
          </div>

          <!-- Info -->
          <div class="profile-info">
            <div class="profile-info-top">
              <h1 class="profile-name">John Doe</h1>
              <span class="profile-badge badge-legend">
                <i data-lucide="crown" class="w-3 h-3"></i> Legend
              </span>
            </div>
            <div class="profile-meta-row">
              <span class="profile-meta-item"><i data-lucide="mail" class="w-3.5 h-3.5"></i> john@diversity.is</span>
              <span class="profile-meta-dot"></span>
              <span class="profile-meta-item"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i> San Francisco, CA</span>
              <span class="profile-meta-dot"></span>
              <span class="profile-meta-item"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Joined Sept 2023</span>
            </div>
            <div class="profile-tags">
              <span class="profile-tag"><i data-lucide="code-2" class="w-3 h-3"></i> Full Stack Dev</span>
              <span class="profile-tag tag-accent"><i data-lucide="rocket" class="w-3 h-3"></i> Innovator</span>
              <span class="profile-tag tag-green"><i data-lucide="users" class="w-3 h-3"></i> Community Builder</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="profile-actions">
            <button class="btn btn-primary btn-sm" id="editToggleBtn">
              <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit Details
            </button>
            <button class="profile-action-icon" title="Export Data">
              <i data-lucide="download" class="w-4 h-4"></i>
            </button>
            <button class="profile-action-icon action-danger" title="Sign Out">
              <i data-lucide="log-out" class="w-4 h-4"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Content Grid -->
      <div class="profile-grid">

        <!-- Left Column -->
        <div class="profile-left">

          <!-- Engagement Stats -->
          <div class="glass-card panel fade-in-section">
            <div class="panel-header">
              <h3 class="panel-title">Engagement</h3>
              <button class="panel-action"><i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i></button>
            </div>
            <div class="stats-grid">
              <div class="stat-item">
                <div class="stat-icon stat-icon-indigo"><i data-lucide="zap" class="w-4 h-4"></i></div>
                <div class="stat-value">47</div>
                <div class="stat-label">Projects</div>
              </div>
              <div class="stat-item">
                <div class="stat-icon stat-icon-cyan"><i data-lucide="git-pull-request" class="w-4 h-4"></i></div>
                <div class="stat-value">189</div>
                <div class="stat-label">Contributions</div>
              </div>
              <div class="stat-item">
                <div class="stat-icon stat-icon-emerald"><i data-lucide="star" class="w-4 h-4"></i></div>
                <div class="stat-value">4.9</div>
                <div class="stat-label">Rating</div>
              </div>
              <div class="stat-item">
                <div class="stat-icon stat-icon-amber"><i data-lucide="trophy" class="w-4 h-4"></i></div>
                <div class="stat-value">12</div>
                <div class="stat-label">Badges</div>
              </div>
            </div>
          </div>

          <!-- Skills Panel -->
          <div class="glass-card panel fade-in-section">
            <div class="panel-header">
              <h3 class="panel-title">Skills & Expertise</h3>
              <button class="panel-action" title="Add Skill"><i data-lucide="plus" class="w-3.5 h-3.5"></i></button>
            </div>
            <div class="skills-tags">
              <span class="skill-tag">React <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">Node.js <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">TypeScript <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">Python <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">System Design <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
              <span class="skill-tag">AWS <button class="skill-remove"><i data-lucide="x" class="w-2.5 h-2.5"></i></button></span>
            </div>
            <div class="skill-input-row">
              <input type="text" placeholder="Add a skill..." class="skill-input" id="skillInput">
            </div>
          </div>

          <!-- Connections Panel -->
          <div class="glass-card panel fade-in-section">
            <div class="panel-header">
              <h3 class="panel-title">Connections</h3>
            </div>
            <div class="connections-list">
              <div class="connection-item">
                <div class="connection-icon connection-discord"><i data-lucide="message-square" class="w-3.5 h-3.5"></i></div>
                <div class="connection-info">
                  <span class="connection-name">Discord</span>
                  <span class="connection-status">Connected</span>
                </div>
                <div class="connection-dot dot-active"></div>
              </div>
              <div class="connection-item">
                <div class="connection-icon connection-github"><i data-lucide="github" class="w-3.5 h-3.5"></i></div>
                <div class="connection-info">
                  <span class="connection-name">GitHub</span>
                  <span class="connection-status">Connected</span>
                </div>
                <div class="connection-dot dot-active"></div>
              </div>
              <div class="connection-item">
                <div class="connection-icon"><i data-lucide="linkedin" class="w-3.5 h-3.5"></i></div>
                <div class="connection-info">
                  <span class="connection-name">LinkedIn</span>
                  <span class="connection-status">Not connected</span>
                </div>
                <button class="connection-connect-btn">Connect</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="profile-right">

          <!-- AI Insight Card -->
          <div class="ai-insight-card glass-card fade-in-section">
            <div class="ai-insight-glow"></div>
            <div class="ai-insight-inner">
              <div class="ai-insight-header">
                <div class="ai-insight-title">
                  <i data-lucide="sparkles" class="w-4 h-4"></i>
                  <span>AI IMPACT ANALYSIS</span>
                </div>
                <span class="ai-insight-updated">Updated 2h ago</span>
              </div>
              <h3 class="ai-insight-headline">Top 5% Collaborator</h3>
              <p class="ai-insight-description">Your collaboration score is <strong>trending up 23%</strong> this month. You've positively impacted <strong>12 team projects</strong> and mentored <strong>3 new developers</strong>.</p>
              <div class="ai-insight-bar">
                <div class="ai-bar-labels">
                  <span>Impact Score</span>
                  <span>Top 5%</span>
                </div>
                <div class="ai-bar-track">
                  <div class="ai-bar-fill" id="aiBarFill"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <div class="profile-tabs fade-in-section">
            <button class="profile-tab active" data-tab="settings"><i data-lucide="settings" class="w-4 h-4"></i> Settings</button>
            <button class="profile-tab" data-tab="history"><i data-lucide="clock" class="w-4 h-4"></i> History</button>
            <button class="profile-tab" data-tab="security"><i data-lucide="shield" class="w-4 h-4"></i> Security</button>
          </div>

          <!-- Settings Panel -->
          <div class="tab-content active" data-content="settings">
            <div class="settings-grid fade-in-section">
              <!-- Notifications -->
              <div class="glass-card panel">
                <h4 class="panel-sub-title"><i data-lucide="bell" class="w-4 h-4"></i> Notifications</h4>
                <p class="panel-sub-desc">Manage how you receive updates.</p>
                <div class="toggle-list">
                  <div class="toggle-row">
                    <span>Weekly Digest</span>
                    <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                  </div>
                  <div class="toggle-row">
                    <span>Project Alerts</span>
                    <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                  </div>
                  <div class="toggle-row">
                    <span>Marketing Emails</span>
                    <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
                  </div>
                </div>
              </div>

              <!-- Privacy -->
              <div class="glass-card panel">
                <h4 class="panel-sub-title"><i data-lucide="eye" class="w-4 h-4"></i> Privacy</h4>
                <p class="panel-sub-desc">Control your data visibility.</p>
                <div class="toggle-list">
                  <div class="toggle-row">
                    <span>Public Profile</span>
                    <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                  </div>
                  <div class="toggle-row">
                    <span>Share Activity Status</span>
                    <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
                  </div>
                  <div class="toggle-row">
                    <span>Allow AI Analysis</span>
                    <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- History Panel -->
          <div class="tab-content" data-content="history">
            <div class="glass-card panel fade-in-section">
              <h4 class="panel-sub-title">Recent Activity</h4>
              <div class="activity-timeline">
                <div class="activity-item">
                  <div class="activity-dot dot-blue"></div>
                  <div class="activity-body">
                    <p>Updated profile settings</p>
                    <span>Just now · Web</span>
                  </div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot dot-green"></div>
                  <div class="activity-body">
                    <p>Completed <strong>API Design Sprint</strong> challenge</p>
                    <span>2 hours ago · +300 XP</span>
                  </div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot dot-purple"></div>
                  <div class="activity-body">
                    <p>Reviewed <strong>Sarah Kim's</strong> project</p>
                    <span>Yesterday, 14:00 · Review</span>
                  </div>
                </div>
                <div class="activity-item">
                  <div class="activity-dot dot-amber"></div>
                  <div class="activity-body">
                    <p>Earned <strong>Team Player</strong> badge</p>
                    <span>3 days ago · Achievement</span>
                  </div>
                </div>
              </div>
              <button class="panel-full-btn">
                <i data-lucide="history" class="w-3.5 h-3.5"></i> View Full History
              </button>
            </div>
          </div>

          <!-- Security Panel -->
          <div class="tab-content" data-content="security">
            <div class="glass-card panel fade-in-section">
              <h4 class="panel-sub-title"><i data-lucide="shield-check" class="w-4 h-4"></i> Security Settings</h4>
              <div class="toggle-list">
                <div class="toggle-row">
                  <span>Two-Factor Authentication</span>
                  <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                  <span>Login Notifications</span>
                  <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                </div>
                <div class="toggle-row">
                  <span>Biometric Login</span>
                  <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
                </div>
              </div>
              <div class="security-actions">
                <button class="btn btn-secondary btn-sm"><i data-lucide="key" class="w-3.5 h-3.5"></i> Change Password</button>
                <button class="btn btn-secondary btn-sm"><i data-lucide="smartphone" class="w-3.5 h-3.5"></i> Manage Devices</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Profile Modal -->
      <div class="modal-overlay" id="editModal">
        <div class="modal glass-card">
          <div class="modal-header">
            <h3>Edit Profile Details</h3>
            <button class="modal-close" id="closeEditBtn"><i data-lucide="x" class="w-4 h-4"></i></button>
          </div>
          <form id="profileForm" class="modal-form">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label-sm">Full Name</label>
                <input type="text" class="form-input" id="editName" placeholder="Your name" value="John Doe">
              </div>
              <div class="form-group">
                <label class="form-label-sm">Title / Role</label>
                <input type="text" class="form-input" placeholder="e.g. Full Stack Developer" value="Full Stack Dev">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label-sm">Location</label>
              <input type="text" class="form-input" id="editLocation" placeholder="City, Country" value="San Francisco, CA">
            </div>
            <div class="form-group">
              <div class="form-label-row">
                <label class="form-label-sm">Bio</label>
                <button type="button" class="ai-enhance-btn"><i data-lucide="sparkles" class="w-3 h-3"></i> Enhance with AI</button>
              </div>
              <textarea class="form-input" id="editBio" rows="3" placeholder="Tell us about yourself...">Passionate full-stack developer with 5+ years of experience building scalable web applications.</textarea>
            </div>
            <div class="modal-actions">
              <button type="button" class="btn btn-secondary btn-sm" id="cancelEditBtn">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </main>

  <script src="../scripts/main.js"></script>
  <script src="../scripts/mouse-tracking.js"></script>
  <script src="../scripts/profile.js"></script>
</body>
</html>
