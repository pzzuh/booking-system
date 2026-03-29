(() => {
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // Password toggles: add data-toggle-password="#inputId"
  qsa('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = qs(btn.getAttribute('data-toggle-password'));
      if (!target) return;
      const isPwd = target.getAttribute('type') === 'password';
      target.setAttribute('type', isPwd ? 'text' : 'password');
      const icon = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-eye', !isPwd);
        icon.classList.toggle('fa-eye-slash', isPwd);
      }
    });
  });
})();
