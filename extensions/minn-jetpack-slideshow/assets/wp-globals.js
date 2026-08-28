/**
 * The three small browser globals the slideshow's view script expects the
 * page to provide: a DOM-ready helper, a translation API (the site carries
 * no translations, so strings pass through), and HTML escaping. Defined
 * only when absent, so a page that already carries them is left alone.
 */
(function (root) {
    'use strict';
    var wp = root.wp = root.wp || {};

    if (!wp.domReady) {
        wp.domReady = function (callback) {
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                callback();
                return;
            }
            document.addEventListener('DOMContentLoaded', callback);
        };
    }

    if (!wp.i18n) {
        var sprintf = function (format) {
            var args = Array.prototype.slice.call(arguments, 1);
            var next = 0;
            return String(format).replace(/%(\d+\$)?([sdf])/g, function (match, position, type) {
                var value = position ? args[parseInt(position, 10) - 1] : args[next++];
                if (value === undefined) {
                    return '';
                }
                if (type === 'd') {
                    return String(parseInt(value, 10));
                }
                if (type === 'f') {
                    return String(parseFloat(value));
                }
                return String(value);
            });
        };
        wp.i18n = {
            __: function (text) { return text; },
            _x: function (text) { return text; },
            _n: function (single, plural, number) { return number === 1 ? single : plural; },
            _nx: function (single, plural, number) { return number === 1 ? single : plural; },
            isRTL: function () { return document.documentElement.dir === 'rtl'; },
            sprintf: sprintf,
            setLocaleData: function () {},
            hasTranslation: function () { return false; }
        };
    }

    if (!wp.escapeHtml) {
        var escapeAmpersand = function (value) {
            return String(value).replace(/&(?!([a-z0-9]+|#[0-9]+|#x[a-f0-9]+);)/gi, '&amp;');
        };
        var escapeLessThan = function (value) { return String(value).replace(/</g, '&lt;'); };
        var escapeQuotationMark = function (value) { return String(value).replace(/"/g, '&quot;'); };
        wp.escapeHtml = {
            escapeAmpersand: escapeAmpersand,
            escapeLessThan: escapeLessThan,
            escapeQuotationMark: escapeQuotationMark,
            escapeAttribute: function (value) { return escapeLessThan(escapeAmpersand(escapeQuotationMark(value))); },
            escapeHTML: function (value) { return escapeLessThan(escapeAmpersand(value)); },
            escapeEditableHTML: function (value) { return escapeLessThan(String(value).replace(/&/g, '&amp;')); },
            isValidAttributeName: function (name) { return !/[- "'>\/=]/.test(name); }
        };
    }
})(window);
