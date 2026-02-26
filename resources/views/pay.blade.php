<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment – Kantemba Gateway</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-deep:    #07071a;
            --card-bg:    rgba(15, 15, 38, 0.85);
            --card-border: rgba(120, 100, 255, 0.25);
            --card-glow:   rgba(100, 80, 255, 0.12);
            --input-bg:   rgba(255,255,255,0.05);
            --input-border: rgba(255,255,255,0.12);
            --input-focus: rgba(139,92,246,0.7);
            --accent-1:   #8b5cf6;
            --accent-2:   #6366f1;
            --accent-grd: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            --text-primary: #f0f0ff;
            --text-muted:   rgba(200,200,235,0.55);
            --text-sub:     rgba(200,200,235,0.75);
            --success: #22c55e;
            --error:   #ef4444;
            --warn:    #f59e0b;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background-color: var(--bg-deep);
            overflow: hidden;
        }

        /* ── Animated gradient background ────────────────────────── */
        .bg-mesh {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 20%, rgba(99,102,241,0.35) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 80%, rgba(139,92,246,0.3) 0%, transparent 55%),
                radial-gradient(ellipse 50% 70% at 50% 50%, rgba(30,27,75,0.8) 0%, transparent 80%),
                #07071a;
            animation: meshShift 12s ease-in-out infinite alternate;
        }
        @keyframes meshShift {
            0%  { filter: hue-rotate(0deg); }
            100%{ filter: hue-rotate(20deg); }
        }

        /* Floating orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.18;
            pointer-events: none;
            animation: orbFloat 18s ease-in-out infinite;
            z-index: 0;
        }
        .orb-1 { width: 520px; height: 520px; background: #8b5cf6; top: -160px; left: -160px; animation-delay: 0s; }
        .orb-2 { width: 400px; height: 400px; background: #6366f1; bottom: -100px; right: -100px; animation-delay: -6s; }
        .orb-3 { width: 300px; height: 300px; background: #a78bfa; top: 40%; left: 60%; animation-delay: -12s; }
        @keyframes orbFloat {
            0%,100%{ transform: translate(0,0) scale(1); }
            33%    { transform: translate(30px,-30px) scale(1.05); }
            66%    { transform: translate(-20px,20px) scale(0.97); }
        }

        /* ── Card ─────────────────────────────────────────────────── */
        .card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.04) inset,
                0 40px 80px rgba(0,0,0,0.55),
                0 0 60px var(--card-glow);
            overflow: hidden;
            animation: cardIn 0.6s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes cardIn {
            from { opacity:0; transform: translateY(28px) scale(0.97); }
            to   { opacity:1; transform: translateY(0)    scale(1); }
        }

        /* ── Card header ──────────────────────────────────────────── */
        .card-header {
            padding: 28px 28px 24px;
            background: linear-gradient(135deg, rgba(99,102,241,0.22) 0%, rgba(139,92,246,0.18) 100%);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        /* Lock badge */
        .lock-badge {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg,#8b5cf6,#6366f1);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 0 6px rgba(139,92,246,0.15);
        }
        .lock-badge svg { color: #fff; }

        .header-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--text-primary);
        }
        .header-order {
            font-size: 12px;
            color: var(--text-muted);
            letter-spacing: 0.06em;
        }

        /* Amount pill */
        .amount-pill {
            margin-top: 4px;
            display: inline-flex;
            align-items: baseline;
            gap: 4px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 99px;
            padding: 8px 20px;
        }
        .amount-currency {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-sub);
        }
        .amount-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        /* ── Card body ────────────────────────────────────────────── */
        .card-body  { padding: 24px 28px; }
        .card-footer{ padding: 16px 28px 24px; }

        /* ── Pill tabs ─────────────────────────────────────────────── */
        .tab-rail {
            display: flex;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 4px;
            gap: 4px;
            margin-bottom: 24px;
            position: relative;
        }
        .tab-btn {
            flex: 1;
            padding: 9px 0;
            border: none;
            border-radius: 9px;
            cursor: pointer;
            font-family: 'Inter',sans-serif;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.02em;
            transition: color 0.25s, background 0.25s;
            display: flex; align-items: center; justify-content: center; gap: 7px;
        }
        .tab-btn.active {
            background: var(--accent-grd);
            color: #fff;
            box-shadow: 0 4px 16px rgba(99,102,241,0.35);
        }
        .tab-btn.inactive {
            background: transparent;
            color: var(--text-muted);
        }
        .tab-btn.inactive:hover { color: var(--text-sub); }

        /* ── Floating-label input ─────────────────────────────────── */
        .field-group { position: relative; margin-bottom: 16px; }
        .field-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 7px;
        }
        .field-input {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            padding: 12px 14px;
            font-family: 'Inter',sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            -webkit-appearance: none;
        }
        .field-input::placeholder { color: rgba(200,200,235,0.3); }
        .field-input:focus {
            border-color: var(--input-focus);
            background: rgba(139,92,246,0.08);
            box-shadow: 0 0 0 3px rgba(139,92,246,0.2);
        }
        .field-input-icon {
            position: relative;
        }
        .field-input-icon .field-input { padding-right: 42px; }
        .field-input-icon .icon-end {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        /* select */
        select.field-input {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238b5cf6' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 36px;
        }
        select.field-input option { background: #1a1a3e; }

        /* two-col grid */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* divider */
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 4px 0 20px;
        }
        .divider::before,.divider::after {
            content:''; flex:1; height:1px;
            background: rgba(255,255,255,0.08);
        }
        .divider span {
            font-size: 11px; color: var(--text-muted);
            letter-spacing: 0.07em; text-transform: uppercase;
        }

        /* ── Pay button ───────────────────────────────────────────── */
        .pay-btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-family: 'Inter',sans-serif;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.03em;
            color: #fff;
            position: relative;
            overflow: hidden;
            background: var(--accent-grd);
            box-shadow: 0 8px 28px rgba(99,102,241,0.4);
            transition: transform 0.18s, box-shadow 0.18s, opacity 0.18s;
            display: flex; align-items: center; justify-content: center; gap: 9px;
        }
        .pay-btn::after {
            content:'';
            position:absolute;
            inset:0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.15) 50%, transparent 100%);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }
        .pay-btn:hover:not(:disabled)::after { transform: translateX(100%); }
        .pay-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 12px 36px rgba(99,102,241,0.5);
        }
        .pay-btn:active:not(:disabled) { transform: translateY(0); }
        .pay-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        /* spinner */
        .spinner {
            width: 18px; height: 18px;
            border: 2.5px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
            display: none;
        }
        .pay-btn.loading .spinner { display: block; }
        .pay-btn.loading .btn-text-wrap { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Toast / status message ──────────────────────────────── */
        #message {
            display: none;
            margin-top: 14px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            align-items: center;
            gap: 9px;
            animation: toastIn 0.3s ease both;
        }
        #message.show { display: flex; }
        @keyframes toastIn {
            from { opacity:0; transform:translateY(6px); }
            to   { opacity:1; transform:translateY(0); }
        }
        #message.success { background:rgba(34,197,94,0.12); border:1px solid rgba(34,197,94,0.3); color:#4ade80; }
        #message.error   { background:rgba(239,68,68,0.12);  border:1px solid rgba(239,68,68,0.3);  color:#f87171; }
        #message.info    { background:rgba(99,102,241,0.12); border:1px solid rgba(99,102,241,0.3); color:#a5b4fc; }
        #message.warn    { background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); color:#fbbf24; }

        /* ── Footer / trust badges ────────────────────────────────── */
        .trust-bar {
            padding: 16px 28px;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            flex-wrap: wrap;
        }
        .trust-badge {
            display: flex; align-items: center; gap: 5px;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            text-transform: uppercase;
        }
        .trust-badge svg { opacity: 0.6; }

        .powered {
            text-align: center;
            font-size: 11px;
            color: rgba(200,200,235,0.3);
            padding: 0 28px 16px;
            letter-spacing: 0.05em;
        }
        .powered span { color: rgba(139,92,246,0.7); font-weight: 600; }

        /* mobile fields hidden helper */
        .hidden { display: none !important; }
    </style>
