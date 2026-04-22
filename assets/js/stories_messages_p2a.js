let selectedStoryFile = null;
let currentStoryData = null;
let currentStoryIndex = 0;
let storyTimeout = null;

const STORY_API_BASE = '../../index.php';

function storyApiUrl(action, params = {}) {
    const search = new URLSearchParams({ action, ...params });
    return `${STORY_API_BASE}?${search.toString()}`;
}

function resolveStoryMediaUrl(path) {
    const raw = String(path || '').trim();
    if (!raw) return '';

    if (/^(https?:|data:|blob:)/i.test(raw)) return raw;

    const normalized = raw.replace(/\\/g, '/');
    if (normalized.startsWith('/')) return normalized;

    const cleaned = normalized
        .replace(/^(\.\/)+/, '')
        .replace(/^(\.\.\/)+/, '');

    if (cleaned.startsWith('assets/')) {
        return `../../${cleaned}`;
    }
    if (cleaned.startsWith('uploads/')) {
        return `../../assets/${cleaned}`;
    }

    const fileName = cleaned.split('/').filter(Boolean).pop() || cleaned;
    if (!fileName) return '';
    return `../../assets/uploads/stories/${fileName}`;
}

function storyAvatarForUser(entry) {
    const avatar = String(entry?.author_avatar || entry?.avatar_url || '').trim();
    if (avatar) return avatar;
    const name = String(entry?.author_name || entry?.first_name || 'User').trim() || 'User';
    return `https://api.dicebear.com/9.x/adventurer/svg?seed=${encodeURIComponent(name)}`;
}

function storyDisplayName(entry) {
    const fullName = [entry?.first_name, entry?.last_name].filter(Boolean).join(' ').trim();
    if (fullName) return fullName;
    return String(entry?.author_name || entry?.name || 'User');
}

