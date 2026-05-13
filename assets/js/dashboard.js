// --- 1. Theme Logic ---
function toggleTheme() {
    const html = document.documentElement;
    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
}

// Init Theme
if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark')
} else {
    document.documentElement.classList.remove('dark')
}

// --- 2. Main Dashboard Logic (Integrated from main app) ---

const DASHBOARD_ROUTES = {
    login: 'auth.php?mode=login',
    profile: 'profile.php',
    messages: 'social.php'
};

const DASHBOARD_API_BASE = '../../api';

const nativeFetch = window.fetch.bind(window);
window.fetch = function (resource, init) {
    if (typeof resource === 'string') {
        resource = resource.replace(/^(\.\/)?\.\.\/api\//, `${DASHBOARD_API_BASE}/`);
    }
    return nativeFetch(resource, init);
};

// Dashboard script with full functionality integration
console.log('Dashboard script loading with full functionality...');
// --- AI Features Integration ---
document.addEventListener('DOMContentLoaded', function () {
    // Generate Title
    const dashGenerateTitleBtn = document.getElementById('dashGenerateTitleBtn');
    if (dashGenerateTitleBtn) {
        dashGenerateTitleBtn.addEventListener('click', async function () {
            const contentInput = document.querySelector('#story-tab #storyContent');
            const titleInput = document.querySelector('#story-tab #storyTitle');

            if (!contentInput || !titleInput) return;

            const content = contentInput.value.trim();
            if (!content || content.length < 50) {
                Swal.fire({
                    title: 'Info',
                    text: 'Please enter at least 50 characters of content first.',
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                return;
            }

            const originalText = this.innerHTML;
            this.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Generating...';
            this.disabled = true;
            if (typeof lucide !== 'undefined') lucide.createIcons();

            try {
                if (typeof AI_Features === 'undefined') throw new Error('AI features not loaded');

                const result = await AI_Features.generateTitleDescription(content);

                if (result.title) {
                    titleInput.value = result.title;
                    Swal.fire({
                        title: 'Success',
                        text: 'Title generated successfully!',
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            } catch (error) {
                console.error('AI generation error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to generate title',
                    icon: 'error',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            } finally {
                this.innerHTML = originalText;
                this.disabled = false;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        });
    }

    // Improve Text
    const dashImproveTextBtn = document.getElementById('dashImproveTextBtn');
    if (dashImproveTextBtn) {
        dashImproveTextBtn.addEventListener('click', async function () {
            const contentInput = document.querySelector('#story-tab #storyContent');

            if (!contentInput) return;

            const content = contentInput.value.trim();
            if (!content || content.length < 20) {
                Swal.fire({
                    title: 'Info',
                    text: 'Please enter some content to improve.',
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                return;
            }

            const originalText = this.innerHTML;
            this.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Improving...';
            this.disabled = true;
            if (typeof lucide !== 'undefined') lucide.createIcons();

            try {
                if (typeof AI_Features === 'undefined') throw new Error('AI features not loaded');

                const result = await AI_Features.improveText(content);

                if (result.improvedText) {
                    contentInput.value = result.improvedText;
                    Swal.fire({
                        title: 'Success',
                        text: 'Text improved successfully!',
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            } catch (error) {
                console.error('AI improvement error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to improve text',
                    icon: 'error',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            } finally {
                this.innerHTML = originalText;
                this.disabled = false;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        });
    }
});

// DOM Elements
const sidebar = document.getElementById("sidebar");
const sidebarToggle = document.getElementById("sidebarToggle");
const headerToggle = document.getElementById("headerToggle");
const navItems = document.querySelectorAll(".nav-item");
const pages = document.querySelectorAll(".page");
const notificationBtn = document.getElementById("notificationBtn");
const notificationDropdown = document.getElementById("notificationDropdown");
const userMenu = document.getElementById("userMenu");
const userDropdown = document.getElementById("userDropdown");
const createActionBtn = document.getElementById("createActionBtn");
const createModal = document.getElementById("createModal");
const closeModal = document.getElementById("closeModal");
const tabBtns = document.querySelectorAll(".tab-btn");
const tabContents = document.querySelectorAll(".tab-content");
const actionForm = document.getElementById('actionForm');
const resourceForm = document.getElementById('resourceForm');

// Global State (integrated from main app)
let isAuthenticated = false;
let currentUser = null;
let actionsData = [];
let resourcesData = [];
let dashboardUsersData = [];

// Init
document.addEventListener('DOMContentLoaded', async () => {
    // Initialize icons after DOM is loaded
    lucide.createIcons();

    // Check auth status
    await checkAuthStatus();

    // Start status heartbeat if authenticated
    if (isAuthenticated) {
        startStatusHeartbeat();
    }

    initializeApp();

    // Set up form submissions
    actionForm?.addEventListener('submit', submitActionForm);
    resourceForm?.addEventListener('submit', submitResourceForm);

    // Location picker buttons
    document.querySelectorAll('.pick-location-btn').forEach(button => {
        button.addEventListener('click', function () {
            const formType = this.getAttribute('data-form');
            openLocationPicker(formType);
        });
    });

    // Modal controls
    document.getElementById('confirmLocationBtn')?.addEventListener('click', confirmLocationSelection);
    document.getElementById('cancelLocationPicker')?.addEventListener('click', () => {
        document.getElementById('locationPickerModal').classList.add('hidden');
    });
    document.getElementById('closeLocationPicker')?.addEventListener('click', () => {
        document.getElementById('locationPickerModal').classList.add('hidden');
    });

    // Search inputs
    document.getElementById('my-actions-search')?.addEventListener('input', filterActionsTable);
    document.getElementById('my-resources-search')?.addEventListener('input', filterResourcesTable);

    // Status filters
    document.getElementById('my-actions-status-filter')?.addEventListener('change', filterActionsTable);
    document.getElementById('my-resources-status-filter')?.addEventListener('change', filterResourcesTable);

    // Reports filters
    document.getElementById('searchReports')?.addEventListener('input', filterReportsTable);
    document.getElementById('statusFilter')?.addEventListener('change', filterReportsTable);
    document.getElementById('categoryFilter')?.addEventListener('change', filterReportsTable);

    // Users module controls
    document.getElementById('usersSearch')?.addEventListener('input', filterDashboardUsersTable);
    document.getElementById('addUserBtn')?.addEventListener('click', openCreateDashboardUserDialog);

    // Handle URL hash navigation - check if a specific page should be shown
    const hash = window.location.hash.replace('#', '');
    if (hash) {
        // Map hash values to page names (they should match the section IDs and data-page values)
        const pageMap = {
            'overview': 'overview',
            'actions': 'actions',
            'stories': 'stories',
            'users': 'users',
            'challenges': 'challenges',
            'reminders': 'reminders',
            'contact': 'contact'
        };

        // Additional pages for admins only
        const adminPages = {
            'reports': 'reports',
            'moderation': 'stories_moderation',
            'stories_moderation': 'stories_moderation'
        };

        // Check if user has permission to access the requested page
        const userRole = currentUser?.role;
        const isAdmin = userRole === 'admin' || userRole === 'administrator';
        let targetPage = pageMap[hash] || adminPages[hash];

        // If it's an admin-only page and user is not admin, redirect to overview
        if ((adminPages[hash] !== undefined) && !isAdmin) {
            targetPage = 'overview';
            // Update URL hash to reflect redirected page
            window.location.hash = 'overview';
        }

        if (targetPage) {
            // Show the requested page after a brief delay to ensure initialization is complete
            setTimeout(() => {
                showPage(targetPage);

                // Update the active state in the navigation
                document.querySelectorAll('.nav-item[data-page]').forEach(navItem => {
                    navItem.classList.remove('active', 'text-zinc-900', 'dark:text-zinc-100', 'bg-zinc-100', 'dark:bg-white/5');
                    navItem.classList.add('text-zinc-600', 'dark:text-zinc-400');
                });

                const activeNavItem = document.querySelector(`.nav-item[data-page="${targetPage}"]`);
                if (activeNavItem) {
                    activeNavItem.classList.remove('text-zinc-600', 'dark:text-zinc-400');
                    activeNavItem.classList.add('active', 'text-zinc-900', 'dark:text-zinc-100', 'bg-zinc-100', 'dark:bg-white/5');
                }
            }, 100);
        }
    }
});

// Initialize UI based on user role after loading user data
function initUIForRole() {
    const userRole = currentUser?.role;
    const isAdmin = userRole === 'admin' || userRole === 'administrator';

    // Show/hide admin-only navigation items
    const adminNavItems = document.querySelectorAll('.nav-item[data-admin-only]');
    const allNavItems = document.querySelectorAll('.nav-item[data-page]');

    // First, show all items and then hide admin-only items if not admin
    allNavItems.forEach(item => {
        item.style.display = 'flex'; // Make sure all items are visible initially
    });

    if (!isAdmin) {
        // Hide admin-only navigation items
        document.querySelectorAll('.nav-item[data-admin-only="true"]').forEach(item => {
            item.style.display = 'none';
        });

        // Special handling for admin-only links that don't have the data attribute
        // Check specific admin pages that should be hidden
        const allLinks = document.querySelectorAll('.nav-item[href*="reports"], .nav-item[href*="moderation"], .nav-item[href*="stories_moderation"]');
        allLinks.forEach(link => {
            // Check if the link points to an admin section
            const href = link.getAttribute('href') || '';
            if (href.includes('reports') || href.includes('moderation') || href.includes('stories_moderation')) {
                link.style.display = 'none';
            }
        });
    } else {
        // For admins, make sure all items are visible (especially ones that might have been hidden)
        document.querySelectorAll('.nav-item[data-admin-only="true"]').forEach(item => {
            item.style.display = 'flex';
        });
    }
}

// Additional initialization to ensure icons are properly loaded
if (document.readyState === 'loading') {
    // If the document is still loading, wait for it to be complete
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            lucide.createIcons();
        }, 100);
    });
} else {
    // If the document is already loaded, run immediately
    setTimeout(() => {
        lucide.createIcons();
    }, 100);
}

// Utility function to refresh icons after dynamic content changes
function refreshIcons() {
    lucide.createIcons();
}

async function checkAuthStatus() {
    if (window.__AUTH_USER && window.__AUTH_USER.id !== undefined && window.__AUTH_USER.id !== null) {
        isAuthenticated = true;
        currentUser = window.__AUTH_USER;
        window.currentUser = currentUser;
        return;
    }

    // Use Auth helper if available
    if (typeof Auth !== 'undefined') {
        const user = await Auth.checkSession();
        if (user) {
            isAuthenticated = true;
            currentUser = user;
            window.currentUser = currentUser;
        } else {
            console.log('Util: Auth check failed, redirecting...');
            isAuthenticated = false;
            currentUser = null;
            window.location.href = DASHBOARD_ROUTES.login;
        }
    } else {
        // Fallback to manual check
        try {
            const response = await fetch("./../api/users/check_auth.php");

            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = DASHBOARD_ROUTES.login;
                    return;
                }
                throw new Error(`HTTP Error: ${response.status} ${response.statusText}`);
            }

            let result;
            try {
                const responseText = await response.text();
                if (responseText.trim().startsWith('<!DOCTYPE') || responseText.trim().startsWith('<html') ||
                    responseText.trim().startsWith('<b>') || responseText.trim().startsWith('<br')) {
                    console.error("Auth API returned HTML instead of JSON:", responseText.substring(0, 200) + "...");
                    throw new Error("Server returned HTML instead of JSON. Check for PHP errors.");
                }
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error("Error parsing auth response:", parseError);
                throw new Error("Invalid JSON response from server");
            }

            if (result.success && result.authenticated && result.user) {
                isAuthenticated = true;
                currentUser = result.user;
                window.currentUser = currentUser;
            } else {
                isAuthenticated = false;
                currentUser = null;
                window.location.href = DASHBOARD_ROUTES.login;
            }
        } catch (error) {
            console.error("Failed to check authentication status:", error);
            window.location.href = DASHBOARD_ROUTES.login;
            isAuthenticated = false;
            currentUser = null;
        }
    }
}

// Status heartbeat functionality to keep user status updated
let statusHeartbeatInterval = null;
let isPageVisible = true;

// Start status heartbeat to update online status regularly
function startStatusHeartbeat() {
    if (!isAuthenticated || !currentUser) return;

    // Update status immediately
    updateCurrentUserStatus('online');

    // Set up periodic updates (every 2 minutes)
    if (statusHeartbeatInterval) {
        clearInterval(statusHeartbeatInterval);
    }

    // Update status every 2 minutes when page is visible
    statusHeartbeatInterval = setInterval(() => {
        if (isPageVisible && isAuthenticated) {
            updateCurrentUserStatus('online');
        }
    }, 2 * 60 * 1000); // Update every 2 minutes

    // Listen for visibility changes
    document.addEventListener('visibilitychange', () => {
        isPageVisible = !document.hidden;
        if (isPageVisible && isAuthenticated) {
            // Update status when page becomes visible again
            updateCurrentUserStatus('online');
        }
    });
}

// Update current user's status via API
async function updateCurrentUserStatus(status) {
    if (!currentUser || !currentUser.id) return;

    try {
        const response = await fetch('../api/users/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                status: status,
                user_id: currentUser.id
            })
        });

        const result = await response.json();

        if (!result.success) {
            console.error('Failed to update status:', result.message);
        }
    } catch (error) {
        console.error('Error updating status:', error);
    }
}

async function initializeApp() {
    await populateCountryDropdowns();
    setupEventListeners();

    // Load all data after checking auth
    if (isAuthenticated) {
        await loadAllData();
        // Update user info in the UI
        updateUserInfo();
    }
}

async function loadAllData() {
    await loadUserActions();
    await loadUserResources();
    await loadUserStats();
    await loadRecentActivity();
    await loadUserReminders(); // Load user reminders as well
    await loadUserStories(); // Load user stories as well
    await loadEngagementChart(); // Load engagement chart
    await loadRecommendations(); // Load AI recommendations
    await loadUpcomingActions(); // Load upcoming actions
    await loadDashboardUsers();
    // Load gamification challenges via dedicated script
    if (window.initChallengesDashboard) {
        await window.initChallengesDashboard();
    }

    // Initialize UI elements based on user role after all data is loaded
    if (currentUser) {
        initUIForRole(); // Fixed function name
    }
}

async function loadUserStories() {
    try {
        // Determine API endpoint based on user role
        let storiesEndpoint;
        let statsEndpoint;

        if (currentUser && currentUser.role && (currentUser.role === 'admin' || currentUser.role === 'administrator')) {
            // Admins can see all stories
            storiesEndpoint = './../api/admin/get_all_stories_admin.php';
            statsEndpoint = './../api/admin/get_all_story_stats.php'; // This endpoint should aggregate all story stats
        } else {
            // Regular users see only their own stories
            storiesEndpoint = `./../api/stories/get_my_stories.php?user_id=${currentUser.id}`;
            statsEndpoint = `./../api/stories/get_story_stats.php?user_id=${currentUser.id}`;
        }

        // Load stories and stats in parallel
        const [storiesResponse, statsResponse] = await Promise.allSettled([
            fetch(storiesEndpoint),
            fetch(statsEndpoint)
        ]);

        // Handle stories response
        let stories = [];
        if (storiesResponse.status === 'fulfilled' && storiesResponse.value.ok) {
            const storiesResult = await storiesResponse.value.json();
            stories = storiesResult.stories || storiesResult.data || [];
            renderStoriesTable(stories);
        } else {
            console.error('Failed to load stories:', storiesResponse.reason || "Network error");
            renderStoriesTable([]);
        }

        // Handle stats response
        if (statsResponse.status === 'fulfilled' && statsResponse.value.ok) {
            const statsResult = await statsResponse.value.json();
            if (statsResult.success) {
                // Update story statistics in the UI
                const totalStoriesEl = document.getElementById('myTotalStoriesCount');
                const publishedStoriesEl = document.getElementById('myPublishedStoriesCount');
                const viewsStoriesEl = document.getElementById('myStoriesViewsCount');

                if (totalStoriesEl) totalStoriesEl.textContent = statsResult.data.total_stories || statsResult.data.totalCount || 0;
                if (publishedStoriesEl) publishedStoriesEl.textContent = statsResult.data.published_stories || statsResult.data.publishedCount || 0;
                if (viewsStoriesEl) viewsStoriesEl.textContent = statsResult.data.total_views || statsResult.data.totalViews || 0;
            } else {
                console.error('Failed to load story stats:', statsResult.message);
            }
        } else {
            console.error('Failed to load story stats:', statsResponse.reason || "Network error");
        }
    } catch (error) {
        console.error('Error loading stories or stats:', error);
        renderStoriesTable([]);
    }
}

function renderStoriesTable(stories) {
    const tbody = document.getElementById('my-stories-table-body');
    if (!tbody) return;

    if (stories.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                    <div class="flex flex-col items-center justify-center">
                        <i data-lucide="book-open" class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mb-3"></i>
                        <p class="text-sm">No stories found</p>
                        <p class="text-xs text-zinc-500 mt-1">Create your first story to get started!</p>
                    </div>
                </td>
            </tr>
        `;
        lucide.createIcons();
        return;
    }

    tbody.innerHTML = stories.map(story => {
        // Determine status badge styling
        let statusClass = 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50';
        if (story.status.toLowerCase() === 'pending') {
            statusClass = 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50';
        } else if (story.status.toLowerCase() === 'rejected') {
            statusClass = 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50';
        }

        // Determine creator information
        const creatorName = story.author_name || story.creator?.name || 'Unknown Creator';
        const creatorAvatar = story.author_avatar || story.creator?.avatar || `https://api.dicebear.com/7.x/avataaars/svg?seed=${creatorName}`;

        return `
            <tr class="hover:bg-zinc-50 dark:hover:bg-white/5 transition-colors border-b border-zinc-100 dark:border-zinc-800/50">
                <td class="py-3 px-5 font-medium text-zinc-900 dark:text-zinc-200 max-w-xs truncate">${story.title || 'Untitled'}</td>
                <td class="py-3 px-5">${story.theme || 'N/A'}</td>
                <td class="py-3 px-5"><span class="px-2 py-1 rounded-full text-[10px] font-semibold ${statusClass}">${story.status}</span></td>
                <td class="py-3 px-5">${story.views || 0}</td>
                <td class="py-3 px-5 text-zinc-500">${story.date || 'N/A'}</td>
                <td class="py-3 px-5">
                    <div class="flex gap-2">
                        <button onclick="viewStory(${story.id})" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-xs font-medium">View</button>
                        <button onclick="editStory(${story.id})" class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 text-xs font-medium">Edit</button>
                        <button onclick="deleteStory(${story.id})" class="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300 text-xs font-medium">Delete</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    lucide.createIcons();
}

function populateCountryDropdowns() {
    // Verify COUNTRIES data is loaded before attempting to populate
    if (!window.COUNTRIES || !Array.isArray(window.COUNTRIES)) {
        console.error('CRITICAL: COUNTRIES data not loaded! Expected window.COUNTRIES array.');
        // Fallback to a basic country list if COUNTRIES is not available
        const basicCountries = [
            { name: "United States", code: "US" },
            { name: "United Kingdom", code: "GB" },
            { name: "Canada", code: "CA" },
            { name: "Australia", code: "AU" },
            { name: "India", code: "IN" },
            { name: "Country not available", code: "N/A" }
        ];
        window.COUNTRIES = basicCountries;
    }

    const actionSelect = document.getElementById('actionCountrySelect');
    const resourceSelect = document.getElementById('resourceCountrySelect');

    const populate = (select) => {
        if (!select) return;
        select.innerHTML = '<option value="">Select Country</option>';
        // Double check that COUNTRIES exists before using it
        if (!window.COUNTRIES || !Array.isArray(window.COUNTRIES)) {
            console.error('COUNTRIES data still not available after fallback');
            return;
        }
        try {
            window.COUNTRIES.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.name;
                opt.textContent = c.name;
                select.appendChild(opt);
            });
        } catch (error) {
            console.error('Error populating country dropdown:', error);
        }
    };
    populate(actionSelect);
    populate(resourceSelect);
}

function setupEventListeners() {
    // Nav
    navItems.forEach((item) => {
        item.addEventListener("click", (e) => {
            // Only prevent default if it's an internal page navigation (has data-page attribute)
            const pageName = item.getAttribute("data-page");
            if (pageName) {
                // CORE FIX: Check if the target section actually exists on this page
                const targetPage = document.getElementById(pageName);
                if (!targetPage) {
                    // If target doesn't exist (e.g. we are on contacts.html linking to #actions),
                    // allow default behavior (browser navigation to href)
                    return;
                }

                e.preventDefault();
                if (item.classList.contains("logout")) {
                    handleLogout();
                    return;
                }
                showPage(pageName);
                // Update active state in nav
                document.querySelectorAll('.nav-item[data-page]').forEach(n => {
                    n.classList.remove('active', 'text-zinc-900', 'dark:text-zinc-100', 'bg-zinc-100', 'dark:bg-white/5');
                    n.classList.add('text-zinc-600', 'dark:text-zinc-400');
                });
                item.classList.add('active', 'text-zinc-900', 'dark:text-zinc-100', 'bg-zinc-100', 'dark:bg-white/5');
                // Close mobile sidebar after navigation
                if (window.innerWidth < 1024) { // lg breakpoint
                    sidebar.classList.add("-translate-x-full");
                }
            } else if (item.classList.contains("logout")) {
                // Handle logout without preventing default
                e.preventDefault();
                handleLogout();
            }
            // For links without data-page (external pages), let the default behavior occur
        });
    });

    // Toggles
    headerToggle?.addEventListener("click", () => {
        sidebar.classList.toggle("-translate-x-full"); // Simple toggle for mobile logic
    });

    // Notifications
    notificationBtn?.addEventListener("click", (e) => {
        e.stopPropagation();
        notificationDropdown.classList.toggle("hidden");
        userDropdown.classList.add("hidden");

        // Load notifications when dropdown is opened
        if (!notificationDropdown.classList.contains("hidden")) {
            loadNotifications();
        }
    });

    // User Menu
    userMenu?.addEventListener("click", (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle("hidden");
        notificationDropdown.classList.add("hidden");

        // Update user info when menu is opened
        if (!userDropdown.classList.contains("hidden")) {
            updateUserInfo();
        }
    });

    document.addEventListener("click", () => {
        notificationDropdown?.classList.add("hidden");
        userDropdown?.classList.add("hidden");
    });

    // Create Modal
    createActionBtn?.addEventListener("click", () => openCreateModal('action'));
    closeModal?.addEventListener("click", () => {
        createModal.classList.add("hidden");
        resetFormErrors();
    });

    // Tab Switching
    tabBtns.forEach((btn) => {
        btn.addEventListener("click", (e) => {
            e.preventDefault();  // Prevent default anchor behavior
            const tabId = btn.getAttribute("data-tab");
            if (tabId) switchTab(tabId);
        });
    });

    // Add logout functionality to all logout buttons
    document.querySelectorAll('.logout').forEach(logoutBtn => {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            handleLogout();
        });
    });

    // Add Profile Settings functionality
    document.querySelectorAll('#userDropdown button:not(.logout)').forEach(btn => {
        if (btn.textContent.trim().includes('Profile') || btn.textContent.trim().includes('Profile Settings')) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                // Open profile settings modal or redirect to profile page
                if (currentUser && currentUser.id) {
                    window.location.href = `${DASHBOARD_ROUTES.profile}?user_id=${currentUser.id}`;
                } else {
                    // Fallback: show a message or redirect to login
                    showErrorMessage('Please log in to access profile');
                }
            });
        } else if (btn.textContent.trim().includes('Preferences')) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                // Open preferences modal or redirect to settings page
                if (currentUser && currentUser.id) {
                    window.location.href = `${DASHBOARD_ROUTES.profile}?user_id=${currentUser.id}`;
                } else {
                    // Fallback: show a message or redirect to login
                    showErrorMessage('Please log in to access preferences');
                }
            });
        }
    });

    // Initialize navigation based on user role
    initializeNavigationForRole();

    // Handle URL hash on load for deep linking (SPA routing)
    if (window.location.hash) {
        // Remove the '#' character
        const pageName = window.location.hash.substring(1);

        // Check if the page/section actually exists to avoid errors on MPA pages
        if (document.getElementById(pageName) && document.querySelector(`.nav-item[data-page="${pageName}"]`)) {
            // Slight delay to ensure everything is ready
            setTimeout(() => {
                showPage(pageName);

                // Update active state in nav
                document.querySelectorAll('.nav-item[data-page]').forEach(n => {
                    n.classList.remove('active', 'text-zinc-900', 'dark:text-zinc-100', 'bg-zinc-100', 'dark:bg-white/5');
                    n.classList.add('text-zinc-600', 'dark:text-zinc-400');
                });

                const activeNav = document.querySelector(`.nav-item[data-page="${pageName}"]`);
                if (activeNav) {
                    activeNav.classList.add('active', 'text-zinc-900', 'dark:text-zinc-100', 'bg-zinc-100', 'dark:bg-white/5');
                }
            }, 100);
        }
    }
}

function initializeNavigationForRole() {
    if (!currentUser) return;

    const userRole = currentUser.role || currentUser.user_role || currentUser.access_level;
    const isAdmin = (userRole === 'admin' || userRole === 'administrator');

    // Show/hide admin-only navigation items
    const adminOnlyElements = document.querySelectorAll('[data-admin-only="true"]');
    const nonAdminOnlyElements = document.querySelectorAll('[data-user-access="admin"]');

    if (isAdmin) {
        // For admins, show all elements including admin-only ones
        adminOnlyElements.forEach(el => {
            el.style.display = 'flex';
        });

        // Also check for navigation items that should be visible to admins
        document.querySelectorAll('.nav-item[href*="reports"], .nav-item[href*="moderation"], .nav-item[href*="stories_moderation"]').forEach(el => {
            el.style.display = 'flex';
        });
    } else {
        // For non-admins, hide admin-only elements
        adminOnlyElements.forEach(el => {
            el.style.display = 'none';
        });

        // Hide navigation items that are admin-only (those that should be hidden from regular users)
        document.querySelectorAll('.nav-item[href*="reports"], .nav-item[href*="moderation"], .nav-item[href*="stories_moderation"]').forEach(el => {
            el.style.display = 'none';
        });
    }
}

function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active', 'border-indigo-500', 'text-zinc-900', 'dark:text-zinc-100');
        b.classList.add('border-transparent', 'text-zinc-500');
    });
    const activeBtn = document.querySelector(`[data-tab="${tabId}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active', 'border-indigo-500', 'text-zinc-900', 'dark:text-zinc-100');
        activeBtn.classList.remove('border-transparent', 'text-zinc-500');
    }

    // Hide all tab content and remove active classes
    document.querySelectorAll('.tab-content').forEach(c => {
        c.classList.remove('active', 'block');
        c.classList.add('hidden');
    });

    // Show selected tab content
    const activeTab = document.getElementById(tabId);
    if (activeTab) {
        activeTab.classList.remove('hidden', 'active');
        activeTab.classList.add('active', 'block');
    }
}

function showPage(pageName) {
    // Check if user has permission to access the page
    const userRole = currentUser?.role;
    const isAdmin = userRole === 'admin' || userRole === 'administrator';

    // Pages that require admin access
    const adminPages = ['reports', 'stories_moderation', 'moderation'];

    if (adminPages.includes(pageName) && !isAdmin) {
        showErrorMessage('Access denied. Admin privileges required for this page.');
        return;
    }

    // Hide all pages
    pages.forEach((page) => {
        page.classList.remove("active", "block");
        page.classList.add("hidden");
    });

    // Show selected page
    const page = document.getElementById(pageName);
    if (page) {
        page.classList.remove("hidden", "active");
        page.classList.add("active", "block");
    }

    if (pageName === 'reminders') {
        if (currentUser && currentUser.id) {
            loadUserReminders(); // Load reminders when navigating to reminders page
        } else {
            showSwal('Login Required', 'Please log in to view your reminders.', 'info');
        }
    } else if (pageName === 'messages') {
        if (currentUser && currentUser.id) {
            loadMessagesPage(); // Load messages when navigating to messages page
        } else {
            showSwal('Login Required', 'Please log in to view your messages.', 'info');
        }
    } else if (pageName === 'meetings') {
        // Initialize MeetingsManager when navigating to meetings page
        if (currentUser && currentUser.id) {
            if (typeof MeetingsManager !== 'undefined' && typeof MeetingsManager.init === 'function') {
                MeetingsManager.init();
            } else {
                console.error('MeetingsManager not loaded');
            }
        } else {
            showSwal('Login Required', 'Please log in to view meetings.', 'info');
        }
    } else if (pageName === 'reports') {
        if (currentUser && isAdmin) {
            loadReportsPage(); // Load reports when navigating to reports page
        } else {
            showSwal('Access Denied', 'Only administrators can access reports.', 'error');
            showPage('overview'); // Redirect to overview
        }
    } else if (pageName === 'stories_moderation' || pageName === 'moderation') {
        if (currentUser && isAdmin) {
            loadStoriesModerationData(); // Load stories moderation when navigating to moderation page
        } else {
            showSwal('Access Denied', 'Only administrators can access moderation panel.', 'error');
            showPage('overview'); // Redirect to overview
        }
    } else if (pageName === 'users') {
        if (currentUser && isAdmin) {
            loadDashboardUsers();
        } else {
            showSwal('Access Denied', 'Only administrators can access user management.', 'error');
            showPage('overview');
        }
    }

    // Update URL hash based on current page (except for overview/dashboard)
    if (pageName === 'overview') {
        history.replaceState(null, null, window.location.pathname + window.location.search);
    } else {
        history.replaceState(null, null, window.location.pathname + window.location.search + '#' + pageName);
    }
}

// Placeholder functions that need to be defined
function loadReportsPage() {
    console.log('Loading reports page...');
    // In a real implementation, this would load report data
}

function loadStoriesModerationData() {
    console.log('Loading stories moderation data...');
    // In a real implementation, this would load story moderation data
}

function showErrorMessage(message) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 5000,
        background: isDark ? '#18181b' : '#ffffff',
        color: isDark ? '#e4e4e7' : '#18181b'
    });
}


function openCreateModal(type) {
    resetFormErrors(); // Clear previous errors
    createModal.classList.remove('hidden');
    if (type === 'action') {
        switchTab('action-tab');
        document.getElementById('editActionId').value = ""; // Clear edit mode
    } else {
        switchTab('resource-tab');
        document.getElementById('editResourceId').value = ""; // Clear edit mode
    }
}

// Data loading functions
async function loadUserActions() {
    try {
        // Determine API endpoint based on user role
        let apiEndpoint;
        if (currentUser && currentUser.role && (currentUser.role === 'admin' || currentUser.role === 'administrator')) {
            // Admins can see all actions
            apiEndpoint = './../api/admin/get_all_actions.php';
        } else {
            // Regular users see only their own actions
            apiEndpoint = `./../api/actions/get_my_actions.php?user_id=${currentUser.id}`;
        }

        const response = await fetch(apiEndpoint, {
            method: "GET",
            headers: { "Content-Type": "application/json" }
        });

        // Check if the response is ok before trying to parse JSON
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            actionsData = result.actions || result.data || [];
            renderUserActions(actionsData);
        } else {
            console.error("Failed to load actions:", result.message);
        }
    } catch (error) {
        console.error("Error loading actions:", error);
        // Additional check if the error is due to unexpected HTML response
        if (error.message && error.message.includes('JSON')) {
            console.error("API returned HTML instead of JSON. Check for PHP errors.");
        }
    }
}

async function loadUserResources() {
    try {
        // Determine API endpoint based on user role
        let apiEndpoint;
        if (currentUser && currentUser.role && (currentUser.role === 'admin' || currentUser.role === 'administrator')) {
            // Admins can see all resources
            apiEndpoint = './../api/admin/get_all_resources.php';
        } else {
            // Regular users see only their own resources
            apiEndpoint = `./../api/resources/get_my_resources.php?user_id=${currentUser.id}`;
        }

        const response = await fetch(apiEndpoint, {
            method: "GET",
            headers: { "Content-Type": "application/json" }
        });

        // Check if the response is ok before trying to parse JSON
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            resourcesData = result.resources || result.data || [];
            renderUserResources(resourcesData);
        } else {
            console.error("Failed to load resources:", result.message);
        }
    } catch (error) {
        console.error("Error loading resources:", error);
        // Additional check if the error is due to unexpected HTML response
        if (error.message && error.message.includes('JSON')) {
            console.error("API returned HTML instead of JSON. Check for PHP errors.");
        }
    }
}

async function loadUserStats() {
    try {
        // Fetch all stats in parallel
        const [actionsRes, participationRes] = await Promise.allSettled([
            fetch(`./../api/other/dashboard_stats.php?user_id=${currentUser.id}`, {
                method: "GET",
                headers: { "Content-Type": "application/json" }
            }),
            fetch(`./../api/other/dashboard_stats.php?user_id=${currentUser.id}&get_participation=true`, { // Using same file for participation count
                method: "GET",
                headers: { "Content-Type": "application/json" }
            })
        ]);

        // Handle actions and resources stats with trends
        if (actionsRes.status === 'fulfilled' && actionsRes.value.ok) {
            let actionsStats;
            try {
                const responseText = await actionsRes.value.text();
                actionsStats = JSON.parse(responseText);
            } catch (parseError) {
                console.error("Error parsing actions stats response:", parseError);
                throw new Error("Invalid JSON response from server");
            }

            if (actionsStats.success) {
                // Safely update elements only if they exist
                const myActionsCountEl = document.getElementById('myActionsCount');
                if (myActionsCountEl) myActionsCountEl.textContent = actionsStats.total_actions || 0;

                const myResourcesCountEl = document.getElementById('myResourcesCount');
                if (myResourcesCountEl) myResourcesCountEl.textContent = actionsStats.total_resources || 0;

                // Update trend indicators
                updateTrendElement('actionsTrend', actionsStats.actions_trend || 0);
                updateTrendElement('resourcesTrend', actionsStats.resources_trend || 0);
                updateTrendElement('commentsTrend', actionsStats.comments_trend || 0);
            } else {
                console.error("Actions stats API error:", actionsStats.message);
                const myActionsCountEl = document.getElementById('myActionsCount');
                if (myActionsCountEl) myActionsCountEl.textContent = "0";
                const myResourcesCountEl = document.getElementById('myResourcesCount');
                if (myResourcesCountEl) myResourcesCountEl.textContent = "0";

                // Update trend indicators to zero
                updateTrendElement('actionsTrend', 0);
                updateTrendElement('resourcesTrend', 0);
                updateTrendElement('commentsTrend', 0);
            }
        } else {
            console.error("Failed to load action stats:", actionsRes.reason || "Network error");
            const myActionsCountEl = document.getElementById('myActionsCount');
            if (myActionsCountEl) myActionsCountEl.textContent = "0";
            const myResourcesCountEl = document.getElementById('myResourcesCount');
            if (myResourcesCountEl) myResourcesCountEl.textContent = "0";

            // Update trend indicators to zero
            updateTrendElement('actionsTrend', 0);
            updateTrendElement('resourcesTrend', 0);
            updateTrendElement('commentsTrend', 0);
        }

        // Handle participation stats
        if (participationRes.status === 'fulfilled' && participationRes.value.ok) {
            let participationStats;
            try {
                const responseText = await participationRes.value.text();
                participationStats = JSON.parse(responseText);
            } catch (parseError) {
                console.error("Error parsing participation stats response:", parseError);
                throw new Error("Invalid JSON response from server");
            }

            if (participationStats.success) {
                const participatedCountEl = document.getElementById('participatedCount');
                if (participatedCountEl) participatedCountEl.textContent = participationStats.count || 0;

                // Update participation trend
                updateTrendElement('participationTrend', participationStats.participation_trend || 0);
            } else {
                console.error("Participation stats API error:", participationStats.message);
                const participatedCountEl = document.getElementById('participatedCount');
                if (participatedCountEl) participatedCountEl.textContent = "0";

                // Update participation trend to zero
                updateTrendElement('participationTrend', 0);
            }
        } else {
            console.error("Failed to load participation stats:", participationRes.reason || "Network error");
            const participatedCountEl = document.getElementById('participatedCount');
            if (participatedCountEl) participatedCountEl.textContent = "0";

            // Update participation trend to zero
            updateTrendElement('participationTrend', 0);
        }

        // Comments/Engagement count - fetch separately (only update if element exists)
        const engagementCountEl = document.getElementById('engagementCount');
        if (engagementCountEl) {  // Only fetch and update if the element exists
            try {
                const commentsRes = await fetch(`./../api/other/dashboard_stats.php?user_id=${currentUser.id}&type=comments`, {
                    method: "GET",
                    headers: { "Content-Type": "application/json" }
                });

                if (commentsRes.ok) {
                    const commentsStats = await commentsRes.json();
                    engagementCountEl.textContent = commentsStats.comments_count || 0;
                } else {
                    if (engagementCountEl) engagementCountEl.textContent = "0";
                }
            } catch (commentsError) {
                console.error("Error loading comments count:", commentsError);
                if (engagementCountEl) engagementCountEl.textContent = "0";
            }
        }
    } catch (error) {
        console.error("Error loading stats:", error);
        // Safely set defaults only if elements exist
        const myActionsCountEl = document.getElementById('myActionsCount');
        if (myActionsCountEl) myActionsCountEl.textContent = "0";

        const myResourcesCountEl = document.getElementById('myResourcesCount');
        if (myResourcesCountEl) myResourcesCountEl.textContent = "0";

        const participatedCountEl = document.getElementById('participatedCount');
        if (participatedCountEl) participatedCountEl.textContent = "0";

        const engagementCountEl = document.getElementById('engagementCount');
        if (engagementCountEl) engagementCountEl.textContent = "0";

        // Update trend indicators to zero
        updateTrendElement('actionsTrend', 0);
        updateTrendElement('resourcesTrend', 0);
        updateTrendElement('participationTrend', 0);
        updateTrendElement('commentsTrend', 0);
    }
}

// Helper function to update trend elements
function updateTrendElement(elementId, trendValue) {
    const element = document.getElementById(elementId);
    if (element) {
        const trendText = trendValue > 0 ? `+${trendValue} this month` : `${trendValue} this month`;
        element.innerHTML = `
                    <i data-lucide="${trendValue >= 0 ? 'trending-up' : 'trending-down'}"></i>
                    <span>${trendText}</span>
                `;

        // Toggle negative class based on trend value
        if (trendValue < 0) {
            element.classList.add('negative');
            element.classList.remove('positive');
        } else {
            element.classList.remove('negative');
            element.classList.add('positive');
        }

        // Refresh icons after updating content
        lucide.createIcons();
    }
}

async function loadRecentActivity() {
    try {
        const response = await fetch(`./../api/other/recent_activity.php?user_id=${currentUser.id}&limit=5&role=${currentUser.role}`, {
            method: "GET",
            headers: { "Content-Type": "application/json" }
        });

        // Check if the response is ok before trying to parse JSON
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        let result;
        try {
            // Read response as text first to check for HTML content
            const responseText = await response.text();

            // Check if response looks like HTML (starts with <!DOCTYPE or <html)
            if (responseText.trim().startsWith('<!DOCTYPE') || responseText.trim().startsWith('<html') ||
                responseText.trim().startsWith('<b>') || responseText.trim().startsWith('<br')) {
                console.error("API returned HTML instead of JSON:", responseText.substring(0, 200) + "...");
                throw new Error("Server returned HTML instead of JSON. Check for PHP errors.");
            }

            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error("Error parsing recent activity response:", parseError);
            throw new Error("Invalid JSON response from server");
        }

        if (result.success) {
            renderRecentActivity(result.activity || []);
        } else {
            console.error("Failed to load activity:", result.message);
            // Fallback to empty state
            const activityList = document.getElementById('recentActivityList');
            if (activityList) activityList.innerHTML = '<p class="text-sm text-zinc-500 text-center py-4">No recent activity</p>';
        }
    } catch (error) {
        console.error("Error loading recent activity:", error);
        // Additional check if the error is due to unexpected HTML response
        if (error.message && error.message.includes('JSON')) {
            console.error("API returned HTML instead of JSON. Check for PHP errors.");
        } else if (error.message && error.message.includes('HTML instead of JSON')) {
            console.error("Server error detected - likely PHP error in API");
        }
        const activityList = document.getElementById('recentActivityList');
        if (activityList) activityList.innerHTML = '<p class="text-sm text-zinc-500 text-center py-4">Error loading activity</p>';
    }
}


// Notification functions - Facebook-style
async function loadNotifications() {
    try {
        const response = await fetch(`./../api/notifications/get_notifications.php?user_id=${currentUser.id}&limit=10`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        let result;
        try {
            // Read response as text first to check for HTML content
            const responseText = await response.text();

            // Check if response looks like HTML (starts with <!DOCTYPE or <html)
            if (responseText.trim().startsWith('<!DOCTYPE') || responseText.trim().startsWith('<html') ||
                responseText.trim().startsWith('<b>') || responseText.trim().startsWith('<br')) {
                console.error("API returned HTML instead of JSON:", responseText.substring(0, 200) + "...");
                throw new Error("Server returned HTML instead of JSON. Check for PHP errors.");
            }

            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error("Error parsing notifications response:", parseError);
            throw new Error("Invalid JSON response from server");
        }

        if (result.success) {
            renderNotifications(result.notifications);
            updateNotificationBadge(result.unread_count);
        } else {
            console.error("Failed to load notifications:", result.message);
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

function renderNotifications(notifications) {
    const container = document.getElementById('notificationDropdown');
    if (!container) return; // Safety check
    if (!notifications || notifications.length === 0) {
        container.innerHTML = '<p class="text-sm text-zinc-500 text-center py-8">No notifications yet</p>';
        return;
    }

    let notificationsHTML = `
                <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/50 flex items-center justify-between">
                    <h3 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">Notifications</h3>
                    <div class="flex gap-2">
                        <button onclick="markAllAsRead()" class="text-xs text-indigo-600 hover:text-indigo-500">Mark all as read</button>
                        <button onclick="clearAllNotifications()" class="text-xs text-zinc-500 hover:text-zinc-700">Clear all</button>
                    </div>
                </div>
                <div class="max-h-96 overflow-y-auto" id="notificationsList">
            `;

    notifications.forEach(notif => {
        const isUnread = !notif.is_read || notif.isRead === 0;

        // Get the user name instead of the item title to match main project behavior
        const userName = notif.user_name || notif.userName || notif.name || 'User';

        // The message should be what the user did
        const message = notif.message || notif.title || notif.notification_message || 'No message';

        const timestamp = notif.created_at || notif.date || notif.timestamp || notif.createdAt;

        notificationsHTML += `
                    <div class="px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 border-b border-zinc-100 dark:border-zinc-800 cursor-pointer ${isUnread ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : ''}"
                         onclick="markAsRead(${notif.id || notif.ID || 0})">
                        <div class="flex gap-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                                    <i data-lucide="${getNotificationIcon(notif.type || 'default')}" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${userName}</p>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">${message}</p>
                                <p class="text-xs text-zinc-400 mt-1">${formatTimeAgo(timestamp)}</p>
                            </div>
                            ${isUnread ? '<div class="w-2 h-2 bg-indigo-600 rounded-full"></div>' : ''}
                        </div>
                    </div>
                `;
    });

    notificationsHTML += `
                </div>
                <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900/50">
                    <button onclick="loadMoreNotifications()" class="text-xs text-center w-full text-indigo-600 hover:text-indigo-500 font-medium">
                        See more
                    </button>
                </div>
            `;

    container.innerHTML = notificationsHTML;
    lucide.createIcons();
}

function getNotificationIcon(type) {
    const icons = {
        'action_created': 'plus-circle',
        'action_updated': 'edit',
        'resource_created': 'package',
        'comment_added': 'message-circle',
        'action_joined': 'users',
        'default': 'bell'
    };
    return icons[type] || icons.default;
}

function formatTimeAgo(timestamp) {
    const now = new Date();
    const then = new Date(timestamp);
    const seconds = Math.floor((now - then) / 1000);

    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
    if (seconds < 86400) return Math.floor(seconds / 60) + 'h ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
    return then.toLocaleDateString();
}

async function markAsRead(notificationId) {
    try {
        const response = await fetch(`./../api/notifications/mark_notification_read.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: notificationId })
        });

        if (response.ok) {
            loadNotifications(); // Reload to update UI
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

async function markAllAsRead() {
    try {
        const response = await fetch(`./../api/notifications/mark_all_notifications_read.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: currentUser.id })
        });

        if (response.ok) {
            loadNotifications();
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
    }
}

async function clearAllNotifications() {
    // Show a simple confirmation without SweetAlert
    if (!confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch(`./../api/notifications/clear_all_notifications.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: currentUser.id })
        });

        if (response.ok) {
            loadNotifications();
        } else {
            console.error('Failed to clear notifications:', response.status);
        }
    } catch (error) {
        console.error('Error clearing notifications:', error);
    }
}

async function loadMoreNotifications() {
    try {
        // Get current notification count to use as offset for pagination
        const currentNotificationsCount = document.querySelectorAll('#notificationsList > div').length;
        const limit = 10; // Load 10 more notifications

        const response = await fetch(`./../api/notifications/get_notifications.php?user_id=${currentUser.id}&limit=10&offset=${currentNotificationsCount}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        let result;
        try {
            // Read response as text first to check for HTML content
            const responseText = await response.text();

            // Check if response looks like HTML (starts with <!DOCTYPE or <html)
            if (responseText.trim().startsWith('<!DOCTYPE') || responseText.trim().startsWith('<html') ||
                responseText.trim().startsWith('<b>') || responseText.trim().startsWith('<br')) {
                console.error("API returned HTML instead of JSON:", responseText.substring(0, 200) + "...");
                throw new Error("Server returned HTML instead of JSON. Check for PHP errors.");
            }

            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error("Error parsing notifications response:", parseError);
            throw new Error("Invalid JSON response from server");
        }

        if (result.success && result.notifications && result.notifications.length > 0) {
            // Append new notifications to the existing list
            const container = document.getElementById('notificationsList');
            if (container) {
                // Add new notifications to the list (after existing ones)
                result.notifications.forEach(notif => {
                    const isUnread = !notif.is_read;

                    // Get the user name instead of the item title to match main project behavior
                    const userName = notif.user_name || notif.userName || notif.name || 'User';

                    // The message should be what the user did
                    const message = notif.message || notif.title || notif.notification_message || 'No message';
                    const timestamp = notif.created_at || notif.date || notif.timestamp || notif.createdAt;

                    const notificationHTML = `
                                <div class="px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/50 border-b border-zinc-100 dark:border-zinc-800 cursor-pointer ${isUnread ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : ''}"
                                     onclick="markAsRead(${notif.id || notif.ID || 0})">
                                    <div class="flex gap-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                                                <i data-lucide="${getNotificationIcon(notif.type || 'default')}" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">${userName}</p>
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">${message}</p>
                                            <p class="text-xs text-zinc-400 mt-1">${formatTimeAgo(timestamp)}</p>
                                        </div>
                                        ${isUnread ? '<div class="w-2 h-2 bg-indigo-600 rounded-full"></div>' : ''}
                                    </div>
                                </div>
                            `;
                    container.insertAdjacentHTML('beforeend', notificationHTML);
                });
                lucide.createIcons();
            }
        } else {
            alert('No more notifications to load');
        }
    } catch (error) {
        console.error('Error loading more notifications:', error);
    }
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('#notificationBtn .badge');
    if (badge) {
        if (count > 0) {
            badge.style.display = 'block';
            badge.textContent = count > 99 ? '99+' : count;
        } else {
            badge.style.display = 'none';
        }
    }
}

function renderUserActions(actions) {
    const tbody = document.getElementById('my-actions-table-body');
    if (!tbody) return; // Safety check
    tbody.innerHTML = actions.map(a => {
        let statusClass = 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700';

        if (a.status.toLowerCase() === 'pending') {
            statusClass = 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50';
        } else if (a.status.toLowerCase() === 'approved') {
            statusClass = 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50';
        } else if (a.status.toLowerCase() === 'rejected') {
            statusClass = 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50';
        }

        const isAdmin = currentUser && currentUser.role === 'admin';
        let actionButtons = '';

        if (isAdmin) {
            actionButtons = `
                        <button class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 text-xs font-medium mr-2" onclick="approveAction(${a.id})">Approve</button>
                        <button class="text-rose-600 dark:text-rose-400 hover:text-rose-500 text-xs font-medium mr-2" onclick="rejectAction(${a.id})">Reject</button>
                        <button class="text-amber-600 dark:text-amber-400 hover:text-amber-500 text-xs font-medium" onclick="openEditAction(${a.id})">Edit</button>
                    `;
        } else {
            actionButtons = `
                        <button class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 text-xs font-medium mr-2" onclick="openEditAction(${a.id})">Edit</button>
                        <button class="text-rose-600 dark:text-rose-400 hover:text-rose-500 text-xs font-medium" onclick="confirmDeleteAction(${a.id})">Delete</button>
                    `;
        }

        return `
                    <tr class="hover:bg-zinc-50 dark:hover:bg-white/5 transition-colors border-b border-zinc-100 dark:border-zinc-800/50">
                        <td class="py-3 px-5 text-zinc-500">#${a.id}</td>
                        <td class="py-3 px-5">
                            <img src="${a.image_url || a.image || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEFjdGlvbiBJbWFnZTwvdGV4dD48L3N2Zz4='}" alt="${a.title}" class="w-12 h-12 object-cover rounded" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEFjdGlvbiBJbWFnZTwvdGV4dD48L3N2Zz4='">
                        </td>
                        <td class="py-3 px-5 font-medium text-zinc-900 dark:text-zinc-200">${a.title}</td>
                        <td class="py-3 px-5">${a.category}</td>
                        <td class="py-3 px-5"><span class="px-2 py-1 rounded-full text-[10px] font-semibold ${statusClass}">${a.status}</span></td>
                        <td class="py-3 px-5">${a.participants || 0}</td>
                        <td class="py-3 px-5 text-zinc-500">${new Date(a.created_at).toLocaleDateString()}</td>
                        <td class="py-3 px-5">
                            ${actionButtons}
                        </td>
                    </tr>
                `;
    }).join('');
}

function renderUserResources(resources) {
    const tbody = document.getElementById('my-resources-table-body');
    if (!tbody) return; // Safety check
    tbody.innerHTML = resources.map(r => {
        let statusClass = 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50';

        if (r.status.toLowerCase() === 'pending') {
            statusClass = 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50';
        } else if (r.status.toLowerCase() === 'approved') {
            statusClass = 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50';
        } else if (r.status.toLowerCase() === 'rejected') {
            statusClass = 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50';
        }

        const isAdmin = currentUser && currentUser.role === 'admin';
        let resourceButtons = '';

        if (isAdmin) {
            resourceButtons = `
                        <button class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 text-xs font-medium mr-2" onclick="approveResource(${r.id})">Approve</button>
                        <button class="text-rose-600 dark:text-rose-400 hover:text-rose-500 text-xs font-medium mr-2" onclick="rejectResource(${r.id})">Reject</button>
                        <button class="text-amber-600 dark:text-amber-400 hover:text-amber-500 text-xs font-medium" onclick="openEditResource(${r.id})">Edit</button>
                    `;
        } else {
            resourceButtons = `
                        <button class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 text-xs font-medium mr-2" onclick="openEditResource(${r.id})">Edit</button>
                        <button class="text-rose-600 dark:text-rose-400 hover:text-rose-500 text-xs font-medium" onclick="confirmDeleteResource(${r.id})">Delete</button>
                    `;
        }

        return `
                    <tr class="hover:bg-zinc-50 dark:hover:bg-white/5 transition-colors border-b border-zinc-100 dark:border-zinc-800/50">
                        <td class="py-3 px-5 text-zinc-500">#${r.id}</td>
                        <td class="py-3 px-5">
                            <img src="${r.image_url || r.image || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIFJlc291cmNlIEltYWdlPC90ZXh0Pjwvc3ZnPg=='}}" alt="${r.resource_name || r.title}" class="w-12 h-12 object-cover rounded" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIFJlc291cmNlIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                        </td>
                        <td class="py-3 px-5 font-medium text-zinc-900 dark:text-zinc-200">${r.resource_name || r.title}</td>
                        <td class="py-3 px-5">${r.category}</td>
                        <td class="py-3 px-5 uppercase text-[10px] font-bold text-zinc-500">${r.type || ''}</td>
                        <td class="py-3 px-5"><span class="px-2 py-1 rounded-full text-[10px] font-semibold ${statusClass}">${r.status}</span></td>
                        <td class="py-3 px-5 text-zinc-500">${r.location || ''}</td>
                        <td class="py-3 px-5">
                            ${resourceButtons}
                        </td>
                    </tr>
                `;
    }).join('');
}

function renderRecentActivity(activity) {
    const activityList = document.getElementById('recentActivityList');
    if (!activityList) return; // Safety check

    if (activity.length === 0) {
        if (activityList) activityList.innerHTML = '<p class="text-sm text-zinc-500 text-center py-4">No recent activity</p>';
        return;
    }

    // Limit to 5 items to prevent long scrolling lists
    const limitedActivity = activity.slice(0, 5);

    let activityHTML = limitedActivity.map(item => {
        let icon = "circle";  // Default
        let bgClass = "bg-zinc-500/10";
        let textClass = "text-zinc-500";

        // Support multiple field name variations
        const itemType = item.type || item.itemType || item.activityType || 'unknown';
        const message = item.message || item.title || item.activity_message || 'No activity';
        const details = item.details || item.description || item.content || '';
        const timestamp = item.timestamp || item.date || item.created_at || item.createdAt;

        if (itemType.includes('action')) {
            icon = "zap";
            bgClass = "bg-indigo-500/10";
            textClass = "text-indigo-600";
        } else if (itemType.includes('resource')) {
            icon = "package";
            bgClass = "bg-emerald-500/10";
            textClass = "text-emerald-600";
        } else if (itemType.includes('comment')) {
            icon = "message-circle";
            bgClass = "bg-amber-500/10";
            textClass = "text-amber-600";
        }

        return `
                <div class="flex gap-4 group">
                    <div class="w-8 h-8 rounded-full ${bgClass} border flex items-center justify-center ${textClass} shrink-0">
                        <i data-lucide="${icon}" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <p class="text-sm text-zinc-900 dark:text-zinc-200 font-medium">${message}</p>
                        <p class="text-xs text-zinc-500 mt-0.5">${details}</p>
                        <p class="text-[10px] text-zinc-500 mt-1">${timestamp ? new Date(timestamp).toLocaleString() : ''}</p>
                    </div>
                </div>
                `;
    }).join('');

    // Add view more button if there are more items than shown
    if (activity.length > 5) {
        activityHTML += `
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-800/50 mt-4">
                    <a href="#activity" class="w-full block py-2 text-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 text-sm font-medium rounded-lg border border-indigo-200 dark:border-indigo-900/50 hover:bg-indigo-50 dark:hover:bg-indigo-900/10 transition-colors">
                        View all ${activity.length} activities
                    </a>
                </div>
                `;
    }

    if (activityList) activityList.innerHTML = activityHTML;

    lucide.createIcons();
}


// Filtering functions
function filterActionsTable() {
    const searchTerm = document.getElementById('my-actions-search').value.toLowerCase();
    const statusFilter = document.getElementById('my-actions-status-filter').value;

    const filtered = actionsData.filter(action => {
        const matchesSearch = action.title.toLowerCase().includes(searchTerm) ||
            action.category.toLowerCase().includes(searchTerm);
        const matchesStatus = !statusFilter || action.status === statusFilter;

        return matchesSearch && matchesStatus;
    });

    renderUserActions(filtered);
}

function filterResourcesTable() {
    const searchTerm = document.getElementById('my-resources-search').value.toLowerCase();
    const statusFilter = document.getElementById('my-resources-status-filter').value;

    const filtered = resourcesData.filter(resource => {
        const resourceName = resource.resource_name || resource.title;
        const matchesSearch = resourceName.toLowerCase().includes(searchTerm) ||
            resource.category.toLowerCase().includes(searchTerm);
        const matchesStatus = !statusFilter || resource.status === statusFilter;

        return matchesSearch && matchesStatus;
    });

    renderUserResources(filtered);
}


// Edit functionality
function openEditAction(id) {
    const action = actionsData.find(a => a.id == id);
    if (action) {
        document.getElementById('editActionId').value = action.id;
        document.getElementById('actionTitle').value = action.title || '';
        document.getElementById('actionCategory').value = action.category || '';
        document.getElementById('actionTheme').value = action.theme || action.category || '';
        document.getElementById('actionDateTime').value = action.start_time || '';
        document.getElementById('actionDuration').value = action.actionDuration || action.duration || '';
        document.getElementById('actionDescription').value = action.description || '';

        // Location fields
        document.getElementById('actionCountrySelect').value = action.country || '';
        document.getElementById('actionLocationDetails').value = action.location_details || '';
        document.getElementById('actionLatitude').value = action.latitude || '';
        document.getElementById('actionLongitude').value = action.longitude || '';

        // Show image preview if available
        if (action.image_url || action.image) {
            document.getElementById('actionImagePreview').src = action.image_url || action.image;
            document.getElementById('actionImagePreviewContainer').classList.remove('hidden');
        } else {
            document.getElementById('actionImagePreviewContainer').classList.add('hidden');
        }

        openCreateModal('action');
    }
}

function openEditResource(id) {
    const resource = resourcesData.find(r => r.id == id);
    if (resource) {
        document.getElementById('editResourceId').value = resource.id;
        document.getElementById('resourceName').value = resource.resource_name || '';
        document.getElementById('resourceCategory').value = resource.category || '';
        document.getElementById('resourceType').querySelector(`input[value="${resource.type}"]`)?.click();
        document.getElementById('resourceDescription').value = resource.description || '';

        // Location fields
        document.getElementById('resourceCountrySelect').value = resource.country || '';
        document.getElementById('resourceLocationDetails').value = resource.location_details || '';
        document.getElementById('resourceLatitude').value = resource.latitude || '';
        document.getElementById('resourceLongitude').value = resource.longitude || '';

        // Show image preview if available
        if (resource.image_url || resource.image) {
            document.getElementById('resourceImagePreview').src = resource.image_url || resource.image;
            document.getElementById('resourceImagePreviewContainer').classList.remove('hidden');
        } else {
            document.getElementById('resourceImagePreviewContainer').classList.add('hidden');
        }

        openCreateModal('resource');
    }
}

// Form validation and submission
function resetFormErrors() {
    // Remove error classes from inputs
    document.querySelectorAll('.input-error').forEach(el => {
        el.classList.remove('input-error');
    });

    // Hide error messages
    document.querySelectorAll('.error-message').forEach(el => {
        el.classList.remove('show');
        el.style.display = 'none';
    });
}

function addFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorEl = document.getElementById(fieldId + '-error');

    if (field) field.classList.add('input-error');
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.add('show');
        errorEl.style.display = 'block';
    }
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorEl = document.getElementById(fieldId + '-error');

    if (field) field.classList.remove('input-error');
    if (errorEl) {
        errorEl.classList.remove('show');
        errorEl.style.display = 'none';
    }
}

async function submitActionForm(e) {
    e.preventDefault();

    resetFormErrors();

    const form = e.target;
    const formData = new FormData(form);
    const editId = document.getElementById('editActionId').value;

    // Check if we have a file input in the form and if there's a file selected
    const fileInput = document.getElementById('action-file-input');
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

    // Get field values
    const title = document.getElementById('actionTitle').value.trim();
    const category = document.getElementById('actionCategory').value;
    const theme = document.getElementById('actionTheme').value;
    const start_time = document.getElementById('actionDateTime').value;
    const actionDuration = document.getElementById('actionDuration').value;
    const description = document.getElementById('actionDescription').value.trim();
    const country = document.getElementById('actionCountrySelect').value;
    const location_details = document.getElementById('actionLocationDetails').value.trim();
    const latitude = document.getElementById('actionLatitude').value;
    const longitude = document.getElementById('actionLongitude').value;

    // Construct location field by combining country and location details
    const location = country && location_details ?
        `${country} - ${location_details}` :
        country || location_details || '';

    // Validation
    let isValid = true;

    if (!title) {
        addFieldError('actionTitle', 'Title is required');
        isValid = false;
    }

    if (!category) {
        addFieldError('actionCategory', 'Category is required');
        isValid = false;
    }

    if (!start_time) {
        addFieldError('actionDateTime', 'Date & Time is required');
        isValid = false;
    }

    if (!country) {
        addFieldError('actionCountrySelect', 'Country is required');
        isValid = false;
    }

    if (!isValid) return;

    try {
        if (hasFile) {
            // If there's a file, submit via FormData
            const actionFormData = new FormData();
            actionFormData.append('title', title);
            actionFormData.append('category', category);
            actionFormData.append('theme', theme);
            actionFormData.append('description', description);
            actionFormData.append('start_time', start_time);
            if (actionDuration) actionFormData.append('actionDuration', actionDuration);
            actionFormData.append('country', country);
            actionFormData.append('location_details', location_details);
            actionFormData.append('location', location); // Add constructed location field
            actionFormData.append('latitude', latitude);
            actionFormData.append('longitude', longitude);
            actionFormData.append('creator_id', currentUser.id);

            if (editId) {
                actionFormData.append('id', editId);
            }

            actionFormData.append('image', fileInput.files[0]);

            const url = editId ? "./../api/actions/update_action.php" : "./../api/actions/create_action.php";
            const response = await fetch(url, {
                method: "POST",
                body: actionFormData
            });

            const result = await response.json();

            if (result.success) {
                showSuccessMessage(`Action ${(editId ? 'updated' : 'created')} successfully!`);
                createModal.classList.add('hidden');
                // Reset form and hide image preview
                document.getElementById('actionImagePreviewContainer').classList.add('hidden');

                // Reload data
                await loadAllData();
            } else {
                showErrorMessage(`Failed to ${(editId ? 'update' : 'create')} action: ${result.message}`);
            }
        } else {
            // Prepare payload for JSON submission (when no image is uploaded)
            const payload = {
                id: editId ? parseInt(editId) : undefined,
                title,
                category,
                theme,
                description,
                start_time,
                actionDuration: actionDuration ? parseInt(actionDuration) : null,
                country,
                location_details,
                location, // Add constructed location field
                latitude: latitude ? parseFloat(latitude) : null,
                longitude: longitude ? parseFloat(longitude) : null,
                creator_id: currentUser.id
            };

            const url = editId ? "./../api/actions/update_action.php" : "./../api/actions/create_action.php";
            const response = await fetch(url, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                showSuccessMessage(`Action ${(editId ? 'updated' : 'created')} successfully!`);
                createModal.classList.add('hidden');

                // Reload data
                await loadAllData();
            } else {
                showErrorMessage(`Failed to ${(editId ? 'update' : 'create')} action: ${result.message}`);
            }
        }
    } catch (error) {
        console.error("Error submitting action:", error);
        showErrorMessage("Network error. Please try again.");
    }
}

async function submitResourceForm(e) {
    e.preventDefault();

    resetFormErrors();

    const form = e.target;
    const formData = new FormData(form);
    const editId = document.getElementById('editResourceId').value;

    // Check if we have a file input in the form and if there's a file selected
    const fileInput = document.getElementById('resource-file-input');
    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

    // Get field values
    const resource_name = document.getElementById('resourceName').value.trim();
    const category = document.getElementById('resourceCategory').value;
    const type = document.querySelector('input[name="type"]:checked')?.value;
    const description = document.getElementById('resourceDescription').value.trim();
    const country = document.getElementById('resourceCountrySelect').value;
    const location_details = document.getElementById('resourceLocationDetails').value.trim();
    const latitude = document.getElementById('resourceLatitude').value;
    const longitude = document.getElementById('resourceLongitude').value;

    // Construct location field by combining country and location details
    const location = country && location_details ?
        `${country} - ${location_details}` :
        country || location_details || '';

    // Validation
    let isValid = true;

    if (!resource_name) {
        addFieldError('resourceName', 'Resource name is required');
        isValid = false;
    }

    if (!category) {
        addFieldError('resourceCategory', 'Category is required');
        isValid = false;
    }

    if (!type) {
        addFieldError('resourceType', 'Type (Offer/Request) is required');
        isValid = false;
    }

    if (!country) {
        addFieldError('resourceCountrySelect', 'Country is required');
        isValid = false;
    }

    if (!isValid) return;

    try {
        if (hasFile) {
            // If there's a file, submit via FormData
            const resourceFormData = new FormData();
            resourceFormData.append('resource_name', resource_name);
            resourceFormData.append('category', category);
            resourceFormData.append('type', type);
            resourceFormData.append('description', description);
            resourceFormData.append('country', country);
            resourceFormData.append('location_details', location_details);
            resourceFormData.append('latitude', latitude);
            resourceFormData.append('longitude', longitude);
            resourceFormData.append('location', location); // Add constructed location field
            resourceFormData.append('publisher_id', currentUser.id);

            if (editId) {
                resourceFormData.append('id', editId);
            }

            resourceFormData.append('image', fileInput.files[0]);

            const url = editId ? "./../api/resources/update_resource.php" : "./../api/resources/create_resource.php";
            const response = await fetch(url, {
                method: "POST",
                body: resourceFormData
            });

            const result = await response.json();

            if (result.success) {
                showSuccessMessage(`Resource ${(editId ? 'updated' : 'created')} successfully!`);
                createModal.classList.add('hidden');
                // Reset form and hide image preview
                document.getElementById('resourceImagePreviewContainer').classList.add('hidden');

                // Reload data
                await loadAllData();
            } else {
                showErrorMessage(`Failed to ${(editId ? 'update' : 'create')} resource: ${result.message}`);
            }
        } else {
            // Prepare payload for JSON submission (when no image is uploaded)
            const payload = {
                id: editId ? parseInt(editId) : undefined,
                resource_name,
                category,
                type,
                description,
                country,
                location_details,
                location, // Add constructed location field
                latitude: latitude ? parseFloat(latitude) : null,
                longitude: longitude ? parseFloat(longitude) : null,
                publisher_id: currentUser.id
            };

            const url = editId ? "./../api/resources/update_resource.php" : "./../api/resources/create_resource.php";
            const response = await fetch(url, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                showSuccessMessage(`Resource ${(editId ? 'updated' : 'created')} successfully!`);
                createModal.classList.add('hidden');

                // Reload data
                await loadAllData();
            } else {
                showErrorMessage(`Failed to ${(editId ? 'update' : 'create')} resource: ${result.message}`);
            }
        }
    } catch (error) {
        console.error("Error submitting resource:", error);
        showErrorMessage("Network error. Please try again.");
    }
}

async function confirmDeleteAction(id) {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        background: document.documentElement.classList.contains('dark') ? '#18181b' : '#fff',
        color: document.documentElement.classList.contains('dark') ? '#e4e4e7' : '#18181b',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch("./../api/actions/delete_action.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id: id })
            });

            const result = await response.json();

            if (result.success) {
                showSuccessMessage('Action deleted successfully!');
                await loadAllData(); // Reload to reflect changes
            } else {
                showErrorMessage(`Failed to delete action: ${result.message}`);
            }
        } catch (error) {
            console.error("Error deleting action:", error);
            showErrorMessage("Network error. Please try again.");
        }
    }
}

