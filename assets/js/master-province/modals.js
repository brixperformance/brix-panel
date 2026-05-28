(function () {
    // ===== Create Modal =====
    const createModal  = document.getElementById('create-province-modal');
    const createForm   = document.getElementById('create-province-form');
    const addBtn       = document.getElementById('add-new-btn');
    const cancelCreate = document.getElementById('cancel-create-province-btn');
    const islandSel    = document.getElementById('create-province-island');
    const suffixInput  = document.getElementById('create-province-suffix');
    const preview      = document.getElementById('province-code-preview');

    function updatePreview() {
        var island = islandSel ? islandSel.value : '';
        var suffix = suffixInput ? suffixInput.value.toUpperCase() : '';
        if (island && suffix.length === 2) {
            preview.textContent = island + suffix;
        } else if (island || suffix) {
            preview.textContent = (island || '__') + suffix.padEnd(2, '_');
        } else {
            preview.textContent = '—';
        }
    }

    islandSel?.addEventListener('change', updatePreview);
    suffixInput?.addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '');
        updatePreview();
    });

    addBtn?.addEventListener('click', function () {
        createForm.reset();
        document.getElementById('create-province-status').checked = true;
        preview.textContent = '—';
        createModal.classList.add('visible');
        islandSel?.focus();
    });

    cancelCreate?.addEventListener('click', function () {
        createModal.classList.remove('visible');
    });

    createForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        var island = islandSel ? islandSel.value : '';
        var suffix = suffixInput ? suffixInput.value.toUpperCase() : '';

        if (!island) { Swal.fire('Validation', 'Please select an island.', 'warning'); return; }
        if (suffix.length !== 2) { Swal.fire('Validation', 'Province suffix must be exactly 2 letters.', 'warning'); return; }

        const statusEl = document.getElementById('create-province-status');
        const fd = new FormData(createForm);
        fd.set('status', statusEl.checked ? 'Y' : 'N');
        fd.set('suffix', suffix);

        fetch('/master-province/create', { method: 'POST', body: fd, credentials: 'same-origin' })
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
    const editModal   = document.getElementById('edit-province-modal');
    const editForm    = document.getElementById('edit-province-form');
    const cancelEdit  = document.getElementById('cancel-edit-province-btn');
    let originalData  = {};

    document.querySelectorAll('.edit-province-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const code   = btn.dataset.code;
            const name   = btn.dataset.name;
            const status = btn.dataset.status;

            document.getElementById('edit-province-code').value         = code;
            document.getElementById('edit-province-code-display').value = code;
            document.getElementById('edit-province-name').value         = name;
            document.getElementById('edit-province-status').checked     = status === 'Y';

            originalData = { name: name, status: status };
            editModal.classList.add('visible');
            document.getElementById('edit-province-name').focus();
        });
    });

    cancelEdit?.addEventListener('click', function () {
        const currentName   = document.getElementById('edit-province-name').value;
        const currentStatus = document.getElementById('edit-province-status').checked ? 'Y' : 'N';
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
        const currentName   = document.getElementById('edit-province-name').value.trim();
        const currentStatus = document.getElementById('edit-province-status').checked ? 'Y' : 'N';
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

            fetch('/master-province/update', { method: 'POST', body: fd, credentials: 'same-origin' })
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
