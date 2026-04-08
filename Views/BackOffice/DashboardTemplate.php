<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Diversity.is user dashboard with rich CRUD, AI assistance, analytics and operations modules.">
  <title>Dashboard â€” Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg dashboard-body">
  <div class="dashboard-layout">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-brand">
          <div class="brand-icon">â¬¡</div>
          <span class="sidebar-brand-text">Diversity.is</span>
        </div>
        <button class="sidebar-close" id="sidebarClose"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>

      <nav class="sidebar-nav">
        <a href="#overview" class="sidebar-link active" data-page="overview"><i data-lucide="layout-dashboard" class="w-4 h-4"></i><span>Dashboard</span></a>
        <a href="#users" class="sidebar-link" data-page="users"><i data-lucide="users" class="w-4 h-4"></i><span>User Management</span></a>
        <a href="#social" class="sidebar-link" data-page="social"><i data-lucide="share-2" class="w-4 h-4"></i><span>Social Feed</span></a>
        <a href="#clients" class="sidebar-link" data-page="clients"><i data-lucide="briefcase" class="w-4 h-4"></i><span>Clients</span></a>
        <a href="#employees" class="sidebar-link" data-page="employees"><i data-lucide="contact" class="w-4 h-4"></i><span>Employees</span></a>
        <a href="#products" class="sidebar-link" data-page="products"><i data-lucide="package" class="w-4 h-4"></i><span>Products</span></a>
        <a href="#sales" class="sidebar-link" data-page="sales"><i data-lucide="shopping-cart" class="w-4 h-4"></i><span>Sales</span></a>
        <a href="#suppliers" class="sidebar-link" data-page="suppliers"><i data-lucide="truck" class="w-4 h-4"></i><span>Suppliers</span></a>
      </nav>

      <div class="sidebar-footer">
        <a href="auth.html" class="sidebar-link sidebar-logout"><i data-lucide="log-out" class="w-4 h-4"></i><span>Sign Out</span></a>
      </div>
    </aside>

    <main class="dashboard-main" id="dashboardMain">
      <header class="dashboard-header">
        <div class="header-left">
          <button class="header-menu-btn" id="headerToggle"><i data-lucide="menu" class="w-5 h-5"></i></button>
          <div class="header-search">
            <i data-lucide="search" class="w-4 h-4 search-icon"></i>
            <input type="text" placeholder="Search dashboard modules..." class="search-input" id="globalSearchInput">
          </div>
        </div>
        <div class="header-right">
          <button class="theme-toggle" aria-label="Toggle theme">
            <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          </button>
          <button class="header-icon-btn" id="notifBtn"><i data-lucide="bell" class="w-5 h-5"></i><span class="notif-badge">4</span></button>
          <div class="header-divider"></div>
          <div class="nav-profile">
            <button class="nav-profile-btn" aria-label="User menu">
              <div class="nav-avatar">AD</div>
              <span class="user-role-label hidden-mobile" style="font-size: 0.8rem; margin-left: var(--space-xs); font-weight: 500;">Admin User</span>
            </button>
            <div class="nav-dropdown">
              <div class="nav-dropdown-header">
                <strong>Admin User</strong>
                <span>admin@diversity.is</span>
              </div>
              <a href="profile.html" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
              <a href="TemplateHome.php" class="nav-dropdown-item"><i data-lucide="home" class="w-4 h-4"></i> Back to Home</a>
              <a href="auth.html" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
            </div>
          </div>
        </div>
      </header>

      <div class="dashboard-content">
        <section class="dash-page active" id="overview">
          <div class="page-header">
            <div>
              <h2 class="page-title">Diversity.is Dashboard</h2>
              <p class="page-subtitle">A complete command center for users, operations, and AI-guided decisions.</p>
            </div>
            <button class="btn btn-primary btn-sm" id="openUserModalFromOverview"><i data-lucide="plus" class="w-4 h-4"></i> Add User</button>
          </div>

          <div class="stats-row">
            <div class="dash-stat-card">
              <div class="dash-stat-top">
                <div>
                  <p class="dash-stat-label">Total Users</p>
                  <h3 class="dash-stat-value" id="overviewTotalUsers" data-target="0">0</h3>
                  <p class="dash-stat-trend trend-up"><i data-lucide="trending-up" class="w-3.5 h-3.5"></i> Real-time CRUD synced</p>
                </div>
                <div class="dash-stat-icon icon-indigo"><i data-lucide="users" class="w-5 h-5 icon-gradient-primary"></i></div>
              </div>
            </div>
            <div class="dash-stat-card">
              <div class="dash-stat-top">
                <div>
                  <p class="dash-stat-label">Active Profiles</p>
                  <h3 class="dash-stat-value" id="overviewActiveUsers" data-target="0">0</h3>
                  <p class="dash-stat-trend trend-up"><i data-lucide="activity" class="w-3.5 h-3.5"></i> Online and productive</p>
                </div>
                <div class="dash-stat-icon icon-emerald"><i data-lucide="user-check" class="w-5 h-5 icon-gradient-success"></i></div>
              </div>
            </div>
            <div class="dash-stat-card">
              <div class="dash-stat-top">
                <div>
                  <p class="dash-stat-label">Admin Accounts</p>
                  <h3 class="dash-stat-value" id="overviewAdmins" data-target="0">0</h3>
                  <p class="dash-stat-trend trend-up"><i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Governance and control</p>
                </div>
                <div class="dash-stat-icon icon-purple"><i data-lucide="shield" class="w-5 h-5 icon-gradient-purple"></i></div>
              </div>
            </div>
            <div class="dash-stat-card">
              <div class="dash-stat-top">
                <div>
                  <p class="dash-stat-label">AI Suggestions</p>
                  <h3 class="dash-stat-value" id="overviewSuggestions" data-target="12">0</h3>
                  <p class="dash-stat-trend trend-up"><i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Ready to apply</p>
                </div>
                <div class="dash-stat-icon icon-amber"><i data-lucide="bot" class="w-5 h-5 icon-gradient-warning"></i></div>
              </div>
            </div>
          </div>

          <div class="dash-grid-2">
            <div class="dash-card">
              <h3 class="dash-card-title">Live Activity</h3>
              <div class="dash-activity-list" id="activityList"></div>
            </div>
            <div class="dash-card">
              <h3 class="dash-card-title">Quick Actions</h3>
              <div class="quick-actions-grid">
                <button class="quick-action-btn" data-go-page="users" data-open-user-modal="true">
                  <div class="quick-action-icon icon-indigo"><i data-lucide="user-plus" class="w-5 h-5 icon-gradient-primary"></i></div>
                  <span>Create User</span>
                </button>
                <button class="quick-action-btn" data-go-page="users" data-ai-action="summary">
                  <div class="quick-action-icon icon-emerald"><i data-lucide="brain-circuit" class="w-5 h-5 icon-gradient-success"></i></div>
                  <span>AI Summary</span>
                </button>
                <button class="quick-action-btn" data-go-page="sales">
                  <div class="quick-action-icon icon-amber"><i data-lucide="line-chart" class="w-5 h-5 icon-gradient-warning"></i></div>
                  <span>Sales View</span>
                </button>
                <button class="quick-action-btn" data-go-page="suppliers">
                  <div class="quick-action-icon icon-purple"><i data-lucide="truck" class="w-5 h-5 icon-gradient-purple"></i></div>
                  <span>Suppliers</span>
                </button>
              </div>
            </div>
          </div>
        </section>

        <section class="dash-page" id="users">
          <div class="page-breadcrumbs"><span>Dashboard</span><i data-lucide="chevron-right" class="w-3 h-3"></i><span>User Management</span></div>
          <div class="page-header" style="margin-top: var(--space-xs);">
            <div>
              <h2 class="page-title">User Management</h2>
            </div>
            <button class="btn btn-primary btn-sm" id="openUserModalBtn"><i data-lucide="plus" class="w-4 h-4"></i> New User</button>
          </div>

          <div class="kpi-mini-grid">
            <div class="kpi-mini"><span>Total users</span><strong id="userTotalStat">0</strong></div>
            <div class="kpi-mini"><span>Active now</span><strong id="userActiveStat">0</strong></div>
            <div class="kpi-mini"><span>Admins</span><strong id="userAdminStat">0</strong></div>
            <div class="kpi-mini"><span>New this month</span><strong id="userNewStat">0</strong></div>
          </div>

          <div class="dash-card">
            <div class="filters-row">
              <div class="filter-group form-group">
                <label>Search</label>
                <div class="input-with-icon">
                  <i data-lucide="search" class="w-4 h-4 text-secondary"></i>
                  <input type="text" class="form-control" id="usersSearchInput" placeholder="Name, email, location...">
                </div>
              </div>
              <div class="filter-group form-group" style="min-width: 170px;">
                <label>Role</label>
                <select class="form-control" id="usersRoleFilter">
                  <option value="all">All roles</option>
                  <option value="admin">Admin</option>
                  <option value="manager">Manager</option>
                  <option value="member">Member</option>
                </select>
              </div>
              <div class="filter-group form-group" style="min-width: 170px;">
                <label>Status</label>
                <select class="form-control" id="usersStatusFilter">
                  <option value="all">All status</option>
                  <option value="active">Active</option>
                  <option value="offline">Offline</option>
                </select>
              </div>
            </div>

            <div class="table-wrap">
              <table class="dash-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Last Active</th>
                    <th style="text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody id="usersTableBody"></tbody>
              </table>
              <div class="empty-state hidden" id="usersEmptyState">
                <i data-lucide="users" class="w-5 h-5"></i>
                <p>No users match this filter set.</p>
              </div>
            </div>
          </div>

          <div class="dash-card ai-panel">
            <div class="dash-card-header-row">
              <h3 class="dash-card-title">AI Operations Assistant</h3>
              <span class="badge-pill">beta</span>
            </div>
            <div class="ai-actions">
              <button class="btn btn-secondary btn-sm" id="aiSummarizeBtn"><i data-lucide="brain-circuit" class="w-4 h-4"></i> Generate Summary</button>
              <button class="btn btn-secondary btn-sm" id="aiPrioritizeBtn"><i data-lucide="list-checks" class="w-4 h-4"></i> Prioritize Follow-up</button>
              <button class="btn btn-secondary btn-sm" id="aiFillDemoBtn"><i data-lucide="sparkles" class="w-4 h-4"></i> Add AI Demo Users</button>
            </div>
            <div class="ai-output" id="aiInsightOutput">AI ready. Choose an action to generate guidance.</div>
          </div>

          <div class="module-rich-grid" style="margin-top: var(--space-xl);">
            <article class="module-panel">
              <h3>Frontend Highlights</h3>
              <p>Modern glass UI, deep interaction states, and richer dashboard navigation for a premium experience.</p>
              <div class="insight-chip-row">
                <span class="badge-pill">Polished UI</span>
                <span class="badge-pill">Fast CRUD</span>
              </div>
            </article>
            <article class="module-panel">
              <h3>User Journey Health</h3>
              <p>Onboarding, activation, and retention checkpoints are visible directly in this page for quick actions.</p>
              <ul class="mini-check-list">
                <li><i data-lucide="check-circle-2" class="w-4 h-4"></i> Onboarding complete rate up</li>
                <li><i data-lucide="check-circle-2" class="w-4 h-4"></i> Role assignment automation ready</li>
                <li><i data-lucide="check-circle-2" class="w-4 h-4"></i> Follow-up list generated by AI</li>
              </ul>
            </article>
            <article class="module-panel">
              <h3>Roadmap Timeline</h3>
              <ul class="timeline-list">
                <li><span>Q2</span> Advanced dashboards and team heatmaps</li>
                <li><span>Q3</span> Smarter recommendations by user segment</li>
                <li><span>Q4</span> Full analytics suite and export center</li>
              </ul>
            </article>
          </div>
        </section>

        <section class="dash-page" id="clients">
          <div class="page-header"><div><h2 class="page-title">Clients</h2><p class="page-subtitle">Portfolio quality, retention, and relationship health.</p></div></div>
          <div class="module-rich-grid">
            <article class="module-panel"><h3>Top Accounts</h3><p>Alpha Retail, BlueCore, Nova Supply</p><span class="badge-pill">92% retention</span></article>
            <article class="module-panel"><h3>Renewal Risk</h3><p>3 accounts need immediate outreach this week.</p><span class="badge-pill badge-warn">High Priority</span></article>
            <article class="module-panel"><h3>Client Satisfaction</h3><p>Average score 4.7/5 from last 30 days.</p><span class="badge-pill">+0.3 QoQ</span></article>
          </div>
        </section>

        <section class="dash-page" id="employees">
          <div class="page-header"><div><h2 class="page-title">Employees</h2><p class="page-subtitle">Capacity, talent distribution, and team performance.</p></div></div>
          <div class="module-rich-grid">
            <article class="module-panel"><h3>Team Capacity</h3><p>84% average utilization across active squads.</p><span class="badge-pill">Balanced</span></article>
            <article class="module-panel"><h3>Skill Gaps</h3><p>Cloud architecture and QA automation need recruitment focus.</p><span class="badge-pill badge-warn">Action Needed</span></article>
            <article class="module-panel"><h3>Recognition</h3><p>8 employees exceeded sprint objectives this cycle.</p><span class="badge-pill">Celebrate Wins</span></article>
          </div>
        </section>

        <section class="dash-page" id="products">
          <div class="page-header"><div><h2 class="page-title">Products</h2><p class="page-subtitle">Roadmap execution and release confidence.</p></div></div>
          <div class="module-rich-grid">
            <article class="module-panel"><h3>Release Train</h3><p>v3.2, v3.3, and mobile patch are on-track.</p><span class="badge-pill">3 Planned</span></article>
            <article class="module-panel"><h3>Backlog Health</h3><p>34 backlog items ready for sprint planning.</p><span class="badge-pill">Refined</span></article>
            <article class="module-panel"><h3>Quality Gate</h3><p>Critical bug count reduced by 41% this month.</p><span class="badge-pill">Improving</span></article>
          </div>
        </section>

        <section class="dash-page" id="sales">
          <div class="page-header"><div><h2 class="page-title">Sales</h2><p class="page-subtitle">Pipeline visibility and conversion intelligence.</p></div></div>
          <div class="module-rich-grid">
            <article class="module-panel"><h3>Pipeline Value</h3><p>$1.8M open opportunities this quarter.</p><span class="badge-pill">+11%</span></article>
            <article class="module-panel"><h3>Conversion Rate</h3><p>31% close rate across qualified leads.</p><span class="badge-pill">Healthy</span></article>
            <article class="module-panel"><h3>Forecast Confidence</h3><p>AI estimates monthly target achievement at 96%.</p><span class="badge-pill">Stable</span></article>
          </div>
        </section>

        <section class="dash-page" id="suppliers">
          <div class="page-header"><div><h2 class="page-title">Suppliers</h2><p class="page-subtitle">Vendor reliability, procurement timing, and cost visibility.</p></div></div>
          <div class="module-rich-grid">
            <article class="module-panel"><h3>On-time Delivery</h3><p>91% of deliveries arrived on schedule in March.</p><span class="badge-pill">Reliable</span></article>
            <article class="module-panel"><h3>Cost Variance</h3><p>Material category C exceeded budget by 6%.</p><span class="badge-pill badge-warn">Watchlist</span></article>
            <article class="module-panel"><h3>Contract Renewal</h3><p>2 suppliers pending renegotiation in 18 days.</p><span class="badge-pill">Upcoming</span></article>
          </div>
        </section>
      </div>
    </main>
  </div>

  <div class="user-modal" id="userModal" aria-hidden="true">
    <div class="user-modal-card">
      <div class="user-modal-header">
        <h3 id="userModalTitle">Create User</h3>
        <button class="table-action-btn" id="closeUserModal" title="Close"><i data-lucide="x" class="w-4 h-4"></i></button>
      </div>
      <form id="userForm">
        <input type="hidden" id="userId">
        <div class="form-row-2">
          <div class="form-group"><label>First Name</label><input type="text" class="form-control" id="firstNameInput" required></div>
          <div class="form-group"><label>Last Name</label><input type="text" class="form-control" id="lastNameInput" required></div>
        </div>
        <div class="form-group"><label>Email</label><input type="email" class="form-control" id="emailInput" required></div>
        <div class="form-row-2">
          <div class="form-group">
            <label>Role</label>
            <select class="form-control" id="roleInput" required>
              <option value="member">Member</option>
              <option value="manager">Manager</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" id="statusInput" required>
              <option value="active">Active</option>
              <option value="offline">Offline</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Location</label><input type="text" class="form-control" id="locationInput" required></div>
        <div class="form-actions-right">
          <button type="button" class="btn" id="aiSuggestRoleBtn"><i data-lucide="sparkles" class="w-4 h-4"></i> AI Suggest Role</button>
          <button type="button" class="btn" id="cancelUserModal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="saveUserBtn">Save User</button>
        </div>
      </form>
    </div>
  </div>

  <div class="toast-stack" id="toastContainer"></div>

  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/dashboard.js"></script>
</body>
</html>

