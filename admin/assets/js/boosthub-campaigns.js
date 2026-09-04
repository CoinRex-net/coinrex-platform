(function () {
 'use strict';
 var prefix = location.pathname.indexOf('/coinrex/') === 0 ? '/coinrex' : '';
 var api = prefix + '/api/admin/boosthub-campaigns.php';
 var modal = document.getElementById('campaignModal');
 var view = document.getElementById('campaignView');
 var form = document.getElementById('campaignForm');
 var formTitle = document.getElementById('campaignFormTitle');
 var statusHelp = document.getElementById('campaignStatusHelp');
 var logoPreview = document.getElementById('campaignLogoPreview');
 var logoFile = form.elements.project_logo_file;
 var logoUrl = form.elements.project_logo;
 var logoObjectUrl = '';
 var coverPreview = document.getElementById('campaignCoverPreview');
 var coverFile = form.elements.project_cover_file;
 var coverUrl = form.elements.project_cover;
 var coverObjectUrl = '';
 var statusMessages = {
  draft: 'Draft is private and safest while you prepare tasks.',
  scheduled: 'Scheduled campaigns remain closed until an admin makes them Active.',
  active: 'Active campaigns accept participation only between the start and end dates.',
  paused: 'Paused campaigns temporarily stop new participation.',
  completed: 'Completed is permanent for normal use. Choose it only when the campaign is finished.'
 };
 function localDateTime(date) {
  var offset = date.getTimezoneOffset() * 60000;
  return new Date(date.getTime() - offset).toISOString().slice(0, 16);
 }
 function parseCampaignLocalDateTime(value) {
  if (!value) return null;
  var date = new Date(String(value).replace(' ', 'T'));
  return isNaN(date.getTime()) ? null : date;
 }
 function campaignLocalInputValue(value) {
  var date = parseCampaignLocalDateTime(value);
  return date ? localDateTime(date) : String(value || '').replace(' ', 'T').slice(0, 16);
 }
 function campaignLocalDisplay(value) {
  var date = parseCampaignLocalDateTime(value);
  if (!date) return '';
  return date.toLocaleString(undefined, {month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'});
 }
 document.querySelectorAll('[data-campaign-local-datetime]').forEach(function (el) {
  var label = campaignLocalDisplay(el.getAttribute('data-campaign-local-datetime'));
  if (label) el.textContent = label;
 });
 function resolveCampaignMediaUrl(url) {
  if (!url) return url;
  try {
   var parsed = new URL(url, window.location.origin);
   var marker = parsed.pathname.indexOf('/assets/uploads/campaign-');
   if (marker >= 0) return prefix + parsed.pathname.slice(marker);
  } catch (error) {
   return url;
  }
  return url;
 }
 function updateLogoPreview(url) {
  if (logoObjectUrl) { URL.revokeObjectURL(logoObjectUrl); logoObjectUrl = ''; }
  logoPreview.innerHTML = '';
  if (!url) {
   logoPreview.innerHTML = '<i class=\'fas fa-image\'></i>';
   return;
  }
  var image = document.createElement('img');
  image.src = resolveCampaignMediaUrl(url);
  image.alt = 'Campaign logo preview';
  image.onerror = function () { logoPreview.innerHTML = '<i class=\'fas fa-image\'></i>'; };
  logoPreview.appendChild(image);
 }
 function updateCoverPreview(url) {
  if (coverObjectUrl) { URL.revokeObjectURL(coverObjectUrl); coverObjectUrl = ''; }
  coverPreview.innerHTML = '';
  if (!url) {
   coverPreview.innerHTML = '<div><i class=\'fas fa-panorama\'></i> X-style campaign cover</div>';
   return;
  }
  var image = document.createElement('img');
  image.src = resolveCampaignMediaUrl(url);
  image.alt = 'Campaign cover preview';
  image.onerror = function () { coverPreview.innerHTML = '<div><i class=\'fas fa-panorama\'></i> Cover preview unavailable</div>'; };
  coverPreview.appendChild(image);
 }
 function open(c) {
  form.reset();
  form.elements.id.value = c && c.id ? c.id : 0;
  formTitle.textContent = c ? 'Edit Campaign' : 'Create Your Campaign';
  if (c) {
   Object.keys(c).forEach(function (key) {
    if (!form.elements[key]) return;
    var value = c[key] == null ? '' : c[key];
    if (key === 'start_at' || key === 'end_at') value = campaignLocalInputValue(value);
    form.elements[key].value = value;
   });
  } else {
   var start = new Date();
   start.setMinutes(Math.ceil(start.getMinutes() / 15) * 15, 0, 0);
   var end = new Date(start.getTime() + (7 * 24 * 60 * 60 * 1000));
   form.elements.start_at.value = localDateTime(start);
   form.elements.end_at.value = localDateTime(end);
   form.elements.max_participants.value = '100';
   form.elements.status.value = 'draft';
  }
  updateLogoPreview(c && c.project_logo ? c.project_logo : '');
  updateCoverPreview(c && c.project_cover ? c.project_cover : '');
  updateStatusHelp();
  modal.classList.add('show');
  setTimeout(function () { form.elements.campaign_name.focus(); }, 50);
 }
 function close() { modal.classList.remove('show'); }
 function updateStatusHelp() {
  if (statusHelp) statusHelp.textContent = statusMessages[form.elements.status.value] || '';
 }
 function showError(error) {
  if (window.showToast) showToast(error.message || String(error), 'error');
  else window.alert(error.message || String(error));
 }
 document.querySelectorAll('#newCampaign, [data-create-campaign]').forEach(function (button) {
  button.onclick = function () { open(null); };
 });
 document.getElementById('closeCampaign').onclick = close;
 document.getElementById('cancelCampaign').onclick = close;
 form.elements.status.addEventListener('change', updateStatusHelp);
 logoFile.addEventListener('change', function () {
  var file = logoFile.files && logoFile.files[0];
  if (!file) { updateLogoPreview(logoUrl.value.trim()); return; }
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
   logoFile.value = '';
   showError(new Error('Choose a JPG, PNG, or WebP logo up to 2 MB.'));
   updateLogoPreview(logoUrl.value.trim());
   return;
  }
  logoObjectUrl = URL.createObjectURL(file);
  var image = document.createElement('img');
  logoPreview.innerHTML = '';
  image.src = logoObjectUrl;
  image.alt = 'Selected campaign logo';
  logoPreview.appendChild(image);
 });
 logoUrl.addEventListener('input', function () {
  if (!logoFile.files || !logoFile.files.length) updateLogoPreview(logoUrl.value.trim());
 });
 coverFile.addEventListener('change', function () {
  var file = coverFile.files && coverFile.files[0];
  if (!file) { updateCoverPreview(coverUrl.value.trim()); return; }
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
   coverFile.value = '';
   showError(new Error('Choose a JPG, PNG, or WebP cover up to 5 MB.'));
   updateCoverPreview(coverUrl.value.trim());
   return;
  }
  coverObjectUrl = URL.createObjectURL(file);
  var image = document.createElement('img');
  coverPreview.innerHTML = '';
  image.src = coverObjectUrl;
  image.alt = 'Selected campaign cover';
  coverPreview.appendChild(image);
 });
 coverUrl.addEventListener('input', function () {
  if (!coverFile.files || !coverFile.files.length) updateCoverPreview(coverUrl.value.trim());
 });
 document.querySelectorAll('.campaign-edit').forEach(function (button) {
  button.onclick = function () { open(JSON.parse(button.dataset.json)); };
 });
 document.querySelectorAll('.campaign-status').forEach(function (button) {
  button.onclick = function () {
   var action = button.textContent.trim().toLowerCase();
   var warning = action === 'complete'
    ? 'Complete this campaign? New participation will stop. This is intended for campaigns that are finished.'
    : action.charAt(0).toUpperCase() + action.slice(1) + ' this campaign?';
   if (!window.confirm(warning)) return;
   var data = new FormData();
   data.append('action_type', 'status');
   data.append('id', button.dataset.id);
   data.append('status', button.dataset.status);
   fetch(api, {method: 'POST', body: data}).then(function (r) { return r.json(); }).then(function (result) {
    if (!result.success) throw new Error(result.error || 'Status update failed.');
    location.reload();
   }).catch(showError);
  };
 });
 form.onsubmit = function (event) {
  event.preventDefault();
  if (new Date(form.elements.end_at.value) <= new Date(form.elements.start_at.value)) {
   showError(new Error('End date must be later than the start date.'));
   form.elements.end_at.focus();
   return;
  }
  var saveButton = form.querySelector('[type=submit]');
  var originalLabel = saveButton.innerHTML;
  saveButton.disabled = true;
  saveButton.innerHTML = '<i class=\'fas fa-spinner fa-spin\'></i> Saving...';
  var data = new FormData(form);
  data.append('action_type', 'save');
  fetch(api, {method: 'POST', body: data}).then(function (r) { return r.json(); }).then(function (result) {
   if (!result.success) throw new Error(result.error || 'Save failed.');
   if (window.showToast) showToast('Campaign saved successfully.', 'success');
   setTimeout(function () { location.reload(); }, 350);
  }).catch(function (error) {
   saveButton.disabled = false;
   saveButton.innerHTML = originalLabel;
   showError(error);
  });
 };
 document.getElementById('closeView').onclick = function () { view.classList.remove('show'); };
 document.querySelectorAll('.campaign-view').forEach(function (button) {
  button.onclick = function () {
   view.classList.add('show');
   document.getElementById('campaignAnalytics').innerHTML = '<p><i class=\'fas fa-spinner fa-spin\'></i> Loading campaign results...</p>';
   fetch(api + '?action=analytics&id=' + encodeURIComponent(button.dataset.id)).then(function (r) { return r.json(); }).then(function (result) {
    if (!result.success) throw new Error(result.error);
    renderAnalytics(result.data);
   }).catch(function (error) { document.getElementById('campaignAnalytics').textContent = error.message; });
  };
 });
 function renderAnalytics(data) {
  var s = data.summary;
  var html = '<h4>' + escapeHtml(data.campaign.campaign_name) + '</h4><p class=campaign-help>' + escapeHtml(data.campaign.project_name) + ' · Unique approved users count once, even across multiple tasks.</p>';
  html += '<div class=campaign-analytics-grid>';
  html += metric('Approved participants', s.unique_approved_participants + ' / ' + s.maximum_participants);
  html += metric('Slots remaining', s.remaining_participant_slots);
  html += metric('Capacity used', s.capacity_utilization_percent + '%');
  html += metric('All submissions', s.total_submissions);
  html += metric('Approval rate', s.approval_rate + '%');
  html += metric('Rewards issued', s.total_rewards_issued + ' $REX');
  html += '</div><h4>Results by task</h4>';
  if (!data.tasks.length) {
   html += '<p class=campaign-empty>No BoostHub tasks are attached yet. Open BoostHub Management to attach one.</p>';
  } else {
   data.tasks.forEach(function (t) {
    html += '<div class=campaign-metric><strong>' + escapeHtml(t.title) + '</strong><p class=campaign-help>' + t.total_submissions + ' total · ' + t.approved + ' approved · ' + t.pending + ' pending · ' + t.rejected + ' rejected</p></div>';
   });
  }
  document.getElementById('campaignAnalytics').innerHTML = html;
 }
 function metric(label, value) {
  return '<div class=campaign-metric><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(String(value)) + '</strong></div>';
 }
 function escapeHtml(value) { var d = document.createElement('div'); d.textContent = value || ''; return d.innerHTML; }
 [modal, view].forEach(function (dialog) {
  dialog.addEventListener('click', function (event) {
   if (event.target === dialog) dialog.classList.remove('show');
  });
 });
 document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape') { modal.classList.remove('show'); view.classList.remove('show'); }
 });
})();
