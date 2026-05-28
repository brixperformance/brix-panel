(function () {
  // ===== helpers: modal show/hide =====
  const show = (el) => el.classList.add('visible');
  const hide = (el) => el.classList.remove('visible');

  // Simple renderer for custom options (like your existing pattern)
  function renderOptions(container, options, onPick) {
    container.innerHTML = '';
    if (!options.length) {
      container.innerHTML = '<div class="custom-option disabled">No results</div>';
      return;
    }
    options.forEach(opt => {
      const div = document.createElement('div');
      div.className = 'custom-option';
      div.textContent = opt.text;
      div.dataset.value = opt.value;
      div.addEventListener('click', () => onPick(opt.value, opt.text));
      container.appendChild(div);
    });
  }

  // ===== STEP 1 DOM =====
  const modal1 = document.getElementById('modal-step1');
  const step1Form = document.getElementById('step1-form');
  const step1SelectIsland = document.getElementById('step1-island');
  const step1Search = document.getElementById('step1-island-search');
  const step1Options = document.getElementById('step1-island-options');
  const step1Next = document.getElementById('step1-next');
  const step1Cancel = document.getElementById('step1-cancel');

  // ===== STEP 2 DOM =====
  const modal2 = document.getElementById('modal-step2');
  const step2Form = document.getElementById('step2-form');
  const step2IslandDisplay = document.getElementById('step2-island-display');
  const step2SelectProvince = document.getElementById('step2-province');
  const step2Search = document.getElementById('step2-province-search');
  const step2Options = document.getElementById('step2-province-options');
  const step2Back = document.getElementById('step2-back');
  const step2Next = document.getElementById('step2-next');

  // ===== STEP 3 DOM =====
  const modal3 = document.getElementById('modal-step3');
  const step3Back = document.getElementById('step3-back');
  const createForm = document.getElementById('dealer-create-form');
  const step3IslandDisplay = document.getElementById('step3-island-display');
  const step3ProvinceDisplay = document.getElementById('step3-province-display');
  const finalIsland = document.getElementById('final-island');
  const finalProvince = document.getElementById('final-province');

  // ===== trigger button =====
  const btnAdd = document.getElementById('add-new-btn');

  // ===== flow state =====
  const state = {
    island_code: '',
    island_name: '',
    province_code: '',
    province_name: ''
  };

  // =========================
  // STEP 1 — Choose Island
  // =========================
  function step1IslandsList() {
    const all = Array.from(step1SelectIsland.options)
      .filter(o => o.value !== '')
      .map(o => ({ value: o.value, text: o.text, l: o.text.toLowerCase() }));

    const q = (step1Search.value || '').toLowerCase();
    const filtered = q ? all.filter(o => o.l.includes(q)) : all;  // ✅ show all if no search
    renderOptions(step1Options, filtered.slice(0, 30), (val, text) => {
      step1SelectIsland.value = val;
      step1Search.value = text;
      state.island_code = val;
      state.island_name = text;
      step1Next.disabled = false;
      step1Options.style.display = 'none'; // close after select
    });
    step1Options.style.display = filtered.length ? 'block' : 'none';
  }

  step1Search?.addEventListener('focus', step1IslandsList);
  step1Search?.addEventListener('input', () => {
    step1SelectIsland.value = '';
    step1Next.disabled = true;
    step1IslandsList();
  });

  document.addEventListener('click', (e) => {
    const wrap1 = step1Options?.closest('.custom-select-wrapper');
    const wrap2 = step2Options?.closest('.custom-select-wrapper');
    if (wrap1 && !wrap1.contains(e.target)) step1Options.style.display = 'none';
    if (wrap2 && !wrap2.contains(e.target)) step2Options.style.display = 'none';
  });

  step1Next?.addEventListener('click', async () => {
    if (!state.island_code) return;
    // move to Step 2
    hide(modal1);
    await step2Init();
    show(modal2);
  });

  step1Cancel?.addEventListener('click', () => {
    hide(modal1);
  });

  btnAdd?.addEventListener('click', () => {
    // reset flow
    state.island_code = '';
    state.island_name = '';
    state.province_code = '';
    state.province_name = '';

    step1Form?.reset();
    step2Form?.reset();
    createForm?.reset();

    step1Search.value = '';
    step1Next.disabled = true;
    step1IslandsList();
    show(modal1);
    step1Search.focus();
  });

  // ===========================
  // STEP 2 — Choose Province
  // ===========================
  async function step2Init() {
    step2IslandDisplay.value = state.island_name;
    step2SelectProvince.innerHTML = '<option value="">Select a province</option>';
    step2Options.innerHTML = '<div class="custom-option disabled">Loading...</div>';
    step2Options.style.display = 'block';
    step2Search.value = '';
    step2Search.disabled = true;
    step2Next.disabled = true;

    try {
      const url = `/master-dealer/read?island_code=${encodeURIComponent(state.island_code)}`;
      const res = await fetch(url, { credentials: 'same-origin' });
      const text = await res.text();
      let data = [];
      try { data = JSON.parse(text); } catch (_) { throw new Error('Unexpected response (maybe login?)'); }

      if (!Array.isArray(data) || data.length === 0) {
        step2Options.innerHTML = '<div class="custom-option disabled">No provinces found</div>';
        return;
      }

      // fill native select
      data.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.msp_code;
        opt.text = p.msp_name;
        step2SelectProvince.appendChild(opt);
      });

      // prepare custom list
      step2Search.disabled = false;
      step2Populate();
    } catch (e) {
      console.error(e);
      step2Options.innerHTML = '<div class="custom-option disabled">Failed to load</div>';
      Swal.fire('Error', 'Failed to load provinces', 'error');
    }
  }

  function step2Populate() {
    const q = (step2Search.value || '').toLowerCase();
    const opts = Array.from(step2SelectProvince.options)
      .filter(o => o.value !== '')
      .map(o => ({ value: o.value, text: o.text, l: o.text.toLowerCase() }))
      .filter(o => o.l.includes(q))
      .slice(0, 40);

    renderOptions(step2Options, opts, (val, text) => {
      step2SelectProvince.value = val;
      state.province_code = val;
      state.province_name = text;
      step2Search.value = text;
      step2Next.disabled = false;
      step2Options.style.display = 'none';
    });

    step2Options.style.display = opts.length ? 'block' : 'none';
  }

  step2Search?.addEventListener('input', step2Populate);

  step2Back?.addEventListener('click', () => {
    hide(modal2);
    show(modal1);
    step1Search.focus();
  });

  step2Next?.addEventListener('click', () => {
    if (!state.province_code) return;
    // move to Step 3
    hide(modal2);
    step3Init();
    show(modal3);
    document.getElementById('dealer_name').focus();
  });

  // ===========================
  // STEP 3 — Dealer Details
  // ===========================
  function step3Init() {
    step3IslandDisplay.value = state.island_name;
    step3ProvinceDisplay.value = state.province_name;

    // put codes into hidden inputs so they get submitted
    finalIsland.value = state.island_code;
    finalProvince.value = state.province_code;
  }

  step3Back?.addEventListener('click', () => {
    hide(modal3);
    show(modal2);
    step2Search.focus();
  });

  // Click-driven create (prevents full-page navigation)
  document.getElementById('create-submit')?.addEventListener('click', async () => {
    // Make sure codes are set
    if (!finalIsland.value)  finalIsland.value  = state.island_code || '';
    if (!finalProvince.value) finalProvince.value = state.province_code || '';

    // Basic required fields (optional)
    const nameEl = document.getElementById('dealer_name');
    const typeEl = document.getElementById('dealer_type');
    if (!finalIsland.value || !finalProvince.value || !nameEl.value.trim() || !typeEl.value.trim()) {
      Swal.fire({ icon: 'warning', title: 'Please complete island, province, name & type.' });
      return;
    }

    const fd = new FormData(createForm);

    try {
      const res = await fetch('/master-dealer/create', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const text = await res.text();
      const trimmed = text.trim();

      if (trimmed === 'OK') {
        hide(modal3);
        Swal.fire({ icon: 'success', title: 'Created!', timer: 1200, showConfirmButton: false })
          .then(() => location.reload());
        return;
      }

      // If server responded with HTML, it’s probably a redirect or error page
      if (/^<!doctype html>|^<html/i.test(trimmed)) {
        Swal.fire('Route/Session issue', 'Server returned HTML (likely redirect).', 'error');
        console.error('Create response looked like HTML:\n', trimmed.slice(0, 500));
        return;
      }

      Swal.fire('Create failed', trimmed || 'Unknown error', 'error');
    } catch (err) {
      Swal.fire('Network error', String(err), 'error');
    }
  });
})();