async function confirmDeleteResource(id) {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        background: document.documentElement.classList.contains('dark') ? '#18181b' : '#fff',
        color: document.documentElement.classList.contains('dark') ? '#e4e4e7' : '#18181b',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch("./../api/resources/delete_resource.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id: id })
            });

            const result = await response.json();

            if (result.success) {
                showSuccessMessage('Resource deleted successfully!');
                await loadAllData(); // Reload to reflect changes
            } else {
                showErrorMessage(`Failed to delete resource: ${result.message}`);
            }
        } catch (error) {
            console.error("Error deleting resource:", error);
            showErrorMessage("Network error. Please try again.");
        }
    }
}

// Add approve/reject functions for actions
async function approveAction(id) {
    const result = await Swal.fire({
        title: 'Approve Action?',
        text: 'This action will be visible to all users',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve it',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch("./../api/actions/approve_action.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: id, action: 'approve' })
        });
        const result = await response.json();
        if (result.success) {
            showSuccessMessage('Action approved successfully!');
            await loadAllData();
        } else {
            showErrorMessage(result.message);
        }
    } catch (error) {
        console.error("Error approving action:", error);
        showErrorMessage('Error approving action');
    }
}

async function rejectAction(actionId) {
    const result = await Swal.fire({
        title: 'Reject Action?',
        text: 'Please provide a reason for rejection',
        input: 'textarea',
        inputPlaceholder: 'Enter rejection reason...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reject it',
        cancelButtonText: 'Cancel',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to provide a reason!';
            }
        }
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch('./../api/actions/approve_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: actionId,
                action: 'reject',
                admin_notes: result.value  // Include the rejection reason
            })
        });

        const data = await response.json();

        if (data.success) {
            showSuccessMessage('Action rejected successfully');
            await loadAllData();
        } else {
            showErrorMessage('Failed to reject action: ' + data.message);
        }
    } catch (error) {
        console.error('Error rejecting action:', error);
        showErrorMessage('Network error. Please try again.');
    }
}

