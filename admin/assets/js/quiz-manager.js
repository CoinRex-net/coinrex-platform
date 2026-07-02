/**
 * Quiz Manager — AJAX-driven CRUD
 * Requires: admin-toast.js (showToast)
 */
(function () {
    'use strict';

    var pathPrefix = window.location.pathname.indexOf('/coinrex/') === 0 ? '/coinrex' : '';
    var API_BASE = pathPrefix + '/api/admin/quiz.php';
    var currentTaskKey = '';
    var questions = [];

    // ─── DOM refs ────────────────────────────────────────────────
    var taskSelect = document.getElementById('quizTaskSelect');
    var questionsContainer = document.getElementById('quizQuestionsContainer');
    var questionsCount = document.getElementById('quizQuestionsCount');
    var addBtn = document.getElementById('quizAddBtn');
    var modal = document.getElementById('quizModal');
    var modalOverlay = document.getElementById('quizModalOverlay');
    var modalTitle = document.getElementById('quizModalTitle');
    var modalForm = document.getElementById('quizModalForm');
    var modalQuestion = document.getElementById('modalQuestion');
    var modalChoicesContainer = document.getElementById('modalChoicesContainer');
    var modalSortOrder = document.getElementById('modalSortOrder');
    var modalTaskKey = document.getElementById('modalTaskKey');
    var modalQuestionId = document.getElementById('modalQuestionId');
    var modalCancelBtn = document.getElementById('modalCancelBtn');
    var addChoiceBtn = document.getElementById('addChoiceBtn');
    var modalTaskSelect = document.getElementById('modalTaskSelect');
    var modalTaskSelectWrap = document.getElementById('modalTaskSelectWrap');

    // ─── Delete modal refs ───────────────────────────────────────
    var deleteModal = document.getElementById('deleteModal');
    var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    var cancelDeleteBtns = document.querySelectorAll('#cancelDeleteBtn, #cancelDeleteBtn2');
    var pendingDeleteId = null;

    // ─── Init ────────────────────────────────────────────────────
    function init() {
        if (!taskSelect) return;

        // Load questions on task change
        taskSelect.addEventListener('change', function () {
            currentTaskKey = this.value;
            loadQuestions(currentTaskKey);
        });

        // Load initial
        if (taskSelect.value) {
            currentTaskKey = taskSelect.value;
            loadQuestions(currentTaskKey);
        }

        // Add button
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                openAddModal();
            });
        }

        // Modal cancel (X button + footer Cancel button)
        if (modalCancelBtn) {
            modalCancelBtn.addEventListener('click', closeModal);
        }
        var modalCancelBtn2 = document.getElementById('modalCancelBtn2');
        if (modalCancelBtn2) {
            modalCancelBtn2.addEventListener('click', closeModal);
        }
        if (modalOverlay) {
            modalOverlay.addEventListener('click', closeModal);
        }

        // Modal form submit
        if (modalForm) {
            modalForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveQuestion();
            });
        }

        // Add choice row
        if (addChoiceBtn) {
            addChoiceBtn.addEventListener('click', function () {
                addChoiceRow('', false);
            });
        }

        // Modal task select change (for add mode)
        if (modalTaskSelect) {
            modalTaskSelect.addEventListener('change', function () {
                // no-op, just for user to pick
            });
        }

        // ─── Delete modal events ──────────────────────────────
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function () {
                if (pendingDeleteId !== null) {
                    executeDelete(pendingDeleteId);
                }
            });
        }
        for (var ci = 0; ci < cancelDeleteBtns.length; ci++) {
            cancelDeleteBtns[ci].addEventListener('click', function () {
                if (deleteModal) deleteModal.classList.remove('show');
                pendingDeleteId = null;
            });
        }
        if (deleteModal) {
            deleteModal.addEventListener('click', function (e) {
                if (e.target === deleteModal) {
                    deleteModal.classList.remove('show');
                    pendingDeleteId = null;
                }
            });
        }

        // Close modal on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeModal();
        });
    }

    // ─── Load Questions ──────────────────────────────────────────
    function loadQuestions(taskKey) {
        if (!taskKey) {
            questionsContainer.innerHTML = '<div class="dashboard-empty"><i class="fas fa-triangle-exclamation"></i><p>Select a task to view its questions.</p></div>';
            if (questionsCount) questionsCount.textContent = '0';
            return;
        }

        questionsContainer.innerHTML = '<div class="quiz-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

        fetch(API_BASE + '?task_key=' + encodeURIComponent(taskKey))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.error || 'Failed to load');
                questions = res.data || [];
                renderQuestions();
            })
            .catch(function (err) {
                questionsContainer.innerHTML = '<div class="dashboard-empty is-error"><i class="fas fa-exclamation-triangle"></i><p>' + escapeHtml(err.message) + '</p></div>';
            });
    }

    // ─── Render Questions Table ──────────────────────────────────
    function renderQuestions() {
        if (!questionsContainer) return;
        if (questionsCount) questionsCount.textContent = questions.length;

        if (questions.length === 0) {
            questionsContainer.innerHTML = '' +
                '<div class="dashboard-empty">' +
                    '<i class="fas fa-circle-question"></i>' +
                    '<p>No custom questions saved for this task. The system will use default questions.</p>' +
                '</div>';
            return;
        }

        var html = '' +
            '<div class="quiz-table-wrap">' +
            '<table class="quiz-table">' +
            '<thead>' +
            '<tr>' +
            '<th class="quiz-col-num">#</th>' +
            '<th class="quiz-col-question">Question</th>' +
            '<th class="quiz-col-choices">Choices</th>' +
            '<th class="quiz-col-status">Status</th>' +
            '<th class="quiz-col-actions">Actions</th>' +
            '</tr>' +
            '</thead>' +
            '<tbody>';

        for (var i = 0; i < questions.length; i++) {
            var q = questions[i];
            var choices = q.choices || [];
            var answerIndices = q.answer || [0];
            var isActive = q.is_active == 1;

            // Build choices HTML with correct answer markers
            var choicesHtml = '';
            for (var c = 0; c < choices.length; c++) {
                var isCorrect = answerIndices.indexOf(c) !== -1;
                choicesHtml += '<div class="quiz-choice-row' + (isCorrect ? ' is-correct' : '') + '">';
                choicesHtml += '<span class="quiz-choice-num">' + (c + 1) + '.</span> ';
                choicesHtml += escapeHtml(choices[c]);
                if (isCorrect) {
                    choicesHtml += ' <span class="quiz-correct-badge"><i class="fas fa-check"></i></span>';
                }
                choicesHtml += '</div>';
            }

            var statusClass = isActive ? 'status-pending' : 'status-rejected';
            var statusText = isActive ? 'Active' : 'Inactive';

            html += '' +
                '<tr data-id="' + q.id + '" data-sort="' + (q.sort_order || 0) + '">' +
                '<td class="quiz-col-num">' +
                    '<span class="quiz-row-num">' + (i + 1) + '</span>' +
                    '<div class="quiz-reorder">' +
                        '<button type="button" class="quiz-reorder-btn quiz-reorder-up" title="Move up"><i class="fas fa-chevron-up"></i></button>' +
                        '<button type="button" class="quiz-reorder-btn quiz-reorder-down" title="Move down"><i class="fas fa-chevron-down"></i></button>' +
                    '</div>' +
                '</td>' +
                '<td class="quiz-col-question">' +
                    '<div class="quiz-question-text">' + escapeHtml(q.question) + '</div>' +
                    '<div class="quiz-meta">' +
                        '<span>Sort: ' + (q.sort_order || 0) + '</span>' +
                        '<span>ID: ' + q.id + '</span>' +
                    '</div>' +
                '</td>' +
                '<td class="quiz-col-choices">' + choicesHtml + '</td>' +
                '<td class="quiz-col-status">' +
                    '<button type="button" class="quiz-toggle-btn ' + statusClass + '" data-id="' + q.id + '" title="Click to toggle">' +
                        statusText +
                    '</button>' +
                '</td>' +
                '<td class="quiz-col-actions">' +
                    '<div class="quiz-action-btns">' +
                        '<button type="button" class="btn btn-secondary btn-sm quiz-edit-btn" data-id="' + q.id + '"><i class="fas fa-pen"></i> Edit</button>' +
                        '<button type="button" class="btn btn-danger btn-sm quiz-delete-btn" data-id="' + q.id + '"><i class="fas fa-trash"></i> Delete</button>' +
                    '</div>' +
                '</td>' +
                '</tr>';
        }

        html += '</tbody></table></div>';
        questionsContainer.innerHTML = html;

        // ─── Bind events ──────────────────────────────────────────
        // Toggle
        var toggleBtns = questionsContainer.querySelectorAll('.quiz-toggle-btn');
        for (var t = 0; t < toggleBtns.length; t++) {
            toggleBtns[t].addEventListener('click', function () {
                var id = parseInt(this.getAttribute('data-id'));
                toggleQuestion(id);
            });
        }

        // Edit
        var editBtns = questionsContainer.querySelectorAll('.quiz-edit-btn');
        for (var e = 0; e < editBtns.length; e++) {
            editBtns[e].addEventListener('click', function () {
                var id = parseInt(this.getAttribute('data-id'));
                openEditModal(id);
            });
        }

        // Delete
        var deleteBtns = questionsContainer.querySelectorAll('.quiz-delete-btn');
        for (var d = 0; d < deleteBtns.length; d++) {
            deleteBtns[d].addEventListener('click', function () {
                var id = parseInt(this.getAttribute('data-id'));
                deleteQuestion(id);
            });
        }

        // Reorder up
        var upBtns = questionsContainer.querySelectorAll('.quiz-reorder-up');
        for (var u = 0; u < upBtns.length; u++) {
            upBtns[u].addEventListener('click', function () {
                var row = this.closest('tr');
                var prev = row.previousElementSibling;
                if (prev) {
                    swapReorder(row, prev);
                }
            });
        }

        // Reorder down
        var downBtns = questionsContainer.querySelectorAll('.quiz-reorder-down');
        for (var dn = 0; dn < downBtns.length; dn++) {
            downBtns[dn].addEventListener('click', function () {
                var row = this.closest('tr');
                var next = row.nextElementSibling;
                if (next) {
                    swapReorder(row, next);
                }
            });
        }
    }

    // ─── Swap Reorder ────────────────────────────────────────────
    function swapReorder(rowA, rowB) {
        var idA = parseInt(rowA.getAttribute('data-id'));
        var idB = parseInt(rowB.getAttribute('data-id'));
        var sortA = parseInt(rowA.getAttribute('data-sort'));
        var sortB = parseInt(rowB.getAttribute('data-sort'));

        // Swap sort_order values in DOM
        rowA.setAttribute('data-sort', sortB);
        rowB.setAttribute('data-sort', sortA);

        // Send reorder to API
        var ids = [];
        var rows = questionsContainer.querySelectorAll('tr[data-id]');
        for (var i = 0; i < rows.length; i++) {
            ids.push(parseInt(rows[i].getAttribute('data-id')));
        }

        fetch(API_BASE + '?action=reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) throw new Error(res.error || 'Reorder failed');
            // Reload to get fresh data
            loadQuestions(currentTaskKey);
            if (typeof showToast === 'function') showToast('Reordered.', 'success');
        })
        .catch(function (err) {
            if (typeof showToast === 'function') showToast(err.message, 'error');
            loadQuestions(currentTaskKey);
        });
    }

    // ─── Toggle Question ─────────────────────────────────────────
    function toggleQuestion(id) {
        fetch(API_BASE + '?action=toggle&id=' + id, { method: 'POST' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.error || 'Toggle failed');
                if (typeof showToast === 'function') showToast(res.message || 'Toggled!', 'success');
                loadQuestions(currentTaskKey);
            })
            .catch(function (err) {
                if (typeof showToast === 'function') showToast(err.message, 'error');
            });
    }

    // ─── Delete Question (show custom modal) ─────────────────────
    function deleteQuestion(id) {
        pendingDeleteId = id;
        if (deleteModal) deleteModal.classList.add('show');
    }

    // ─── Execute Delete (called from confirm button) ─────────────
    function executeDelete(id) {
        if (deleteModal) deleteModal.classList.remove('show');
        pendingDeleteId = null;

        fetch(API_BASE + '?id=' + id, { method: 'DELETE' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.error || 'Delete failed');
                if (typeof showToast === 'function') showToast(res.message || 'Deleted!', 'success');
                loadQuestions(currentTaskKey);
            })
            .catch(function (err) {
                if (typeof showToast === 'function') showToast(err.message, 'error');
            });
    }

    // ─── Open Add Modal ──────────────────────────────────────────
    function openAddModal() {
        if (!modal) return;

        modalTitle.textContent = 'Add New Question';
        modalQuestionId.value = '0';
        modalQuestion.value = '';
        modalSortOrder.value = '0';

        // Show task selector if no task selected, or let user pick
        if (modalTaskSelect) {
            modalTaskSelectWrap.style.display = '';
            // Copy options from main select
            modalTaskSelect.innerHTML = taskSelect.innerHTML;
            if (currentTaskKey) {
                modalTaskSelect.value = currentTaskKey;
            }
        }

        // Reset choices
        modalChoicesContainer.innerHTML = '';
        addChoiceRow('', false);
        addChoiceRow('', false);

        modalTaskKey.value = currentTaskKey || '';

        modal.classList.add('is-open');
        modalOverlay.classList.add('is-open');
    }

    // ─── Open Edit Modal ─────────────────────────────────────────
    function openEditModal(id) {
        if (!modal) return;

        // Find question in local data
        var q = null;
        for (var i = 0; i < questions.length; i++) {
            if (questions[i].id === id) {
                q = questions[i];
                break;
            }
        }
        if (!q) {
            if (typeof showToast === 'function') showToast('Question not found.', 'error');
            return;
        }

        modalTitle.textContent = 'Edit Question';
        modalQuestionId.value = q.id;
        modalQuestion.value = q.question || '';
        modalSortOrder.value = q.sort_order || 0;
        modalTaskKey.value = q.task_key || currentTaskKey;

        // Hide task selector in edit mode
        if (modalTaskSelect) {
            modalTaskSelectWrap.style.display = 'none';
        }

        // Build choice rows
        modalChoicesContainer.innerHTML = '';
        var choices = q.choices || [];
        var answerIndices = q.answer || [0];
        for (var c = 0; c < choices.length; c++) {
            var isCorrect = answerIndices.indexOf(c) !== -1;
            addChoiceRow(choices[c], isCorrect);
        }

        modal.classList.add('is-open');
        modalOverlay.classList.add('is-open');
    }

    // ─── Close Modal ─────────────────────────────────────────────
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modalOverlay.classList.remove('is-open');
    }

    // ─── Add Choice Row ──────────────────────────────────────────
    function addChoiceRow(value, isCorrect) {
        if (!modalChoicesContainer) return;
        var index = modalChoicesContainer.children.length;

        var row = document.createElement('div');
        row.className = 'quiz-choice-input-row';

        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'quiz-choice-correct';
        checkbox.title = 'Mark as correct answer';
        if (isCorrect) checkbox.checked = true;

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'quiz-choice-input';
        input.placeholder = 'Choice ' + (index + 1);
        input.value = value || '';

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'quiz-choice-remove';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.title = 'Remove this choice';
        removeBtn.addEventListener('click', function () {
            if (modalChoicesContainer.children.length <= 2) {
                if (typeof showToast === 'function') showToast('At least 2 choices required.', 'error');
                return;
            }
            row.remove();
        });

        row.appendChild(checkbox);
        row.appendChild(input);
        row.appendChild(removeBtn);
        modalChoicesContainer.appendChild(row);
    }

    // ─── Save Question (Create or Update) ────────────────────────
    function saveQuestion() {
        var id = parseInt(modalQuestionId.value);
        var question = modalQuestion.value.trim();
        var sortOrder = parseInt(modalSortOrder.value) || 0;

        // Get task key
        var taskKey = '';
        if (modalTaskSelect && modalTaskSelectWrap.style.display !== 'none') {
            taskKey = modalTaskSelect.value;
        } else {
            taskKey = modalTaskKey.value;
        }

        if (!taskKey) {
            if (typeof showToast === 'function') showToast('Please select a task.', 'error');
            return;
        }
        if (!question) {
            if (typeof showToast === 'function') showToast('Question text is required.', 'error');
            return;
        }

        // Collect choices
        var choiceInputs = modalChoicesContainer.querySelectorAll('.quiz-choice-input');
        var choiceCheckboxes = modalChoicesContainer.querySelectorAll('.quiz-choice-correct');
        var choices = [];
        var answerIndices = [];

        for (var i = 0; i < choiceInputs.length; i++) {
            var val = choiceInputs[i].value.trim();
            if (val === '') continue;
            choices.push(val);
            if (choiceCheckboxes[i] && choiceCheckboxes[i].checked) {
                answerIndices.push(choices.length - 1);
            }
        }

        if (choices.length < 2) {
            if (typeof showToast === 'function') showToast('At least 2 choices are required.', 'error');
            return;
        }
        if (answerIndices.length === 0) {
            if (typeof showToast === 'function') showToast('Please mark at least one correct answer.', 'error');
            return;
        }

        var payload = {
            id: id,
            task_key: taskKey,
            question: question,
            choices: choices,
            answer: answerIndices,
            sort_order: sortOrder
        };

        var method = id > 0 ? 'PUT' : 'POST';
        var url = id > 0 ? API_BASE : API_BASE;

        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) throw new Error(res.error || 'Save failed');
            if (typeof showToast === 'function') showToast(res.message || 'Saved!', 'success');
            closeModal();
            loadQuestions(currentTaskKey);
        })
        .catch(function (err) {
            if (typeof showToast === 'function') showToast(err.message, 'error');
        });
    }

    // ─── Utility ─────────────────────────────────────────────────
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ─── Boot ────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
