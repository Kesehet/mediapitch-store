(() => {
  const root = document.querySelector('[data-help-root]');
  if (!root) return;

  const input = root.querySelector('[data-help-search]');
  const count = root.querySelector('[data-help-count]');
  const sections = Array.from(root.querySelectorAll('[data-help-section]'));

  const normalize = value => String(value || '').toLowerCase().trim();

  const filter = () => {
    const query = normalize(input?.value);
    let visible = 0;

    sections.forEach(section => {
      const haystack = normalize(`${section.dataset.helpKeywords || ''} ${section.textContent || ''}`);
      const match = !query || query.split(/\s+/).every(term => haystack.includes(term));
      section.hidden = !match;
      if (match) visible += 1;
    });

    if (count) {
      count.textContent = query
        ? `${visible} help topic${visible === 1 ? '' : 's'} found`
        : 'Showing all help topics';
    }
  };

  input?.addEventListener('input', filter);

  root.querySelectorAll('a[href^="#help-"]').forEach(link => {
    link.addEventListener('click', () => {
      if (input) input.value = '';
      filter();
    });
  });
})();
