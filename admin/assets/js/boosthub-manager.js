/**
 * BoostHub Manager — AJAX-driven CRUD
 * Requires: admin-toast.js (showToast)
 * Follows same pattern as quiz-manager.js
 */
(function () {
    'use strict';

    var API_BASE = '/coinrex/api/admin/boosthub.php';
    var tasks = [];
    var reviews = [];
    var taskCategories = {};

    // ─── DOM refs ────────────────────────────────────────────────
    var taskListContainer = document.getElementById('boosthubTaskList');
    var taskCountEl = document.getElementById('boosthubTaskCount');
    var reviewContainer = document.getElementById('boosthubReviewContainer');
    var reviewCountEl = document.getElementById('boosthubReviewCount');
    var categorySelect = document.getElementById('boosthubCategoryFilter');
    var addBtn = document.getElementById('boosthubAddBtn');

    // ─── Task Modal refs ─────────────────────────────────────────
    var taskModal = document.getElementById('boosthubTaskModal');
    var taskModalOverlay = document.getElementById('boosthubTaskModalOverlay');
    var taskModalTitle = document.getElementById('boosthubTaskModalTitle');
    var taskModalForm = document.getElementById('boosthubTaskModalForm');
    var taskModalId = document.getElementById('boosthubTaskModalId');
    var taskModalTitleInput = document.getElementById('boosthubTaskModalTitle');
    var taskModalCategory = document.getElementById('boosthubTaskModalCategory');
    var taskModalReward = document.getElementById('boosthubTaskModalReward');
    var taskModalCooldown = document.getElementById('boosthubTaskModalCooldown');
    var taskModalDescription = document.getElementById('boosthubTaskModalDescription');
    var taskModalLink = document.getElementById('boosthubTaskModalLink');
    var taskModalSteps = document.getElementById('boosthubTaskModalSteps');
    var taskModalProof = document.getElementById('boosthubTaskModalProof');
    var taskModalCta = document.getElementById('boosthubTaskModalCta');
    var taskModalDailyLimit = document.getElementById('boosthubTaskModalDailyLimit');
    var taskModalActive = document.getElementById('boosthubTaskModalActive');
    var taskModalCancelBtn = document.getElementById('boosthubTaskModalCancel');
    var taskModalCancelBtn2 = document.getElementById('boosthubTaskModalCancel2');

    // ─── Delete Modal refs ───────────────────────────────────────
    var deleteModal = document.getElementById('boosthubDeleteModal');
    var confirmDeleteBtn = document.getElementById('boosthubConfirmDelete');
    var cancelDeleteBtns = document.querySelectorAll('#boosthubCancelDelete, #boosthubCancelDelete2');
    var pendingDeleteId = null;

    // ─── Review Confirm Modal refs ───────────────────────────────
    var reviewModal = document.getElementById('boosthubReviewModal');
    var reviewModalMessage = document.getElementById('boosthubReviewModalMessage');
    var reviewModalConfirm = document.getElementById('boosthubReviewModalConfirm');
    var reviewModalCancel = document.getElementById('boosthubReviewModalCancel');
    var pendingReview = null; // { logId, decision }

    // ─── Init ────────────────────────────────────────────────────
    function init() {
        if (!taskListContainer) return;

        // Load task categories from the select element
        if (categorySelect) {
            var opts = categorySelect.querySelectorAll('option');
            for (var i = 0; i < opts.length; i++) {
                taskCategories[opts[i].value] = opts[i].textContent;
            }
            categorySelect.addEventListener('change', function () {
                loadTasks(this.value);
            });
        }

        // Add button
        if (addBtn) {
            addBtn.addEventListener('click', openAddModal);
        }

        // Task modal cancel
        if (taskModalCancelBtn) taskModalCancelBtn.addEventListener('click', closeTaskModal);
        if (taskModalCancelBtn2) taskModalCancelBtn2.addEventListener('click', closeTaskModal);
        if (taskModalOverlay) taskModalOverlay.addEventListener('click', closeTaskModal);

        // Task modal form submit
        if (taskModalForm) {
            taskModalForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveTask();
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

        // ─── Review confirm modal ─────────────────────────────
        if (reviewModalConfirm) {
            reviewModalConfirm.addEventListener('click', function () {
                if (pendingReview) {
                    executeReview(pendingReview.logId, pendingReview.decision);
                }
            });
        }
        if (reviewModalCancel) {
            reviewModalCancel.addEventListener('click', closeReviewModal);
        }
        var reviewModalClose = document.getElementById('boosthubReviewModalClose');
        if (reviewModalClose) {
            reviewModalClose.addEventListener('click', closeReviewModal);
        }
        if (reviewModal) {
            reviewModal.addEventListener('click', function (e) {
                if (e.target === reviewModal) closeReviewModal();
            });
        }

        // Close modals on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeTaskModal();
                if (deleteModal) deleteModal.classList.remove('show');
                closeReviewModal();
            }
        });

        // Load initial data
        loadTasks(categorySelect ? categorySelect.value : 'all');
        loadReviews();
    }

    // ─── Load Tasks ──────────────────────────────────────────────
    function loadTasks(category) {
        category = category || 'all';
        taskListContainer.innerHTML = '<div class="quiz-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

        var url = API_BASE + '?task_category=' + encodeURIComponent(category);

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.error || 'Failed to load');
                tasks = res.data || [];
                renderTasks();
            })
            .catch(function (err) {
                taskListContainer.innerHTML = '<div class="dashboard-empty is-error"><i class="fas fa-exclamation-triangle"></i><p>' + escapeHtml(err.message) + '</p></div>';
            });
    }

    // ─── Render Tasks ────────────────────────────────────────────
    function renderTasks() {
        if (!taskListContainer) return;
        if (taskCountEl) taskCountEl.textContent = tasks.length;

        if (tasks.length === 0) {
            taskListContainer.innerHTML = '' +
                '<div class="dashboard-empty">' +
                    '<i class="fas fa-tasks"></i>' +
                    '<p>No BoostHub tasks found. Create one to get started.</p>' +
                '</div>';
            return;
        }

        var html = '';
        for (var i = 0; i < tasks.length; i++) {
            var t = tasks[i];
            var catLabel = taskCategories[t.task_category] || t.task_category || 'Custom Task';
            var isActive = t.is_active == 1;
            var statusClass = isActive ? 'status-pending' : 'status-rejected';
            var statusText = isActive ? 'Active' : 'Inactive';

            html += '' +
                '<div class="boosthub-task-card" data-id="' + t.id + '">' +
                    '<div class="boosthub-task-card-head">' +
                        '<div>' +
                            '<span class="status-pill status-under-review">' + escapeHtml(catLabel) + '</span>' +
                            '<h3>' + escapeHtml(t.title) + '</h3>' +
                        '</div>' +
                        '<button type="button" class="quiz-toggle-btn ' + statusClass + ' boosthub-toggle-btn" data-id="' + t.id + '" title="Click to toggle">' +
                            statusText +
                        '</button>' +
                    '</div>' +
                    '<div class="boosthub-task-card-body">' +
                        '<p class="boosthub-task-desc">' + escapeHtml(t.description || '') + '</p>' +
                        '<div class="boosthub-task-meta">' +
                            '<span><i class="fas fa-coins"></i> ' + parseFloat(t.reward || 0).toFixed(2) + ' $REX</span>' +
                            '<span><i class="fas fa-clock"></i> ' + ((t.cooldown_seconds || 86400) / 3600).toFixed(0) + 'h cooldown</span>' +
                            '<span><i class="fas fa-repeat"></i> ' + (t.daily_limit || 1) + '/day</span>' +
                        '</div>' +
                        '<div class="boosthub-task-stats">' +
                            '<span><strong>' + numberFormat(t.completed_total || 0) + '</strong> completed</span>' +
                            '<span><strong>' + numberFormat(t.completed_today || 0) + '</strong> today</span>' +
                            '<span><strong>' + numberFormat(t.blocked_total || 0) + '</strong> blocked</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="boosthub-task-card-foot">' +
                        '<button type="button" class="btn btn-secondary btn-sm boosthub-edit-btn" data-id="' + t.id + '"><i class="fas fa-pen"></i> Edit</button>' +
                        '<button type="button" class="btn btn-danger btn-sm boosthub-delete-btn" data-id="' + t.id + '"><i class="fas fa-trash"></i> Delete</button>' +
                    '</div>' +
                '</div>';
        }

        taskListContainer.innerHTML = html;

        // ─── Bind events ──────────────────────────────────────────
        // Toggle
        var toggleBtns = taskListContainer.querySelectorAll('.boosthub-toggle-btn');
        for (var tb = 0; tb < toggleBtns.length; tb++) {
            toggleBtns[tb].addEventListener('click', function () {
                var id = parseInt(this.getAttribute('data-id'));
                toggleTask(id);
            });
        }

        // Edit
        var editBtns = taskListContainer.querySelectorAll('.boosthub-edit-btn');
        for (var eb = 0; eb < editBtns.length; eb++) {
            editBtns[eb].addEventListener('click', function () {
                var id = parseInt(this.getAttribute('data-id'));
                openEditModal(id);
            });
        }

        // Delete
        var deleteBtns = taskListContainer.querySelectorAll('.boosthub-delete-btn');
        for (var db = 0; db < deleteBtns.length; db++) {
            deleteBtns[db].addEventListener('click', function () {
                var id = parseInt(this.getAttribute('data-id'));
                pendingDeleteId = id;
                if (deleteModal) deleteModal.classList.add('show');
            });
        }
    }

    // ─── Toggle Task ─────────────────────────────────────────────
    function toggleTask(id) {
        var formData = new FormData();
        formData.append('action_type', 'toggle');
        formData.append('task_id', id);

        fetch(API_BASE, {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) throw new Error(res.error || 'Toggle failed');
            if (typeof showToast === 'function') showToast(res.message || 'Toggled!', 'success');
            loadTasks(categorySelect ? categorySelect.value : 'all');
        })
        .catch(function (err) {
            if (typeof showToast === 'function') showToast(err.message, 'error');
        });
    }

    // ─── Execute Delete ──────────────────────────────────────────
    function executeDelete(id) {
        if (deleteModal) deleteModal.classList.remove('show');
        pendingDeleteId = null;

        var formData = new FormData();
        formData.append('action_type', 'delete');
        formData.append('task_id', id);

        fetch(API_BASE, {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) throw new Error(res.error || 'Delete failed');
            if (typeof showToast === 'function') showToast(res.message || 'Deleted!', 'success');
            loadTasks(categorySelect ? categorySelect.value : 'all');
        })
        .catch(function (err) {
            if (typeof showToast === 'function') showToast(err.message, 'error');
        });
    }

    // ─── Open Add Modal ──────────────────────────────────────────
    function openAddModal() {
        if (!taskModal) return;

        taskModalTitle.textContent = 'Create New BoostHub Task';
        taskModalId.value = '0';
        taskModalTitleInput.value = '';
        taskModalReward.value = '';
        taskModalCooldown.value = '86400';
        taskModalDescription.value = '';
        taskModalLink.value = '';
        taskModalSteps.value = '';
        taskModalProof.value = '';
        taskModalCta.value = '';
        taskModalDailyLimit.value = '1';
        taskModalActive.checked = true;

        // Reset category to first option
        if (taskModalCategory) taskModalCategory.selectedIndex = 0;

        taskModal.classList.add('is-open');
        taskModalOverlay.classList.add('is-open');
    }

    // ─── Open Edit Modal ─────────────────────────────────────────
    function openEditModal(id) {
        if (!taskModal) return;

        // Find task in local data
        var t = null;
        for (var i = 0; i < tasks.length; i++) {
            if (tasks[i].id === id) {
                t = tasks[i];
                break;
            }
        }
        if (!t) {
            if (typeof showToast === 'function') showToast('Task not found.', 'error');
            return;
        }

        taskModalTitle.textContent = 'Edit Task';
        taskModalId.value = t.id;
        taskModalTitleInput.value = t.title || '';
        taskModalCategory.value = t.task_category || 'custom';
        taskModalReward.value = t.reward || '';
        taskModalCooldown.value = t.cooldown_seconds || 86400;
        taskModalDescription.value = t.description || '';
        taskModalLink.value = t.task_link || '';
        taskModalSteps.value = t.completion_steps || '';
        taskModalProof.value = t.proof_notes || '';
        taskModalCta.value = t.cta_label || '';
        taskModalDailyLimit.value = t.daily_limit || 1;
        taskModalActive.checked = t.is_active == 1;

        taskModal.classList.add('is-open');
        taskModalOverlay.classList.add('is-open');
    }

    // ─── Close Task Modal ────────────────────────────────────────
    function closeTaskModal() {
        if (!taskModal) return;
        taskModal.classList.remove('is-open');
        taskModalOverlay.classList.remove('is-open');
    }

    // ─── Save Task ───────────────────────────────────────────────
    function saveTask() {
        var id = parseInt(taskModalId.value);
        var title = taskModalTitleInput.value.trim();
        var reward = parseFloat(taskModalReward.value) || 0;
        var cooldown = parseInt(taskModalCooldown.value) || 86400;
        var description = taskModalDescription.value.trim();
        var link = taskModalLink.value.trim();
        var steps = taskModalSteps.value.trim();
        var proof = taskModalProof.value.trim();
        var cta = taskModalCta.value.trim();
        var dailyLimit = parseInt(taskModalDailyLimit.value) || 1;
        var isActive = taskModalActive.checked ? 1 : 0;
        var category = taskModalCategory.value;

        if (!title) {
            if (typeof showToast === 'function') showToast('Task name is required.', 'error');
            return;
        }
        if (!description) {
            if (typeof showToast === 'function') showToast('Short description is required.', 'error');
            return;
        }
        if (reward <= 0) {
            if (typeof showToast === 'function') showToast('Reward must be greater than 0.', 'error');
            return;
        }
        if (!steps) {
            if (typeof showToast === 'function') showToast('Completion steps are required.', 'error');
            return;
        }

        var formData = new FormData();
        formData.append('action_type', 'save_task');
        formData.append('task_id', id);
        formData.append('title', title);
        formData.append('task_category', category);
        formData.append('reward', reward);
        formData.append('cooldown_seconds', cooldown);
        formData.append('description', description);
        formData.append('task_link', link);
        formData.append('completion_steps', steps);
        formData.append('proof_notes', proof);
        formData.append('cta_label', cta);
        formData.append('daily_limit', dailyLimit);
        formData.append('is_active', isActive);

        fetch(API_BASE, {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) throw new Error(res.error || 'Save failed');
            if (typeof showToast === 'function') showToast(res.message || 'Saved!', 'success');
            closeTaskModal();
            loadTasks(categorySelect ? categorySelect.value : 'all');
        })
        .catch(function (err) {
            if (typeof showToast === 'function') showToast(err.message, 'error');
        });
    }

    // ─── Load Reviews ────────────────────────────────────────────
    function loadReviews() {
        if (!reviewContainer) return;

        fetch(API_BASE + '?action=reviews')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) throw new Error(res.error || 'Failed to load reviews');
                reviews = res.data || [];
                renderReviews();
            })
            .catch(function (err) {
                reviewContainer.innerHTML = '<div class="dashboard-empty is-error"><i class="fas fa-exclamation-triangle"></i><p>' + escapeHtml(err.message) + '</p></div>';
            });
    }

    // ─── Render Reviews ──────────────────────────────────────────
    function renderReviews() {
        if (!reviewContainer) return;
        if (reviewCountEl) reviewCountEl.textContent = reviews.length;

        if (reviews.length === 0) {
            reviewContainer.innerHTML = '' +
                '<tr>' +
                    '<td colspan="4" class="dashboard-empty">' +
                        '<i class="fas fa-check-circle"></i>' +
                        '<p>No pending BoostHub evidence submissions.</p>' +
                    '</td>' +
                '</tr>';
            return;
        }

        var html = '';
        for (var i = 0; i < reviews.length; i++) {
            var r = reviews[i];
            var catLabel = taskCategories[r.task_category] || r.task_category || 'Custom Task';

            html += '' +
                '<tr data-log-id="' + r.id + '">' +
                    '<td data-label="User">' +
                        '<strong>' + escapeHtml(r.username || 'Unknown') + '</strong><br>' +
                        '<span class="muted">' + escapeHtml(r.email || '') + '</span>' +
                    '</td>' +
                    '<td data-label="Task">' +
                        '<span class="status-pill status-under-review">' + escapeHtml(catLabel) + '</span><br>' +
                        '<strong>' + escapeHtml(r.title || '') + '</strong><br>' +
                        '<span class="muted">Reward: ' + parseFloat(r.reward || 0).toFixed(2) + ' $REX</span>' +
                    '</td>' +
                    '<td data-label="Evidence">' +
                        '<code>' + escapeHtml(r.proof_data || '') + '</code>' +
                    '</td>' +
                    '<td data-label="Action">' +
                        '<button type="button" class="btn btn-primary btn-sm boosthub-review-btn" data-log-id="' + r.id + '" data-decision="approve">' +
                            '<i class="fas fa-check"></i> Approve' +
                        '</button>' +
                        '<button type="button" class="btn btn-danger btn-sm boosthub-review-btn" data-log-id="' + r.id + '" data-decision="reject" style="margin-top:6px;">' +
                            '<i class="fas fa-times"></i> Reject' +
                        '</button>' +
                    '</td>' +
                '</tr>';
        }

        reviewContainer.innerHTML = html;

        // Bind review buttons
        var reviewBtns = reviewContainer.querySelectorAll('.boosthub-review-btn');
        for (var rb = 0; rb < reviewBtns.length; rb++) {
            reviewBtns[rb].addEventListener('click', function () {
                var logId = parseInt(this.getAttribute('data-log-id'));
                var decision = this.getAttribute('data-decision');
                openReviewModal(logId, decision);
            });
        }
    }

    // ─── Open Review Confirm Modal ───────────────────────────────
    function openReviewModal(logId, decision) {
        if (!reviewModal) return;
        pendingReview = { logId: logId, decision: decision };

        var actionLabel = decision === 'approve' ? 'approve' : 'reject';
        reviewModalMessage.textContent = 'Are you sure you want to ' + actionLabel + ' this submission?';
        reviewModalConfirm.textContent = decision === 'approve' ? 'Yes, Approve' : 'Yes, Reject';
        reviewModalConfirm.className = 'btn ' + (decision === 'approve' ? 'btn-primary' : 'btn-danger');

        reviewModal.classList.add('show');
    }

    function closeReviewModal() {
        if (!reviewModal) return;
        reviewModal.classList.remove('show');
        pendingReview = null;
    }

    // ─── Execute Review ──────────────────────────────────────────
    function executeReview(logId, decision) {
        closeReviewModal();

        var formData = new FormData();
        formData.append('action_type', 'review');
        formData.append('log_id', logId);
        formData.append('decision', decision);

        fetch(API_BASE, {
            method: 'POST',
            body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) throw new Error(res.error || 'Review failed');
            if (typeof showToast === 'function') showToast(res.message || 'Done!', 'success');
            loadReviews();
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

    function numberFormat(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // ─── Boot ────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
