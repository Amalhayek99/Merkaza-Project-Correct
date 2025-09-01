function wireSimpleSearch(selectId, inputId, resultsId) {
  const sel = document.getElementById(selectId);
  const inp = document.getElementById(inputId);
  const out = document.getElementById(resultsId);
  if (!sel || !inp || !out) return;

  // Build [{id, name}] from the select once
  const items = Array.from(sel.options)
    .filter(o => o.value !== '')
    .map(o => ({ id: o.value, name: o.text.trim() }));

  function render(list) {
    out.innerHTML = '';
    list.slice(0, 8).forEach(item => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = item.name;
      b.addEventListener('click', () => {
        sel.value = item.id;     // <-- sets product_id for form submit
        inp.value = item.name;   // show chosen name in input
        out.innerHTML = '';      // hide results
      });
      out.appendChild(b);
    });
  }

  function filter(q) {
    const s = q.trim().toLowerCase();
    if (!s) { out.innerHTML = ''; return; }
    // starts-with first, then contains
    const starts = items.filter(x => x.name.toLowerCase().startsWith(s));
    const contains = items.filter(x => !x.name.toLowerCase().startsWith(s) && x.name.toLowerCase().includes(s));
    render(starts.concat(contains));
  }

  // If editing, show current product name in the input
  if (sel.value) {
    const cur = items.find(x => x.id === sel.value);
    if (cur) inp.value = cur.name;
  }

  inp.addEventListener('input', e => filter(e.target.value));
  inp.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      const first = out.querySelector('button');
      if (first) first.click();
    }
  });

  // Clear list on blur (optional)
  inp.addEventListener('blur', () => setTimeout(() => out.innerHTML = '', 150));
}

document.addEventListener('DOMContentLoaded', () => {
  wireSimpleSearch('product_id_select', 'product_search_simple', 'product_results');
});