// Add approve/reject functions for resources
async function approveResource(id) {
    const result = await Swal.fire({
        title: 'Approve Resource?',
        text: 'This resource will be visible to all users',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve it',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch("./../api/resources/approve_resource.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: id, action: 'approve' })
        });
        const result = await response.json();
        if (result.success) {
            showSuccessMessage('Resource approved successfully!');
            await loadAllData();
        } else {
            showErrorMessage(result.message);
        }
    } catch (error) {
        console.error("Error approving resource:", error);
        showErrorMessage('Error approving resource');
    }
}

async function rejectResource(resourceId) {
    const result = await Swal.fire({
        title: 'Reject Resource?',
        text: 'Please provide a reason for rejection',
        input: 'textarea',
        inputPlaceholder: 'Enter rejection reason...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reject it',
        cancelButtonText: 'Cancel',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to provide a reason!';
            }
        }
    });

    if (!result.isConfirmed) return;

    try {
        const response = await fetch('./../api/resources/approve_resource.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: resourceId,
                action: 'reject',
                admin_notes: result.value  // Include the rejection reason
            })
        });

        const data = await response.json();

        if (data.success) {
            showSuccessMessage('Resource rejected successfully');
            await loadAllData();
        } else {
            showErrorMessage('Failed to reject resource: ' + data.message);
        }
    } catch (error) {
        console.error('Error rejecting resource:', error);
        showErrorMessage('Network error. Please try again.');
    }
}

