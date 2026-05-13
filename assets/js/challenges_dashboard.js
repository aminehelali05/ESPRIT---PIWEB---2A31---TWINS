/**
 * Dashboard Challenges & Gamification Logic
 * Handles user stats, active challenges, and Admin Management (Create/Delete).
 */

// Expose the init function globally so script.js can call it
window.initChallengesDashboard = async function () {
    // Check if user is authenticated via main script
    if (window.currentUser) {
        // Load data immediately
        await loadDashboardGamification();
    } else {
        console.warn('initChallengesDashboard called but currentUser is not set.');
    }
};

async function loadDashboardGamification() {
    try {
        // 1. Load User Stats (Gamification Hero)
        const statsRes = await fetch('../api/challenges/stats.php');
        const stats = await statsRes.json();

        if (stats.success) {
            updateGamificationUI(stats);
            // Check admin access immediately since we know currentUser is set
            if (window.currentUser.role === 'admin' || window.currentUser.role === 'administrator') {
                showAdminInterface();
            }
        }

        // 2. Load User's Active Challenges (For everyone)
        loadMyActiveChallenges();

    } catch (e) {
        console.error("Dashboard gamification error", e);
    }
}


function updateGamificationUI(stats) {
    const elLevel = document.getElementById('dashUserLevel');
    const elTotal = document.getElementById('dashTotalXP');
    const elNext = document.getElementById('dashNextLevelXP');
    const elBar = document.getElementById('dashXPBar');
    const elCompleted = document.getElementById('dashCompletedCount');
    const elTitle = document.getElementById('dashUserTitle');

    if (elLevel) elLevel.textContent = stats.level;
    if (elTotal) elTotal.textContent = stats.total_points + ' XP';
    if (elNext) elNext.textContent = stats.next_level_points;
    if (elBar) elBar.style.width = stats.level_progress_percent + '%';
    if (elCompleted) elCompleted.textContent = stats.completed_challenges;

    if (elTitle) {
        const titles = ['Novice', 'Apprentice', 'Explorer', 'Leader', 'Champion', 'Legend'];
        const titleIndex = Math.min(stats.level - 1, titles.length - 1);
        elTitle.textContent = titles[titleIndex] || 'Hero';
    }
}

async function loadMyActiveChallenges() {
    try {
        const res = await fetch('../api/challenges/my_challenges.php');
        const data = await res.json();

        const tableBody = document.getElementById('dashChallengesTableBody');
        if (!tableBody) return;

        if (data.success && data.challenges.length > 0) {
            const active = data.challenges.filter(c => c.statut === 'en cours');

            if (active.length > 0) {
                tableBody.innerHTML = active.map(c => `
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors">
                        <td class="py-3 px-5 font-medium">${c.titre}</td>
                        <td class="py-3 px-5 text-indigo-500">+${c.points} XP</td>
                        <td class="py-3 px-5"><span class="px-2 py-1 bg-blue-500/10 text-blue-500 rounded text-xs">Active</span></td>
                        <td class="py-3 px-5 text-zinc-500">${new Date(c.date_fin).toLocaleDateString()}</td>
                    </tr>
                `).join('');
            } else {
                renderEmptyTable(tableBody, 'No active challenges.');
            }
        } else {
            renderEmptyTable(tableBody, 'No active challenges.');
        }
    } catch (e) {
        console.error('Error loading active challenges', e);
    }
}

function renderEmptyTable(tbody, msg) {
    tbody.innerHTML = `<tr><td colspan="4" class="py-6 text-center text-zinc-500">${msg} <a href="../vue/challenges.html" class="text-indigo-500 underline">Join one!</a></td></tr>`;
}

// --- Admin Section Logic ---

async function checkAdminAccess() {
    let attempts = 0;
    const interval = setInterval(() => {
        attempts++;
        if (window.currentUser) {
            clearInterval(interval);
            if (window.currentUser.role === 'admin' || window.currentUser.role === 'administrator') {
                showAdminInterface();
            }
        }
        if (attempts > 10) clearInterval(interval);
    }, 200);
}

function showAdminInterface() {
    ['adminChallengesSection', 'adminProofsSection', 'adminQuizSection'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('hidden');
    });

    loadAllChallengesAdmin();
    loadPendingProofs();
    loadAdminQuizzes();
}

