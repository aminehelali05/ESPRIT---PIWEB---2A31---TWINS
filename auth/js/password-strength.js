document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const strengthContainer = document.getElementById('passwordStrengthContainer');
    const bars = [
        document.getElementById('strengthBar1'),
        document.getElementById('strengthBar2'),
        document.getElementById('strengthBar3'),
        document.getElementById('strengthBar4')
    ];
    const text = document.getElementById('strengthText');

    passwordInput.addEventListener('input', () => {
        const password = passwordInput.value;
        if (password.length > 0) {
            strengthContainer.style.display = 'block';
        } else {
            strengthContainer.style.display = 'none';
        }

        const strength = calculateStrength(password);
        updateMeter(strength);
    });

    function calculateStrength(password) {
        let score = 0;
        if (password.length > 8) score++;
        if (password.match(/[A-Z]/)) score++;
        if (password.match(/[0-9]/)) score++;
        if (password.match(/[^A-Za-z0-9]/)) score++;
        return score;
    }

    function updateMeter(score) {
        // Reset
        bars.forEach(bar => bar.className = 'h-full w-1/4 rounded-full bg-zinc-700 transition-colors duration-300');

        const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-emerald-500'];
        const texts = ['Weak', 'Fair', 'Good', 'Strong'];

        // Fill bars based on score
        for (let i = 0; i < 4; i++) {
            if (i < score) {
                // Determine color: if score is low, use red/orange. If high, all bars get the 'strong' color
                // Actually, let's color active bars with the color corresponding to current score
                // Example: Score 2 (Fair) -> First 2 bars are Orange

                bars[i].classList.remove('bg-zinc-700');
                bars[i].classList.add(colors[Math.min(score, 3)]); // Use color of current level
            }
        }

        text.textContent = texts[Math.min(score, 3)] || 'Too Short';
        text.className = `text-xs text-right font-medium ${score === 0 ? 'text-zinc-500' :
            score === 1 ? 'text-red-500' :
                score === 2 ? 'text-orange-500' :
                    score === 3 ? 'text-yellow-500' : 'text-emerald-500'}`;
    }
});
