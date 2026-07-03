/**
 * LearnHub Premium — Single-Card Focus Experience
 * Handles: Day stepper, hero card transitions, timer overlay, quiz, learning gate
 * 
 * v2.1 — Auto-load quiz after validation + Revisit Page button in tip section
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
        if (!modal || !modalTitle || !modalMessage || !modalAction) {
            window.alert((title ? title + ': ' : '') + (message || 'Something went wrong.'));
            if (typeof onConfirm === 'function') onConfirm();
            return;
        }
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
        // Try to parse JSON regardless of status code
        try {
            const data = await response.json();
            return data;
        } catch (e) {
            // If JSON parsing fails, throw with the HTTP status info
            throw new Error('Server returned ' + response.status + ' ' + response.statusText + '. Invalid response format.');
        }
    }

    function setActionButtonLoading(btn, loadingText) {
        if (!btn || btn.tagName !== 'BUTTON') return;
        if (!btn.dataset.originalHtml) {
            btn.dataset.originalHtml = btn.innerHTML;
        }
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.textContent = loadingText || 'Submitting...';
    }

    function restoreActionButton(btn) {
        if (!btn || btn.tagName !== 'BUTTON') return;
        btn.disabled = false;
        btn.classList.remove('is-loading');
        if (btn.dataset.originalHtml) {
            btn.innerHTML = btn.dataset.originalHtml;
        }
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
            // Already validated — replace with Revisit Page button and auto-show quiz
            replaceOpenWithReviewBtn(gate, taskKey);
            autoShowQuiz(gate, taskKey);
            gate.hidden = true;
            return;
        }

        opener.addEventListener('click', function(e) {
            e.preventDefault();
            const learningWindow = window.open('about:blank', '_blank');
            if (learningWindow) {
                try {
                    learningWindow.document.write('<!doctype html><title>Opening learning...</title><body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;color:#e2e8f0;font-family:Arial,sans-serif"><div style="text-align:center;padding:24px"><strong>Opening learning page...</strong><br><span style="color:#94a3b8;font-size:13px">Please wait.</span></div></body>');
                    learningWindow.document.close();
                } catch (err) {
                    // Ignore popup document access failures.
                }
            }
            startBackendSession(gate, taskKey, learningUrl, learningWindow);
        });
    }

    async function startBackendSession(gate, taskKey, learningUrl, learningWindow) {
        const status = gate.querySelector('[data-learning-status]');
        const opener = gate.querySelector('[data-learning-open]');

        gate.classList.remove('is-locked');
        gate.classList.add('is-reading');
        if (status) {
            status.textContent = '⏳ Opening learning page...';
            status.style.color = 'var(--th-primary)';
            status.style.borderColor = 'rgba(29, 78, 216, 0.3)';
            status.style.background = 'rgba(29, 78, 216, 0.08)';
        }
        if (opener) {
            opener.classList.add('is-waiting');
            opener.textContent = 'Waiting...';
            opener.setAttribute('aria-busy', 'true');
        }
        if (status) {
            status.textContent = 'Waiting...';
        }

        let sessionData = null;
        try {
            const response = await fetch(learningApi.start, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({ task_key: taskKey }),
            });
            const data = await response.json();
            if (data.success) {
                sessionData = data;
            }
        } catch (e) {
            if (learningWindow && !learningWindow.closed) {
                learningWindow.close();
            }
            if (status) {
                status.textContent = '⚠ Failed to start session. Please try again.';
                status.style.color = 'var(--th-red)';
                status.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                status.style.background = 'rgba(239, 68, 68, 0.08)';
            }
            if (opener) {
                opener.disabled = false;
                opener.classList.remove('is-waiting');
                opener.textContent = 'Open & Validate';
                opener.removeAttribute('aria-busy');
            }
            gate.classList.remove('is-reading');
            gate.classList.add('is-locked');
            return;
        }

        if (!sessionData || !sessionData.session_token) {
            if (learningWindow && !learningWindow.closed) {
                learningWindow.close();
            }
            if (status) {
                status.textContent = '⚠ Failed to create learning session.';
                status.style.color = 'var(--th-red)';
                status.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                status.style.background = 'rgba(239, 68, 68, 0.08)';
            }
            if (opener) {
                opener.disabled = false;
                opener.classList.remove('is-waiting');
                opener.textContent = 'Open & Validate';
                opener.removeAttribute('aria-busy');
            }
            gate.classList.remove('is-reading');
            gate.classList.add('is-locked');
            return;
        }

        const sessionToken = sessionData.session_token;
        const bridgeUrl = sessionData.bridge_url || '';
        gate.dataset.learnSessionToken = sessionToken;

        // Open the learning bridge page in a new tab
        // The bridge page shows the learning material with a countdown timer
        // After the timer completes, the user clicks "I've Read It" which calls verify_session.php
        // The bridge page then sends a postMessage back to this tab
        const destinationUrl = bridgeUrl || learningUrl;
        if (destinationUrl) {
            if (learningWindow && !learningWindow.closed) {
                learningWindow.location.href = destinationUrl;
            } else {
                window.location.href = destinationUrl;
            }
        }

        // Update status to show we're waiting for the user to complete reading
        if (status) {
            status.textContent = '📖 Learning page opened — please read the material';
            status.style.color = 'var(--th-primary)';
            status.style.borderColor = 'rgba(29, 78, 216, 0.3)';
            status.style.background = 'rgba(29, 78, 216, 0.08)';
        }
        if (opener) {
            opener.textContent = '⏳ Waiting...';
            opener.disabled = true;
            opener.textContent = 'Waiting...';
            opener.classList.add('is-waiting');
            opener.setAttribute('aria-busy', 'true');
        }
        if (status) {
            status.textContent = 'Waiting...';
        }

        // Listen for postMessage from the learning bridge page
        function handleLearningMessage(event) {
            if (event.data && event.data.type === 'th-learning-verified' && event.data.sessionToken === sessionToken && event.data.taskKey === taskKey) {
                window.removeEventListener('message', handleLearningMessage);
                // Now validate on the backend
                validateLearning(gate, taskKey, sessionToken);
            }
        }
        window.addEventListener('message', handleLearningMessage);

        // Fallback: also check via polling every 5 seconds in case postMessage fails
        let pollCount = 0;
        const pollInterval = setInterval(async function() {
            pollCount++;
            // Stop polling after 5 minutes (60 * 5 = 300 seconds / 5 = 60 polls)
            if (pollCount > 60) {
                clearInterval(pollInterval);
                if (opener) {
                    opener.textContent = '🔄 Try Again';
                    opener.disabled = false;
                    opener.classList.remove('is-waiting');
                    opener.removeAttribute('aria-busy');
                }
                if (status) {
                    status.textContent = '⏰ Timed out — please try again';
                    status.style.color = 'var(--th-red)';
                    status.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                    status.style.background = 'rgba(239, 68, 68, 0.08)';
                }
                gate.classList.remove('is-reading');
                gate.classList.add('is-locked');
                return;
            }
            try {
                const checkResp = await fetch(learningApi.validate, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({ task_key: taskKey, session_token: sessionToken }),
                });
                const checkData = await checkResp.json();
                if (checkData.success && checkData.valid) {
                    clearInterval(pollInterval);
                    window.removeEventListener('message', handleLearningMessage);
                    validateLearning(gate, taskKey, sessionToken);
                }
            } catch (e) {
                // Silently retry
            }
        }, 5000);
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
                // Replace "Open & Validate" with "Revisit Page" button
                replaceOpenWithReviewBtn(gate, taskKey);
                // Auto-show quiz questions immediately
                quizBlock.hidden = false;
                gate.hidden = true;
                initQuizBlock(quizBlock);
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
     * Replaces the "Open & Validate" button with a "Revisit Page" link
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

        // Create a wrapper to hold the review material link
        const wrapper = document.createElement('div');
        wrapper.className = 'th-learning-btn-group';
        wrapper.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;';

        // Revisit Page link
        const reviewBtn = document.createElement('a');
        reviewBtn.className = 'th-learning-btn is-validated';
        reviewBtn.setAttribute('data-review-material', '');
        reviewBtn.innerHTML = '📖 Revisit Page';
        reviewBtn.target = '_blank';
        reviewBtn.rel = 'noopener noreferrer';
        reviewBtn.href = reviewHref;
        if (!learningUrl) {
            reviewBtn.style.cursor = 'default';
            reviewBtn.style.opacity = '0.5';
        }
        wrapper.appendChild(reviewBtn);


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
            gate.hidden = true;
            initQuizBlock(quizBlock);
        }
    }


    // ============================================================
    // QUIZ SYSTEM v2.1 — Interactive with Wrong/Right Feedback + Revisit Page link
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

                        // Show "Revisit Page" prompt with a link to the learning page
                        let retryPrompt = q.querySelector('[data-retry-prompt]');
                        if (!retryPrompt) {
                            retryPrompt = document.createElement('div');
                            retryPrompt.className = 'th-quiz-retry-prompt';
                            retryPrompt.setAttribute('data-retry-prompt', '');
                            if (reviewUrl) {
                                retryPrompt.innerHTML = '💡 <strong>Not quite right.</strong> <a href="' + reviewUrl.replace(/&/g, '&') + '" target="_blank" rel="noopener noreferrer" style="color:var(--th-primary-light);text-decoration:underline;font-weight:600;">Revisit Page</a> and try again.';
                            } else {
                                retryPrompt.innerHTML = '💡 <strong>Not quite right.</strong> Open the page again and try again.';
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

        // Handle submit button click — directly call handleTaskSubmit
        submitBtn.addEventListener('click', function() {
            if (this.disabled) return;
            const row = quizBlock.closest('[data-task-key]');
            if (!row) return;
            // Create a synthetic button reference for handleTaskSubmit
            // Use the row itself as the context since it has data-task-key
            handleTaskSubmit(row);
        });




        showQuestion(0);
        updateScorePreview();
    }

    // Initialize any visible quiz blocks on page load
    document.querySelectorAll('[data-quiz-block]:not([hidden])').forEach(initQuizBlock);

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
            <button type="button" class="th-success-continue-btn" style="margin-top:24px;min-height:44px;padding:0 32px;border-radius:12px;background:linear-gradient(135deg,var(--th-primary),#1E40AF);color:#fff;font-size:14px;font-weight:700;border:none;cursor:pointer;transition:all 0.2s ease;display:inline-flex;align-items:center;gap:8px;">Continue →</button>
        `;

        fullscreenOverlay.appendChild(modalCard);
        document.body.appendChild(fullscreenOverlay);

        // Allow user to continue immediately by clicking the button
        const continueBtn = modalCard.querySelector('.th-success-continue-btn');
        if (continueBtn) {
            continueBtn.addEventListener('click', function() {
                fullscreenOverlay.remove();
                confettiContainer.remove();
                location.reload();
            });
        }


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
    // TASK SUBMISSION — Shared handler for all action buttons
    // ============================================================
    async function handleTaskSubmit(btn) {
        // btn can be a button element or a row element (when called from quiz submit)
        const row = btn.closest('[data-task-key]');
        if (!row) return;
        const taskKey = row.dataset.taskKey;
        const verificationMode = row.dataset.verificationMode;

        // Find the actual action button in the row to disable it
        const actionBtn = row.querySelector('[data-th-action]') || btn;
        if (actionBtn && actionBtn.disabled) return;
        setActionButtonLoading(actionBtn, 'Submitting...');

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

        // Client-side validation for social follow task (day1_social_follow)
        if (taskKey === 'day1_social_follow') {
            const hasX = !!(payload.x_handle && payload.x_handle.trim());
            const hasTelegram = !!(payload.telegram_handle && payload.telegram_handle.trim());
            if (!hasX && !hasTelegram) {
                restoreActionButton(actionBtn);
                // Highlight empty fields
                if (xHandle && !xHandle.value.trim()) {
                    xHandle.classList.add('is-error');
                }
                if (telegramHandle && !telegramHandle.value.trim()) {
                    telegramHandle.classList.add('is-error');
                }
                showModal('Missing Information', 'Please enter your X (Twitter) or Telegram username/URL before submitting.');
                return;
            }
        }


        const sharePlatform = row.querySelector('[data-share-platform]');
        const shareProofUrl = row.querySelector('[data-share-proof-url]');
        if (sharePlatform && sharePlatform.value) payload.platform = sharePlatform.value;
        if (shareProofUrl && shareProofUrl.value.trim()) payload.proof = shareProofUrl.value.trim();

        // Client-side validation for share experience task (day3_share_experience)
        if (taskKey === 'day3_share_experience') {
            const hasPlatform = !!(payload.platform && payload.platform.trim());
            const hasProof = !!(payload.proof && payload.proof.trim());
            if (!hasPlatform || !hasProof) {
                restoreActionButton(actionBtn);
                // Highlight empty fields
                if (sharePlatform && !sharePlatform.value) {
                    sharePlatform.style.borderColor = 'var(--th-red)';
                    sharePlatform.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.15)';
                }
                if (shareProofUrl && !shareProofUrl.value.trim()) {
                    shareProofUrl.style.borderColor = 'var(--th-red)';
                    shareProofUrl.style.boxShadow = '0 0 0 3px rgba(239,68,68,0.15)';
                }
                showModal('Missing Information', 'Please select a platform and paste your public post URL before submitting.');
                return;
            }
        }

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
                    restoreActionButton(actionBtn);
                    showModal('Incomplete Quiz', 'Please answer all questions correctly before submitting.');
                    return;
                }
                payload.answers_json = JSON.stringify(answers);
            }
        }

        const isCheckIn = taskKey && (taskKey.includes('_check_in') || taskKey.includes('_checkin'));

        // Disable the action button (if it's a real button element)
        setActionButtonLoading(actionBtn, 'Submitting...');


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

                if (isCheckIn) {
                    setTimeout(() => location.reload(), 1800);
                }
            } else {
                restoreActionButton(actionBtn);
                showModal('Error', data.message || 'Failed to submit task.');
            }
        } catch (err) {
            restoreActionButton(actionBtn);

            // Try to extract the actual error message from the response
            const errorMsg = (err && err.message) ? err.message : 'Network error. Please try again.';
            showModal('Error', errorMsg);
        }

    }

    // Handler for [data-submit-task] buttons (legacy)
    document.querySelectorAll('[data-submit-task]').forEach((btn) => {
        btn.addEventListener('click', function() {
            handleTaskSubmit(this);
        });
    });

    // Handler for [data-th-action] buttons (check-in, social, quiz, mystery, instant)
    document.querySelectorAll('[data-th-action]').forEach((btn) => {
        btn.addEventListener('click', function() {
            const action = this.dataset.thAction;
            // Mystery box is handled separately
            if (action === 'mystery') {
                const mysteryModal = document.getElementById('taskhubMysteryModal');
                if (mysteryModal) {
                    mysteryModal.hidden = false;
                    mysteryModal.dispatchEvent(new CustomEvent('taskhub:mystery-open'));
                }
                return;
            }
            handleTaskSubmit(this);
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
        const confettiContainer = document.getElementById('mysteryConfetti');
        let selectedBox = null;
        let claimed = false;

        function resetMysteryModal() {
            selectedBox = null;
            claimed = false;
            mysteryModal.classList.remove('is-ready-to-claim', 'is-opening-box', 'is-result-mode', 'is-claiming', 'is-claimed', 'is-reward-revealed');
            boxes.forEach((box) => {
                box.classList.remove('is-flipped', 'is-selected', 'is-muted');
                box.style.pointerEvents = '';
                const rewardEl = box.querySelector('[data-box-reward]');
                if (rewardEl) rewardEl.textContent = 'Claim to reveal';
            });
            if (resultDiv) resultDiv.hidden = true;
            if (resultTextEl) resultTextEl.textContent = 'Box selected';
            if (resultSubEl) resultSubEl.textContent = 'Claim now to reveal your server-verified reward.';
            const proUnlockEl = document.getElementById('mysteryProUnlock');
            const partialMsgEl = document.getElementById('mysteryPartialMsg');
            const requirementListEl = document.getElementById('mysteryRequirementList');
            if (proUnlockEl) proUnlockEl.hidden = true;
            if (partialMsgEl) partialMsgEl.hidden = true;
            if (requirementListEl) requirementListEl.innerHTML = '';
            if (claimBtn) {
                claimBtn.hidden = false;
                claimBtn.removeAttribute('hidden');
                claimBtn.disabled = true;
                claimBtn.textContent = 'Choose a Box';
                claimBtn.classList.remove('is-visible');
                claimBtn.style.display = '';
            }
            if (confettiContainer) confettiContainer.innerHTML = '';
        }

        mysteryModal.addEventListener('taskhub:mystery-open', resetMysteryModal);

        mysteryModal.querySelectorAll('[data-mystery-close]').forEach((closeBtn) => {
            closeBtn.addEventListener('click', function() {
                mysteryModal.hidden = true;
                resetMysteryModal();
            });
        });

        boxes.forEach((box) => {
            const rewardEl = box.querySelector('[data-box-reward]');
            if (rewardEl) rewardEl.textContent = 'Claim to reveal';
        });

        boxes.forEach((box) => {
            box.addEventListener('click', function() {
                if (selectedBox !== null || claimed) return;
                selectedBox = Number(this.dataset.boxIndex);

                this.classList.add('is-selected');
                mysteryModal.classList.add('is-opening-box');

                boxes.forEach((b) => {
                    b.style.pointerEvents = 'none';
                    if (b !== this) b.classList.add('is-muted');
                });

                setTimeout(() => {
                    this.classList.add('is-flipped');
                    mysteryModal.classList.remove('is-opening-box');
                    mysteryModal.classList.add('is-ready-to-claim');
                    mysteryModal.classList.add('is-result-mode');
                    if (resultDiv) resultDiv.hidden = false;
                    if (resultTextEl) resultTextEl.textContent = 'Revealing reward...';
                    if (resultSubEl) resultSubEl.textContent = 'Your server-verified $REX amount is coming out now.';
                    if (claimBtn) {
                        claimBtn.hidden = false;
                        claimBtn.removeAttribute('hidden');
                        claimBtn.style.display = 'inline-flex';
                        claimBtn.disabled = false;
                        claimBtn.textContent = 'Revealing...';
                        claimBtn.classList.add('is-visible');
                        claimBtn.click();
                    }
                    triggerConfetti(18, 2.2);
                }, 520);
            });
        });

        if (claimBtn) {
            claimBtn.addEventListener('click', async function() {
                if (claimed) return;
                if (selectedBox === null) {
                    showModal('Choose a Box', 'Please choose one mystery box first.');
                    return;
                }
                mysteryModal.classList.add('is-claiming');
                this.disabled = true;
                this.textContent = 'Claiming...';

                try {
                    const response = await fetch(mysteryUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                        body: new URLSearchParams({ box: selectedBox !== null ? String(selectedBox) : '' }),
                    });
                    const data = await response.json();

                    if (data.success) {
                        claimed = true;
                        const rewardAmount = Number(data.reward || 0);
                        const rewardLabel = rewardAmount.toFixed(rewardAmount % 1 === 0 ? 0 : 2) + ' $REX';
                        const proUnlocked = Boolean(data.pro_unlocked);
                        const resultMessages = ['Reward has been added to your balance!'];
                        if (data.airdrop_unlocked) {
                            const airdropAmount = Number(data.airdrop_amount || 0);
                            const airdropLabel = airdropAmount > 0
                                ? airdropAmount.toFixed(airdropAmount % 1 === 0 ? 0 : 2) + ' $REX'
                                : '';
                            resultMessages.push(airdropLabel ? 'Airdrop unlocked: ' + airdropLabel + '.' : 'Airdrop unlocked.');
                        }
                        resultTextEl.textContent = 'You won ' + rewardLabel + '!';
                        resultSubEl.textContent = resultMessages.join(' ');
                        this.textContent = 'Claimed ✅';
                        this.disabled = true;
                        mysteryModal.classList.remove('is-claiming');
                        mysteryModal.classList.add('is-claimed', 'is-reward-revealed');
                        boxes.forEach((box) => {
                            const rewardEl = box.querySelector('[data-box-reward]');
                            if (rewardEl) rewardEl.textContent = rewardLabel;
                        });
                        triggerConfetti(72, 3.5);
                        
                        // Show PRO unlock or partial completion message
                        const proUnlockEl = document.getElementById('mysteryProUnlock');
                        const partialMsgEl = document.getElementById('mysteryPartialMsg');
                        const requirementListEl = document.getElementById('mysteryRequirementList');
                        
                        if (proUnlocked && proUnlockEl) {
                            proUnlockEl.hidden = false;
                        } else if (partialMsgEl) {
                            if (requirementListEl) {
                                requirementListEl.innerHTML = '';
                                const requirements = Array.isArray(data.pro_requirements) ? data.pro_requirements : [];
                                requirements.forEach((requirement) => {
                                    const item = document.createElement('li');
                                    item.className = requirement && requirement.complete
                                        ? 'is-complete'
                                        : 'is-pending';

                                    const marker = document.createElement('span');
                                    marker.className = 'taskhub-mystery-requirement-marker';
                                    marker.textContent = requirement && requirement.complete ? '✓' : '•';

                                    const body = document.createElement('span');
                                    body.className = 'taskhub-mystery-requirement-body';

                                    const label = document.createElement('strong');
                                    label.textContent = requirement && requirement.label ? requirement.label : 'Requirement';

                                    const meta = document.createElement('small');
                                    meta.textContent = requirement && requirement.meta ? requirement.meta : '';

                                    body.appendChild(label);
                                    if (meta.textContent) body.appendChild(meta);
                                    item.appendChild(marker);
                                    item.appendChild(body);
                                    requirementListEl.appendChild(item);
                                });
                            }
                            partialMsgEl.hidden = false;
                        }
                        
                        setTimeout(() => location.reload(), 3000);
                    } else {
                        mysteryModal.classList.remove('is-claiming');
                        this.disabled = false;
                        this.textContent = 'Claim Reward';
                        showModal('Error', data.message || 'Failed to claim reward.');
                    }
                } catch (err) {
                    mysteryModal.classList.remove('is-claiming');
                    this.disabled = false;
                    this.textContent = 'Claim Reward';
                    showModal('Error', 'Network error. Please try again.');
                }
            });
        }

        function triggerConfetti(count = 50, maxDuration = 3) {
            if (!confettiContainer) return;
            confettiContainer.innerHTML = '';
            const colors = ['#4ade80', '#22d3ee', '#fbbf24', '#f472b6', '#a78bfa', '#fb923c'];
            for (let i = 0; i < count; i++) {
                const piece = document.createElement('div');
                piece.className = 'taskhub-confetti-piece';
                piece.style.left = Math.random() * 100 + '%';
                piece.style.setProperty('--th-confetti-x', (Math.random() * 160 - 80) + 'px');
                piece.style.setProperty('--th-confetti-rot', (Math.random() * 900 + 360) + 'deg');
                piece.style.background = colors[Math.floor(Math.random() * colors.length)];
                piece.style.animationDelay = Math.random() * 0.35 + 's';
                piece.style.animationDuration = (Math.random() * 1.4 + maxDuration) + 's';
                confettiContainer.appendChild(piece);
            }
            setTimeout(() => {
                if (confettiContainer) confettiContainer.innerHTML = '';
            }, Math.ceil((maxDuration + 2) * 1000));
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

    // ============================================================
    // WAITING CARD — Countdown Timer
    // ============================================================
    const waitingTimers = document.querySelectorAll('[data-th-timer-count]');
    waitingTimers.forEach(el => {
        const totalSeconds = parseInt(el.textContent.replace(/[^0-9]/g, ''), 10) || 0;
        let remaining = totalSeconds;
        
        const interval = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(interval);
                el.textContent = 'Unlocked!';
                // Reload page to show new state
                setTimeout(() => location.reload(), 2000);
                return;
            }
            el.textContent = formatDuration(remaining);
        }, 1000);
    });

    // ============================================================
    // NOTIFICATION PERMISSION — "Notify me when ready"
    // ============================================================
    const notifyBtn = document.querySelector('[data-enable-notifications]');
    if (notifyBtn) {
        notifyBtn.addEventListener('click', async () => {
            if (!('Notification' in window)) {
                alert('Notifications are not supported in this browser.');
                return;
            }
            
            if (Notification.permission === 'granted') {
                // Find the timer value
                const timerEl = document.querySelector('[data-th-timer-count]');
                const timerText = timerEl ? timerEl.textContent : 'a few minutes';
                new Notification('🔔 LearnHub', {
                    body: `Your next challenge unlocks in ${timerText}. We'll remind you!`,
                    icon: '/favicon.ico'
                });
                notifyBtn.textContent = '✅ Notification Set!';
                notifyBtn.disabled = true;
            } else if (Notification.permission === 'denied') {
                alert('Notifications are blocked. Please enable them in your browser settings.');
            } else {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    const timerEl = document.querySelector('[data-th-timer-count]');
                    const timerText = timerEl ? timerEl.textContent : 'a few minutes';
                    new Notification('🔔 LearnHub', {
                        body: `Your next challenge unlocks in ${timerText}. We'll remind you!`,
                        icon: '/favicon.ico'
                    });
                    notifyBtn.textContent = '✅ Notification Set!';
                    notifyBtn.disabled = true;
                } else {
                    alert('Notification permission was denied.');
                }
            }
        });
    }

    // ============================================================
    // SHARE PROGRESS BUTTON
    // ============================================================
    const shareBtn = document.querySelector('[data-share-progress]');
    if (shareBtn) {
        shareBtn.addEventListener('click', async () => {
            const day = shareBtn.dataset.day || '1';
            const shareText = `🔥 I just completed Day ${day} on CoinRex LearnHub! Earning rewards and building my crypto knowledge. Join me at CoinRex! 🚀`;
            
            if (navigator.share) {
                try {
                    await navigator.share({
                        title: 'CoinRex LearnHub Progress',
                        text: shareText,
                        url: window.location.href,
                    });
                } catch (e) {
                    // User cancelled
                }
            } else {
                // Fallback: copy to clipboard
                try {
                    await navigator.clipboard.writeText(shareText + ' ' + window.location.href);
                    shareBtn.textContent = '✅ Copied to clipboard!';
                    setTimeout(() => {
                        shareBtn.textContent = '📤 Share Your Progress';
                    }, 3000);
                } catch (e) {
                    alert('Share not supported. You can copy the URL manually.');
                }
            }
        });
    }

    // ============================================================
    // DAY COUNTDOWN TIMER (next day unlock)
    // ============================================================
    const dayCountdownEls = document.querySelectorAll('[data-th-day-countdown]');
    dayCountdownEls.forEach(el => {
        let seconds = parseInt(el.dataset.thDayCountdown, 10) || 0;
        
        const interval = setInterval(() => {
            seconds--;
            if (seconds <= 0) {
                clearInterval(interval);
                el.textContent = 'Unlocked!';
                setTimeout(() => location.reload(), 3000);
                return;
            }
            el.textContent = formatDuration(seconds);
            el.dataset.thDayCountdown = String(seconds);
        }, 1000);
    });

})();


