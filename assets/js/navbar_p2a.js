// Migrated navbar from Projet-2A/vue/assets/js/navbar.js
// API paths updated to point at /Projet-2A/api/

document.addEventListener('DOMContentLoaded', function () {
    initNavbar();
    setupLogoutButtons();
    if (typeof VoiceAssistant !== 'undefined' && VoiceAssistant.isSupported()) {
        initNavbarVoiceButton();
    }
});

function initNavbar() {
    const profileMenu = document.querySelector('.profile-menu');
    const dropdown = profileMenu?.querySelector('.dropdown');

    if (profileMenu && dropdown) {
        profileMenu.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', function (e) {
            if (!profileMenu.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });
    }

    const navbar = document.getElementById('navbar');
    if (navbar) {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    updateUserInfoInNavbar();
}

async function updateUserInfoInNavbar() {
    try {
        const response = await fetch('/Projet-2A/api/users/check_auth.php');
        const result = await response.json();

        if (result.success && result.authenticated && result.user) {
            const user = result.user;
            const profileAvatar = document.querySelector('.profile-avatar img');
            const commentUserAvatar = document.getElementById('commentUserAvatar');
            const commentUserName = document.getElementById('commentUserName');

            if (profileAvatar) {
                profileAvatar.src = user.avatar_url || `https://api.dicebear.com/7.x/avataaars/svg?seed=${encodeURIComponent(user.name || 'user')}`;
                profileAvatar.alt = user.name;
            }

            if (commentUserAvatar) {
                commentUserAvatar.textContent = user.name ? user.name.charAt(0).toUpperCase() : 'U';
            }

            if (commentUserName) {
                commentUserName.textContent = user.name || 'User';
            }
        }
    } catch (error) {
        console.error("Error updating user info in navbar:", error);
    }
}

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

function refreshNavbarIcons() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

async function handleLogout() {
    try {
        const response = await fetch('/Projet-2A/api/users/logout.php', {
            method: 'POST',
            credentials: 'include'
        });
        const result = await response.json();
        if (result.success) {
            console.log('Logged out successfully');
        }
    } catch (e) {
        console.error('Logout error:', e);
    } finally {
        localStorage.clear();
        sessionStorage.clear();
        window.location.href = '/Projet-2A/vue/auth/login.html';
    }
}

function setupLogoutButtons() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            handleLogout();
        });
    }

    document.querySelectorAll('a[href="#"]').forEach(link => {
        const icon = link.querySelector('i[data-lucide="log-out"]');
        const text = link.textContent.toLowerCase();
        if (icon || text.includes('logout')) {
            const newLink = link.cloneNode(true);
            link.parentNode.replaceChild(newLink, link);

            newLink.addEventListener('click', function (e) {
                e.preventDefault();
                handleLogout();
            });
        }
    });
}

function initNavbarVoiceButton() {
    const navVoiceBtn = document.querySelector('.voice-nav-btn');
    if (!navVoiceBtn) return;

    navVoiceBtn.addEventListener('click', () => {
        if (window.voiceAssistant) {
            if (!window.voiceAssistant.isActive) {
                window.voiceAssistant.activate();
            } else {
                window.voiceAssistant.deactivate();
            }
        }
    });
}