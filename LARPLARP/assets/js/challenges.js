/* ============================================================
   CHALLENGES.JS — Quiz engine with timer & transitions
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // --- Quiz Data ---
  const quizData = [
    {
      question: "What will `typeof null` return in JavaScript?",
      choices: ["'null'", "'object'", "'undefined'", "'boolean'"],
      correct: 1
    },
    {
      question: "Which method is used to convert a JSON string to a JavaScript object?",
      choices: ["JSON.stringify()", "JSON.parse()", "JSON.convert()", "JSON.objectify()"],
      correct: 1
    },
    {
      question: "What does the `===` operator compare?",
      choices: ["Value only", "Type only", "Value and type", "Reference only"],
      correct: 2
    },
    {
      question: "Which of the following is NOT a primitive type in JavaScript?",
      choices: ["string", "boolean", "array", "symbol"],
      correct: 2
    },
    {
      question: "What will `console.log(0.1 + 0.2 === 0.3)` output?",
      choices: ["true", "false", "undefined", "NaN"],
      correct: 1
    }
  ];

  const letters = ['A', 'B', 'C', 'D'];
  
  // DOM elements
  const startScreen = document.getElementById('quiz-start');
  const activeScreen = document.getElementById('quiz-active');
  const resultScreen = document.getElementById('quiz-result');
  const startBtn = document.getElementById('quiz-start-btn');
  const restartBtn = document.getElementById('quiz-restart-btn');
  const questionEl = document.getElementById('quiz-question');
  const choicesEl = document.getElementById('quiz-choices');
  const progressEl = document.getElementById('quiz-progress');
  const questionNumEl = document.getElementById('quiz-question-num');
  const timerText = document.getElementById('timer-text');
  const timerFill = document.getElementById('timer-fill');

  let currentQuestion = 0;
  let score = 0;
  let timer = null;
  let timeLeft = 60;

  function showScreen(screen) {
    [startScreen, activeScreen, resultScreen].forEach(s => {
      if (s) s.style.display = 'none';
    });
    if (screen) screen.style.display = 'flex';
  }

  function startQuiz() {
    currentQuestion = 0;
    score = 0;
    showScreen(activeScreen);
    loadQuestion();
  }

  function loadQuestion() {
    if (currentQuestion >= quizData.length) {
      showResult();
      return;
    }

    const q = quizData[currentQuestion];
    
    // Update progress
    if (progressEl) progressEl.style.width = `${(currentQuestion / quizData.length) * 100}%`;
    if (questionNumEl) questionNumEl.textContent = `Question ${currentQuestion + 1}/${quizData.length}`;
    
    // Fade out then update
    if (questionEl) {
      questionEl.style.opacity = '0';
      questionEl.style.transform = 'translateY(10px)';
    }
    
    setTimeout(() => {
      if (questionEl) {
        questionEl.textContent = q.question;
        questionEl.style.transition = 'all 0.3s ease';
        questionEl.style.opacity = '1';
        questionEl.style.transform = 'translateY(0)';
      }

      if (choicesEl) {
        choicesEl.innerHTML = '';
        q.choices.forEach((choice, i) => {
          const btn = document.createElement('button');
          btn.className = 'quiz-choice';
          btn.innerHTML = `<span class="choice-letter">${letters[i]}</span><span>${choice}</span>`;
          btn.style.opacity = '0';
          btn.style.transform = 'translateX(-10px)';
          
          btn.addEventListener('click', () => selectAnswer(i, q.correct));
          choicesEl.appendChild(btn);

          // Stagger animation
          setTimeout(() => {
            btn.style.transition = 'all 0.3s ease';
            btn.style.opacity = '1';
            btn.style.transform = 'translateX(0)';
          }, i * 100);
        });
      }

      startTimer();
    }, 200);
  }

  function startTimer() {
    timeLeft = 60;
    updateTimer();
    
    if (timer) clearInterval(timer);
    timer = setInterval(() => {
      timeLeft--;
      updateTimer();
      
      if (timeLeft <= 0) {
        clearInterval(timer);
        // Auto-select wrong answer
        selectAnswer(-1, quizData[currentQuestion].correct);
      }
    }, 1000);
  }

  function updateTimer() {
    if (timerText) {
      timerText.textContent = timeLeft;
      timerText.className = 'timer-text';
      if (timeLeft <= 10) timerText.classList.add('danger');
      else if (timeLeft <= 20) timerText.classList.add('warning');
    }
    
    if (timerFill) {
      const dashValue = (timeLeft / 60) * 100;
      timerFill.style.strokeDasharray = `${dashValue}, 100`;
      
      if (timeLeft <= 10) timerFill.style.stroke = '#EF4444';
      else if (timeLeft <= 20) timerFill.style.stroke = '#F59E0B';
      else timerFill.style.stroke = 'var(--color-accent)';
    }
  }

  function selectAnswer(selected, correct) {
    clearInterval(timer);
    
    const choices = choicesEl ? choicesEl.querySelectorAll('.quiz-choice') : [];
    
    // Disable all choices
    choices.forEach(c => c.classList.add('disabled'));
    
    // Mark correct
    if (choices[correct]) choices[correct].classList.add('correct');
    
    // Mark selected if wrong
    if (selected !== correct && selected >= 0 && choices[selected]) {
      choices[selected].classList.add('incorrect');
    }
    
    if (selected === correct) score++;

    // Next question
    setTimeout(() => {
      currentQuestion++;
      loadQuestion();
    }, 1500);
  }

  function showResult() {
    if (progressEl) progressEl.style.width = '100%';
    showScreen(resultScreen);

    const resultIcon = document.getElementById('result-icon');
    const resultTitle = document.getElementById('result-title');
    const resultSubtitle = document.getElementById('result-subtitle');
    const resultScore = document.getElementById('result-score');
    const resultXP = document.getElementById('result-xp');

    const percentage = (score / quizData.length) * 100;
    
    if (resultScore) resultScore.textContent = `${score}/${quizData.length}`;
    if (resultXP) resultXP.textContent = `+${score * 50} XP earned`;

    if (percentage >= 80) {
      if (resultIcon) resultIcon.textContent = '🎉';
      if (resultTitle) resultTitle.textContent = 'Excellent!';
      if (resultSubtitle) resultSubtitle.textContent = 'Outstanding performance!';
    } else if (percentage >= 60) {
      if (resultIcon) resultIcon.textContent = '👏';
      if (resultTitle) resultTitle.textContent = 'Good Job!';
      if (resultSubtitle) resultSubtitle.textContent = 'Keep up the great work!';
    } else if (percentage >= 40) {
      if (resultIcon) resultIcon.textContent = '💪';
      if (resultTitle) resultTitle.textContent = 'Not Bad!';
      if (resultSubtitle) resultSubtitle.textContent = 'Room for improvement.';
    } else {
      if (resultIcon) resultIcon.textContent = '📚';
      if (resultTitle) resultTitle.textContent = 'Keep Learning!';
      if (resultSubtitle) resultSubtitle.textContent = 'Practice makes perfect.';
    }
  }

  // --- Event Listeners ---
  if (startBtn) startBtn.addEventListener('click', startQuiz);
  if (restartBtn) restartBtn.addEventListener('click', () => {
    showScreen(startScreen);
  });

  // --- Challenge Start Buttons (demo) ---
  document.querySelectorAll('.challenge-start').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.textContent = 'Starting...';
      btn.style.opacity = '0.7';
      setTimeout(() => {
        btn.textContent = '✓ In Progress';
        btn.style.background = 'linear-gradient(135deg, #22C55E, #16A34A)';
        btn.style.opacity = '1';
      }, 1000);
    });
  });
});
