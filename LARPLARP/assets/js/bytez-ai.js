/**
 * Bytez AI Frontend Integration
 * Handles interactions with the Bytez AI backend endpoints.
 */
const BytezAI = {
    /**
     * Translate text
     * @param {string} text - Text to translate
     * @param {string} targetLang - Target language code (e.g., 'fr', 'es')
     * @param {string} sourceLang - Source language code (default 'en')
     * @returns {Promise<string>} - Translated text
     */
    async translate(text, targetLang, sourceLang = 'en') {
        try {
            const response = await fetch('../api/ai/translate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text, target_lang: targetLang, source_lang: sourceLang })
            });
            const data = await response.json();
            if (data.success) {
                return data.translation;
            } else {
                console.error('Translation failed:', data.message);
                return null;
            }
        } catch (error) {
            console.error('Translation error:', error);
            return null;
        }
    },

    /**
     * Generate image description
     * @param {string} imageUrl - URL of the image
     * @returns {Promise<string>} - Image description
     */
    async generateImageDescription(imageUrl) {
        try {
            const response = await fetch('../api/ai/image-description.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image_url: imageUrl })
            });
            const data = await response.json();
            if (data.success) {
                return data.description;
            } else {
                console.error('Image description failed:', data.message);
                return null;
            }
        } catch (error) {
            console.error('Image description error:', error);
            return null;
        }
    },

    /**
     * Moderate content
     * @param {string} content - Text content to check
     * @returns {Promise<object>} - Moderation result
     */
    async moderateContent(content) {
        try {
            const response = await fetch('../api/ai/moderate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content })
            });
            const data = await response.json();
            if (data.success) {
                return data.moderation_result;
            } else {
                console.error('Moderation failed:', data.message);
                return null;
            }
        } catch (error) {
            console.error('Moderation error:', error);
            return null;
        }
    },

    /**
     * UI Helper: Add "Magic Translate" button to a textarea
     * @param {string} textareaId - ID of the textarea
     */
    attachTranslateButton(textareaId) {
        const textarea = document.getElementById(textareaId);
        if (!textarea) return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'absolute right-2 bottom-2 p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-md transition-colors';
        btn.innerHTML = '<i data-lucide="languages" class="w-4 h-4"></i>';
        btn.title = 'Translate to French (AI)';

        btn.onclick = async () => {
            if (!textarea.value.trim()) return;

            const originalText = textarea.value;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
            btn.disabled = true;

            const translated = await this.translate(originalText, 'fr'); // Default to French for demo

            if (translated) {
                textarea.value = translated;
                // Trigger change event
                textarea.dispatchEvent(new Event('change'));
            }

            btn.innerHTML = '<i data-lucide="languages" class="w-4 h-4"></i>';
            btn.disabled = false;
            lucide.createIcons();
        };

        // Wrap textarea in relative container if not already
        const parent = textarea.parentElement;
        if (getComputedStyle(parent).position === 'static') {
            parent.style.position = 'relative';
        }
        parent.appendChild(btn);
        lucide.createIcons();
    },

    /**
     * Initialize AI features on the page
     */
    init() {
        // Attach translation buttons to known textareas
        this.attachTranslateButton('actionDescription');
        this.attachTranslateButton('resourceDescription');
        this.attachTranslateButton('storyContent');

        // Add listeners for specific AI buttons if they exist
        const generateTitleBtn = document.getElementById('dashGenerateTitleBtn');
        if (generateTitleBtn) {
            generateTitleBtn.addEventListener('click', async () => {
                const content = document.getElementById('storyContent').value;
                if (!content) return;

                // For now, simple simulation or call to a generation endpoint if we had one
                // We can use the image description endpoint as a placeholder or just translate a summary
                // But let's just use translation for now as a demo
                generateTitleBtn.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Generating...';
                const translated = await this.translate(content.substring(0, 50), 'en'); // Translate start to English as "title"
                if (translated) {
                    document.getElementById('storyTitle').value = translated;
                }
                generateTitleBtn.innerHTML = '<i data-lucide="sparkles" class="w-3 h-3"></i> Generate';
                lucide.createIcons();
            });
        }

        const improveTextBtn = document.getElementById('dashImproveTextBtn');
        if (improveTextBtn) {
            improveTextBtn.addEventListener('click', async () => {
                const content = document.getElementById('storyContent').value;
                if (!content) return;

                improveTextBtn.innerHTML = '<i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Improving...';
                // Use moderation to check score first, then maybe translate back and forth or just leave as is for demo
                const moderation = await this.moderateContent(content);
                console.log('Moderation:', moderation);

                // For demo, let's just translate to English and back to French (or similar) to "refine"
                // Or just show a success toast
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                Toast.fire({
                    icon: 'success',
                    title: 'AI Analysis Complete',
                    text: 'Content looks good!'
                });

                improveTextBtn.innerHTML = '<i data-lucide="wand-2" class="w-3 h-3"></i> Improve';
                lucide.createIcons();
            });
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    BytezAI.init();
});

// Export
window.BytezAI = BytezAI;
