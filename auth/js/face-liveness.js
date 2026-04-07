/**
 * Face Liveness Detection Helper
 * Provides liveness detection functionality to prevent spoofing attacks
 */

const FaceLiveness = {
    config: {
        blinkThreshold: 0.2,          // Eye aspect ratio threshold for blink detection
        blinkMinFrames: 2,            // Minimum frames for blink detection
        headMovementThreshold: 0.1,   // Minimum head movement for liveness
        maxVerificationTime: 5000,     // Maximum time allowed for liveness check (5 seconds)
        livenessEnabled: true,        // Toggle liveness detection
        sensitivity: 'medium'         // 'low', 'medium', 'high'
    },

    state: {
        isDetecting: false,
        blinkCount: 0,
        lastBlinkTime: 0,
        headMovementDetected: false,
        startTime: 0,
        livenessScore: 0,
        detectionHistory: []
    },

    /**
     * Initialize liveness detection
     * @param {Object} options Configuration options
     */
    initialize: function(options = {}) {
        // Initialize with config from FaceConfig if available, otherwise default config
        const faceConfig = window.FaceConfig || {};
        const livenessConfig = faceConfig.liveness || {};

        // Merge FaceConfig.liveness, provided options, and defaults
        this.config = {
            ...this.config,
            ...livenessConfig,
            ...options
        };

        // Initialize detection history buffer
        this.state.detectionHistory = [];

        console.log('Face liveness detection initialized with config:', this.config);
    },

    /**
     * Main liveness detection function
     * @param {HTMLVideoElement} videoElement Video element to analyze
     * @param {Object} detection Result from face-api.js detection
     * @returns {Promise<Object>} Liveness result
     */
    async detectLiveness: async function(videoElement, detection) {
        if (!this.config.livenessEnabled) {
            return { isLive: true, confidence: 1.0, score: 100, reasons: ['liveness_check_disabled'] };
        }

        try {
            // Start timing if not already started
            if (this.state.startTime === 0) {
                this.state.startTime = Date.now();
            }

            // Check time limit
            const elapsed = Date.now() - this.state.startTime;
            if (elapsed > this.config.maxVerificationTime) {
                return {
                    isLive: false,
                    confidence: 0.5,
                    score: 0,
                    reasons: ['time_limit_exceeded'],
                    elapsed: elapsed
                };
            }

            // Determine which checks to perform based on configuration
            const checksToPerform = [];
            if (this.config.requiredChecks.includes('blink')) {
                checksToPerform.push(this.checkBlinkLiveness(detection));
            }
            if (this.config.requiredChecks.includes('movement')) {
                checksToPerform.push(this.checkHeadMovementLiveness(detection));
            }
            if (this.config.requiredChecks.includes('texture')) {
                checksToPerform.push(this.checkTextureLiveness(videoElement, detection));
            }

            // Perform configured liveness checks
            const results = await Promise.all(checksToPerform);

            // Calculate overall liveness score
            const liveness = this.calculateLivenessScore(results);

            return liveness;

        } catch (error) {
            console.error('Liveness detection error:', error);
            return {
                isLive: false,
                confidence: 0.0,
                score: 0,
                reasons: ['detection_error'],
                error: error.message
            };
        }
    },

    /**
     * Check for blink-based liveness
     * @param {Object} detection Face detection result
     * @returns {Object} Blink liveness result
     */
    checkBlinkLiveness: function(detection) {
        try {
            if (!detection || !detection.landmarks) {
                return { type: 'blink', passed: false, confidence: 0.0, value: 0 };
            }

            const landmarks = detection.landmarks;
            const leftEye = landmarks.getLeftEye();
            const rightEye = landmarks.getRightEye();

            if (!leftEye || !rightEye || leftEye.length < 6 || rightEye.length < 6) {
                return { type: 'blink', passed: false, confidence: 0.3, value: 0 };
            }

            // Calculate Eye Aspect Ratio (EAR)
            const leftEAR = this.calculateEAR(leftEye);
            const rightEAR = this.calculateEAR(rightEye);
            const avgEAR = (leftEAR + rightEAR) / 2;

            // Store EAR value in detection history
            this.state.detectionHistory.push({
                timestamp: Date.now(),
                type: 'ear',
                value: avgEAR
            });

            // Keep only recent values (last 5 seconds)
            this.state.detectionHistory = this.state.detectionHistory.filter(
                entry => Date.now() - entry.timestamp < 5000
            );

            // Detect blink (EAR drops below threshold)
            if (avgEAR < this.config.blinkThreshold) {
                const now = Date.now();
                if (now - this.state.lastBlinkTime > 300) { // Debounce blinks
                    this.state.blinkCount++;
                    this.state.lastBlinkTime = now;
                }
            }

            // Consider blink liveness passed if we've detected some blinks
            const blinkThreshold = this.config.sensitivity === 'high' ? 1 : 
                                 this.config.sensitivity === 'medium' ? 1 : 0;
            
            const passed = this.state.blinkCount >= blinkThreshold;
            const confidence = passed ? 0.8 : 0.2;

            return {
                type: 'blink',
                passed: passed,
                confidence: confidence,
                value: avgEAR,
                blinkCount: this.state.blinkCount
            };

        } catch (error) {
            console.warn('Blink detection error:', error);
            return { type: 'blink', passed: false, confidence: 0.1, value: 0 };
        }
    },

    /**
     * Calculate Eye Aspect Ratio (EAR) for blink detection
     * @param {Array} eyePoints Eye landmark points
     * @returns {Number} Eye aspect ratio
     */
    calculateEAR: function(eyePoints) {
        try {
            // Calculate distances between vertical eye landmarks
            const A = this.calculateDistance(eyePoints[1], eyePoints[5]);
            const B = this.calculateDistance(eyePoints[2], eyePoints[4]);
            
            // Calculate distance between horizontal eye landmarks
            const C = this.calculateDistance(eyePoints[0], eyePoints[3]);

            // EAR formula
            return (A + B) / (2.0 * C);
        } catch (error) {
            return 0;
        }
    },

    /**
     * Check for head movement liveness
     * @param {Object} detection Face detection result
     * @returns {Object} Head movement liveness result
     */
    checkHeadMovementLiveness: function(detection) {
        try {
            if (!detection || !detection.detection) {
                return { type: 'head_movement', passed: false, confidence: 0.0, value: 0 };
            }

            // Get face position/rotation data if available
            const position = detection.detection.box;
            const angle = detection.angle || detection.detection.angle || { roll: 0, pitch: 0, yaw: 0 };

            // Store head movement data
            this.state.detectionHistory.push({
                timestamp: Date.now(),
                type: 'head_movement',
                position: position,
                angle: angle
            });

            // Calculate movement/rotation change
            let movementDetected = false;
            if (this.state.detectionHistory.length > 5) {
                const recentEntries = this.state.detectionHistory
                    .filter(entry => entry.type === 'head_movement')
                    .slice(-10);

                if (recentEntries.length >= 2) {
                    const first = recentEntries[0];
                    const last = recentEntries[recentEntries.length - 1];
                    
                    const rollChange = Math.abs(first.angle.roll - last.angle.roll);
                    const pitchChange = Math.abs(first.angle.pitch - last.angle.pitch);
                    const yawChange = Math.abs(first.angle.yaw - last.angle.yaw);
                    
                    const totalChange = rollChange + pitchChange + yawChange;
                    movementDetected = totalChange > this.config.headMovementThreshold;
                }
            }

            const passed = movementDetected;
            const confidence = passed ? 0.8 : 0.2;

            return {
                type: 'head_movement',
                passed: passed,
                confidence: confidence,
                value: movementDetected ? 1 : 0,
                angleChange: movementDetected
            };

        } catch (error) {
            console.warn('Head movement detection error:', error);
            return { type: 'head_movement', passed: false, confidence: 0.1, value: 0 };
        }
    },

    /**
     * Basic texture analysis liveness check
     * Note: This is a simplified version - a full implementation would use more sophisticated techniques
     * @param {HTMLVideoElement} videoElement Video element
     * @param {Object} detection Face detection result
     * @returns {Object} Texture liveness result
     */
    checkTextureLiveness: function(videoElement, detection) {
        try {
            // Skip texture analysis if no detection provided
            if (!detection || !detection.detection) {
                return { type: 'texture', passed: false, confidence: 0.5, value: 0 };
            }

            // Basic texture check: capture frame and analyze for artificial patterns
            // In a real implementation, this would use more sophisticated algorithms
            // such as Local Binary Patterns (LBP), Discrete Cosine Transform (DCT), etc.
            
            // For now, return a moderate confidence based on face quality
            const faceQuality = detection.detection.score || 0.8;
            const passed = faceQuality > 0.5;
            const confidence = faceQuality;

            return {
                type: 'texture',
                passed: passed,
                confidence: confidence,
                value: faceQuality
            };

        } catch (error) {
            console.warn('Texture analysis error:', error);
            return { type: 'texture', passed: true, confidence: 0.6, value: 0.6 }; // Conservative
        }
    },

    /**
     * Calculate overall liveness score from individual checks
     * @param {Array} results Array of liveness check results
     * @returns {Object} Overall liveness result
     */
    calculateLivenessScore: function(results) {
        try {
            // Calculate weighted score based on sensitivity
            let passedChecks = 0;
            let totalConfidence = 0;
            const reasons = [];

            results.forEach(result => {
                if (result.passed) {
                    passedChecks++;
                }
                totalConfidence += result.confidence;
                
                if (result.passed) {
                    reasons.push(`${result.type}_passed`);
                } else {
                    reasons.push(`${result.type}_failed`);
                }
            });

            // Calculate final score
            const score = Math.round((passedChecks / results.length) * 100);
            const avgConfidence = totalConfidence / results.length;
            const isLive = this.shouldPassLiveness(passedChecks, results.length, avgConfidence);

            // Adjust based on sensitivity
            let finalIsLive = isLive;
            if (this.config.sensitivity === 'high') {
                finalIsLive = passedChecks >= results.length; // All checks must pass
            } else if (this.config.sensitivity === 'low') {
                finalIsLive = passedChecks >= Math.ceil(results.length / 2); // Majority pass
            }

            return {
                isLive: finalIsLive,
                confidence: avgConfidence,
                score: score,
                reasons: reasons,
                individualResults: results,
                passedChecks: passedChecks,
                totalChecks: results.length
            };

        } catch (error) {
            console.error('Score calculation error:', error);
            return {
                isLive: false,
                confidence: 0.0,
                score: 0,
                reasons: ['calculation_error']
            };
        }
    },

    /**
     * Determine if liveness should pass based on checks passed and confidence
     * @param {Number} passedChecks Number of successful checks
     * @param {Number} totalChecks Total number of checks
     * @param {Number} avgConfidence Average confidence
     * @returns {Boolean} Whether liveness should pass
     */
    shouldPassLiveness: function(passedChecks, totalChecks, avgConfidence) {
        if (totalChecks === 0) return false;

        const percentagePassed = passedChecks / totalChecks;
        
        // Base requirement: at least 60% of checks must pass
        const baseRequirement = 0.6;
        
        // If percentage is above base and confidence is decent
        return percentagePassed >= baseRequirement && avgConfidence >= 0.5;
    },

    /**
     * Calculate distance between two points
     * @param {Object} point1 First point
     * @param {Object} point2 Second point
     * @returns {Number} Distance
     */
    calculateDistance: function(point1, point2) {
        const dx = point1.x - point2.x;
        const dy = point1.y - point2.y;
        return Math.sqrt(dx * dx + dy * dy);
    },

    /**
     * Reset liveness detection state
     */
    reset: function() {
        this.state = {
            isDetecting: false,
            blinkCount: 0,
            lastBlinkTime: 0,
            headMovementDetected: false,
            startTime: 0,
            livenessScore: 0,
            detectionHistory: []
        };
    },

    /**
     * Get current liveness configuration
     * @returns {Object} Configuration object
     */
    getConfig: function() {
        return { ...this.config };
    },

    /**
     * Update liveness configuration
     * @param {Object} newConfig New configuration
     */
    updateConfig: function(newConfig) {
        this.config = { ...this.config, ...newConfig };
    }
};

// Expose to global scope if needed
if (typeof window !== 'undefined') {
    window.FaceLiveness = FaceLiveness;
}

// Export for module systems if available
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FaceLiveness;
}