document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-master-article-form]');
    const detailBlocks = document.getElementById('detail-blocks');
    const codeInput = document.getElementById('msa_code');
    const categoryInput = document.getElementById('msa_category');
    const titleInput = document.getElementById('msa_title');
    const slugInput = document.getElementById('msa_slug');
    const headerFileInput = document.getElementById('head_header_file');
    const headerPreview = document.getElementById('head_header_preview');
    const headerPreviewWrap = document.getElementById('head_header_preview_wrap');
    const headerClearButton = document.getElementById('head_header_clear');

    const categoryPrefixes = {
        'street-series': 'SS',
        'competition-series': 'CS',
        'event': 'EV',
    };

    function slugify(value) {
        return value
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    }

    async function fetchNextCode(category) {
        if (!categoryPrefixes[category]) {
            return null;
        }

        try {
            const response = await fetch(`/master-article/next-code?category=${encodeURIComponent(category)}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();

            if (!payload || !payload.ok || !payload.code) {
                return null;
            }

            return String(payload.code).replace(/(\d+)(?!.*\d)/, match => match.padStart(2, '0'));
        } catch (_) {
            return null;
        }
    }

    function setCodeValue(value) {
        if (!codeInput) {
            return;
        }

        codeInput.value = value;
        codeInput.setAttribute('value', value);
        codeInput.defaultValue = value;
    }

    function nextBlockId() {
        window.__masterArticleBlockCounter = (window.__masterArticleBlockCounter || 0) + 1;
        return `new${window.__masterArticleBlockCounter}`;
    }

    function nextBlockOrder() {
        if (!detailBlocks) {
            return 1;
        }

        let max = 0;
        detailBlocks.querySelectorAll("input[name$='[order]']").forEach(input => {
            const value = parseInt(input.value || '0', 10) || 0;
            if (value > max) {
                max = value;
            }
        });

        return max + 1;
    }

    function reindexOrders() {
        if (!detailBlocks) {
            return;
        }

        Array.from(detailBlocks.querySelectorAll('.article-block')).forEach((block, index) => {
            const orderInput = block.querySelector("input[name$='[order]']");
            if (orderInput) {
                orderInput.value = String(index + 1);
            }
        });
    }

    function reindexImageRows(blockId) {
        const container = document.querySelector(`.article-image-rows[data-blockid='${blockId}']`);
        if (!container) {
            return;
        }

        Array.from(container.querySelectorAll('.article-image-row')).forEach((row, index) => {
            row.dataset.index = String(index);

            const altInput = row.querySelector('[data-role="img-alt"]');
            if (altInput) {
                altInput.name = `block_${blockId}_img_${index}_alt`;
            }

            const existingInput = row.querySelector('[data-role="img-existing"]');
            if (existingInput) {
                existingInput.name = `block_${blockId}_img_${index}_existing`;
            }
        });
    }

    function updatePreviewImage(previewNode, fileOrUrl) {
        if (!previewNode) {
            return;
        }

        if (typeof fileOrUrl === 'string') {
            previewNode.src = fileOrUrl;
            previewNode.style.display = fileOrUrl !== '' ? 'block' : 'none';
            return;
        }

        if (!(fileOrUrl instanceof File) || !fileOrUrl.type.startsWith('image/')) {
            previewNode.src = '';
            previewNode.style.display = 'none';
            return;
        }

        const objectUrl = URL.createObjectURL(fileOrUrl);
        previewNode.src = objectUrl;
        previewNode.style.display = 'block';
        previewNode.onload = () => URL.revokeObjectURL(objectUrl);
    }

    function buildImageRow(blockId, index, existingUrl = '', existingFilename = '', existingAlt = '') {
        return `
            <div class="article-image-row" data-index="${index}">
                <div>
                    <label class="form-label">Upload Image</label>
                    <input class="form-control" type="file" name="files_block_${blockId}[]" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                    <input type="hidden" data-role="img-existing" name="block_${blockId}_img_${index}_existing" value="${existingFilename}">
                    <img class="article-preview-image" alt="Body image preview" ${existingUrl ? `src="${existingUrl}" style="display:block"` : ''}>
                </div>
                <div class="article-inline-grid">
                    <div>
                        <label class="form-label">ALT Text</label>
                        <input class="form-control" data-role="img-alt" name="block_${blockId}_img_${index}_alt" value="${existingAlt}" placeholder="Describe the image">
                    </div>
                </div>
                <div class="article-image-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-action="move-up">Move Up</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-action="move-down">Move Down</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-row">Remove Row</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" data-action="clear-file">Clear Selection</button>
                </div>
            </div>
        `;
    }

    function renderBlock(type, id, order) {
        const hiddenFields = `
            <input type="hidden" name="blocks[${id}][type]" value="${type}">
            <input type="hidden" name="blocks[${id}][order]" value="${order}">
        `;

        if (type === 'text') {
            return `
                ${hiddenFields}
                <div class="article-block-header">
                    <h3 class="article-block-title">Paragraph Block</h3>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button>
                </div>
                <div class="article-block-body">
                    <div>
                        <label class="form-label">Content</label>
                        <textarea class="form-control" name="blocks[${id}][content]" rows="7" placeholder="Use one blank line between paragraphs."></textarea>
                    </div>
                </div>
            `;
        }

        if (type === 'image') {
            return `
                ${hiddenFields}
                <div class="article-block-header">
                    <h3 class="article-block-title">Image Block</h3>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button>
                </div>
                <div class="article-block-body">
                    <div class="article-image-rows" data-blockid="${id}">
                        ${buildImageRow(id, 0)}
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-action="add-image-row" data-blockid="${id}">Add Image</button>
                    </div>
                </div>
            `;
        }

        if (type === 'cta') {
            return `
                ${hiddenFields}
                <div class="article-block-header">
                    <h3 class="article-block-title">CTA Block</h3>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button>
                </div>
                <div class="article-block-body">
                    <div>
                        <label class="form-label">CTA Head</label>
                        <input class="form-control" name="blocks[${id}][content][head]" placeholder="Short CTA title">
                    </div>
                    <div>
                        <label class="form-label">CTA Body</label>
                        <textarea class="form-control" name="blocks[${id}][content][body]" rows="4" placeholder="CTA copy"></textarea>
                    </div>
                    <div>
                        <label class="form-label">Links</label>
                        <div class="article-block-body article-cta-links" data-blockid="${id}"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-action="add-cta-link" data-blockid="${id}">Add Link</button>
                    </div>
                </div>
            `;
        }

        if (type === 'list') {
            return `
                ${hiddenFields}
                <div class="article-block-header">
                    <h3 class="article-block-title">List Block</h3>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button>
                </div>
                <div class="article-block-body">
                    <div>
                        <label class="form-label">Head</label>
                        <textarea class="form-control" name="blocks[${id}][content][head]" rows="3" placeholder="Optional intro"></textarea>
                    </div>
                    <div>
                        <label class="form-label">Items</label>
                        <div class="article-block-body article-list-items" data-blockid="${id}"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-action="add-list-item" data-blockid="${id}">Add Item</button>
                    </div>
                </div>
            `;
        }

        if (type === 'list-grouped') {
            return `
                ${hiddenFields}
                <div class="article-block-header">
                    <h3 class="article-block-title">Grouped List Block</h3>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button>
                </div>
                <div class="article-block-body">
                    <div>
                        <label class="form-label">Head</label>
                        <textarea class="form-control" name="blocks[${id}][content][head]" rows="3" placeholder="Optional intro"></textarea>
                    </div>
                    <div>
                        <label class="form-label">Grouped Items</label>
                        <div class="article-block-body article-grouped-items" data-blockid="${id}"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-action="add-group-item" data-blockid="${id}">Add Group</button>
                    </div>
                </div>
            `;
        }

        return `
            ${hiddenFields}
            <div class="article-block-header">
                <h3 class="article-block-title">Unknown Block</h3>
                <button type="button" class="btn btn-outline-danger btn-sm" data-action="remove-block">Delete Block</button>
            </div>
        `;
    }

    function appendBlock(type) {
        if (!detailBlocks) {
            return;
        }

        const id = nextBlockId();
        const block = document.createElement('section');
        block.className = 'article-block';
        block.innerHTML = renderBlock(type, id, nextBlockOrder());
        detailBlocks.appendChild(block);
        reindexOrders();
        reindexImageRows(id);
    }

    function addCtaLink(blockId, label = '', url = '') {
        const container = document.querySelector(`.article-cta-links[data-blockid='${blockId}']`);
        if (!container) {
            return;
        }

        const index = container.querySelectorAll('.article-inline-grid').length;
        const row = document.createElement('div');
        row.className = 'article-inline-grid';
        row.innerHTML = `
            <div>
                <label class="form-label">Label</label>
                <input class="form-control" name="blocks[${blockId}][content][links][${index}][label]" value="${label}" placeholder="Shop now">
            </div>
            <div>
                <label class="form-label">URL</label>
                <input class="form-control" name="blocks[${blockId}][content][links][${index}][url]" value="${url}" placeholder="https://...">
            </div>
        `;
        container.appendChild(row);
    }

    function addListItem(blockId, value = '') {
        const container = document.querySelector(`.article-list-items[data-blockid='${blockId}']`);
        if (!container) {
            return;
        }

        const index = container.querySelectorAll('input').length;
        const input = document.createElement('input');
        input.className = 'form-control';
        input.name = `blocks[${blockId}][content][items][${index}]`;
        input.value = value;
        input.placeholder = `Item ${index + 1}`;
        container.appendChild(input);
    }

    function addGroupItem(blockId, title = '', body = '') {
        const container = document.querySelector(`.article-grouped-items[data-blockid='${blockId}']`);
        if (!container) {
            return;
        }

        const index = container.querySelectorAll("input[name*='[title]']").length;
        const row = document.createElement('div');
        row.className = 'article-block-body';
        row.innerHTML = `
            <input class="form-control" name="blocks[${blockId}][content][items][${index}][title]" value="${title}" placeholder="Title">
            <textarea class="form-control" name="blocks[${blockId}][content][items][${index}][body]" rows="3" placeholder="Body">${body}</textarea>
        `;
        container.appendChild(row);
    }

    window.addMasterArticleBlock = appendBlock;
    window.renderMasterArticleBlock = renderBlock;

    document.querySelectorAll('[data-add-master-article-block]').forEach(button => {
        button.addEventListener('click', () => appendBlock(button.getAttribute('data-add-master-article-block')));
    });

    if (categoryInput && codeInput) {
        categoryInput.addEventListener('change', async () => {
            if (form?.dataset.mode === 'update' && codeInput.value.trim() !== '') {
                return;
            }

            const code = await fetchNextCode(categoryInput.value);
            if (code) {
                setCodeValue(code);
            }
        });

        if (categoryInput.value && codeInput.value.trim() === '') {
            fetchNextCode(categoryInput.value).then(code => {
                if (code) {
                    setCodeValue(code);
                }
            });
        }
    }

    if (titleInput && slugInput) {
        const currentSlug = slugInput.value.trim();
        if (currentSlug !== '' && currentSlug !== slugify(titleInput.value)) {
            slugInput.dataset.touched = 'true';
        }

        titleInput.addEventListener('input', () => {
            if (slugInput.dataset.touched === 'true') {
                return;
            }

            slugInput.value = slugify(titleInput.value);
        });

        slugInput.addEventListener('input', () => {
            slugInput.dataset.touched = 'true';
        });
    }

    if (headerFileInput && headerPreview && headerPreviewWrap && headerClearButton) {
        const existingSrc = headerPreview.dataset.existingSrc || headerPreview.getAttribute('src') || '';

        headerFileInput.addEventListener('change', () => {
            const file = headerFileInput.files && headerFileInput.files[0];
            if (file) {
                updatePreviewImage(headerPreview, file);
                headerPreviewWrap.style.display = 'block';
            } else if (existingSrc !== '') {
                updatePreviewImage(headerPreview, existingSrc);
                headerPreviewWrap.style.display = 'block';
            } else {
                updatePreviewImage(headerPreview, '');
                headerPreviewWrap.style.display = 'none';
            }
        });

        headerClearButton.addEventListener('click', () => {
            headerFileInput.value = '';
            if (existingSrc !== '') {
                updatePreviewImage(headerPreview, existingSrc);
                headerPreviewWrap.style.display = 'block';
            } else {
                updatePreviewImage(headerPreview, '');
                headerPreviewWrap.style.display = 'none';
            }
        });
    }

    document.addEventListener('change', event => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || target.type !== 'file') {
            return;
        }

        if (target === headerFileInput) {
            return;
        }

        const row = target.closest('.article-image-row');
        if (!row) {
            return;
        }

        const preview = row.querySelector('.article-preview-image');
        const file = target.files && target.files[0];
        if (preview instanceof HTMLImageElement) {
            updatePreviewImage(preview, file || '');
        }
    });

    document.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const blockButton = target.closest('[data-action]');
        if (!(blockButton instanceof HTMLElement)) {
            return;
        }

        const action = blockButton.getAttribute('data-action');
        if (!action) {
            return;
        }

        if (action === 'remove-block') {
            const block = blockButton.closest('.article-block');
            if (block) {
                block.remove();
                reindexOrders();
            }
            return;
        }

        if (action === 'add-image-row') {
            const blockId = blockButton.getAttribute('data-blockid') || '';
            const container = document.querySelector(`.article-image-rows[data-blockid='${blockId}']`);
            if (!container) {
                return;
            }

            const index = container.querySelectorAll('.article-image-row').length;
            container.insertAdjacentHTML('beforeend', buildImageRow(blockId, index));
            reindexImageRows(blockId);
            return;
        }

        if (action === 'add-cta-link') {
            addCtaLink(blockButton.getAttribute('data-blockid') || '');
            return;
        }

        if (action === 'add-list-item') {
            addListItem(blockButton.getAttribute('data-blockid') || '');
            return;
        }

        if (action === 'add-group-item') {
            addGroupItem(blockButton.getAttribute('data-blockid') || '');
            return;
        }

        const row = blockButton.closest('.article-image-row');
        const container = blockButton.closest('.article-image-rows');
        if (!row || !container) {
            return;
        }

        const blockId = container.getAttribute('data-blockid') || '';

        if (action === 'move-up' && row.previousElementSibling) {
            container.insertBefore(row, row.previousElementSibling);
            reindexImageRows(blockId);
            return;
        }

        if (action === 'move-down' && row.nextElementSibling) {
            container.insertBefore(row.nextElementSibling, row);
            reindexImageRows(blockId);
            return;
        }

        if (action === 'remove-row') {
            if (container.querySelectorAll('.article-image-row').length <= 1) {
                const fileInput = row.querySelector('input[type="file"]');
                const altInput = row.querySelector('[data-role="img-alt"]');
                const existingInput = row.querySelector('[data-role="img-existing"]');
                const preview = row.querySelector('.article-preview-image');
                if (fileInput instanceof HTMLInputElement) {
                    fileInput.value = '';
                }
                if (altInput instanceof HTMLInputElement) {
                    altInput.value = '';
                }
                if (existingInput instanceof HTMLInputElement) {
                    existingInput.value = '';
                }
                if (preview instanceof HTMLImageElement) {
                    updatePreviewImage(preview, '');
                }
                return;
            }

            row.remove();
            reindexImageRows(blockId);
            return;
        }

        if (action === 'clear-file') {
            const fileInput = row.querySelector('input[type="file"]');
            const existingInput = row.querySelector('[data-role="img-existing"]');
            const preview = row.querySelector('.article-preview-image');
            if (fileInput instanceof HTMLInputElement) {
                fileInput.value = '';
            }
            if (preview instanceof HTMLImageElement) {
                const existingValue = existingInput instanceof HTMLInputElement ? existingInput.value.trim() : '';
                if (existingValue !== '' && preview.dataset.existingSrc) {
                    updatePreviewImage(preview, preview.dataset.existingSrc);
                } else {
                    updatePreviewImage(preview, '');
                }
            }
        }
    });

    document.querySelectorAll('.article-image-rows[data-blockid]').forEach(container => {
        reindexImageRows(container.getAttribute('data-blockid') || '');
    });
});

