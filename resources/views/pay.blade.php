<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment – Kantemba Gateway</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="payment-reference" content="{{ $payment->reference }}">
    <meta name="callback-url" content="{!! request('callback_url') ?? 'http://localhost:8001/api/payment/callback' !!}">

    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link href="{{ asset('css/pay.css') }}" rel="stylesheet">
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

            <p class="header-title">Feltech Pay</p>
            <p class="header-order">Order #{{ $payment->metadata['order_id'] ?? 'N/A' }}</p>

            <div class="amount-pill">
                <span class="amount-currency">{{ $payment->currency }}</span>
                <span class="amount-value">{{ number_format((float) $payment->amount, 2) }}</span>
            </div>
        </div>

        <!-- ─── Body ──────────────────────────────────────── -->
        <div class="card-body">

            <!-- Payment method tabs -->
            <div class="tab-rail">
                <button id="tab-card" class="tab-btn inactive disabled-tab" type="button" title="Card payments are coming soon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                        <line x1="1" y1="10" x2="23" y2="10"/>
                    </svg>
                    Credit Card
                    <span class="soon-badge">Soon</span>
                </button>
                <button id="tab-mobile" class="tab-btn active" onclick="switchTab('mobile')" type="button">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                        <line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                    Mobile Money
                </button>
            </div>

            <form id="payment-form">
                <input type="hidden" id="payment-method" value="mobile">

                <!-- ── Card fields ──────────────────────────── -->
                <div id="card-fields" class="hidden">
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
                <div id="mobile-fields">
                    <div class="field-group field-input-icon" style="margin-bottom: 8px;">
                        <label class="field-label">Phone Number</label>
                        <input type="text" id="phone_number" class="field-input"
                               placeholder="e.g. 0970000000" autocomplete="tel">
                        
                    </div>
                    <span class="icon-end" id="network-logo-container" style="display: none; ">
                            <img id="network-logo" src="" alt="Network Logo" style="height: 40px; border-radius: 4px; object-fit: contain;">
                        </span>
                    
                    <div id="network-name-display" style="font-size: 11px; color: var(--text-muted); min-height: 16px; margin-bottom: 20px; padding-left: 4px;">
                        Enter phone number 
                    </div>

                    <input type="hidden" id="provider" value="">
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
                        Pay {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}
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
        <p class="powered">Powered by <span>Feltech Payment Gateway</span> www.feltech.org</p>

    </div>

    <script src="{{ asset('js/pay.js') }}"></script>
</body>

</html>