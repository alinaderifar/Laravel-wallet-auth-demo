<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Wallet Auth Sandbox</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 42rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        button, .btn-link { margin: 0.25rem 0.25rem 0.25rem 0; padding: 0.5rem 0.75rem; cursor: pointer; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        pre { background: #f4f4f5; padding: 0.75rem; overflow-x: auto; font-size: 0.85rem; white-space: pre-wrap; }
        .ok { color: #166534; }
        .err { color: #991b1b; }
        .warn { color: #92400e; }
        #error-box { display: none; margin: 1rem 0; padding: 0.75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; }
        #error-box.visible { display: block; }
        #error-box.retryable #btn-retry { display: inline-block; }
        #btn-retry { display: none; }
        ol { padding-left: 1.25rem; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <h1>Wallet Auth Sandbox</h1>
    <p>SIWE sign-in (EIP-4361). MetaMask stays connected silently when already authorized.</p>

    <ol>
        <li>
            <button type="button" id="btn-connect">Connect MetaMask</button>
            <span id="connect-hint" class="warn hidden"> — permission needed</span>
        </li>
        <li><button type="button" id="btn-signin" disabled>Sign in with Ethereum (SIWE)</button></li>
        <li><a href="{{ route('dashboard') }}" id="link-protected">Open protected route</a></li>
        <li><button type="button" id="btn-logout" disabled>Logout</button></li>
    </ol>

    <p id="status">Status: initializing…</p>

    <div id="error-box">
        <strong id="error-title">Error</strong>
        <p id="error-message"></p>
        <button type="button" id="btn-retry">Retry</button>
        <button type="button" id="btn-dismiss">Dismiss</button>
    </div>

    <pre id="log">Ready.</pre>

    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const routes = {
            status: @json(route('auth.status')),
            nonce: @json(route('auth.nonce')),
            verify: @json(route('auth.verify')),
            logout: @json(route('auth.logout')),
        };

        const logEl = document.getElementById('log');
        const statusEl = document.getElementById('status');
        const btnConnect = document.getElementById('btn-connect');
        const btnSignIn = document.getElementById('btn-signin');
        const btnLogout = document.getElementById('btn-logout');
        const connectHint = document.getElementById('connect-hint');
        const errorBox = document.getElementById('error-box');
        const errorMessage = document.getElementById('error-message');
        const btnRetry = document.getElementById('btn-retry');
        const btnDismiss = document.getElementById('btn-dismiss');

        const MAX_FETCH_RETRIES = 3;
        const RETRY_BASE_MS = 400;

        let address = null;
        let chainId = null;
        let sessionWallet = null;
        let isAuthenticated = false;
        let lastFailedAction = null;
        let busy = false;

        function log(msg, kind = '') {
            logEl.textContent = msg;
            logEl.className = kind;
        }

        function setBusy(state) {
            busy = state;
            btnSignIn.disabled = state || !address || isAuthenticated;
            btnConnect.disabled = state || isAuthenticated;
        }

        function showError(message, { retryable = false, code = '' } = {}) {
            errorMessage.textContent = code ? `[${code}] ${message}` : message;
            errorBox.classList.toggle('visible', true);
            errorBox.classList.toggle('retryable', retryable);
        }

        function clearError() {
            errorBox.classList.remove('visible', 'retryable');
            errorMessage.textContent = '';
        }

        function isUserRejection(err) {
            return err && (err.code === 4001 || /user rejected|user denied/i.test(err.message || ''));
        }

        async function sleep(ms) {
            return new Promise(r => setTimeout(r, ms));
        }

        async function requestJson(url, options = {}, retries = MAX_FETCH_RETRIES) {
            let lastError;
            for (let attempt = 0; attempt < retries; attempt++) {
                try {
                    const res = await fetch(url, {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                            ...options.headers,
                        },
                        ...options,
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const err = new Error(data.message || res.statusText);
                        err.code = data.code;
                        err.retryable = data.retryable ?? (res.status >= 500);
                        throw err;
                    }
                    return data;
                } catch (e) {
                    lastError = e;
                    if (e.retryable === false || attempt === retries - 1) throw e;
                    await sleep(RETRY_BASE_MS * Math.pow(2, attempt));
                }
            }
            throw lastError;
        }

        function parseChainId(hexOrInt) {
            if (typeof hexOrInt === 'string' && hexOrInt.startsWith('0x')) {
                return parseInt(hexOrInt, 16);
            }
            return Number(hexOrInt);
        }

        async function refreshChainId() {
            if (!window.ethereum) return null;
            chainId = parseChainId(await ethereum.request({ method: 'eth_chainId' }));
            return chainId;
        }

        /** Silent read — no MetaMask popup if already connected */
        async function silentAccounts() {
            if (!window.ethereum) return [];
            return ethereum.request({ method: 'eth_accounts' });
        }

        /** Popup only when user has not granted access yet */
        async function requestAccounts() {
            return ethereum.request({ method: 'eth_requestAccounts' });
        }

        async function loadSession() {
            const data = await requestJson(routes.status, { method: 'GET' });
            isAuthenticated = data.authenticated;
            sessionWallet = data.wallet_address ? data.wallet_address.toLowerCase() : null;
            return data;
        }

        function updateUi() {
            if (isAuthenticated && sessionWallet) {
                statusEl.innerHTML = 'Status: <span class="ok">logged in</span> — ' + sessionWallet;
                btnLogout.disabled = false;
                btnSignIn.disabled = true;
                btnConnect.disabled = true;
                connectHint.classList.add('hidden');
                if (address && address.toLowerCase() !== sessionWallet) {
                    statusEl.innerHTML += ' <span class="warn">(MetaMask shows a different account)</span>';
                    btnSignIn.disabled = false;
                    btnSignIn.textContent = 'Switch wallet — sign in again';
                }
                return;
            }

            if (address) {
                statusEl.textContent = 'Status: wallet connected — ' + address + (chainId ? ' (chain ' + chainId + ')' : '');
                btnSignIn.disabled = busy;
                btnSignIn.textContent = 'Sign in with Ethereum (SIWE)';
                btnLogout.disabled = true;
            } else {
                statusEl.textContent = 'Status: wallet not connected';
                btnSignIn.disabled = true;
                btnLogout.disabled = true;
            }
        }

        async function connectWallet(promptIfNeeded = true) {
            if (!window.ethereum) {
                showError('MetaMask not found. Install the browser extension.', { retryable: false });
                log('MetaMask not found.', 'err');
                return false;
            }

            let accounts = await silentAccounts();

            if (accounts.length === 0 && promptIfNeeded) {
                connectHint.classList.remove('hidden');
                accounts = await requestAccounts();
                connectHint.classList.add('hidden');
            } else {
                connectHint.classList.add('hidden');
            }

            if (accounts.length === 0) {
                return false;
            }

            address = accounts[0];
            await refreshChainId();
            updateUi();
            log('Wallet ready (no sign-in yet):\n' + address + '\nChain ID: ' + chainId);
            return true;
        }

        async function signIn() {
            if (!address || busy) return;
            clearError();
            setBusy(true);
            lastFailedAction = signIn;

            try {
                await refreshChainId();
                if (!chainId) throw new Error('Could not read chain ID from MetaMask.');

                log('Requesting SIWE challenge…');
                const challenge = await requestJson(routes.nonce, {
                    method: 'POST',
                    body: JSON.stringify({ address, chain_id: chainId }),
                });

                log('Approve the SIWE message in MetaMask:\n\n' + challenge.message);

                let signature;
                try {
                    signature = await ethereum.request({
                        method: 'personal_sign',
                        params: [challenge.message, address],
                    });
                } catch (e) {
                    if (isUserRejection(e)) {
                        showError('You declined the signature request.', { retryable: true, code: 'user_rejected' });
                        log('Sign-in cancelled in MetaMask.', 'warn');
                        return;
                    }
                    throw e;
                }

                log('Verifying signature…');
                const result = await requestJson(routes.verify, {
                    method: 'POST',
                    body: JSON.stringify({ address, signature, chain_id: chainId }),
                });

                isAuthenticated = true;
                sessionWallet = (result.wallet_address || address).toLowerCase();
                clearError();
                log('Signed in:\n' + JSON.stringify(result, null, 2) + '\n\nOpen the protected route when ready.');
            } catch (e) {
                const retryable = e.retryable !== false && !isUserRejection(e);
                showError(e.message, { retryable, code: e.code || '' });
                log('Sign-in failed: ' + e.message, 'err');

                if (e.code === 'nonce_expired' || e.code === 'nonce_missing') {
                    log('Tip: click Retry to request a fresh SIWE challenge.', 'warn');
                }
            } finally {
                setBusy(false);
                updateUi();
            }
        }

        async function logout() {
            clearError();
            setBusy(true);
            try {
                await requestJson(routes.logout, { method: 'POST', body: '{}' });
                isAuthenticated = false;
                sessionWallet = null;
                log('Logged out. Wallet may stay connected in MetaMask without a new popup.');
            } catch (e) {
                showError(e.message, { retryable: e.retryable !== false, code: e.code || '' });
                log('Logout failed: ' + e.message, 'err');
            } finally {
                setBusy(false);
                updateUi();
            }
        }

        async function init() {
            try {
                const session = await loadSession();

                if (session.authenticated) {
                    isAuthenticated = true;
                    sessionWallet = session.wallet_address.toLowerCase();
                    log('Existing server session found for:\n' + sessionWallet);
                }

                if (window.ethereum) {
                    await connectWallet(false);

                    if (isAuthenticated && address && address.toLowerCase() === sessionWallet) {
                        log('MetaMask already connected to the same wallet — no sign-in popup needed.');
                    } else if (isAuthenticated && !address) {
                        log('Session active. Connect MetaMask only if you need to switch wallets.');
                    }

                    ethereum.on?.('accountsChanged', async (accounts) => {
                        address = accounts[0] || null;
                        if (isAuthenticated && address && address.toLowerCase() !== sessionWallet) {
                            showError('Account changed in MetaMask. Sign in again or log out.', {
                                retryable: true,
                                code: 'account_changed',
                            });
                            isAuthenticated = false;
                            sessionWallet = null;
                        }
                        updateUi();
                    });

                    ethereum.on?.('chainChanged', () => {
                        refreshChainId().then(updateUi);
                    });
                } else {
                    log('Install MetaMask to continue.', 'warn');
                }
            } catch (e) {
                log('Init failed: ' + e.message, 'err');
            }

            updateUi();
        }

        btnConnect.addEventListener('click', async () => {
            clearError();
            setBusy(true);
            try {
                await connectWallet(true);
            } catch (e) {
                if (!isUserRejection(e)) {
                    showError(e.message, { retryable: true });
                } else {
                    showError('Connection request declined.', { retryable: true, code: 'user_rejected' });
                }
                log('Connect failed: ' + e.message, 'err');
            } finally {
                setBusy(false);
                updateUi();
            }
        });

        btnSignIn.addEventListener('click', signIn);
        btnLogout.addEventListener('click', logout);
        btnRetry.addEventListener('click', () => {
            clearError();
            if (typeof lastFailedAction === 'function') lastFailedAction();
        });
        btnDismiss.addEventListener('click', clearError);

        init();
    </script>
</body>
</html>
