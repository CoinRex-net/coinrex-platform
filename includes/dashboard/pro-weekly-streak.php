<?php
$weekly_state = (array) $pro_weekly_streak_state;
$weekly_completed = array_map('intval', (array) ($weekly_state['completed_days'] ?? []));
$weekly_status = (string) ($weekly_state['status'] ?? 'ready');
$weekly_can_checkin = !empty($weekly_state['can_checkin']);
$weekly_box_pending = !empty($weekly_state['box_pending']);
?>
<section class="card pro-weekly" id="proWeeklyStreakCard" aria-labelledby="pro-weekly-title">
 <div class="pro-weekly__glow"></div>
 <header class="pro-weekly__header">
  <div class="pro-weekly__heading">
   <span class="pro-weekly__icon"><i class="fas fa-fire"></i></span>
   <div><span class="pro-weekly__eyebrow">PRO weekly rewards</span>
    <h2 id="pro-weekly-title">7-Day Check-in Streak</h2>
    <p id="proWeeklyMessage"><?php echo htmlspecialchars((string)($weekly_state['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
   </div>
  </div>
  <div class="pro-weekly__meta">
   <?php if(!empty($weekly_state['test_mode'])): ?><span class="pro-weekly__test-badge"><i class="fas fa-flask"></i> Test mode</span><?php endif; ?>
   <div class="pro-weekly__cycle"><span>Cycle</span><strong id="proWeeklyCycle"><?php echo (int)($weekly_state['cycle_number'] ?? 1); ?></strong></div>
  </div>
 </header>
 <div class="pro-weekly__days" id="proWeeklyDays" aria-label="Weekly streak progress">
  <?php foreach ((array)($weekly_state['daily_rewards'] ?? []) as $item): ?>
   <?php $day=(int)$item['day']; $done=in_array($day,$weekly_completed,true); $next=$weekly_can_checkin && $day===(int)($weekly_state['next_day']??1); ?>
   <div class="pro-weekly__day<?php echo $done?' is-complete':($next?' is-next':''); ?>">
    <span class="pro-weekly__day-check"><i class="fas <?php echo $done?'fa-check':'fa-calendar-day'; ?>"></i></span>
    <span>Day <?php echo $day; ?></span><strong>+<?php echo (int)$item['reward']; ?> $REX</strong>
    <?php if($day===7): ?><small>+ Mystery Box</small><?php endif; ?>
   </div>
  <?php endforeach; ?>
 </div>
 <footer class="pro-weekly__footer">
  <div class="pro-weekly__next">
   <span><?php echo $weekly_box_pending?'Reward unlocked':($weekly_status==='waiting_reset'?'Next cycle':'Next reward'); ?></span>
   <strong id="proWeeklyNextReward"><?php echo $weekly_box_pending?'10-20 $REX box':($weekly_status==='waiting_reset'?'After reset':'+'.(int)($weekly_state['today_reward']??1).' $REX'); ?></strong>
   <small id="proWeeklyCountdown" data-reset-at="<?php echo htmlspecialchars((string)($weekly_state['next_reset_at']??''),ENT_QUOTES,'UTF-8'); ?>"></small>
  </div>
  <div class="pro-weekly__actions">
   <p class="pro-weekly__feedback" id="proWeeklyFeedback" role="status" aria-live="polite"></p>
   <button type="button" class="pro-weekly__button" id="proWeeklyAction"
    data-action="<?php echo $weekly_box_pending?'open_box':'checkin'; ?>"
    <?php echo (!$weekly_box_pending && !$weekly_can_checkin)?'disabled':''; ?>>
    <i class="fas <?php echo $weekly_box_pending?'fa-gift':($weekly_can_checkin?'fa-calendar-check':'fa-circle-check'); ?>"></i>
    <span><?php echo $weekly_box_pending?'Open Mystery Box':($weekly_can_checkin?'Check In - Earn '.(int)($weekly_state['today_reward']??1).' $REX':'Checked In Today'); ?></span>
   </button>
  </div>
 </footer>
</section>

<div class="pro-box-modal" id="proWeeklyBoxModal" hidden>
 <div class="pro-box-modal__backdrop" data-pro-box-close></div>
 <section class="pro-box-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pro-box-title">
  <button type="button" class="pro-box-modal__close" data-pro-box-close aria-label="Close"><i class="fas fa-xmark"></i></button>
  <span class="pro-box-modal__eyebrow">Seven days complete</span>
  <h2 id="pro-box-title">Your Mystery Box Is Ready</h2>
  <p id="proBoxDescription">Reveal your secure server-generated reward between 10 and 20 $REX.</p>
  <div class="pro-box-vault" id="proBoxVault" aria-hidden="true">
   <div class="pro-box-vault__shine"></div><i class="fas fa-gift"></i><span>?</span>
  </div>
  <div class="pro-box-result" id="proBoxResult" hidden>
   <span>You won</span><strong><span id="proBoxReward">0</span> $REX</strong>
   <small>Your reward has been added to your available balance.</small>
  </div>
  <button type="button" class="pro-box-modal__reveal" id="proBoxReveal">
   <i class="fas fa-wand-magic-sparkles"></i><span>Reveal Reward</span>
  </button>
 </section>
</div>
<script>
window.coinrexProWeeklyStreak=<?php echo json_encode([
 'endpoint'=>BASE_URL.'/api/pro_weekly_streak.php',
 'csrf'=>appCsrfToken(),
 'state'=>$weekly_state,
],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>;
</script>
