(function () {
    const dataset = window.masterDistributorData || {};
    const provinces = Array.isArray(dataset.provinces) ? dataset.provinces : [];

    function filterProvincesByIsland(islandCode) {
        return provinces.filter((province) => (province.msp_msi_code || '') === islandCode);
    }

    function fillProvinceOptions(selectEl, islandCode, selectedCode) {
        if (!selectEl) {
            return;
        }

        const items = filterProvincesByIsland(islandCode);
        selectEl.innerHTML = '<option value="">Select province</option>';

        items.forEach((province) => {
            const option = document.createElement('option');
            option.value = province.msp_code || '';
            option.textContent = province.msp_name || '';
            option.selected = (province.msp_code || '') === selectedCode;
            selectEl.appendChild(option);
        });

        selectEl.disabled = items.length === 0;
    }

    const createModal = document.getElementById('create-distributor-modal');
    const createForm = document.getElementById('create-distributor-form');
    const createIsland = document.getElementById('create-distributor-island');
    const createProvince = document.getElementById('create-distributor-province');

    if (createModal && createForm) {
        const addBtn = document.getElementById('add-new-btn');
        const cancelBtn = document.getElementById('cancel-create-distributor-btn');
        const createName = document.getElementById('create-distributor-name');

        addBtn?.addEventListener('click', () => {
            createForm.reset();
            fillProvinceOptions(createProvince, '', '');
            createModal.classList.add('visible');
            createName?.focus();
        });

        createIsland?.addEventListener('change', () => {
            fillProvinceOptions(createProvince, createIsland.value, '');
        });

        cancelBtn?.addEventListener('click', () => {
            createModal.classList.remove('visible');
        });
    }

    const editModal = document.getElementById('edit-distributor-modal');
    const editForm = document.getElementById('edit-distributor-form');

    if (editModal && editForm) {
        const cancelBtn = document.getElementById('cancel-edit-distributor-btn');
        const codeEl = document.getElementById('edit-distributor-code');
        const codePreviewEl = document.getElementById('edit-distributor-code-preview');
        const islandEl = document.getElementById('edit-distributor-island');
        const provinceEl = document.getElementById('edit-distributor-province');
        const nameEl = document.getElementById('edit-distributor-name');
        const typeEl = document.getElementById('edit-distributor-type');
        const contactEl = document.getElementById('edit-distributor-contact');
        const addressEl = document.getElementById('edit-distributor-address');
        const mapEl = document.getElementById('edit-distributor-map');
        const joinDateEl = document.getElementById('edit-distributor-join-date');
        const activeEl = document.getElementById('edit-distributor-active');

        document.querySelectorAll('.edit-distributor-button').forEach((button) => {
            button.addEventListener('click', () => {
                codeEl.value = button.dataset.code || '';
                codePreviewEl.textContent = button.dataset.code || '-';
                islandEl.value = button.dataset.islandName || '';
                provinceEl.value = button.dataset.provinceName || '';
                nameEl.value = button.dataset.name || '';
                typeEl.value = button.dataset.type || '';
                contactEl.value = button.dataset.contact || '';
                addressEl.value = button.dataset.address || '';
                mapEl.value = button.dataset.mapEmbed || '';
                joinDateEl.value = button.dataset.joinDate || '';
                activeEl.checked = (button.dataset.status || 'N') === 'Y';
                editModal.classList.add('visible');
                nameEl.focus();
            });
        });

        cancelBtn?.addEventListener('click', () => {
            editModal.classList.remove('visible');
        });
    }
})();
