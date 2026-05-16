<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Wallet Auth Sandbox</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        button { margin: 0.25rem 0.25rem 0.25rem 0; padding: 0.5rem 0.75rem; cursor: pointer; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        pre { background: #f4f4f5; padding: 0.75rem; overflow-x: auto; font-size: 0.85rem; }
        .ok { color: #166534; }
        .err { color: #991b1b; }
        ol { padding-left: 1.25rem; }
    </style>
</head>
<body>
    <h1>Wallet Auth Sandbox</h1>
    <p>Flow: Connect MetaMask → nonce → sign → verify → session login → protected route.</p>

    <ol>
        <li><button type="button" id="btn-connect">1. Connect MetaMask</button></li>
        <li><button type="button" id="btn-signin" disabled>2–4. Request nonce, sign &amp; verify</button></li>
        <li><a href="{{ route('dashboard') }}" id="link-protected">5. Open protected route</a></li>
        <li><button type="button" id="btn-logout" @guest disabled @endguest>Logout</button></li>
    </ol>

    <p id="status">Status: not connected</p>
    <pre id="log">Ready.</pre>

    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const logEl = document.getElementById('log');
        const statusEl = document.getElementById('status');
        const btnConnect = document.getElementById('btn-connect');
        const btnSignIn = document.getElementById('btn-signin');
        const btnLogout = document.getElementById('btn-logout');

        let address = null;

        function log(msg, isError = false) {
            logEl.textContent = msg;
            logEl.className = isError ? 'err' : '';
        }

        async function post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || res.statusText);
            return data;
        }

        btnConnect.addEventListener('click', async () => {
            if (!window.ethereum) {
                log('MetaMask not found. Install the extension.', true);
                return;
            }
            try {
                const accounts = await ethereum.request({ method: 'eth_requestAccounts' });
                address = accounts[0];
                statusEl.textContent = 'Status: connected — ' + address;
                btnSignIn.disabled = false;
                log('Connected:\n' + address);
            } catch (e) {
                log('Connect failed: ' + e.message, true);
            }
        });

        btnSignIn.addEventListener('click', async () => {
            if (!address) return;
            try {
                log('Requesting nonce from server…');
                const { message } = await post('{{ route('auth.nonce') }}', { address });

                log('Sign this message in MetaMask:\n\n' + message);
                const signature = await ethereum.request({
                    method: 'personal_sign',
                    params: [message, address],
                });

                log('Verifying signature on server…');
                const result = await post('{{ route('auth.verify') }}', { address, signature });

                statusEl.innerHTML = 'Status: <span class="ok">logged in</span> — ' + address;
                btnLogout.disabled = false;
                log('Success:\n' + JSON.stringify(result, null, 2) + '\n\nOpen the protected route link above.');
            } catch (e) {
                log('Sign-in failed: ' + e.message, true);
            }
        });

        btnLogout.addEventListener('click', async () => {
            try {
                await post('{{ route('auth.logout') }}', {});
                address = null;
                btnSignIn.disabled = true;
                btnLogout.disabled = true;
                statusEl.textContent = 'Status: logged out';
                log('Logged out.');
            } catch (e) {
                log('Logout failed: ' + e.message, true);
            }
        });
    </script>
</body>
</html>
