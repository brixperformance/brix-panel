(function () {
    const createModal = document.getElementById('create-product-modal');
    const editModal = document.getElementById('edit-product-modal');
    const createForm = document.getElementById('create-product-form');
    const editForm = document.getElementById('edit-product-form');

    function bindFitmentSearch(searchInput, selectEl) {
        if (!searchInput || !selectEl) {
            return;
        }

        const options = Array.from(selectEl.options).map((option) => ({
            option,
            text: option.text.toLowerCase(),
        }));

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            options.forEach(({ option, text }) => {
                option.hidden = query !== '' && !text.includes(query);
            });
        });
    }

    function setSelectedValues(selectEl, values) {
        const selectedValues = new Set(values.map((value) => String(value)));
        Array.from(selectEl.options).forEach((option) => {
            option.selected = selectedValues.has(option.value);
        });
    }

    if (createModal && createForm) {
        const addBtn = document.getElementById('add-new-btn');
        const cancelBtn = document.getElementById('cancel-create-product-btn');
        const searchInput = document.getElementById('create-product-car-search');
        const selectEl = document.getElementById('create-product-cars');

        bindFitmentSearch(searchInput, selectEl);

        addBtn?.addEventListener('click', () => {
            createForm.reset();
            if (searchInput) {
                searchInput.value = '';
            }
            Array.from(selectEl.options).forEach((option) => {
                option.hidden = false;
                option.selected = false;
            });
            createModal.classList.add('visible');
        });

        cancelBtn?.addEventListener('click', () => {
            createModal.classList.remove('visible');
        });
    }

    if (editModal && editForm) {
        const cancelBtn = document.getElementById('cancel-edit-product-btn');
        const searchInput = document.getElementById('edit-product-car-search');
        const selectEl = document.getElementById('edit-product-cars');
        bindFitmentSearch(searchInput, selectEl);

        document.querySelectorAll('.edit-product-button').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('edit-product-id').value = btn.dataset.id || '';
                document.getElementById('edit-product-type').value = btn.dataset.typeId || '';
                document.getElementById('edit-product-title').value = btn.dataset.title || '';
                document.getElementById('edit-product-part-number').value = btn.dataset.partNumber || '';
                document.getElementById('edit-product-sku').value = btn.dataset.sku || '';
                document.getElementById('edit-product-price').value = btn.dataset.price || '0';
                document.getElementById('edit-product-stock-qty').value = btn.dataset.stockQty || '0';
                document.getElementById('edit-product-stock-status').value = btn.dataset.stockStatus || 'in_stock';
                document.getElementById('edit-product-fitment-sizing').value = btn.dataset.fitmentSizing || '';
                document.getElementById('edit-product-thickness').value = btn.dataset.thicknessMm || '';
                document.getElementById('edit-product-width').value = btn.dataset.widthMm || '';
                document.getElementById('edit-product-height').value = btn.dataset.heightMm || '';
                document.getElementById('edit-product-diameter').value = btn.dataset.diameterMm || '';
                document.getElementById('edit-product-pcd-holes').value = btn.dataset.pcdHoles || '';
                document.getElementById('edit-product-weight').value = btn.dataset.weightGrams || '';

                if (searchInput) {
                    searchInput.value = '';
                }
                Array.from(selectEl.options).forEach((option) => {
                    option.hidden = false;
                });

                const fitmentIds = (btn.dataset.fitmentIds || '')
                    .split(',')
                    .map((value) => value.trim())
                    .filter(Boolean);
                setSelectedValues(selectEl, fitmentIds);
                editModal.classList.add('visible');
            });
        });

        cancelBtn?.addEventListener('click', () => {
            editModal.classList.remove('visible');
        });
    }
})();