// 1. Challenges Management
async function loadAllChallengesAdmin() {
    try {
        const res = await fetch('../api/challenges/list.php');
        const data = await res.json();
        const tbody = document.getElementById('adminChallengesTableBody');
        if (!tbody) return;

        if (data.success && data.challenges.length > 0) {
            tbody.innerHTML = data.challenges.map(c => `
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors border-b border-zinc-100 dark:border-zinc-800/50">
                    <td class="py-3 px-5 font-medium show-tooltip" title="${c.description}">${c.titre}</td>
                    <td class="py-3 px-5">${c.points} XP</td>
                    <td class="py-3 px-5">${c.type}</td>
                    <td class="py-3 px-5 text-zinc-500">${new Date(c.date_fin).toLocaleDateString()}</td>
                    <td class="py-3 px-5">
                       <button onclick="deleteChallenge(${c.id})" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Delete</button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="py-6 text-center text-zinc-500">No challenges found.</td></tr>`;
        }
    } catch (e) {
        console.error('Error loading admin challenges', e);
    }
}

async function createChallenge(event) {
    event.preventDefault();
    const form = event.target;
    // Extract data
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => data[key] = value);

    if (!data.titre || !data.points) return alert('Title and Points are required');

    try {
        const res = await fetch('../api/challenges/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.success) {
            if (typeof Swal !== 'undefined') Swal.fire('Success', 'Challenge created!', 'success');
            else alert('Success');
            closeCreateChallengeModal();
            form.reset();
            loadAllChallengesAdmin();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        console.error(e);
        alert('Failed to create challenge');
    }
}

async function deleteChallenge(id) {
    if (!confirm('Are you sure you want to delete this challenge?')) return;
    try {
        const res = await fetch('../api/challenges/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const result = await res.json();
        if (result.success) loadAllChallengesAdmin();
        else alert('Error: ' + result.message);
    } catch (e) { console.error(e); }
}

// 2. Proof Review Management
async function loadPendingProofs() {
    try {
        const res = await fetch('../api/challenges/admin/list_pending.php');
        const data = await res.json();
        const tbody = document.getElementById('adminProofsTableBody');
        if (!tbody) return;

        if (data.success && data.pending && data.pending.length > 0) {
            tbody.innerHTML = data.pending.map(p => {
                // Check if proof has image
                let proofDisplay = p.preuve;
                let imageLink = '';
                if (p.preuve.includes('[IMAGE]')) {
                    const parts = p.preuve.split('[IMAGE]');
                    proofDisplay = parts[0];
                    const imgUrl = parts[1];
                    imageLink = `<br><a href="../${imgUrl}" target="_blank" class="text-indigo-500 hover:underline text-xs flex items-center gap-1 mt-1"><i data-lucide="image" class="w-3 h-3"></i> View Image</a>`;
                }

                return `
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors border-b border-zinc-100 dark:border-zinc-800/50">
                    <td class="py-3 px-5">
                        <div class="flex items-center gap-2">
                            <img src="${p.avatar_url || 'https://api.placeholder.com/30/30'}" class="w-6 h-6 rounded-full">
                            <span class="font-medium text-sm">${p.username}</span>
                        </div>
                    </td>
                    <td class="py-3 px-5 text-sm">${p.challenge_title} <span class="text-xs text-zinc-500">(${p.points} XP)</span></td>
                    <td class="py-3 px-5 text-sm text-zinc-600 dark:text-zinc-400 max-w-xs break-words">
                        ${proofDisplay || 'No text provided'}
                        ${imageLink}
                    </td>
                    <td class="py-3 px-5 text-sm text-zinc-500">${new Date(p.date_completion).toLocaleDateString()}</td>
                    <td class="py-3 px-5">
                       <div class="flex gap-2">
                           <button onclick="reviewProof(${p.progress_id}, 'approve')" class="p-1 text-emerald-600 hover:bg-emerald-50 rounded"><i data-lucide="check" class="w-4 h-4"></i></button>
                           <button onclick="reviewProof(${p.progress_id}, 'reject')" class="p-1 text-rose-600 hover:bg-rose-50 rounded"><i data-lucide="x" class="w-4 h-4"></i></button>
                       </div>
                    </td>
                </tr>
            `}).join('');
            lucide.createIcons();
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="py-6 text-center text-zinc-500">No pending proofs.</td></tr>`;
        }
    } catch (e) {
        console.error('Error loading pending proofs', e);
    }
}

async function reviewProof(id, action) {
    if (!confirm(`Are you sure you want to ${action} this proof?`)) return;
    try {
        const res = await fetch('../api/challenges/admin/approve.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ progress_id: id, action: action })
        });
        const result = await res.json();
        if (result.success) {
            loadPendingProofs();
            loadDashboardGamification(); // Refresh stats if affected
            if (typeof Swal !== 'undefined') Swal.fire('Success', result.message, 'success');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) { console.error(e); }
}


// 3. Quiz Management
async function loadAdminQuizzes() {
    try {
        const res = await fetch('../api/challenges/quiz/list.php');
        const data = await res.json();
        const tbody = document.getElementById('adminQuizTableBody');
        if (!tbody) return;

        if (data.success && data.quizzes && data.quizzes.length > 0) {
            tbody.innerHTML = data.quizzes.map(q => {
                // Parse options if string (DB might return string or json depending on driver, usually string for longtext)
                // Wait, in JS fetch, JSON response parses JSON columns automatically if PHP sends them as array?
                // PHP `list.php` does `fetchAll`. `options` column is JSON.
                // Depending on PDO config, it might be string or object. 
                // Let's safe parse.
                let options = q.options;
                if (typeof options === 'string') {
                    try { options = JSON.parse(options); } catch (e) { }
                }
                const correct = options[q.correct_index];

                return `
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition-colors border-b border-zinc-100 dark:border-zinc-800/50">
                    <td class="py-3 px-5">
                        <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">${q.question}</div>
                        <div class="text-xs text-zinc-500 mt-1">Ans: <span class="text-emerald-500">${correct}</span></div>
                    </td>
                    <td class="py-3 px-5 text-sm">${q.points} pts</td>
                    <td class="py-3 px-5">
                       <button onclick="deleteQuizQuestion(${q.id})" class="text-rose-600 hover:text-rose-800 text-xs font-medium">Delete</button>
                    </td>
                </tr>
            `}).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="3" class="py-6 text-center text-zinc-500">No quiz questions found.</td></tr>`;
        }
    } catch (e) {
        console.error('Error loading quizzes', e);
    }
}

async function createQuiz(event) {
    event.preventDefault();
    const form = event.target;
    // We need to construct the options array
    const formData = new FormData(form);
    const data = {
        question: formData.get('question'),
        points: formData.get('points'),
        correct_index: formData.get('correct_index'),
        options: [
            formData.get('option_0'),
            formData.get('option_1'),
            formData.get('option_2'),
            formData.get('option_3')
        ]
    };

    if (!data.question || data.options.some(o => !o)) return alert("Please fill all fields");

    try {
        const res = await fetch('../api/challenges/quiz/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            closeCreateQuizModal();
            form.reset();
            loadAdminQuizzes();
            if (typeof Swal !== 'undefined') Swal.fire('Success', 'Question added!', 'success');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) { console.error(e); }
}

async function deleteQuizQuestion(id) {
    if (!confirm('Start removing this question?')) return;
    try {
        const res = await fetch('../api/challenges/quiz/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const result = await res.json();
        if (result.success) loadAdminQuizzes();
        else alert('Error: ' + result.message);
    } catch (e) { console.error(e); }
}

// Modal Functions
function openCreateChallengeModal() {
    document.getElementById('createChallengeModal').classList.remove('hidden');
    document.getElementById('createChallengeModal').classList.add('flex');
}
function closeCreateChallengeModal() {
    document.getElementById('createChallengeModal').classList.add('hidden');
    document.getElementById('createChallengeModal').classList.remove('flex');
}

function openCreateQuizModal() {
    document.getElementById('createQuizModal').classList.remove('hidden');
    document.getElementById('createQuizModal').classList.add('flex');
}
function closeCreateQuizModal() {
    document.getElementById('createQuizModal').classList.add('hidden');
    document.getElementById('createQuizModal').classList.remove('flex');
}

// Expose functions
window.createChallenge = createChallenge;
window.deleteChallenge = deleteChallenge;
window.openCreateChallengeModal = openCreateChallengeModal;
window.closeCreateChallengeModal = closeCreateChallengeModal;
window.reviewProof = reviewProof;
window.createQuiz = createQuiz;
window.deleteQuizQuestion = deleteQuizQuestion;
window.openCreateQuizModal = openCreateQuizModal;
window.closeCreateQuizModal = closeCreateQuizModal;
