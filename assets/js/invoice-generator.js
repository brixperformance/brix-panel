(function () {
  const form = document.getElementById('invoice-generator-form');
  const rowsContainer = document.getElementById('invoice-item-rows');
  const rowTemplate = document.getElementById('invoice-item-row-template');
  const customRowTemplate = document.getElementById('invoice-custom-item-row-template');
  const addRowButton = document.getElementById('add-item-row');
  const addCustomRowButton = document.getElementById('add-custom-item-row');
  const invoiceTypeSelect = document.getElementById('invoice-type-select');
  const billToHelp = document.getElementById('bill-to-help');
  const billToSelect = document.getElementById('bill-to-select');
  const billToDealerField = document.getElementById('bill-to-dealer-field');
  const billToCustomerField = document.getElementById('bill-to-customer-field');
  const billToCustomerInput = document.getElementById('bill-to-customer-input');
  const billToValueInput = document.getElementById('bill-to-value');
  const shipToValueInput = document.getElementById('ship-to-value');
  const shippingModeSelect = document.getElementById('shipping-mode-select');
  const shippingManualFields = document.getElementById('shipping-manual-fields');
  const shippingAutoFields = document.getElementById('shipping-auto-fields');
  const shippingAutoOverrideField = document.getElementById('shipping-auto-override-field');
  const shippingManualShipToInput = document.getElementById('shipping-manual-shipto');
  const shippingAutoShipToOverrideInput = document.getElementById('shipping-auto-shipto-override');
  const shippingDeliveryGroupSelect = document.getElementById('shipping-delivery-group-select');
  const shippingAreaBasedFields = document.getElementById('shipping-area-based-fields');
  const shippingPointBasedFields = document.getElementById('shipping-point-based-fields');
  const discountFlatInput    = document.getElementById('discount-flat-input');
  const discountPercentInput = document.getElementById('discount-percent-input');
  const discountMaxInput     = document.getElementById('discount-max-input');
  const discountFlatWrap     = document.getElementById('discount-flat-wrap');
  const discountPercentWrap  = document.getElementById('discount-percent-wrap');
  const discountTypeHidden   = document.getElementById('discount-type-hidden');
  const discountValueHidden  = document.getElementById('discount-value-hidden');
  const discountMaxHidden    = document.getElementById('discount-max-hidden');
  const discountPercentPreview = document.getElementById('discount-percent-preview');
  const shippingCostInput = document.getElementById('shipping-cost-input');
  const shippingCostHiddenInput = document.getElementById('shipping-cost');
  const shippingTotalWeightDisplay = document.getElementById('shipping-total-weight-display');
  const additionalFeeInput = document.getElementById('additional-fee-input');
  const additionalFeeLabelInput = document.getElementById('additional-fee-label-input');
  const additionalFeeHiddenInput = document.getElementById('additional-fee');
  const additionalFeeLabelHiddenInput = document.getElementById('additional-fee-label');
  const footerNotesHeaderInput  = document.getElementById('footer-notes-header-input');
  const footerNotesHeaderField  = document.getElementById('footer-notes-header');
  const footerNotesInput        = document.getElementById('footer-notes-input');
  const footerNoteInputs        = [1, 2, 3].map((n) => document.getElementById(`footer-notes-${n}`)).filter(Boolean);
  const footerNotesResetBtn     = document.getElementById('footer-notes-reset-btn');

  const footerClosingHeaderInput  = document.getElementById('footer-closing-header-input');
  const footerClosingHeaderField  = document.getElementById('footer-closing-header');
  const footerClosingMessageInput = document.getElementById('footer-closing-message-input');
  const footerClosingInputs       = [1].map((n) => document.getElementById(`footer-closing-${n}`)).filter(Boolean);
  const footerClosingResetBtn     = document.getElementById('footer-closing-reset-btn');

  const footerPreviewNotesHeader   = document.getElementById('footer-preview-notes-header');
  const footerPreviewClosingHeader = document.getElementById('footer-preview-closing-header');
  const footerCounters = Array.from(document.querySelectorAll('[data-footer-counter-for]'));
  const footerResetButtons = Array.from(document.querySelectorAll('[data-footer-reset-for]'));
  const footerPreviewFrame = document.getElementById('footer-preview-frame');
  const footerPreviewScaleWrap = document.getElementById('footer-preview-scale-wrap');
  const footerPreviewCanvas = document.getElementById('footer-preview-canvas');
  const footerPreviewNotes = document.getElementById('footer-preview-notes');
  const footerPreviewClosing = document.getElementById('footer-preview-closing');
  const subtotalPreview = document.getElementById('subtotal-preview');
  const totalPreview = document.getElementById('total-preview');

  if (!form || !rowsContainer || !rowTemplate || !customRowTemplate || !addRowButton || !addCustomRowButton) {
    return;
  }

  // ── Invoice Meta dates (Asia/Jakarta = UTC+7) ──
  (function initMetaDates() {
    function jakartaDate(offsetDays) {
      const now = new Date();
      const jakarta = new Date(now.getTime() + (7 * 60 - now.getTimezoneOffset()) * 60000);
      jakarta.setDate(jakarta.getDate() + (offsetDays || 0));
      const y = jakarta.getFullYear();
      const m = String(jakarta.getMonth() + 1).padStart(2, '0');
      const d = String(jakarta.getDate()).padStart(2, '0');
      const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
      return { iso: `${y}-${m}-${d}`, display: `${months[jakarta.getMonth()]} ${d}, ${y}` };
    }

    const dateInput = document.getElementById('meta-invoice-date');
    if (dateInput) dateInput.value = jakartaDate(0).display;
  })();

  (function initDueDatePicker() {
    const el     = document.getElementById('meta-due-date');
    const hidden = document.getElementById('due-date-hidden');
    if (!el || typeof flatpickr === 'undefined') return;

    function jakartaDateObj(offsetDays) {
      const now = new Date();
      const jkt = new Date(now.getTime() + (7 * 60 - now.getTimezoneOffset()) * 60000);
      jkt.setDate(jkt.getDate() + offsetDays);
      return new Date(jkt.getFullYear(), jkt.getMonth(), jkt.getDate());
    }

    function toIso(dateObj) {
      return [
        dateObj.getFullYear(),
        String(dateObj.getMonth() + 1).padStart(2, '0'),
        String(dateObj.getDate()).padStart(2, '0'),
      ].join('-');
    }

    const todayDate   = jakartaDateObj(0);
    const defaultDate = jakartaDateObj(3);

    if (hidden) hidden.value = toIso(defaultDate);

    const fp = flatpickr(el, {
      dateFormat: 'F j, Y',
      minDate: todayDate,
      defaultDate: defaultDate,
      allowInput: true,
      onReady: function (_, __, instance) {
        const w = el.offsetWidth;
        if (w > 0) {
          instance.calendarContainer.style.width    = w + 'px';
          instance.calendarContainer.style.minWidth = 'unset';
        }
      },
      onChange: function (selectedDates) {
        if (!hidden || selectedDates.length === 0) return;
        hidden.value = toIso(selectedDates[0]);
      },
      onClose: function (selectedDates) {
        if (selectedDates.length === 0 || selectedDates[0] < todayDate) {
          fp.setDate(todayDate, true);
        }
      },
    });
  })();

  function updateFooterCounter(input) {
    if (!input || !input.id) return;

    const counter = document.querySelector(`[data-footer-counter-for="${input.id}"]`);
    if (!counter) return;

    const used = input.value.length;
    const max = Number(input.getAttribute('maxlength') || 0);
    counter.textContent = max > 0 ? `${used} / ${max}` : `${used}`;

    counter.classList.remove('is-warning', 'is-limit');
    if (max > 0) {
      if (used >= max) {
        counter.classList.add('is-limit');
      } else if (used >= Math.floor(max * 0.85)) {
        counter.classList.add('is-warning');
      }
    }
  }

  function updateFooterDraftState(input) {
    if (!input || !input.id) return;

    const status = document.querySelector(`[data-footer-status-for="${input.id}"]`);
    const resetButton = document.querySelector(`[data-footer-reset-for="${input.id}"]`);
    const isDraft = input.value !== input.defaultValue;

    input.classList.toggle('is-draft', isDraft);

    if (status) {
      status.textContent = isDraft ? 'Draft' : 'Default';
      status.classList.toggle('is-draft', isDraft);
    }

    if (resetButton) {
      resetButton.hidden = !isDraft;
    }
  }

  function getEffectiveFooterValue(input) {
    if (!input) return '';
    const value = input.value.trim();
    return value !== '' ? value : input.defaultValue.trim();
  }

  function renderFooterPreviewParagraphs(container, paragraphs, isClosing) {
    if (!container) return;

    container.innerHTML = '';
    paragraphs.forEach((paragraph, index) => {
      const p = document.createElement('p');
      if (isClosing && index === 0) {
        p.classList.add('is-lead');
      }

      const lines = paragraph.split(/\n/);
      lines.forEach((line, lineIndex) => {
        if (lineIndex > 0) {
          p.appendChild(document.createElement('br'));
        }
        p.appendChild(document.createTextNode(line));
      });

      container.appendChild(p);
    });
  }

  function fitFooterPreview() {
    if (!footerPreviewFrame || !footerPreviewScaleWrap || !footerPreviewCanvas) return;

    footerPreviewScaleWrap.style.width = '';
    footerPreviewScaleWrap.style.height = '';
    footerPreviewCanvas.style.transform = '';

    const frameStyle = getComputedStyle(footerPreviewFrame);
    const framePadding = parseFloat(frameStyle.paddingLeft) + parseFloat(frameStyle.paddingRight);
    const availableWidth = footerPreviewFrame.clientWidth - framePadding;
    const canvasWidth = footerPreviewCanvas.offsetWidth;
    if (!availableWidth || !canvasWidth) return;

    const scale = availableWidth / canvasWidth;
    footerPreviewScaleWrap.style.width = `${canvasWidth * scale}px`;
    footerPreviewScaleWrap.style.height = `${footerPreviewCanvas.offsetHeight * scale}px`;
    footerPreviewCanvas.style.transform = `scale(${scale})`;
    footerPreviewCanvas.style.transformOrigin = 'top left';
  }

  function syncNotesHidden() {
    if (footerNotesHeaderInput && footerNotesHeaderField) {
      footerNotesHeaderInput.value = footerNotesHeaderField.value.trim();
    }
    if (footerNotesInput) {
      footerNotesInput.value = footerNoteInputs.map((inp) => inp.value.trim()).filter(Boolean).join('\n\n');
    }
  }

  function syncClosingHidden() {
    if (footerClosingHeaderInput && footerClosingHeaderField) {
      footerClosingHeaderInput.value = footerClosingHeaderField.value.trim();
    }
    if (footerClosingMessageInput) {
      footerClosingMessageInput.value = footerClosingInputs.map((inp) => inp.value.trim()).filter(Boolean).join('\n\n');
    }
  }

  function setElementHtml(el, text) {
    if (!el) return;
    el.innerHTML = '';
    text.split('\n').forEach((line, i) => {
      if (i > 0) el.appendChild(document.createElement('br'));
      el.appendChild(document.createTextNode(line));
    });
  }

  function autoGrow(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
  }

  function updateFooterPreview() {
    const notesHeaderText = footerNotesHeaderField?.value.trim() || footerNotesHeaderField?.defaultValue.trim() || 'Notes';
    setElementHtml(footerPreviewNotesHeader, notesHeaderText);

    const closingHeaderText = footerClosingHeaderField?.value.trim() || footerClosingHeaderField?.defaultValue.trim() || '';
    setElementHtml(footerPreviewClosingHeader, closingHeaderText);

    const notesParagraphs = footerNoteInputs.map((inp) => inp.value.trim()).filter(Boolean);
    const closingParagraphs = footerClosingInputs.map((inp) => inp.value.trim()).filter(Boolean);

    renderFooterPreviewParagraphs(footerPreviewNotes, notesParagraphs, false);
    renderFooterPreviewParagraphs(footerPreviewClosing, closingParagraphs, false);
    fitFooterPreview();
  }

  function updateFooterFieldUI(input) {
    updateFooterCounter(input);
    updateFooterDraftState(input);
    updateFooterPreview();
  }

  function initFooterCounters() {
    footerCounters.forEach((counter) => {
      const inputId = counter.getAttribute('data-footer-counter-for');
      if (!inputId) return;

      const input = document.getElementById(inputId);
      if (!input) return;

      updateFooterFieldUI(input);
      input.addEventListener('input', () => updateFooterFieldUI(input));
    });
  }

  function initFooterResetButtons() {
    footerResetButtons.forEach((button) => {
      const inputId = button.getAttribute('data-footer-reset-for');
      if (!inputId) return;

      const input = document.getElementById(inputId);
      if (!input) return;

      button.hidden = input.value === input.defaultValue;
      button.addEventListener('click', () => {
        input.value = input.defaultValue;
        updateFooterFieldUI(input);
        input.focus();
      });
    });
  }

  initFooterCounters();
  initFooterResetButtons();

  const paragraphLineInputs = [footerNotesHeaderField, ...footerNoteInputs, ...footerClosingInputs].filter(Boolean);
  const headerLineInputs    = [footerClosingHeaderField].filter(Boolean);
  const allFooterLineInputs = [...paragraphLineInputs, ...headerLineInputs];

  allFooterLineInputs.forEach((inp) => autoGrow(inp));

  paragraphLineInputs.forEach((inp) => {
    inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
  });

  if (footerNotesHeaderField) {
    updateFooterCounter(footerNotesHeaderField);
    footerNotesHeaderField.addEventListener('input', () => {
      autoGrow(footerNotesHeaderField);
      updateFooterCounter(footerNotesHeaderField);
      syncNotesHidden();
      updateFooterPreview();
    });
  }

  footerNoteInputs.forEach((inp) => {
    updateFooterCounter(inp);
    inp.addEventListener('input', () => {
      autoGrow(inp);
      updateFooterCounter(inp);
      syncNotesHidden();
      updateFooterPreview();
    });
  });

  if (footerNotesResetBtn) {
    footerNotesResetBtn.addEventListener('click', () => {
      [footerNotesHeaderField, ...footerNoteInputs].filter(Boolean).forEach((inp) => {
        inp.value = inp.defaultValue;
        autoGrow(inp);
        updateFooterCounter(inp);
      });
      syncNotesHidden();
      updateFooterPreview();
      footerNotesHeaderField?.focus();
    });
  }

  if (footerClosingHeaderField) {
    updateFooterCounter(footerClosingHeaderField);
    footerClosingHeaderField.addEventListener('input', () => {
      autoGrow(footerClosingHeaderField);
      updateFooterCounter(footerClosingHeaderField);
      syncClosingHidden();
      updateFooterPreview();
    });
  }

  footerClosingInputs.forEach((inp) => {
    updateFooterCounter(inp);
    inp.addEventListener('input', () => {
      autoGrow(inp);
      updateFooterCounter(inp);
      syncClosingHidden();
      updateFooterPreview();
    });
  });

  if (footerClosingResetBtn) {
    footerClosingResetBtn.addEventListener('click', () => {
      [footerClosingHeaderField, ...footerClosingInputs].filter(Boolean).forEach((inp) => {
        inp.value = inp.defaultValue;
        autoGrow(inp);
        updateFooterCounter(inp);
      });
      syncClosingHidden();
      updateFooterPreview();
      footerClosingHeaderField?.focus();
    });
  }

  updateFooterPreview();
  window.addEventListener('resize', fitFooterPreview);

  const comboboxStates = [];
  let invoiceItemOptions = [];

  function parseCurrency(value) {
    const raw = String(value || '').trim().replace(/IDR\s*/i, '').replace(/\s+/g, '');
    if (!raw) return 0;

    if (raw.includes(',') && raw.includes('.')) {
      const normalized = raw.replace(/,/g, '');
      const parsed = Number(normalized);
      return Number.isFinite(parsed) ? parsed : 0;
    }

    if (raw.includes(',')) {
      const normalized = raw.replace(/,/g, '');
      const parsed = Number(normalized);
      return Number.isFinite(parsed) ? parsed : 0;
    }

    if (raw.includes('.')) {
      const dotParts = raw.split('.');

      if (dotParts.length > 2) {
        const normalized = dotParts.join('');
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
      }

      const [, decimalPart = ''] = dotParts;
      const normalized = decimalPart.length <= 2 ? raw : raw.replace(/\./g, '');
      const parsed = Number(normalized);
      return Number.isFinite(parsed) ? parsed : 0;
    }

    const digitsOnly = raw.replace(/[^\d]/g, '');
    return digitsOnly ? Number(digitsOnly) : 0;
  }

  function formatCurrency(value) {
    return 'IDR ' + Number(value || 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function formatCurrencyInput(input) {
    if (!input) return;
    const numericValue = parseCurrency(input.value);
    input.value = numericValue > 0 ? formatCurrency(numericValue) : '';
  }

  function formatQuantityInput(input) {
    if (!input) return;
    if (input.value === '') return;
    const numericValue = Number(input.value);
    input.value = Number.isFinite(numericValue) && numericValue >= 0 ? String(numericValue) : '';
  }

  function getSelectedText(field) {
    if (!field) return '';
    const option = field.options[field.selectedIndex];
    return option ? option.text.trim() : '';
  }

  function getSelectedOption(field) {
    if (!field) return null;
    return field.options[field.selectedIndex] || null;
  }

  function getInvoiceType() {
    return invoiceTypeSelect?.value === 'customer' ? 'customer' : 'dealer';
  }

  function getInvoicePriceKey() {
    return getInvoiceType() === 'customer' ? 'retailPrice' : 'resellerPrice';
  }

  function getDiscountAmount(subtotal) {
    if (discountTypeHidden?.value === 'percent') {
      const pct = parseFloat(discountPercentInput?.value || '0') || 0;
      const max = parseCurrency(discountMaxInput?.value);
      let amount = subtotal * (pct / 100);
      if (max > 0) amount = Math.min(amount, max);
      return amount;
    }
    return parseCurrency(discountFlatInput?.value);
  }

  function getShippingMode() {
    return shippingModeSelect?.value === 'automatic' ? 'automatic' : 'manual';
  }

  function getShippingDeliveryGroup() {
    return shippingDeliveryGroupSelect?.value === 'point_based' ? 'point_based' : 'area_based';
  }

  function getAdditionalFeeLabel() {
    return additionalFeeLabelInput?.value.trim() || '';
  }

  function splitFooterParagraphs(value) {
    return String(value || '')
      .trim()
      .replace(/\r\n?/g, '\n')
      .split(/\n{2,}/)
      .map((part) => part.trim())
      .filter(Boolean);
  }

  function validateFooterField(input, label) {
    if (!input) return '';

    const value = input.value.trim();
    if (!value) return '';

    const maxChars = Number(input.getAttribute('maxlength') || 0);
    const maxParagraphs = Number(input.dataset.maxParagraphs || 0);
    const maxCharsPerParagraph = Number(input.dataset.maxCharsPerParagraph || 0);

    if (maxChars > 0 && value.length > maxChars) {
      return `${label} maksimal ${maxChars} karakter.`;
    }

    const paragraphs = splitFooterParagraphs(value);
    if (maxParagraphs > 0 && paragraphs.length > maxParagraphs) {
      return `${label} maksimal ${maxParagraphs} paragraf.`;
    }

    if (maxCharsPerParagraph > 0 && paragraphs.some((paragraph) => paragraph.length > maxCharsPerParagraph)) {
      return `${label} maksimal ${maxCharsPerParagraph} karakter per paragraf.`;
    }

    return '';
  }

  function syncBillToHidden() {
    const isCustomer      = getInvoiceType() === 'customer';
    const dealerCodeInput = document.getElementById('dealer-code-value');

    const billToValue = isCustomer
      ? (billToCustomerInput?.value.trim() || '')
      : (getSelectedOption(billToSelect)?.dataset?.billTo?.trim() || '');

    if (billToValueInput) {
      billToValueInput.value = billToValue;
    }

    if (dealerCodeInput) {
      dealerCodeInput.value = isCustomer ? '' : (billToSelect?.value || '');
    }
  }

  function getRowItemValue(row) {
    const manualInput = row.querySelector('.invoice-item-manual-input');
    if (manualInput) {
      return manualInput.value.trim();
    }

    return row.querySelector('.invoice-item-select')?.value.trim() || '';
  }

  function hasValidInvoiceItems() {
    const items = Array.from(rowsContainer.querySelectorAll('.invoice-item-row'));
    return items.some((row) => {
      const item = getRowItemValue(row);
      const quantity = Number(row.querySelector('input[name="quantity[]"]')?.value || 0);
      const rate = parseCurrency(row.querySelector('input[name="rate[]"]')?.value);
      return item !== '' && quantity > 0 && rate > 0;
    });
  }

  function getTotalItemQuantity() {
    let totalQty = 0;
    rowsContainer.querySelectorAll('.invoice-item-row').forEach((row) => {
      const item = getRowItemValue(row);
      const quantity = Number(row.querySelector('input[name="quantity[]"]')?.value || 0);
      if (item !== '' && quantity > 0) {
        totalQty += quantity;
      }
    });
    return totalQty;
  }

  function getShippingWeightGrams() {
    const totalQty = getTotalItemQuantity();
    const totalKg = Math.max(1, Math.ceil(totalQty / 3));
    return totalKg * 1000;
  }

  function formatWeightLabel(weightGrams) {
    const kg = Number(weightGrams || 0) / 1000;
    return kg + ' kg';
  }

  function getItemShippingSignature() {
    return Array.from(rowsContainer.querySelectorAll('.invoice-item-row')).map((row) => {
      const item = getRowItemValue(row);
      const quantity = Number(row.querySelector('input[name="quantity[]"]')?.value || 0);
      return item + ':' + quantity;
    }).join('|');
  }

  function toggleCalculationLocks(hasValidItems) {
    const shippingLock = document.getElementById('shipping-fees-lock');
    const discountLock = document.getElementById('discount-fees-lock');
    const unlocked = !!hasValidItems;

    if (shippingLock) {
      shippingLock.hidden = unlocked;
    }
    if (discountLock) {
      discountLock.hidden = unlocked;
    }
  }

  function syncShipToValue() {
    if (!shipToValueInput) return;

    if (getShippingMode() === 'manual') {
      shipToValueInput.value = shippingManualShipToInput?.value.trim() || '';
      return;
    }

    const override = shippingAutoShipToOverrideInput?.value.trim() || '';
    if (override !== '') {
      shipToValueInput.value = override;
      return;
    }

    if (getShippingDeliveryGroup() === 'point_based') {
      const pointLabel = document.getElementById('shipping-point-label')?.value.trim() || '';
      const lat = document.getElementById('shipping-point-lat-hidden')?.value.trim() || '';
      const lng = document.getElementById('shipping-point-lng-hidden')?.value.trim() || '';
      const parts = [pointLabel];
      if (lat && lng) {
        parts.push(`Pinned Location (${lat}, ${lng})`);
      }
      parts.push(getSelectedText(document.getElementById('shipping-country-select')));
      shipToValueInput.value = parts.filter(Boolean).join(', ');
      return;
    }

    const parts = [
      getSelectedText(document.getElementById('shipping-subdistrict-select')),
      getSelectedText(document.getElementById('shipping-district-select')),
      getSelectedText(document.getElementById('shipping-city-select')),
      getSelectedText(document.getElementById('shipping-province-select')),
      getSelectedText(document.getElementById('shipping-country-select')),
      document.getElementById('shipping-postal-display')?.value.trim() || ''
    ].filter(Boolean);
    shipToValueInput.value = parts.join(', ');
  }

  function applyShippingModeUI() {
    const isAutomatic = getShippingMode() === 'automatic';
    const isPointBased = getShippingDeliveryGroup() === 'point_based';

    if (shippingManualFields) shippingManualFields.hidden = isAutomatic;
    if (shippingAutoFields) shippingAutoFields.hidden = !isAutomatic;
    if (shippingAutoOverrideField) shippingAutoOverrideField.hidden = !isAutomatic;
    if (shippingAreaBasedFields) shippingAreaBasedFields.hidden = !isAutomatic || isPointBased;
    if (shippingPointBasedFields) shippingPointBasedFields.hidden = !isAutomatic || !isPointBased;
    if (shippingCostInput) {
      shippingCostInput.readOnly = isAutomatic;
      shippingCostInput.placeholder = isAutomatic ? 'Calculated automatically' : 'IDR 0.00';
    }

    if (isAutomatic && isPointBased) {
      requestAnimationFrame(function () {
        window.dispatchEvent(new Event('resize'));
      });
    }

    syncShipToValue();
  }

  function updateSummary() {
    const hasValidItems = hasValidInvoiceItems();
    let subtotal = 0;
    rowsContainer.querySelectorAll('.invoice-item-row').forEach((row) => {
      const qtyInput = row.querySelector('input[name="quantity[]"]');
      const rateInput = row.querySelector('input[name="rate[]"]');
      const amountOutput = row.querySelector('.amount-output');
      const quantity = Number(qtyInput?.value || 0);
      const rate = parseCurrency(rateInput?.value);
      const amount = quantity * rate;

      subtotal += amount;
      if (amountOutput) {
        amountOutput.value = formatCurrency(amount);
      }
    });

    const discount       = getDiscountAmount(subtotal);
    const shipping       = parseCurrency(shippingCostInput?.value);
    const additionalFee  = parseCurrency(additionalFeeInput?.value);
    const total          = subtotal - discount + shipping + additionalFee;

    if (discountTypeHidden?.value === 'percent' && discountPercentPreview) {
      discountPercentPreview.textContent = discount > 0 ? '= ' + formatCurrency(discount) : '';
    }
    if (shippingCostHiddenInput) {
      shippingCostHiddenInput.value = String(shipping);
    }
    if (additionalFeeHiddenInput) {
      additionalFeeHiddenInput.value = String(additionalFee);
    }
    if (additionalFeeLabelHiddenInput) {
      additionalFeeLabelHiddenInput.value = getAdditionalFeeLabel();
    }
    if (subtotalPreview) subtotalPreview.textContent = formatCurrency(subtotal);
    if (totalPreview) totalPreview.textContent = formatCurrency(total);
    if (shippingTotalWeightDisplay) {
      shippingTotalWeightDisplay.value = formatWeightLabel(getShippingWeightGrams());
    }
    toggleCalculationLocks(hasValidItems);
    syncShipToValue();

    document.dispatchEvent(new CustomEvent('invoice-items-changed', {
      detail: {
        hasValidItems: hasValidItems,
        itemSignature: getItemShippingSignature(),
        weightGrams: getShippingWeightGrams()
      }
    }));
  }

  function syncInvoiceTypeUI() {
    const isCustomer = getInvoiceType() === 'customer';

    if (billToDealerField) {
      billToDealerField.hidden = isCustomer;
    }
    if (billToCustomerField) {
      billToCustomerField.hidden = !isCustomer;
    }
    if (billToHelp) {
      billToHelp.textContent = isCustomer
        ? 'Enter bill-to text manually for customer invoice.'
        : 'Select dealer from master dealer data.';
    }
    if (billToSelect) {
      billToSelect.required = !isCustomer;
      billToSelect.disabled = isCustomer;
      syncCombobox(billToSelect);
    }
    if (billToCustomerInput) {
      billToCustomerInput.required = isCustomer;
      billToCustomerInput.disabled = !isCustomer;
    }

    rowsContainer.querySelectorAll('.invoice-item-row').forEach((row) => {
      if (row.dataset.rowMode === 'preset') {
        syncRowRateFromSelection(row);
      }
    });

    syncBillToHidden();
    updateSummary();
  }

  function updateFloatingMenuPosition(state) {
    if (!state || state.menu.hidden) return;

    const rect = state.trigger.getBoundingClientRect();
    state.menu.style.position = 'fixed';
    state.menu.style.top = Math.round(rect.bottom + 6) + 'px';
    state.menu.style.left = Math.round(rect.left) + 'px';
    state.menu.style.width = Math.round(rect.width) + 'px';
    state.menu.style.zIndex = '9999';
  }

  function renderComboboxOptions(state) {
    const keyword = (state.search.value || '').toLowerCase();
    const options = Array.from(state.field.options).filter((option) => option.value !== '');
    const filtered = keyword
      ? options.filter((option) => option.text.toLowerCase().includes(keyword))
      : options;

    state.options.innerHTML = '';

    if (!filtered.length) {
      const empty = document.createElement('div');
      empty.className = 'shipping-combobox-empty';
      empty.textContent = 'No results';
      state.options.appendChild(empty);
      return;
    }

    filtered.forEach((option) => {
      const button = document.createElement('button');
      button.type = 'button';
      const isDisabled = !!option.disabled;
      button.className = 'shipping-combobox-option'
        + (state.field.value === option.value ? ' is-active' : '')
        + (isDisabled ? ' is-disabled' : '');
      button.textContent = option.text;
      button.disabled = isDisabled;
      button.addEventListener('click', () => {
        if (isDisabled) return;
        state.field.value = option.value;
        syncCombobox(state.field);
        closeCombobox(state);
        state.field.dispatchEvent(new Event('change', { bubbles: true }));
      });
      state.options.appendChild(button);
    });
  }

  function openCombobox(state) {
    if (state.field.disabled) return;
    if (state.menu.parentNode !== document.body) {
      state.originalParent = state.menu.parentNode;
      state.originalNextSibling = state.menu.nextSibling;
      document.body.appendChild(state.menu);
    }
    state.menu.hidden = false;
    state.combobox.classList.add('is-open');
    state.search.value = '';
    updateFloatingMenuPosition(state);
    renderComboboxOptions(state);
    state.search.focus();
  }

  function closeCombobox(state) {
    state.menu.hidden = true;
    state.combobox.classList.remove('is-open');
    state.search.value = '';
    state.menu.style.position = '';
    state.menu.style.top = '';
    state.menu.style.left = '';
    state.menu.style.width = '';
    state.menu.style.zIndex = '';
    if (state.originalParent && state.menu.parentNode === document.body) {
      state.originalParent.insertBefore(state.menu, state.originalNextSibling);
    }
  }

  function syncCombobox(field) {
    const state = comboboxStates.find((item) => item.field === field);
    if (!state) return;

    const selectedText = getSelectedText(field);
    state.label.textContent = selectedText || state.placeholder;
    state.trigger.disabled = field.disabled;
  }

  function registerCombobox(field, placeholder) {
    const combobox = field?.closest('[data-combobox]');
    if (!field || !combobox) return;
    if (comboboxStates.some((state) => state.field === field)) return;

    const trigger = combobox.querySelector('[data-combobox-trigger]');
    const menu = combobox.querySelector('[data-combobox-menu]');
    const search = combobox.querySelector('[data-combobox-search]');
    const options = combobox.querySelector('[data-combobox-options]');
    const label = combobox.querySelector('[data-combobox-label]');
    if (!trigger || !menu || !search || !options || !label) return;

    const state = { field, combobox, trigger, menu, search, options, label, placeholder, originalParent: null, originalNextSibling: null };
    comboboxStates.push(state);

    trigger.addEventListener('click', () => {
      const isOpen = !menu.hidden;
      comboboxStates.forEach(closeCombobox);
      if (!isOpen) {
        openCombobox(state);
      }
    });

    search.addEventListener('input', () => renderComboboxOptions(state));
    syncCombobox(field);
  }

  function setInvoiceItemOptions(field, rows) {
    if (!field) return;

    field.innerHTML = '';
    const first = document.createElement('option');
    first.value = '';
    first.textContent = 'Select Item';
    field.appendChild(first);

    rows.forEach((row) => {
      const option = document.createElement('option');
      option.value = String(row.label || '');
      option.textContent = String(row.label || '');
      option.dataset.itemId = String(row.id || '');
      option.dataset.resellerPrice = String(row.reseller_price || 0);
      option.dataset.retailPrice = String(row.retail_price || 0);
      field.appendChild(option);
    });

    field.disabled = rows.length === 0;
    syncCombobox(field);
  }

  function getSelectedPresetItemValues() {
    return Array.from(rowsContainer.querySelectorAll('.invoice-item-row[data-row-mode="preset"] .invoice-item-select'))
      .map((field) => field.value.trim())
      .filter(Boolean);
  }

  function refreshInvoiceItemAvailability() {
    const allSelected = getSelectedPresetItemValues();
    rowsContainer.querySelectorAll('.invoice-item-row[data-row-mode="preset"] .invoice-item-select').forEach((field) => {
      const currentValue = field.value.trim();
      Array.from(field.options).forEach((option) => {
        if (option.value === '') {
          option.disabled = false;
          return;
        }
        option.disabled = option.value !== currentValue && allSelected.includes(option.value);
      });
      syncCombobox(field);
    });
  }

  async function fetchInvoiceItemOptions() {
    const response = await fetch('/api/invoice-item-options', {
      headers: { Accept: 'application/json' }
    });
    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.error || 'Invoice item options are unavailable.');
    }

    return Array.isArray(data.results) ? data.results : [];
  }

  function syncRowRateFromSelection(row) {
    const itemSelect = row.querySelector('.invoice-item-select');
    const rateInput = row.querySelector('input[name="rate[]"]');
    if (!itemSelect || !rateInput) return;
    const selectedOption = getSelectedOption(itemSelect);
    const priceKey = getInvoicePriceKey();
    const priceValue = Number(selectedOption?.dataset?.[priceKey] || 0);

    if (rateInput) {
      rateInput.value = priceValue > 0 ? formatCurrency(priceValue) : '';
    }

    updateSummary();
  }

  function bindRow(row) {
    const itemSelect = row.querySelector('.invoice-item-select');
    const manualItemInput = row.querySelector('.invoice-item-manual-input');
    const quantityInput = row.querySelector('input[name="quantity[]"]');
    const rateInput = row.querySelector('input[name="rate[]"]');
    const removeButton = row.querySelector('.remove-row-button');

    if (itemSelect) {
      setInvoiceItemOptions(itemSelect, invoiceItemOptions);
      registerCombobox(itemSelect, 'Select Item');
      itemSelect.addEventListener('change', () => {
        refreshInvoiceItemAvailability();
        syncRowRateFromSelection(row);
      });
    }

    manualItemInput?.addEventListener('input', updateSummary);

    quantityInput?.addEventListener('input', updateSummary);
    quantityInput?.addEventListener('blur', () => {
      formatQuantityInput(quantityInput);
      updateSummary();
    });

    rateInput?.addEventListener('input', updateSummary);
    rateInput?.addEventListener('blur', () => {
      formatCurrencyInput(rateInput);
      updateSummary();
    });

    removeButton?.addEventListener('click', () => {
      if (rowsContainer.children.length === 1) {
        Swal.fire({ icon: 'info', title: 'At least one row is required.' });
        return;
      }

      Swal.fire({
        icon: 'warning',
        title: 'Hapus item ini?',
        text: 'Item akan dihapus dari invoice.',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c7a89',
        confirmButtonText: 'Hapus',
        cancelButtonText: 'Batal',
      }).then((result) => {
        if (!result.isConfirmed) return;
        row.remove();
        refreshInvoiceItemAvailability();
        updateSummary();
      });
    });
  }

  function appendRowFromTemplate(template) {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector('.invoice-item-row');
    if (!row) return;

    rowsContainer.appendChild(row);
    bindRow(row);
    refreshInvoiceItemAvailability();
    updateSummary();
    return row;
  }

  function addRow() {
    const row = appendRowFromTemplate(rowTemplate);
    row?.querySelector('.shipping-combobox-trigger')?.focus();
  }

  function addCustomRow() {
    const row = appendRowFromTemplate(customRowTemplate);
    row?.querySelector('.invoice-item-manual-input')?.focus();
  }

  addRowButton.addEventListener('click', addRow);
  addCustomRowButton.addEventListener('click', addCustomRow);
  rowsContainer.querySelectorAll('.invoice-item-row').forEach(bindRow);

  registerCombobox(invoiceTypeSelect, 'Dealer Invoice');
  registerCombobox(billToSelect, 'Select Dealer');
  registerCombobox(shippingModeSelect, 'Fill Manually');
  registerCombobox(shippingDeliveryGroupSelect, 'Regular Delivery');

  document.addEventListener('click', (event) => {
    comboboxStates.forEach((state) => {
      if (!state.combobox.contains(event.target) && !state.menu.contains(event.target)) {
        closeCombobox(state);
      }
    });
  });

  window.addEventListener('scroll', () => {
    comboboxStates.forEach((state) => {
      if (!state.menu.hidden) {
        updateFloatingMenuPosition(state);
      }
    });
  }, true);

  window.addEventListener('resize', () => {
    comboboxStates.forEach((state) => {
      if (!state.menu.hidden) {
        updateFloatingMenuPosition(state);
      }
    });
  });

  invoiceTypeSelect?.addEventListener('change', syncInvoiceTypeUI);
  billToSelect?.addEventListener('change', () => {
    syncBillToHidden();
  });
  billToCustomerInput?.addEventListener('input', () => {
    syncBillToHidden();
  });
  shippingManualShipToInput?.addEventListener('input', syncShipToValue);
  shippingAutoShipToOverrideInput?.addEventListener('input', syncShipToValue);
  shippingDeliveryGroupSelect?.addEventListener('change', applyShippingModeUI);
  document.getElementById('shipping-point-label')?.addEventListener('input', syncShipToValue);
  document.querySelectorAll('#invoice-generator-form .discount-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      const type = tab.dataset.type;
      document.querySelectorAll('#invoice-generator-form .discount-tab').forEach((t) => t.classList.remove('is-active'));
      tab.classList.add('is-active');
      if (discountTypeHidden) discountTypeHidden.value = type;
      if (discountFlatWrap) discountFlatWrap.hidden = type !== 'flat';
      if (discountPercentWrap) discountPercentWrap.hidden = type !== 'percent';
      if (discountPercentPreview) discountPercentPreview.textContent = '';
      updateSummary();
    });
  });

  discountFlatInput?.addEventListener('input', updateSummary);
  discountFlatInput?.addEventListener('blur', () => {
    formatCurrencyInput(discountFlatInput);
    updateSummary();
  });
  discountPercentInput?.addEventListener('input', updateSummary);
  discountMaxInput?.addEventListener('input', updateSummary);
  discountMaxInput?.addEventListener('blur', () => {
    formatCurrencyInput(discountMaxInput);
    updateSummary();
  });
  shippingCostInput?.addEventListener('input', updateSummary);
  shippingCostInput?.addEventListener('blur', () => {
    formatCurrencyInput(shippingCostInput);
    updateSummary();
  });
  additionalFeeInput?.addEventListener('input', updateSummary);
  additionalFeeInput?.addEventListener('blur', () => {
    formatCurrencyInput(additionalFeeInput);
    updateSummary();
  });
  additionalFeeLabelInput?.addEventListener('input', updateSummary);

  (async function initPage() {
    invoiceItemOptions = await fetchInvoiceItemOptions().catch(() => []);
    rowsContainer.querySelectorAll('.invoice-item-row').forEach((row) => {
      const itemSelect = row.querySelector('.invoice-item-select');
      if (itemSelect) {
        setInvoiceItemOptions(itemSelect, invoiceItemOptions);
      }
    });

    refreshInvoiceItemAvailability();
    syncInvoiceTypeUI();
    applyShippingModeUI();
    updateSummary();
  })();

  // --- preview modal & download flow ---
  const previewModalEl    = document.getElementById('modal-invoice-preview');
  const previewFrame      = document.getElementById('invoice-preview-frame');
  const downloadFrame     = document.getElementById('invoice-download-frame');
  const btnPreviewInvoice = document.getElementById('btn-preview-invoice');
  const btnModalDownload  = document.getElementById('btn-modal-download');
  const previewModal      = previewModalEl ? new tabler.Modal(previewModalEl) : null;

  let currentLogId = null;

  function validateInvoice() {
    if (!hasValidInvoiceItems()) {
      Swal.fire({ icon: 'warning', title: 'Please fill at least one valid invoice item.' });
      return false;
    }
    if (!billToValueInput?.value) {
      Swal.fire({ icon: 'warning', title: getInvoiceType() === 'customer' ? 'Please fill Bill To customer.' : 'Please select Bill To dealer.' });
      return false;
    }
    if (getShippingMode() === 'automatic') {
      const selectedAreaId = document.getElementById('shipping-area-id')?.value.trim() || '';
      const deliveryGroup = getShippingDeliveryGroup();
      const lat = document.getElementById('shipping-point-lat-hidden')?.value.trim() || '';
      const lng = document.getElementById('shipping-point-lng-hidden')?.value.trim() || '';
      const shippingCost = parseCurrency(shippingCostInput?.value);
      if (deliveryGroup === 'point_based') {
        if (!lat || !lng) {
          Swal.fire({ icon: 'warning', title: 'Please pin the instant delivery location first.' });
          return false;
        }
      } else {
        if (!selectedAreaId) {
          Swal.fire({ icon: 'warning', title: 'Please choose the destination area first.' });
          return false;
        }
      }
      if (shippingCost <= 0) {
        Swal.fire({ icon: 'warning', title: 'Please complete the online shipping calculation first.' });
        return false;
      }
    }
    if (!shipToValueInput?.value.trim()) {
      Swal.fire({ icon: 'warning', title: 'Please complete Ship To location.' });
      return false;
    }
    syncNotesHidden();
    syncClosingHidden();
    if (!footerNotesHeaderField?.value.trim()) {
      Swal.fire({ icon: 'warning', title: 'Notes header is required.' });
      footerNotesHeaderField?.focus();
      return false;
    }
    if (!footerClosingHeaderField?.value.trim()) {
      Swal.fire({ icon: 'warning', title: 'Closing message header is required.' });
      footerClosingHeaderField?.focus();
      return false;
    }
    if (!form.reportValidity()) return false;
    return true;
  }

  function prepareRates() {
    rowsContainer.querySelectorAll('input[name="rate[]"]').forEach((input) => {
      input.value = String(parseCurrency(input.value));
    });
    const dtype = discountTypeHidden?.value || 'flat';
    if (discountValueHidden) {
      discountValueHidden.value = dtype === 'percent'
        ? String(parseFloat(discountPercentInput?.value || '0') || 0)
        : String(parseCurrency(discountFlatInput?.value));
    }
    if (discountMaxHidden) {
      discountMaxHidden.value = dtype === 'percent'
        ? String(parseCurrency(discountMaxInput?.value))
        : '0';
    }
    if (shippingCostHiddenInput) {
      shippingCostHiddenInput.value = String(parseCurrency(shippingCostInput?.value));
    }
    if (additionalFeeHiddenInput) {
      additionalFeeHiddenInput.value = String(parseCurrency(additionalFeeInput?.value));
    }
    if (additionalFeeLabelHiddenInput) {
      additionalFeeLabelHiddenInput.value = getAdditionalFeeLabel();
    }
  }

  function restoreRates() {
    rowsContainer.querySelectorAll('input[name="rate[]"]').forEach(formatCurrencyInput);
    formatCurrencyInput(discountFlatInput);
    formatCurrencyInput(discountMaxInput);
    formatCurrencyInput(shippingCostInput);
    formatCurrencyInput(additionalFeeInput);
  }

  function openPreviewModal() {
    previewModal?.show();
  }

  btnPreviewInvoice?.addEventListener('click', () => {
    if (!validateInvoice()) return;
    prepareRates();

    form.action = '/invoice-generator/preview?nobar=1';
    form.target = 'invoice-preview-frame';
    form.submit();

    restoreRates();
    currentLogId = null;
    openPreviewModal();
  });

  previewFrame?.addEventListener('load', () => {
    try {
      const doc = previewFrame.contentDocument || previewFrame.contentWindow?.document;
      if (doc?.documentElement) {
        previewFrame.style.height = doc.documentElement.scrollHeight + 'px';
      }
    } catch (error) {
      console.warn('Unable to resize invoice preview frame:', error);
    }
  });

  previewModalEl?.addEventListener('shown.bs.modal', () => {
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
      backdrop.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;z-index:10400;';
    }
  });

  previewModalEl?.addEventListener('hidden.bs.modal', () => {
    if (previewFrame) {
      previewFrame.src = 'about:blank';
      previewFrame.style.height = '0';
    }
    currentLogId = null;
  });

  btnModalDownload?.addEventListener('click', () => {
    if (currentLogId && downloadFrame) {
      downloadFrame.src = `/invoice-log/preview?log_id=${currentLogId}&autodownload=1`;
      return;
    }

    if (previewFrame?.contentWindow) {
      previewFrame.contentWindow.postMessage({ type: 'downloadInvoicePdf' }, window.location.origin);
      return;
    }

    Swal.fire({
      icon: 'warning',
      title: 'Preview belum siap untuk di-download.',
    });
  });

  window.addEventListener('message', (event) => {
    if (event.data?.type === 'invoiceReady') {
      currentLogId = Number(event.data.logId) > 0 ? Number(event.data.logId) : null;

      if (event.data.persisted === false && event.data.persistError) {
        console.warn('Invoice log persistence failed:', event.data.persistError);
      }
    }
  });


  /* ── Shipping Address Chain + Courier Selection ── */
  (function initShippingAddress() {
    const countrySelect  = document.getElementById('shipping-country-select');
    const provinceSelect = document.getElementById('shipping-province-select');
    const citySelect     = document.getElementById('shipping-city-select');
    const districtSelect = document.getElementById('shipping-district-select');
    const villageSelect  = document.getElementById('shipping-subdistrict-select');
    const postalDisplay  = document.getElementById('shipping-postal-display');
    const areaStatus     = document.getElementById('shipping-area-status');
    const pointStatus    = document.getElementById('shipping-point-status');
    const areaIdHidden   = document.getElementById('shipping-area-id');
    const postalHidden   = document.getElementById('shipping-postal-code');
    const pointLatInput  = document.getElementById('shipping-point-lat');
    const pointLngInput  = document.getElementById('shipping-point-lng');
    const pointLatHidden = document.getElementById('shipping-point-lat-hidden');
    const pointLngHidden = document.getElementById('shipping-point-lng-hidden');
    const pointCheckRatesButton = document.getElementById('shipping-point-check-rates-btn');
    const methodsPanel   = document.getElementById('invoice-shipping-methods');
    const couriersBox    = document.getElementById('shipping-couriers-box');
    const optionsBox     = document.getElementById('shipping-options-box');
    const statusEl       = document.getElementById('inv-shipping-status');

    if (!countrySelect || !provinceSelect || !citySelect || !districtSelect || !villageSelect) return;

    let shippingOptions  = [];
    let selectedCourier  = '';
    let selectedService  = '';
    let lastHasValidItems = hasValidInvoiceItems();
    let lastItemSignature = getItemShippingSignature();

    registerCombobox(countrySelect, 'Indonesia');
    registerCombobox(provinceSelect, 'Select Province');
    registerCombobox(citySelect, 'Select City');
    registerCombobox(districtSelect, 'Select District');
    registerCombobox(villageSelect, 'Select Coverage Area');

    function setAreaStatus(msg, isError) {
      if (!areaStatus) return;
      areaStatus.textContent = msg || '';
      areaStatus.style.color = isError ? '#c0392b' : '#607485';
    }

    function setPointStatus(msg, isError) {
      if (!pointStatus) return;
      pointStatus.textContent = msg || '';
      pointStatus.style.color = isError ? '#c0392b' : '#607485';
    }

    function setShippingStatus(msg, isError) {
      if (!statusEl) return;
      statusEl.textContent = msg || '';
      statusEl.style.color = isError ? '#c0392b' : '#607485';
    }

    function triggerPointBasedQuote() {
      const selectedArea = getSelectedAreaPayload();
      if (getShippingMode() !== 'automatic' || selectedArea.delivery_group !== 'point_based') return;
      if (!selectedArea.lat || !selectedArea.lng) {
        setPointStatus('Pin the instant delivery location first.', true);
        return;
      }
      if (!hasValidInvoiceItems()) {
        setPointStatus('Add at least one valid invoice item before calculating shipping cost.', true);
        return;
      }
      setPointStatus('Pin location ready. Checking rates...', false);
      fetchShippingQuote(selectedArea);
    }

    function escHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function clearSelect(field, placeholder) {
      if (!field) return;
      field.innerHTML = '';
      const first = document.createElement('option');
      first.value = '';
      first.textContent = placeholder;
      field.appendChild(first);
      field.value = '';
      field.disabled = true;
      syncCombobox(field);
    }

    function setSelectOptions(field, rows, placeholder, selectedValue) {
      if (!field) return;
      field.innerHTML = '';
      const first = document.createElement('option');
      first.value = '';
      first.textContent = placeholder;
      field.appendChild(first);

      rows.forEach(function (row) {
        const option = document.createElement('option');
        option.value = String(row.id || '');
        option.textContent = String(row.name || '');
        if (row.zip_code) option.dataset.zipCode = String(row.zip_code);
        if (row.label) option.dataset.label = String(row.label);
        field.appendChild(option);
      });

      field.disabled = rows.length === 0;
      if (selectedValue) {
        field.value = selectedValue;
      }
      syncCombobox(field);
    }

    function resetShippingOptions() {
      shippingOptions = [];
      selectedCourier = '';
      selectedService = '';
      if (couriersBox) {
        couriersBox.innerHTML = '';
        couriersBox.hidden = false;
      }
      if (optionsBox) optionsBox.innerHTML = '';
      if (methodsPanel) methodsPanel.hidden = getShippingMode() !== 'automatic';
      setShippingStatus('', false);
      applyShippingFee(0);
      renderCouriers();
    }

    function getSelectedAreaPayload() {
      return {
        delivery_group: getShippingDeliveryGroup(),
        areaId: areaIdHidden?.value.trim() || '',
        postalCode: postalHidden?.value.trim() || '',
        lat: pointLatHidden?.value.trim() || '',
        lng: pointLngHidden?.value.trim() || ''
      };
    }

    function getCouriers() {
      if (getShippingDeliveryGroup() === 'point_based') {
        return [
          { code: 'gojek', name: 'Gojek' },
          { code: 'grab', name: 'Grab' },
          { code: 'lalamove', name: 'Lalamove' }
        ];
      }

      const map = new Map();
      shippingOptions.forEach(function (o) {
        if (!map.has(o.courier_code)) {
          map.set(o.courier_code, { code: o.courier_code, name: o.courier_name });
        }
      });
      return Array.from(map.values());
    }

    function getFilteredOptions() {
      if (!selectedCourier) return shippingOptions;
      const filtered = shippingOptions.filter(function (o) { return o.courier_code === selectedCourier; });
      return filtered.length ? filtered : shippingOptions;
    }

    function renderCouriers() {
      if (!couriersBox) return;
      couriersBox.innerHTML = '';
      const couriers = getCouriers();
      if (couriers.length <= 1) { couriersBox.hidden = true; return; }
      couriersBox.hidden = false;
      couriers.forEach(function (c) {
        const isAvailable = shippingOptions.some(function (o) { return o.courier_code === c.code; });
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'inv-courier-btn'
          + (c.code === selectedCourier ? ' is-active' : '')
          + (isAvailable ? '' : ' is-disabled');
        btn.setAttribute('aria-label', c.name);
        btn.disabled = !isAvailable;
        btn.innerHTML = '<img src="/assets/images/logos/' + escHtml(c.code) + '.svg" alt="' + escHtml(c.name) + '"><span>' + escHtml(c.name) + '</span>';
        const img = btn.querySelector('img');
        img?.addEventListener('error', function () {
          img.hidden = true;
        }, { once: true });
        btn.addEventListener('click', function () {
          if (!isAvailable) return;
          if (selectedCourier === c.code) return;
          selectedCourier = c.code;
          selectedService = '';
          renderCouriers();
          renderOptions();
        });
        couriersBox.appendChild(btn);
      });
    }

    function renderOptions() {
      if (!optionsBox) return;
      optionsBox.innerHTML = '';
      const opts = getFilteredOptions();
      if (!opts.length) {
        optionsBox.innerHTML = '<div class="inv-shipping-status">Tidak ada layanan tersedia untuk courier yang dipilih.</div>';
        return;
      }
      if (!selectedService) selectedService = opts[0].code;
      opts.forEach(function (o) {
        const label = document.createElement('label');
        label.className = 'inv-shipping-option' + (o.code === selectedService ? ' is-active' : '');
        label.innerHTML =
          '<input type="radio" name="inv_shipping_service" value="' + escHtml(o.code) + '"' +
          (o.code === selectedService ? ' checked' : '') + '>' +
          '<span class="inv-shipping-option-body">' +
            '<span class="inv-shipping-option-label">' +
              '<span>' + escHtml(o.label) + '</span>' +
              '<strong>' + formatIDR(o.fee) + '</strong>' +
            '</span>' +
            '<span class="inv-shipping-option-eta">' + escHtml(o.eta) + '</span>' +
          '</span>';
        label.addEventListener('click', function () {
          if (selectedService === o.code) return;
          selectedService = o.code;
          applyShippingFee(o.fee);
          renderOptions();
        });
        optionsBox.appendChild(label);
      });
      // Auto-apply first option if none selected yet
      const auto = opts.find(function (o) { return o.code === selectedService; }) || opts[0];
      if (auto) applyShippingFee(auto.fee);
    }

    function formatIDR(amount) {
      return 'IDR ' + Number(amount).toLocaleString('id-ID');
    }

    function applyShippingFee(fee) {
      if (!shippingCostInput) return;
      shippingCostInput.value = 'IDR ' + Number(fee).toLocaleString('id-ID');
      if (shippingCostHiddenInput) shippingCostHiddenInput.value = String(fee);
      updateSummary();
    }

    function buildShipToValue() {
      if (getShippingDeliveryGroup() === 'point_based') {
        return shipToValueInput?.value.trim() || '';
      }

      const parts = [
        getSelectedText(villageSelect),
        getSelectedText(districtSelect),
        getSelectedText(citySelect),
        getSelectedText(provinceSelect),
        getSelectedText(countrySelect),
        postalDisplay?.value.trim() || ''
      ].filter(Boolean);
      return parts.join(', ');
    }

    function clearSelectedArea(level) {
      if (level === 'country' || level === 'province') {
        clearSelect(citySelect, 'Select City');
        clearSelect(districtSelect, 'Select District');
        clearSelect(villageSelect, 'Select Coverage Area');
      }
      if (level === 'city') {
        clearSelect(districtSelect, 'Select District');
        clearSelect(villageSelect, 'Select Coverage Area');
      }
      if (level === 'district') {
        clearSelect(villageSelect, 'Select Coverage Area');
      }

      if (postalDisplay) postalDisplay.value = '';
      if (postalHidden) postalHidden.value = '';
      if (areaIdHidden) areaIdHidden.value = '';
      setAreaStatus('', false);
      resetShippingOptions();
      syncShipToValue();
    }

    function resetPointSelection() {
      if (pointLatInput) pointLatInput.value = '';
      if (pointLngInput) pointLngInput.value = '';
      if (pointLatHidden) pointLatHidden.value = '';
      if (pointLngHidden) pointLngHidden.value = '';
      setPointStatus('Click the map to pick an instant delivery point.', false);
      resetShippingOptions();
      syncShipToValue();
    }

    async function fetchAddressOptions(level, parentId) {
      const params = new URLSearchParams({ level: level });
      if (parentId) params.set('parent_id', parentId);

      const response = await fetch('/api/address-options?' + params.toString(), {
        headers: { Accept: 'application/json' }
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.error || 'Address options are unavailable.');
      }

      return Array.isArray(data.results) ? data.results : [];
    }

    async function loadProvinceOptions() {
      const results = await fetchAddressOptions('province');
      setSelectOptions(provinceSelect, results, 'Select Province');
    }

    async function fetchShippingQuote(destination) {
      if (getShippingMode() !== 'automatic') {
        return;
      }

      if (!hasValidInvoiceItems()) {
        resetShippingOptions();
        if (destination.delivery_group === 'point_based') {
          setPointStatus('Add at least one valid invoice item before calculating shipping cost.', true);
        } else {
          setAreaStatus('Add at least one valid invoice item before calculating shipping cost.', true);
        }
        return;
      }

      if (!methodsPanel) return;
      setShippingStatus('Menghitung ongkos kirim...', false);
      methodsPanel.hidden = false;
      if (couriersBox) couriersBox.innerHTML = '';
      if (optionsBox) optionsBox.innerHTML = '';

      try {
        const res = await fetch('/api/shipping-quote', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            delivery_group: destination.delivery_group,
            destination: {
              area_id: destination.areaId,
              postal_code: destination.postalCode,
              lat: destination.lat,
              lng: destination.lng
            },
            weight_grams: getShippingWeightGrams()
          }),
        });
        const data = await res.json();
        if (!res.ok || !data.options) {
          resetShippingOptions();
          setShippingStatus(data.error || 'Gagal mendapatkan tarif.', true);
          return;
        }
        shippingOptions = data.options;
        selectedCourier = shippingOptions.length ? shippingOptions[0].courier_code : '';
        selectedService = '';
        renderCouriers();
        renderOptions();
        setShippingStatus('', false);
      } catch (e) {
        resetShippingOptions();
        setShippingStatus('Layanan pengiriman tidak tersedia.', true);
      }
    }

    shippingModeSelect?.addEventListener('change', async function () {
      const selectedMode = shippingModeSelect.value === 'automatic' ? 'automatic' : 'manual';

      if (selectedMode === 'automatic') {
        const result = await Swal.fire({
          icon: 'warning',
          title: 'Calculate shipping online?',
          text: 'This action will use shipping calculation tokens.',
          showCancelButton: true,
          confirmButtonText: 'Continue',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#284b63',
          cancelButtonColor: '#94a6b5'
        });

        if (!result.isConfirmed) {
          shippingModeSelect.value = 'manual';
          syncCombobox(shippingModeSelect);
          applyShippingModeUI();
          return;
        }
      }

      if (selectedMode === 'manual') {
        resetShippingOptions();
        setAreaStatus('', false);
        setPointStatus('', false);
      } else {
        const selectedArea = getSelectedAreaPayload();
        if (selectedArea.delivery_group === 'point_based') {
          if (selectedArea.lat && selectedArea.lng) {
            setPointStatus('Pin location ready. Click Check Rates to search available couriers.', false);
          }
        } else if ((selectedArea.areaId || selectedArea.postalCode) && hasValidInvoiceItems()) {
          fetchShippingQuote(selectedArea);
        }
      }

      syncCombobox(shippingModeSelect);
      applyShippingModeUI();
    });

    shippingDeliveryGroupSelect?.addEventListener('change', function () {
      resetShippingOptions();
      setShippingStatus('', false);
      setAreaStatus('', false);
      setPointStatus('', false);
      applyShippingModeUI();
      syncShipToValue();

      const selectedArea = getSelectedAreaPayload();
      if (getShippingMode() !== 'automatic') {
        return;
      }

      if (selectedArea.delivery_group === 'point_based') {
        if (selectedArea.lat && selectedArea.lng) {
          setPointStatus('Pin location ready. Click Check Rates to search available couriers.', false);
        } else {
          setPointStatus('Klik peta untuk memilih titik instant delivery.', false);
        }
        return;
      }

      if ((selectedArea.areaId || selectedArea.postalCode) && hasValidInvoiceItems()) {
        fetchShippingQuote(selectedArea);
      }
    });

    countrySelect.addEventListener('change', async function () {
      clearSelectedArea('country');
      clearSelect(provinceSelect, 'Select Province');
      if (!countrySelect.value) return;

      try {
        await loadProvinceOptions();
      } catch (error) {
        setAreaStatus(error.message || 'Province list is unavailable.', true);
      }
    });

    provinceSelect.addEventListener('change', async function () {
      clearSelectedArea('province');
      if (!provinceSelect.value) return;

      try {
        const results = await fetchAddressOptions('city', provinceSelect.value);
        setSelectOptions(citySelect, results, 'Select City');
      } catch (error) {
        setAreaStatus(error.message || 'City list is unavailable.', true);
      }
    });

    citySelect.addEventListener('change', async function () {
      clearSelectedArea('city');
      if (!citySelect.value) return;

      try {
        const results = await fetchAddressOptions('district', citySelect.value);
        setSelectOptions(districtSelect, results, 'Select District');
      } catch (error) {
        setAreaStatus(error.message || 'District list is unavailable.', true);
      }
    });

    districtSelect.addEventListener('change', async function () {
      clearSelectedArea('district');
      if (!districtSelect.value) return;

      try {
        setAreaStatus('Loading coverage area...', false);
        const results = await fetchAddressOptions('subdistrict', districtSelect.value);
        setSelectOptions(villageSelect, results, 'Select Coverage Area');
        setAreaStatus(results.length ? 'Select the matching coverage area.' : 'Coverage area is unavailable for this district.', !results.length);
      } catch (error) {
        setAreaStatus(error.message || 'Coverage area list is unavailable.', true);
      }
    });

    villageSelect.addEventListener('change', function () {
      if (getShippingMode() !== 'automatic') {
        return;
      }

      resetShippingOptions();

      const option = getSelectedOption(villageSelect);
      const areaId = villageSelect.value || '';
      const postalCode = option?.dataset?.zipCode || '';

      if (areaIdHidden) areaIdHidden.value = areaId;
      if (postalHidden) postalHidden.value = postalCode;
      if (postalDisplay) postalDisplay.value = postalCode;
      syncShipToValue();

      if (!areaId) {
        setAreaStatus('', false);
        return;
      }

      if (!hasValidInvoiceItems()) {
        setAreaStatus('Add at least one valid invoice item before calculating shipping cost.', true);
        return;
      }

      setAreaStatus('Area dipilih: ' + (shipToValueInput?.value || ''), false);
      fetchShippingQuote(getSelectedAreaPayload());
    });

    function updatePointSelection(lat, lng, options) {
      const latValue = Number(lat);
      const lngValue = Number(lng);
      if (!Number.isFinite(latValue) || !Number.isFinite(lngValue)) {
        resetPointSelection();
        return;
      }

      const latFixed = latValue.toFixed(6);
      const lngFixed = lngValue.toFixed(6);
      if (pointLatInput) pointLatInput.value = latFixed;
      if (pointLngInput) pointLngInput.value = lngFixed;
      if (pointLatHidden) pointLatHidden.value = latFixed;
      if (pointLngHidden) pointLngHidden.value = lngFixed;
      setPointStatus(options?.message || `Pinpoint ready at ${latFixed}, ${lngFixed}.`, false);
      syncShipToValue();
      resetShippingOptions();
      if (getShippingMode() === 'automatic' && getShippingDeliveryGroup() === 'point_based') {
        setPointStatus('Pin location ready. Click Check Rates to search available couriers.', false);
      }
    }

    function bindPointCoordinateInputs() {
      [pointLatInput, pointLngInput].forEach(function (input) {
        input?.addEventListener('input', function () {
          const lat = parseFloat(pointLatInput?.value || '');
          const lng = parseFloat(pointLngInput?.value || '');
          if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            if (pointLatHidden) pointLatHidden.value = '';
            if (pointLngHidden) pointLngHidden.value = '';
            resetShippingOptions();
            setPointStatus('Complete both coordinates before checking rates.', true);
            syncShipToValue();
            return;
          }
          updatePointSelection(lat, lng, { message: 'Coordinates updated manually.' });
        });
      });
    }

    function initPointMap() {
      const mapElement = document.getElementById('shipping-point-map');
      if (!mapElement || typeof window.L === 'undefined') {
        setPointStatus('Map library is unavailable. Fill coordinates manually.', true);
        return;
      }

      const fallbackCenter = [-6.268912, 106.642294];
      const map = window.L.map(mapElement, {
        center: fallbackCenter,
        zoom: 11,
        zoomControl: true
      });

      window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      const marker = window.L.marker(fallbackCenter, { draggable: true }).addTo(map);

      function syncMarker(lat, lng, message) {
        marker.setLatLng([lat, lng]);
        map.panTo([lat, lng], { animate: true });
        updatePointSelection(lat, lng, { message: message });
      }

      map.on('click', function (event) {
        syncMarker(event.latlng.lat, event.latlng.lng, 'Pinpoint updated from map.');
      });

      marker.on('dragend', function () {
        const latlng = marker.getLatLng();
        updatePointSelection(latlng.lat, latlng.lng, { message: 'Pinpoint adjusted.' });
      });

      [pointLatInput, pointLngInput].forEach(function (input) {
        input?.addEventListener('change', function () {
          const lat = parseFloat(pointLatInput?.value || '');
          const lng = parseFloat(pointLngInput?.value || '');
          if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
          marker.setLatLng([lat, lng]);
          map.panTo([lat, lng], { animate: true });
        });
      });

      requestAnimationFrame(function () {
        map.invalidateSize();
      });
      updatePointSelection(fallbackCenter[0], fallbackCenter[1], { message: 'Default instant delivery point loaded.' });
      setPointStatus('Pin location ready. Click Check Rates to search available couriers.', false);
    }

    pointCheckRatesButton?.addEventListener('click', function () {
      triggerPointBasedQuote();
    });

    document.addEventListener('invoice-items-changed', function (event) {
      const hasValidItems = !!event.detail?.hasValidItems;
      const itemSignature = String(event.detail?.itemSignature || '');
      const stateUnchanged = hasValidItems === lastHasValidItems && itemSignature === lastItemSignature;
      if (stateUnchanged) return;
      lastHasValidItems = hasValidItems;
      lastItemSignature = itemSignature;

      const selectedArea = getSelectedAreaPayload();
      if (selectedArea.delivery_group === 'point_based') {
        if (!selectedArea.lat || !selectedArea.lng) return;
      } else if (!selectedArea.areaId && !selectedArea.postalCode) {
        return;
      }

      if (!hasValidItems) {
        resetShippingOptions();
        if (selectedArea.delivery_group === 'point_based') {
          setPointStatus('Add at least one valid invoice item before calculating shipping cost.', true);
        } else {
          setAreaStatus('Add at least one valid invoice item before calculating shipping cost.', true);
        }
        return;
      }

      if (getShippingMode() !== 'automatic') {
        return;
      }

      if (selectedArea.delivery_group === 'point_based') {
        resetShippingOptions();
        setPointStatus('Pin location ready. Click Check Rates to refresh instant delivery options.', false);
      } else {
        setAreaStatus('Area dipilih: ' + (shipToValueInput?.value || ''), false);
        fetchShippingQuote(selectedArea);
      }
    });

    if (countrySelect.value) {
      loadProvinceOptions().catch(function (error) {
        setAreaStatus(error.message || 'Province list is unavailable.', true);
      });
    }

    bindPointCoordinateInputs();
    initPointMap();
  })();

})();