async function handleLogout() {
    try {
        const response = await fetch("./../api/users/logout.php", {
            method: "POST",
            credentials: 'include' // Include cookies for session
        });
        const result = await response.json();
        if (result.success) {
            console.log('Logged out successfully');
        }
    } catch (e) {
        console.error('Logout error:', e);
        // Even if logout fails, redirect to login
    } finally {
        // Clear any local storage or session storage if needed
        localStorage.clear();
        sessionStorage.clear();
        // Redirect to login page (unlogged)
        window.location.href = DASHBOARD_ROUTES.login;
    }
}

function updateUserInfo() {
    if (currentUser) {
        // Update user dropdown information
        const userNameElement = document.querySelector('#userDropdown .text-zinc-900') ||
            document.querySelector('#userDropdown p.text-zinc-900');
        const userEmailElement = document.querySelector('#userDropdown .text-zinc-500.truncate') ||
            document.querySelector('#userDropdown p.text-zinc-500');

        if (userNameElement) {
            userNameElement.textContent = 'You';
        }

        if (userEmailElement) {
            userEmailElement.textContent = currentUser.email || 'user@example.com';
        }

        // Update header user info in the user menu button
        const headerUserNameElement = document.querySelector('#userMenu .text-zinc-700') ||
            document.querySelector('#userMenu .text-zinc-300');
        if (headerUserNameElement) {
            const text = headerUserNameElement.textContent.trim();
            // Only update if it's the placeholder text, not a real name
            // Always show "You" instead of the actual name
            headerUserNameElement.textContent = 'You';
        }

        // Update avatar in user menu
        const headerAvatarElement = document.querySelector('#userMenu img');
        if (headerAvatarElement) {
            const avatarUrl = currentUser.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${encodeURIComponent(currentUser.name || 'user')}`;
            headerAvatarElement.src = avatarUrl;
        }

        // Update avatar in dropdown (if it exists)
        const dropdownAvatarElement = document.querySelector('#userDropdown img');
        if (dropdownAvatarElement) {
            const avatarUrl = currentUser.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${encodeURIComponent(currentUser.name || 'user')}`;
            dropdownAvatarElement.src = avatarUrl;
        }
    }
}

