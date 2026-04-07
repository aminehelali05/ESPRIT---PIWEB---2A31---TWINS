document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    const originalText = btn.innerText;

    // UI Loading State
    btn.innerHTML = `<i data-lucide="loader-2" class="animate-spin inline mr-2"></i> Signing in...`;
    lucide.createIcons();
    btn.disabled = true;

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const token = document.getElementById('gamified_token')?.value;

    const result = await Auth.login(email, password, token);

    if (result.success) {
        Swal.fire({
            icon: 'success',
            title: 'Welcome back!',
            text: 'Redirecting to dashboard...',
            timer: 1500,
            showConfirmButton: false,
            background: '#1f1f23',
            color: '#fff'
        }).then(() => {

            window.location.href = '../dashboard/index.html';

        });
    } else {
        btn.innerText = originalText;
        btn.disabled = false;

        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: result.message || 'Invalid credentials',
            background: '#1f1f23',
            color: '#fff'
        });
    }
});
