/* ── Toast helper ─────────────────────────────────────────── */
function showMsg(text, type) {
    const icons = {
        success: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
        error: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>`,
        info: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
        warn: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    };
    const msg = document.getElementById('message');
    msg.className = `show ${type}`;
    msg.innerHTML = (icons[type] || '') + `<span>${text}</span>`;
}
function hideMsg() {
    const msg = document.getElementById('message');
    msg.className = '';
    msg.innerHTML = '';
}

/* ── Tab switching ────────────────────────────────────────── */
function switchTab(tab) {
    document.getElementById('payment-method').value = tab;

    const cardBtn = document.getElementById('tab-card');
    const mobileBtn = document.getElementById('tab-mobile');
    const cardFlds = document.getElementById('card-fields');
    const mobFlds = document.getElementById('mobile-fields');

    if (tab === 'card') {
        cardBtn.className = 'tab-btn active';
        mobileBtn.className = 'tab-btn inactive';
        cardFlds.classList.remove('hidden');
        mobFlds.classList.add('hidden');
    } else {
        mobileBtn.className = 'tab-btn active';
        cardBtn.className = 'tab-btn inactive';
        mobFlds.classList.remove('hidden');
        cardFlds.classList.add('hidden');
    }
}

/* ── Network Detection ────────────────────────────────────── */
document.getElementById('phone_number').addEventListener('input', function (e) {
    let phone = e.target.value.replace(/\D/g, ''); // strip non-digits

    // if starts with 260, strip it to check the 09x prefix easily
    let checkPhone = phone;
    if (phone.startsWith('260') && phone.length > 3) {
        checkPhone = '0' + phone.substring(3);
    } else if (!phone.startsWith('0') && phone.length > 0) {
        checkPhone = '0' + phone;
    }

    const providerInput = document.getElementById('provider');
    const logoContainer = document.getElementById('network-logo-container');
    const logoImg = document.getElementById('network-logo');
    const nameDisplay = document.getElementById('network-name-display');

    let detectedProvider = '';
    let logoSrc = '';
    let networkName = '';

    const prefix = checkPhone.substring(0, 3);

    if (['097', '077', '057'].includes(prefix)) {
        detectedProvider = 'airtel';
        logoSrc = '/images/Airtel_logo-01.png';
        networkName = 'Airtel Money';
    } else if (['096', '076', '056'].includes(prefix)) {
        detectedProvider = 'mtn';
        logoSrc = '/images/mtn_logo.jpeg';
        networkName = 'MTN Money';
    } else if (['095', '075', '055'].includes(prefix)) {
        detectedProvider = 'zamtel';
        logoSrc = '/images/zamtel_logo.png';
        networkName = 'Zamtel Kwacha';
    }

    if (detectedProvider && phone.length >= 3) {
        providerInput.value = detectedProvider;
        logoImg.src = logoSrc;
        logoContainer.style.display = 'block';

    } else {
        providerInput.value = '';
        logoContainer.style.display = 'none';
        nameDisplay.textContent = phone.length >= 3 ? 'Unsupported or incomplete network prefix' : 'Enter phone number to detect network';
        nameDisplay.style.color = phone.length >= 3 ? 'var(--warn)' : 'var(--text-muted)';
    }
});

/* ── Status polling ──────────────────────────────────────── */
async function checkPaymentStatus(reference) {
    try {
        const response = await fetch(`/pay/status/${reference}`);
        const data = await response.json();

        if (data.payment.status === 'succeeded') return { status: 'succeeded' };
        if (data.payment.status === 'failed') return { status: 'failed' };
        if (data.payment.status === 'requires_action') return { status: 'requires_action', url: data.payment.metadata?.redirect_url };

        return { status: 'pending' };
    } catch (e) {
        console.error('Polling error', e);
        return { status: 'pending' };
    }
}

async function pollStatus(reference) {
    const maxAttempts = 60;
    let attempts = 0;
    const interval = setInterval(async () => {
        attempts++;
        const result = await checkPaymentStatus(reference);
        const status = result.status;

        if (status === 'succeeded') {
            clearInterval(interval);
            showMsg('Payment confirmed! Redirecting…', 'success');
            setTimeout(() => {
                window.location.href = document.querySelector('meta[name="callback-url"]').getAttribute('content') + "?reference=" + reference;
            }, 1000);
        } else if (status === 'requires_action' && result.url) {
            clearInterval(interval);
            showMsg('Redirecting to 3D Secure authorization...', 'info');
            setTimeout(() => {
                window.location.href = result.url;
            }, 1000);
        } else if (status === 'failed') {
            clearInterval(interval);
            showMsg('Payment failed or cancelled.', 'error');
            const btn = document.getElementById('pay-btn');
            btn.disabled = false;
            btn.classList.remove('loading');
            btn.querySelector('.btn-text-wrap').innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>Retry Payment`;
        } else if (attempts >= maxAttempts) {
            clearInterval(interval);
            showMsg('Payment timed out. Please check your phone.', 'warn');
            const btn = document.getElementById('pay-btn');
            btn.disabled = false;
            btn.classList.remove('loading');
            btn.querySelector('.btn-text-wrap').innerHTML = `
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                        </svg>Check Status / Retry`;
        }
    }, 2000);
}

/* ── Form submission ─────────────────────────────────────── */
document.getElementById('payment-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('pay-btn');
    const method = document.getElementById('payment-method').value;

    btn.disabled = true;
    btn.classList.add('loading');
    hideMsg();

    const url = method === 'card' ? '/pay/submit' : '/pay/submit-mobile-money';
    const reference = document.querySelector('meta[name="payment-reference"]').getAttribute('content');

    const payload = { payment_reference: reference };

    if (method === 'card') {
        payload.card_number = document.getElementById('card_number').value;
        payload.exp_month = document.getElementById('exp_month').value;
        payload.exp_year = document.getElementById('exp_year').value;
        payload.cvc = document.getElementById('cvc').value;
    } else {
        payload.phone_number = document.getElementById('phone_number').value;
        payload.provider = document.getElementById('provider').value;
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Idempotency-Key': crypto.randomUUID(),
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            if (data.status === 'pay-offline') {
                showMsg(data.message || 'Please authorise the payment on your phone.', 'info');
                pollStatus(reference);
            } else {
                showMsg('Payment successful! Redirecting…', 'success');
                setTimeout(() => {
                    window.location.href = document.querySelector('meta[name="callback-url"]').getAttribute('content') + "?reference=" + reference;
                }, 1000);
            }
        } else {
            throw new Error(data.error || 'Payment failed');
        }
    } catch (error) {
        showMsg(error.message, 'error');
        btn.disabled = false;
        btn.classList.remove('loading');
    }
});
