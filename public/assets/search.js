(() => {
  const forms = document.querySelectorAll('.search-autocomplete, .header-search');
  forms.forEach((form) => {
    const input = form.querySelector('input[type="search"][name="q"]');
    if (!input) return;

    let box = form.querySelector('.autocomplete-results');
    if (!box) {
      const wrap = document.createElement('div');
      wrap.className = 'autocomplete-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
      box = document.createElement('div');
      box.className = 'autocomplete-results';
      box.hidden = true;
      wrap.appendChild(box);
    }

    let timer = 0;
    let controller = null;
    const close = () => { box.hidden = true; box.innerHTML = ''; };

    input.addEventListener('input', () => {
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) { close(); return; }
      timer = window.setTimeout(async () => {
        if (controller) controller.abort();
        controller = new AbortController();
        try {
          const response = await fetch('/api/search-suggestions?q=' + encodeURIComponent(q), { signal: controller.signal, headers: { Accept: 'application/json' } });
          if (!response.ok) return close();
          const payload = await response.json();
          const items = Array.isArray(payload.suggestions) ? payload.suggestions : [];
          if (!items.length) return close();
          box.innerHTML = items.map((item) => `<a href="${escapeAttr(item.url)}"><span>${escapeHtml(item.label)}</span><small>${escapeHtml(item.type)}</small></a>`).join('');
          box.hidden = false;
        } catch (error) {
          if (error.name !== 'AbortError') close();
        }
      }, 180);
    });

    input.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    document.addEventListener('click', (event) => { if (!form.contains(event.target)) close(); });
  });

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[char]));
  }
  function escapeAttr(value) { return escapeHtml(value); }
})();
