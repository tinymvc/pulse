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
        --rail-width: 232px;
        --bar-height: 60px;
        --gutter: clamp(20px, 2vw, 40px);
        display: grid;
        grid-template-columns: var(--rail-width) minmax(0, 1fr);
        grid-template-rows: var(--bar-height) 1fr;
        min-height: 100vh;
    }

    .workspace-bar {
        position: sticky;
        top: 0;
        z-index: 5;
        grid-column: 1 / -1;
        display: grid;
        grid-template-columns: var(--rail-width) minmax(0, 1fr);
        height: var(--bar-height);
        border-bottom: 1px solid var(--line);
        background: var(--surface);
    }

    .brand-cell,
    .bar-body {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    .brand-cell {
        padding-inline: 22px;
        border-right: 1px solid var(--line);
    }

    .bar-body {
        justify-content: space-between;
        gap: 12px;
        padding-inline: var(--gutter);
    }

    .rail {
        position: sticky;
        top: var(--bar-height);
        align-self: start;
        height: calc(100vh - var(--bar-height));
        height: calc(100dvh - var(--bar-height));
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: var(--line-strong) transparent;
        padding: 22px 12px;
        border-right: 1px solid var(--line);
        background: var(--surface);
        color: var(--ink);
    }

    .brand {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: var(--ink);
        text-decoration: none;
        font-size: 16px;
        font-weight: 500;
        letter-spacing: -.035em;
        white-space: nowrap;
    }

    .brand strong {
        font-weight: 720;
    }

    .signal {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: none;
        gap: 2px;
        width: 32px;
        height: 32px;
        border: 1px solid rgb(255 255 255 / .16);
        border-radius: 10px;
        background: linear-gradient(145deg, #237963, #144c42);
        box-shadow: inset 0 1px 0 rgb(255 255 255 / .12);
        color: #adf4ce;
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

    .rail h2 {
        margin: 22px 10px 6px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .045em;
        text-transform: uppercase;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 33px;
        padding: 5px 10px;
        border-radius: 7px;
        color: var(--ink-2);
        font-size: 13px;
        text-decoration: none;
        transition: background-color .12s, color .12s;
    }

    .nav-glyph {
        flex: none;
        width: 15px;
        height: 15px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.35;
        stroke-linecap: round;
        stroke-linejoin: round;
        color: var(--muted);
    }

    .nav-link[aria-current] .nav-glyph {
        color: var(--accent);
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
        flex: none;
        margin-left: auto;
        color: var(--muted);
        font: 500 11.5px/20px var(--mono);
        font-variant-numeric: tabular-nums;
    }

    .nav-link[aria-current] .nav-count {
        color: var(--accent-ink);
    }

    .overview-link {
        justify-content: flex-start;
        font-weight: 600;
    }

    .frame {
        padding-inline: var(--gutter);
    }

    .app-context {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 9px;
    }

    .app-context>svg {
        flex: none;
        color: var(--muted);
    }

    .app-name,
    .environment {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .app-name {
        color: var(--ink);
        font-size: 13px;
        font-weight: 600;
    }

    .environment {
        flex: none;
        max-width: 14ch;
        padding: 1px 7px;
        border: 1px solid color-mix(in srgb, currentColor 20%, transparent);
        border-radius: 5px;
        background: var(--ok-bg);
        color: var(--ok-fg);
        font: 500 10.5px/17px var(--mono);
    }

    .environment.env-prod {
        background: var(--warn-bg);
        color: var(--warn-fg);
    }

    .icon-button {
        display: inline-grid;
        place-items: center;
        flex: none;
        width: 32px;
        height: 32px;
        padding: 0;
        border: 1px solid var(--line);
        border-radius: 7px;
        background: var(--surface);
        color: var(--ink-2);
        cursor: pointer;
    }

    .icon-button:hover {
        border-color: var(--line);
        background: var(--hover);
        color: var(--ink);
    }

    .icon-sun,
    :root[data-theme="dark"] .icon-moon {
        display: none;
    }

    :root[data-theme="dark"] .icon-sun {
        display: block;
    }

    .content {
        min-width: 0;
        scroll-margin-top: var(--bar-height);
        padding-block: 22px 56px;
    }

    .section-head,
    .heading-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .heading-row {
        min-height: 34px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    h1 {
        font-size: clamp(26px, 2.2vw, 32px);
        font-weight: 650;
        line-height: 1.08;
        letter-spacing: -.045em;
    }

    .heading-row p,
    .back {
        display: block;
        margin-top: 6px;
        color: var(--muted);
        font-size: 13.5px;
    }

    .back {
        text-decoration: none;
    }

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
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
        min-width: 122px;
        justify-content: center;
    }

    .live[aria-pressed="true"] {
        border-color: color-mix(in srgb, var(--accent) 30%, var(--line));
        background: var(--accent-soft);
        color: var(--accent-ink);
    }

    .control.danger {
        border-color: transparent;
        background: transparent;
        box-shadow: none;
        color: var(--muted);
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
    .metrics {
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
        grid-template-columns: minmax(0, 1.45fr) minmax(240px, 1fr);
        gap: 16px;
    }

    .activity,
    .runtime {
        padding: 18px 22px;
    }

    .activity-body {
        display: grid;
        grid-template-columns: clamp(96px, 10vw, 130px) minmax(0, 1fr);
        align-items: center;
        gap: clamp(18px, 2.8vw, 46px);
        margin-top: 10px;
    }

    .dial {
        position: relative;
        container-type: inline-size;
        width: 100%;
        aspect-ratio: 1;
    }

    .dial svg {
        display: block;
        width: 100%;
        transform: rotate(-90deg);
    }

    .dial-track {
        stroke: var(--line);
    }

    .dial-total {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        line-height: 1.25;
    }

    .dial-total strong {
        font-size: 23px;
        font-size: min(29cqi, calc(124cqi / var(--digits)));
        font-variant-numeric: tabular-nums;
        font-weight: 620;
        letter-spacing: -.05em;
    }

    .dial-total span {
        color: var(--muted);
        font: 11px/1.4 var(--mono);
    }

    .series-requests {
        color: var(--s-requests);
    }

    .series-queries {
        color: var(--s-queries);
    }

    .series-logs {
        color: var(--s-logs);
    }

    .series-other {
        color: var(--s-other);
    }

    .legend>div,
    .legend dt,
    .legend dd {
        display: flex;
        align-items: center;
    }

    .legend>div {
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 2px 8px;
        padding: 5px 0;
        border-bottom: 1px solid var(--line);
    }

    .legend>div:last-child {
        border-bottom: 0;
    }

    .legend dt {
        gap: 8px;
        color: var(--ink-2);
        font-size: 13px;
    }

    .legend dt i {
        width: 7px;
        height: 7px;
        border-radius: 2px;
        background: currentColor;
    }

    .legend dd {
        margin-left: auto;
        font-size: 12.5px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .legend small {
        width: 4.5ch;
        margin-left: 8px;
        text-align: right;
        font: 400 11px/1.5 var(--mono);
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
        margin: 16px 0 24px;
        padding-block: 15px;
    }

    .metrics>div {
        min-width: 0;
        padding: 0 22px;
    }

    .metrics>div+div {
        border-left: 1px solid var(--line);
    }

    .metrics h2 {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12.5px;
        font-weight: 550;
        color: var(--muted);
    }

    .metrics strong {
        display: block;
        margin: 3px 0 1px;
        font-size: 24px;
        line-height: 1.35;
        font-weight: 620;
        letter-spacing: -.035em;
        font-variant-numeric: tabular-nums;
    }

    .storage-reading {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .meter {
        flex: 0 1 60px;
        height: 4px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--line);
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

    .entries th:last-child {
        width: max(18%, 8rem);
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

    #payload .json-key {
        color: #93c5fd;
    }

    #payload .json-string {
        color: #86e3b0;
    }

    #payload .json-number {
        color: #f8c784;
    }

    #payload .json-boolean {
        color: #d1b2ff;
    }

    #payload .json-null {
        color: #a5b3c3;
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
        left: -6.5px;
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
        width: 16ch;
        white-space: nowrap;
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
            --rail-width: 200px;
        }

        .summary-grid {
            grid-template-columns: minmax(0, 1.3fr) minmax(205px, 1fr);
        }

        .activity,
        .runtime,
        .metrics>div {
            padding-inline: 16px;
        }
    }

    @media (max-width: 800px) {
        .shell {
            --bar-height: 54px;
            --gutter: 16px;
            display: block;
        }

        .workspace-bar {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .brand-cell {
            gap: 8px;
            padding-inline: 12px;
        }

        .bar-body {
            padding-inline: 12px;
        }

        .rail {
            position: static;
            height: auto;
            max-height: 40vh;
            padding: 12px;
            border: 0;
            border-bottom: 1px solid var(--line);
        }

        .rail[popover] {
            position: fixed;
            inset: calc(var(--bar-height) + 4px) auto auto 8px;
            width: min(264px, calc(100vw - 16px));
            max-height: calc(100dvh - var(--bar-height) - 12px);
            margin: 0;
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 12px 32px rgb(0 0 0 / .18);
        }

        .nav-toggle {
            border-color: transparent;
        }

        .content {
            padding-top: 18px;
        }

        .heading-row {
            margin-bottom: 16px;
        }

        table {
            min-width: 560px;
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

    @media (max-width: 600px) {

        .brand-prefix,
        .app-context>svg {
            display: none;
        }

        .brand {
            gap: 6px;
        }

        .signal {
            width: 26px;
            height: 26px;
            border-radius: 8px;
        }

        .bar-body,
        .app-context {
            gap: 6px;
        }

        .app-context {
            flex: 1;
        }

        .environment {
            max-width: min(20ch, 70%);
        }

        .summary-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .activity-body {
            grid-template-columns: minmax(88px, .65fr) minmax(0, 1fr);
            gap: 22px;
        }

        .dial {
            max-width: 118px;
            justify-self: center;
        }

        .runtime .facts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-top: 12px;
        }

        .runtime .facts div {
            display: block;
            padding: 0;
            border: 0;
        }

        .runtime .facts dt,
        .metrics h2 {
            font-size: 11.5px;
        }

        .runtime .facts dd {
            margin-top: 3px;
            text-align: left;
        }

        .metrics {
            margin: 12px 0 16px;
            padding-block: 12px;
        }

        .metrics>div {
            padding-inline: 10px;
        }

        .metrics h2 svg,
        .meter {
            display: none;
        }

        .metrics strong {
            font-size: 20px;
        }

        .metrics small {
            display: block;
            font-size: 11px;
        }

        .recent table {
            min-width: 320px;
        }

        .recent th,
        .recent td {
            padding: 9px 4px;
        }

        .recent th:first-child,
        .recent td:first-child {
            width: 40%;
            padding-left: 12px;
        }

        .recent th:nth-child(2) {
            width: 14%;
        }

        .recent th:last-child {
            width: 7rem;
        }

        .recent th:last-child,
        .recent td:last-child {
            padding-right: 10px;
        }

        .section-head,
        .detail-body {
            padding: 14px;
        }
    }

    @media (max-width: 360px) {
        .app-context {
            flex-direction: column;
            align-items: flex-start;
            gap: 1px;
        }

        .app-name,
        .environment {
            max-width: 100%;
        }

        .environment {
            padding: 0 5px;
            line-height: 15px;
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
