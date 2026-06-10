/*
 * Enables drag ordering on tenant artwork placement order tables.
 */
(() => {
  document.querySelectorAll('.sortable-artworks tbody').forEach((tbody) => {
    let dragged = null;

    const renumber = () => {
      Array.from(tbody.querySelectorAll('.sort-input')).forEach((input, index) => {
        input.value = String(index * 10);
      });
    };

    tbody.addEventListener('dragstart', (event) => {
      dragged = event.target.closest('tr');
      if (dragged) {
        dragged.classList.add('is-dragging');
      }
    });

    tbody.addEventListener('dragend', () => {
      if (dragged) {
        dragged.classList.remove('is-dragging');
      }
      dragged = null;
      renumber();
    });

    tbody.addEventListener('dragover', (event) => {
      event.preventDefault();
      const row = event.target.closest('tr');
      if (!dragged || !row || row === dragged || !tbody.contains(row)) {
        return;
      }

      const box = row.getBoundingClientRect();
      const before = event.clientY < box.top + box.height / 2;
      tbody.insertBefore(dragged, before ? row : row.nextSibling);
      renumber();
    });
  });
})();

// End of file.
