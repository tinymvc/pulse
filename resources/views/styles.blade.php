<style>
    :root {
        --ink: #192a24;
        --muted: #596b62;
        --line: #d6dfd8;
        --paper: #f5f7f3;
        --accent: #176943;
        --rail: #152d24;
        --mono: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        font: 15px/1.5 "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        color: var(--ink);
        background: var(--paper);
    }
    * { box-sizing: border-box; }
    body, h1, h2, h3, h4, p, dl, dd, ol { margin: 0; }
    [hidden] { display: none !important; }
    button, input { font: inherit; }
    a { color: var(--accent); text-underline-offset: .2em; }
    button, a { touch-action: manipulation; }
    :focus-visible { outline: 3px solid #518ee6; outline-offset: 3px; }
    .skip { position: fixed; left: 1rem; top: -6rem; z-index: 4; padding: .6rem 1rem; background: white; }
    .skip:focus { top: 1rem; }
    .shell { display: grid; grid-template-columns: 224px minmax(0, 1fr); min-height: 100vh; }
    .rail { background: var(--rail); color: #edf6ee; padding: 0 16px 24px; }
    .brand { display: flex; align-items: center; gap: 5px; height: 72px; margin: 0 6px 12px; color: inherit; text-decoration: none; font-size: 20px; letter-spacing: -.8px; }
    .brand strong { font-weight: 720; }
    .signal { display: inline-flex; align-items: center; justify-content: center; gap: 2px; background: #c2f39a; width: 32px; height: 32px; margin-right: 6px; border-radius: 9px; color: var(--rail); }
    .signal i { width: 3px; height: 7px; background: currentColor; border-radius: 2px; }
    .signal i:nth-child(2) { height: 17px; }
    .signal i:nth-child(3) { height: 11px; }
    .mobile-nav { display: none; }
    .rail h2 { color: #a2b6aa; font-size: 10px; letter-spacing: .13em; text-transform: uppercase; margin: 20px 12px 5px; }
    .nav-link { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: #d6e1d9; text-decoration: none; padding: 6px 12px; min-height: 32px; border-radius: 6px; font-size: 13px; }
    .nav-link:hover { background: #223e32; }
    .nav-link[aria-current] { background: #305440; color: #efffe6; box-shadow: inset 3px 0 #bde990; }
    .nav-count { font: 11px var(--mono); color: #b2c5b7; }
    .overview-link { justify-content: flex-start; font-weight: 650; min-height: 38px; }
    .grid-icon { width: 13px; height: 13px; background: conic-gradient(currentColor 25%, transparent 0 50%, currentColor 0 75%, transparent 0) 0 0 / 8px 8px; }
    .topbar { display: flex; align-items: center; gap: 13px; min-height: 62px; padding: 12px clamp(18px, 3.6vw, 58px); background: #fff; border-bottom: 1px solid var(--line); color: var(--muted); font-size: 13px; }
    .app-name { display: inline-flex; align-items: center; gap: 8px; color: var(--ink); font-weight: 650; overflow-wrap: anywhere; }
    .app-icon { display: grid; place-items: center; width: 23px; height: 23px; background: #ebf1e9; border: 1px solid var(--line); border-radius: 6px; font: bold 15px var(--mono); }
    .separator { color: #a4b2a7; }
    .environment { margin-left: auto; color: #445e4b; background: #eef4eb; border: 1px solid var(--line); border-radius: 5px; padding: 3px 9px; font: 12px var(--mono); }
    .content { max-width: 1440px; margin: 0 auto; padding: 32px clamp(18px, 3.6vw, 58px) 48px; }
    .page-head, .section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .page-head { margin-bottom: 24px; flex-wrap: wrap; }
    h1 { font-size: clamp(30px, 3.3vw, 44px); letter-spacing: -.055em; line-height: 1.12; font-weight: 630; }
    .actions, .search-box, .tags { display: flex; align-items: center; gap: 8px; }
    .control { padding: 7px 12px; background: #fff; border: 1px solid #c9d4cc; border-radius: 6px; color: var(--ink); cursor: pointer; font-size: 12px; font-weight: 600; }
    .control:hover { border-color: #799080; background: #f4f8f2; }
    .live { display: inline-flex; align-items: center; gap: 7px; }
    .dot { width: 7px; height: 7px; border-radius: 50%; background: #27804b; }
    .live[aria-pressed="false"] .dot { background: #88908a; }
    .summary-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(225px, 1fr); gap: 16px; }
    .panel, .metrics { border: 1px solid var(--line); border-radius: 12px; background: #fff; }
    .panel h2 { font-size: 16px; font-weight: 650; letter-spacing: -.025em; }
    .activity, .runtime { padding: 22px 24px; }
    .activity { background: #203e31; color: #f3faef; border-color: #203e31; }
    .activity h2 { color: #daeadb; }
    .total { margin: 8px 0 16px; font-size: 46px; line-height: 1; letter-spacing: -.055em; font-variant-numeric: tabular-nums; }
    .total span { font-size: 14px; color: #d3e6d5; letter-spacing: 0; }
    .distribution { display: flex; height: 9px; border-radius: 2px; overflow: hidden; background: repeating-linear-gradient(115deg, #365443 0 3px, #425e4c 3px 5px); }
    .series-requests { background: #c1e990; }
    .series-queries { background: #76b79e; }
    .series-logs { background: #99b6e3; }
    .series-other { background: #dfbd85; }
    .legend { display: flex; flex-wrap: wrap; gap: 7px 18px; margin-top: 12px; font-size: 11px; color: #e9f0e7; }
    .legend div, .legend dt { display: flex; gap: 5px; align-items: center; }
    .legend i { width: 6px; height: 6px; border-radius: 2px; }
    .legend dd, .facts dd, .mono, .count, .ids, .tags { font-family: var(--mono); }
    .facts { margin-top: 12px; }
    .facts div { display: flex; align-items: baseline; justify-content: space-between; gap: 15px; padding: 8px 0; border-bottom: 1px solid #e6ece6; font-size: 13px; }
    .facts div:last-child { padding-bottom: 0; border-bottom: 0; }
    .facts dt, small, .count, .detail time, .empty p { color: var(--muted); }
    .facts dd { font-size: 12px; text-align: right; overflow-wrap: anywhere; }
    .metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 16px 0 24px; padding: 18px 0; }
    .metrics > div { padding: 0 24px; border-right: 1px solid var(--line); }
    .metrics > div:last-child { border: 0; }
    .metrics h2 { font-size: 12px; font-weight: 600; color: var(--muted); }
    .metrics strong { display: block; font-size: 25px; font-weight: 620; letter-spacing: -.045em; margin: 3px 0 1px; }
    small, .count, .detail time { font-size: 12px; }
    .section-head { padding: 16px 22px; border-bottom: 1px solid var(--line); }
    .text-link, .pager a, .back, .filter a { font-size: 12px; font-weight: 620; }
    .text-link { text-decoration: none; }
    .text-link:hover, .trace a:hover { text-decoration: underline; }
    .text-link span { margin-left: 4px; }
    .allocation-note { padding: 8px 22px; color: var(--muted); font-size: 12px; }
    .scroll-table { overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; font-size: 13px; table-layout: fixed; }
    th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); background: #f8faf7; }
    th, td { padding: 11px 12px; border-bottom: 1px solid #e5eae4; }
    tr:last-child td { border: 0; }
    th:first-child, td:first-child { padding-left: 22px; width: 49%; }
    th:last-child, td:last-child { padding-right: 22px; text-align: right; }
    .row-link { display: block; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; color: var(--ink); font: 12px var(--mono); text-decoration: none; padding: 4px 0; }
    tr:hover td { background: #f5f8f2; }
    tr:hover .row-link { text-decoration: underline; }
    td.mono { font-size: 11px; }
    .tags { flex-wrap: wrap; gap: 4px; }
    .tag { border: 1px solid #d8e2d9; background: #eff5ef; color: #354e3c; border-radius: 4px; padding: 2px 6px; font-size: 10px; max-width: 100%; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; }
    .empty { padding: 34px 16px; text-align: center; }
    .empty-mark { display: inline-grid; place-items: center; width: 34px; height: 34px; margin-bottom: 8px; font-size: 24px; border: 1px solid var(--line); background: #f3f7f0; border-radius: 8px; color: var(--accent); }
    .empty h3 { font-size: 14px; }
    .empty p { font-size: 13px; margin-top: 3px; }
    .filter { margin-bottom: 16px; }
    .filter label, .detail h4 { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 650; }
    input[type=search] { min-width: 0; width: min(100%, 340px); border: 1px solid #b9c7bd; border-radius: 6px; padding: 8px 12px; font-size: 13px; background: #fff; color: var(--ink); }
    .pager { display: flex; gap: 20px; justify-content: flex-end; padding: 14px 22px; border-top: 1px solid var(--line); }
    .back { display: inline-block; margin-bottom: 10px; }
    .detail-body { padding: 20px 22px; }
    .detail h3 { font: 600 18px/1.45 var(--mono); margin-bottom: 10px; overflow-wrap: anywhere; }
    .ids { max-width: 620px; margin: 18px 0; }
    pre { max-height: 40rem; overflow: auto; margin: 0; border-radius: 8px; background: #162c23; color: #e0ece2; padding: 16px; font: 12px/1.7 var(--mono); }
    .trace { margin-top: 20px; }
    .trace ol { list-style: none; padding: 6px 22px; }
    .trace li { display: flex; gap: 16px; padding: 10px 0; align-items: baseline; border-bottom: 1px solid var(--line); }
    .trace li:last-child { border: 0; }
    .trace .mono { font-size: 11px; color: var(--muted); }
    .trace a { display: block; font: 13px var(--mono); text-decoration: none; overflow-wrap: anywhere; }
    .trace [aria-current] { color: var(--ink); font-weight: bold; }
    .paused { margin-bottom: 16px; padding: 9px 14px; background: #fff5da; color: #695321; border: 1px solid #e3d3a9; border-radius: 6px; font-size: 13px; }
    .notice { position: fixed; bottom: 20px; right: 20px; max-width: min(90vw, 400px); padding: 12px 18px; background: #372c25; color: white; border-radius: 8px; font-size: 13px; }
    @media (max-width: 1050px) {
        .shell { grid-template-columns: 184px minmax(0, 1fr); }
        .content, .topbar { padding-inline: 24px; }
        .activity, .runtime, .metrics > div { padding-inline: 16px; }
    }
    @media (max-width: 760px) {
        .shell { display: block; }
        .rail { padding: 0 16px 10px; }
        .brand { height: 46px; margin: 0; font-size: 18px; }
        .signal { width: 26px; height: 26px; }
        .rail nav { display: none; }
        .mobile-nav { display: flex; align-items: center; gap: 8px; }
        .mobile-nav select, .mobile-nav button { font: inherit; font-size: 13px; color: #efffe6; background: #244233; border: 1px solid #516f5f; border-radius: 6px; padding: 6px 10px; }
        .mobile-nav select { flex: 1; }
        .topbar { min-height: 44px; }
        .content, .topbar { padding-inline: 16px; }
        .content { padding-top: 22px; }
        .summary-grid { grid-template-columns: 1fr; gap: 12px; }
        .metrics > div { padding-inline: 12px; }
        .metrics strong { font-size: 21px; }
        .metrics small { font-size: 11px; }
        .section-head, .detail-body { padding: 14px; }
        table { min-width: 540px; }
        .page-head { margin-bottom: 16px; }
        .ids div { flex-wrap: wrap; gap: 5px; }
    }
</style>
