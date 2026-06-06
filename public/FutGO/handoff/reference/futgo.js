/* FutGO · shared UI behavior ------------------------------------ */
(function () {
  // Theme: dark-first, persisted.
  const KEY = 'futgo-theme';
  const saved = localStorage.getItem(KEY);
  if (saved === 'light') document.documentElement.setAttribute('data-theme', 'light');

  window.FutGO = {
    toggleTheme() {
      const el = document.documentElement;
      const next = el.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      if (next === 'light') el.setAttribute('data-theme', 'light');
      else el.removeAttribute('data-theme');
      localStorage.setItem(KEY, next);
      document.querySelectorAll('[data-theme-label]').forEach(n => {
        n.textContent = next === 'light' ? 'Claro' : 'Oscuro';
      });
    },
    isLight() { return document.documentElement.getAttribute('data-theme') === 'light'; }
  };

  document.addEventListener('DOMContentLoaded', () => {
    // reflect current theme in labels
    const light = window.FutGO.isLight();
    document.querySelectorAll('[data-theme-label]').forEach(n => n.textContent = light ? 'Claro' : 'Oscuro');
    document.querySelectorAll('[data-theme-check]').forEach(n => { n.checked = light; });

    // generic segmented / tabs / chips toggles
    const groups = ['.segmented', '.tabs'];
    groups.forEach(sel => document.querySelectorAll(sel).forEach(g => {
      g.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
        g.querySelectorAll('button').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
      }));
    }));
    document.querySelectorAll('[data-chipgroup]').forEach(g => {
      g.querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => {
        g.querySelectorAll('.chip').forEach(x => x.classList.remove('active'));
        c.classList.add('active');
      }));
    });
  });
})();
