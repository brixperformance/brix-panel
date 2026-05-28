// assets/js/master-brand/modals.js
(function () {
    // ===== Create Modal Logic (minimal) =====
    const createModal = document.getElementById('create-brand-modal');
    const createForm  = document.getElementById('create-brand-form');

    if (createModal && createForm) {
        const addBtn    = document.getElementById('add-new-btn');
        const nameEl    = document.getElementById('create-brand-name');
        const fileEl    = document.getElementById('create-brand-file');
        const flagEl    = document.getElementById('create-brand-flag');
        const cancelBtn = document.getElementById('cancel-create-brand-btn');

        function syncCreateFile() {
            if (!nameEl || !fileEl) return;
                // reuse brandNameToFile from above
                fileEl.value = (typeof brandNameToFile === 'function') ? brandNameToFile(nameEl.value) : '';
            }
        nameEl?.addEventListener('input', syncCreateFile);
        nameEl?.addEventListener('change', syncCreateFile);

        addBtn?.addEventListener('click', () => {
            createForm.reset();
            fileEl.value = '';
            flagEl.checked = true; // default Active
            createModal.classList.add('visible');
            nameEl.focus();
        });

        cancelBtn?.addEventListener('click', () => {
            createModal.classList.remove('visible');
        });

        createForm.addEventListener('submit', (e) => {
            e.preventDefault();
            syncCreateFile();

            const formData = new FormData(createForm);
            formData.set('flag', flagEl.checked ? 'Y' : 'N');

            fetch('/master-brand/create', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(res => res.text())
            .then(response => {
                if (response.trim() === 'OK') {
                Swal.fire({ icon:'success', title:'Created!', timer:1500, showConfirmButton:false })
                    .then(() => { createModal.classList.remove('visible'); location.reload(); });
                } else {
                Swal.fire('Failed', response || 'Unknown error', 'error');
                }
            })
            .catch(err => Swal.fire('Error', String(err), 'error'));
        });
    }
    
    // ===== Edit Modal Logic =====
    const editModal = document.getElementById('edit-brand-modal');
    const editForm  = document.getElementById('edit-brand-form');
    let originalData = {};

    function brandNameToFile(val) {
        if (!val) return '';
        let first = val.split('/')[0].trim();
        first = first.toLowerCase();
        first = first.replace(/\s+/g, ' ');
        first = first.replace(/[^a-z0-9 \-]/g, '');
        first = first.replace(/ /g, '-').replace(/\-+/g, '-').replace(/^\-+|\-+$/g, '');
        return first;
    }

    if (editModal && editForm) {
        const inputId   = document.getElementById('edit-brand-id');
        const inputName = document.getElementById('edit-brand-name');
        const inputFile = document.getElementById('edit-brand-file');
        const inputFlag = document.getElementById('edit-brand-flag');
        inputFile?.setAttribute('readonly', 'readonly');

        function syncFileFromName() {
            if (!inputName || !inputFile) return;
            inputFile.value = brandNameToFile(inputName.value);
        }
        inputName?.addEventListener('input', syncFileFromName);
        inputName?.addEventListener('change', syncFileFromName);

        document.querySelectorAll('.edit-brand-button').forEach(btn => {
            btn.addEventListener('click', () => {
                inputId.value   = btn.dataset.id;
                inputName.value = btn.dataset.name;

                const derived = brandNameToFile(btn.dataset.name || '');
                inputFile.value = derived;

                // ✅ set flag checkbox from data-flag
                if (inputFlag) inputFlag.checked = (btn.dataset.flag === 'Y');

                originalData = {
                    id:   btn.dataset.id,
                    name: btn.dataset.name,
                    file: derived,
                    flag: (btn.dataset.flag === 'Y') ? 'Y' : 'N'
                };
                editModal.classList.add('visible');
            });
        });

        document.getElementById('cancel-edit-brand-btn')?.addEventListener('click', () => {
            const hasChanges =
                inputId.value   !== originalData.id ||
                inputName.value !== originalData.name ||
                inputFile.value !== originalData.file ||
                ((inputFlag && (inputFlag.checked ? 'Y':'N')) !== originalData.flag); // ✅ include flag

            if (hasChanges) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Unsaved changes',
                    text: 'You have unsaved changes. Discard?',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No'
                }).then(result => {
                    if (result.isConfirmed) editModal.classList.remove('visible');
                });
            } else {
                editModal.classList.remove('visible');
            }
        });

        editForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const hasChanges =
                inputId.value   !== originalData.id ||
                inputName.value !== originalData.name ||
                inputFile.value !== originalData.file ||
                ((inputFlag && (inputFlag.checked ? 'Y':'N')) !== originalData.flag); // ✅ include flag

            if (!hasChanges) {
                Swal.fire({ icon: 'info', title: 'No changes to save.' });
                return;
            }

            Swal.fire({
                icon: 'question',
                title: 'Save changes?',
                showCancelButton: true,
                confirmButtonText: 'Save',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (!result.isConfirmed) return;

                const formData = new FormData(editForm);
                if (inputFlag) formData.set('flag', inputFlag.checked ? 'Y' : 'N');

                fetch('/master-brand/update', { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(res => res.text())
                .then(response => {
                    if (response.trim() === "OK") {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: 'Brand has been updated.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            editModal.classList.remove('visible'); location.reload();
                        });
                    } else {
                        Swal.fire('Failed', response, 'error');
                    }
                })
                .catch(err => Swal.fire('Error', String(err), 'error'));
            });
        });
    }
})();