/**
 * Face Login Module
 * Handles face recognition authentication
 */

const FaceLogin = {
    videoElement: null,
    canvasElement: null,
    stream: null,
    isProcessing: false,

    // UI update state for debounced updates
    lastUIUpdate: 0,
    currentQualityScore: 0,
    currentDetectionState: null,

    /**
     * Initialize face login
     */
    async initialize() {
        try {
            // Update status indicators
            this.updateStatusIndicator('camera', 'pending');
            this.updateStatusIndicator('models', 'pending');

            // Load face-api.js models
            FaceUtils.showLoading('Loading face recognition models...');
            const modelsLoaded = await FaceUtils.loadModels();

            if (!modelsLoaded) {
                throw new Error('Failed to load face recognition models');
            }

            // Update models status indicator
            this.updateStatusIndicator('models', 'success');

            // Get video and canvas elements
            this.videoElement = document.getElementById('face-login-video');
            this.canvasElement = document.getElementById('face-login-canvas');

            if (!this.videoElement || !this.canvasElement) {
                throw new Error('Video or canvas element not found');
            }

            // Match canvas size to video
            this.canvasElement.width = 640;
            this.canvasElement.height = 480;

            // Initialize webcam
            FaceUtils.showLoading('Accessing camera...');
            this.stream = await FaceUtils.initializeWebcam(this.videoElement);

            // Update camera status indicator
            this.updateStatusIndicator('camera', 'success');

            // Reset detection buffer
            FaceUtils.resetDetectionBuffer();

            FaceUtils.hideLoading();
            this.startDetection();

        } catch (error) {
            console.error('Face login initialization error:', error);
            this.updateStatusIndicator('camera', 'error');
            this.updateStatusIndicator('models', 'error');
            FaceUtils.hideLoading();
            FaceUtils.showError(error.message);
        }
    },

    /**
     * Start continuous face detection
     */
    startDetection() {
        let detectionSuccessCount = 0;
        let detectionFailureCount = 0;
        let lastDetectionTime = 0;

        const detectInterval = setInterval(async () => {
            if (this.isProcessing) {
                return;
            }

            // Implement adaptive detection interval to manage CPU usage
            const currentTime = Date.now();
            if (lastDetectionTime && (currentTime - lastDetectionTime) < 100) {
                return; // Skip this cycle if previous was too fast
            }

            const detection = await FaceUtils.detectFaceWithFallback(this.videoElement);

            if (detection && detection.detection) {
                detectionSuccessCount++;

                // Update detection status indicator
                this.updateStatusIndicator('detection', 'success');

                // Draw face box with debug info if debug mode is enabled
                if (FaceUtils.debugMode) {
                    FaceUtils.drawDebugInfo(this.canvasElement, detection, this.videoElement);
                } else {
                    FaceUtils.drawFaceBox(this.canvasElement, detection);
                }

                // Validate face quality and calculate smoothed quality
                const validation = FaceUtils.validateFaceQuality(detection);
                const smoothedScore = FaceUtils.calculateSmoothedQuality(validation);
                const detectionState = FaceUtils.updateDetectionState(smoothedScore, FaceUtils.config);

                // Store results for UI updating
                this.currentQualityScore = smoothedScore;
                this.currentDetectionState = detectionState;

                // Only update UI elements every 300ms to prevent rapid visual changes
                if (Date.now() - this.lastUIUpdate >= FaceUtils.config.ui.uiUpdateInterval) {
                    this.updateQualityBarSmooth(smoothedScore, detectionState);
                    this.updateProgressiveFeedback(smoothedScore, detectionState);
                    this.updateStatus(this.getProgressiveStatusMessage(validation, detectionState));
                    this.lastUIUpdate = Date.now();
                }

                // Enable verify button if face quality is good
                const verifyBtn = document.getElementById('verify-face-btn');
                if (verifyBtn) {
                    verifyBtn.disabled = !validation.valid;
                }
            } else {
                detectionFailureCount++;

                // Update detection status indicator
                this.updateStatusIndicator('detection', 'pending');

                // Clear canvas if no face detected
                const ctx = this.canvasElement.getContext('2d');
                ctx.clearRect(0, 0, this.canvasElement.width, this.canvasElement.height);

                // Provide more specific feedback based on detection attempts
                let statusMessage = 'No face detected';
                if (detection && detection.attempts) {
                    const failedAttempts = detection.attempts.filter(attempt => !attempt.success);
                    if (failedAttempts.length > 0) {
                        const successfulAttempt = detection.attempts.find(attempt => attempt.success);
                        if (!successfulAttempt) {
                            statusMessage = 'No face detected. Please ensure your face is visible and well-lit.';
                        }
                    }
                }
                this.updateStatus(statusMessage);

                const verifyBtn = document.getElementById('verify-face-btn');
                if (verifyBtn) {
                    verifyBtn.disabled = true;
                }

                // Update quality indicators when no face is detected
                const smoothedScore = FaceUtils.calculateSmoothedQuality(null);
                const detectionState = FaceUtils.updateDetectionState(smoothedScore, FaceUtils.config);

                // Store results for UI updating
                this.currentQualityScore = smoothedScore;
                this.currentDetectionState = detectionState;

                // Only update UI elements every 300ms to prevent rapid visual changes
                if (Date.now() - this.lastUIUpdate >= FaceUtils.config.ui.uiUpdateInterval) {
                    this.updateQualityBarSmooth(smoothedScore, detectionState);
                    this.updateProgressiveFeedback(smoothedScore, detectionState);
                    this.lastUIUpdate = Date.now();
                }

                // Show troubleshooting tips if detection is failing consistently
                if (detectionFailureCount > 10 && detectionSuccessCount < 2) {
                    this.showTroubleshootingTips();
                    detectionFailureCount = 0; // Reset to avoid constant tip display
                }
            }

            lastDetectionTime = Date.now();
        }, 100); // Check every 100ms

        // Store interval ID for cleanup
        this.detectionInterval = detectInterval;
    },

    /**
     * Verify face and login
     */
    async verifyFace() {
        if (this.isProcessing) {
            return;
        }

        // Add retry capability
        return this.verifyFaceWithRetry();
    },

    /**
     * Verify face with multiple retry attempts
     */
    async verifyFaceWithRetry() {
        const maxAttempts = 3;
        let currentAttempt = 1;

        // Update verification attempts indicator
        this.updateVerificationAttemptsIndicator(currentAttempt, maxAttempts);

        while (currentAttempt <= maxAttempts) {
            if (this.isProcessing) {
                return;
            }

            try {
                this.isProcessing = true;

                // Update loading message to show attempt number
                const loadingMessage = currentAttempt > 1
                    ? `Verifying face... (Attempt ${currentAttempt}/${maxAttempts})`
                    : 'Verifying face...';

                FaceUtils.showLoading(loadingMessage);

                // Update verification attempts indicator
                this.updateVerificationAttemptsIndicator(currentAttempt, maxAttempts);

                // Get email from form
                const emailInput = document.getElementById('email') || document.getElementById('login-email');
                if (!emailInput || !emailInput.value) {
                    throw new Error('Please enter your email address');
                }

                const email = emailInput.value.trim();

                // Detect face and get descriptor using fallback method
                const detection = await FaceUtils.detectFaceWithFallback(this.videoElement);

                if (!detection || !detection.detection) {
                    throw new Error('No face detected. Please ensure your entire face is visible in the frame.');
                }

                // Validate face quality
                const validation = FaceUtils.validateFaceQuality(detection);
                if (!validation.valid) {
                    throw new Error(validation.message);
                }

                // Additional descriptor quality check before sending to backend
                if (!detection.descriptor || detection.descriptor.length !== 128) {
                    throw new Error('Invalid face descriptor. Please try again with better positioning.');
                }

                // Send to verification API
                const response = await fetch('../api/face/verify.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        face_descriptor: detection.descriptor
                    })
                });

                // Check if response status is 429 (rate limited) first without reading body
                if (response.status === 429) {
                    // Read the response body
                    const responseText = await response.text();
                    try {
                        // Parse the response as JSON
                        const rateLimitedResult = JSON.parse(responseText);

                        FaceUtils.hideLoading();
                        FaceUtils.showError(rateLimitedResult.message || 'Too many requests. Please try again later.');

                        // If retry_after is provided, show when user can try again
                        if (rateLimitedResult.retry_after) {
                            const retryAfterDate = new Date(rateLimitedResult.retry_after * 1000);
                            const timeLeft = Math.ceil((rateLimitedResult.retry_after * 1000 - Date.now()) / 1000);
                            FaceUtils.showError(`Too many attempts. Please try again in ${timeLeft} seconds. Retry after: ${retryAfterDate.toLocaleTimeString()}`);
                        }
                    } catch (jsonError) {
                        // If response is not JSON, show generic error
                        FaceUtils.hideLoading();
                        FaceUtils.showError('Too many requests. Please try again later.');
                    }

                    this.isProcessing = false;
                    return; // Don't retry when rate limited
                }

                // Check if response is OK
                if (!response.ok) {
                    // Read the response body
                    const responseText = await response.text();
                    try {
                        // Try to parse as JSON
                        const errorResult = JSON.parse(responseText);
                        throw new Error(errorResult.message || 'Server error occurred');
                    } catch (jsonError) {
                        // If response is not JSON, show server status error
                        console.error('API Error (non-JSON response):', responseText);
                        throw new Error(`Server error: ${response.status} - ${response.statusText}`);
                    }
                }

                // For successful responses, parse JSON
                const result = await response.json();

                if (result.success) {
                    FaceUtils.hideLoading();
                    FaceUtils.showSuccess('Face verified! Logging in...');

                    // Display distance/threshold metrics in debug mode
                    if (FaceUtils.debugMode && result.distance !== undefined && result.threshold !== undefined) {
                        console.log(`Verification Metrics - Distance: ${result.distance.toFixed(6)}, Threshold: ${result.threshold.toFixed(6)}, Match: ${result.distance < result.threshold ? 'PASS' : 'FAIL'}`);
                    }

                    // Stop webcam
                    this.cleanup();

                    // Redirect to dashboard
                    setTimeout(() => {
                        window.location.href = '../dashboard/index.html';
                    }, 1500);
                    return; // Success, exit retry loop
                } else {
                    // Handle specific failure reasons from backend
                    let errorMessage = result.message || 'Face verification failed';

                    if (result.reason === 'rate_limited') {
                        // If rate limited, don't retry and inform user immediately
                        FaceUtils.hideLoading();
                        FaceUtils.showError(errorMessage);

                        // If retry_after is provided, show when user can try again
                        if (result.retry_after) {
                            const retryAfterDate = new Date(result.retry_after * 1000);
                            const timeLeft = Math.ceil((result.retry_after * 1000 - Date.now()) / 1000);
                            FaceUtils.showError(`Too many attempts. Please try again in ${timeLeft} seconds. Retry after: ${retryAfterDate.toLocaleTimeString()}`);
                        }

                        this.isProcessing = false;
                        return; // Don't retry when rate limited
                    }

                    if (result.reason) {
                        // Backend provided specific reason
                        switch (result.reason) {
                            case 'user_not_found':
                                errorMessage = 'User not found. Please check your email address.';
                                break;
                            case 'no_face_enrolled':
                                errorMessage = 'No face enrolled for this account. Please log in with password.';
                                break;
                            case 'threshold_exceeded':
                                errorMessage = 'Face recognition confidence too low. Please try again with better lighting or positioning.';
                                if (currentAttempt < maxAttempts) {
                                    errorMessage += ` ${maxAttempts - currentAttempt} attempts remaining.`;
                                }
                                break;
                            case 'distance_too_high':
                                errorMessage = 'Face does not match enrolled face. Please ensure it is you.';
                                break;
                            default:
                                errorMessage = result.message || 'Face verification failed';
                        }
                    }

                    // Display distance/threshold metrics in debug mode
                    if (FaceUtils.debugMode && result.distance !== undefined && result.threshold !== undefined) {
                        console.log(`Verification Metrics - Distance: ${result.distance.toFixed(6)}, Threshold: ${result.threshold.toFixed(6)}, Match: ${result.distance < result.threshold ? 'PASS' : 'FAIL'}`);
                    }

                    if (currentAttempt < maxAttempts) {
                        // Update status with retry info
                        this.updateStatus(`Attempt ${currentAttempt} failed. ${maxAttempts - currentAttempt} attempts remaining.`);
                        currentAttempt++;

                        // Reset processing state for next attempt
                        this.isProcessing = false;

                        // Wait longer before next attempt to avoid triggering rate limits
                        await new Promise(resolve => setTimeout(resolve, 2000)); // Increased from 1000ms to 2000ms
                        continue; // Continue to next attempt
                    } else {
                        throw new Error(errorMessage);
                    }
                }

            } catch (error) {
                console.error('Face verification error:', error);

                // Handle HTTP 429 error specifically
                if (error.message && error.message.includes('429')) {
                    // If we got a 429 response, don't retry and inform user immediately
                    FaceUtils.hideLoading();
                    FaceUtils.showError('Too many requests. Please try again later.');
                    this.isProcessing = false;
                    return; // Don't retry when rate limited
                }

                // Check if it's a syntax error related to invalid JSON
                if (error instanceof SyntaxError && error.message.includes('JSON')) {
                    // This means we received HTML (like PHP error page) instead of JSON
                    error.message = 'Server configuration error. Please contact administrator.';
                }

                if (currentAttempt < maxAttempts) {
                    this.updateStatus(`Attempt ${currentAttempt} failed. ${maxAttempts - currentAttempt} attempts remaining.`);
                    currentAttempt++;

                    // Reset processing state for next attempt
                    this.isProcessing = false;

                    // Wait longer before next attempt to avoid triggering rate limits
                    await new Promise(resolve => setTimeout(resolve, 2000)); // Increased from 1000ms to 2000ms
                } else {
                    // Final attempt failed
                    FaceUtils.hideLoading();
                    FaceUtils.showError(error.message);
                    this.isProcessing = false;
                    break; // Exit retry loop
                }
            }
        }

        // Reset verification attempts indicator after completion
        this.updateVerificationAttemptsIndicator(0, maxAttempts);
    },

    /**
     * Update status indicator
     * @param {string} type - Type of indicator ('camera', 'models', 'detection')
     * @param {string} status - Status ('pending', 'success', 'error')
     */
    updateStatusIndicator(type, status) {
        const indicatorElement = document.getElementById(`${type}-status-indicator`);
        if (!indicatorElement) return; // Guard against missing element

        switch (status) {
            case 'success':
                indicatorElement.className = 'w-3 h-3 rounded-full bg-emerald-500 mx-auto mb-1';
                break;
            case 'pending':
                indicatorElement.className = 'w-3 h-3 rounded-full bg-yellow-500 mx-auto mb-1';
                break;
            case 'error':
                indicatorElement.className = 'w-3 h-3 rounded-full bg-red-500 mx-auto mb-1';
                break;
            default:
                indicatorElement.className = 'w-3 h-3 rounded-full bg-gray-500 mx-auto mb-1';
        }
    },

    /**
     * Update verification attempts indicator
     * @param {number} current - Current attempt number
     * @param {number} max - Maximum attempts
     */
    updateVerificationAttemptsIndicator(current, max) {
        const countElement = document.getElementById('verification-attempts-count');
        const indicatorElement = document.getElementById('verification-attempts-indicator');

        if (countElement) {
            countElement.textContent = `${current}/${max}`;
        }

        if (indicatorElement) {
            if (current === 0) {
                indicatorElement.className = 'w-3 h-3 rounded-full bg-gray-500 mx-auto mb-1';
            } else if (current < max) {
                indicatorElement.className = 'w-3 h-3 rounded-full bg-yellow-500 mx-auto mb-1';
            } else {
                indicatorElement.className = 'w-3 h-3 rounded-full bg-red-500 mx-auto mb-1';
            }
        }
    },

    /**
     * Get progressive status message based on validation and detection state
     * @param {Object} validation - Validation result from validateFaceQuality
     * @param {Object} detectionState - Detection state from updateDetectionState
     * @returns {string} Status message
     */
    getProgressiveStatusMessage(validation, detectionState) {
        if (detectionState.state === 'stabilizing') {
            return `Stabilizing... ${validation?.message || 'Hold steady'}`;
        } else if (detectionState.state === 'stable') {
            return validation?.message || 'Ready for verification';
        } else {
            // For 'unstable' state, return original validation message
            return validation?.message || 'No face detected';
        }
    },

    /**
     * Update quality bar with smooth transitions
     * @param {Object} smoothedScore - Smoothed quality score from calculateSmoothedQuality
     * @param {Object} detectionState - Detection state from updateDetectionState
     */
    updateQualityBarSmooth(smoothedScore, detectionState) {
        // Get progressive feedback for styling
        const feedback = FaceUtils.getProgressiveFeedback(smoothedScore.score);

        // Update quality bar elements
        const qualityBar = document.getElementById('quality-bar');
        const qualityPercentage = document.getElementById('quality-percentage');

        if (qualityBar) {
            qualityBar.style.width = `${smoothedScore.score}%`;
            qualityBar.className = `${feedback.cssClass} h-2 rounded-full transition-all duration-300`;

            // Add pulsing animation if in stabilizing state
            if (detectionState.state === 'stabilizing') {
                qualityBar.classList.add('animate-pulse');
            } else {
                qualityBar.classList.remove('animate-pulse');
            }
        }

        if (qualityPercentage) {
            qualityPercentage.textContent = `${Math.round(smoothedScore.score)}%`;
        }
    },

    /**
     * Update progressive feedback for login
     * @param {Object} smoothedScore - Smoothed quality score from calculateSmoothedQuality
     * @param {Object} detectionState - Detection state from updateDetectionState
     */
    updateProgressiveFeedback(smoothedScore, detectionState) {
        // Get progressive feedback based on smoothed score
        const feedback = FaceUtils.getProgressiveFeedback(smoothedScore.score);

        // Update state badge based on detection state (if we add one to the UI)
        // This would involve updating the UI to show state information
    },

    /**
     * Update quality bar based on validation result - deprecated, kept for compatibility
     * @param {Object} validation - Validation result from validateFaceQuality
     */
    updateQualityBar(validation) {
        // Calculate quality score based on validation results
        let qualityScore = 0;

        if (validation && validation.details) {
            const { sizeValid, centeringValid, confidenceValid, lightingValid } = validation.details;
            const validChecks = [sizeValid, centeringValid, confidenceValid, lightingValid].filter(Boolean).length;
            qualityScore = (validChecks / 4) * 100; // Max 4 checks
        }

        // Update quality bar elements
        const qualityBar = document.getElementById('quality-bar');
        const qualityPercentage = document.getElementById('quality-percentage');

        if (qualityBar) {
            qualityBar.style.width = `${qualityScore}%`;

            // Update color based on quality
            if (qualityScore >= 80) {
                qualityBar.className = 'bg-emerald-500 h-2 rounded-full transition-all duration-300';
            } else if (qualityScore >= 50) {
                qualityBar.className = 'bg-yellow-500 h-2 rounded-full transition-all duration-300';
            } else {
                qualityBar.className = 'bg-red-500 h-2 rounded-full transition-all duration-300';
            }
        }

        if (qualityPercentage) {
            qualityPercentage.textContent = `${Math.round(qualityScore)}%`;
        }
    },

    /**
     * Show troubleshooting tips when verification fails consistently
     */
    showTroubleshootingTips() {
        const tips = [
            "💡 Ensure your face is well-lit (avoid backlighting)",
            "💡 Position your face in the center of the camera frame",
            "💡 Make sure your camera lens is clean",
            "💡 Try moving slightly closer or farther from the camera",
            "💡 Ensure your face is clearly visible (remove hats, glasses if possible)",
            "💡 Try a different angle or position"
        ];

        // Select a random tip
        const randomTip = tips[Math.floor(Math.random() * tips.length)];

        // Update status with the tip
        const currentStatus = document.getElementById('face-login-status')?.textContent || 'No face detected';
        if (!currentStatus.includes('💡')) { // Only show if not already showing a tip
            this.updateStatus(`${randomTip} - ${currentStatus}`);

            // Reset to regular status after 5 seconds
            setTimeout(() => {
                if (document.getElementById('face-login-status')?.textContent === `${randomTip} - ${currentStatus}`) {
                    this.updateStatus(currentStatus);
                }
            }, 5000);
        }
    },

    /**
     * Update status message
     */
    updateStatus(message) {
        const statusEl = document.getElementById('face-login-status');
        if (statusEl) {
            statusEl.textContent = message;
        }
    },

    /**
     * Cleanup resources
     */
    cleanup() {
        if (this.stream) {
            FaceUtils.stopWebcam(this.stream);
            this.stream = null;
        }

        if (this.detectionInterval) {
            clearInterval(this.detectionInterval);
            this.detectionInterval = null;
        }

        // Reset detection buffer
        FaceUtils.resetDetectionBuffer();

        this.isProcessing = false;
    },

    /**
     * Cancel face login
     */
    cancel() {
        this.cleanup();

        // Hide modal
        const modal = document.getElementById('face-login-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    },

    /**
     * Show face login modal
     */
    showModal() {
        const modal = document.getElementById('face-login-modal');
        if (modal) {
            modal.style.display = 'flex';
            this.initialize();
        }
    }
};

// Auto-setup event listeners
document.addEventListener('DOMContentLoaded', () => {
    // Face login button
    const faceLoginBtn = document.getElementById('face-login-btn');
    if (faceLoginBtn) {
        faceLoginBtn.addEventListener('click', () => {
            FaceLogin.showModal();
        });
    }

    // Verify button
    const verifyBtn = document.getElementById('verify-face-btn');
    if (verifyBtn) {
        verifyBtn.addEventListener('click', () => {
            FaceLogin.verifyFace();
        });
    }

    // Cancel button
    const cancelBtn = document.getElementById('cancel-face-login');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            FaceLogin.cancel();
        });
    }

    // Close modal on background click
    const modal = document.getElementById('face-login-modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                FaceLogin.cancel();
            }
        });
    }
});