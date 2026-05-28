(function () {
    // ===== Create Modal =====
    const createModal  = document.getElementById('create-island-modal');
    const createForm   = document.getElementById('create-island-form');
    const addBtn       = document.getElementById('add-new-btn');
    const cancelCreate = document.getElementById('cancel-create-island-btn');
    const codeInput    = document.getElementById('create-island-code');

    addBtn?.addEventListener('click', function () {
        createForm.reset();
        document.getElementById('create-island-status').checked = true;
        createModal.classList.add('visible');
        codeInput?.focus();
    });

    cancelCreate?.addEventListener('click', function () {
        createModal.classList.remove('visible');
    });

    codeInput?.addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '');
    });

    createForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        const statusEl = document.getElementById('create-island-status');
        const fd = new FormData(createForm);
        fd.set('status', statusEl.checked ? 'Y' : 'N');

        fetch('/master-island/create', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (res) { return res.text(); })
        .then(function (text) {
            if (text.trim() === 'OK') {
                Swal.fire({ icon: 'success', title: 'Created!', timer: 1500, showConfirmButton: false })
                    .then(function () { createModal.classList.remove('visible'); location.reload(); });
            } else {
                Swal.fire('Failed', text || 'Unknown error', 'error');
            }
        })
        .catch(function (err) { Swal.fire('Error', String(err), 'error'); });
    });

    // ===== Edit Modal =====
    const editModal    = document.getElementById('edit-island-modal');
    const editForm     = document.getElementById('edit-island-form');
    const cancelEdit   = document.getElementById('cancel-edit-island-btn');
    let originalData   = {};

    document.querySelectorAll('.edit-island-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const code   = btn.dataset.code;
            const name   = btn.dataset.name;
            const status = btn.dataset.status;

            document.getElementById('edit-island-code').value         = code;
            document.getElementById('edit-island-code-display').value = code;
            document.getElementById('edit-island-name').value         = name;
            document.getElementById('edit-island-status').checked     = status === 'Y';

            originalData = { name: name, status: status };
            editModal.classList.add('visible');
            document.getElementById('edit-island-name').focus();
        });
    });

    cancelEdit?.addEventListener('click', function () {
        const currentName   = document.getElementById('edit-island-name').value;
        const currentStatus = document.getElementById('edit-island-status').checked ? 'Y' : 'N';
        const hasChanges    = currentName !== originalData.name || currentStatus !== originalData.status;

        if (hasChanges) {
            Swal.fire({
                icon: 'warning',
                title: 'Unsaved changes',
                text: 'Discard changes?',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
            }).then(function (result) {
                if (result.isConfirmed) editModal.classList.remove('visible');
            });
        } else {
            editModal.classList.remove('visible');
        }
    });

    editForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        const currentName   = document.getElementById('edit-island-name').value.trim();
        const currentStatus = document.getElementById('edit-island-status').checked ? 'Y' : 'N';
        const hasChanges    = currentName !== originalData.name || currentStatus !== originalData.status;

        if (!hasChanges) {
            Swal.fire({ icon: 'info', title: 'No changes to save.' });
            return;
        }

        Swal.fire({
            icon: 'question',
            title: 'Save changes?',
            showCancelButton: true,
            confirmButtonText: 'Save',
            cancelButtonText: 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const fd = new FormData(editForm);
            fd.set('status', currentStatus);

            fetch('/master-island/update', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (res) { return res.text(); })
            .then(function (text) {
                if (text.trim() === 'OK') {
                    Swal.fire({ icon: 'success', title: 'Updated!', timer: 1500, showConfirmButton: false })
                        .then(function () { editModal.classList.remove('visible'); location.reload(); });
                } else {
                    Swal.fire('Failed', text || 'Unknown error', 'error');
                }
            })
            .catch(function (err) { Swal.fire('Error', String(err), 'error'); });
        });
    });
})();
