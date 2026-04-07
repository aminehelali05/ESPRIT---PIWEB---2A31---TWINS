// Gamified Captcha Logic
document.addEventListener('DOMContentLoaded', () => {
    const icons = ['heart', 'star', 'sun', 'moon', 'cloud', 'zap', 'anchor', 'music', 'camera', 'flag', 'bell', 'lock'];
    let targetIcon = '';
    let isVerified = false;

    // Create Captcha Elements
    const captchaContainer = document.getElementById('gamified-captcha-container');
    if (!captchaContainer) return;

    captchaContainer.innerHTML = `
        <input type="hidden" name="gamified_token" id="gamified_token" value="">
        <button type="button" id="captcha-trigger-btn" class="w-full py-3 px-4 bg-white dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 hover:border-indigo-500/50 rounded-xl flex items-center justify-between group transition-all duration-300 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-6 h-6 rounded-md bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center group-hover:bg-indigo-500/20 transition-colors">
                    <i data-lucide="shield-check" class="w-4 h-4 text-zinc-500 dark:text-zinc-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400"></i>
                </div>
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white" id="captcha-text">Verify you are human</span>
            </div>
            <div class="w-2 h-2 rounded-full bg-zinc-300 dark:bg-zinc-600" id="captcha-status-dot"></div>
        </button>

        <!-- Captcha Modal -->
        <div id="captcha-modal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-2xl p-6 max-w-sm w-full mx-4 transform scale-95 transition-transform duration-300 shadow-2xl">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-zinc-800 dark:text-white">Security Check</h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">Select the</span>
                            <div class="bg-indigo-50 dark:bg-indigo-500/20 p-1.5 rounded-lg border border-indigo-200 dark:border-indigo-500/30">
                                <i id="target-icon-display" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">icon</span>
                        </div>
                    </div>
                    <button type="button" id="close-captcha-modal" class="text-zinc-400 hover:text-zinc-800 dark:hover:text-white transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-4 gap-3 mb-4" id="captcha-grid">
                    <!-- Icons generated here -->
                </div>

                <div class="text-center">
                    <p class="text-xs text-zinc-600">Gamified Verification</p>
                </div>
            </div>
        </div>
    `;

    // Re-initialize icons for the new button
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const triggerBtn = document.getElementById('captcha-trigger-btn');
    const modal = document.getElementById('captcha-modal');
    const closeBtn = document.getElementById('close-captcha-modal');
    const grid = document.getElementById('captcha-grid');
    const targetIconDisplay = document.getElementById('target-icon-display');
    const tokenInput = document.getElementById('gamified_token');
    const statusDot = document.getElementById('captcha-status-dot');
    const captchaText = document.getElementById('captcha-text');

    triggerBtn.addEventListener('click', () => {
        if (isVerified) return;
        openModal();
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    function openModal() {
        modal.classList.remove('hidden');
        // Slight delay for animation
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
            modal.querySelector('div').classList.add('scale-100');
        }, 10);
        generateGame();
    }

    function closeModal() {
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.remove('scale-100');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function generateGame() {
        grid.innerHTML = '';
        // Pick 8 random icons from the list + 1 target
        // Actually lets just pick 9 random icons
        const gameIcons = [];

        // Select target
        targetIcon = icons[Math.floor(Math.random() * icons.length)];

        // Update Target Display
        targetIconDisplay.setAttribute('data-lucide', targetIcon);

        // Fill grid with random icons including at least one target
        // Let's create a set of 12 icons
        const gridIcons = [targetIcon];
        while (gridIcons.length < 12) {
            const randomIcon = icons[Math.floor(Math.random() * icons.length)];
            gridIcons.push(randomIcon);
        }

        // Shuffle
        gridIcons.sort(() => Math.random() - 0.5);

        gridIcons.forEach(icon => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'aspect-square bg-zinc-50 hover:bg-zinc-100 dark:bg-zinc-800 dark:hover:bg-zinc-700 rounded-xl flex items-center justify-center transition-all hover:scale-105 active:scale-95 border border-zinc-200 dark:border-zinc-700/50';
            btn.innerHTML = `<i data-lucide="${icon}" class="w-6 h-6 text-zinc-600 dark:text-zinc-300"></i>`;
            btn.onclick = () => checkAnswer(icon, btn);
            grid.appendChild(btn);
        });

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function checkAnswer(selectedIcon, btn) {
        if (selectedIcon === targetIcon) {
            // Success
            btn.classList.remove('bg-zinc-50', 'bg-zinc-800', 'border-zinc-200', 'dark:border-zinc-700/50');
            btn.classList.add('bg-indigo-500', 'border-indigo-500', 'text-white');
            
            const btnIcon = btn.querySelector('svg') || btn.querySelector('i');
            if (btnIcon) {
                btnIcon.classList.remove('text-zinc-600', 'dark:text-zinc-300');
                btnIcon.classList.add('text-white');
            }

            setTimeout(() => {
                isVerified = true;
                tokenInput.value = 'gamified_token_verified_' + Date.now();

                // Update trigger button UI
                triggerBtn.classList.add('border-indigo-500/50', 'bg-indigo-50/50', 'dark:bg-indigo-500/10');
                statusDot.classList.remove('bg-zinc-300', 'dark:bg-zinc-600');
                statusDot.classList.add('bg-indigo-500', 'shadow-[0_0_10px_rgba(79,82,217,0.5)]');
                captchaText.textContent = "Verified Human";
                captchaText.classList.remove('text-zinc-700', 'dark:text-zinc-300', 'group-hover:text-zinc-900', 'dark:group-hover:text-white');
                captchaText.classList.add('text-indigo-600', 'dark:text-indigo-400');

                const iconElement = triggerBtn.querySelector('svg') || triggerBtn.querySelector('i');
                if (iconElement) {
                    iconElement.classList.remove('text-zinc-500', 'dark:text-zinc-400', 'group-hover:text-indigo-600', 'dark:group-hover:text-indigo-400');
                    iconElement.classList.add('text-indigo-600', 'dark:text-indigo-400');
                }

                closeModal();
            }, 500);
        } else {
            // Fail
            btn.classList.add('bg-red-500/20', 'border-red-500', 'animate-shake');
            setTimeout(() => {
                btn.classList.remove('bg-red-500/20', 'border-red-500', 'animate-shake');
            }, 500);
        }
    }
});
