/** Progressive enhancement for the smaller archives. Content remains visible without JS. */
(() => {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)');
    const reveal = element => {
        if (!reduce.matches && element.animate) element.animate([{opacity:0, transform:'translateY(8px)'},{opacity:1,transform:'translateY(0)'}],{duration:360,easing:'cubic-bezier(.2,.7,.2,1)'});
    };
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.isIntersecting) { reveal(entry.target); observer.unobserve(entry.target); }
        }),{threshold:.08});
        document.querySelectorAll('.ui-collection .ui-feed-card,.ui-collection .ui-card,.ui-collection .ui-more-card,.ui-collection .comedy-social-card').forEach(card=>observer.observe(card));
    }
    document.querySelectorAll('[data-collection]').forEach(collection => {
        const input=collection.querySelector('[data-collection-search]');
        const buttons=[...collection.querySelectorAll('[data-filter]')];
        const cards=[...collection.querySelectorAll('[data-collection-item]')];
        const status=collection.querySelector('[data-collection-status]');
        const empty=collection.querySelector('[data-collection-empty]');
        const normalize=text=>text.normalize('NFKD').replace(/\p{M}/gu,'').toLowerCase();
        const text=new Map(cards.map(card=>[card,normalize(card.textContent)]));
        let category='all';
        function filter(){
            const words=normalize(input?.value||'').trim().split(/\s+/).filter(Boolean);
            let count=0;
            for(const card of cards){
                const visible=(category==='all'||card.dataset.category===category)&&words.every(word=>text.get(card).includes(word));
                card.hidden=!visible;if(visible)count++;
            }
            buttons.forEach(button=>{const active=button.dataset.filter===category;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',String(active));});
            if(status)status.textContent=`${count} of ${cards.length} entries`;
            if(empty)empty.hidden=count!==0;
        }
        buttons.forEach(button=>button.addEventListener('click',()=>{category=button.dataset.filter;filter();}));
        input?.addEventListener('input',filter);
        collection.querySelector('[data-collection-reset]')?.addEventListener('click',()=>{category='all';if(input)input.value='';filter();input?.focus();});
        const showTarget=()=>{
            let id;try{id=decodeURIComponent(location.hash.slice(1));}catch{return;}
            const target=document.getElementById(id);
            if(target&&cards.includes(target)){category='all';if(input)input.value='';filter();target.scrollIntoView({block:'start',behavior:'instant'});}
        };
        collection.querySelectorAll('.ui-collection-index a').forEach(link=>link.addEventListener('click',()=>{category='all';if(input)input.value='';filter();}));
        addEventListener('hashchange',showTarget);filter();showTarget();
    });
    // Tenor is loaded by an explicit control, keeping the first render light.
    const loadMemes=document.querySelector('[data-load-memes]');
    loadMemes?.addEventListener('click',()=>{
        const script=document.createElement('script');script.src='https://tenor.com/embed.js';script.async=true;
        loadMemes.disabled=true;loadMemes.textContent='Loading animations…';
        script.onload=()=>{loadMemes.textContent='Animations loaded';document.querySelector('.comedy-memes')?.classList.add('is-loaded');};
        script.onerror=()=>{loadMemes.disabled=false;loadMemes.textContent='Try loading animations again';};
        document.head.append(script);
    });
})();