function showSuccessMessage(msg) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: msg,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        background: isDark ? '#18181b' : '#fff',
        color: isDark ? '#e4e4e7' : '#18181b',
        customClass: { popup: 'swal2-popup-custom shadow-xl border border-zinc-200 dark:border-zinc-800' }
    });
}

function showErrorMessage(msg) {
    const isDark = document.documentElement.classList.contains('dark');
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: msg,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 5000,
        background: isDark ? '#18181b' : '#fff',
        color: isDark ? '#e4e4e7' : '#18181b',
        customClass: { popup: 'swal2-popup-custom shadow-xl border border-zinc-200 dark:border-zinc-800' }
    });
}


// --- Location Map Logic ---
function initLocationPickerMap(lat = 48.8566, lng = 2.3522) {
    if (window.locationPickerMap) window.locationPickerMap.remove();

    const mapEl = document.getElementById('locationPickerMap');
    if (!mapEl) return;

    window.locationPickerMap = L.map('locationPickerMap').setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(window.locationPickerMap);

    window.selectedLocationMarker = L.marker([lat, lng]).addTo(window.locationPickerMap);

    window.locationPickerMap.on('click', function (e) {
        const { lat, lng } = e.latlng;
        if (window.selectedLocationMarker) window.selectedLocationMarker.setLatLng(e.latlng);
        else window.selectedLocationMarker = L.marker(e.latlng).addTo(window.locationPickerMap);

        document.getElementById('selectedCoords').textContent = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        window.selectedLocation = { lat, lng };
    });
}

