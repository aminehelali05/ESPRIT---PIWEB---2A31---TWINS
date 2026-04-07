/**
 * AI Features - Centralized API Client for Voices of Peace
 * Powered by Hugging Face & Backend AI Services
 */

window.AI_Features = {
    // --- Configuration ---
    endpoints: {
        translate: '../api/ai/translate.php',
        sentiment: '../api/ai/sentiment.php',
        summarize: '../api/ai/summarize.php',
        moderate: '../api/ai/moderate.php',
        improve: '../api/ai/improve-text.php',
        analyze_image: '../api/ai/caption_image.php'
    },

    cacheTTL: 10 * 60 * 1000, // 10 minutes

    /**
     * Generic API Caller with Caching
     * 
     * Makes API requests with automatic caching and basic error handling.
     * 
     * @param {string} endpointUrl - URL to call
     * @param {object} payload - JSON payload to send in POST body
     * @param {string} cachePrefix - Prefix for sessionStorage cache key (optional, pass null to disable caching)
     * 
     * @returns {Promise<object>} Response object with structure:
     *   - On success: { success: true, ...actual data fields... }
     *   - On API error: { success: false, message: string, ...other fields... }
     *   - On network error: { success: false, message: string, error: true }
     * 
     * Error Handling Contract:
     * - This function returns error information in the response object, it does NOT throw exceptions
     * - Callers should check `result.success` to determine if the call succeeded
     * - Network errors (fetch failures) return { success: false, message: error.message, error: true }
     * - Backend errors return whatever JSON the backend sent (typically { success: false, message: ... })
     * - This function does NOT display error messages to users; callers are responsible for user-facing error handling
     * 
     * Caching:
     * - If cachePrefix is provided and result is successful ({ success: true }), response is cached for 10 minutes
     * - Cache key format: `${cachePrefix}_${hash(payload)}`
     * - Cached only on success to avoid caching error responses
     */
    async callAPI(endpointUrl, payload, cachePrefix = null) {
        // 1. Check Cache
        if (cachePrefix) {
            const cacheKey = cachePrefix + '_' + btoa(JSON.stringify(payload)).slice(0, 32);
            const cached = sessionStorage.getItem(cacheKey);
            if (cached) {
                try {
                    const parsed = JSON.parse(cached);
                    if (Date.now() - parsed.timestamp < this.cacheTTL) {
                        console.log(`[AI Cache] Hit for ${cachePrefix}`);
                        return parsed.data;
                    }
                } catch (e) { console.warn('Cache parse error', e); }
            }
        }

        // 2. Make Network Request
        try {
            const response = await fetch(endpointUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();

            // 3. Cache Success Response
            if (data.success && cachePrefix) {
                sessionStorage.setItem(cachePrefix + '_' + btoa(JSON.stringify(payload)).slice(0, 32), JSON.stringify({
                    timestamp: Date.now(),
                    data: data
                }));
            }

            return data;

        } catch (error) {
            console.error(`[AI Error] Call to ${endpointUrl} failed:`, error);
            return { success: false, message: error.message, error: true };
        }
    },

    /**
     * Translate Text
     * @param {string} text 
     * @param {string} targetLang 
     * @param {string} sourceLang 
     */
    async translateText(text, targetLang = 'fr', sourceLang = 'en') {
        if (!text || !text.trim()) return null;
        const result = await this.callAPI(this.endpoints.translate, {
            text, target_lang: targetLang, source_lang: sourceLang
        }, 'ai_trans');

        return result.success ? result.translation : null;
    },

    /**
     * Analyze Sentiment
     * @param {string} text 
     */
    async analyzeSentiment(text) {
        if (!text || !text.trim()) return null;
        // Legacy compatibility: backend returns 'sentiment' field
        return await this.callAPI(this.endpoints.sentiment, { text }, 'ai_sent');
    },

    /**
     * Summarize Content
     * @param {string} text 
     * @param {number} maxLength 
     */
    async summarizeContent(text, maxLength = 150) {
        if (!text || JSON.stringify(text).length < 50) return null; // Too short
        const result = await this.callAPI(this.endpoints.summarize, {
            text, max_length: maxLength
        }, 'ai_summ');

        return result.success ? result.summary : null;
    },

    /**
     * Moderate Content (Hate Speech / Safety)
     * @param {string} text 
     */
    async checkContent(text) {
        if (!text) return { success: true, is_safe: true }; // Empty is safe
        const result = await this.callAPI(this.endpoints.moderate, { content: text }); // Endpoint expects 'content'

        if (result.success && result.moderation_result) {
            return result.moderation_result;
        }
        return { is_safe: true, verdict: 'unknown' }; // Fail open or closed? Fail open for now to not block user
    },

    // --- New Features ---

    /**
     * Improve Text (Bio/Story/etc)
     * @param {string} text 
     * @param {string} type 'bio' | 'story' | 'general'
     */
    async improveText(text, type = 'general') {
        if (!text) return null;
        // Improve text endpoint expects { text: "..." }
        // It returns { improvedText: "...", suggestedTags: [...] }
        try {
            const result = await this.callAPI(this.endpoints.improve, { text: text }, 'ai_improve');
            if (result.success) {
                return result.improvedText;
            }
        } catch (e) {
            console.error("Improve text error", e);
        }
        return null;
    },

    /**
     * Analyze Image (Captioning)
     * @param {string} imageUrl 
     */
    async analyzeImage(imageUrl) {
        if (!imageUrl) return null;
        try {
            const result = await this.callAPI(this.endpoints.analyze_image, { image_url: imageUrl }, 'ai_img');
            if (result.success) {
                return result.caption;
            }
        } catch (e) {
            console.error("Analyze image error", e);
        }
        return null;
    },

    // --- Legacy / Helper Wrappers ---

    // Legacy method for tag suggestion (forwarded to improve-text if exists, or use local fallback)
    async suggestTags(text) {
        // Simple local fallback for speed if main API not used
        const keywords = {
            'peace': 'Peace Building', 'community': 'Community', 'help': 'Social Help',
            'education': 'Educational', 'nature': 'Environment', 'art': 'Art & Culture'
        };
        const suggestions = new Set();
        const lower = text.toLowerCase();
        for (const [k, v] of Object.entries(keywords)) {
            if (lower.includes(k)) suggestions.add(v);
        }
        return Array.from(suggestions);
    },

    /**
     * Generate Title & Description
     * @param {string} content
     */
    async generateTitleDescription(content) {
        if (!content) return null;
        try {
            // Use the new endpoint
            const result = await this.callAPI('../api/ai/generate_metadata.php', { content: content }, 'ai_meta');
            if (result.success) {
                return result;
            }
        } catch (e) {
            console.error("Generate metadata error", e);
        }
        // Fallback
        return {
            titleSuggestion: 'New Story',
            descriptionSuggestion: content.substring(0, 100) + '...',
            tagsSuggestion: await this.suggestTags(content)
        };
    }
};

console.log("AI Features Service Loaded (v2)");
