(function(){
 'use strict';
 const cfg=window.coinrexProWeeklyStreak;
 if(!cfg)return;
 const q=id=>document.getElementById(id);
 const card=q('proWeeklyStreakCard'),days=q('proWeeklyDays'),message=q('proWeeklyMessage');
 const cycle=q('proWeeklyCycle'),action=q('proWeeklyAction'),feedback=q('proWeeklyFeedback');
 const nextReward=q('proWeeklyNextReward'),countdown=q('proWeeklyCountdown');
 const modal=q('proWeeklyBoxModal'),reveal=q('proBoxReveal'),result=q('proBoxResult');
 let state=cfg.state||{},busy=false,timer=null,revealed=false;

 if(card&&window.matchMedia('(pointer:fine)').matches){
  card.addEventListener('pointermove',event=>{
   const rect=card.getBoundingClientRect();
   card.style.setProperty('--streak-x',((event.clientX-rect.left)/rect.width*100).toFixed(1)+'%');
   card.style.setProperty('--streak-y',((event.clientY-rect.top)/rect.height*100).toFixed(1)+'%');
  });
  card.addEventListener('pointerleave',()=>{
   card.style.setProperty('--streak-x','82%');card.style.setProperty('--streak-y','12%');
  });
 }

 function esc(value){const el=document.createElement('span');el.textContent=String(value);return el.innerHTML;}
 function post(actionName){
  return fetch(cfg.endpoint,{method:'POST',credentials:'same-origin',
   headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
   body:new URLSearchParams({action:actionName,csrf_token:cfg.csrf})
  }).then(async response=>{const data=await response.json().catch(()=>({}));
   if(!response.ok||!data.success)throw new Error(data.message||'Request could not be completed.');
   return data;
  });
 }
 function updateBalance(value){
  const amount=Number(value||0).toFixed(2);
  document.querySelectorAll('.hero-balance-number').forEach(el=>el.textContent=amount);
 }
 function renderDays(){
  const completed=(state.completed_days||[]).map(Number),next=Number(state.next_day||1);
  days.innerHTML=(state.daily_rewards||[]).map(item=>{
   const day=Number(item.day),done=completed.includes(day),isNext=state.can_checkin&&day===next;
   return '<div class="pro-weekly__day'+(done?' is-complete':(isNext?' is-next':''))+'">'+
    '<span class="pro-weekly__day-check"><i class="fas '+(done?'fa-check':'fa-calendar-day')+'"></i></span>'+
    '<span>Day '+day+'</span><strong>+'+Number(item.reward)+' $REX</strong>'+
    (day===7?'<small>+ Mystery Box</small>':'')+'</div>';
  }).join('');
 }
 function render(){
  renderDays(); message.textContent=state.message||''; cycle.textContent=String(state.cycle_number||1);
  feedback.textContent='';
  if(state.box_pending){
   nextReward.textContent='10-20 $REX box'; action.dataset.action='open_box'; action.disabled=false;
   action.innerHTML='<i class="fas fa-gift"></i><span>Open Mystery Box</span>';
  }else if(state.can_checkin){
   nextReward.textContent='+'+Number(state.today_reward||1)+' $REX'; action.dataset.action='checkin'; action.disabled=false;
   action.innerHTML='<i class="fas fa-calendar-check"></i><span>Check In - Earn '+Number(state.today_reward||1)+' $REX</span>';
  }else{
   nextReward.textContent=state.status==='waiting_reset'?'After reset':'Secured today';
   action.dataset.action='checkin'; action.disabled=true;
   action.innerHTML='<i class="fas fa-circle-check"></i><span>'+(state.status==='waiting_reset'?'Next Cycle Soon':'Checked In Today')+'</span>';
  }
  countdown.dataset.resetAt=state.next_reset_at||''; startCountdown();
 }
 function startCountdown(){
  if(timer)clearInterval(timer);
  const draw=()=>{
   const raw=countdown.dataset.resetAt;
   if(!raw){countdown.textContent='';return;}
   const target=new Date(raw.replace(' ','T')).getTime(),left=Math.max(0,target-Date.now());
   const total=Math.floor(left/1000),h=Math.floor(total/3600),m=Math.floor((total%3600)/60),s=total%60;
   countdown.textContent=left>0?'Next reset in '+String(h).padStart(2,'0')+'h '+String(m).padStart(2,'0')+'m '+String(s).padStart(2,'0')+'s':'Reset available';
   if(left<=0){clearInterval(timer);window.setTimeout(refreshState,800);}
  };draw();timer=setInterval(draw,1000);
 }
 function refreshState(){
  fetch(cfg.endpoint,{credentials:'same-origin'}).then(r=>r.json()).then(data=>{
   if(data.success){state=data.state;render();}
  }).catch(()=>{});
 }
 function openModal(){
  modal.hidden=false;document.body.style.overflow='hidden';
  if(!revealed){modal.classList.remove('is-opening','is-revealed');result.hidden=true;
   reveal.disabled=false;reveal.innerHTML='<i class="fas fa-wand-magic-sparkles"></i><span>Reveal Reward</span>';}
  reveal.focus();
 }
 function closeModal(){modal.hidden=true;document.body.style.overflow='';}
 action.addEventListener('click',async()=>{
  if(action.dataset.action==='open_box'){openModal();return;}
  if(busy||action.disabled)return;busy=true;const original=action.innerHTML;action.disabled=true;
  action.innerHTML='<i class="fas fa-circle-notch fa-spin"></i><span>Checking in...</span>';
  try{
   const data=await post('checkin');state=data.state;updateBalance(data.balance);render();
   card.classList.remove('is-rewarded');void card.offsetWidth;card.classList.add('is-rewarded');
   window.setTimeout(()=>card.classList.remove('is-rewarded'),750);
   feedback.textContent='+'+Number(data.reward)+' $REX added.';
   if(data.box_unlocked)window.setTimeout(openModal,350);
  }catch(error){feedback.textContent=error.message;action.disabled=false;action.innerHTML=original;}
  finally{busy=false;}
 });
 reveal.addEventListener('click',async()=>{
  if(revealed){closeModal();return;}if(busy)return;busy=true;reveal.disabled=true;
  reveal.innerHTML='<i class="fas fa-circle-notch fa-spin"></i><span>Opening securely...</span>';
  modal.classList.add('is-opening');
  try{
   const data=await post('claim_box');
   await new Promise(resolve=>setTimeout(resolve,600));
   state=data.state;revealed=true;modal.classList.remove('is-opening');modal.classList.add('is-revealed');
   q('proBoxReward').textContent=String(parseInt(data.reward,10));result.hidden=false;
   reveal.disabled=false;reveal.innerHTML='<i class="fas fa-check"></i><span>Continue</span>';
   updateBalance(data.balance);render();
  }catch(error){
   modal.classList.remove('is-opening');reveal.disabled=false;
   reveal.innerHTML='<i class="fas fa-rotate-right"></i><span>Try Again</span>';
   feedback.textContent=error.message;
  }finally{busy=false;}
 });
 modal.querySelectorAll('[data-pro-box-close]').forEach(el=>el.addEventListener('click',closeModal));
 document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!modal.hidden)closeModal();});
 render();
})();