function openLocationPicker(formType) {
    const modal = document.getElementById('locationPickerModal');
    modal.classList.remove('hidden');
    // Slight delay to render map correctly
    setTimeout(() => {
        // Use current coordinates if available, otherwise default
        let lat = 48.8566, lng = 2.3522;

        // Try to get current location
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {
                lat = position.coords.latitude;
                lng = position.coords.longitude;
                initLocationPickerMap(lat, lng);
                if (window.locationPickerMap) window.locationPickerMap.invalidateSize();
            }, function () {
                // If geolocation fails, use default coords
                initLocationPickerMap(lat, lng);
                if (window.locationPickerMap) window.locationPickerMap.invalidateSize();
            });
        } else {
            initLocationPickerMap(lat, lng);
            if (window.locationPickerMap) window.locationPickerMap.invalidateSize();
        }
    }, 100);
}

function confirmLocationSelection() {
    const modal = document.getElementById('locationPickerModal');
    modal.classList.add('hidden');
    if (window.selectedLocation) {
        // Update inputs based on active tab
        const activeTab = document.querySelector('.tab-content.active').id;
        const prefix = activeTab === 'action-tab' ? 'action' : 'resource';

        document.getElementById(`${prefix}Latitude`).value = window.selectedLocation.lat;
        document.getElementById(`${prefix}Longitude`).value = window.selectedLocation.lng;

        // Update country field based on coordinates using reverse geocoding
        updateCountryFromCoordinates(window.selectedLocation.lat, window.selectedLocation.lng, `${prefix}Country`);
    }
}

async function updateCountryFromCoordinates(lat, lng, countryFieldId) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
        const data = await response.json();

        if (data.address && data.address.country) {
            // Find the country in our list and set it
            const countrySelect = document.getElementById(countryFieldId + 'Select');
            if (countrySelect) {
                for (let option of countrySelect.options) {
                    if (option.text === data.address.country) {
                        option.selected = true;
                        document.getElementById(countryFieldId).value = option.value; // Hidden field
                        break;
                    }
                }
            }
        }
    } catch (error) {
        console.error("Error getting country from coordinates:", error);
    }
}

// --- 8. REMINDER MANAGEMENT FUNCTIONS ---

// Load user reminders
async function loadUserReminders() {
    try {
        const response = await fetch("../api/reminders/get_reminders.php");
        const result = await response.json();

        if (result.success) {
            const reminders = result.reminders || result.data || [];
            const totalRemindersCountEl = document.getElementById('totalRemindersCount');
            if (totalRemindersCountEl) totalRemindersCountEl.textContent = reminders.length;

            // Calculate upcoming and overdue reminders
            const now = new Date();
            const upcoming = reminders.filter(reminder => new Date(reminder.reminder_time) > now).length;
            const overdue = reminders.filter(reminder => new Date(reminder.reminder_time) <= now && !reminder.sent).length;

            const upcomingRemindersCountEl = document.getElementById('upcomingRemindersCount');
            if (upcomingRemindersCountEl) upcomingRemindersCountEl.textContent = upcoming;

            const pastRemindersCountEl = document.getElementById('pastRemindersCount');
            if (pastRemindersCountEl) pastRemindersCountEl.textContent = overdue;

            renderRemindersTable(reminders);
        } else {
            console.error("Failed to load reminders:", result.message);
            showErrorMessage(result.message || "Failed to load reminders");
        }
    } catch (error) {
        console.error("Error loading reminders:", error);
        showErrorMessage("Network error. Failed to load reminders.");
    }
}

// Render reminders in the table
function renderRemindersTable(reminders) {
    const tableBody = document.getElementById('remindersTableBody');
    if (!tableBody) return;

    if (reminders.length === 0) {
        tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="py-8 text-center text-zinc-500">
                            <div class="flex flex-col items-center justify-center">
                                <i data-lucide="bell-off" class="w-12 h-12 text-zinc-300 dark:text-zinc-600 mb-3"></i>
                                <p class="text-sm">No reminders set yet</p>
                                <p class="text-xs text-zinc-400 mt-1">Create reminders from the calendar view</p>
                            </div>
                        </td>
                    </tr>
                `;
        // Initialize Lucide icons
        lucide.createIcons();
        return;
    }

    tableBody.innerHTML = reminders.map(reminder => {
        const reminderTime = new Date(reminder.reminder_time);
        const statusClass = reminder.sent ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' :
            new Date(reminder.reminder_time) <= new Date() ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' :
                'bg-blue-500/10 text-blue-600 dark:text-blue-400';
        const statusText = reminder.sent ? 'Sent' :
            new Date(reminder.reminder_time) <= new Date() ? 'Overdue' :
                'Upcoming';

        return `
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20">
                        <td class="py-3 px-5 text-sm text-zinc-700 dark:text-zinc-300">${reminder.id}</td>
                        <td class="py-3 px-5 text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">${reminder.item_title || 'Untitled'}</div>
                            <div class="text-xs text-zinc-500">${reminder.item_type || 'N/A'}</div>
                        </td>
                        <td class="py-3 px-5 text-sm text-zinc-700 dark:text-zinc-300 capitalize">${reminder.item_type || 'N/A'}</td>
                        <td class="py-3 px-5 text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">${new Date(reminder.reminder_time).toLocaleDateString()}</div>
                            <div class="text-xs text-zinc-500">${new Date(reminder.reminder_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                        </td>
                        <td class="py-3 px-5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                ${statusText}
                            </span>
                        </td>
                        <td class="py-3 px-5 text-sm text-zinc-500">${new Date(reminder.created_at).toLocaleDateString()}</td>
                        <td class="py-3 px-5">
                            <div class="flex gap-2">
                                <button onclick="editReminder(${reminder.id})" class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">Edit</button>
                                <button onclick="deleteReminder(${reminder.id})" class="text-xs text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300 ml-2">Delete</button>
                                <button onclick="downloadICSFromReminder(${reminder.id})" class="text-xs text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-300 ml-2 flex items-center gap-1">
                                    <i data-lucide="download" class="w-3 h-3"></i> Download
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
    }).join('');

    // Initialize Lucide icons
    lucide.createIcons();
}

// Filter reminders table
function filterRemindersTable() {
    const searchTerm = document.getElementById('remindersSearch').value.toLowerCase();
    const statusFilter = document.getElementById('reminderStatusFilter').value;

    // For this implementation, we'll reload and filter all reminders
    loadUserReminders();
}

// Edit reminder function
async function editReminder(id) {
    try {
        const response = await fetch(`../api/reminders/get_reminder.php?id=${id}`);
        const result = await response.json();

        if (result.success && result.data) {
            const reminder = result.data;

            // Show a modal or form to edit the reminder
            Swal.fire({
                title: 'Edit Reminder',
                html: `
                            <div class="text-left mx-auto w-full">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Event: ${reminder.item_title || 'N/A'}</label>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Type: ${reminder.item_type || 'N/A'}</label>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Current Time: ${new Date(reminder.reminder_time).toLocaleString()}</label>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">New Reminder Time</label>
                                    <input type="datetime-local" id="editReminderTime" class="swal2-input w-full mt-1" value="${reminder.reminder_time.slice(0, 16)}">
                                </div>
                            </div>
                        `,
                focusConfirm: false,
                preConfirm: () => {
                    const newTime = document.getElementById('editReminderTime').value;
                    if (!newTime) {
                        Swal.showValidationMessage('Please select a reminder time');
                        return false;
                    }
                    return { newTime };
                },
                showCancelButton: true,
                confirmButtonText: 'Update',
                cancelButtonText: 'Cancel'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    // Validate that the new reminder time is in the future
                    const selectedTime = new Date(result.value.newTime);
                    const now = new Date();

                    if (selectedTime <= now) {
                        showErrorMessage('Reminder time is in the past. Please select a future time.');
                        return;
                    }

                    try {
                        const updateResponse = await fetch('../api/reminders/update_reminder.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id: id,
                                reminder_time: result.value.newTime
                            })
                        });

                        const updateResult = await updateResponse.json();

                        if (updateResult.success) {
                            showSuccessMessage(updateResult.message || 'Reminder updated successfully');
                            loadUserReminders(); // Reload the reminders table
                        } else {
                            showErrorMessage(updateResult.message || 'Failed to update reminder');
                        }
                    } catch (error) {
                        console.error('Error updating reminder:', error);
                        showErrorMessage('Network error. Please try again.');
                    }
                }
            });
        } else {
            showErrorMessage(result.message || 'Failed to get reminder details');
        }
    } catch (error) {
        console.error('Error fetching reminder details:', error);
        showErrorMessage('Network error. Please try again.');
    }
}

