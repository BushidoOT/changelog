document.addEventListener('DOMContentLoaded',()=>{
  const share=document.querySelector('[data-share]');
  if(share){
    const oldIcon=share.querySelector('svg');
    if(oldIcon){
      const image=document.createElement('img');
      image.src='/assets/share.svg';
      image.alt='';
      image.width=17;
      image.height=17;
      oldIcon.replaceWith(image);
    }
  }

  const toast=document.querySelector('.share-toast');
  const showToast=(text='Bağlantı kopyalandı')=>{
    if(!toast)return;
    toast.textContent=text;
    toast.classList.add('show');
    clearTimeout(window.__viohyToast);
    window.__viohyToast=setTimeout(()=>toast.classList.remove('show'),1800);
  };

  share?.addEventListener('click',async()=>{
    const url=share.dataset.url||location.href;
    const title=share.dataset.title||document.title;
    try{
      if(navigator.share){await navigator.share({title,url});return;}
      await navigator.clipboard.writeText(url);
      showToast();
    }catch(err){
      if(err?.name!=='AbortError')showToast('Bağlantı kopyalanamadı');
    }
  });

  document.querySelectorAll('.modern-link,.social-button,.card-action').forEach((el,i)=>{
    el.style.setProperty('--delay',`${Math.min(i*35,280)}ms`);
  });

  const details=document.querySelector('.contact-sheet');
  details?.addEventListener('toggle',()=>{
    if(details.open)setTimeout(()=>details.scrollIntoView({behavior:'smooth',block:'nearest'}),120);
  });
});
