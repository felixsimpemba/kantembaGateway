<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden">
        <div class="bg-indigo-600 p-6 text-white text-center">
            <h1 class="text-xl font-bold uppercase tracking-widest">Secure Checkout</h1>
            <p class="text-indigo-100 text-sm mt-1">Order #{{ $payment->metadata['order_id'] ?? 'N/A' }}</p>
        </div>

        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <span class="text-gray-500 text-sm">Amount to pay</span>
                <span class="text-2xl font-bold text-gray-800">{{ $payment->currency }}
                    {{ number_format($payment->amount, 2) }}</span>
            </div>

            <!-- Payment Method Tabs -->
            <div class="flex border-b border-gray-200 mb-6">
                <button id="tab-card" onclick="switchTab('card')"
                    class="flex-1 py-4 px-1 text-center border-b-2 border-indigo-500 font-medium text-indigo-600 focus:outline-none">
                    Credit Card
                </button>
                <button id="tab-mobile" onclick="switchTab('mobile')"
                    class="flex-1 py-4 px-1 text-center border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none">
                    Mobile Money
                </button>
            </div>

            <form id="payment-form" class="space-y-4">
                <input type="hidden" id="payment-method" value="card">

                <!-- Card Fields -->
                <div id="card-fields">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Card
                            Number</label>
                        <input type="text" id="card_number"
                            class="w-full border border-gray-300 rounded p-3 focus:outline-none focus:border-indigo-500 transition-colors"
                            placeholder="4242 4242 4242 4242" value="4242424242424242">
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Expiry
                                Date</label>
                            <div class="flex gap-2">
                                <input type="text" id="exp_month"
                                    class="w-full border border-gray-300 rounded p-3 text-center focus:outline-none focus:border-indigo-500"
                                    placeholder="MM" value="12">
                                <input type="text" id="exp_year"
                                    class="w-full border border-gray-300 rounded p-3 text-center focus:outline-none focus:border-indigo-500"
                                    placeholder="YY" value="30">
                            </div>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">CVC</label>
                            <input type="text" id="cvc"
                                class="w-full border border-gray-300 rounded p-3 text-center focus:outline-none focus:border-indigo-500"
                                placeholder="123" value="123">
                        </div>
                    </div>
                </div>

                <!-- Mobile Money Fields -->
                <div id="mobile-fields" class="hidden">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Phone
                            Number</label>
                        <input type="text" id="phone_number"
                            class="w-full border border-gray-300 rounded p-3 focus:outline-none focus:border-indigo-500 transition-colors"
                            placeholder="e.g. 260970000000">
                    </div>

                    <div class="mt-4">
                        <label
                            class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Provider</label>
                        <select id="provider"
                            class="w-full border border-gray-300 rounded p-3 focus:outline-none focus:border-indigo-500 bg-white">
                            <option value="mtn">MTN Money</option>
                            <option value="airtel">Airtel Money</option>
                            <option value="zamtel">Zamtel Kwacha</option>
                        </select>
                    </div>
                </div>

                <button type="submit" id="pay-btn"
                    class="w-full bg-indigo-600 text-white font-bold py-4 rounded hover:bg-indigo-700 transition-colors mt-8">
                    Pay {{ $payment->currency }} {{ number_format($payment->amount, 2) }}
                </button>
            </form>

            <div id="message" class="hidden mt-4 text-center text-sm font-medium"></div>
        </div>
        <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-400">Powered by Kantemba Gateway</p>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.getElementById('payment-method').value = tab;

            if (tab === 'card') {
                document.getElementById('card-fields').classList.remove('hidden');
                document.getElementById('mobile-fields').classList.add('hidden');

                document.getElementById('tab-card').classList.add('border-indigo-500', 'text-indigo-600');
                document.getElementById('tab-card').classList.remove('border-transparent', 'text-gray-500');

                document.getElementById('tab-mobile').classList.add('border-transparent', 'text-gray-500');
                document.getElementById('tab-mobile').classList.remove('border-indigo-500', 'text-indigo-600');
            } else {
                document.getElementById('card-fields').classList.add('hidden');
                document.getElementById('mobile-fields').classList.remove('hidden');

                document.getElementById('tab-mobile').classList.add('border-indigo-500', 'text-indigo-600');
                document.getElementById('tab-mobile').classList.remove('border-transparent', 'text-gray-500');

                document.getElementById('tab-card').classList.add('border-transparent', 'text-gray-500');
                document.getElementById('tab-card').classList.remove('border-indigo-500', 'text-indigo-600');
            }
        }

        async function checkPaymentStatus(reference) {
            try {
                const response = await fetch(`/pay/status/${reference}`);
                const data = await response.json();

                if (data.payment.status === 'succeeded') {
                    return 'succeeded';
                } else if (data.payment.status === 'failed') {
                    return 'failed';
                }
                return 'pending';
            } catch (e) {
                console.error('Polling error', e);
                return 'pending';
            }
        }

        async function pollStatus(reference) {
            const msg = document.getElementById('message');
            const maxAttempts = 60; // 2 minutes (assuming 2s interval)
            let attempts = 0;

            const interval = setInterval(async () => {
                attempts++;
                const status = await checkPaymentStatus(reference);

                if (status === 'succeeded') {
                    clearInterval(interval);
                    msg.textContent = 'Payment Confirmation Received! Redirecting...';
                    msg.classList.remove('text-blue-600');
                    msg.classList.add('text-green-500');
                    setTimeout(() => {
                        window.location.href = "{{ request('callback_url') ?? 'http://localhost:8001/api/payment/callback' }}?reference=" + reference;
                    }, 1000);
                } else if (status === 'failed') {
                    clearInterval(interval);
                    msg.textContent = 'Payment Failed or Cancelled.';
                    msg.classList.remove('text-blue-600');
                    msg.classList.add('text-red-500');
                    document.getElementById('pay-btn').disabled = false;
                    document.getElementById('pay-btn').innerHTML = "Retry Payment";
                } else if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    msg.textContent = 'Payment timed out. Please check your phone.';
                    msg.classList.remove('text-blue-600');
                    msg.classList.add('text-yellow-600');
                    document.getElementById('pay-btn').disabled = false;
                    document.getElementById('pay-btn').innerHTML = "Check Status / Retry";
                }
            }, 2000);
        }

        document.getElementById('payment-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('pay-btn');
            const msg = document.getElementById('message');
            const method = document.getElementById('payment-method').value;

            btn.disabled = true;
            btn.innerHTML = 'Processing...';
            msg.classList.add('hidden');
            msg.className = 'hidden mt-4 text-center text-sm font-medium'; // reset classes

            const url = method === 'card' ? '/pay/submit' : '/pay/submit-mobile-money';
            const reference = '{{ $payment->reference }}';

            const payload = {
                payment_reference: reference
            };

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
                        msg.textContent = data.message || 'Please authorise the payment on your phone.';
                        msg.classList.remove('hidden');
                        msg.classList.add('text-blue-600');

                        // Start polling
                        pollStatus(reference);

                    } else {
                        msg.textContent = 'Payment Successful! Redirecting...';
                        msg.classList.remove('hidden');
                        msg.classList.add('text-green-500');

                        setTimeout(() => {
                            window.location.href = "{{ request('callback_url') ?? 'http://localhost:8001/api/payment/callback' }}?reference=" + reference;
                        }, 1000);
                    }
                } else {
                    throw new Error(data.error || 'Payment failed');
                }
            } catch (error) {
                msg.textContent = error.message;
                msg.classList.remove('hidden');
                msg.classList.add('text-red-500');
                btn.disabled = false;
                btn.innerHTML = "Pay {{ $payment->currency }} {{ number_format($payment->amount, 2) }}";
            }
        });
    </script>
</body>

</html>