// ===== EDIT DEALER =====
(function () {
  const editModal   = document.getElementById('edit-modal');
  const editForm    = document.getElementById('dealer-edit-form');
  const btnCancel   = document.getElementById('edit-cancel');
  const btnSave     = document.getElementById('edit-save');

  const f = {
    code:    document.getElementById('edit-code'),
    name:    document.getElementById('edit-name'),
    type:    document.getElementById('edit-type'),
    contact: document.getElementById('edit-contact'),
    address: document.getElementById('edit-address'),
    map:     document.getElementById('edit-map'),
    join:    document.getElementById('edit-join'),
    status:  document.getElementById('edit-status'),
    statusH: document.getElementById('edit-status-hidden'),
  };

  const show = (el) => el.classList.add('visible');
  const hide = (el) => el.classList.remove('visible');

  // Open modal + load data
  document.querySelectorAll('.edit-button').forEach(btn => {
    btn.addEventListener('click', async () => {
      const code = btn.dataset.id;
      if (!code) return;
      try {
        const res = await fetch(`/master-dealer/read?dealer_code=${encodeURIComponent(code)}`, { credentials: 'same-origin' });
        const data = await res.json();

        f.code.value    = data.dealer_code || code;
        f.name.value    = data.dealer_name || '';
        f.type.value    = (data.dealer_type === 'O' ? 'O' : 'R'); // default R
        f.contact.value = data.dealer_contact || '';
        f.address.value = data.dealer_address || '';
        f.map.value     = data.dealer_map || '';
        f.join.value    = data.dealer_join_date || '';
        const active    = (data.dealer_status || 'Y') === 'Y';
        f.status.checked = active;
        f.statusH.value  = active ? 'Y' : 'N';

        show(editModal);
      } catch (e) {
        Swal.fire('Error', 'Failed to load dealer', 'error');
      }
    });
  });

  // Toggle status
  f.status?.addEventListener('change', () => {
    f.statusH.value = f.status.checked ? 'Y' : 'N';
  });

  // Cancel
  btnCancel?.addEventListener('click', () => hide(editModal));

  // Save
  editForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    // Basic validation
    if (!f.name.value.trim() || (f.type.value !== 'R' && f.type.value !== 'O')) {
      Swal.fire({ icon: 'warning', title: 'Dealer name and type are required.' });
      return;
    }

    const fd = new FormData(editForm);

    try {
      const res  = await fetch('/master-dealer/update', { method: 'POST', body: fd, credentials: 'same-origin' });
      const text = (await res.text()).trim();
      if (text === 'OK') {
        hide(editModal);
        Swal.fire({ icon: 'success', title: 'Updated!', timer: 1200, showConfirmButton: false })
          .then(() => location.reload());
      } else {
        Swal.fire('Update failed', text || 'Unknown error', 'error');
      }
    } catch (err) {
      Swal.fire('Error', String(err), 'error');
    }
  });
})();