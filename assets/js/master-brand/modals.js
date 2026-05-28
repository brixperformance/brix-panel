(function () {
    const createModal = document.getElementById('create-brand-modal');
    const editModal = document.getElementById('edit-brand-modal');
    const createForm = document.getElementById('create-brand-form');
    const editForm = document.getElementById('edit-brand-form');

    function brandNameToCode(value) {
        return (value || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]/g, '')
            .slice(0, 3);
    }

    if (createModal && createForm) {
        const addBtn = document.getElementById('add-new-btn');
        const cancelBtn = document.getElementById('cancel-create-brand-btn');
        const nameEl = document.getElementById('create-brand-name');
        const codeEl = document.getElementById('create-brand-code');
        const activeEl = document.getElementById('create-brand-flag');

        const syncCode = () => {
            if (!codeEl.dataset.touched) {
                codeEl.value = brandNameToCode(nameEl.value);
            }
        };

        addBtn?.addEventListener('click', () => {
            createForm.reset();
            delete codeEl.dataset.touched;
            activeEl.checked = true;
            createModal.classList.add('visible');
            nameEl.focus();
        });

        nameEl?.addEventListener('input', syncCode);
        codeEl?.addEventListener('input', () => {
            codeEl.dataset.touched = '1';
            codeEl.value = brandNameToCode(codeEl.value);
        });

        cancelBtn?.addEventListener('click', () => {
            createModal.classList.remove('visible');
        });
    }

    if (editModal && editForm) {
        const cancelBtn = document.getElementById('cancel-edit-brand-btn');
        const inputId = document.getElementById('edit-brand-id');
        const inputName = document.getElementById('edit-brand-name');
        const inputCode = document.getElementById('edit-brand-code');
        const inputActive = document.getElementById('edit-brand-flag');

        document.querySelectorAll('.edit-brand-button').forEach((btn) => {
            btn.addEventListener('click', () => {
                inputId.value = btn.dataset.id || '';
                inputName.value = btn.dataset.name || '';
                inputCode.value = brandNameToCode(btn.dataset.code || '');
                inputActive.checked = btn.dataset.active === '1';
                editModal.classList.add('visible');
            });
        });

        inputCode?.addEventListener('input', () => {
            inputCode.value = brandNameToCode(inputCode.value);
        });

        cancelBtn?.addEventListener('click', () => {
            editModal.classList.remove('visible');
        });
    }
})();
