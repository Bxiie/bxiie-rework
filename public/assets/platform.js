


/* Pricing UI repair: add admin-user details on pricing pages. */
(() => {
  const path = window.location.pathname;
  if (path !== '/pricing' && !document.body.classList.contains('pricing-page')) {
    return;
  }

  const normalize = (value) => (value || '').toLowerCase().trim();
  const planAdminUsers = {
    free: '1 admin user',
    starter: '1 admin user',
    studio: '3 admin users',
    professional: '10 admin users',
    'custom domain': '10 admin users',
    collective: 'Unlimited admin users',
    teams: 'Unlimited admin users'
  };

  const cards = Array.from(document.querySelectorAll('.pricing-card, .plan-card, .pricing-tier'));
  cards.forEach((card) => {
    if (card.querySelector('[data-admin-users-added]')) {
      return;
    }

    const heading = normalize(card.querySelector('h2, h3, .plan-name')?.textContent);
    const eyebrow = normalize(card.querySelector('.eyebrow')?.textContent);
    const text = normalize(card.textContent);

    let label = null;
    for (const [key, value] of Object.entries(planAdminUsers)) {
      if (heading.includes(key) || eyebrow.includes(key) || text.includes(key)) {
        label = value;
        break;
      }
    }

    if (!label) {
      return;
    }

    const ul = card.querySelector('ul');
    if (!ul) {
      return;
    }

    const li = document.createElement('li');
    li.textContent = label;
    li.setAttribute('data-admin-users-added', '1');
    ul.insertBefore(li, ul.firstChild);
  });

  const comparisonTables = Array.from(document.querySelectorAll('table'));
  comparisonTables.forEach((table) => {
    const tableText = normalize(table.textContent);
    if (!tableText.includes('allowed artworks') && !tableText.includes('monthly price')) {
      return;
    }

    if (tableText.includes('admin users')) {
      return;
    }

    const body = table.querySelector('tbody') || table;
    const row = document.createElement('tr');
    row.innerHTML = '<th>Admin users</th><td>1</td><td>3</td><td>10</td><td>Unlimited</td>';

    const rows = Array.from(body.querySelectorAll('tr'));
    const after = rows.find((candidate) => normalize(candidate.textContent).includes('allowed email addresses'))
      || rows.find((candidate) => normalize(candidate.textContent).includes('allowed artworks'))
      || rows[0];

    if (after && after.parentNode) {
      after.parentNode.insertBefore(row, after.nextSibling);
    } else {
      body.appendChild(row);
    }
  });
})();