// Delete reminder function
async function deleteReminder(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch(`../api/reminders/delete_reminder.php?id=${id}`, {
                    method: 'GET'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccessMessage(result.message || 'Reminder deleted successfully');
                    loadUserReminders(); // Reload the reminders table
                } else {
                    showErrorMessage(result.message || 'Failed to delete reminder');
                }
            } catch (error) {
                console.error('Error deleting reminder:', error);
                showErrorMessage('Network error. Please try again.');
            }
        }
    });
}

// Download ICS for a specific reminder
async function downloadICSFromReminder(reminderId) {
    try {
        // Fetch the reminder details by ID
        const response = await fetch(`../api/reminders/get_reminder.php?id=${reminderId}`);
        const result = await response.json();

        if (result.success && result.data) {
            const reminder = result.data;

            // Check if the reminder has an associated action (for resources, there may not be a start_time for a calendar event)
            if (!reminder.item_title) {
                Swal.fire('Error', 'Cannot create calendar entry for this item.', 'error');
                return;
            }

            // For actions, we can create a calendar event using the action's start time, not the reminder time
            // But we need to get the full action details to do this properly
            const actionsList = (typeof actionsData !== 'undefined') ? actionsData : [];
            const resourcesList = (typeof resourcesData !== 'undefined') ? resourcesData : [];

            const fullItem = reminder.item_type === 'action' ?
                actionsList.find(action => action.id == reminder.item_id) :
                resourcesList.find(resource => resource.id == reminder.item_id);

            if (!fullItem) {
                // Determine correct API endpoint path
                let apiPathType = reminder.item_type + 's';
                if (reminder.item_type === 'story') apiPathType = 'stories';

                // If the full item isn't in our cached data, we need to fetch it
                fetch(`../api/${apiPathType}/get_${reminder.item_type}.php?id=${reminder.item_id}`)
                    .then(response => response.json())
                    .then(fetchResult => {
                        if (fetchResult.success) {
                            const item = fetchResult.data;
                            generateAndDownloadICS(item, reminder.item_title);
                        } else {
                            Swal.fire('Error', 'Failed to get item details for calendar export.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching item details for ICS:', error);
                        Swal.fire('Error', 'Failed to get item details for calendar export.', 'error');
                    });
            } else {
                generateAndDownloadICS(fullItem, reminder.item_title);
            }
        } else {
            Swal.fire('Error', result.message || 'Failed to get reminder details.', 'error');
        }
    } catch (error) {
        console.error('Error downloading ICS for reminder:', error);
        Swal.fire('Error', 'Network error. Failed to download calendar file.', 'error');
    }
}

// Helper function to generate and download ICS file
function generateAndDownloadICS(item, titleOverride = null) {
    try {
        // Use start_time from the action/resource rather than reminder time
        // This creates a calendar event for the actual action/resource event, not the reminder
        const startTime = new Date(item.start_time || item.created_at);
        if (isNaN(startTime.getTime())) {
            Swal.fire('Error', 'Invalid date for calendar export.', 'error');
            return;
        }

        // Calculate end time
        let durationHours = 2; // Default duration
        if (item.actionDuration) durationHours = parseFloat(item.actionDuration);
        else if (item.duration) durationHours = parseFloat(item.duration);

        const endTime = new Date(startTime.getTime() + (durationHours * 60 * 60 * 1000));

        // Create unique UID for the event
        const uid = `${item.id}-${item.creator_id || item.publisher_id}@connectforpeace.com`;

        // Build ICS content
        const icsContent = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Voices Of Peace//Calendar Export//EN',
            'BEGIN:VEVENT',
            `UID:${uid}`,
            `DTSTAMP:${toISOStringForICS(new Date())}`,
            `DTSTART:${toISOStringForICS(startTime)}`,
            `DTEND:${toISOStringForICS(endTime)}`,
            `SUMMARY:${escapeICSText(titleOverride || item.title || item.resource_name)}`,
            `DESCRIPTION:${escapeICSText(item.description || 'No description provided')}`,
            `LOCATION:${escapeICSText(item.location || 'Location not specified')}`,
            'END:VEVENT',
            'END:VCALENDAR'
        ].join('\\r\\n');

        // Create blob and download
        const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
        const url = URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = url;
        link.download = `${(titleOverride || item.title || item.resource_name || 'event').replace(/[^a-z0-9]/gi, '_')}.ics`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        Swal.fire('Success', 'Calendar file downloaded successfully!', 'success');
    } catch (error) {
        console.error('Error generating ICS:', error);
        Swal.fire('Error', 'Failed to generate calendar file.', 'error');
    }
}

// Helper function to format date for ICS
function toISOStringForICS(date) {
    // Format date as YYYYMMDDTHHMMSSZ for ICS
    return date.getFullYear() +
        String(date.getMonth() + 1).padStart(2, '0') +
        String(date.getDate()).padStart(2, '0') + 'T' +
        String(date.getHours()).padStart(2, '0') +
        String(date.getMinutes()).padStart(2, '0') +
        String(date.getSeconds()).padStart(2, '0') + 'Z';
}

// Helper function to escape ICS text
function escapeICSText(text) {
    if (!text) return '';
    return text.toString()
        .replace(/\\/g, '\\\\')
        .replace(/;/g, '\\;')
        .replace(/,/g, '\\,')
        .replace(/\n/g, '\\n');
}

// Add event listeners for reminders page
document.addEventListener('DOMContentLoaded', function () {
    // Add event listeners for reminders filters if on reminders page
    const reminderStatusFilter = document.getElementById('reminderStatusFilter');
    const remindersSearch = document.getElementById('remindersSearch');

    if (reminderStatusFilter) {
        reminderStatusFilter.addEventListener('change', filterRemindersTable);
    }

    if (remindersSearch) {
        remindersSearch.addEventListener('input', filterRemindersTable);
    }
});

// Load engagement chart data
async function loadEngagementChart() {
    try {
        const response = await fetch(`./../api/other/engagement-data.php?user_id=${currentUser.id}`, {
            method: "GET",
            headers: { "Content-Type": "application/json" }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            drawEngagementChart(result.data);
        } else {
            console.error("Failed to load engagement data:", result.message);
            // Show error in the chart area
            const chartContainer = document.getElementById('engagementChart');
            if (chartContainer) {
                chartContainer.parentElement.innerHTML = '<p class="text-sm text-zinc-500 text-center py-8">No engagement data available</p>';
            }
        }
    } catch (error) {
        console.error("Error loading engagement data:", error);
        const chartContainer = document.getElementById('engagementChart');
        if (chartContainer) {
            chartContainer.parentElement.innerHTML = '<p class="text-sm text-zinc-500 text-center py-8">Error loading engagement chart</p>';
        }
    }
}

// Draw engagement chart with Chart.js
function drawEngagementChart(data) {
    const ctx = document.getElementById('engagementChart');
    if (!ctx) return;

    const labels = data.map(d => d.date);
    const actionsData = data.map(d => d.actions);
    const resourcesData = data.map(d => d.resources);
    const participationsData = data.map(d => d.participations);

    // Define color sets for light and dark mode
    const isDarkMode = document.documentElement.classList.contains('dark');
    const colors = {
        actions: isDarkMode ? 'rgba(99, 102, 241, 0.7)' : 'rgba(59, 130, 246, 0.7)', // Blue
        resources: isDarkMode ? 'rgba(16, 185, 129, 0.7)' : 'rgba(16, 185, 129, 0.7)', // Emerald
        participations: isDarkMode ? 'rgba(139, 92, 246, 0.7)' : 'rgba(139, 92, 246, 0.7)', // Violet
        borderColor: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)',
        textColor: isDarkMode ? '#e4e4e7' : '#3f3f46',
        gridColor: isDarkMode ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)'
    };

    if (ctx.chart) {
        // Update existing chart data instead of recreating
        ctx.chart.data.labels = labels;
        ctx.chart.data.datasets[0].data = actionsData;
        ctx.chart.data.datasets[1].data = resourcesData;
        ctx.chart.data.datasets[2].data = participationsData;

        // Update colors for dark/light mode
        ctx.chart.data.datasets[0].backgroundColor = colors.actions;
        ctx.chart.data.datasets[0].borderColor = colors.borderColor;
        ctx.chart.data.datasets[1].backgroundColor = colors.resources;
        ctx.chart.data.datasets[1].borderColor = colors.borderColor;
        ctx.chart.data.datasets[2].backgroundColor = colors.participations;
        ctx.chart.data.datasets[2].borderColor = colors.borderColor;

        ctx.chart.options.scales.y.ticks.color = colors.textColor;
        ctx.chart.options.scales.x.ticks.color = colors.textColor;
        ctx.chart.options.scales.y.grid.color = colors.gridColor;
        ctx.chart.options.plugins.tooltip.backgroundColor = isDarkMode ? 'rgba(30, 30, 30, 0.9)' : 'rgba(255, 255, 255, 0.9)';
        ctx.chart.options.plugins.tooltip.titleColor = colors.textColor;
        ctx.chart.options.plugins.tooltip.bodyColor = colors.textColor;
        ctx.chart.options.plugins.tooltip.borderColor = colors.borderColor;
        ctx.chart.options.plugins.legend.labels.color = colors.textColor;

        // Use 'none' animation to prevent the jumping effect and set duration to 0 to prevent any flickering
        ctx.chart.update({
            duration: 0,
            easing: 'linear'
        });
    } else {
        // Create new chart if it doesn't exist
        ctx.chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Actions Created',
                        data: actionsData,
                        backgroundColor: colors.actions,
                        borderColor: colors.borderColor,
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'Resources Created',
                        data: resourcesData,
                        backgroundColor: colors.resources,
                        borderColor: colors.borderColor,
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'Participations',
                        data: participationsData,
                        backgroundColor: colors.participations,
                        borderColor: colors.borderColor,
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'x', // Set the index axis to x for vertical bars
                animation: {
                    duration: 0, // No animation to prevent jumping effect
                    easing: 'linear',
                    animateRotate: false,
                    animateScale: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: colors.textColor,
                            font: {
                                size: 12,
                                family: "'Inter', 'sans-serif'"
                            },
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: isDarkMode ? 'rgba(30, 30, 30, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                        titleColor: colors.textColor,
                        bodyColor: colors.textColor,
                        borderColor: colors.borderColor,
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 6,
                        displayColors: true,
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: colors.gridColor,
                            drawBorder: false,
                            zeroLineColor: colors.gridColor
                        },
                        ticks: {
                            color: colors.textColor,
                            font: {
                                size: 11,
                                family: "'Inter', 'sans-serif'"
                            },
                            precision: 0, // Only show whole numbers
                            stepSize: 1 // Set consistent step size
                        },
                        title: {
                            display: false, // Hide title to save space
                            text: 'Count',
                            color: colors.textColor,
                            font: {
                                size: 12,
                                family: "'Inter', 'sans-serif'"
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            color: colors.textColor,
                            font: {
                                size: 11,
                                family: "'Inter', 'sans-serif'"
                            },
                            maxRotation: 0,
                            minRotation: 0
                        },
                        title: {
                            display: false, // Hide title to save space
                            text: 'Month',
                            color: colors.textColor,
                            font: {
                                size: 12,
                                family: "'Inter', 'sans-serif'"
                            }
                        }
                    }
                }
            }
        });

        // Add a theme change listener to update colors when theme changes
        if (!window.engagementChartThemeListenerAdded) {
            // Listen for theme changes by monitoring class changes on html element
            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.attributeName === 'class') {
                        // Reload the chart to update colors for new theme
                        loadEngagementChart();
                    }
                });
            });

            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class']
            });

            window.engagementChartThemeListenerAdded = true;
        }
    }
}

// Load AI recommendations
async function loadRecommendations() {
    try {
        const response = await fetch(`./../api/ai/recommendations.php?user_id=${currentUser.id}`, {
            method: "GET",
            headers: { "Content-Type": "application/json" }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            renderRecommendations(result.recommendations);
        } else {
            console.error("Failed to load recommendations:", result.message);
            // Show message in recommendations container
            const container = document.getElementById('recommendationsContainer');
            if (container) {
                container.innerHTML = '<p class="text-sm text-zinc-500 text-center py-4">No recommendations available</p>';
            }
        }
    } catch (error) {
        console.error("Error loading recommendations:", error);
        const container = document.getElementById('recommendationsContainer');
        if (container) {
            container.innerHTML = '<p class="text-sm text-zinc-500 text-center py-4">Error loading recommendations</p>';
        }
    }
}

// Render recommendations in the UI
function renderRecommendations(recommendations) {
    const container = document.getElementById('recommendationsContainer');
    if (!container) return;

    if (!recommendations || recommendations.length === 0) {
        container.innerHTML = '<p class="text-sm text-zinc-500 text-center py-4">No recommendations available</p>';
        return;
    }

    container.innerHTML = recommendations.map(item => `
                <div class="recommendation-card group cursor-pointer mb-3" data-id="${item.id}" data-type="${item.type}">
                    <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-zinc-800/50 transition-colors">
                        <img src="${item.image_url || 'https://placehold.co/60x60?text=' + (item.title ? item.title.charAt(0).toUpperCase() : 'R')}"
                             alt="${item.title}"
                             class="w-10 h-10 rounded-lg object-cover">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm truncate">${item.title}</p>
                            <p class="text-xs text-zinc-400 truncate">${item.reason || 'Based on your interests'}</p>
                        </div>
                    </div>
                </div>
            `).join('');

    // Add click handlers to recommendation cards
    document.querySelectorAll('.recommendation-card').forEach(card => {
        card.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');

            if (type === 'action') {
                // Open action detail modal or navigate to action page
                window.location.hash = `actions`;
                setTimeout(() => {
                    showPage('actions');
                }, 100);
                // In a real implementation, you would open the action detail view
            } else if (type === 'resource') {
                // Open resource detail modal or navigate to resource page
                window.location.hash = `actions`;
                setTimeout(() => {
                    showPage('actions');
                }, 100);
                // In a real implementation, you would open the resource detail view
            }
        });
    });
}

