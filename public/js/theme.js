/*
 * SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
 * SPDX-License-Identifier: MIT
 *
 * Theme control deliberately uses in-memory DOM state only. ClearStats does
 * not persist UI preferences in cookies, localStorage, or sessionStorage.
 */
(function () {
    'use strict';

    var style = document.createElement('style');
    style.textContent = '\
    :root { color-scheme: dark; }\
    [data-theme="light"] { color-scheme: light; }\
    [data-theme="dark"] { color-scheme: dark; }\
    @media (prefers-color-scheme: light) {\
      :root:not([data-theme="dark"]) { color-scheme: light; }\
    }\
    [data-theme="light"] body, [data-theme="light"] html {\
      background: #f4f7fb !important; color: #182230 !important;\
    }\
    [data-theme="light"] body a:not(.button) { color: #075985 !important; text-decoration: underline; text-underline-offset: 3px; }\
    [data-theme="light"] input, [data-theme="light"] select {\
      background: #ffffff !important; color: #182230 !important; border-color: #9aaabd !important;\
    }\
    [data-theme="light"] .card, [data-theme="light"] .login-panel, [data-theme="light"] .brand-panel, [data-theme="light"] .install-panel {\
      background: #ffffff !important; border-color: #c5d0dc !important; box-shadow: 0 16px 36px rgba(31, 52, 73, .12) !important;\
    }\
    [data-theme="light"] .sidebar { background: #e7edf4 !important; border-color: #c5d0dc !important; }\
    [data-theme="light"] .muted, [data-theme="light"] label, [data-theme="light"] p, [data-theme="light"] .site-meta, [data-theme="light"] .table th, [data-theme="light"] .stat-label { color: #425466 !important; }\
    [data-theme="light"] .title, [data-theme="light"] h1, [data-theme="light"] h2, [data-theme="light"] .site-name, [data-theme="light"] .stat-value, [data-theme="light"] td { color: #182230 !important; }\
    [data-theme="light"] .button { color: #183047 !important; background: #e6eef7 !important; border-color: #9aaabd !important; }\
    [data-theme="light"] .button.primary, [data-theme="light"] button { color: #ffffff !important; background: #075985 !important; border-color: #075985 !important; }\
    [data-theme="light"] .nav a { color: #425466 !important; }\
    [data-theme="light"] .nav a.active, [data-theme="light"] .nav a:hover { color: #182230 !important; background: #d8e3ee !important; border-color: #9aaabd !important; }\
    [data-theme="light"] .install-panel pre { background: #eef3f8 !important; color: #183047 !important; }\
    .clearstats-theme-control { display: inline-flex; align-items: center; gap: 8px; padding: 7px 10px; border: 1px solid rgba(148, 163, 184, .35); border-radius: 10px; background: rgba(9, 15, 25, .88); color: #dbeafe; box-shadow: 0 10px 24px rgba(2, 6, 23, .22); font: 12px/1.2 Inter, "Segoe UI", sans-serif; }\
    .clearstats-theme-control label { color: inherit; font-size: 11px; }\
    .clearstats-theme-control select { width: auto; min-width: 82px; padding: 5px 7px; border: 1px solid rgba(148, 163, 184, .35); border-radius: 7px; background: #172335; color: #f8fafc; font: inherit; }\
    [data-theme="light"] .clearstats-theme-control { background: #ffffff; color: #183047; border-color: #9aaabd; }\
    [data-theme="light"] .clearstats-theme-control select { background: #ffffff !important; color: #183047 !important; border-color: #9aaabd !important; }\
  ';
    document.head.appendChild(style);

    var control = document.createElement('div');
    control.className = 'clearstats-theme-control';
    control.innerHTML = '<label for="clearstats-theme">Theme</label><select id="clearstats-theme" aria-label="Theme"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select>';
    var themeSlot = document.getElementById('clearstats-theme-slot');
    (themeSlot || document.body).appendChild(control);

    var select = control.querySelector('select');
    var systemPreference = window.matchMedia('(prefers-color-scheme: light)');

    function applySystemTheme() {
        document.documentElement.setAttribute('data-theme', systemPreference.matches ? 'light' : 'dark');
    }

    applySystemTheme();

    select.addEventListener('change', function () {
        if (select.value === 'system') {
            applySystemTheme();
        } else {
            document.documentElement.setAttribute('data-theme', select.value);
        }
    });
    systemPreference.addEventListener('change', function () {
        if (select.value === 'system') applySystemTheme();
    });
}());
