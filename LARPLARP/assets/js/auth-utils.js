/**
 * Auth Utils for Voices Of Peace
 * Handles session checking, login, logout, and shared UI effects.
 */

const API_BASE = '../api/users';

const Auth = {
    /**
     * Check if user is authenticated
     * @returns {Promise<Object>} User object or null
     */
    async checkSession() {
        try {
            const response = await fetch(`${API_BASE}/check_auth.php`);
            const data = await response.json();
            if (data.authenticated) {
                return data.user;
            } else {
                return null;
            }
        } catch (error) {
            console.error('Session check failed:', error);
            return null;
        }
    },

    /**
     * Login user
     * @param {string} email 
     * @param {string} password 
     * @returns {Promise<Object>} Result
     */
    async login(email, password, token = null) {
        try {
            const body = { email, password };
            if (token) {
                // Determine if it's gamified or recaptcha based on token format or just send both keys
                // For simplicity, let's send both potential keys if the token matches
                if (token.startsWith('gamified_')) {
                    body.gamified_token = token;
                } else {
                    body.recaptcha_response = token;
                }
            }

            const response = await fetch(`${API_BASE}/login.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            const result = await response.json();

            // If login backend doesn't set session automatically (it should), we force it here if needed
            // But standard PHP login should set PHPSESSID.

            if (result.success && result.user) {
                // Double check session is set
                await fetch(`${API_BASE}/set_session.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: result.user.id })
                });
            }

            return result;
        } catch (error) {
            console.error('Login error:', error);
            return { success: false, message: 'Network error occurred.' };
        }
    },

    /**
     * Register user
     * @param {Object} data 
     */
    async register(data) {
        try {
            const response = await fetch(`${API_BASE}/register.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            return await response.json();
        } catch (error) {
            console.error('Registration error:', error);
            return { success: false, message: 'Network error occurred.' };
        }
    },

    /**
     * Logout user
     */
    async logout(redirectTo = '../auth/login.html') {
        try {
            await fetch(`${API_BASE}/logout.php`, { method: 'POST', credentials: 'include' });
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = redirectTo;
        } catch (error) {
            console.error('Logout error:', error);
            // Even if logout fails, clear storage and redirect
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = redirectTo;
        }
    },

    /**
     * Enforce login on proteced pages
     */
    async requireAuth() {
        const user = await this.checkSession();
        if (!user) {
            // Save current URL to redirect back after login (optional, simple implementation)
            window.location.href = '../auth/login.html';
        }
        return user;
    }
};

/* Particle System for Background */
class ParticleSystem {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        this.ctx = this.canvas.getContext('2d');
        this.particles = [];
        this.init();
        this.animate();

        window.addEventListener('resize', () => this.resize());
    }

    init() {
        this.resize();
        this.createParticles();
    }

    resize() {
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;
    }

    createParticles() {
        // Create 60 particles
        for (let i = 0; i < 60; i++) {
            this.particles.push({
                x: Math.random() * this.canvas.width,
                y: Math.random() * this.canvas.height,
                vx: (Math.random() - 0.5) * 0.5,
                vy: (Math.random() - 0.5) * 0.5,
                radius: Math.random() * 2 + 1,
                alpha: Math.random() * 0.5 + 0.1
            });
        }
    }

    animate() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        this.particles.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;

            // Bounce
            if (p.x < 0 || p.x > this.canvas.width) p.vx *= -1;
            if (p.y < 0 || p.y > this.canvas.height) p.vy *= -1;

            this.ctx.beginPath();
            this.ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            this.ctx.fillStyle = `rgba(174, 225, 249, ${p.alpha})`; // Primary color
            this.ctx.fill();
        });

        // Connect particles
        this.particles.forEach((p1, i) => {
            this.particles.slice(i + 1).forEach(p2 => {
                const dx = p1.x - p2.x;
                const dy = p1.y - p2.y;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < 100) {
                    this.ctx.beginPath();
                    this.ctx.moveTo(p1.x, p1.y);
                    this.ctx.lineTo(p2.x, p2.y);
                    this.ctx.strokeStyle = `rgba(174, 225, 249, ${0.1 * (1 - dist / 100)})`;
                    this.ctx.stroke();
                }
            });
        });

        requestAnimationFrame(() => this.animate());
    }
}

// Global initialization
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('particleCanvas')) {
        new ParticleSystem('particleCanvas');
    }
});