function createStory() {
    const modal = document.getElementById('storyUploadModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeStoryUploadModal() {
    const modal = document.getElementById('storyUploadModal');
    if (!modal) return;

    modal.classList.add('hidden');
    modal.classList.remove('flex');
    selectedStoryFile = null;

    const fi = document.getElementById('storyFileInput');
    if (fi) fi.value = '';

    const preview = document.getElementById('storyPreview');
    if (preview) preview.style.display = 'none';

    const img = document.getElementById('storyPreviewImage');
    if (img) {
        img.style.display = 'none';
        img.src = '';
    }

    const vid = document.getElementById('storyPreviewVideo');
    if (vid) {
        vid.style.display = 'none';
        vid.pause();
        vid.src = '';
    }

    const btn = document.getElementById('uploadStoryBtn');
    if (btn) btn.disabled = true;
}

function previewStory(input) {
    if (!input?.files || !input.files[0]) return;

    const file = input.files[0];
    selectedStoryFile = file;

    const preview = document.getElementById('storyPreview');
    const previewImage = document.getElementById('storyPreviewImage');
    const previewVideo = document.getElementById('storyPreviewVideo');
    if (!preview || !previewImage || !previewVideo) return;

    const objectUrl = URL.createObjectURL(file);
    preview.style.display = 'block';

    if (String(file.type || '').startsWith('video/')) {
        previewVideo.src = objectUrl;
        previewVideo.style.display = 'block';
        previewImage.style.display = 'none';
    } else {
        previewImage.src = objectUrl;
        previewImage.style.display = 'block';
        previewVideo.style.display = 'none';
    }

    const uploadBtn = document.getElementById('uploadStoryBtn');
    if (uploadBtn) uploadBtn.disabled = false;
}

async function uploadStory() {
    if (!selectedStoryFile) {
        if (window.Swal) Swal.fire('Error', 'Please select a file', 'error');
        return;
    }

    const btn = document.getElementById('uploadStoryBtn');
    const btnText = document.getElementById('uploadBtnText');
    if (btn) btn.disabled = true;
    if (btnText) btnText.textContent = 'Uploading...';

    const storyType = String(selectedStoryFile.type || '').startsWith('video/') ? 'video' : 'image';
    const formData = new FormData();
    formData.append('story_type', storyType);
    formData.append('caption', '');
    formData.append('duration', storyType === 'video' ? '8' : '5');
    formData.append('visibility', 'public');
    formData.append('media', selectedStoryFile);

    try {
        const response = await fetch(storyApiUrl('create_story'), {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        });
        const data = await response.json();

        if (data?.success) {
            if (window.Swal) Swal.fire('Success', 'Story posted successfully', 'success');
            closeStoryUploadModal();
            await loadStoriesStrip();
        } else {
            const message = String(data?.message || 'Failed to upload story');
            if (window.Swal) Swal.fire('Error', message, 'error');
        }
    } catch (error) {
        console.error('uploadStory error:', error);
        if (window.Swal) Swal.fire('Error', 'Failed to upload story', 'error');
    } finally {
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Share Story';
    }
}

async function loadStoriesStrip() {
    const container = document.getElementById('storiesContainer');
    if (!container) return;

    try {
        const response = await fetch(storyApiUrl('get_story_users'), { credentials: 'same-origin' });
        const users = await response.json();
        const userList = Array.isArray(users) ? users : [];

        if (!userList.length) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = '';
        userList.forEach((userEntry) => {
            const userName = storyDisplayName(userEntry);
            const userAvatar = storyAvatarForUser({
                author_avatar: userEntry.avatar_url,
                author_name: userName,
            });
            const userId = Number(userEntry.id || userEntry.user_id || 0);

            const storyCircle = document.createElement('div');
            storyCircle.className = 'flex flex-col items-center gap-1 cursor-pointer group flex-shrink-0';
            storyCircle.addEventListener('click', () => viewStory({
                user_id: userId,
                author_name: userName,
                author_avatar: userAvatar,
            }));

            storyCircle.innerHTML = `
                <div class="relative">
                    <div class="w-14 h-14 rounded-full border-2 border-white dark:border-zinc-900 p-[2px] ring-2 ring-blue-500">
                        <img src="${userAvatar}" class="w-full h-full rounded-full object-cover" alt="${userName} story">
                    </div>
                </div>
                <span class="text-[11px] text-gray-600 dark:text-gray-400 font-medium w-16 text-center truncate mt-1">${userName}</span>
            `;

            container.appendChild(storyCircle);
        });
    } catch (error) {
        console.error('loadStoriesStrip error:', error);
    }
}

async function viewStory(userStories) {
    const userId = Number(userStories?.user_id || userStories?.id || 0);
    if (!userId) return;

    try {
        const response = await fetch(storyApiUrl('get_stories', { user_id: userId }), { credentials: 'same-origin' });
        const stories = await response.json();
        const storyList = Array.isArray(stories) ? stories : [];
        if (!storyList.length) {
            if (window.Swal) Swal.fire('Info', 'No active stories found.', 'info');
            return;
        }

        currentStoryData = {
            author_name: String(userStories?.author_name || storyDisplayName(userStories) || 'User'),
            author_avatar: storyAvatarForUser(userStories),
            stories: storyList,
        };
        currentStoryIndex = 0;

        const modal = document.getElementById('storyViewerModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        const avatar = document.getElementById('storyViewerAvatar');
        if (avatar) avatar.src = currentStoryData.author_avatar;
        const name = document.getElementById('storyViewerName');
        if (name) name.textContent = currentStoryData.author_name;

        const progressContainer = document.getElementById('storyProgressBars');
        if (progressContainer) {
            progressContainer.innerHTML = '';
            currentStoryData.stories.forEach((_, index) => {
                const bar = document.createElement('div');
                bar.className = 'h-1 flex-1 bg-gray-500/50 rounded-full overflow-hidden';
                bar.innerHTML = `<div class="h-full bg-white transition-all duration-300" style="width:0%" data-progress-bar="${index}"></div>`;
                progressContainer.appendChild(bar);
            });
        }

        showStoryItem(0);
    } catch (error) {
        console.error('viewStory error:', error);
        if (window.Swal) Swal.fire('Error', 'Could not load stories.', 'error');
    }
}

function showStoryItem(index) {
    if (!currentStoryData || !Array.isArray(currentStoryData.stories) || index >= currentStoryData.stories.length) {
        closeStoryViewer();
        return;
    }

    currentStoryIndex = index;
    const story = currentStoryData.stories[index] || {};
    const mediaUrl = resolveStoryMediaUrl(story.media_url || story.image_path || story.image_url || '');
    const type = String(story.story_type || '').toLowerCase();
    const isVideo = type === 'video' || /\.(mp4|webm|ogg|mov)$/i.test(mediaUrl);

    document.querySelectorAll('[data-progress-bar]').forEach((bar, barIndex) => {
        if (barIndex < index) {
            bar.style.width = '100%';
            return;
        }
        if (barIndex > index) {
            bar.style.width = '0%';
            return;
        }
        bar.style.width = '0%';
        requestAnimationFrame(() => {
            bar.style.width = '100%';
        });
    });

    const image = document.getElementById('storyViewerImage');
    const video = document.getElementById('storyViewerVideo');
    if (!image || !video) return;

    if (storyTimeout) {
        clearTimeout(storyTimeout);
        storyTimeout = null;
    }
    video.onended = null;

    if (!mediaUrl) {
        image.style.display = 'none';
        video.style.display = 'none';
        storyTimeout = setTimeout(() => nextStory(), 3500);
        return;
    }

    if (isVideo) {
        video.src = mediaUrl;
        video.style.display = 'block';
        image.style.display = 'none';
        video.currentTime = 0;
        video.play().catch(() => {});

        const fallbackMs = Math.max(4000, Number(story.duration || 8) * 1000);
        storyTimeout = setTimeout(() => nextStory(), fallbackMs);
        video.onended = () => {
            if (storyTimeout) {
                clearTimeout(storyTimeout);
                storyTimeout = null;
            }
            nextStory();
        };
        return;
    }

    image.src = mediaUrl;
    image.style.display = 'block';
    video.style.display = 'none';
    const durationMs = Math.max(3000, Number(story.duration || 5) * 1000);
    storyTimeout = setTimeout(() => nextStory(), durationMs);
}

function nextStory() {
    if (!currentStoryData || !Array.isArray(currentStoryData.stories)) return;
    if (currentStoryIndex < currentStoryData.stories.length - 1) {
        showStoryItem(currentStoryIndex + 1);
        return;
    }
    closeStoryViewer();
}

function previousStory() {
    if (!currentStoryData || !Array.isArray(currentStoryData.stories)) return;
    if (currentStoryIndex > 0) {
        showStoryItem(currentStoryIndex - 1);
    }
}

function closeStoryViewer() {
    const modal = document.getElementById('storyViewerModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    if (storyTimeout) {
        clearTimeout(storyTimeout);
        storyTimeout = null;
    }

    const video = document.getElementById('storyViewerVideo');
    if (video) {
        video.pause();
        video.src = '';
    }

    currentStoryData = null;
    currentStoryIndex = 0;
}

document.addEventListener('DOMContentLoaded', () => {
    loadStoriesStrip();
    setInterval(loadStoriesStrip, 60000);
});