</head>

<body>
    <div class="bg-mesh"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="card">

        <!-- ─── Header ───────────────────────────────────── -->
        <div class="card-header">
            <div class="lock-badge">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>

            <p class="header-title">Secure Checkout</p>
            <p class="header-order">Order #{{ $payment->metadata['order_id'] ?? 'N/A' }}</p>

            <div class="amount-pill">
                <span class="amount-currency">{{ $payment->currency }}</span>
                <span class="amount-value">{{ number_format($payment->amount, 2) }}</span>
            </div>
        </div>

        <!-- ─── Body ──────────────────────────────────────── -->
        <div class="card-body">

            <!-- Payment method tabs -->
            <div class="tab-rail">
                <button id="tab-card" class="tab-btn active" onclick="switchTab('card')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Credit Card
                </button>
                <button id="tab-mobile" class="tab-btn inactive" onclick="switchTab('mobile')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                        <line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                    Mobile Money
                </button>
            </div>

            <form id="payment-form">
                <input type="hidden" id="payment-method" value="card">

                <!-- ── Card fields ──────────────────────────── -->
                <div id="card-fields">
                    <div class="field-group field-input-icon">
                        <label class="field-label">Card Number</label>
                        <input type="text" id="card_number" class="field-input"
                               placeholder="4242 4242 4242 4242" value="4242424242424242"
                               maxlength="19" autocomplete="cc-number">
                        <span class="icon-end">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="1" y="4" width="22" height="16" rx="2"/>
                                <line x1="1" y1="10" x2="23" y2="10"/>
                            </svg>
                        </span>
                    </div>

                    <div class="grid-2">
                        <div>
                            <div class="field-group">
                                <label class="field-label">Month</label>
                                <input type="text" id="exp_month" class="field-input"
                                       placeholder="MM" value="12" maxlength="2" autocomplete="cc-exp-month">
                            </div>
                        </div>
                        <div>
                            <div class="field-group">
                                <label class="field-label">Year</label>
                                <input type="text" id="exp_year" class="field-input"
                                       placeholder="YY" value="30" maxlength="2" autocomplete="cc-exp-year">
                            </div>
                        </div>
                    </div>

                    <div class="field-group field-input-icon">
                        <label class="field-label">CVC</label>
                        <input type="text" id="cvc" class="field-input"
                               placeholder="···" value="123" maxlength="4" autocomplete="cc-csc">
                        <span class="icon-end">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                        </span>
                    </div>
                </div>

                <!-- ── Mobile Money fields ──────────────────── -->
                <div id="mobile-fields" class="hidden">
                    <div class="field-group field-input-icon">
                        <label class="field-label">Phone Number</label>
                        <input type="text" id="phone_number" class="field-input"
                               placeholder="e.g. 260970000000" autocomplete="tel">
                        <span class="icon-end">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                                <line x1="12" y1="18" x2="12.01" y2="18"/>
                            </svg>
                        </span>
                    </div>

                    <div class="field-group">
                        <label class="field-label">Mobile Provider</label>
                        <select id="provider" class="field-input">
                            <option value="mtn">MTN Money</option>
                            <option value="airtel">Airtel Money</option>
                            <option value="zamtel">Zamtel Kwacha</option>
                        </select>
                    </div>
                </div>

                <!-- ── Submit ───────────────────────────────── -->
                <button type="submit" id="pay-btn" class="pay-btn">
                    <div class="spinner"></div>
                    <span class="btn-text-wrap" style="display:flex;align-items:center;gap:8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        Pay {{ $payment->currency }} {{ number_format($payment->amount, 2) }}
                    </span>
                </button>

                <!-- Status toast -->
                <div id="message"></div>
            </form>
        </div>

        <!-- ─── Trust bar ─────────────────────────────────── -->
        <div class="trust-bar">
            <span class="trust-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                SSL Encrypted
            </span>
            <span class="trust-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                PCI DSS Compliant
            </span>
            <span class="trust-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Secure Checkout
            </span>
        </div>

        <!-- Powered by -->
        <p class="powered">Powered by <span>Kantemba Gateway</span></p>

    </div>

    <script>
        /* ── Toast helper ─────────────────────────────────────────── */
        function showMsg(text, type) {
            const icons = {
                success: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
                error:   `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>`,
                info:    `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
                warn:    `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
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

            const cardBtn   = document.getElementById('tab-card');
            const mobileBtn = document.getElementById('tab-mobile');
            const cardFlds  = document.getElementById('card-fields');
            const mobFlds   = document.getElementById('mobile-fields');

            if (tab === 'card') {
                cardBtn.className   = 'tab-btn active';
                mobileBtn.className = 'tab-btn inactive';
                cardFlds.classList.remove('hidden');
                mobFlds.classList.add('hidden');
            } else {
                mobileBtn.className = 'tab-btn active';
                cardBtn.className   = 'tab-btn inactive';
                mobFlds.classList.remove('hidden');
                cardFlds.classList.add('hidden');
            }
        }

        /* ── Status polling ──────────────────────────────────────── */
        async function checkPaymentStatus(reference) {
            try {
                const response = await fetch(`/pay/status/${reference}`);
                const data = await response.json();
                if (data.payment.status === 'succeeded') return 'succeeded';
                if (data.payment.status === 'failed')    return 'failed';
                return 'pending';
            } catch (e) {
                console.error('Polling error', e);
                return 'pending';
            }
        }

        async function pollStatus(reference) {
            const maxAttempts = 60;
            let attempts = 0;
            const interval = setInterval(async () => {
                attempts++;
                const status = await checkPaymentStatus(reference);
                if (status === 'succeeded') {
                    clearInterval(interval);
                    showMsg('Payment confirmed! Redirecting…', 'success');
                    setTimeout(() => {
                        window.location.href = "{{ request('callback_url') ?? 'http://localhost:8001/api/payment/callback' }}?reference=" + reference;
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
            const btn    = document.getElementById('pay-btn');
            const method = document.getElementById('payment-method').value;

            btn.disabled = true;
            btn.classList.add('loading');
            hideMsg();

            const url = method === 'card' ? '/pay/submit' : '/pay/submit-mobile-money';
            const reference = '{{ $payment->reference }}';

            const payload = { payment_reference: reference };

            if (method === 'card') {
                payload.card_number = document.getElementById('card_number').value;
                payload.exp_month   = document.getElementById('exp_month').value;
                payload.exp_year    = document.getElementById('exp_year').value;
                payload.cvc         = document.getElementById('cvc').value;
            } else {
                payload.phone_number = document.getElementById('phone_number').value;
                payload.provider     = document.getElementById('provider').value;
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
                            window.location.href = "{{ request('callback_url') ?? 'http://localhost:8001/api/payment/callback' }}?reference=" + reference;
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
    </script>
</body>

</html>