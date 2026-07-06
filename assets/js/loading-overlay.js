/**
 * Full-screen loading overlay (shared with Picot AIO Optimizer layout).
 */
(function (window) {
    'use strict';

    var OVERLAY_ID = 'picot-ai-seo-writer-overlay';
    var overlayConfig = window.picotSeoWritingOverlayStrings || {};
    var DEFAULT_SUBMESSAGE = overlayConfig.defaultSubmessage || 'AIが処理を実行しています。しばらくお待ちください...';
    var DEFAULT_MESSAGE = overlayConfig.defaultMessage || '処理中...';

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function spinnerHtml() {
        return '' +
            '<div class="picot-spinner-container" style="position:relative; width:100px; height:100px; margin-bottom:30px;">' +
            '<div class="picot-spinner-outer" style="position:absolute; top:0; left:0; width:100%; height:100%; border:4px solid transparent; border-top-color:#3b82f6; border-radius:50%; animation:picot-spin 1.5s linear infinite;"></div>' +
            '<div class="picot-spinner-inner" style="position:absolute; top:15px; left:15px; width:70px; height:70px; border:4px solid transparent; border-top-color:#60a5fa; border-radius:50%; animation:picot-spin-reverse 1s linear infinite;"></div>' +
            '<div class="picot-spinner-center" style="position:absolute; top:35px; left:35px; width:30px; height:30px; background:#3b82f6; border-radius:50%; box-shadow:0 0 20px #3b82f6; animation:picot-pulse 2s ease-in-out infinite;"></div>' +
            '</div>';
    }

    function buildOverlay(message, submessage) {
        var overlay = document.createElement('div');
        overlay.id = OVERLAY_ID;
        overlay.innerHTML =
            spinnerHtml() +
            '<div class="picot-ai-seo-writer-overlay-message" style="font-size:24px; font-weight:600; letter-spacing:-0.025em; margin-bottom:10px; text-align:center;">' +
            escapeHtml(message) +
            '</div>' +
            '<div class="picot-ai-seo-writer-overlay-submessage" style="font-size:14px; color:rgba(255,255,255,0.6); font-weight:400; text-align:center;">' +
            escapeHtml(submessage) +
            '</div>';
        return overlay;
    }

    function show(message, submessage) {
        var msg = message || DEFAULT_MESSAGE;
        var sub = submessage || DEFAULT_SUBMESSAGE;
        var existing = document.getElementById(OVERLAY_ID);

        if (existing) {
            var messageEl = existing.querySelector('.picot-ai-seo-writer-overlay-message');
            var submessageEl = existing.querySelector('.picot-ai-seo-writer-overlay-submessage');
            if (messageEl) {
                messageEl.textContent = msg;
            }
            if (submessageEl) {
                submessageEl.textContent = sub;
            }
            existing.classList.add('active');
            document.body.style.overflow = 'hidden';
            return;
        }

        var overlay = buildOverlay(msg, sub);
        document.body.appendChild(overlay);
        overlay.offsetHeight;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function hide() {
        var overlay = document.getElementById(OVERLAY_ID);
        if (!overlay) {
            document.body.style.overflow = '';
            return;
        }

        overlay.style.opacity = '0';
        window.setTimeout(function () {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            document.body.style.overflow = '';
        }, 300);
    }

    window.PicotSeoWritingOverlay = {
        show: show,
        hide: hide,
    };
})(window);
