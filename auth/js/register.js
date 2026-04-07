// COMMENT 1 FIX: Enhanced register.js with face enrollment integration

// COMMENT 1 FIX: Enhanced register.js with face enrollment integration and map picker

document.getElementById('registerForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('registerBtn');
    const originalText = btn.innerText;

    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (password !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Oops!',
            text: 'Passwords do not match.',
            background: '#1f1f23',
            color: '#fff'
        });
        return;
    }

    // UI Loading
    btn.innerHTML = `<i data-lucide="loader-2" class="animate-spin inline mr-2"></i> Creating Account...`;
    lucide.createIcons();
    btn.disabled = true;

    // Collect Data
    const data = {
        name: document.getElementById('firstName').value + ' ' + document.getElementById('lastName').value,
        email: document.getElementById('email').value,
        password: password,
        country: document.getElementById('country').value,

        // Map Data
        city: document.getElementById('city')?.value || '',
        address: document.getElementById('fullAddress')?.value || '',
        latitude: document.getElementById('latitude')?.value || '',
        longitude: document.getElementById('longitude')?.value || ''
    };

    try {
        const result = await Auth.register(data);

        if (result.success) {
            const userId = result.data?.user_id || result.data?.id;
            const enrollmentToken = result.data?.enrollment_token;

            // Check if we have pending face data to upload
            if (userId && typeof FaceCapture !== 'undefined' && FaceCapture.faceDescriptor) {
                btn.innerHTML = `<i data-lucide="scan-face" class="animate-pulse inline mr-2"></i> Uploading Face Data...`;

                try {
                    // Pass enrollment token for secure registration enrollment
                    await FaceCapture.uploadPendingData(userId, {
                        redirectOnComplete: false,
                        enrollmentToken: enrollmentToken
                    });
                    // Success with Face
                    Swal.fire({
                        icon: 'success',
                        title: 'Account Created & Face Enrolled!',
                        text: 'You can now login with your face or password.',
                        background: '#1f1f23',
                        color: '#fff',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => window.location.href = 'login.html');
                    return; // Done
                } catch (faceError) {
                    console.error("Face upload failed:", faceError);
                    // Failed face but account created
                    Swal.fire({
                        icon: 'warning',
                        title: 'Account Created',
                        text: 'Account created, but face enrollment failed. You can try again in your profile.',
                        background: '#1f1f23',
                        color: '#fff'
                    }).then(() => window.location.href = 'login.html');
                    return;
                }
            }

            // No face data, standard success
            Swal.fire({
                icon: 'success',
                title: 'Account Created!',
                text: 'Please login to continue.',
                confirmButtonText: 'Go to Login',
                background: '#1f1f23',
                color: '#fff'
            }).then(() => {
                window.location.href = 'login.html';
            });

        } else {
            console.error('Registration failed:', result);
            throw new Error(result.message || 'Registration failed');
        }

    } catch (error) {
        btn.innerHTML = originalText;
        btn.disabled = false;

        Swal.fire({
            icon: 'error',
            title: 'Registration Failed',
            text: error.message || 'Something went wrong.',
            background: '#1f1f23',
            color: '#fff'
        });
    }
});

// Face Enroll Button Click
document.getElementById('enrollFaceBtn')?.addEventListener('click', () => {
    const modal = document.getElementById('face-capture-modal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');

        // Initialize if first time
        if (typeof FaceCapture !== 'undefined' && !FaceCapture.stream) {
            FaceCapture.initialize().catch(err => {
                console.error("Init failed:", err);
                Swal.fire({ icon: 'error', title: 'Camera Error', text: 'Could not access camera.' });
            });
        }
    }
});

// Standard modal handlers
document.getElementById('cancel-face-capture')?.addEventListener('click', () => {
    document.getElementById('face-capture-modal').style.display = 'none';
    if (typeof FaceCapture !== 'undefined') FaceCapture.cleanup();
});

document.getElementById('capture-face-btn')?.addEventListener('click', () => {
    FaceCapture.captureFace();
});
