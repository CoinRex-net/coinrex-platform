/**
 * TaskHub Premium — Single-Card Focus Experience
 * Handles: Day stepper, hero card transitions, timer overlay, quiz, learning gate
 * 
 * v2.1 — Auto-load quiz after validation + Review Material button in tip section
 */
(function() {
    'use strict';

    const BASE_URL = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
    const submitUrl = BASE_URL + '/api/submit_taskhub_task.php';
    const mysteryUrl = BASE_URL + '/api/claim_mystery_box.php';


    const modal = document.getElementById('taskhubModal');
    const modalTitle = document.getElementById('taskhubModalTitle');
    const modalMessage = document.getElementById('taskhubModalMessage');
    const modalAction = document.getElementById('taskhubModalAction');
    const greetingModal = document.getElementById('taskhubGreetingModal');
    const mysteryModal = document.getElementById('taskhubMysteryModal');

    // ============================================================
    // UTILITY FUNCTIONS
    // ============================================================
    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        modalAction.onclick = null;
    }

    function showModal(title, message, onConfirm) {
        if (!modal || !modalTitle || !modalMessage || !modalAction) return;
        modalTitle.textContent = title;
        modalMessage.textContent = message;
        modal.hidden = false;
        modalAction.onclick = function() {
            closeModal();
            if (typeof onConfirm === 'function') onConfirm();
        };
    }

    document.querySelectorAll('[data-modal-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    function formatDuration(totalSeconds) {
        const seconds = Math.max(0, Number(totalSeconds) || 0);
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;
        const parts = [];
        if (hours > 0) parts.push(hours + 'h');
        if (hours > 0 || minutes > 0) parts.push(minutes + 'm');
        parts.push(remainingSeconds + 's');
        return parts.join(' ');
    }

    function formatCountdown(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    async function postForm(body) {
        const response = await fetch(submitUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(body),
        });
        return response.json();
    }

    // ============================================================
    // LEARNING TOAST — brief notification after validation
    // ============================================================
    function showLearningToast(message) {
        const existing = document.querySelector('.th-learning-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'th-learning-toast';
        toast.setAttribute('role', 'status');
        toast.innerHTML = message;
        document.body.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(-50%) translateY(0)';
            toast.style.opacity = '1';
        });

        // Auto-remove after 3 seconds
        setTimeout(() => {
            toast.style.transform = 'translateX(-50%) translateY(-20px)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        }, 3000);
    }

    // ============================================================
    // DAY STEPPER
    // ============================================================
    const dayDots = Array.from(document.querySelectorAll('[data-th-day]'));

    const dayPanels = Array.from(document.querySelectorAll('[data-th-panel]'));

    function selectDay(dayNumber) {
        // Update dots
        dayDots.forEach((dot) => {
            const isActive = Number(dot.dataset.thDay) === dayNumber;
            dot.classList.toggle('is-active', isActive);
            dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
            const step = dot.closest('[data-th-step]');
            if (step) {
                step.classList.toggle('active', isActive);
            }
        });

        // Update panels
        dayPanels.forEach((panel) => {
            panel.hidden = Number(panel.dataset.thPanel) !== dayNumber;
        });

        // Re-init quiz blocks in the newly visible panel
        const visiblePanel = dayPanels.find(p => !p.hidden);
        if (visiblePanel) {
            const quizBlocks = visiblePanel.querySelectorAll('[data-quiz-block]:not([hidden])');
            quizBlocks.forEach(initQuizBlock);
        }
    }

    dayDots.forEach((dot) => {
        dot.addEventListener('click', function() {
            if (this.disabled) return;
            selectDay(Number(this.dataset.thDay));
        });
    });

    // ============================================================
    // HERO CARD — TASK TRANSITIONS
    // ============================================================
    function transitionToTask(container, newHtml) {
        const content = container.querySelector('.th-task-content');
        if (!content) {
            container.innerHTML = newHtml;
            return;
        }
        content.classList.add('is-exiting');
        setTimeout(() => {
            container.innerHTML = newHtml;
        }, 300);
    }

    // ============================================================
    // TIMER OVERLAY (for day/task lock countdowns)
    // ============================================================
    function updateTimerOverlay(heroCard) {
        const overlay = heroCard.querySelector('[data-th-timer]');
        if (!overlay) return;

        const countEl = overlay.querySelector('[data-th-timer-count]');
        const subEl = overlay.querySelector('[data-th-timer-sub]');
        if (!countEl) return;

        let seconds = Number(overlay.dataset.thTimer || 0);
        if (seconds <= 0) {
            overlay.remove();
            return;
        }

        seconds -= 1;
        overlay.dataset.thTimer = String(seconds);
        countEl.textContent = formatDuration(seconds);

        if (subEl) {
            subEl.textContent = seconds > 0 ? 'until next task unlocks' : 'Unlocked!';
        }

        if (seconds <= 0) {
            setTimeout(() => {
                overlay.remove();
                location.reload();
            }, 1000);
        }
    }

    // ============================================================
    // LEARNING GATE — Simplified (instant validation)
    // ============================================================
    const learningApi = {
        start: BASE_URL + '/api/learning/start_session.php',
        validate: BASE_URL + '/api/learning/validate_session.php',
    };

    function initLearningGate(gate) {
        const opener = gate.querySelector('[data-learning-open]');
        const status = gate.querySelector('[data-learning-status]');
        const taskKey = gate.dataset.taskKey || '';
        const learningUrl = opener ? opener.getAttribute('href') : '';
        if (!opener || !status || !taskKey) return;

        if (gate.dataset.learningOpened === '1') {
            // Already validated — replace with Review Material button and auto-show quiz
            replaceOpenWithReviewBtn(gate, taskKey);
            autoShowQuiz(gate, taskKey);
            return;
        }

        opener.addEventListener('click', function(e) {
            e.preventDefault();
            startBackendSession(gate, taskKey, learningUrl);
        });
    }

    async function startBackendSession(gate, taskKey, learningUrl) {
        const status = gate.querySelector('[data-learning-status]');
        const opener = gate.querySelector('[data-learning-open]');

        if (status) {
            status.textContent = '⏳ Opening learning page...';
            status.style.color = 'var(--th-primary)';
            status.style.borderColor = 'rgba(29, 78, 216, 0.3)';
            status.style.background = 'rgba(29, 78, 216, 0.08)';
        }
        if (opener) opener.disabled = true;

        let sessionToken = null;
        try {
            const response = await fetch(learningApi.start, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({ task_key: taskKey }),
            });
            const data = await response.json();
            if (data.success) {
                sessionToken = data.session_token;
            }
        } catch (e) {
            if (status) {
                status.textContent = '⚠ Failed to start session. Please try again.';
                status.style.color = 'var(--th-red)';
                status.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                status.style.background = 'rgba(239, 68, 68, 0.08)';
            }
            if (opener) opener.disabled = false;
            return;
        }

        if (!sessionToken) {
            if (status) {
                status.textContent = '⚠ Failed to create learning session.';
                status.style.color = 'var(--th-red)';
                status.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                status.style.background = 'rgba(239, 68, 68, 0.08)';
            }
            if (opener) opener.disabled = false;
            return;
        }

        gate.dataset.learnSessionToken = sessionToken;

        // Validate immediately (backend requires only 1 second)
        await validateLearning(gate, taskKey, sessionToken);

        // Show a brief success toast instead of auto-opening a tab
        showLearningToast('✅ Learning validated! Complete the quiz below.');

    }

    async function validateLearning(gate, taskKey, sessionToken) {
        if (!sessionToken) return;

        try {
            const response = await fetch(learningApi.validate, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({ task_key: taskKey, session_token: sessionToken }),
            });
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Failed to validate learning.');
            }

            gate.dataset.learningOpened = '1';
            gate.classList.remove('is-reading', 'is-locked', 'is-paused');
            gate.classList.add('is-validated');

            const status = gate.querySelector('[data-learning-status]');
            if (status) {
                status.textContent = 'Learning validated ✓';
                status.style.color = 'var(--th-green)';
                status.style.borderColor = 'rgba(34, 197, 94, 0.3)';
                status.style.background = 'rgba(34, 197, 94, 0.08)';
            }

            // IMPORTANT: The gate itself has data-task-key, so closest() returns the gate.
            // We need the parent container to find sibling quiz blocks.
            const parentContainer = gate.parentElement;
            const quizBlock = parentContainer ? parentContainer.querySelector('[data-quiz-block]') : null;


            if (quizBlock) {
                // Replace "Open & Validate" with "Review Material" button
                replaceOpenWithReviewBtn(gate, taskKey);
                // Auto-show quiz questions immediately
                quizBlock.hidden = false;
                initQuizBlock(quizBlock);
                document.dispatchEvent(new CustomEvent('learning-complete', { target: quizBlock }));
            } else {
                const opener = gate.querySelector('[data-learning-open]');
                if (opener) {
                    const readyBadge = document.createElement('span');
                    readyBadge.className = 'th-learning-btn is-validated';
                    readyBadge.style.cssText = 'background:rgba(34,197,94,0.12);color:var(--th-green);border:1px solid rgba(34,197,94,0.3);cursor:default;';
                    readyBadge.innerHTML = '✅ Ready to Submit';
                    opener.parentNode.replaceChild(readyBadge, opener);
                }
            }
        } catch (e) {
            const status = gate.querySelector('[data-learning-status]');
            if (status) {
                status.textContent = '⚠ ' + (e.message || 'Validation failed. Please try again.');
                status.style.color = 'var(--th-red)';
                status.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                status.style.background = 'rgba(239, 68, 68, 0.08)';
            }

            let retryBtn = gate.querySelector('[data-learn-retry]');
            if (!retryBtn) {
                retryBtn = document.createElement('button');
                retryBtn.type = 'button';
                retryBtn.className = 'th-learning-btn';
                retryBtn.setAttribute('data-learn-retry', '');
                retryBtn.innerHTML = '🔄 Retry Validation';
                retryBtn.style.marginLeft = '8px';
                retryBtn.addEventListener('click', function() {
                    this.remove();
                    validateLearning(gate, taskKey, sessionToken);
                });
                const label = gate.querySelector('.th-learning-label');
                if (label && label.nextSibling) {
                    gate.insertBefore(retryBtn, label.nextSibling);
                } else {
                    gate.appendChild(retryBtn);
                }
            }
        }
    }

    /**
     * Replaces the "Open & Validate" button with a "Review Material" link
     * and a fallback "Take Quiz" button in case auto-load fails.
     */
    function replaceOpenWithReviewBtn(gate, taskKey) {
        const opener = gate.querySelector('[data-learning-open]');
        if (!opener) return;
        const learningUrl = opener.getAttribute('href') || '';
        const sessionToken = gate.dataset.learnSessionToken || '';

        // Build the review material URL with session params
        let reviewHref = '#';
        if (learningUrl) {
            const separator = learningUrl.indexOf('?') >= 0 ? '&' : '?';
            reviewHref = learningUrl + separator + 'th_session=' + encodeURIComponent(sessionToken) + '&th_task_key=' + encodeURIComponent(taskKey);
        }

        // Create a wrapper to hold both buttons
        const wrapper = document.createElement('div');
        wrapper.className = 'th-learning-btn-group';
        wrapper.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;';

        // Review Material link
        const reviewBtn = document.createElement('a');
        reviewBtn.className = 'th-learning-btn is-validated';
        reviewBtn.setAttribute('data-review-material', '');
        reviewBtn.innerHTML = '📖 Review Material';
        reviewBtn.target = '_blank';
        reviewBtn.rel = 'noopener noreferrer';
        reviewBtn.href = reviewHref;
        if (!learningUrl) {
            reviewBtn.style.cursor = 'default';
            reviewBtn.style.opacity = '0.5';
        }
        wrapper.appendChild(reviewBtn);

        // Fallback "Take Quiz" button — shows quiz if auto-load didn't work
        const quizFallbackBtn = document.createElement('button');
        quizFallbackBtn.type = 'button';
        quizFallbackBtn.className = 'th-learning-btn is-validated';
        quizFallbackBtn.setAttribute('data-quiz-fallback', '');
        quizFallbackBtn.innerHTML = '📝 Take Quiz';
        quizFallbackBtn.addEventListener('click', function() {
            const parentContainer = gate.parentElement;
            const quizBlock = parentContainer ? parentContainer.querySelector('[data-quiz-block]') : null;
            if (quizBlock) {
                quizBlock.hidden = false;
                initQuizBlock(quizBlock);
                document.dispatchEvent(new CustomEvent('learning-complete', { target: quizBlock }));
            }
        });

        wrapper.appendChild(quizFallbackBtn);

        opener.parentNode.replaceChild(wrapper, opener);
    }


    /**
     * Auto-shows the quiz block if learning was already validated on page load.
     */
    function autoShowQuiz(gate, taskKey) {
        const parentContainer = gate.parentElement;
        const quizBlock = parentContainer ? parentContainer.querySelector('[data-quiz-block]') : null;
        if (quizBlock) {
            quizBlock.hidden = false;
            initQuizBlock(quizBlock);
            document.dispatchEvent(new CustomEvent('learning-complete', { target: quizBlock }));
        }
    }


    // ============================================================
    // QUIZ SYSTEM v2.1 — Interactive with Wrong/Right Feedback + Review Material link
    // ============================================================
    function initQuizBlock(quizBlock) {
        if (!quizBlock || quizBlock.dataset.quizInitialized === '1') return;
        quizBlock.dataset.quizInitialized = '1';

        const questions = Array.from(quizBlock.querySelectorAll('[data-quiz-question]'));
        const progressFill = quizBlock.querySelector('[data-quiz-progress-fill]');
        const progressLabel = quizBlock.querySelector('[data-quiz-progress-label]');
        const progressPct = quizBlock.querySelector('[data-quiz-progress-pct]');
        const totalQuestions = questions.length;
        const minScoreAttr = Number(quizBlock.dataset.minScore || 0);
        const requiredScore = (Number.isFinite(minScoreAttr) && minScoreAttr > 0) ? minScoreAttr : totalQuestions;
        let currentIndex = 0;
        let answers = new Array(totalQuestions).fill(-1);
        let questionCorrect = new Array(totalQuestions).fill(false); // Track if each question was answered correctly

        // Find the learning gate to get the review material URL
        const row = quizBlock.closest('[data-task-key]');
        const gate = row ? row.querySelector('[data-learning-gate]') : null;
        const reviewBtn = gate ? gate.querySelector('[data-review-material]') : null;
        const reviewUrl = reviewBtn ? reviewBtn.getAttribute('href') || '' : '';

        // Create centered submit button + score preview
        const submitWrap = document.createElement('div');
        submitWrap.className = 'th-quiz-submit-wrap';
        submitWrap.innerHTML = `
            <button type="button" class="th-quiz-submit-btn" data-quiz-submit disabled>
                Submit Quiz <span class="btn-arrow">→</span>
            </button>
            <div class="th-quiz-score-preview" data-quiz-score-preview>0/${totalQuestions} answered</div>
        `;
        quizBlock.appendChild(submitWrap);

        const submitBtn = submitWrap.querySelector('[data-quiz-submit]');
        const scorePreview = submitWrap.querySelector('[data-quiz-score-preview]');

        function updateScorePreview() {
            const answered = answers.filter(a => a >= 0).length;
            const correctCount = questionCorrect.filter(c => c === true).length;
            const passed = correctCount >= requiredScore;
            scorePreview.textContent = correctCount + '/' + totalQuestions + ' correct (need ' + requiredScore + ') ' + (passed ? '✅' : '');
            submitBtn.disabled = answered < totalQuestions || !passed;
        }

        function showQuestion(index) {
            questions.forEach((q, i) => {
                q.hidden = i !== index;
            });
            const pct = totalQuestions > 0 ? Math.round(((index) / totalQuestions) * 100) : 0;
            if (progressFill) progressFill.style.width = pct + '%';
            if (progressLabel) progressLabel.textContent = 'Question ' + (index + 1) + ' of ' + totalQuestions;
            if (progressPct) progressPct.textContent = pct + '%';
        }

        // Handle choice selection with wrong/right feedback
        questions.forEach((q, qIndex) => {
            const choices = q.querySelectorAll('[data-choice]');
            choices.forEach((choice) => {
                const input = choice.querySelector('input[type="radio"]');
                if (!input) return;

                choice.addEventListener('click', function(e) {
                    // If this question was already answered correctly, prevent re-selection
                    if (questionCorrect[qIndex]) return;

                    // Clear previous selection state
                    q.querySelectorAll('[data-choice]').forEach((c) => {
                        c.classList.remove('is-selected', 'is-correct', 'is-wrong');
                        const marker = c.querySelector('.th-quiz-choice-marker');
                        if (marker) {
                            marker.innerHTML = marker.dataset.letter || marker.textContent;
                        }
                        const feedbackIcon = c.querySelector('.th-choice-feedback');
                        if (feedbackIcon) feedbackIcon.remove();
                    });

                    // Mark this choice as selected
                    choice.classList.add('is-selected');
                    input.checked = true;

                    const isCorrect = input.dataset.correct === '1';
                    const marker = choice.querySelector('.th-quiz-choice-marker');
                    const letter = marker ? marker.textContent : '?';

                    if (isCorrect) {
                        // RIGHT ANSWER
                        choice.classList.remove('is-selected');
                        choice.classList.add('is-correct');
                        if (marker) {
                            marker.dataset.letter = letter;
                            marker.innerHTML = '✓';
                        }
                        // Add feedback icon
                        const feedback = document.createElement('span');
                        feedback.className = 'th-choice-feedback th-choice-correct';
                        feedback.textContent = '✓ Correct!';
                        choice.appendChild(feedback);

                        answers[qIndex] = Number(input.value);
                        questionCorrect[qIndex] = true;
                        updateScorePreview();

                        // Auto-advance to next question after 400ms
                        setTimeout(() => {
                            if (qIndex < totalQuestions - 1) {
                                showQuestion(qIndex + 1);
                            } else {
                                const pct = 100;
                                if (progressFill) progressFill.style.width = pct + '%';
                                if (progressLabel) progressLabel.textContent = 'All questions answered correctly! 🎉';
                                if (progressPct) progressPct.textContent = '100%';
                            }
                        }, 400);
                    } else {
                        // WRONG ANSWER
                        choice.classList.remove('is-selected');
                        choice.classList.add('is-wrong');
                        if (marker) {
                            marker.dataset.letter = letter;
                            marker.innerHTML = '✗';
                        }
                        // Add feedback icon
                        const feedback = document.createElement('span');
                        feedback.className = 'th-choice-feedback th-choice-wrong';
                        feedback.textContent = '✗ Try Again';
                        choice.appendChild(feedback);

                        // Show "Review Material" prompt with a link to the learning page
                        let retryPrompt = q.querySelector('[data-retry-prompt]');
                        if (!retryPrompt) {
                            retryPrompt = document.createElement('div');
                            retryPrompt.className = 'th-quiz-retry-prompt';
                            retryPrompt.setAttribute('data-retry-prompt', '');
                            if (reviewUrl) {
                                retryPrompt.innerHTML = '💡 <strong>Not quite right.</strong> <a href="' + reviewUrl.replace(/&/g, '&') + '" target="_blank" rel="noopener noreferrer" style="color:var(--th-primary-light);text-decoration:underline;font-weight:600;">Review the material</a> and try again.';
                            } else {
                                retryPrompt.innerHTML = '💡 <strong>Not quite right.</strong> Review the material and try again.';
                            }
                            q.appendChild(retryPrompt);
                        }

                        // Reset after 1.5s so user can try again
                        setTimeout(() => {
                            choice.classList.remove('is-wrong');
                            if (marker) {
                                marker.innerHTML = marker.dataset.letter || letter;
                            }
                            const fb = choice.querySelector('.th-choice-feedback');
                            if (fb) fb.remove();
                        }, 1500);
                    }
                });
            });
        });

        // Handle submit button click
        submitBtn.addEventListener('click', function() {
            if (this.disabled) return;
            const row = quizBlock.closest('[data-task-key]');
            if (!row) return;
            const mainSubmitBtn = row.querySelector('[data-submit-task]');
            if (mainSubmitBtn) {
                mainSubmitBtn.click();
            }
        });

        showQuestion(0);
        updateScorePreview();
    }

    // Initialize any visible quiz blocks on page load
    document.querySelectorAll('[data-quiz-block]:not([hidden])').forEach(initQuizBlock);

    // Listen for learning-complete event to init quiz blocks
    document.addEventListener('learning-complete', function(e) {
        const quizBlock = e.target;
        if (quizBlock && quizBlock.matches('[data-quiz-block]')) {
            initQuizBlock(quizBlock);
        }
    });

    // ============================================================
    // INIT LEARNING GATES
    // ============================================================
    document.querySelectorAll('[data-learning-gate]').forEach(initLearningGate);

    // ============================================================
    // SUCCESS ANIMATION — Fullscreen centered modal with confetti
    // ============================================================
    function triggerSuccessAnimation(heroCard, message) {
        if (!heroCard) return;

        // Create a fullscreen fixed overlay that covers the entire viewport
        const fullscreenOverlay = document.createElement('div');
        fullscreenOverlay.className = 'th-success-fullscreen';
        fullscreenOverlay.style.cssText = 'position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(2,6,23,0.85);backdrop-filter:blur(8px);z-index:9999;animation:thFadeIn 0.3s ease;';

        // Centered modal card
        const modalCard = document.createElement('div');
        modalCard.className = 'th-success-modal-card';
        modalCard.style.cssText = 'position:relative;background:linear-gradient(165deg,rgba(10,18,34,0.98),rgba(8,16,28,0.98));border:1px solid rgba(29,78,216,0.3);border-radius:24px;padding:48px 40px;text-align:center;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(2,6,23,0.5),0 0 80px rgba(29,78,216,0.15);animation:thSuccessPop 0.5s cubic-bezier(0.175,0.885,0.32,1.275);';

        modalCard.innerHTML = `
            <div style="font-size:72px;line-height:1;margin-bottom:16px;">✅</div>
            <div style="font-size:24px;font-weight:800;color:#fff;margin-bottom:8px;">Task Complete!</div>
            <div style="font-size:15px;color:var(--th-text-muted);line-height:1.6;max-width:300px;margin:0 auto;">${message || 'Task completed successfully!'}</div>
        `;

        fullscreenOverlay.appendChild(modalCard);
        document.body.appendChild(fullscreenOverlay);

        // Floating sparkle stars around the modal
        const sparkleEmojis = ['✨', '⭐', '🌟', '💫', '⚡'];
        for (let i = 0; i < 12; i++) {
            const sparkle = document.createElement('div');
            sparkle.className = 'th-success-sparkle';
            sparkle.textContent = sparkleEmojis[Math.floor(Math.random() * sparkleEmojis.length)];
            sparkle.style.left = (Math.random() * 80 + 10) + '%';
            sparkle.style.top = (Math.random() * 80 + 10) + '%';
            sparkle.style.animationDelay = (Math.random() * 1.5) + 's';
            sparkle.style.animationDuration = (Math.random() * 1 + 1) + 's';
            sparkle.style.fontSize = (Math.random() * 12 + 14) + 'px';
            fullscreenOverlay.appendChild(sparkle);
        }

        // Confetti burst — full viewport
        const confettiContainer = document.createElement('div');
        confettiContainer.className = 'th-confetti-burst';
        confettiContainer.style.cssText = 'position:fixed;inset:0;pointer-events:none;overflow:hidden;z-index:10000;';
        document.body.appendChild(confettiContainer);

        const colors = ['#4ade80', '#22d3ee', '#fbbf24', '#f472b6', '#a78bfa', '#fb923c', '#60a5fa', '#34d399', '#d946ef', '#f97316'];
        for (let i = 0; i < 100; i++) {
            const piece = document.createElement('div');
            piece.className = 'taskhub-confetti-piece';
            piece.style.left = Math.random() * 100 + '%';
            piece.style.top = '-10px';
            piece.style.background = colors[Math.floor(Math.random() * colors.length)];
            piece.style.animationDelay = Math.random() * 2.5 + 's';
            piece.style.animationDuration = (Math.random() * 2 + 2) + 's';
            piece.style.width = (Math.random() * 10 + 4) + 'px';
            piece.style.height = (Math.random() * 10 + 4) + 'px';
            piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
            piece.style.opacity = (Math.random() * 0.4 + 0.6).toString();
            confettiContainer.appendChild(piece);
        }

        // Auto-reload after animation
        setTimeout(() => {
            fullscreenOverlay.remove();
            confettiContainer.remove();
            location.reload();
        }, 3000);

    }


    // ============================================================
    // TASK SUBMISSION
    // ============================================================
    document.querySelectorAll('[data-submit-task]').forEach((btn) => {
        btn.addEventListener('click', async function() {
            if (this.disabled) return;

            const row = this.closest('[data-task-key]');
            if (!row) return;
            const taskKey = row.dataset.taskKey;
            const verificationMode = row.dataset.verificationMode;

            const payload = { task_key: taskKey };

            const walletInput = row.querySelector('.task-wallet-input');
            if (walletInput && walletInput.value.trim()) {
                payload.wallet_address = walletInput.value.trim();
            }

            const proofInput = row.querySelector('.task-proof-input');
            if (proofInput && proofInput.value.trim()) {
                payload.proof = proofInput.value.trim();
            }

            const xHandle = row.querySelector('[data-x-handle]');
            const telegramHandle = row.querySelector('[data-telegram-handle]');
            if (xHandle && xHandle.value.trim()) payload.x_handle = xHandle.value.trim();
            if (telegramHandle && telegramHandle.value.trim()) payload.telegram_handle = telegramHandle.value.trim();

            const sharePlatform = row.querySelector('[data-share-platform]');
            const shareProofUrl = row.querySelector('[data-share-proof-url]');
            if (sharePlatform && sharePlatform.value) payload.platform = sharePlatform.value;
            if (shareProofUrl && shareProofUrl.value.trim()) payload.proof = shareProofUrl.value.trim();

            // Only collect quiz answers for quiz verification mode
            if (verificationMode === 'quiz') {
                const quizBlock = row.querySelector('[data-quiz-block]');
                if (quizBlock) {
                    const questions = Array.from(quizBlock.querySelectorAll('[data-quiz-question]'));
                    const answers = [];
                    let allAnswered = true;
                    questions.forEach((q) => {
                        const selected = q.querySelector('[data-choice].is-correct input, [data-choice].is-selected input');
                        if (selected) {
                            answers.push(Number(selected.value));
                        } else {
                            allAnswered = false;
                            answers.push(-1);
                        }
                    });
                    if (!allAnswered) {
                        showModal('Incomplete Quiz', 'Please answer all questions correctly before submitting.');
                        return;
                    }
                    payload.answers_json = JSON.stringify(answers);
                }
            }

            const isCheckIn = taskKey && taskKey.includes('_check_in');

            this.disabled = true;
            this.textContent = 'Submitting...';

            try {
                const data = await postForm(payload);

                if (data.success) {
                    // Show success animation instead of modal
                    const heroCard = row.closest('.th-hero-card');
                    triggerSuccessAnimation(heroCard, data.message || 'Task completed successfully!');
                    
                    if (isCheckIn && greetingModal) {
                        const dayNumber = taskKey ? taskKey.replace('day', '').replace('_check_in', '') : '1';
                        document.getElementById('greetingDayNumber').textContent = dayNumber;
                        document.getElementById('greetingTitle').textContent = 'Day ' + dayNumber + ' - Ready to Go!';
                        document.getElementById('greetingMessage').textContent = 'Great start! Let\'s complete today\'s tasks.';
                        greetingModal.hidden = false;
                    }
                } else {
                    this.disabled = false;
                    this.textContent = 'Submit';
                    showModal('Error', data.message || 'Failed to submit task.');
                }
            } catch (err) {
                this.disabled = false;
                this.textContent = 'Submit';
                showModal('Error', 'Network error. Please try again.');
            }
        });
    });

    // ============================================================
    // HELP TOOLTIP — Click to show explanation
    // ============================================================
    document.querySelectorAll('[data-help-tip]').forEach((tip) => {
        tip.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('is-showing');
        });
        
        tip.addEventListener('blur', function() {
            setTimeout(() => this.classList.remove('is-showing'), 200);
        });
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('[data-help-tip]')) {
            document.querySelectorAll('[data-help-tip].is-showing').forEach((tip) => {
                tip.classList.remove('is-showing');
            });
        }
    });

    // ============================================================
    // GREETING MODAL
    // ============================================================
    if (greetingModal) {
        const greetingClose = greetingModal.querySelector('[data-greeting-close]');
        const greetingAction = document.getElementById('greetingAction');

        function closeGreeting() {
            greetingModal.hidden = true;
        }

        if (greetingClose) greetingClose.addEventListener('click', closeGreeting);
        if (greetingAction) greetingAction.addEventListener('click', closeGreeting);
    }

    // ============================================================
    // MYSTERY BOX (Day 10)
    // ============================================================
    if (mysteryModal) {
        const boxes = Array.from(document.querySelectorAll('[data-box-index]'));
        const claimBtn = document.getElementById('mysteryClaimBtn');
        const resultDiv = document.getElementById('mysteryResult');
        const resultTextEl = document.getElementById('mysteryResultText');
        const resultSubEl = document.getElementById('mysteryResultSub');
        let selectedReward = 0;
        let claimed = false;

        const rewards = [
            Math.floor(Math.random() * 11) + 10,
            Math.floor(Math.random() * 11) + 10,
            Math.floor(Math.random() * 11) + 10
        ];

        boxes.forEach((box) => {
            const index = Number(box.dataset.boxIndex);
            const rewardEl = box.querySelector('[data-box-reward]');
            if (rewardEl) rewardEl.textContent = rewards[index] + ' $REX';
        });

        boxes.forEach((box) => {
            box.addEventListener('click', function() {
                if (this.classList.contains('is-flipped') || claimed) return;
                const index = Number(this.dataset.boxIndex);
                selectedReward = rewards[index];

                this.classList.add('is-flipped');

                boxes.forEach((b) => {
                    if (b !== this) b.style.pointerEvents = 'none';
                });

                setTimeout(() => {
                    resultDiv.hidden = false;
                    resultTextEl.textContent = 'You won ' + selectedReward + ' $REX!';
                    claimBtn.hidden = false;
                }, 800);
            });
        });

        if (claimBtn) {
            claimBtn.addEventListener('click', async function() {
                if (claimed) return;
                this.disabled = true;
                this.textContent = 'Claiming...';

                try {
                    const response = await fetch(mysteryUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: new URLSearchParams({ reward: selectedReward }),
                    });
                    const data = await response.json();

                    if (data.success) {
                        claimed = true;
                        resultSubEl.textContent = 'Reward has been added to your balance!';
                        this.textContent = 'Claimed ✅';
                        this.disabled = true;
                        triggerConfetti();
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        this.disabled = false;
                        this.textContent = 'Claim Reward';
                        showModal('Error', data.message || 'Failed to claim reward.');
                    }
                } catch (err) {
                    this.disabled = false;
                    this.textContent = 'Claim Reward';
                    showModal('Error', 'Network error. Please try again.');
                }
            });
        }

        function triggerConfetti() {
            const confettiContainer = document.getElementById('mysteryConfetti');
            if (!confettiContainer) return;
            const colors = ['#4ade80', '#22d3ee', '#fbbf24', '#f472b6', '#a78bfa', '#fb923c'];
            for (let i = 0; i < 50; i++) {
                const piece = document.createElement('div');
                piece.className = 'taskhub-confetti-piece';
                piece.style.left = Math.random() * 100 + '%';
                piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                piece.style.animationDelay = Math.random() * 2 + 's';
                piece.style.animationDuration = (Math.random() * 2 + 1) + 's';
                confettiContainer.appendChild(piece);
            }
        }
    }

    // ============================================================
    // COUNTDOWN TICKER
    // ============================================================
    setInterval(() => {
        document.querySelectorAll('[data-th-timer]').forEach((overlay) => {
            const heroCard = overlay.closest('.th-hero-card');
            if (heroCard) updateTimerOverlay(heroCard);
        });

        document.querySelectorAll('[data-th-day-countdown]').forEach((el) => {
            let seconds = Number(el.dataset.thDayCountdown || 0);
            if (seconds <= 0) return;
            seconds -= 1;
            el.dataset.thDayCountdown = String(seconds);
            el.textContent = seconds > 0 ? 'Unlocks in ' + formatDuration(seconds) : 'Unlocked!';
        });
    }, 1000);

    // ============================================================
    // AUTO-REFRESH STATE EVERY 30 SECONDS
    // ============================================================
    setInterval(async () => {
        try {
            const response = await fetch(submitUrl.replace('submit_taskhub_task', 'get_taskhub_state'), {
                method: 'GET',
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (data.success && data.state) {
                const currentDayEl = document.getElementById('currentDayValue');
                const missionStatusEl = document.getElementById('missionStatusText');
                const progressStatusEl = document.getElementById('progressStatusValue');
                if (currentDayEl) currentDayEl.textContent = data.state.current_day || 1;
                if (missionStatusEl) missionStatusEl.textContent = data.state.status_message || '';
                if (progressStatusEl) progressStatusEl.textContent = data.state.status || 'in_progress';
            }
        } catch (e) {
            // Silent fail
        }
    }, 30000);

    // ============================================================
    // INIT — Select current day on load
    // ============================================================
    const activeDot = document.querySelector('[data-th-day].is-active');
    if (activeDot) {
        selectDay(Number(activeDot.dataset.thDay));
    }

    // ============================================================
    // EXPOSE selectDay globally for external use
    // ============================================================
    window.taskhubSelectDay = selectDay;

})();
