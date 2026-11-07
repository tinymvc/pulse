<style>
    /* ------------------------------------------------------------------
     * Tokens
     * ------------------------------------------------------------------ */
    :root {
        color-scheme: light;
        --bg: #f6f7f9;
        --surface: #ffffff;
        --surface-2: #f9fafb;
        --hover: #f2f4f7;
        --ink: #101828;
        --ink-2: #344054;
        --muted: #667085;
        --faint: #98a2b3;
        --line: #e4e7ec;
        --line-strong: #d0d5dd;
        --accent: #0b7a55;
        --accent-ink: #09593f;
        --accent-soft: #e6f5ee;
        --ok-bg: #e6f5ee;
        --ok-fg: #09593f;
        --info-bg: #e8f0fe;
        --info-fg: #1d4ed8;
        --warn-bg: #fef3dc;
        --warn-fg: #8a5a00;
        --err-bg: #fde8e8;
        --err-fg: #b42318;
        --s-requests: #0b7a55;
        --s-queries: #3b82f6;
        --s-logs: #f0a020;
        --s-other: #98a2b3;
        --code-bg: #0f1720;
        --code-ink: #d7e0ea;
        --focus: #3b82f6;
        --shadow: 0 1px 2px rgb(16 24 40 / .05);
        --mono: ui-monospace, "SFMono-Regular", "JetBrains Mono", Menlo, Consolas, "Liberation Mono", monospace;
        font: 14px/1.55 "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--ink);
        background: var(--bg);
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
    }

    :root[data-theme="dark"] {
        color-scheme: dark;
        --bg: #0d1117;
        --surface: #141a22;
        --surface-2: #18202a;
        --hover: #1e2733;
        --ink: #e8edf3;
        --ink-2: #c3ccd6;
        --muted: #8a96a5;
        --faint: #66727f;
        --line: #252e3a;
        --line-strong: #364252;
        --accent: #3ccf91;
        --accent-ink: #8ae8bc;
        --accent-soft: #12352a;
        --ok-bg: #12352a;
        --ok-fg: #8ae8bc;
        --info-bg: #172a4d;
        --info-fg: #8ab4ff;
        --warn-bg: #3a2c0d;
        --warn-fg: #f5c35c;
        --err-bg: #431a1a;
        --err-fg: #ff9b92;
        --s-requests: #3ccf91;
        --s-queries: #5b9bff;
        --s-logs: #f5b14a;
        --s-other: #66727f;
        --code-bg: #0a0f15;
        --shadow: none;
    }

    /* ------------------------------------------------------------------
     * Base
     * ------------------------------------------------------------------ */
    * {
        box-sizing: border-box;
    }

    body,
    h1,
    h2,
    h3,
    h4,
    p,
    dl,
    dd,
    ol {
        margin: 0;
    }

    [hidden] {
        display: none !important;
    }

    button,
    input,
    select {
        font: inherit;
    }

    a {
        color: var(--accent);
        text-underline-offset: .2em;
    }

    button,
    a {
        touch-action: manipulation;
    }

    :focus-visible {
        outline: 2px solid var(--focus);
        outline-offset: 2px;
    }

    .skip {
        position: fixed;
        left: 1rem;
        top: -6rem;
        z-index: 20;
        padding: .6rem 1rem;
        background: var(--surface);
        color: var(--ink);
        border: 1px solid var(--line-strong);
        border-radius: 8px;
    }

    .skip:focus {
        top: 1rem;
    }

    small,
    .count {
        font-size: 12px;
        color: var(--muted);
    }

    .mono,
    .count,
    .tags,
    .ids dd,
    .facts dd,
    .legend dd {
        font-family: var(--mono);
    }

    /* ------------------------------------------------------------------
     * Shell: sidebar + main
     * ------------------------------------------------------------------ */
    .shell {
        display: grid;
        grid-template-columns: 248px minmax(0, 1fr);
        min-height: 100vh;
    }

    .rail {
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        padding: 0 12px 24px;
        background: var(--surface);
        border-right: 1px solid var(--line);
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 10px;
        height: 60px;
        margin: 0 4px 6px;
        color: var(--ink);
        text-decoration: none;
        font-size: 15px;
        font-weight: 500;
        letter-spacing: -.01em;
    }

    .brand strong {
        font-weight: 700;
        margin-left: -6px;
    }

    .signal {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 2px;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: var(--accent);
        color: #fff;
    }

    .signal i {
        width: 2.5px;
        height: 8px;
        border-radius: 2px;
        background: currentColor;
    }

    .signal i:nth-child(2) {
        height: 15px;
    }

    .signal i:nth-child(3) {
        height: 11px;
    }

    .mobile-nav {
        display: none;
    }

    .rail h2 {
        margin: 18px 10px 4px;
        font-size: 12px;
        font-weight: 600;
        color: var(--faint);
    }

    .nav-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-height: 34px;
        padding: 6px 10px;
        border-radius: 8px;
        color: var(--ink-2);
        font-size: 13.5px;
        text-decoration: none;
        transition: background-color .12s, color .12s;
    }

    .nav-link:hover {
        background: var(--hover);
        color: var(--ink);
    }

    .nav-link[aria-current] {
        background: var(--accent-soft);
        color: var(--accent-ink);
        font-weight: 600;
    }

    .nav-count {
        min-width: 22px;
        padding: 0 7px;
        border-radius: 999px;
        background: var(--hover);
        color: var(--muted);
        font: 500 11.5px/20px var(--mono);
        text-align: center;
        font-variant-numeric: tabular-nums;
    }

    .nav-link[aria-current] .nav-count {
        background: color-mix(in srgb, var(--accent) 16%, transparent);
        color: var(--accent-ink);
    }

    .overview-link {
        justify-content: flex-start;
        font-weight: 600;
    }

    .grid-icon {
        width: 12px;
        height: 12px;
        background: conic-gradient(currentColor 25%, transparent 0 50%, currentColor 0 75%, transparent 0) 0 0 / 7px 7px;
        opacity: .8;
    }

    .topbar {
        position: sticky;
        top: 0;
        z-index: 5;
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 60px;
        padding: 10px clamp(18px, 3.6vw, 48px);
        background: color-mix(in srgb, var(--surface) 88%, transparent);
        backdrop-filter: saturate(1.4) blur(10px);
        border-bottom: 1px solid var(--line);
        color: var(--muted);
        font-size: 13px;
    }

    .app-name {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--ink);
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .app-icon {
        display: grid;
        place-items: center;
        width: 22px;
        height: 22px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--surface-2);
        font-size: 13px;
        line-height: 1;
    }

    .crumbs {
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .separator {
        color: var(--line-strong);
    }

    .crumb-current {
        color: var(--ink-2);
        font-weight: 500;
    }

    .environment {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-left: auto;
        padding: 2px 10px;
        border-radius: 999px;
        background: var(--ok-bg);
        color: var(--ok-fg);
        font: 500 12px/20px var(--mono);
    }

    .environment::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .environment.env-prod {
        background: var(--warn-bg);
        color: var(--warn-fg);
    }

    .icon-button {
        display: inline-grid;
        place-items: center;
        width: 32px;
        height: 32px;
        padding: 0;
        border: 1px solid var(--line-strong);
        border-radius: 8px;
        background: var(--surface);
        color: var(--ink-2);
        cursor: pointer;
        box-shadow: var(--shadow);
        transition: background-color .12s;
    }

    .icon-button:hover {
        background: var(--hover);
    }

    .icon-sun,
    :root[data-theme="dark"] .icon-moon {
        display: none;
    }

    :root[data-theme="dark"] .icon-sun {
        display: block;
    }

    .content {
        max-width: 1280px;
        margin: 0 auto;
        padding: 28px clamp(18px, 3.6vw, 48px) 56px;
    }

    .page-head,
    .section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .page-head {
        flex-wrap: wrap;
        margin-bottom: 22px;
    }

    h1 {
        font-size: clamp(24px, 2.4vw, 28px);
        font-weight: 650;
        line-height: 1.2;
        letter-spacing: -.022em;
    }

    .back {
        display: inline-block;
        margin-bottom: 6px;
        color: var(--muted);
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
    }

    .back:hover {
        color: var(--accent);
    }

    /* ------------------------------------------------------------------
     * Controls
     * ------------------------------------------------------------------ */
    .actions,
    .search-box {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .control {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 32px;
        padding: 5px 12px;
        border: 1px solid var(--line-strong);
        border-radius: 8px;
        background: var(--surface);
        box-shadow: var(--shadow);
        color: var(--ink-2);
        cursor: pointer;
        font-size: 13px;
        font-weight: 550;
        text-decoration: none;
        transition: background-color .12s, border-color .12s, color .12s;
    }

    .control:hover {
        background: var(--hover);
        color: var(--ink);
    }

    .control:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .control.danger:hover {
        background: var(--err-bg);
        border-color: color-mix(in srgb, var(--err-fg) 35%, var(--line-strong));
        color: var(--err-fg);
    }

    .control.small {
        min-height: 28px;
        padding: 3px 10px;
        font-size: 12px;
    }

    .live {
        min-width: 128px;
    }

    .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--faint);
    }

    .live[aria-pressed="true"] .dot {
        background: var(--accent);
        animation: ping 2s ease-out infinite;
    }

    @keyframes ping {
        0% {
            box-shadow: 0 0 0 0 color-mix(in srgb, var(--accent) 45%, transparent);
        }

        70%,
        100% {
            box-shadow: 0 0 0 6px transparent;
        }
    }

    .text-link,
    .filter a {
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
    }

    .text-link:hover,
    .filter a:hover,
    .trace a:hover {
        text-decoration: underline;
    }

    .filter {
        margin-bottom: 16px;
    }

    .filter label {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
        font-weight: 600;
        color: var(--ink-2);
    }

    .search-field {
        position: relative;
        flex: 1;
        max-width: 400px;
    }

    .search-icon {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--faint);
        pointer-events: none;
    }

    input[type=search] {
        width: 100%;
        min-width: 0;
        height: 34px;
        padding: 6px 12px 6px 32px;
        border: 1px solid var(--line-strong);
        border-radius: 8px;
        background: var(--surface);
        color: var(--ink);
        font-size: 13.5px;
        box-shadow: var(--shadow);
    }

    input[type=search]::placeholder {
        color: var(--faint);
    }

    /* ------------------------------------------------------------------
     * Panels
     * ------------------------------------------------------------------ */
    .panel,
    .metrics>div {
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--surface);
        box-shadow: var(--shadow);
    }

    .panel h2 {
        font-size: 14px;
        font-weight: 600;
        letter-spacing: -.01em;
    }

    .section-head {
        padding: 14px 18px;
        border-bottom: 1px solid var(--line);
    }

    .paused {
        margin-bottom: 16px;
        padding: 10px 14px;
        border: 1px solid color-mix(in srgb, var(--warn-fg) 25%, transparent);
        border-radius: 8px;
        background: var(--warn-bg);
        color: var(--warn-fg);
        font-size: 13px;
    }

    /* ------------------------------------------------------------------
     * Overview
     * ------------------------------------------------------------------ */
    .summary-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.6fr) minmax(240px, 1fr);
        gap: 16px;
    }

    .activity,
    .runtime {
        padding: 20px 22px;
    }

    .total {
        margin: 10px 0 18px;
        font-size: 36px;
        font-weight: 600;
        line-height: 1;
        letter-spacing: -.03em;
        font-variant-numeric: tabular-nums;
    }

    .total span {
        margin-left: 2px;
        font-size: 14px;
        font-weight: 450;
        letter-spacing: 0;
        color: var(--muted);
    }

    .distribution {
        display: flex;
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--hover);
    }

    .series-requests {
        background: var(--s-requests);
    }

    .series-queries {
        background: var(--s-queries);
    }

    .series-logs {
        background: var(--s-logs);
    }

    .series-other {
        background: var(--s-other);
    }

    .legend {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
    }

    .legend dt {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--muted);
        font-size: 12.5px;
    }

    .legend dt i {
        width: 8px;
        height: 8px;
        border-radius: 3px;
    }

    .legend dd {
        margin-top: 2px;
        font-size: 15px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .legend dd span {
        margin-left: 4px;
        color: var(--faint);
        font-size: 11.5px;
        font-weight: 400;
    }

    .facts {
        margin-top: 8px;
    }

    .facts div {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 15px;
        padding: 9px 0;
        border-bottom: 1px solid var(--line);
        font-size: 13px;
    }

    .facts div:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .facts dt {
        color: var(--muted);
    }

    .facts dd {
        font-size: 12.5px;
        text-align: right;
        overflow-wrap: anywhere;
    }

    .metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin: 16px 0 24px;
    }

    .metrics>div {
        padding: 16px 20px;
    }

    .metrics h2 {
        font-size: 12.5px;
        font-weight: 550;
        color: var(--muted);
    }

    .metrics strong {
        display: block;
        margin: 4px 0 2px;
        font-size: 24px;
        font-weight: 600;
        letter-spacing: -.03em;
        font-variant-numeric: tabular-nums;
    }

    .meter {
        display: block;
        height: 4px;
        margin: 8px 0 6px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--hover);
    }

    .meter i {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--accent);
    }

    .meter.warn i {
        background: var(--s-logs);
    }

    .meter.crit i {
        background: var(--err-fg);
    }

    /* ------------------------------------------------------------------
     * Tables
     * ------------------------------------------------------------------ */
    .allocation-note {
        padding: 9px 18px;
        border-bottom: 1px solid var(--line);
        background: var(--surface-2);
        color: var(--muted);
        font-size: 12.5px;
    }

    .scroll-table {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 13px;
    }

    th {
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: var(--muted);
        background: var(--surface-2);
    }

    th,
    td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--line);
    }

    tr:last-child td {
        border-bottom: 0;
    }

    th:first-child,
    td:first-child {
        width: 46%;
        padding-left: 18px;
    }

    th:last-child,
    td:last-child {
        padding-right: 18px;
        text-align: right;
    }

    tbody tr:hover td {
        background: var(--surface-2);
    }

    .row-link {
        display: block;
        overflow: hidden;
        padding: 3px 0;
        color: var(--ink);
        font: 12.5px var(--mono);
        text-decoration: none;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .row-link:hover {
        color: var(--accent);
        text-decoration: underline;
    }

    td.mono {
        color: var(--ink-2);
        font-size: 12px;
        font-variant-numeric: tabular-nums;
    }

    .tags {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 5px;
    }

    .tag {
        max-width: 100%;
        padding: 1px 8px;
        overflow: hidden;
        border-radius: 6px;
        background: var(--hover);
        color: var(--ink-2);
        font-size: 11.5px;
        line-height: 20px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tag-ok {
        background: var(--ok-bg);
        color: var(--ok-fg);
    }

    .tag-info {
        background: var(--info-bg);
        color: var(--info-fg);
    }

    .tag-warn {
        background: var(--warn-bg);
        color: var(--warn-fg);
    }

    .tag-err {
        background: var(--err-bg);
        color: var(--err-fg);
    }

    .empty {
        padding: 52px 16px;
        text-align: center;
    }

    .empty-mark {
        display: inline-grid;
        place-items: center;
        width: 40px;
        height: 40px;
        margin-bottom: 12px;
        border: 1px solid var(--line);
        border-radius: 10px;
        background: var(--surface-2);
        color: var(--accent);
        font-size: 22px;
    }

    .empty h3 {
        font-size: 14px;
        font-weight: 600;
    }

    .empty p {
        margin-top: 3px;
        color: var(--muted);
        font-size: 13px;
    }

    .pager {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 12px 18px;
        border-top: 1px solid var(--line);
    }

    /* ------------------------------------------------------------------
     * Entry detail
     * ------------------------------------------------------------------ */
    .detail-body {
        padding: 20px 22px 22px;
    }

    .detail h3 {
        margin-bottom: 10px;
        font: 600 16px/1.5 var(--mono);
        overflow-wrap: anywhere;
    }

    .ids {
        max-width: 720px;
        margin: 18px 0 22px;
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: 8px;
    }

    .ids div {
        padding: 9px 14px;
    }

    .ids dd {
        text-align: right;
    }

    .payload-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }

    .payload-head h4 {
        font-size: 13px;
        font-weight: 600;
    }

    pre {
        max-height: 36rem;
        margin: 0;
        padding: 16px 18px;
        overflow: auto;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: var(--code-bg);
        color: var(--code-ink);
        font: 12.5px/1.7 var(--mono);
        tab-size: 2;
    }

    .trace {
        margin-top: 16px;
    }

    .trace ol {
        margin: 16px 22px 16px 28px;
        padding: 0;
        list-style: none;
        border-left: 1px solid var(--line-strong);
    }

    .trace li {
        position: relative;
        display: flex;
        align-items: baseline;
        gap: 16px;
        padding: 8px 0 8px 20px;
    }

    .trace li::before {
        content: "";
        position: absolute;
        left: -4.5px;
        top: 1.1rem;
        width: 8px;
        height: 8px;
        border: 2px solid var(--line-strong);
        border-radius: 50%;
        background: var(--surface);
    }

    .trace li:has([aria-current])::before {
        border-color: var(--accent);
        background: var(--accent);
    }

    .trace .mono {
        flex: none;
        width: 96px;
        color: var(--muted);
        font-size: 11.5px;
    }

    .trace a {
        display: block;
        font: 13px var(--mono);
        text-decoration: none;
        overflow-wrap: anywhere;
    }

    .trace [aria-current] {
        color: var(--ink);
        font-weight: 700;
    }

    /* ------------------------------------------------------------------
     * Toast
     * ------------------------------------------------------------------ */
    .notice {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 30;
        max-width: min(90vw, 400px);
        padding: 12px 16px;
        border-radius: 10px;
        background: var(--ink);
        color: var(--bg);
        font-size: 13px;
        box-shadow: 0 8px 24px rgb(16 24 40 / .25);
    }

    /* ------------------------------------------------------------------
     * Responsive
     * ------------------------------------------------------------------ */
    @media (max-width: 1050px) {
        .shell {
            grid-template-columns: 200px minmax(0, 1fr);
        }

        .legend {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .shell {
            display: block;
        }

        .rail {
            position: static;
            height: auto;
            padding: 0 16px 12px;
            border-right: 0;
            border-bottom: 1px solid var(--line);
        }

        .brand {
            height: 52px;
            margin: 0;
        }

        .rail nav {
            display: none;
        }

        .mobile-nav {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mobile-nav select,
        .mobile-nav button {
            min-height: 36px;
            padding: 6px 12px;
            border: 1px solid var(--line-strong);
            border-radius: 8px;
            background: var(--surface);
            color: var(--ink);
            font-size: 14px;
        }

        .mobile-nav select {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            position: static;
            min-height: 48px;
        }

        .topbar,
        .content {
            padding-inline: 16px;
        }

        .content {
            padding-top: 20px;
        }

        .crumbs {
            display: none;
        }

        .summary-grid,
        .metrics {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .metrics {
            margin-bottom: 16px;
        }

        .section-head,
        .detail-body {
            padding: 14px;
        }

        table {
            min-width: 560px;
        }

        .page-head {
            margin-bottom: 16px;
        }

        .ids div {
            flex-wrap: wrap;
            gap: 4px;
        }

        .ids dd {
            text-align: left;
        }

        .trace li {
            flex-direction: column;
            gap: 2px;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        *,
        *::before,
        *::after {
            animation: none !important;
            transition: none !important;
        }
    }
</style>
