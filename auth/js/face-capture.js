/**
 * Face Capture Module
 * Handles face enrollment during registration
 */

const FaceCapture = {
    videoElement: null,
    canvasElement: null,
    stream: null,
    capturedImages: [],
    faceDescriptor: null,
    currentStep: 0,
    totalSteps: 5,
    detectionIntervalId: null,

    // UI update state for debounced updates
    lastUIUpdate: 0,
    currentQualityScore: 0,
    currentDetectionState: null,

    /**
     * Initialize face capture
     */
    async initialize() {
        try {
            // Update status indicators
            this.updateStatusIndicator('camera', 'pending');
            this.updateStatusIndicator('models', 'pending');

            // Load face-api.js models with timeout
            FaceUtils.showLoading('Loading face recognition models (0%)...');
            const timeoutPromise = new Promise((_, reject) =>
                setTimeout(() => reject(new Error('Model loading timed out after 30 seconds')), 30000)
            );

            // Create a progress tracking version of loadModels
            const modelsLoadedPromise = this.loadModelsWithProgress();

            // Race with timeout
            const modelsLoaded = await Promise.race([modelsLoadedPromise, timeoutPromise]);

            if (!modelsLoaded) {
                throw new Error('Failed to load face recognition models. Please check your connection and try again.');
            }

            // Update models status indicator
            this.updateStatusIndicator('models', 'success');

            // Get video and canvas elements
            this.videoElement = document.getElementById('face-video');
            this.canvasElement = document.getElementById('face-canvas');

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

            // Show capture controls
            const captureBtn = document.getElementById('capture-face-btn');
            const progressDiv = document.getElementById('capture-progress');
            const initBtn = document.getElementById('init-face-capture');

            if (captureBtn) captureBtn.classList.remove('hidden');
            if (progressDiv) progressDiv.classList.remove('hidden');
            if (initBtn) initBtn.classList.add('hidden');

            this.startDetection();

        } catch (error) {
            console.error('Face capture initialization error:', error);
            this.updateStatusIndicator('camera', 'error');
            this.updateStatusIndicator('models', 'error');
            FaceUtils.hideLoading();
            if (error.message.includes('timed out')) {
                FaceUtils.showError(error.message + ' Please try again or use password-based registration.');
            } else {
                FaceUtils.showError(error.message);
            }
        }
    },

    /**
     * Load models with progress tracking
     */
    async loadModelsWithProgress() {
        try {
            console.log('Starting model loading process...');

            // Update loading message with progress
            FaceUtils.showLoading('Loading face recognition models (10%)...');

            // Check if models are already loaded
            if (FaceUtils.modelsLoaded) {
                console.log('Models already loaded, skipping load process');
                return true;
            }

            console.log('Loading face-api.js models...');

            // Load required models with progress updates
            FaceUtils.showLoading('Loading SSD model (20%)...');
            await faceapi.nets.ssdMobilenetv1.loadFromUri(FaceUtils.modelPath);

            FaceUtils.showLoading('Loading Tiny Face Detector (40%)...');
            await faceapi.nets.tinyFaceDetector.loadFromUri(FaceUtils.modelPath);

            FaceUtils.showLoading('Loading Face Landmarks (60%)...');
            await faceapi.nets.faceLandmark68Net.loadFromUri(FaceUtils.modelPath);

            FaceUtils.showLoading('Loading Face Recognition (80%)...');
            await faceapi.nets.faceRecognitionNet.loadFromUri(FaceUtils.modelPath);

            FaceUtils.showLoading('Verifying models (90%)...');

            // Verify models are loaded and functional
            try {
                await faceapi.nets.ssdMobilenetv1.load(FaceUtils.modelPath);
                await faceapi.nets.tinyFaceDetector.load(FaceUtils.modelPath);
                await faceapi.nets.faceLandmark68Net.load(FaceUtils.modelPath);
                await faceapi.nets.faceRecognitionNet.load(FaceUtils.modelPath);
                console.log('All face-api.js models verified and loaded successfully');
            } catch (verifyError) {
                console.error('Error verifying models:', verifyError);
                // Don't throw error for verification, models should still be functional
            }

            FaceUtils.modelsLoaded = true;
            console.log('Face-api.js models loaded successfully');
            return true;

        } catch (error) {
            console.error('Error loading face-api.js models:', error);
            return false;
        }
    },

    /**
     * Start continuous face detection
     */
    startDetection() {
        // Clear any existing interval first
        if (this.detectionIntervalId) {
            clearInterval(this.detectionIntervalId);
        }

        let detectionSuccessCount = 0;
        let detectionFailureCount = 0;
        let lastDetectionTime = 0;

        this.detectionIntervalId = setInterval(async () => {
            if (this.currentStep >= this.totalSteps) {
                clearInterval(this.detectionIntervalId);
                this.detectionIntervalId = null;
                return;
            }

            // Implement adaptive detection interval: slow down if CPU usage is high
            const currentTime = Date.now();
            if (lastDetectionTime && (currentTime - lastDetectionTime) < 100) {
                // Skip this detection cycle if previous one was very fast
                return;
            }

            const detection = await FaceUtils.detectFaceWithFallback(this.videoElement);

            if (detection && detection.detection) {
                // Update success count for adaptive detection
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
                    this.updateInstructions(this.getProgressiveInstructionMessage(validation, detectionState));
                    this.lastUIUpdate = Date.now();
                }

                // Enable capture button if face quality is good
                const captureBtn = document.getElementById('capture-face-btn');
                if (captureBtn) {
                    captureBtn.disabled = !validation.valid;
                }
            } else {
                // Update failure count for adaptive detection
                detectionFailureCount++;

                // Update detection status indicator
                this.updateStatusIndicator('detection', 'pending');

                // Clear canvas if no face detected
                const ctx = this.canvasElement.getContext('2d');
                ctx.clearRect(0, 0, this.canvasElement.width, this.canvasElement.height);

                // Provide more specific feedback based on detection attempts
                let instructionMessage = 'No face detected';
                if (detection && detection.attempts) {
                    const failedAttempts = detection.attempts.filter(attempt => !attempt.success);
                    if (failedAttempts.length > 0) {
                        // Show the model that was most successful (or indicate all failed)
                        const successfulAttempt = detection.attempts.find(attempt => attempt.success);
                        if (!successfulAttempt) {
                            instructionMessage = 'No face detected. Please ensure your face is visible and well-lit.';
                        }
                    }
                }
                this.updateInstructions(instructionMessage);

                const captureBtn = document.getElementById('capture-face-btn');
                if (captureBtn) {
                    captureBtn.disabled = true;
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
            }

            lastDetectionTime = Date.now();

            // Adaptive detection: reduce frequency if detection is failing consistently
            if (detectionFailureCount > 10 && detectionSuccessCount < 2) {
                // If we're having trouble detecting, show tips
                this.showTroubleshootingTips();
                detectionFailureCount = 0; // Reset to avoid constant tip display
            }
        }, 100); // Check every 100ms
    },

    /**
     * Capture current face image
     */
    async captureFace() {
        try {
            FaceUtils.showLoading('Capturing face...');

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

            // Validate image quality before storing
            const imageQuality = await this.validateImageQuality(this.videoElement);
            if (!imageQuality.valid) {
                throw new Error(imageQuality.message);
            }

            // Capture image
            const imageBlob = await FaceUtils.captureImage(this.videoElement);

            // Store multiple descriptors and average them for better quality
            if (this.currentStep === 0) {
                // For the first capture, store the descriptor directly
                this.faceDescriptor = detection.descriptor;
            } else {
                // For subsequent captures, we'll create an average descriptor if needed later
                // For now, just store the detection data for potential averaging
                if (!this.allDescriptors) {
                    this.allDescriptors = [];
                }
                this.allDescriptors.push(detection.descriptor);
            }

            // Store image
            this.capturedImages.push(imageBlob);
            this.currentStep++;

            // Show visual confirmation (flash effect)
            this.showCaptureConfirmation();

            // Update progress
            this.updateProgress();

            // Update enrollment progress indicator
            this.updateEnrollmentProgress();

            FaceUtils.hideLoading();

            // Show next instruction
            if (this.currentStep < this.totalSteps) {
                this.showNextInstruction();
            } else {
                // Average descriptors if we collected multiple ones
                if (this.allDescriptors && this.allDescriptors.length > 0) {
                    this.faceDescriptor = this.averageDescriptors([this.faceDescriptor, ...this.allDescriptors]);
                }
                this.completeCapture();
            }

        } catch (error) {
            console.error('Face capture error:', error);
            FaceUtils.hideLoading();
            FaceUtils.showError(error.message);
        }
    },

    /**
     * Validate image quality before storing
     * @param {HTMLVideoElement} videoElement Video element
     * @returns {Promise<Object>} Quality validation result
     */
    async validateImageQuality(videoElement) {
        try {
            // Check video stream health
            const streamHealth = await FaceUtils.validateVideoStream(videoElement);
            if (!streamHealth.valid) {
                return { valid: false, message: streamHealth.reason };
            }

            // Check if video has minimum resolution
            if (videoElement.videoWidth < 160 || videoElement.videoHeight < 120) {
                return { valid: false, message: 'Video resolution too low for quality face recognition' };
            }

            // Check if the image is too dark (by sampling a few pixels)
            const canvas = document.createElement('canvas');
            canvas.width = videoElement.videoWidth;
            canvas.height = videoElement.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(videoElement, 0, 0);

            // Sample the image brightness to detect if it's too dark
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;
            let totalBrightness = 0;
            let pixelCount = 0;

            // Sample every 10th pixel to avoid performance issues
            for (let i = 0; i < data.length; i += 4 * 10) {
                const r = data[i];
                const g = data[i + 1];
                const b = data[i + 2];
                const brightness = (r + g + b) / 3;
                totalBrightness += brightness;
                pixelCount++;
            }

            const avgBrightness = pixelCount > 0 ? totalBrightness / pixelCount : 0;

            if (avgBrightness < 30) { // Threshold for "too dark"
                return { valid: false, message: 'Image too dark for face recognition. Please improve lighting.' };
            }

            if (avgBrightness > 220) { // Threshold for "too bright"
                return { valid: false, message: 'Image too bright for face recognition. Please reduce lighting.' };
            }

            return { valid: true, message: 'Image quality is good' };
        } catch (error) {
            console.warn('Error validating image quality:', error);
            // If validation fails, we still allow capture but with warning
            return { valid: true, message: 'Image quality validation failed, but proceeding' };
        }
    },

    /**
     * Average multiple face descriptors to improve quality
     * @param {Array} descriptors Array of face descriptors
     * @returns {Array} Averaged descriptor
     */
    averageDescriptors(descriptors) {
        if (!descriptors || descriptors.length === 0) {
            return [];
        }

        if (descriptors.length === 1) {
            return descriptors[0];
        }

        // Ensure all descriptors have the same length (128 for face-api.js)
        const descriptorLength = descriptors[0].length;
        const avgDescriptor = new Array(descriptorLength).fill(0);

        // Sum all descriptors
        for (let i = 0; i < descriptors.length; i++) {
            for (let j = 0; j < descriptorLength; j++) {
                avgDescriptor[j] += descriptors[i][j];
            }
        }

        // Calculate average
        for (let i = 0; i < descriptorLength; i++) {
            avgDescriptor[i] /= descriptors.length;
        }

        return avgDescriptor;
    },

    /**
     * Show capture confirmation visual effect
     */
    showCaptureConfirmation() {
        // Create a temporary element for visual feedback
        const canvas = this.canvasElement;
        const ctx = canvas.getContext('2d');

        // Save current context
        ctx.save();

        // Create a temporary "flash" effect
        ctx.fillStyle = 'rgba(0, 255, 0, 0.3)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // Restore context after a short delay
        setTimeout(() => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }, 200);

        // Restore original context
        ctx.restore();
    },

    /**
     * Show troubleshooting tips when detection fails consistently
     */
    showTroubleshootingTips() {
        const tips = [
            "💡 Ensure your face is well-lit (avoid backlighting)",
            "💡 Position your face in the center of the camera frame",
            "💡 Make sure your camera lens is clean",
            "💡 Try moving slightly closer or farther from the camera",
            "💡 Avoid extreme head angles or positions",
            "💡 Ensure no objects are blocking your face (glasses, hats, etc.)"
        ];

        // Select a random tip
        const randomTip = tips[Math.floor(Math.random() * tips.length)];

        // Update instructions with the tip
        const currentInstruction = document.getElementById('face-instructions')?.textContent || 'No face detected';
        if (!currentInstruction.includes('💡')) { // Only show if not already showing a tip
            this.updateInstructions(`${randomTip} - ${currentInstruction}`);

            // Reset to regular instruction after 5 seconds
            setTimeout(() => {
                if (document.getElementById('face-instructions')?.textContent === `${randomTip} - ${currentInstruction}`) {
                    this.updateInstructions(currentInstruction);
                }
            }, 5000);
        }
    },

    /**
     * Show instruction for next capture
     */
    showNextInstruction() {
        const instructions = [
            'Great! Now turn your head slightly to the LEFT',
            'Perfect! Now turn your head slightly to the RIGHT',
            'Excellent! Now tilt your head slightly UP',
            'Almost done! Now tilt your head slightly DOWN'
        ];

        if (this.currentStep < instructions.length) {
            this.updateInstructions(instructions[this.currentStep]);
        }
    },

    /**
     * Update progress indicator
     */
    updateProgress() {
        const countSpan = document.getElementById('capture-count');
        const progressBar = document.getElementById('capture-progress-bar');

        if (countSpan) {
            countSpan.textContent = `${this.currentStep}/${this.totalSteps}`;
        }

        if (progressBar) {
            const percentage = (this.currentStep / this.totalSteps) * 100;
            progressBar.style.width = `${percentage}%`;
        }
    },

    /**
     * Update instructions text
     */
    updateInstructions(message) {
        const instructionsEl = document.getElementById('face-instructions');
        if (instructionsEl) {
            const paragraph = instructionsEl.querySelector('p');
            if (paragraph) {
                paragraph.textContent = message;
            } else {
                instructionsEl.textContent = message;
            }
        }
    },

    /**
     * Complete capture and enroll face
     */
    /**
     * Complete capture and enroll face
     */
    async completeCapture() {
        try {
            FaceUtils.showLoading('Processing face data...');

            // Check if we can upload immediately (user_id exists)
            const userId = document.getElementById('user-id')?.value || sessionStorage.getItem('user_id');

            if (userId) {
                await this.uploadFaceData(userId);
            } else {
                // Deferred mode: Store data and notify
                console.log('Face captured, waiting for user registration to upload...');
                this.onDeferredComplete();
            }

        } catch (error) {
            console.error('Face enrollment error:', error);
            FaceUtils.hideLoading();
            FaceUtils.showError(error.message);
        }
    },

    async uploadFaceData(userId, options = { redirectOnComplete: true, enrollmentToken: null }) {
        try {
            FaceUtils.showLoading('Enrolling face recognition...');

            // Prepare form data
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('face_descriptor', JSON.stringify(this.faceDescriptor));

            // Add enrollment token if provided (for registration flow)
            if (options.enrollmentToken) {
                formData.append('enrollment_token', options.enrollmentToken);
            }

            // Append images
            this.capturedImages.forEach((blob, index) => {
                formData.append(`face_image_${index + 1}`, blob, `face_${index + 1}.jpg`);
            });

            // Send to enrollment API
            const response = await fetch('../api/face/enroll.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                FaceUtils.hideLoading();
                FaceUtils.showSuccess('Face recognition enrolled successfully!');
                this.cleanup();

                // Only redirect if explicitly requested (profile update flow)
                if (options.redirectOnComplete) {
                    setTimeout(() => {
                        this.onComplete();
                    }, 1500);
                }
            } else {
                throw new Error(result.message || 'Enrollment failed');
            }
        } catch (error) {
            throw error; // Re-throw to be handled by caller
        }
    },

    /**
     * Handle deferred completion
     */
    onDeferredComplete() {
        FaceUtils.hideLoading();
        FaceUtils.showSuccess('Face captured! Logic will complete after registration.');

        // Update UI to show enrolled status
        const statusDiv = document.getElementById('enrollmentStatus');
        if (statusDiv) {
            statusDiv.textContent = 'Face data captured. Ready to register.';
            statusDiv.className = 'text-sm text-center text-emerald-400 font-medium block mt-2';
            statusDiv.classList.remove('hidden');
        }

        const enrollBtn = document.getElementById('enrollFaceBtn');
        if (enrollBtn) {
            enrollBtn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i><span>Face Captured</span>';
            enrollBtn.classList.add('bg-emerald-900/30', 'border-emerald-500/50', 'text-emerald-400');
            enrollBtn.classList.remove('text-gray-300', 'bg-zinc-800/50');
        }

        // Hide modal
        const modal = document.getElementById('face-capture-modal');
        if (modal) {
            modal.style.display = 'none';
        }

        // Don't cleanup yet, we need the data!
        // But reset stepping for re-takes if needed
        this.currentStep = 0;
    },

    /**
     * Public method to trigger upload from external script
     */
    async uploadPendingData(userId, options = { redirectOnComplete: false, enrollmentToken: null }) {
        if (!this.faceDescriptor) {
            throw new Error('No face data to upload');
        }
        return this.uploadFaceData(userId, options);
    },

    /**
     * Cleanup resources
     */
    cleanup() {
        // Clear detection interval
        if (this.detectionIntervalId) {
            clearInterval(this.detectionIntervalId);
            this.detectionIntervalId = null;
        }

        if (this.stream) {
            FaceUtils.stopWebcam(this.stream);
            this.stream = null;
        }

        // Reset detection buffer
        FaceUtils.resetDetectionBuffer();

        this.capturedImages = [];
        this.faceDescriptor = null;
        this.currentStep = 0;
    },

    /**
     * Cancel face capture
     */
    cancel() {
        this.cleanup();

        // Hide modal or redirect
        const modal = document.getElementById('face-capture-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    },

    /**
     * Get progressive instruction message based on validation and detection state
     * @param {Object} validation - Validation result from validateFaceQuality
     * @param {Object} detectionState - Detection state from updateDetectionState
     * @returns {string} Instruction message
     */
    getProgressiveInstructionMessage(validation, detectionState) {
        if (detectionState.state === 'stabilizing') {
            return `Stabilizing... ${validation?.message || 'Hold steady'}`;
        } else if (detectionState.state === 'stable') {
            return validation?.message || 'Ready for capture';
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
        const qualityBar = document.getElementById('quality-bar-reg');
        const qualityPercentage = document.getElementById('quality-percentage-reg');

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
     * Update progressive feedback chips
     * @param {Object} smoothedScore - Smoothed quality score from calculateSmoothedQuality
     * @param {Object} detectionState - Detection state from updateDetectionState
     */
    updateProgressiveFeedback(smoothedScore, detectionState) {
        // Get progressive feedback based on smoothed score
        const feedback = FaceUtils.getProgressiveFeedback(smoothedScore.score);

        // Update the capture quality feedback container
        const feedbackContainer = document.getElementById('capture-quality-feedback');
        if (feedbackContainer) {
            feedbackContainer.classList.remove('hidden');
        }

        // Update overall quality level display
        const qualityScoreValue = document.getElementById('quality-score-value');
        const qualityScoreLabel = document.getElementById('quality-score-label');
        const enrollmentQualityScore = document.getElementById('enrollment-quality-score');

        if (qualityScoreValue) {
            qualityScoreValue.textContent = `${Math.round(smoothedScore.score)}%`;
        }

        if (qualityScoreLabel) {
            qualityScoreLabel.textContent = `${feedback.level.charAt(0).toUpperCase() + feedback.level.slice(1)} Quality`;
        }

        if (enrollmentQualityScore) {
            enrollmentQualityScore.classList.remove('hidden');
        }

        // Update state badge based on detection state
        const stateBadge = document.createElement('span');
        stateBadge.className = `px-2 py-1 text-xs rounded ${detectionState.state === 'stabilizing' ? 'bg-yellow-900/50 text-yellow-300' : detectionState.state === 'stable' ? 'bg-emerald-900/50 text-emerald-300' : 'bg-gray-900/50 text-gray-300'}`;
        stateBadge.textContent = detectionState.state.charAt(0).toUpperCase() + detectionState.state.slice(1);
    },

    /**
     * Callback when capture is complete
     */
    onComplete() {
        // Override this in implementation
        console.log('Face capture complete');
        window.location.href = '../dashboard/index.html';
    },

    /**
     * Update status indicator for enrollment
     * @param {string} type - Type of indicator ('camera', 'models', 'detection')
     * @param {string} status - Status ('pending', 'success', 'error')
     */
    updateStatusIndicator(type, status) {
        const indicatorElement = document.getElementById(`${type}-status-indicator-reg`);
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
     * Update enrollment progress indicator
     */
    updateEnrollmentProgress() {
        const countElement = document.getElementById('enrollment-progress-count');
        const indicatorElement = document.getElementById('enrollment-progress-indicator');

        if (countElement) {
            countElement.textContent = `${this.currentStep}/${this.totalSteps}`;
        }

        if (indicatorElement) {
            if (this.currentStep === 0) {
                indicatorElement.className = 'w-3 h-3 rounded-full bg-gray-500 mx-auto mb-1';
            } else if (this.currentStep < this.totalSteps) {
                indicatorElement.className = 'w-3 h-3 rounded-full bg-yellow-500 mx-auto mb-1';
            } else {
                indicatorElement.className = 'w-3 h-3 rounded-full bg-emerald-500 mx-auto mb-1';
            }
        }
    },

    /**
     * Update quality bar for face capture
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
        const qualityBar = document.getElementById('quality-bar-reg');
        const qualityPercentage = document.getElementById('quality-percentage-reg');

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
     * Update capture quality feedback chips
     * @param {Object} validation - Validation result from validateFaceQuality
     */
    updateCaptureQualityFeedback(validation) {
        if (!validation || !validation.details) {
            // Hide feedback if no validation
            const feedbackContainer = document.getElementById('capture-quality-feedback');
            if (feedbackContainer) {
                feedbackContainer.classList.add('hidden');
            }
            return;
        }

        // Show feedback container
        const feedbackContainer = document.getElementById('capture-quality-feedback');
        if (feedbackContainer) {
            feedbackContainer.classList.remove('hidden');
        }

        const { sizeValid, centeringValid, confidenceValid, lightingValid } = validation.details;

        // Update size feedback
        const sizeFeedback = document.getElementById('size-feedback');
        if (sizeFeedback) {
            sizeFeedback.textContent = sizeValid ? '✓ Size Good' : '⚠ Size Issue';
            sizeFeedback.className = sizeValid
                ? 'px-2 py-1 text-xs rounded bg-emerald-900/50 text-emerald-300'
                : 'px-2 py-1 text-xs rounded bg-red-900/50 text-red-300';
        }

        // Update centering feedback
        const centeringFeedback = document.getElementById('centering-feedback');
        if (centeringFeedback) {
            centeringFeedback.textContent = centeringValid ? '✓ Centered' : '⚠ Off Center';
            centeringFeedback.className = centeringValid
                ? 'px-2 py-1 text-xs rounded bg-emerald-900/50 text-emerald-300'
                : 'px-2 py-1 text-xs rounded bg-red-900/50 text-red-300';
        }

        // Update lighting feedback
        const lightingFeedback = document.getElementById('lighting-feedback');
        if (lightingFeedback) {
            lightingFeedback.textContent = lightingValid ? '✓ Good Light' : '⚠ Poor Light';
            lightingFeedback.className = lightingValid
                ? 'px-2 py-1 text-xs rounded bg-emerald-900/50 text-emerald-300'
                : 'px-2 py-1 text-xs rounded bg-red-900/50 text-red-300';
        }

        // Update confidence feedback
        const confidenceFeedback = document.getElementById('confidence-feedback');
        if (confidenceFeedback) {
            confidenceFeedback.textContent = confidenceValid ? '✓ Good Confidence' : '⚠ Low Confidence';
            confidenceFeedback.className = confidenceValid
                ? 'px-2 py-1 text-xs rounded bg-emerald-900/50 text-emerald-300'
                : 'px-2 py-1 text-xs rounded bg-red-900/50 text-red-300';
        }
    }
};

// Auto-initialize if on face capture page
document.addEventListener('DOMContentLoaded', () => {
    const initBtn = document.getElementById('init-face-capture');
    if (initBtn) {
        initBtn.addEventListener('click', () => {
            FaceCapture.initialize();
        });
    }

    const captureBtn = document.getElementById('capture-face-btn');
    if (captureBtn) {
        captureBtn.addEventListener('click', () => {
            FaceCapture.captureFace();
        });
    }

    const cancelBtn = document.getElementById('cancel-face-capture');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            FaceCapture.cancel();
        });
    }

    const skipBtn = document.getElementById('skip-face-enrollment');
    if (skipBtn) {
        skipBtn.addEventListener('click', () => {
            FaceCapture.cancel();
        });
    }
});