// assets/js/modals.js
(function () {
  // ===== Custom Brand Dropdown (for Create modal) =====
  document.addEventListener("DOMContentLoaded", function () {
    const select = document.getElementById('create-brand');
    const searchInput = document.getElementById('brand-search');
    const optionsBox = document.querySelector('.custom-options');
    if (!select || !searchInput || !optionsBox) return;

    const brands = Array.from(select.options)
      .filter(opt => opt.value !== "")
      .map(opt => ({ value: opt.value, text: opt.text }));

    function renderOptions(keyword = '') {
      optionsBox.innerHTML = '';
      const filtered = brands.filter(b => b.text.toLowerCase().includes(keyword.toLowerCase()));
      filtered.slice(0, 20).forEach(brand => {
        const div = document.createElement('div');
        div.className = 'custom-option';
        div.textContent = brand.text;
        div.dataset.value = brand.value;
        optionsBox.appendChild(div);
      });
      optionsBox.style.display = filtered.length ? 'block' : 'none';
    }

    searchInput.addEventListener('focus', () => renderOptions(searchInput.value));

    searchInput.addEventListener('input', function () {
      renderOptions(this.value);
      select.value = ''; // Reset selection when user types again
    });

    optionsBox.addEventListener('click', function (e) {
      if (e.target.classList.contains('custom-option')) {
        searchInput.value = e.target.textContent;
        select.value = e.target.dataset.value;
        optionsBox.style.display = 'none';
      }
    });

    document.addEventListener('click', function (e) {
      const wrapper = document.querySelector('.custom-select-wrapper');
      if (wrapper && !wrapper.contains(e.target)) {
        optionsBox.style.display = 'none';
      }
    });
  });

  // ===== Create Modal Logic =====
  document.addEventListener("DOMContentLoaded", function () {
    const createModal = document.getElementById('create-modal');
    const createForm  = document.getElementById('create-form');
    const selectBrand = document.getElementById('create-brand');
    const searchInput = document.getElementById('brand-search');

    if (!createModal || !createForm) return;

    document.getElementById('add-new-btn')?.addEventListener('click', () => {
      createForm.reset();
      if (searchInput) searchInput.value = '';
      createModal.classList.add('visible');
      if (searchInput) searchInput.dispatchEvent(new Event('focus'));
    });

    document.getElementById('cancel-create-btn')?.addEventListener('click', (e) => {
      e.preventDefault();
      createModal.classList.remove('visible');
    });

    createForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!selectBrand?.value) {
        Swal.fire({ icon: 'warning', title: 'Please choose a brand.' });
        return;
      }

      const fd = new FormData(createForm);
      try {
        const res = await fetch('/master-pricelist/create', { method: 'POST', body: fd, credentials: 'same-origin' });
        const text = await res.text();

        if (text.trim() === 'OK') {
          createModal.classList.remove('visible');
          Swal.fire({ icon: 'success', title: 'Created successfully!', timer: 1200, showConfirmButton: false })
            .then(() => location.reload());
        } else {
          Swal.fire('Create failed', text, 'error');
        }
      } catch (err) {
        Swal.fire('Error', String(err), 'error');
      }
    });
  });

  // ===== Edit Modal Logic =====
  const editModal = document.getElementById('edit-modal');
  const editForm  = document.getElementById('edit-form');
  let originalData = {};

  if (editModal && editForm) {
    document.querySelectorAll('.edit-button').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('edit-id').value = btn.dataset.id;
        document.getElementById('edit-brand').value = btn.dataset.brand;
        document.getElementById('edit-type').value = btn.dataset.type;
        document.getElementById('edit-year').value = btn.dataset.year;
        document.getElementById('edit-reseller').value = btn.dataset.reseller.replace(',', '');
        document.getElementById('edit-retail').value = btn.dataset.retail.replace(',', '');
        document.getElementById('edit-reseller-carbon').value = btn.dataset.resellerCarbon.replace(',', '');
        document.getElementById('edit-retail-carbon').value = btn.dataset.retailCarbon.replace(',', '');

        const inputStatus = document.getElementById('edit-status');
        const inputStock  = document.getElementById('edit-status-stock');
        if (inputStatus) inputStatus.checked = (btn.dataset.status === 'Y');
        if (inputStock)  inputStock.checked  = (btn.dataset.stock  === 'Y');

        originalData = {
          type: document.getElementById('edit-type').value,
          year: document.getElementById('edit-year').value,
          reseller: document.getElementById('edit-reseller').value,
          retail: document.getElementById('edit-retail').value,
          reseller_carbon: document.getElementById('edit-reseller-carbon').value,
          retail_carbon: document.getElementById('edit-retail-carbon').value,
          status: (inputStatus && inputStatus.checked) ? 'Y' : 'N',
          status_stock: (inputStock && inputStock.checked) ? 'Y' : 'N'
        };
        editModal.classList.add('visible');
      });
    });

    document.getElementById('cancel-btn')?.addEventListener('click', () => {
      const inputStatus = document.getElementById('edit-status');
      const inputStock  = document.getElementById('edit-status-stock');

      const hasChanges =
        document.getElementById('edit-type').value !== originalData.type ||
        document.getElementById('edit-year').value !== originalData.year ||
        document.getElementById('edit-reseller').value !== originalData.reseller ||
        document.getElementById('edit-retail').value !== originalData.retail ||
        document.getElementById('edit-reseller-carbon').value !== originalData.reseller_carbon ||
        document.getElementById('edit-retail-carbon').value !== originalData.retail_carbon ||
        ((inputStatus && (inputStatus.checked ? 'Y' : 'N')) !== originalData.status) ||          // ✅ NEW
        ((inputStock  && (inputStock.checked  ? 'Y' : 'N')) !== originalData.status_stock);      // ✅ NEW

      if (hasChanges) {
        Swal.fire({
          icon: 'warning', title: 'Unsaved changes',
          text: 'You have unsaved changes. Discard?',
          showCancelButton: true, confirmButtonText: 'Yes', cancelButtonText: 'No'
        }).then(result => {
          if (result.isConfirmed) editModal.classList.remove('visible');
        });
      } else {
        editModal.classList.remove('visible');
      }
    });

    editForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const inputStatus = document.getElementById('edit-status');
      const inputStock  = document.getElementById('edit-status-stock');

      const hasChanges =
        document.getElementById('edit-type').value !== originalData.type ||
        document.getElementById('edit-year').value !== originalData.year ||
        document.getElementById('edit-reseller').value !== originalData.reseller ||
        document.getElementById('edit-retail').value !== originalData.retail ||
        document.getElementById('edit-reseller-carbon').value !== originalData.reseller_carbon ||
        document.getElementById('edit-retail-carbon').value !== originalData.retail_carbon ||
        ((inputStatus && (inputStatus.checked ? 'Y' : 'N')) !== originalData.status) ||          // ✅ NEW
        ((inputStock  && (inputStock.checked  ? 'Y' : 'N')) !== originalData.status_stock);      // ✅ NEW

      if (!hasChanges) {
        Swal.fire({ icon: 'info', title: 'No changes to save.' });
        return;
      }

      Swal.fire({
        icon: 'question', title: 'Save changes?',
        showCancelButton: true, confirmButtonText: 'Save', cancelButtonText: 'Cancel'
      }).then(result => {
        if (!result.isConfirmed) return;

        const formData = new FormData(editForm);
        if (inputStatus) formData.set('status',      inputStatus.checked ? 'Y' : 'N');
        if (inputStock)  formData.set('status_stock', inputStock.checked  ? 'Y' : 'N');

        fetch('/master-pricelist/update', { method: 'POST', body: formData, credentials: 'same-origin' })
          .then(res => res.text())
          .then(response => {
            if (response.trim() === "OK") {
              Swal.fire({ icon: 'success', title: 'Updated!', text: 'Product has been updated.', timer: 2000, showConfirmButton: false })
                .then(() => { editModal.classList.remove('visible'); location.reload(); });
            } else {
              Swal.fire('Failed', response, 'error');
            }
          })
          .catch(err => Swal.fire('Error', String(err), 'error'));
      });
    });
  }
})();
