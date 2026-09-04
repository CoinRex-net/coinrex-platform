<?php
$page_title = 'BoostHub Campaigns';
$activePage = 'boosthub-campaigns';
require_once __DIR__ . '/includes/header.php';
if (!canCurrentAdmin('moderate_tasks')) { http_response_code(403); exit('Access denied.'); }
$db = getDBConnection();
$campaigns = boostHubCampaignList($db);
?>
<style>
.campaign-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.campaign-form-grid .form-group{display:flex;flex-direction:column;gap:7px}
.campaign-form-grid .form-group:nth-last-child(1),.campaign-form-grid .form-group:nth-last-child(2){grid-column:1/-1}
.campaign-form-grid label{font-size:12px;font-weight:700;color:#cbd5e1}
.campaign-form-grid input,.campaign-form-grid select,.campaign-form-grid textarea{width:100%;border:1px solid rgba(148,163,184,.18);border-radius:10px;background:#0f172a;color:#f8fafc;padding:11px 12px;font:inherit}
.campaign-form-grid textarea{min-height:88px;resize:vertical}
.campaign-form-grid input:focus,.campaign-form-grid select:focus,.campaign-form-grid textarea:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.14)}
.dashboard-modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid rgba(148,163,184,.1)}
.campaign-help{margin:0;color:#94a3b8;font-size:11px;line-height:1.5}
.campaign-required{color:#f87171}
.campaign-guide{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px}
.campaign-guide-card{position:relative;padding:16px;border:1px solid rgba(148,163,184,.1);border-radius:14px;background:linear-gradient(145deg,rgba(15,23,42,.86),rgba(30,41,59,.52))}
.campaign-guide-number{display:inline-grid;place-items:center;width:28px;height:28px;border-radius:9px;background:#1d4ed8;color:#fff;font-size:12px;font-weight:800;margin-bottom:10px}
.campaign-guide-card strong{display:block;color:#f8fafc;font-size:13px;margin-bottom:4px}.campaign-guide-card p{margin:0;color:#94a3b8;font-size:11px;line-height:1.45}
.campaign-guide-link{display:inline-flex;margin-top:9px;color:#93c5fd;font-size:11px;font-weight:700;text-decoration:none}.campaign-guide-link:hover{color:#bfdbfe}
.campaign-empty{text-align:center;padding:44px 20px;color:#94a3b8}.campaign-empty i{font-size:32px;color:#3b82f6;margin-bottom:12px}.campaign-empty strong{display:block;color:#e2e8f0;margin-bottom:6px}
.campaign-status{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:capitalize;background:rgba(148,163,184,.12);color:#cbd5e1}
.campaign-status.is-active{background:rgba(34,197,94,.14);color:#86efac}.campaign-status.is-paused{background:rgba(245,158,11,.14);color:#fcd34d}.campaign-status.is-completed{background:rgba(96,165,250,.14);color:#93c5fd}
.campaign-modal-intro{margin:0;padding:12px 14px;border-radius:11px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.16);color:#bfdbfe;font-size:12px;line-height:1.5;grid-column:1/-1}
.campaign-logo-field{display:grid;grid-template-columns:76px 1fr;gap:12px;align-items:center}.campaign-logo-preview{width:76px;height:76px;display:grid;place-items:center;border:1px dashed rgba(148,163,184,.28);border-radius:14px;background:#0b1220;color:#64748b;overflow:hidden}.campaign-logo-preview img{width:100%;height:100%;object-fit:cover}.campaign-logo-inputs{display:flex;flex-direction:column;gap:8px}.campaign-logo-inputs input[type=file]{padding:8px;font-size:11px}.campaign-logo-or{color:#64748b;font-size:10px;font-weight:800;text-align:center;text-transform:uppercase;letter-spacing:.08em}
.campaign-cover-group{grid-column:1/-1}.campaign-cover-preview{position:relative;width:100%;aspect-ratio:3/1;max-height:190px;display:grid;place-items:center;border:1px dashed rgba(148,163,184,.28);border-radius:14px;background:linear-gradient(135deg,#0b1220,#172554);color:#64748b;overflow:hidden}.campaign-cover-preview img{width:100%;height:100%;object-fit:cover}.campaign-cover-controls{display:grid;grid-template-columns:1fr auto 1fr;gap:10px;align-items:center;margin-top:9px}.campaign-cover-controls input[type=file]{padding:8px;font-size:11px}
.campaign-analytics-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:18px}.campaign-metric{padding:13px;border-radius:12px;background:rgba(15,23,42,.72);border:1px solid rgba(148,163,184,.1)}.campaign-metric span{display:block;color:#94a3b8;font-size:10px;text-transform:uppercase;letter-spacing:.05em}.campaign-metric strong{display:block;color:#f8fafc;font-size:20px;margin-top:5px}
@media(max-width:720px){.campaign-form-grid{grid-template-columns:1fr}.campaign-form-grid .form-group{grid-column:1}.campaign-cover-controls{grid-template-columns:1fr}.campaign-logo-or{text-align:left}}
@media(max-width:900px){.campaign-guide{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.campaign-guide,.campaign-analytics-grid{grid-template-columns:1fr}}
</style>
<div class='dashboard-header'>
 <div class='dashboard-header-left'><div class='dashboard-header-icon'><i class='fas fa-bullhorn'></i></div>
 <div class='dashboard-header-text'><h1>Partner Campaigns</h1><p>Create, launch, pause, complete, and measure BoostHub campaigns.</p></div></div>
</div>
<section class='campaign-guide' aria-label='How partner campaigns work'>
 <div class='campaign-guide-card'><span class='campaign-guide-number'>1</span><strong>Create the campaign</strong><p>Add the partner name, dates, and participant limit. Keep it as a draft while preparing.</p></div>
 <div class='campaign-guide-card'><span class='campaign-guide-number'>2</span><strong>Attach BoostHub tasks</strong><p>Open BoostHub Management and choose this campaign when creating or editing a task.</p><a class='campaign-guide-link' href='<?php echo ADMIN_BASE_URL; ?>/boosthub.php'>Open task manager <i class='fas fa-arrow-right'></i></a></div>
 <div class='campaign-guide-card'><span class='campaign-guide-number'>3</span><strong>Launch and review</strong><p>Set it to Active. Users submit normal evidence and admins review it manually.</p></div>
 <div class='campaign-guide-card'><span class='campaign-guide-number'>4</span><strong>Track and finish</strong><p>Use View for results. Pause temporarily or complete the campaign when it ends.</p></div>
</section>
<div class='dashboard-modal' id='campaignModal'><div class='dashboard-modal-card'>
 <div class='dashboard-modal-header'><h3 id='campaignFormTitle'>Create Campaign</h3><button class='dashboard-modal-close' type='button' id='closeCampaign'>&times;</button></div>
 <form id='campaignForm'><div class='dashboard-modal-body campaign-form-grid'>
  <input type='hidden' name='id' value='0'>
  <p class='campaign-modal-intro'><i class='fas fa-circle-info'></i> Start with Draft if you are still preparing tasks. Nothing becomes available to users until the campaign is Active and within its dates.</p>
  <div class='form-group'><label>Campaign name <span class='campaign-required'>*</span></label><input name='campaign_name' maxlength='150' placeholder='Example: CoinRex Community Launch' required><p class='campaign-help'>The public name users and reviewers will recognize.</p></div>
  <div class='form-group'><label>Partner or project name <span class='campaign-required'>*</span></label><input name='project_name' maxlength='150' placeholder='Example: CoinRex Labs' required><p class='campaign-help'>The company, token, or community sponsoring the tasks.</p></div>
  <div class='form-group'><label>Project website <span class='campaign-help'>(optional)</span></label><input name='project_website' type='url' placeholder='https://example.com'><p class='campaign-help'>Use the full HTTP(S) address.</p></div>
  <div class='form-group'><label>Project logo <span class='campaign-help'>(optional)</span></label><div class='campaign-logo-field'><div class='campaign-logo-preview' id='campaignLogoPreview'><i class='fas fa-image'></i></div><div class='campaign-logo-inputs'><input name='project_logo_file' type='file' accept='image/jpeg,image/png,image/webp'><span class='campaign-logo-or'>or use an image URL</span><input name='project_logo' type='url' placeholder='https://example.com/logo.png'></div></div><p class='campaign-help'>Upload JPG, PNG, or WebP up to 2 MB. A new upload replaces the URL value.</p></div>
  <div class='form-group campaign-cover-group'><label>Campaign cover image <span class='campaign-help'>(optional)</span></label><div class='campaign-cover-preview' id='campaignCoverPreview'><div><i class='fas fa-panorama'></i> X-style campaign cover</div></div><div class='campaign-cover-controls'><input name='project_cover_file' type='file' accept='image/jpeg,image/png,image/webp'><span class='campaign-logo-or'>or</span><input name='project_cover' type='url' placeholder='https://example.com/cover.jpg'></div><p class='campaign-help'>Recommended 1500 × 500 (3:1). Upload JPG, PNG, or WebP up to 5 MB.</p></div>
  <div class='form-group'><label>Short public description</label><textarea name='short_description' maxlength='1000' placeholder='Briefly explain what this campaign is about.'></textarea><p class='campaign-help'>Keep it short and avoid private information.</p></div>
  <div class='form-group'><label>Start date and time <span class='campaign-required'>*</span></label><input name='start_at' type='datetime-local' required><p class='campaign-help'>Users cannot participate before this time.</p></div>
  <div class='form-group'><label>End date and time <span class='campaign-required'>*</span></label><input name='end_at' type='datetime-local' required><p class='campaign-help'>New participation stops after this time.</p></div>
  <div class='form-group'><label>Maximum approved participants <span class='campaign-required'>*</span></label><input name='max_participants' type='number' min='1' step='1' placeholder='100' required><p class='campaign-help'>One approved user counts once, even after completing several tasks.</p></div>
  <div class='form-group'><label>Campaign status <span class='campaign-required'>*</span></label><select name='status'><option value='draft'>Draft — still preparing</option><option value='scheduled'>Scheduled — waiting to launch</option><option value='active'>Active — open during campaign dates</option><option value='paused'>Paused — temporarily closed</option><option value='completed'>Completed — permanently finished</option></select><p class='campaign-help' id='campaignStatusHelp'>Draft is the safest choice while setting up.</p></div>
  <div class='form-group'><label>Private admin notes <span class='campaign-help'>(optional)</span></label><textarea name='internal_notes' maxlength='5000' placeholder='Partner contact, approval notes, or internal reminders...'></textarea><p class='campaign-help'><i class='fas fa-lock'></i> Only authorized admins can see these notes.</p></div>
 </div><div class='dashboard-modal-footer'><button class='btn btn-secondary' type='button' id='cancelCampaign'>Cancel</button><button class='btn btn-primary' type='submit'>Save Campaign</button></div></form>
</div></div>
<div class='dashboard-modal' id='campaignView'><div class='dashboard-modal-card'><div class='dashboard-modal-header'><h3>Campaign Analytics</h3><button class='dashboard-modal-close' type='button' id='closeView'>&times;</button></div><div class='dashboard-modal-body' id='campaignAnalytics'>Loading...</div></div></div>
<div class='dashboard-panel'>
 <div class='dashboard-panel-header'><div><h3><i class='fas fa-rectangle-list'></i> Your campaigns <span class='panel-badge'><?php echo count($campaigns); ?></span></h3><p class='campaign-help'>Create the campaign here, then attach tasks from BoostHub Management.</p></div><button class='btn btn-primary' type='button' id='newCampaign'><i class='fas fa-plus'></i> Create Campaign</button></div>
 <div class='dashboard-table-wrap'><table class='dashboard-table'><thead><tr><th>Campaign</th><th>Status</th><th>Participants</th><th>Tasks</th><th>Actions</th></tr></thead><tbody>
 <?php foreach ($campaigns as $c): ?>
 <?php $effective_state = boostHubCampaignEffectiveState($c); ?>
 <tr><td><strong><?php echo htmlspecialchars($c['campaign_name']); ?></strong><br><span class='muted'><?php echo htmlspecialchars($c['project_name']); ?></span><br><small class='muted'><time data-campaign-local-datetime='<?php echo htmlspecialchars(boostHubCampaignClientDateTime((string) $c['start_at']), ENT_QUOTES); ?>'><?php echo htmlspecialchars(date('M j, Y g:i A', boostHubCampaignTimestamp((string) $c['start_at']))); ?></time> to <time data-campaign-local-datetime='<?php echo htmlspecialchars(boostHubCampaignClientDateTime((string) $c['end_at']), ENT_QUOTES); ?>'><?php echo htmlspecialchars(date('M j, Y g:i A', boostHubCampaignTimestamp((string) $c['end_at']))); ?></time></small></td>
 <td><span class='campaign-status is-<?php echo htmlspecialchars($effective_state); ?>'><i class='fas fa-circle'></i> <?php echo htmlspecialchars($effective_state); ?></span></td>
 <td><?php echo (int) $c['approved_participants']; ?> / <?php echo (int) $c['max_participants']; ?></td>
 <td><?php echo (int) $c['task_count']; ?></td>
 <td><button class='btn btn-secondary btn-sm campaign-view' data-id='<?php echo (int) $c['id']; ?>'>View</button> <button class='btn btn-secondary btn-sm campaign-edit' data-json='<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES); ?>'>Edit</button> <?php if ($c['status'] === 'active'): ?><button class='btn btn-secondary btn-sm campaign-status' data-id='<?php echo (int) $c['id']; ?>' data-status='paused'>Pause</button><?php elseif ($c['status'] === 'paused'): ?><button class='btn btn-primary btn-sm campaign-status' data-id='<?php echo (int) $c['id']; ?>' data-status='active'>Resume</button><?php endif; ?> <?php if ($c['status'] !== 'completed'): ?><button class='btn btn-secondary btn-sm campaign-status' data-id='<?php echo (int) $c['id']; ?>' data-status='completed'>Complete</button><?php endif; ?></td></tr>
 <?php endforeach; ?>
 <?php if (!$campaigns): ?><tr><td colspan='5' class='campaign-empty'><i class='fas fa-bullhorn'></i><strong>No campaigns yet</strong><p>Create your first campaign as a Draft. You can safely add tasks before launching it.</p><button class='btn btn-primary' type='button' data-create-campaign><i class='fas fa-plus'></i> Create my first campaign</button></td></tr><?php endif; ?>
 </tbody></table></div>
</div>
<script src='<?php echo ADMIN_BASE_URL; ?>/assets/js/boosthub-campaigns.js?v=<?php echo filemtime(__DIR__ . '/assets/js/boosthub-campaigns.js') ?: time(); ?>'></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
