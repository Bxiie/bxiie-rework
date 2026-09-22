/* Desktop space preference is local to this browser and tenant/platform shell. */
(() => {
  document.querySelectorAll('[data-admin-sidebar-shell]').forEach(shell => {
    const button = shell.querySelector('.admin-sidebar-toggle');
    const toolbar = shell.querySelector('.admin-sidebar-toolbar');
    const label = button?.querySelector('[data-sidebar-toggle-label]');
    const sidebar = button && document.getElementById(button.getAttribute('aria-controls'));
    if (!button || !toolbar || !label || !sidebar) return;

    const mobile = window.matchMedia('(max-width: 900px)');
    const key = 'artsfolio.sidebar.collapsed.' + shell.dataset.adminSidebarShell;
    let desktopCollapsed = false;
    let mobileOpen = false;
    try { desktopCollapsed = window.localStorage.getItem(key) === '1'; } catch (_) {}

    const render = () => {
      const expanded = mobile.matches ? mobileOpen : !desktopCollapsed;
      if (!expanded && sidebar.contains(document.activeElement)) button.focus();
      sidebar.hidden = !expanded;
      shell.classList.toggle('admin-sidebar-collapsed', !expanded);
      button.setAttribute('aria-expanded', String(expanded));
      label.textContent = mobile.matches
        ? (expanded ? 'Close admin menu' : 'Open admin menu')
        : (expanded ? 'Hide sidebar' : 'Show sidebar');
    };
    toolbar.hidden = false;
    render();

    button.addEventListener('click', () => {
      if (mobile.matches) {
        mobileOpen = !mobileOpen;
      } else {
        desktopCollapsed = !desktopCollapsed;
        try { window.localStorage.setItem(key, desktopCollapsed ? '1' : '0'); } catch (_) {}
      }
      render();
    });
    sidebar.addEventListener('click', event => {
      if (mobile.matches && event.target.closest('a')) {
        mobileOpen = false;
        render();
      }
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && mobile.matches && mobileOpen) {
        mobileOpen = false;
        render();
      }
    });
    mobile.addEventListener('change', () => {
      mobileOpen = false;
      render();
    });
  });
})();
