/**
 * Face Recognition Debug Tools
 * Toggle with Ctrl+Shift+D
 */

const FaceDebug = {
    enabled: false,
    overlay: null,
    stats: {
        detections: 0,
        successes: 0,
        avgTime: 0,
        lastTime: 0,
        model: 'none'
    },

    init() {
        // Create debug overlay
        this.createOverlay();

        // Listen for toggle key
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                this.toggle();
            }
        });

        // Hook into FaceUtils
        this.hookFaceUtils();

        console.log('Face Debug Tools initialized. Press Ctrl+Shift+D to toggle.');
    },

    createOverlay() {
        const div = document.createElement('div');
        div.id = 'face-debug-overlay';
        div.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            width: 300px;
            background: rgba(0, 0, 0, 0.85);
            color: #0f0;
            font-family: monospace;
            font-size: 12px;
            padding: 10px;
            border-radius: 5px;
            z-index: 9999;
            display: none;
            pointer-events: none;
            border: 1px solid #0f0;
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.2);
        `;
        div.innerHTML = `
            <h3 style="margin: 0 0 5px; border-bottom: 1px solid #0f0;">Face Debug</h3>
            <div id="fd-stats">Waiting for detection...</div>
            <div id="fd-log" style="margin-top: 5px; height: 100px; overflow-y: auto; border-top: 1px solid #333;"></div>
        `;
        document.body.appendChild(div);
        this.overlay = div;
    },

    toggle() {
        this.enabled = !this.enabled;
        this.overlay.style.display = this.enabled ? 'block' : 'none';
        FaceConfig.debug.enabled = this.enabled;
        this.log(`Debug mode ${this.enabled ? 'enabled' : 'disabled'}`);
    },

    updateStats(data) {
        if (!this.enabled) return;

        const statsDiv = document.getElementById('fd-stats');
        if (statsDiv) {
            statsDiv.innerHTML = `
                <div>FPS: ${Math.round(1000 / (performance.now() - this.stats.lastTime))}</div>
                <div>Model: ${data.model || 'unknown'}</div>
                <div>Score: ${data.score ? Math.round(data.score * 100) + '%' : 'N/A'}</div>
                <div>Time: ${Math.round(data.time)}ms</div>
                <div>Size: ${data.box ? Math.round(data.box.width) + 'x' + Math.round(data.box.height) : 'N/A'}</div>
                <div>State: ${FaceUtils.detectionState}</div>
            `;
        }
        this.stats.lastTime = performance.now();
    },

    log(msg) {
        if (!this.enabled) return;

        const logDiv = document.getElementById('fd-log');
        if (logDiv) {
            const line = document.createElement('div');
            line.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
            logDiv.insertBefore(line, logDiv.firstChild);
            if (logDiv.children.length > 20) logDiv.removeChild(logDiv.lastChild);
        }
    },

    hookFaceUtils() {
        // Wrap detectFaceWithFallback
        const originalDetect = FaceUtils.detectFaceWithFallback.bind(FaceUtils);
        FaceUtils.detectFaceWithFallback = async (video) => {
            const start = performance.now();
            const result = await originalDetect(video);
            const time = performance.now() - start;

            if (result) {
                this.updateStats({
                    model: result.meta?.method,
                    score: result.detection.score,
                    time: time,
                    box: result.detection.box
                });
            } else {
                this.updateStats({ model: 'none', time: time });
            }

            return result;
        };
    }
};

// Initialize on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => FaceDebug.init());
} else {
    FaceDebug.init();
}
