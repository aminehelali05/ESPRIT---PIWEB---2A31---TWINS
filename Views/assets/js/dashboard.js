document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const headerToggle = document.getElementById('headerToggle');
  const sidebarClose = document.getElementById('sidebarClose');
  const sidebarLinks = document.querySelectorAll('.sidebar-link[data-page]');
  const pages = document.querySelectorAll('.dash-page');

  const usersTableBody = document.getElementById('usersTableBody');
  const usersEmptyState = document.getElementById('usersEmptyState');
  const usersSearchInput = document.getElementById('usersSearchInput');
  const usersRoleFilter = document.getElementById('usersRoleFilter');
  const usersStatusFilter = document.getElementById('usersStatusFilter');

  const userTotalStat = document.getElementById('userTotalStat');
  const userActiveStat = document.getElementById('userActiveStat');
  const userAdminStat = document.getElementById('userAdminStat');
  const userNewStat = document.getElementById('userNewStat');

  const overviewTotalUsers = document.getElementById('overviewTotalUsers');
  const overviewActiveUsers = document.getElementById('overviewActiveUsers');
  const overviewAdmins = document.getElementById('overviewAdmins');

  const activityList = document.getElementById('activityList');
  const aiInsightOutput = document.getElementById('aiInsightOutput');
  const toastContainer = document.getElementById('toastContainer');

  const userModal = document.getElementById('userModal');
  const userModalTitle = document.getElementById('userModalTitle');
  const userForm = document.getElementById('userForm');
  const userIdInput = document.getElementById('userId');
  const firstNameInput = document.getElementById('firstNameInput');
  const lastNameInput = document.getElementById('lastNameInput');
  const emailInput = document.getElementById('emailInput');
  const roleInput = document.getElementById('roleInput');
  const statusInput = document.getElementById('statusInput');
  const locationInput = document.getElementById('locationInput');

  const storageKey = 'diversity-is-users';
  const defaultUsers = [
    { id: 1001, firstName: 'Amina', lastName: 'Bennett', email: 'amina@diversity.is', role: 'admin', status: 'active', location: 'Paris, FR', createdAt: '2026-01-08', lastActive: '2m ago' },
    { id: 1002, firstName: 'Leo', lastName: 'Santos', email: 'leo.santos@diversity.is', role: 'manager', status: 'active', location: 'Lisbon, PT', createdAt: '2026-02-12', lastActive: '15m ago' },
    { id: 1003, firstName: 'Maya', lastName: 'Rossi', email: 'maya.rossi@diversity.is', role: 'member', status: 'offline', location: 'Milan, IT', createdAt: '2026-03-10', lastActive: '2d ago' },
    { id: 1004, firstName: 'Noah', lastName: 'Kim', email: 'noah.kim@diversity.is', role: 'member', status: 'active', location: 'Seoul, KR', createdAt: '2026-03-21', lastActive: 'Online' }
  ];

  const state = {
    users: loadUsers(),
    search: '',
    role: 'all',
    status: 'all'
  };

  if (headerToggle && sidebar) {
    headerToggle.addEventListener('click', () => sidebar.classList.add('open'));
  }
  if (sidebarClose && sidebar) {
    sidebarClose.addEventListener('click', () => sidebar.classList.remove('open'));
  }

  sidebarLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      switchDashboardPage(link.dataset.page);
    });
  });

  document.querySelectorAll('[data-go-page]').forEach((button) => {
    button.addEventListener('click', () => {
      switchDashboardPage(button.dataset.goPage);
      if (button.dataset.openUserModal === 'true') {
        openUserModal();
      }
      if (button.dataset.aiAction === 'summary') {
        runAiSummary();
      }
    });
  });

  document.querySelectorAll('.theme-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (typeof toggleTheme === 'function') {
        toggleTheme();
      }
    });
  });

  bindUserEvents();
  renderUsers();
  seedActivity();
  refreshIcons();

  function loadUsers() {
    const saved = localStorage.getItem(storageKey);
    if (!saved) {
      localStorage.setItem(storageKey, JSON.stringify(defaultUsers));
      return [...defaultUsers];
    }

    try {
      const parsed = JSON.parse(saved);
      return Array.isArray(parsed) && parsed.length ? parsed : [...defaultUsers];
    } catch {
      return [...defaultUsers];
    }
  }

  function saveUsers() {
    localStorage.setItem(storageKey, JSON.stringify(state.users));
  }

  function bindUserEvents() {
    const openButtons = ['openUserModalBtn', 'openUserModalFromOverview'];
    openButtons.forEach((id) => {
      const element = document.getElementById(id);
      if (element) {
        element.addEventListener('click', () => {
          switchDashboardPage('users');
          openUserModal();
        });
      }
    });

    const closeUserModal = document.getElementById('closeUserModal');
    const cancelUserModal = document.getElementById('cancelUserModal');
    if (closeUserModal) closeUserModal.addEventListener('click', closeModal);
    if (cancelUserModal) cancelUserModal.addEventListener('click', closeModal);

    userModal.addEventListener('click', (event) => {
      if (event.target === userModal) {
        closeModal();
      }
    });

    if (usersSearchInput) {
      usersSearchInput.addEventListener('input', (event) => {
        state.search = event.target.value.trim().toLowerCase();
        renderUsers();
      });
    }

    if (usersRoleFilter) {
      usersRoleFilter.addEventListener('change', (event) => {
        state.role = event.target.value;
        renderUsers();
      });
    }

    if (usersStatusFilter) {
      usersStatusFilter.addEventListener('change', (event) => {
        state.status = event.target.value;
        renderUsers();
      });
    }

    userForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const payload = {
        id: userIdInput.value ? Number(userIdInput.value) : Date.now(),
        firstName: firstNameInput.value.trim(),
        lastName: lastNameInput.value.trim(),
        email: emailInput.value.trim().toLowerCase(),
        role: roleInput.value,
        status: statusInput.value,
        location: locationInput.value.trim(),
        createdAt: userIdInput.value ? getUserById(Number(userIdInput.value)).createdAt : new Date().toISOString().slice(0, 10),
        lastActive: statusInput.value === 'active' ? 'Online' : '1d ago'
      };

      const duplicate = state.users.find((user) => user.email === payload.email && user.id !== payload.id);
      if (duplicate) {
        showToast('This email is already used by another user.', 'error');
        return;
      }

      if (userIdInput.value) {
        state.users = state.users.map((user) => (user.id === payload.id ? payload : user));
        addActivity(`${payload.firstName} ${payload.lastName} profile updated`, 'pencil-line', 'icon-purple');
        showToast('User updated successfully.', 'success');
      } else {
        state.users.unshift(payload);
        addActivity(`${payload.firstName} ${payload.lastName} added to workspace`, 'user-plus', 'icon-indigo');
        showToast('New user created.', 'success');
      }

      saveUsers();
      renderUsers();
      closeModal();
    });

    const aiSummarizeBtn = document.getElementById('aiSummarizeBtn');
    const aiPrioritizeBtn = document.getElementById('aiPrioritizeBtn');
    const aiFillDemoBtn = document.getElementById('aiFillDemoBtn');
    const aiSuggestRoleBtn = document.getElementById('aiSuggestRoleBtn');

    if (aiSummarizeBtn) aiSummarizeBtn.addEventListener('click', runAiSummary);
    if (aiPrioritizeBtn) aiPrioritizeBtn.addEventListener('click', runAiPrioritization);
    if (aiFillDemoBtn) aiFillDemoBtn.addEventListener('click', injectAiDemoUsers);
    if (aiSuggestRoleBtn) aiSuggestRoleBtn.addEventListener('click', suggestRoleFromInput);

    usersTableBody.addEventListener('click', (event) => {
      const target = event.target.closest('button[data-action]');
      if (!target) return;

      const userId = Number(target.dataset.id);
      if (!userId) return;

      if (target.dataset.action === 'edit') {
        const user = getUserById(userId);
        if (user) openUserModal(user);
      }

      if (target.dataset.action === 'delete') {
        state.users = state.users.filter((user) => user.id !== userId);
        saveUsers();
        renderUsers();
        addActivity(`User #${userId} removed`, 'trash-2', 'icon-amber');
        showToast('User deleted.', 'success');
      }

      if (target.dataset.action === 'toggle') {
        state.users = state.users.map((user) => {
          if (user.id !== userId) return user;
          const nextStatus = user.status === 'active' ? 'offline' : 'active';
          return { ...user, status: nextStatus, lastActive: nextStatus === 'active' ? 'Online' : '1h ago' };
        });
        saveUsers();
        renderUsers();
        addActivity(`User #${userId} status toggled`, 'refresh-cw', 'icon-emerald');
      }
    });
  }

  function switchDashboardPage(targetId) {
    sidebarLinks.forEach((link) => {
      link.classList.toggle('active', link.dataset.page === targetId);
    });

    pages.forEach((page) => {
      page.classList.toggle('active', page.id === targetId);
    });

    if (sidebar.classList.contains('open')) {
      sidebar.classList.remove('open');
    }

    const dashboardContent = document.querySelector('.dashboard-content');
    if (dashboardContent) {
      dashboardContent.scrollTop = 0;
    }

    refreshIcons();
  }

  function getFilteredUsers() {
    return state.users.filter((user) => {
      const fullName = `${user.firstName} ${user.lastName}`.toLowerCase();
      const searchHit = !state.search ||
        fullName.includes(state.search) ||
        user.email.toLowerCase().includes(state.search) ||
        user.location.toLowerCase().includes(state.search);
      const roleHit = state.role === 'all' || user.role === state.role;
      const statusHit = state.status === 'all' || user.status === state.status;
      return searchHit && roleHit && statusHit;
    });
  }

  function renderUsers() {
    const users = getFilteredUsers();
    usersTableBody.innerHTML = '';

    users.forEach((user) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="text-secondary">#${user.id}</td>
        <td>
          <div class="table-user">
            <img src="https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(user.firstName + ' ' + user.lastName)}" alt="${user.firstName}" class="table-avatar-img">
            <div><strong>${user.firstName} ${user.lastName}</strong><span class="table-email">${user.email}</span></div>
          </div>
        </td>
        <td><span class="role-badge role-${user.role === 'admin' ? 'admin' : 'user'}">${user.role}</span></td>
        <td><span class="status-indicator ${user.status === 'active' ? 'status-online' : 'status-offline'}"></span> ${user.status}</td>
        <td>${user.location}</td>
        <td class="text-secondary">${user.lastActive}</td>
        <td style="text-align:right;">
          <div class="table-actions" style="justify-content:flex-end;">
            <button class="table-action-btn" data-action="edit" data-id="${user.id}" title="Edit"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></button>
            <button class="table-action-btn" data-action="toggle" data-id="${user.id}" title="Toggle Status"><i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i></button>
            <button class="table-action-btn" data-action="delete" data-id="${user.id}" title="Delete"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
          </div>
        </td>
      `;
      usersTableBody.appendChild(row);
    });

    usersEmptyState.classList.toggle('hidden', users.length > 0);
    refreshStats();
    refreshIcons();
  }

  function refreshStats() {
    const allUsers = state.users;
    const now = new Date();
    const active = allUsers.filter((user) => user.status === 'active').length;
    const admins = allUsers.filter((user) => user.role === 'admin').length;
    const newThisMonth = allUsers.filter((user) => {
      const created = new Date(user.createdAt);
      return created.getMonth() === now.getMonth() && created.getFullYear() === now.getFullYear();
    }).length;

    userTotalStat.textContent = String(allUsers.length);
    userActiveStat.textContent = String(active);
    userAdminStat.textContent = String(admins);
    userNewStat.textContent = String(newThisMonth);

    overviewTotalUsers.textContent = String(allUsers.length);
    overviewActiveUsers.textContent = String(active);
    overviewAdmins.textContent = String(admins);
  }

  function openUserModal(user) {
    const isEdit = Boolean(user);
    userModalTitle.textContent = isEdit ? 'Edit User' : 'Create User';
    userIdInput.value = user?.id || '';
    firstNameInput.value = user?.firstName || '';
    lastNameInput.value = user?.lastName || '';
    emailInput.value = user?.email || '';
    roleInput.value = user?.role || 'member';
    statusInput.value = user?.status || 'active';
    locationInput.value = user?.location || '';
    userModal.classList.add('open');
    userModal.setAttribute('aria-hidden', 'false');
    refreshIcons();
  }

  function closeModal() {
    userModal.classList.remove('open');
    userModal.setAttribute('aria-hidden', 'true');
    userForm.reset();
    userIdInput.value = '';
  }

  function getUserById(userId) {
    return state.users.find((user) => user.id === userId);
  }

  function runAiSummary() {
    const all = state.users.length;
    const active = state.users.filter((user) => user.status === 'active').length;
    const managers = state.users.filter((user) => user.role === 'manager').length;
    const offline = all - active;
    aiInsightOutput.innerHTML = `
      <strong>AI Summary:</strong><br>
      • ${all} users in workspace, ${active} currently active.<br>
      • ${managers} managers covering team coordination.<br>
      • ${offline} offline accounts should get follow-up notifications.<br>
      • Suggested action: run weekly re-engagement campaign.
    `;
    addActivity('AI generated management summary', 'brain-circuit', 'icon-cyan');
  }

  function runAiPrioritization() {
    const offlineUsers = state.users.filter((user) => user.status === 'offline').slice(0, 3);
    if (!offlineUsers.length) {
      aiInsightOutput.innerHTML = '<strong>AI Prioritization:</strong><br>All users are active. No urgent follow-up needed.';
      return;
    }

    const formatted = offlineUsers.map((user, index) => `${index + 1}. ${user.firstName} ${user.lastName} (${user.email})`).join('<br>');
    aiInsightOutput.innerHTML = `<strong>AI Prioritization:</strong><br>${formatted}<br>Suggested action: schedule a direct check-in in the next 24 hours.`;
    addActivity('AI generated user follow-up queue', 'list-checks', 'icon-amber');
  }

  function injectAiDemoUsers() {
    const generated = [
      { id: Date.now() + 1, firstName: 'Sara', lastName: 'Nguyen', email: `sara.nguyen+${Date.now()}@diversity.is`, role: 'member', status: 'active', location: 'Berlin, DE', createdAt: new Date().toISOString().slice(0, 10), lastActive: 'Online' },
      { id: Date.now() + 2, firstName: 'Ibrahim', lastName: 'Khan', email: `ibrahim.khan+${Date.now()}@diversity.is`, role: 'manager', status: 'active', location: 'Dubai, AE', createdAt: new Date().toISOString().slice(0, 10), lastActive: '8m ago' }
    ];

    state.users = [...generated, ...state.users];
    saveUsers();
    renderUsers();
    aiInsightOutput.innerHTML = '<strong>AI Action Completed:</strong><br>2 high-quality demo users were generated and added to your workspace.';
    addActivity('AI generated two demo user profiles', 'sparkles', 'icon-purple');
    showToast('AI demo users added.', 'success');
  }

  function suggestRoleFromInput() {
    const text = `${firstNameInput.value} ${lastNameInput.value} ${emailInput.value}`.toLowerCase();
    let recommendation = 'member';

    if (text.includes('lead') || text.includes('manager')) {
      recommendation = 'manager';
    }
    if (text.includes('admin') || text.includes('ops')) {
      recommendation = 'admin';
    }

    roleInput.value = recommendation;
    showToast(`AI suggested role: ${recommendation}`, 'success');
  }

  function addActivity(message, icon, iconClass) {
    const item = document.createElement('div');
    item.className = 'dash-activity-item';
    item.innerHTML = `
      <div class="dash-activity-icon ${iconClass}"><i data-lucide="${icon}" class="w-4 h-4"></i></div>
      <div class="dash-activity-info"><p>${message}</p><span>Just now</span></div>
    `;
    activityList.prepend(item);

    const items = activityList.querySelectorAll('.dash-activity-item');
    if (items.length > 8) {
      items[items.length - 1].remove();
    }
    refreshIcons();
  }

  function seedActivity() {
    activityList.innerHTML = `
      <div class="dash-activity-item">
        <div class="dash-activity-icon icon-indigo"><i data-lucide="rocket" class="w-4 h-4"></i></div>
        <div class="dash-activity-info"><p><strong>Diversity.is</strong> dashboard activated</p><span>1 minute ago</span></div>
      </div>
      <div class="dash-activity-item">
        <div class="dash-activity-icon icon-emerald"><i data-lucide="database" class="w-4 h-4"></i></div>
        <div class="dash-activity-info"><p>User data loaded from local workspace storage</p><span>2 minutes ago</span></div>
      </div>
      <div class="dash-activity-item">
        <div class="dash-activity-icon icon-purple"><i data-lucide="shield-check" class="w-4 h-4"></i></div>
        <div class="dash-activity-info"><p>Role governance monitor is operational</p><span>3 minutes ago</span></div>
      </div>
    `;
  }

  function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type}`;
    toast.textContent = message;
    toastContainer.appendChild(toast);
    setTimeout(() => {
      toast.classList.add('hide');
      setTimeout(() => toast.remove(), 250);
    }, 2200);
  }

  function refreshIcons() {
    if (typeof lucide !== 'undefined') {
      lucide.createIcons({ attrs: { 'stroke-width': 1.75, class: 'lucide' } });
    }
  }
});
