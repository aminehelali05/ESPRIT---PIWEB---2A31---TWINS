const MeetingsManager = {
    meetings: [],
    currentTab: 'upcoming',
    meetingsCache: {},

    /**
     * Get avatar URL with fallback
     */
    getAvatarUrl(user) {
        // If avatar_url exists and is not empty, use it
        if (user && user.avatar_url && user.avatar_url.trim() !== '') {
            return user.avatar_url;
        }
        // Fallback to DiceBear initials avatar
        const initials = user.name ? user.name.split(' ').map(n => n[0]).join('').toUpperCase() : 'U';
        return `https://api.dicebear.com/7.x/initials/svg?seed=${encodeURIComponent(initials)}`;
    },

    /**
     * Initialize meetings manager
     */
    async init() {
        console.log('Initializing Meetings Manager...');
        await this.loadMeetings();
        this.setupEventListeners();
        this.renderMeetings();
    },

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Tab switching
        document.querySelectorAll('.meeting-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchTab(tab.dataset.tab);
            });
        });

        // Create meeting button
        const createBtn = document.getElementById('createMeetingBtn');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.openCreateModal());
        }

        // Create meeting form submit
        const createForm = document.getElementById('createMeetingForm');
        if (createForm) {
            createForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createMeeting();
            });
        }
    },

    /**
     * Load all users for invitation dropdown
     */
    async loadUsers() {
        const select = document.getElementById('meetingParticipants');
        if (!select) {
            console.error('meetingParticipants select not found');
            return;
        }

        try {
            select.innerHTML = '<option disabled selected>Loading users...</option>';
            console.log('Fetching users from API...');

            const response = await fetch('../api/users/get_all_users.php');
            console.log('API response status:', response.status);

            const data = await response.json();
            console.log('API data:', data);

            if (data.success && data.users && Array.isArray(data.users)) {
                if (data.users.length > 0) {
                    select.innerHTML = data.users.map(user =>
                        `<option value="${user.id}">${user.name} (${user.email})</option>`
                    ).join('');
                    console.log(`✓ Loaded ${data.users.length} users for invitation`);
                } else {
                    select.innerHTML = '<option disabled>No other users available</option>';
                    console.warn('No users returned from API');
                }
            } else {
                select.innerHTML = '<option disabled>Failed to load users</option>';
                console.error('API returned error:', data);
            }
        } catch (error) {
            console.error('Error loading users:', error);
            select.innerHTML = '<option disabled>Error loading users - check console</option>';
        }
    },

    /**
     * Open create meeting modal
     */
    openCreateModal() {
        const modal = document.getElementById('createMeetingModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            this.loadUsers();
            lucide.createIcons();
        }
    },

    /**
     * Close create meeting modal
     */
    closeCreateModal() {
        const modal = document.getElementById('createMeetingModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            document.getElementById('createMeetingForm').reset();
        }
    },

    /**
     * COMMENT 2 FIX: Load meetings from API based on current tab
     */
    async loadMeetings(forceRefresh = false) {
        // Check cache first
        if (!forceRefresh && this.meetingsCache[this.currentTab]) {
            this.meetings = this.meetingsCache[this.currentTab];
            console.log(`Loaded ${this.currentTab} meetings from cache:`, this.meetings);
            return;
        }

        try {
            let endpoint = '';

            // COMMENT 2 FIX: Use different endpoints based on tab
            switch (this.currentTab) {
                case 'upcoming':
                    endpoint = '../api/meetings/get-upcoming.php';
                    break;
                case 'my':
                    endpoint = '../api/meetings/get-my-meetings.php';
                    break;
                case 'past':
                    // Use get-all with status filter for past meetings
                    endpoint = '../api/meetings/get-all.php?status=completed,cancelled';
                    break;
                default:
                    endpoint = '../api/meetings/get-upcoming.php';
            }

            const response = await fetch(endpoint);
            const data = await response.json();

            if (data.success) {
                this.meetings = data.meetings || data.data?.meetings || [];

                // COMMENT 2 FIX: Cache the results
                this.meetingsCache[this.currentTab] = this.meetings;

                console.log(`Loaded ${this.currentTab} meetings:`, this.meetings);
            } else {
                console.error('Failed to load meetings:', data.message);
                this.meetings = [];
            }
        } catch (error) {
            console.error('Error loading meetings:', error);
            this.meetings = [];
        }
    },

    /**
     * COMMENT 2 FIX: Switch tab and load appropriate data
     */
    async switchTab(tabName) {
        this.currentTab = tabName;

        // Update tab UI
        document.querySelectorAll('.meeting-tab').forEach(tab => {
            if (tab.dataset.tab === tabName) {
                tab.classList.add('active', 'bg-zinc-100', 'dark:bg-white/5', 'border', 'border-zinc-200', 'dark:border-zinc-800/50', 'border-b-0', 'text-zinc-900', 'dark:text-zinc-100');
                tab.classList.remove('text-zinc-600', 'dark:text-zinc-400');
            } else {
                tab.classList.remove('active', 'bg-zinc-100', 'dark:bg-white/5', 'border', 'border-zinc-200', 'dark:border-zinc-800/50', 'border-b-0', 'text-zinc-900', 'dark:text-zinc-100');
                tab.classList.add('text-zinc-600', 'dark:text-zinc-400');
            }
        });

        await this.loadMeetings();
        this.renderMeetings();
    },

    /**
     * Render meetings based on current tab
     */
    renderMeetings() {
        const container = document.getElementById('meetingsContainer');
        if (!container) return;

        let filteredMeetings = this.meetings;
        const now = new Date();

        // Filter based on tab
        if (this.currentTab === 'upcoming') {
            filteredMeetings = this.meetings.filter(m => new Date(m.scheduled_time) > now);
        } else if (this.currentTab === 'past') {
            filteredMeetings = this.meetings.filter(m => new Date(m.scheduled_time) <= now);
        }

        if (filteredMeetings.length === 0) {
            container.innerHTML = `
                <div class="col-span-full flex flex-col items-center justify-center py-12">
                    <i data-lucide="video-off" class="w-16 h-16 text-zinc-300 dark:text-zinc-600 mb-4"></i>
                    <p class="text-zinc-500 text-sm">No ${this.currentTab} meetings</p>
                </div>
            `;
            lucide.createIcons();
            return;
        }

        container.innerHTML = filteredMeetings.map(meeting => this.createMeetingCard(meeting)).join('');
        lucide.createIcons();
    },

    /**
     * Create meeting card HTML
     */
    createMeetingCard(meeting) {
        const scheduledDate = new Date(meeting.scheduled_time);
        const formattedDate = scheduledDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const formattedTime = scheduledDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

        const statusColors = {
            scheduled: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
            active: 'bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/20',
            completed: 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400 border-zinc-500/20',
            cancelled: 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20'
        };

        return `
            <div class="bg-white dark:bg-zinc-900/20 backdrop-blur-sm border border-zinc-200 dark:border-zinc-800/60 rounded-xl p-5 hover:border-purple-500/30 transition-all shadow-sm dark:shadow-none">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">${meeting.title}</h4>
                        <p class="text-xs text-zinc-500 line-clamp-2">${meeting.description || 'No description'}</p>
                    </div>
                    <span class="px-2 py-1 text-[10px] font-medium rounded-lg border ${statusColors[meeting.status] || statusColors.scheduled}">
                        ${meeting.status}
                    </span>
                </div>
                
                <div class="space-y-2 mb-4">
                    <div class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                        <span>${formattedDate} at ${formattedTime}</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                        <span>${meeting.duration || 60} minutes</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i>
                        <span>${meeting.participants_count || 0} participants</span>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    ${meeting.participation_status === 'invited' ? `
                        <button onclick="MeetingsManager.respondToInvite(${meeting.id}, 'accepted')" 
                            class="flex-1 px-3 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>Accept
                        </button>
                        <button onclick="MeetingsManager.respondToInvite(${meeting.id}, 'declined')" 
                            class="flex-1 px-3 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>Decline
                        </button>
                    ` : (meeting.status === 'scheduled' || meeting.status === 'active') ? `
                        <button onclick="MeetingsManager.joinMeeting(${meeting.id})" 
                            class="flex-1 px-3 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-2">
                            <i data-lucide="video" class="w-3.5 h-3.5"></i>
                            Join Meeting
                        </button>
                    ` : ''}
                    <button onclick="MeetingsManager.viewDetails(${meeting.id})" 
                        class="px-3 py-2 bg-zinc-100 dark:bg-zinc-800/50 hover:bg-zinc-200 dark:hover:bg-zinc-800 text-zinc-700 dark:text-zinc-300 rounded-lg text-xs font-medium transition-all">
                        Details
                    </button>
                </div>
            </div>
        `;
    },

    /**
     * Close modal helper
     */
    closeModal() {
        const modal = document.getElementById('createMeetingModal');
        if (modal) {
            modal.style.display = 'none';
            document.getElementById('createMeetingForm').reset();
        }
    },

    /**
     * Create new meeting with participant invitations
     */
    async createMeeting() {
        const form = document.getElementById('createMeetingForm');
        const formData = new FormData(form);

        // Get selected participants
        const select = document.getElementById('meetingParticipants');
        const participantIds = select ? Array.from(select.selectedOptions).map(opt => parseInt(opt.value)) : [];

        const meetingData = {
            title: formData.get('title'),
            description: formData.get('description'),
            scheduled_time: formData.get('scheduled_time'),
            duration: parseInt(formData.get('duration')) || 60,
            participant_ids: participantIds
        };

        try {
            const response = await fetch('../api/meetings/create.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(meetingData)
            });

            const data = await response.json();

            if (data.success) {
                this.closeCreateModal();
                await this.loadMeetings(true);
                this.renderMeetings();

                const inviteCount = participantIds.length;
                this.showNotification(
                    inviteCount > 0
                        ? `Meeting created and ${inviteCount} user(s) invited!`
                        : 'Meeting created successfully!',
                    'success'
                );
            } else {
                this.showNotification(data.message || 'Failed to create meeting', 'error');
            }
        } catch (error) {
            console.error('Error creating meeting:', error);
            this.showNotification('An error occurred', 'error');
        }
    },

    /**
     * Join meeting
     */
    async joinMeeting(meetingId) {
        try {
            const response = await fetch('../api/meetings/join.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ meeting_id: meetingId })
            });

            const data = await response.json();

            if (data.success && data.room_url) {
                window.location.href = data.room_url;
            } else {
                this.showNotification(data.message || 'Failed to join meeting', 'error');
            }
        } catch (error) {
            console.error('Error joining meeting:', error);
            this.showNotification('An error occurred', 'error');
        }
    },

    /**
     * View meeting details
     */
    /**
     * View meeting details
     */
    async viewDetails(meetingId) {
        const meeting = this.meetings.find(m => m.id === meetingId);
        if (!meeting) return;

        const scheduledDate = new Date(meeting.scheduled_time);
        const formattedDate = scheduledDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const formattedTime = scheduledDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

        Swal.fire({
            title: meeting.title,
            html: `
                <div class="text-left">
                    <div class="mb-4">
                        <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Description</span>
                        <p class="text-zinc-700 dark:text-zinc-300 mt-1">${meeting.description || 'No description provided.'}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Date</span>
                            <p class="text-zinc-900 dark:text-zinc-100 font-medium">${formattedDate}</p>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Time</span>
                            <p class="text-zinc-900 dark:text-zinc-100 font-medium">${formattedTime}</p>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Duration</span>
                            <p class="text-zinc-900 dark:text-zinc-100 font-medium">${meeting.duration} minutes</p>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Status</span>
                            <p class="capitalize text-zinc-900 dark:text-zinc-100 font-medium">${meeting.status}</p>
                        </div>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Participants</span>
                        <p class="text-zinc-900 dark:text-zinc-100 font-medium">${meeting.participants_count || 0} invited</p>
                    </div>
                </div>
            `,
            showCloseButton: true,
            showConfirmButton: meeting.status === 'scheduled' || meeting.status === 'active',
            confirmButtonText: 'Join Meeting',
            confirmButtonColor: '#7c3aed', // Purple
            showCancelButton: true,
            cancelButtonText: 'Close',
            background: document.documentElement.classList.contains('dark') ? '#18181b' : '#fff',
            color: document.documentElement.classList.contains('dark') ? '#e4e4e7' : '#18181b'
        }).then((result) => {
            if (result.isConfirmed) {
                this.joinMeeting(meeting.id);
            }
        });
    },

    /**
     * Respond to meeting invitation (accept/decline)
     */
    async respondToInvite(meetingId, status) {
        try {
            const res = await fetch('../api/meetings/respond.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ meeting_id: meetingId, status: status })
            });

            const data = await res.json();

            if (data.success) {
                this.showNotification(
                    status === 'accepted' ? 'Meeting invitation accepted!' : 'Meeting invitation declined',
                    'success'
                );
                await this.loadMeetings(true);
                this.renderMeetings();
            } else {
                this.showNotification(data.message || 'Failed to respond to invitation', 'error');
            }
        } catch (error) {
            console.error('Error responding to invite:', error);
            this.showNotification('An error occurred', 'error');
        }
    },

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Simple notification - can be enhanced with SweetAlert2
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            info: 'bg-blue-500'
        };

        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg z-50 animate-fade-in`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
};

// Auto-initialize when meetings page is active
document.addEventListener('DOMContentLoaded', () => {
    // Check if meetings page is active
    const meetingsPage = document.getElementById('meetings');
    if (meetingsPage && meetingsPage.classList.contains('active')) {
        MeetingsManager.init();
    }
});

// Export for global access
window.MeetingsManager = MeetingsManager;
