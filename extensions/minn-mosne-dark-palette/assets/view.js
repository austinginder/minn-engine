/**
 * The dark-palette toggle without the block interactivity runtime: the
 * same three modes (light, dark, OS auto), the same stored preference,
 * the same button class, label, and document attribute the plugin's view
 * script set, driven by the context the item carries in its markup.
 */
(function () {
    'use strict';
    var STORAGE = 'mosne-dark-palette';
    var SUBMENU = ' wp-block-navigation-submenu__toggle';

    function read() {
        try {
            return window.localStorage.getItem(STORAGE);
        } catch (error) {
            return null;
        }
    }

    function store(mode) {
        try {
            window.localStorage.setItem(STORAGE, mode);
        } catch (error) {
            console.error(error.message);
        }
    }

    function setup(wrapper) {
        var context;
        try {
            context = JSON.parse(wrapper.getAttribute('data-wp-context') || '{}');
        } catch (error) {
            context = {};
        }
        var labels = context.labels || { auto: 'OS auto', light: 'Light', dark: 'Dark' };
        var hasAuto = context.hasAuto !== false;
        var button = wrapper.querySelector('button');
        var label = wrapper.querySelector('[data-wp-bind--aria-label]');
        var mode = context.mode || 'auto';

        function apply(next) {
            mode = next;
            var dark = next === 'dark' || (next === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
            if (button) {
                button.className = 'has-icon--' + next + SUBMENU;
            }
            if (label) {
                label.setAttribute('aria-label', labels[next] || next);
            }
            store(next);
        }

        function toggle() {
            if (mode === 'light') {
                apply('dark');
            } else if (mode === 'dark') {
                apply(hasAuto ? 'auto' : 'light');
            } else {
                apply('light');
            }
        }

        var initial = read() || mode;
        apply(initial === 'dark' ? 'dark' : initial === 'auto' ? 'auto' : 'light');

        wrapper.addEventListener('click', function (event) {
            event.preventDefault();
            toggle();
        });
        wrapper.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggle();
            }
        });
    }

    function init() {
        var items = document.querySelectorAll('[data-wp-interactive="mosne/dark-palette"]');
        for (var i = 0; i < items.length; i++) {
            setup(items[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
