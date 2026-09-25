document.addEventListener('click', e => {
  const navToggle = e.target.closest('[data-nav-toggle]');
  if (navToggle) document.querySelector('[data-nav]')?.classList.toggle('open');

  const confirmButton = e.target.closest('[data-confirm]');
  if (confirmButton && !confirm(confirmButton.dataset.confirm || 'Are you sure?')) e.preventDefault();

  const editButton = e.target.closest('[data-address-edit]');
  if (editButton) {
    const panel = document.getElementById(editButton.dataset.addressEdit);
    if (panel) panel.hidden = !panel.hidden;
  }

  const profileEditButton = e.target.closest('[data-profile-edit-toggle]');
  if (profileEditButton) {
    const view = document.querySelector('[data-profile-view]');
    const edit = document.querySelector('[data-profile-edit]');
    if (view && edit) {
      view.hidden = true;
      edit.hidden = false;
      profileEditButton.hidden = true;
      profileEditButton.setAttribute('aria-expanded', 'true');
      edit.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }
  }

  const profileCancelButton = e.target.closest('[data-profile-edit-cancel]');
  if (profileCancelButton) {
    const view = document.querySelector('[data-profile-view]');
    const edit = document.querySelector('[data-profile-edit]');
    const toggle = document.querySelector('[data-profile-edit-toggle]');
    if (view && edit) {
      edit.hidden = true;
      view.hidden = false;
      if (toggle) {
        toggle.hidden = false;
        toggle.setAttribute('aria-expanded', 'false');
      }
      document.getElementById('account')?.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
  }

  const passwordButton = e.target.closest('[data-toggle-password]');
  if (passwordButton) {
    const wrapper = passwordButton.closest('.password-field');
    const input = wrapper?.querySelector('[data-password-input]');
    if (input) {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      passwordButton.textContent = showing ? 'Show' : 'Hide';
    }
  }
});

const imageInput = document.querySelector('[data-image-input]');
imageInput?.addEventListener('change', () => {
  const file = imageInput.files?.[0];
  if (!file) return;
  const url = URL.createObjectURL(file);
  const selector = imageInput.dataset.previewTarget;
  if (selector) {
    const preview = document.querySelector(selector);
    if (preview) preview.src = url;
  }
  const second = document.querySelector('[data-secondary-preview]');
  if (second) second.src = url;
});

const checkoutForm = document.querySelector('[data-checkout-form]');
if (checkoutForm) {
  const radios = [...checkoutForm.querySelectorAll('[data-payment-method]')];
  const mobileBox = checkoutForm.querySelector('[data-mobile-payment]');
  const merchantNumber = checkoutForm.querySelector('[data-pay-number]');
  const payTotal = checkoutForm.querySelector('[data-pay-total]');
  const submitButton = checkoutForm.querySelector('[data-checkout-submit]');
  const couponInput = checkoutForm.querySelector('[data-coupon-input]');
  const couponButton = checkoutForm.querySelector('[data-coupon-apply]');
  const quoteMessage = checkoutForm.querySelector('[data-quote-message]');
  const quotedTotal = checkoutForm.querySelector('[data-quoted-total]');
  const quotedCoupon = checkoutForm.querySelector('[data-quoted-coupon]');
  const summarySubtotal = document.querySelector('[data-summary-subtotal]');
  const summaryDelivery = document.querySelector('[data-summary-delivery]');
  const summaryDiscount = document.querySelector('[data-summary-discount]');
  const summaryDiscountRow = document.querySelector('[data-summary-discount-row]');
  const summaryTotal = document.querySelector('[data-summary-total]');
  let quoteDirty = false;

  const activePayment = () => radios.find(r => r.checked);

  function syncPayment() {
    const selected = activePayment();
    if (!selected) return;
    document.querySelectorAll('[data-payment-option]').forEach(label => {
      const radio = label.querySelector('[data-payment-method]');
      label.classList.toggle('selected', !!radio?.checked);
    });

    const mobile = ['bkash', 'nagad'].includes(selected.value);
    if (mobileBox) {
      mobileBox.hidden = !mobile;
      mobileBox.querySelectorAll('input').forEach(input => input.required = mobile);
    }
    if (merchantNumber) merchantNumber.textContent = selected.dataset.number || '';

    if (submitButton) {
      submitButton.textContent = selected.value === 'cod'
        ? 'Place Cash on Delivery order'
        : selected.value === 'sslcommerz'
          ? 'Continue to secure online payment'
          : `Submit ${selected.value === 'bkash' ? 'bKash' : 'Nagad'} payment for verification`;
    }
  }

  radios.forEach(radio => radio.addEventListener('change', syncPayment));
  syncPayment();

  checkoutForm.querySelector('[data-copy-payment]')?.addEventListener('click', async e => {
    const number = merchantNumber?.textContent?.trim();
    if (!number) return;
    try {
      await navigator.clipboard.writeText(number);
      const button = e.currentTarget;
      const old = button.textContent;
      button.textContent = 'Copied';
      setTimeout(() => button.textContent = old, 1200);
    } catch {
      alert('Merchant number: ' + number);
    }
  });

  couponInput?.addEventListener('input', () => {
    quoteDirty = true;
    quoteMessage?.classList.remove('success', 'error');
    if (quoteMessage) quoteMessage.textContent = 'Coupon changed — click Apply coupon to refresh the payable amount.';
  });

  couponButton?.addEventListener('click', async () => {
    const oldText = couponButton.textContent;
    couponButton.disabled = true;
    couponButton.textContent = 'Checking…';
    try {
      const formData = new FormData(checkoutForm);
      const response = await fetch(checkoutForm.dataset.quoteUrl, {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const data = await response.json();

      if (data.formatted) {
        if (summarySubtotal) summarySubtotal.textContent = data.formatted.subtotal;
        if (summaryDelivery) summaryDelivery.textContent = data.formatted.delivery;
        if (summaryDiscount) summaryDiscount.textContent = data.formatted.discount;
        if (summaryTotal) summaryTotal.textContent = data.formatted.total;
        if (payTotal) payTotal.textContent = data.formatted.total;
        if (summaryDiscountRow) summaryDiscountRow.hidden = Number(data.discount || 0) <= 0;
      }

      if (data.ok) {
        quoteDirty = false;
        if (quotedTotal) quotedTotal.value = Number(data.total).toFixed(2);
        if (quotedCoupon) quotedCoupon.value = (data.coupon_code || '').toUpperCase();
        if (couponInput) couponInput.value = data.coupon_code || couponInput.value.trim().toUpperCase();
        quoteMessage?.classList.remove('error');
        quoteMessage?.classList.add('success');
      } else {
        quoteDirty = true;
        if (quotedCoupon) quotedCoupon.value = '';
        quoteMessage?.classList.remove('success');
        quoteMessage?.classList.add('error');
      }
      if (quoteMessage) quoteMessage.textContent = data.message || (data.ok ? 'Total updated.' : 'Coupon could not be applied.');
    } catch {
      quoteDirty = true;
      quoteMessage?.classList.remove('success');
      quoteMessage?.classList.add('error');
      if (quoteMessage) quoteMessage.textContent = 'Could not refresh the total. Please try again.';
    } finally {
      couponButton.disabled = false;
      couponButton.textContent = oldText;
    }
  });

  checkoutForm.addEventListener('submit', e => {
    const method = activePayment()?.value || 'cod';
    const typedCoupon = (couponInput?.value || '').trim().toUpperCase();
    const quotedCode = (quotedCoupon?.value || '').trim().toUpperCase();
    if (['bkash', 'nagad'].includes(method) && (quoteDirty || typedCoupon !== quotedCode)) {
      e.preventDefault();
      alert('Please click “Apply coupon” first so the exact mobile-payment amount is confirmed. If you are not using a coupon, clear the coupon field and click Apply coupon once.');
      couponButton?.focus();
    }
  });
}
