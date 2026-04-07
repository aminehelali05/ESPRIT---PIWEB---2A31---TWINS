const FaceConfig = {
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
        verificationThreshold: 0.58,
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
