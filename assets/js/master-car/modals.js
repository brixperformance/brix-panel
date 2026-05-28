(function () {
    const createModal = document.getElementById('create-car-modal');
    const editModal = document.getElementById('edit-car-modal');
    const createForm = document.getElementById('create-car-form');
    const editForm = document.getElementById('edit-car-form');

    if (createModal && createForm) {
        const addBtn = document.getElementById('add-new-btn');
        const cancelBtn = document.getElementById('cancel-create-car-btn');
        const activeEl = document.getElementById('create-car-flag');

        addBtn?.addEventListener('click', () => {
            createForm.reset();
            activeEl.checked = true;
            createModal.classList.add('visible');
        });

        cancelBtn?.addEventListener('click', () => {
            createModal.classList.remove('visible');
        });
    }

    if (editModal && editForm) {
        const inputId = document.getElementById('edit-car-id');
        const inputBrand = document.getElementById('edit-car-brand');
        const inputName = document.getElementById('edit-car-name');
        const inputCode = document.getElementById('edit-car-code');
        const inputActive = document.getElementById('edit-car-flag');
        const cancelBtn = document.getElementById('cancel-edit-car-btn');

        document.querySelectorAll('.edit-car-button').forEach((btn) => {
            btn.addEventListener('click', () => {
                inputId.value = btn.dataset.id || '';
                inputBrand.value = btn.dataset.brandId || '';
                inputName.value = btn.dataset.name || '';
                inputCode.value = btn.dataset.code || '';
                inputActive.checked = btn.dataset.active === '1';
                editModal.classList.add('visible');
            });
        });

        cancelBtn?.addEventListener('click', () => {
            editModal.classList.remove('visible');
        });
    }
})();
