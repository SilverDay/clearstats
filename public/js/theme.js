/*
 * SPDX-FileCopyrightText: Klaus-E. Klingner (SilverDay Media)
 * SPDX-License-Identifier: MIT
 *
 * Dashboard theme preference. This is first-party UI state only: it stores a
 * theme choice, never a visitor identifier. The public tracker (js/track.js)
 * remains free of cookies and browser storage.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'clearstats-theme';
    var MODES = ['system', 'light', 'dark'];

    var ICONS = {
        system: '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
        light: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
        dark: '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>'
    };

    var LABELS = { system: 'System theme', light: 'Light theme', dark: 'Dark theme' };

    var systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)');

    function readMode() {
        try {
            var stored = window.localStorage.getItem(STORAGE_KEY);
            return MODES.indexOf(stored) === -1 ? 'system' : stored;
        } catch (error) {
            return 'system';
        }
    }

    function persistMode(mode) {
        try {
            window.localStorage.setItem(STORAGE_KEY, mode);
        } catch (error) {
            /* Storage unavailable (private mode, blocked). Theme still applies for this page. */
        }
    }

    function resolve(mode) {
        if (mode === 'light' || mode === 'dark') {
            return mode;
        }

        return systemPrefersDark.matches ? 'dark' : 'light';
    }

    var mode = readMode();
    var buttons = {};

    function apply(next) {
        mode = next;
        document.documentElement.setAttribute('data-theme', resolve(next));
        MODES.forEach(function (candidate) {
            buttons[candidate].setAttribute('aria-pressed', candidate === next ? 'true' : 'false');
        });
    }

    var control = document.createElement('div');
    control.className = 'theme-switch';
    control.setAttribute('role', 'group');
    control.setAttribute('aria-label', 'Theme');

    MODES.forEach(function (candidate) {
        var button = document.createElement('button');
        button.type = 'button';
        button.title = LABELS[candidate];
        button.setAttribute('aria-label', LABELS[candidate]);
        button.setAttribute('aria-pressed', 'false');
        button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + ICONS[candidate] + '</svg>';
        button.addEventListener('click', function () {
            persistMode(candidate);
            apply(candidate);
        });
        buttons[candidate] = button;
        control.appendChild(button);
    });

    (document.getElementById('clearstats-theme-slot') || document.body).appendChild(control);
    apply(mode);

    systemPrefersDark.addEventListener('change', function () {
        if (mode === 'system') {
            apply('system');
        }
    });
}());
