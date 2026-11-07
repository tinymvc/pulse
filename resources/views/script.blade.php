<script>
    (() => {
        const root = document.documentElement;

        const clockFields = {
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h12'
        };
        const clocks = {
            clock: new Intl.DateTimeFormat(undefined, clockFields),
            trace: new Intl.DateTimeFormat(undefined, {
                ...clockFields,
                fractionalSecondDigits: 3
            }),
            detail: new Intl.DateTimeFormat(undefined, {
                ...clockFields,
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                timeZoneName: 'shortOffset'
            })
        };
        const timeZone = clocks.clock.resolvedOptions().timeZone;

        function displayLocalTimes(scope) {
            for (const heading of scope.querySelectorAll('[data-time-heading]')) {
                heading.textContent = 'Local time';
                heading.title = timeZone;
            }
            for (const time of scope.querySelectorAll('time[data-local-time]')) {
                // Pulse stores UTC with six fractional digits. JS dates carry milliseconds.
                const iso = time.dateTime.replace(/(\.\d{3})\d+/, '$1');
                const instant = new Date(iso);
                if (!Number.isFinite(instant.getTime())) continue;

                time.textContent = clocks[time.dataset.localTime].format(instant);
                time.title = clocks.detail.format(instant) + ' (' + timeZone + ')';
            }
        }

        displayLocalTimes(document);

        // The same navigation is a sidebar on desktop and a native menu on mobile.
        const navigation = document.getElementById('telemetry-nav');
        const menuButton = document.querySelector('[popovertarget="telemetry-nav"]');
        if (navigation && menuButton && 'showPopover' in navigation) {
            const compact = window.matchMedia('(max-width: 800px)');

            function syncNavigation() {
                navigation.toggleAttribute('popover', compact.matches);
                menuButton.hidden = !compact.matches;
            }
            syncNavigation();
            compact.addEventListener('change', syncNavigation);
        }

        // Escape dismisses a tooltip while its control keeps keyboard focus.
        const headerActions = document.querySelectorAll('.bar-actions [data-tip]');
        for (const action of headerActions) {
            const resetTip = () => action.removeAttribute('data-tip-dismissed');
            action.addEventListener('pointerenter', resetTip);
            action.addEventListener('focus', resetTip);
        }
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                for (const action of headerActions) {
                    if (action.matches(':hover, :focus-visible')) action.setAttribute('data-tip-dismissed',
                        '');
                }
            }
        });

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

        const payload = document.getElementById('payload');
        if (payload) {
            const json = payload.textContent;
            const tokens = /"(?:\\.|[^"\\])*"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null/g;
            const fragment = document.createDocumentFragment();
            let offset = 0;

            for (const match of json.matchAll(tokens)) {
                const value = match[0];
                fragment.append(json.slice(offset, match.index));
                offset = match.index + value.length;

                let type = 'number';
                if (value.startsWith('"')) {
                    type = json[offset] === ':' ? 'key' : 'string';
                } else if (value === 'null') {
                    type = 'null';
                } else if (value === 'true' || value === 'false') {
                    type = 'boolean';
                }

                const span = document.createElement('span');
                span.className = 'json-' + type;
                span.textContent = value;
                fragment.append(span);
            }
            fragment.append(json.slice(offset));
            payload.replaceChildren(fragment);
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
            toggle.dataset.tip = live ? 'Pause live updates' : 'Resume live updates';
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
                displayLocalTimes(doc);
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
