(function(){
  const config=window.coinrexEngagement||{};
  const qs=(id)=>document.getElementById(id);
  const announcement=qs('announcementModal');
  const gate=qs('socialGateModal');
  const message=qs('socialGateMessage');
  const submit=qs('socialSubmitButton');
  const cta=qs('socialCta');
  let ctaClicked=Boolean(config.ctaAlreadyClicked);
  let announcementClosed=false;

  function setMessage(text,type){
    if(!message)return;
    message.textContent=text||'';
    message.className='eng-message'+(type?' is-'+type:'');
  }
  function markStep(step){
    document.querySelectorAll('[data-eng-step]').forEach((node)=>{
      const number=Number(node.dataset.engStep);
      node.classList.toggle('is-done',number<step);
      node.classList.toggle('is-active',number===step);
    });
  }
  function syncSubmit(){
    if(!submit)return;
    const form=qs('socialEvidenceForm');
    const file=form?.querySelector('[name="screenshot"]');
    submit.disabled=!ctaClicked||!file?.files?.length;
  }
  if(ctaClicked){markStep(2);}

  function closeAnnouncement(){
    if(announcementClosed)return;
    announcementClosed=true;
    const forever=qs('announcementForever')?.checked?'1':'';
    if(announcement)announcement.hidden=true;
    if(gate){gate.hidden=false;gate.querySelector('button,input')?.focus();}
    if(!config.dismissEndpoint)return;
    fetch(config.dismissEndpoint,{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:config.csrf,announcement_id:String(config.announcementId||0),forever})}).catch(()=>{});
  }

  qs('announcementCloseSecondary')?.addEventListener('click',closeAnnouncement);
  qs('announcementClose')?.addEventListener('click',closeAnnouncement);

  cta?.addEventListener('click',async function(){
    const original=this.innerHTML;
    const channelWindow=window.open('about:blank','_blank');
    this.disabled=true;this.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Opening channel...';setMessage('');
    try{
      const response=await fetch(config.ctaEndpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:config.csrf,assignment_id:String(config.assignmentId||0)})});
      const data=await response.json();
      if(!response.ok||!data.success)throw new Error(data.message||'Could not open the channel.');
      ctaClicked=true;markStep(2);syncSubmit();
      if(channelWindow){channelWindow.opener=null;channelWindow.location=data.url;}else{window.location.href=data.url;}
      setMessage('Channel opened. Follow or join it, then complete Step 2.','success');
      this.innerHTML='<i class="fas fa-check"></i> Channel opened';
    }catch(error){if(channelWindow)channelWindow.close();setMessage(error.message||'Please try again.','error');this.innerHTML=original;}
    finally{this.disabled=false;}
  });

  qs('socialScreenshot')?.addEventListener('change',function(){
    const label=qs('socialFileName');
    if(label)label.textContent=this.files?.[0]?.name||'JPG, PNG or WebP ºw^~)Þt maximum 5 MB';
    if(this.files?.length&&ctaClicked){markStep(3);}else{markStep(ctaClicked?2:1);}
    syncSubmit();
  });

  qs('socialEvidenceForm')?.addEventListener('submit',async function(event){
    event.preventDefault();
    if(!ctaClicked){setMessage('Please complete Step 1 first.','error');return;}
    const data=new FormData(this);data.set('csrf_token',config.csrf);data.set('assignment_id',String(config.assignmentId||0));
    const original=submit.innerHTML;submit.disabled=true;submit.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Uploading safely...';setMessage('Please keep this page open while your proof uploads.');
    try{
      const response=await fetch(config.evidenceEndpoint,{method:'POST',credentials:'same-origin',body:data});
      const result=await response.json();
      if(!response.ok||!result.success)throw new Error(result.message||'Proof could not be submitted.');
      markStep(4);setMessage('Done! Your dashboard is now unlocked while the admin reviews your proof.','success');submit.innerHTML='<i class="fas fa-check"></i> Proof submitted';
      window.setTimeout(()=>window.location.reload(),1200);
    }catch(error){setMessage(error.message||'Please check your details and try again.','error');submit.innerHTML=original;syncSubmit();}
  });
  syncSubmit();
})();
