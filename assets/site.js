/* Progressive enhancement only. Content, forms' fallback messages and links remain available without JS. */
(() => {
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  if (!reduced.matches && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('reveal-seen'); observer.unobserve(entry.target); }
    }), {threshold: .06});
    document.querySelectorAll('.section-top,.image-tile,.history-column,.film-card,.gallery-photo,.orwoco-post-grid>li').forEach(el => observer.observe(el));
  }
  // History story: words brighten as each passage scrolls through the screen. The text stays fully readable without JS.
  const passages = [...document.querySelectorAll('.history-column p:not(.history-number)')];
  if (!reduced.matches && passages.length) {
    const sets = passages.map(p => {
      const walker = document.createTreeWalker(p, NodeFilter.SHOW_TEXT), nodes = [];
      while (walker.nextNode()) nodes.push(walker.currentNode);
      nodes.forEach(node => {
        const frag = document.createDocumentFragment();
        node.textContent.split(/(\s+)/).forEach(part => {
          if (!part) return;
          if (/^\s+$/.test(part)) { frag.append(part); return; }
          const s = document.createElement('span'); s.className = 'w'; s.textContent = part; frag.append(s);
        });
        node.replaceWith(frag);
      });
      return {p, words: [...p.querySelectorAll('.w')], lit: 0};
    });
    document.documentElement.classList.add('orwoco-scrolltext');
    let queued = false;
    const paint = () => {
      queued = false;
      const vh = innerHeight;
      sets.forEach(set => {
        const r = set.p.getBoundingClientRect();
        // Starts as the passage enters the lower screen; complete once its end reaches the middle.
        const progress = Math.min(1, Math.max(0, (vh * .88 - r.top) / (r.height + vh * .38)));
        const lit = Math.round(progress * set.words.length);
        if (lit === set.lit) return;
        const [from, to, on] = lit > set.lit ? [set.lit, lit, true] : [lit, set.lit, false];
        for (let i = from; i < to; i++) set.words[i].classList.toggle('on', on);
        set.lit = lit;
      });
    };
    const queue = () => { if (!queued) { queued = true; requestAnimationFrame(paint); } };
    addEventListener('scroll', queue, {passive: true}); addEventListener('resize', queue); paint();
  }
  // Thin reading-progress line on articles.
  const bar = document.querySelector('.orwoco-progress span'), article = document.querySelector('.orwoco-article');
  if (bar && article && !reduced.matches) {
    let queued = false;
    const update = () => { queued = false; const r = article.getBoundingClientRect(); bar.style.setProperty('--p', Math.min(1, Math.max(0, -r.top / Math.max(1, r.height - innerHeight)))); };
    addEventListener('scroll', () => { if (!queued) { queued = true; requestAnimationFrame(update); } }, {passive: true}); update();
  }
  const dialog = document.createElement('dialog');
  dialog.className = 'orwoco-lightbox';
  dialog.setAttribute('aria-label', 'Community photograph');
  dialog.innerHTML = '<button type="button" aria-label="Close photograph">×</button><img alt=""><p></p>';
  document.body.append(dialog);
  dialog.querySelector('button').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
  document.querySelectorAll('.orwoco-gallery a').forEach(a => a.addEventListener('click', e => {
    if (!dialog.showModal || e.ctrlKey || e.metaKey) return;
    e.preventDefault(); const img = a.querySelector('img');
    dialog.querySelector('img').src = a.href; dialog.querySelector('img').alt = img.alt;
    dialog.querySelector('p').textContent = img.alt; dialog.showModal();
  }));
  // The native editable image link is the video source. No third-party request occurs before click.
  document.querySelectorAll('.orwoco-video a').forEach(a => a.addEventListener('click', e => {
    if (e.ctrlKey || e.metaKey) return;
    let url; try { url = new URL(a.href); } catch { return; }
    const id = url.searchParams.get('v');
    let src = '';
    if (['www.youtube.com','youtube.com'].includes(url.hostname) && /^[A-Za-z0-9_-]{11}$/.test(id || '')) src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1';
    if (url.hostname === 'vimeo.com' && /^\/\d+$/.test(url.pathname)) src = 'https://player.vimeo.com/video' + url.pathname + '?autoplay=1&dnt=1';
    if (!src) return;
    e.preventDefault(); const frame = document.createElement('iframe');
    frame.src = src;
    frame.title = a.querySelector('img')?.alt || 'ORWO film';
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
    frame.referrerPolicy = 'strict-origin-when-cross-origin'; frame.allowFullscreen = true;
    a.parentElement.replaceChildren(frame); frame.focus();
  }));
  document.querySelectorAll('[data-orwoco-form]').forEach(form => {
    const opened = performance.now();
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const status = form.querySelector('.form-status'), button = form.querySelector('[type=submit]');
      form.querySelector('[data-elapsed]').value = Math.round(performance.now() - opened);
      form.querySelectorAll('[aria-invalid]').forEach(x => x.removeAttribute('aria-invalid'));
      form.querySelectorAll('.field-error').forEach(x => x.textContent = '');
      button.disabled = true; const label = button.textContent; button.textContent = 'Sending…';
      try {
        const response = await fetch(form.getAttribute('action'), {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}, credentials:'same-origin'});
        const data = await response.json();
        status.replaceChildren(); const msg = document.createElement('p');
        msg.className = data.ok ? 'form-success' : 'form-error'; msg.textContent = data.message; status.append(msg);
        for (const [key,message] of Object.entries(data.errors || {})) {
          const input = form.elements.namedItem('orwoco_' + key);
          if (input) { input.setAttribute('aria-invalid','true'); input.closest('.field').querySelector('.field-error').textContent = message; }
        }
        if (data.ok) form.reset();
        else form.querySelector('[aria-invalid]')?.focus();
      } catch {
        status.textContent = 'Your message could not be confirmed. Please try again or email sales@filmotec.de.';
      } finally { button.disabled = false; button.textContent = label; }
    });
  });
})();
