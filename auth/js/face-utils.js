/**
 * Face Detection Utilities
 * Shared utilities for face-api.js operations
 */

const FaceUtils = {
    modelsLoaded: false,
    modelPath: '/Projet-2A/models',  // Updated to use local path
    debugMode: false,
    config: null, // Will be initialized in initConfig

    // Detection buffering and smoothing
    detectionBuffer: [],
    maxBufferSize: 10,
    detectionState: 'unstable',
    stableFrameCount: 0,

    /**
     * Initialize configuration
     */
    initConfig() {
        // Use FaceConfig if available, otherwise use defaults
        this.config = (typeof FaceConfig !== 'undefined') ? FaceConfig : {
            detection: {
                ssdMinConfidence: 0.3,
                tinyMinConfidence: 0.4,
                fallbackEnabled: true,
                maxRetries: 3
            },
            validation: {
                minFaceSize: 80,
                maxFaceSize: 500,
                minConfidence: 0.4,
                centeringMargin: 0.15,
                minStableFrames: 5,
                hysteresisMargin: 0.1,
                qualityThresholds: { poor: 25, fair: 50, good: 80 }
            },
            liveness: {
                blinkThreshold: 0.2,          // Eye aspect ratio threshold for blink detection
                blinkMinFrames: 2,            // Minimum frames for blink detection
                headMovementThreshold: 0.1,   // Minimum head movement for liveness
                maxVerificationTime: 5000,     // Maximum time allowed for liveness check (5 seconds)
                livenessEnabled: true,        // Toggle liveness detection
                sensitivity: 'medium',        // 'low', 'medium', 'high'
                requiredChecks: ['blink', 'movement']  // Which checks must pass
            },
            camera: {
                retryAttempts: 3,
                retryDelays: [0, 2000, 5000],
                timeout: 10000
            },
            backend: {
                verificationThreshold: 0.65,
                maxVerificationAttempts: 5,
                lockoutDuration: 300
            },
            debug: {
                enabled: false,
                logLevel: 'info',
                showOverlays: true
            },
            ui: {
                detectionInterval: 100,    // Detection runs every 100ms
                uiUpdateInterval: 300,     // UI updates every 300ms
                transitionDuration: 300    // CSS transition duration in ms
            }
        };
    },

    /**
     * Load face-api.js models
     * @returns {Promise<boolean>} True if models loaded successfully
     */
    async loadModels() {
        // Initialize configuration if not already done
        if (!this.config) {
            this.initConfig();
        }

        if (this.modelsLoaded) {
            return true;
        }

        try {
            console.log('Loading face-api.js models...');

            // Try to enable debug mode from URL parameter or config
            const urlParams = new URLSearchParams(window.location.search);
            this.debugMode = urlParams.has('debug') || localStorage.getItem('faceDebug') === 'true' || this.config.debug.enabled;

            // Load required models with detailed logging
            await Promise.all([
                faceapi.nets.ssdMobilenetv1.loadFromUri(this.modelPath)
                    .then(() => console.log('SSD MobileNet v1 model loaded'))
                    .catch(err => console.error('Error loading SSD MobileNet v1:', err)),
                faceapi.nets.tinyFaceDetector.loadFromUri(this.modelPath)
                    .then(() => console.log('Tiny Face Detector model loaded'))
                    .catch(err => console.error('Error loading Tiny Face Detector:', err)),
                faceapi.nets.faceLandmark68Net.loadFromUri(this.modelPath)
                    .then(() => console.log('Face Landmark 68 model loaded'))
                    .catch(err => console.error('Error loading Face Landmark 68:', err)),
                faceapi.nets.faceRecognitionNet.loadFromUri(this.modelPath)
                    .then(() => console.log('Face Recognition model loaded'))
                    .catch(err => console.error('Error loading Face Recognition:', err))
            ]);

            // Verify models are loaded and functional
            try {
                await faceapi.nets.ssdMobilenetv1.load(this.modelPath);
                await faceapi.nets.tinyFaceDetector.load(this.modelPath);
                await faceapi.nets.faceLandmark68Net.load(this.modelPath);
                await faceapi.nets.faceRecognitionNet.load(this.modelPath);
                console.log('All face-api.js models verified and loaded successfully');
            } catch (verifyError) {
                console.error('Error verifying models:', verifyError);
            }

            this.modelsLoaded = true;
            console.log('Face-api.js models loaded successfully');
            return true;

        } catch (error) {
            console.error('Error loading face-api.js models:', error);
            return false;
        }
    },

    /**
     * Detect face and extract descriptor from video element
     * @param {HTMLVideoElement} videoElement Video element with webcam stream
     * @returns {Promise<Object|null>} Detection result with descriptor or null
     */
    async detectFace(videoElement) {
        try {
            // Initialize configuration if not already done
            if (!this.config) {
                this.initConfig();
            }

            // Start timing for performance metrics
            const startTime = performance.now();

            // Detect single face with landmarks and descriptor using config threshold
            const detection = await faceapi
                .detectSingleFace(videoElement, new faceapi.SsdMobilenetv1Options({ minConfidence: this.config.detection.ssdMinConfidence }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            const endTime = performance.now();
            const detectionTime = endTime - startTime;

            if (!detection) {
                if (this.debugMode) {
                    console.log(`SSD detection failed after ${detectionTime.toFixed(2)}ms`);
                }
                return {
                    detection: null,
                    landmarks: null,
                    descriptor: null,
                    box: null,
                    modelUsed: 'ssd',
                    confidence: 0,
                    detectionTime: detectionTime
                };
            }

            if (this.debugMode) {
                console.log(`SSD detection succeeded after ${detectionTime.toFixed(2)}ms with confidence: ${detection.detection.score}`);
            }

            return {
                detection: detection.detection,
                landmarks: detection.landmarks,
                descriptor: Array.from(detection.descriptor), // Convert Float32Array to regular array
                box: detection.detection.box,
                modelUsed: 'ssd',
                confidence: detection.detection.score,
                detectionTime: detectionTime
            };

        } catch (error) {
            console.error('Error detecting face:', error);
            return null;
        }
    },

    /**
     * Detect face with fallback mechanism: SSD -> TinyFaceDetector -> SSD with very permissive settings
     * @param {HTMLVideoElement} videoElement Video element with webcam stream
     * @returns {Promise<Object>} Best detection result with metadata
     */
    async detectFaceWithFallback(videoElement) {
        // Initialize configuration if not already done
        if (!this.config) {
            this.initConfig();
        }

        const attempts = [];

        // First attempt: SSD with configured confidence
        const ssdResult1 = await this.detectFace(videoElement);

        // Guard against null result from detectFace
        const ssdSuccess = !!(ssdResult1 && ssdResult1.detection);
        attempts.push({
            attempt: 1,
            model: 'ssd',
            confidence: ssdResult1 && typeof ssdResult1.confidence !== 'undefined' ? ssdResult1.confidence : this.config.detection.ssdMinConfidence,
            success: ssdSuccess,
            detectionTime: ssdResult1 && typeof ssdResult1.detectionTime !== 'undefined' ? ssdResult1.detectionTime : 0
        });

        if (ssdResult1 && ssdResult1.detection) {
            if (this.debugMode) {
                console.log(`Detection successful with SSD model, confidence: ${ssdResult1.confidence}`);
            }
            return ssdResult1;
        }

        // Second attempt: TinyFaceDetector with configured confidence
        try {
            const startTime = performance.now();
            const tinyOptions = new faceapi.TinyFaceDetectorOptions({
                inputSize: 512,
                scoreThreshold: this.config.detection.tinyMinConfidence
            });
            const tinyDetection = await faceapi
                .detectSingleFace(videoElement, tinyOptions)
                .withFaceLandmarks()
                .withFaceDescriptor();

            const endTime = performance.now();
            const detectionTime = endTime - startTime;

            attempts.push({
                attempt: 2,
                model: 'tinyface',
                confidence: tinyDetection ? tinyDetection.detection.score : 0,
                success: !!tinyDetection,
                detectionTime: detectionTime
            });

            if (tinyDetection && tinyDetection.detection) {
                if (this.debugMode) {
                    console.log(`Detection successful with TinyFaceDetector, confidence: ${tinyDetection.detection.score}`);
                }
                return {
                    detection: tinyDetection.detection,
                    landmarks: tinyDetection.landmarks,
                    descriptor: Array.from(tinyDetection.descriptor),
                    box: tinyDetection.detection.box,
                    modelUsed: 'tinyface',
                    confidence: tinyDetection.detection.score,
                    detectionTime: detectionTime
                };
            }
        } catch (tinyError) {
            console.warn('TinyFaceDetector failed:', tinyError);
            attempts.push({
                attempt: 2,
                model: 'tinyface',
                confidence: 0,
                success: false,
                detectionTime: performance.now() - (performance.now() - 0), // Placeholder timing
                error: tinyError.message
            });
        }

        // Third attempt: SSD with very permissive settings (hardcoded low threshold)
        try {
            const startTime = performance.now();
            const ssdOptions = new faceapi.SsdMobilenetv1Options({ minConfidence: 0.1 });
            const veryPermissiveDetection = await faceapi
                .detectSingleFace(videoElement, ssdOptions)
                .withFaceLandmarks()
                .withFaceDescriptor();

            const endTime = performance.now();
            const detectionTime = endTime - startTime;

            attempts.push({
                attempt: 3,
                model: 'ssd_permissive',
                confidence: veryPermissiveDetection ? veryPermissiveDetection.detection.score : 0,
                success: !!veryPermissiveDetection,
                detectionTime: detectionTime
            });

            if (veryPermissiveDetection && veryPermissiveDetection.detection) {
                if (this.debugMode) {
                    console.log(`Detection successful with permissive SSD model, confidence: ${veryPermissiveDetection.detection.score}`);
                }
                return {
                    detection: veryPermissiveDetection.detection,
                    landmarks: veryPermissiveDetection.landmarks,
                    descriptor: Array.from(veryPermissiveDetection.descriptor),
                    box: veryPermissiveDetection.detection.box,
                    modelUsed: 'ssd_permissive',
                    confidence: veryPermissiveDetection.detection.score,
                    detectionTime: detectionTime
                };
            }
        } catch (ssdPermissiveError) {
            console.warn('Very permissive SSD failed:', ssdPermissiveError);
            attempts.push({
                attempt: 3,
                model: 'ssd_permissive',
                confidence: 0,
                success: false,
                detectionTime: performance.now() - (performance.now() - 0), // Placeholder timing
                error: ssdPermissiveError.message
            });
        }

        // No detection succeeded, return best result with metadata
        if (this.debugMode) {
            console.log('All detection attempts failed, returning null result with attempt details:', attempts);
        }

        return {
            detection: null,
            landmarks: null,
            descriptor: null,
            box: null,
            modelUsed: null,
            confidence: 0,
            detectionTime: 0,
            attempts: attempts
        };
    },

    /**
     * Draw face detection box on canvas
     * @param {HTMLCanvasElement} canvas Canvas element
     * @param {Object} detection Detection result from detectFace
     */
    drawFaceBox(canvas, detection) {
        if (!detection || !detection.box) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const { x, y, width, height } = detection.box;

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw box
        ctx.strokeStyle = '#00ff00';
        ctx.lineWidth = 3;
        ctx.strokeRect(x, y, width, height);

        // Draw landmarks if available
        if (detection.landmarks) {
            const landmarks = detection.landmarks.positions;
            ctx.fillStyle = '#00ff00';
            landmarks.forEach(point => {
                ctx.beginPath();
                ctx.arc(point.x, point.y, 2, 0, 2 * Math.PI);
                ctx.fill();
            });
        }
    },

    /**
     * Draw debug information on canvas
     * @param {HTMLCanvasElement} canvas Canvas element
     * @param {Object} detection Detection result
     * @param {HTMLVideoElement} videoElement Video element
     */
    drawDebugInfo(canvas, detection, videoElement) {
        if (!this.debugMode || !detection || !detection.box) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const { x, y, width, height } = detection.box;

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw face box with confidence score
        ctx.strokeStyle = '#00ff00';
        ctx.lineWidth = 3;
        ctx.strokeRect(x, y, width, height);

        // Draw confidence percentage
        ctx.fillStyle = '#00ff00';
        ctx.font = '16px Arial';
        ctx.fillText(`Confidence: ${(detection.confidence * 100).toFixed(1)}%`, x, y - 10);

        // Draw face size dimensions
        ctx.fillText(`Size: ${Math.round(width)}x${Math.round(height)}`, x, y - 30);

        // Draw model used
        if (detection.modelUsed) {
            ctx.fillText(`Model: ${detection.modelUsed}`, x, y - 50);
        }

        // Draw centering guide
        const videoWidth = videoElement.videoWidth || 640;
        const videoHeight = videoElement.videoHeight || 480;
        const centerX = x + width / 2;
        const centerY = y + height / 2;

        // Centering margin (15% as per validation)
        const marginX = videoWidth * 0.15;
        const marginY = videoHeight * 0.15;

        // Draw centering margins
        ctx.strokeStyle = '#ffff00';
        ctx.lineWidth = 1;
        ctx.strokeRect(marginX, marginY, videoWidth - 2 * marginX, videoHeight - 2 * marginY);

        // Draw center point
        ctx.beginPath();
        ctx.arc(centerX, centerY, 4, 0, 2 * Math.PI);
        ctx.fillStyle = '#ff0000';
        ctx.fill();

        // Draw landmarks if available
        if (detection.landmarks) {
            const landmarks = detection.landmarks.positions;
            ctx.fillStyle = '#00ff00';
            landmarks.forEach(point => {
                ctx.beginPath();
                ctx.arc(point.x, point.y, 2, 0, 2 * Math.PI);
                ctx.fill();
            });
        }

        // Draw detection timing if available
        if (detection.detectionTime) {
            ctx.fillText(`Time: ${detection.detectionTime.toFixed(2)}ms`, 10, 20);
        }

        // Draw detection attempts if available
        if (detection.attempts) {
            ctx.fillText(`Attempts: ${detection.attempts.length}`, 10, 40);
        }
    },

    /**
     * Validate face quality for enrollment
     * @param {Object} detection Detection result from detectFace
     * @returns {Object} Validation result with {valid: boolean, message: string, details: Object}
     */
    validateFaceQuality(detection) {
        // Initialize configuration if not already done
        if (!this.config) {
            this.initConfig();
        }

        if (!detection) {
            return {
                valid: false,
                message: 'No face detected',
                details: {
                    faceDetected: false,
                    sizeValid: false,
                    centeringValid: false,
                    confidenceValid: false,
                    lightingValid: false
                }
            };
        }

        const { box } = detection;

        // Use configuration values for face size constraints
        if (box.width < this.config.validation.minFaceSize || box.height < this.config.validation.minFaceSize) {
            return {
                valid: false,
                message: 'Face too small. Move closer to camera or improve lighting',
                details: {
                    faceDetected: true,
                    sizeValid: false,
                    centeringValid: true,
                    confidenceValid: true,
                    lightingValid: true
                }
            };
        }

        // Use configuration for max face size constraint
        if (box.width > this.config.validation.maxFaceSize || box.height > this.config.validation.maxFaceSize) {
            return {
                valid: false,
                message: 'Face too large. Move back from camera',
                details: {
                    faceDetected: true,
                    sizeValid: false,
                    centeringValid: true,
                    confidenceValid: true,
                    lightingValid: true
                }
            };
        }

        // Use configuration for centering requirements
        const videoWidth = 640; // Assuming standard webcam resolution
        const videoHeight = 480;
        const centerX = box.x + box.width / 2;
        const centerY = box.y + box.height / 2;

        const marginX = videoWidth * this.config.validation.centeringMargin;
        const marginY = videoHeight * this.config.validation.centeringMargin;

        if (centerX < marginX || centerX > videoWidth - marginX ||
            centerY < marginY || centerY > videoHeight - marginY) {
            return {
                valid: false,
                message: 'Face not centered. Adjust your position to be more in the center of the frame',
                details: {
                    faceDetected: true,
                    sizeValid: true,
                    centeringValid: false,
                    confidenceValid: true,
                    lightingValid: true
                }
            };
        }

        // Use configuration for detection confidence threshold
        if (detection.detection.score < this.config.validation.minConfidence) {
            return {
                valid: false,
                message: 'Face detection confidence too low. Improve lighting or adjust position',
                details: {
                    faceDetected: true,
                    sizeValid: true,
                    centeringValid: true,
                    confidenceValid: false,
                    lightingValid: true
                }
            };
        }

        // Add lighting quality detection using landmark visibility
        let visibleLandmarks = 0;
        const totalLandmarks = 68;
        if (detection.landmarks) {
            // Simple visibility check based on landmark positions variance
            const positions = detection.landmarks.positions;
            if (positions.length > 0) {
                // Count landmarks that are within reasonable bounds
                visibleLandmarks = positions.filter(pos =>
                    pos.x >= 0 && pos.y >= 0 &&
                    pos.x <= 640 && pos.y <= 480
                ).length;
            }
        }

        // Require at least 80% landmarks to be visible for good lighting
        if (visibleLandmarks / totalLandmarks < 0.8) {
            return {
                valid: false,
                message: 'Poor lighting detected. Ensure face is well-lit, especially around eyes and nose',
                details: {
                    faceDetected: true,
                    sizeValid: true,
                    centeringValid: true,
                    confidenceValid: true,
                    lightingValid: false
                }
            };
        }

        return {
            valid: true,
            message: 'Face quality good for recognition',
            details: {
                faceDetected: true,
                sizeValid: true,
                centeringValid: true,
                confidenceValid: true,
                lightingValid: true
            }
        };
    },

    /**
     * Initialize webcam and return video stream
     * @param {HTMLVideoElement} videoElement Video element to attach stream
     * @returns {Promise<MediaStream>} Media stream
     */
    async initializeWebcam(videoElement) {
        // Initialize configuration if not already done
        if (!this.config) {
            this.initConfig();
        }

        // Check browser compatibility
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Camera access not supported in this browser. Please use Chrome, Firefox, Safari, or Edge.');
        }

        // Camera retry attempts with exponential backoff using config values
        const retryAttempts = this.config.camera.retryAttempts;
        const retryDelays = this.config.camera.retryDelays; // Delays in milliseconds

        // Check for camera permission before starting
        try {
            const permissionStatus = await navigator.permissions.query({name: 'camera'});
            if (permissionStatus.state === 'denied') {
                throw new Error('Camera permission denied. Please allow camera access in your browser settings.');
            }
        } catch (permError) {
            console.warn('Could not check camera permission:', permError);
        }

        // Try to get camera access with different constraints if first attempt fails
        const videoConstraints = [
            { // Ideal high resolution
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user'
            },
            { // Reasonable resolution
                width: { ideal: 320 },
                height: { ideal: 240 },
                facingMode: 'user'
            },
            { // Minimum acceptable
                width: { min: 160 },
                height: { min: 120 },
                facingMode: 'user'
            },
            { // Any front-facing camera
                facingMode: 'user'
            },
            true // Default constraints
        ];

        for (let attempt = 0; attempt < retryAttempts; attempt++) {
            if (attempt > 0) {
                if (this.debugMode) {
                    console.log(`Camera initialization attempt ${attempt + 1}, waiting ${retryDelays[attempt]}ms`);
                }
                await new Promise(resolve => setTimeout(resolve, retryDelays[attempt]));
            }

            for (let constraintIndex = 0; constraintIndex < videoConstraints.length; constraintIndex++) {
                try {
                    const constraints = {
                        video: videoConstraints[constraintIndex],
                        audio: false
                    };

                    if (this.debugMode) {
                        console.log(`Trying video constraints:`, constraints);
                    }

                    const stream = await navigator.mediaDevices.getUserMedia(constraints);

                    videoElement.srcObject = stream;

                    // Wait for video metadata and check stream health (timeout from config)
                    await this.waitForVideoMetadata(videoElement, this.config.camera.timeout);

                    // Validate video stream health
                    const isValid = await this.validateVideoStream(videoElement);

                    if (!isValid.valid) {
                        stream.getTracks().forEach(track => track.stop());
                        if (this.debugMode) {
                            console.log(`Video stream validation failed: ${isValid.reason}`);
                        }
                        continue; // Try next constraint
                    }

                    if (this.debugMode) {
                        console.log('Camera initialized successfully with constraints:', constraints.video);
                    }

                    return stream;

                } catch (error) {
                    console.warn(`Camera initialization attempt ${attempt + 1}, constraint ${constraintIndex + 1} failed:`, error);

                    // More specific error messages for different failure scenarios
                    if (error.name === 'NotAllowedError') {
                        throw new Error('Camera access denied. Please grant camera permissions in your browser.');
                    } else if (error.name === 'NotFoundError') {
                        throw new Error('No camera found on this device. Please connect a camera.');
                    } else if (error.name === 'NotReadableError') {
                        throw new Error('Camera is in use by another application. Please close other camera apps.');
                    } else if (error.name === 'OverconstrainedError' || error.name === 'ConstraintNotSatisfiedError') {
                        // Continue to next constraint
                        continue;
                    } else if (error.name === 'TypeError') {
                        throw new Error('Invalid device state. Please restart your browser.');
                    } else {
                        // Try next constraint or attempt
                        continue;
                    }
                }
            }
        }

        // All attempts failed
        throw new Error('Failed to access webcam after multiple attempts. Please check camera permissions and hardware.');
    },

    /**
     * Wait for video metadata to load with timeout
     * @param {HTMLVideoElement} videoElement Video element
     * @param {number} timeout Timeout in milliseconds
     * @returns {Promise<void>}
     */
    waitForVideoMetadata(videoElement, timeout = 10000) {
        return new Promise((resolve, reject) => {
            const timeoutId = setTimeout(() => {
                reject(new Error('Video metadata loading timed out'));
            }, timeout);

            videoElement.onloadedmetadata = () => {
                clearTimeout(timeoutId);
                videoElement.play().then(() => {
                    resolve();
                }).catch(playError => {
                    console.warn('Error playing video:', playError);
                    resolve(); // Still resolve since metadata loaded
                });
            };

            videoElement.onerror = (error) => {
                clearTimeout(timeoutId);
                reject(error);
            };
        });
    },

    /**
     * Validate video stream health
     * @param {HTMLVideoElement} videoElement Video element
     * @returns {Promise<Object>} Validation result with {valid: boolean, reason: string}
     */
    async validateVideoStream(videoElement) {
        try {
            // Check if video element is playing
            if (videoElement.paused || videoElement.ended) {
                return { valid: false, reason: 'Video is paused or ended' };
            }

            // Verify video dimensions are valid
            if (videoElement.videoWidth <= 0 || videoElement.videoHeight <= 0) {
                return { valid: false, reason: 'Invalid video dimensions' };
            }

            // Check if video is actually playing (not frozen) by checking if currentTime is advancing
            const initialTime = videoElement.currentTime;
            await new Promise(resolve => setTimeout(resolve, 100)); // Wait 100ms
            const finalTime = videoElement.currentTime;

            if (initialTime === finalTime) {
                return { valid: false, reason: 'Video stream appears to be frozen' };
            }

            // Additional check: Verify that the video element has a valid srcObject
            if (!videoElement.srcObject) {
                return { valid: false, reason: 'Video element has no source stream' };
            }

            return { valid: true, reason: 'Video stream is healthy' };

        } catch (error) {
            console.error('Error validating video stream:', error);
            return { valid: false, reason: `Validation error: ${error.message}` };
        }
    },

    /**
     * Calculate smoothed quality score using weighted average of recent frames
     * @param {Object} validation - Validation result from validateFaceQuality
     * @returns {Object} Smoothed quality score object with score and trend
     */
    calculateSmoothedQuality(validation) {
        // Calculate current quality score from validation details
        let currentScore = 0;

        if (validation && validation.details) {
            const { sizeValid, centeringValid, confidenceValid, lightingValid } = validation.details;
            const validChecks = [sizeValid, centeringValid, confidenceValid, lightingValid].filter(Boolean).length;
            currentScore = (validChecks / 4) * 100; // Max 4 checks
        }

        // Add current score to buffer
        this.detectionBuffer.push({
            score: currentScore,
            timestamp: Date.now()
        });

        // Keep only the last maxBufferSize frames
        if (this.detectionBuffer.length > this.maxBufferSize) {
            this.detectionBuffer = this.detectionBuffer.slice(-this.maxBufferSize);
        }

        // Apply weighted averaging with exponential decay (recent frames have higher weight)
        // Weights: [0.22, 0.18, 0.16, 0.14, 0.12, 0.10, 0.06, 0.04, 0.02, 0.01] ~ sum to ~1.05
        const weights = [0.22, 0.18, 0.16, 0.14, 0.12, 0.10, 0.06, 0.04, 0.02, 0.01];
        let weightedSum = 0;
        let totalWeight = 0;

        for (let i = 0; i < this.detectionBuffer.length; i++) {
            const bufferIndex = this.detectionBuffer.length - 1 - i; // Process from newest to oldest
            const weightIndex = i < weights.length ? i : weights.length - 1;
            const weight = weights[weightIndex];
            weightedSum += this.detectionBuffer[bufferIndex].score * weight;
            totalWeight += weight;
        }

        const smoothedScore = totalWeight > 0 ? weightedSum / totalWeight : 0;

        // Calculate trend based on last few frames
        let trend = 'stable';
        if (this.detectionBuffer.length >= 3) {
            const recentScores = this.detectionBuffer.slice(-3).map(item => item.score);
            const firstScore = recentScores[0];
            const lastScore = recentScores[recentScores.length - 1];

            if (lastScore > firstScore + 5) {
                trend = 'improving';
            } else if (lastScore < firstScore - 5) {
                trend = 'declining';
            }
        }

        return {
            score: Math.round(smoothedScore * 100) / 100, // Round to 2 decimal places
            trend: trend
        };
    },

    /**
     * Update detection state using hysteresis logic
     * @param {Object} smoothedScore - Score from calculateSmoothedQuality
     * @param {Object} config - Configuration object
     * @returns {Object} Updated detection state
     */
    updateDetectionState(smoothedScore, config) {
        if (!config || !config.validation) {
            return { state: this.detectionState, confidence: 0 };
        }

        const validationConfig = config.validation;
        const minStableFrames = validationConfig.minStableFrames || 5;
        const hysteresisMargin = validationConfig.hysteresisMargin || 0.1;

        // Calculate threshold with hysteresis
        const baseThreshold = validationConfig.qualityThresholds ? validationConfig.qualityThresholds.good || 80 : 80;
        const currentScore = smoothedScore.score;

        if (this.detectionState === 'unstable' && currentScore > baseThreshold + (baseThreshold * hysteresisMargin)) {
            this.stableFrameCount++;
            if (this.stableFrameCount >= minStableFrames) {
                this.detectionState = 'stable';
            } else {
                this.detectionState = 'stabilizing';
            }
        } else if (this.detectionState === 'stable' && currentScore < baseThreshold - (baseThreshold * hysteresisMargin)) {
            this.detectionState = 'unstable';
            this.stableFrameCount = 0;
        } else if (this.detectionState === 'stabilizing' && currentScore < baseThreshold - (baseThreshold * hysteresisMargin)) {
            this.detectionState = 'unstable';
            this.stableFrameCount = 0;
        } else if (this.detectionState === 'stabilizing' && currentScore >= baseThreshold) {
            // If we're stabilizing and score is high enough, confirm as stable
            this.stableFrameCount++;
            if (this.stableFrameCount >= minStableFrames) {
                this.detectionState = 'stable';
            }
        } else {
            // Continue current state
            if (this.detectionState === 'stabilizing' && currentScore >= baseThreshold) {
                this.stableFrameCount++;
            } else if (this.detectionState === 'stable' && currentScore >= baseThreshold) {
                // Maintain stable state if score remains high
                this.stableFrameCount = Math.min(this.stableFrameCount + 1, minStableFrames);
            }
        }

        // Calculate confidence based on stability and score
        let confidence = 0;
        if (this.detectionState === 'stable') {
            confidence = Math.min(1, currentScore / 100);
        } else if (this.detectionState === 'stabilizing') {
            confidence = Math.min(0.7, currentScore / 100);
        } else {
            confidence = currentScore / 100;
        }

        return {
            state: this.detectionState,
            confidence: Math.round(confidence * 100) / 100
        };
    },

    /**
     * Get progressive feedback based on smoothed quality score
     * @param {number} score - Smoothed quality score (0-100)
     * @returns {Object} Feedback object with level, message, and color
     */
    getProgressiveFeedback(score) {
        if (score >= 80) {
            return {
                level: 'excellent',
                message: 'Excellent quality - ready to capture',
                color: 'green',
                cssClass: 'bg-emerald-500'
            };
        } else if (score >= 51) {
            return {
                level: 'good',
                message: 'Good quality - hold steady',
                color: 'blue',
                cssClass: 'bg-blue-500'
            };
        } else if (score >= 26) {
            return {
                level: 'fair',
                message: 'Fair quality - keep adjusting',
                color: 'yellow',
                cssClass: 'bg-yellow-500'
            };
        } else {
            return {
                level: 'poor',
                message: 'Poor quality - adjust position/lighting',
                color: 'red',
                cssClass: 'bg-red-500'
            };
        }
    },

    /**
     * Enable debug mode
     */
    enableDebugMode() {
        this.debugMode = true;
        localStorage.setItem('faceDebug', 'true');
        console.log('Debug mode enabled. Detailed logs will be shown in console.');
    },

    /**
     * Disable debug mode
     */
    disableDebugMode() {
        this.debugMode = false;
        localStorage.setItem('faceDebug', 'false');
        console.log('Debug mode disabled.');
    },

    /**
     * Reset detection buffer
     */
    resetDetectionBuffer() {
        this.detectionBuffer = [];
        this.detectionState = 'unstable';
        this.stableFrameCount = 0;
    },

    stopWebcam(stream) {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    },

    captureImage(videoElement) {
        const canvas = document.createElement('canvas');
        canvas.width = videoElement.videoWidth;
        canvas.height = videoElement.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(videoElement, 0, 0);
        return new Promise((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', 0.95);
        });
    },

    showLoading(message = 'Processing...') {
        const loader = document.getElementById('face-loading');
        if (loader) {
            loader.textContent = message;
            loader.style.display = 'block';
        }
    },

    hideLoading() {
        const loader = document.getElementById('face-loading');
        if (loader) {
            loader.style.display = 'none';
        }
    },

    showError(message) {
        const errorDiv = document.getElementById('face-error');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        } else {
            alert(message);
        }
    },

    showSuccess(message) {
        const successDiv = document.getElementById('face-success');
        if (successDiv) {
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 3000);
        } else {
            alert(message);
        }
    }
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = FaceUtils;
}
