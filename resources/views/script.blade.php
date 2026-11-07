<script>
    (() => {
        const root = document.documentElement;

        /* Theme toggle (works on every page) */
        const themeButton = document.getElementById('theme');
        if (themeButton) {
            themeButton.setAttribute('aria-pressed', String(root.dataset.theme === 'dark'));
            themeButton.addEventListener('click', () => {
                const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
                root.dataset.theme = next;
                themeButton.setAttribute('aria-pressed', String(next === 'dark'));
                try {
                    localStorage.setItem('pulse:theme', next);
                } catch {}
            });
        }

        /* Copy-to-clipboard buttons (e.g. entry payload) */
        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-copy]');
            const target = button && document.querySelector(button.dataset.copy);
            if (!target) return;
            const original = button.textContent;
            try {
                await navigator.clipboard.writeText(target.textContent);
                button.textContent = 'Copied';
            } catch {
                button.textContent = 'Copy failed';
            }
            window.setTimeout(() => {
                button.textContent = original;
            }, 1600);
        });

        /* Live refresh + clear (list and overview pages only) */
        const toggle = document.getElementById('refresh');
        if (!toggle) return;

        const clear = document.getElementById('clear');
        const notice = document.getElementById('notice');
        const label = document.getElementById('refresh-label');
        const setting = 'pulse:live:' + location.pathname;
        let live = true;
        try {
            live = sessionStorage.getItem(setting) !== 'off';
        } catch {}
        let controller;
        let clearing = false;

        function showNotice(message) {
            notice.textContent = message;
            notice.hidden = !message;
        }

        function updateToggle() {
            toggle.setAttribute('aria-pressed', String(live));
            label.textContent = live ? 'Live updates' : 'Updates paused';
        }

        async function refresh() {
            if (!live || clearing || controller || document.hidden) return;
            controller = new AbortController();
            try {
                const response = await fetch(location.href, {
                    headers: {
                        'X-Pulse-Refresh': '1'
                    },
                    signal: controller.signal,
                    cache: 'no-store'
                });
                if (!response.ok) throw new Error();
                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                if (!doc.querySelector('[data-live="entries"]')) throw new Error();
                if (!live || clearing) return;
                for (const region of document.querySelectorAll('[data-live]')) {
                    const updated = doc.querySelector('[data-live="' + region.dataset.live + '"]');
                    if (updated && !region.contains(document.activeElement) && !region.isEqualNode(updated)) {
                        region.replaceWith(updated);
                    }
                }
                showNotice('');
            } catch (error) {
                if (error.name !== 'AbortError') showNotice('Refresh failed. Showing the last loaded entries.');
            } finally {
                controller = null;
            }
        }

        toggle.addEventListener('click', () => {
            live = !live;
            try {
                sessionStorage.setItem(setting, live ? 'on' : 'off');
            } catch {}
            updateToggle();
            if (live) refresh();
            else controller?.abort();
        });

        clear?.addEventListener('click', async () => {
            if (!window.confirm('Clear all recorded Pulse entries? This cannot be undone.')) return;
            clearing = true;
            clear.disabled = true;
            controller?.abort();
            try {
                const response = await fetch(clear.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                            .content
                    }
                });
                if (!response.ok || !(await response.json()).success) throw new Error();
                location.reload();
            } catch {
                showNotice('Clear failed. Reload the page to check access and try again.');
                clear.disabled = false;
                clearing = false;
            }
        });

        updateToggle();
        const tools = document.querySelector('[data-tools]');
        if (tools) tools.hidden = false;
        window.setInterval(refresh, 5000);
        document.addEventListener('visibilitychange', refresh);
    })();
</script>