// Load upcoming actions
async function loadUpcomingActions() {
    try {
        const response = await fetch(`./../api/actions/get-upcoming.php`, {
            method: "GET",
            headers: { "Content-Type": "application/json" }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            renderUpcomingActions(result.actions);
        } else {
            console.error("Failed to load upcoming actions:", result.message);
            const container = document.getElementById('upcomingActionsContainer');
            if (container) {
                container.innerHTML = '<p class="text-sm text-zinc-500 text-center py-4">No upcoming actions available</p>';
            }
        }
    } catch (error) {
        console.error("Error loading upcoming actions:", error);
        const container = document.getElementById('upcomingActionsContainer');
        if (container) {
            container.innerHTML = '<p class="text-sm text-zinc-500 text-center py-4">Error loading upcoming actions</p>';
        }
    }
}

// Render upcoming actions in the UI
function renderUpcomingActions(actions) {
    const container = document.getElementById('upcomingActionsContainer');
    if (!container) return;

    if (!actions || actions.length === 0) {
        container.innerHTML = '<p class="text-sm text-zinc-500 text-center py-4">No upcoming actions available</p>';
        return;
    }

    container.innerHTML = actions.map(action => `
                <div class="upcoming-action-card group cursor-pointer mb-3" data-id="${action.id}">
                    <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-zinc-800/50 transition-colors">
                        <img src="${action.profile_picture || 'https://placehold.co/40x40?text=' + (action.username ? action.username.charAt(0).toUpperCase() : 'U')}"
                             alt="${action.username}"
                             class="w-8 h-8 rounded-full object-cover">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm truncate">${action.title}</p>
                            <p class="text-xs text-zinc-400">${formatDate(new Date(action.start_time))}</p>
                        </div>
                        <span class="badge badge-outline text-xs">${action.category || 'General'}</span>
                    </div>
                </div>
            `).join('');

    // Add click handlers to upcoming action cards
    document.querySelectorAll('.upcoming-action-card').forEach(card => {
        card.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            // In a real implementation, you would open the action detail modal
            // For now, just show a placeholder alert
            showSwal('Action Details', `Action ID: ${id}`, 'info');
        });
    });
}

// Load messages page data
async function loadMessagesPage() {
    try {
        // Load message stats
        const statsResponse = await fetch("../api/contacts/get_message_stats.php");
        const statsResult = await statsResponse.json();

        if (statsResult.success) {
            const stats = statsResult; // The data is returned directly, not in a 'data' wrapper

            const totalMessagesCountEl = document.getElementById('totalMessagesCount');
            if (totalMessagesCountEl) totalMessagesCountEl.textContent = stats.total_messages || 0;

            const unreadMessagesCountEl = document.getElementById('unreadMessagesCount');
            if (unreadMessagesCountEl) unreadMessagesCountEl.textContent = stats.unread_messages || 0;

            const activeConversationsCountEl = document.getElementById('activeConversationsCount');
            if (activeConversationsCountEl) activeConversationsCountEl.textContent = stats.active_conversations || 0;
        } else {
            console.error("Failed to load message stats:", statsResult.message);
        }

        // Load recent conversations
        const conversationsResponse = await fetch("../api/contacts/get_recent_conversations.php");
        const conversationsResult = await conversationsResponse.json();

        if (conversationsResult.success) {
            const conversations = conversationsResult.conversations || [];
            renderMessagesTable(conversations);
        } else {
            console.error("Failed to load recent conversations:", conversationsResult.message);
            showErrorMessage(conversationsResult.message || "Failed to load recent conversations");
        }
    } catch (error) {
        console.error("Error loading messages page:", error);
        showErrorMessage("Network error. Failed to load messages page.");
    }
}

// Render messages in the table
function renderMessagesTable(conversations) {
    const tableBody = document.getElementById('messagesTableBody');
    if (!tableBody) return;

    if (conversations.length === 0) {
        tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="py-8 text-center text-zinc-500">
                            <div class="flex flex-col items-center justify-center">
                                <i data-lucide="message-circle" class="stroke-[1.5] w-8 h-8 mb-2"></i>
                                <p class="text-sm">No conversations yet</p>
                                <p class="text-xs text-zinc-500">Start a conversation by clicking "New Message"</p>
                            </div>
                        </td>
                    </tr>
                `;
        // Initialize Lucide icons
        lucide.createIcons();
        return;
    }

    tableBody.innerHTML = conversations.map(conversation => {
        const lastMessageTime = new Date(conversation.last_message_time);
        const statusClass = conversation.contact_status === 'online' ?
            'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' :
            'bg-zinc-500/10 text-zinc-500 dark:text-zinc-400';
        const statusText = conversation.contact_status === 'online' ? 'Online' : 'Offline';

        let lastMessagePreview = conversation.last_message || 'No messages yet';
        // Truncate long messages
        if (lastMessagePreview.length > 50) {
            lastMessagePreview = lastMessagePreview.substring(0, 50) + '...';
        }

        return `
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20">
                        <td class="py-3 px-5 text-sm">
                            <div class="flex items-center gap-3">
                                <img src="${conversation.contact_avatar || 'https://api.dicebear.com/7.x/avataaars/svg?seed=' + conversation.contact_name}"
                                     alt="${conversation.contact_name}"
                                     class="w-8 h-8 rounded-full object-cover">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">${conversation.contact_name}</div>
                                    <div class="text-xs text-zinc-500">${conversation.is_sent ? 'You:' : ''}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-5 text-sm text-zinc-700 dark:text-zinc-300">${lastMessagePreview}</td>
                        <td class="py-3 px-5 text-sm text-zinc-500">${lastMessageTime.toLocaleDateString()}</td>
                        <td class="py-3 px-5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                ${statusText}
                            </span>
                        </td>
                        <td class="py-3 px-5">
                            <div class="flex gap-2">
                                <button onclick="openConversation(${conversation.contact_id})"
                                    class="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                    Message
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
    }).join('');

    // Initialize Lucide icons
    lucide.createIcons();
}

async function loadDashboardUsers() {
    const body = document.getElementById('usersCrudTableBody');
    if (!body) return;

    const userRole = currentUser?.role || currentUser?.user_role || '';
    const isAdmin = userRole === 'admin' || userRole === 'administrator';

    if (!isAdmin) {
        body.innerHTML = '<tr><td colspan="5" class="py-6 px-5 text-center text-zinc-500">Admin access required.</td></tr>';
        return;
    }

    try {
        const response = await fetch('../api/users/get_all_users.php', { credentials: 'include' });
        const data = await response.json();
        dashboardUsersData = Array.isArray(data.users) ? data.users : [];
        renderDashboardUsersTable(dashboardUsersData);
    } catch (error) {
        console.error('Users load error:', error);
        body.innerHTML = '<tr><td colspan="5" class="py-6 px-5 text-center text-rose-500">Failed to load users.</td></tr>';
    }
}

function dashboardUserDisplayName(user) {
    return user.name || `${user.first_name || ''} ${user.last_name || ''}`.trim() || `User #${user.id || '-'}`;
}

function renderDashboardUsersTable(users) {
    const body = document.getElementById('usersCrudTableBody');
    const totalCount = document.getElementById('usersTotalCount');
    if (!body) return;

    if (totalCount) {
        totalCount.textContent = String(users.length || 0);
    }

    if (!users.length) {
        body.innerHTML = '<tr><td colspan="5" class="py-6 px-5 text-center text-zinc-500">No users found.</td></tr>';
        return;
    }

    body.innerHTML = users.map((user) => {
        const name = dashboardUserDisplayName(user);
        const email = user.email || '—';
        const role = user.role || 'member';
        const isBlocked = Number(user.is_blocked || 0) === 1;
        const status = isBlocked
            ? '<span class="px-2 py-1 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">Blocked</span>'
            : '<span class="px-2 py-1 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Active</span>';

        return `
            <tr class="hover:bg-zinc-50 dark:hover:bg-white/5 transition-colors border-b border-zinc-100 dark:border-zinc-800/50">
                <td class="py-3 px-5 font-medium text-zinc-900 dark:text-zinc-200">${name}</td>
                <td class="py-3 px-5 text-zinc-600 dark:text-zinc-300">${email}</td>
                <td class="py-3 px-5 capitalize">${role}</td>
                <td class="py-3 px-5">${status}</td>
                <td class="py-3 px-5">
                    <div class="flex gap-3">
                        <button onclick="openEditDashboardUserDialog(${user.id})" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-xs font-medium">Edit</button>
                        <button onclick="toggleDashboardUserBlock(${user.id})" class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 text-xs font-medium">${isBlocked ? 'Unblock' : 'Block'}</button>
                        <button onclick="deleteDashboardUser(${user.id})" class="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300 text-xs font-medium">Delete</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function filterDashboardUsersTable() {
    const query = (document.getElementById('usersSearch')?.value || '').toLowerCase().trim();
    if (!query) {
        renderDashboardUsersTable(dashboardUsersData);
        return;
    }

    const filtered = dashboardUsersData.filter((user) => {
        const name = dashboardUserDisplayName(user).toLowerCase();
        const email = (user.email || '').toLowerCase();
        return name.includes(query) || email.includes(query);
    });

    renderDashboardUsersTable(filtered);
}

async function openCreateDashboardUserDialog() {
    const dialog = await Swal.fire({
        title: 'Create User',
        html: `
            <input id="dash-new-name" class="swal2-input" placeholder="Full name">
            <input id="dash-new-email" class="swal2-input" placeholder="Email">
            <input id="dash-new-password" type="password" class="swal2-input" placeholder="Password">
            <select id="dash-new-role" class="swal2-input">
                <option value="member">Member</option>
                <option value="admin">Admin</option>
            </select>
        `,
        showCancelButton: true,
        confirmButtonText: 'Create',
        preConfirm: () => ({
            name: document.getElementById('dash-new-name')?.value?.trim(),
            email: document.getElementById('dash-new-email')?.value?.trim(),
            password: document.getElementById('dash-new-password')?.value,
            role: document.getElementById('dash-new-role')?.value || 'member'
        })
    });

    if (!dialog.isConfirmed) return;
    const payload = dialog.value || {};
    if (!payload.name || !payload.email || !payload.password) {
        showErrorMessage('Name, email and password are required.');
        return;
    }

    try {
        const response = await fetch('../api/users/create_user_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (data.success) {
            showSuccessMessage('User created successfully.');
            await loadDashboardUsers();
        } else {
            showErrorMessage(data.message || 'Failed to create user.');
        }
    } catch (error) {
        console.error('Create user error:', error);
        showErrorMessage('Network error while creating user.');
    }
}

async function openEditDashboardUserDialog(userId) {
    const user = dashboardUsersData.find((item) => Number(item.id) === Number(userId));
    if (!user) {
        showErrorMessage('User not found.');
        return;
    }

    const dialog = await Swal.fire({
        title: 'Edit User',
        html: `
            <input id="dash-edit-name" class="swal2-input" value="${(dashboardUserDisplayName(user) || '').replace(/"/g, '&quot;')}" placeholder="Full name">
            <input id="dash-edit-email" class="swal2-input" value="${(user.email || '').replace(/"/g, '&quot;')}" placeholder="Email">
            <select id="dash-edit-role" class="swal2-input">
                <option value="member" ${user.role === 'member' ? 'selected' : ''}>Member</option>
                <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
            </select>
        `,
        showCancelButton: true,
        confirmButtonText: 'Save',
        preConfirm: () => ({
            id: userId,
            name: document.getElementById('dash-edit-name')?.value?.trim(),
            email: document.getElementById('dash-edit-email')?.value?.trim(),
            role: document.getElementById('dash-edit-role')?.value || 'member'
        })
    });

    if (!dialog.isConfirmed) return;

    try {
        const response = await fetch('../api/users/update_user_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(dialog.value)
        });
        const data = await response.json();
        if (data.success) {
            showSuccessMessage('User updated successfully.');
            await loadDashboardUsers();
        } else {
            showErrorMessage(data.message || 'Failed to update user.');
        }
    } catch (error) {
        console.error('Update user error:', error);
        showErrorMessage('Network error while updating user.');
    }
}

async function toggleDashboardUserBlock(userId) {
    try {
        const response = await fetch('../api/admin/toggle_block_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ user_id: userId })
        });
        const data = await response.json();
        if (data.success) {
            showSuccessMessage(data.message || 'User status updated.');
            await loadDashboardUsers();
        } else {
            showErrorMessage(data.message || 'Failed to update block status.');
        }
    } catch (error) {
        console.error('Block toggle error:', error);
        showErrorMessage('Network error while updating block status.');
    }
}

async function deleteDashboardUser(userId) {
    const dialog = await Swal.fire({
        title: 'Delete user?',
        text: 'This removes the user from active records.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete'
    });

    if (!dialog.isConfirmed) return;

    try {
        const response = await fetch('../api/users/delete_user_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id: userId })
        });
        const data = await response.json();
        if (data.success) {
            showSuccessMessage('User deleted successfully.');
            await loadDashboardUsers();
        } else {
            showErrorMessage(data.message || 'Failed to delete user.');
        }
    } catch (error) {
        console.error('Delete user error:', error);
        showErrorMessage('Network error while deleting user.');
    }
}

window.openEditDashboardUserDialog = openEditDashboardUserDialog;
window.toggleDashboardUserBlock = toggleDashboardUserBlock;
window.deleteDashboardUser = deleteDashboardUser;

// Open a conversation in the messages view
function openConversation(contactId) {
    // In a real app, this would navigate to the message view with that contact
    // For now, redirect to the messages page
    window.location.href = DASHBOARD_ROUTES.messages;
}

// Format date as "Jan 15" format
function formatDate(date) {
    if (!date) return 'Date unknown';
    if (!(date instanceof Date)) {
        date = new Date(date);
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// Clean up heartbeat on page unload
window.addEventListener('beforeunload', () => {
    if (statusHeartbeatInterval) {
        clearInterval(statusHeartbeatInterval);
    }

    // Optionally update status to offline when leaving
    if (isAuthenticated && currentUser) {
        updateCurrentUserStatus('offline');
    }
});