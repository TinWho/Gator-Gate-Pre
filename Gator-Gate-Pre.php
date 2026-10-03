/*
 * Code Snippet:       Gator-Gate-Pre for bbPress
 * Description:        A simple and robust preformatted code block
 * Version:            0.0.2-Alpha
 * AUTHOR:             Tin Who (https://tinfoilwho.com)
 * License:            GPL-2.0-or-later
 *
 * AI-generated/AI-assisted code provided AS-IS.
 * User assumes all risk and responsibility for use.
 */



if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'bbp_after_get_the_content_parse_args', function ( $args ) {
    $args['tinymce']   = true;
    $args['quicktags'] = true;
    $args['teeny']     = false;
    return $args;
} );

add_filter( 'mce_buttons', function ( $buttons ) {
    if ( function_exists( 'is_bbpress' ) && is_bbpress() && ! in_array( 'bbp_pre', $buttons, true ) ) {
        $buttons[] = 'bbp_pre';
    }
    return $buttons;
} );

add_filter( 'mce_external_plugins', function ( $plugins ) {
    if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
        $plugins['bbp_pre'] = admin_url( 'admin-ajax.php?action=bbp_pre_plugin' );
    }
    return $plugins;
} );

add_action( 'wp_ajax_bbp_pre_plugin', 'bbp_pre_plugin_js' );
add_action( 'wp_ajax_nopriv_bbp_pre_plugin', 'bbp_pre_plugin_js' );
function bbp_pre_plugin_js() {
    nocache_headers();
    header( 'Content-Type: application/javascript; charset=UTF-8' );
    echo 'tinymce.PluginManager.add("bbp_pre",function(ed){ed.addButton("bbp_pre",{text:"Pre",tooltip:"Paste preformatted text",onclick:function(){if(window.bbpOpenPrePanel)window.bbpOpenPrePanel();}});});';
    exit;
}

add_action( 'bbp_theme_before_topic_form_content', 'bbp_pre_paste_box' );
add_action( 'bbp_theme_before_reply_form_content', 'bbp_pre_paste_box' );
function bbp_pre_paste_box() {
    static $done = false;
    if ( $done ) {
        return;
    }
    $done = true;
    ?>
    <div id="bbp-pre-panel" hidden>
        <textarea id="bbp-pre-input" rows="8"></textarea>
        <p>
            <button type="button" class="button" id="bbp-pre-insert">Insert into post</button>
            <button type="button" class="button" id="bbp-pre-cancel">Cancel</button>
        </p>
    </div>
    <script>
    (function () {
        var panel = document.getElementById('bbp-pre-panel'),
            input = document.getElementById('bbp-pre-input'),
            loaded = null;
        if (!panel || !input) return;

        function ed() {
            return window.tinyMCE && (tinyMCE.get('bbp_topic_content') || tinyMCE.get('bbp_reply_content'));
        }
        function inPre(e, node) {
            try {
                return !!(e && e.selection && e.dom.getParent(node || e.selection.getNode(), 'pre'));
            } catch (err) {
                return false;
            }
        }
        function preNode(e) {
            return inPre(e) ? e.dom.getParent(e.selection.getNode(), 'pre') : null;
        }
        function esc(s) {
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
        function plain(s) {
            return String(s || '')
                .replace(/^\s*<pre\b[^>]*>/i, '')
                .replace(/<\/pre>\s*$/i, '')
                .replace(/<\/?pre\b[^>]*>/gi, '');
        }
        function clearBuffer() {
            if (!navigator.clipboard || !navigator.clipboard.writeText) return;
            navigator.clipboard.writeText('').catch(function () {});
        }
        function close() {
            panel.hidden = true;
            input.value = '';
            loaded = null;
        }
        window.bbpOpenPrePanel = function () {
            var e = ed();
            loaded = preNode(e);
            input.value = loaded ? (loaded.innerText || loaded.textContent || '') : '';
            panel.hidden = false;
            input.focus();
        };
        function dimFormat(e, locked) {
            var root = e.getContainer && e.getContainer();
            if (!root) return;
            var boxes = root.querySelectorAll('.mce-listbox');
            for (var i = 0; i < boxes.length; i++) {
                boxes[i].style.pointerEvents = locked ? 'none' : '';
                boxes[i].style.opacity = locked ? '0.4' : '';
            }
        }
        function guard(e) {
            if (!e || e._bbpPre) return;
            e._bbpPre = true;
            e.on('keydown', function (ev) {
                if ((ev.ctrlKey || ev.metaKey) && String(ev.key).toLowerCase() === 'v' && inPre(e)) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    clearBuffer();
                    window.bbpOpenPrePanel();
                }
            });
            e.on('paste', function (ev) {
                if (!inPre(e)) return;
                ev.preventDefault();
                ev.stopPropagation();
                clearBuffer();
                window.bbpOpenPrePanel();
            });
            e.on('contextmenu', function (ev) {
                if (!inPre(e)) return;
                clearBuffer();
            });
            e.on('BeforeExecCommand', function (ev) {
                var cmd = String(ev.command || '');
                var val = String(ev.value || '');
                if (!inPre(e)) return;
                if (/^mcePaste/i.test(cmd) || cmd === 'Paste') {
                    ev.preventDefault();
                    ev.stopImmediatePropagation();
                    clearBuffer();
                    window.bbpOpenPrePanel();
                    return false;
                }
                if (cmd === 'FormatBlock' || cmd === 'mceToggleFormat' || cmd === 'mceBlockQuote' || cmd === 'FontName' || cmd === 'FontSize' || /^h[1-6]$/i.test(val)) {
                    ev.preventDefault();
                    ev.stopImmediatePropagation();
                    return false;
                }
            });
            e.on('NodeChange click', function () {
                dimFormat(e, inPre(e));
            });
        }
        document.addEventListener('keydown', function (ev) {
            if (!(ev.ctrlKey || ev.metaKey) || String(ev.key).toLowerCase() !== 'v') return;
            if (ev.target === input) {
                setTimeout(clearBuffer, 0);
                return;
            }
            var e = ed();
            if (!e || !inPre(e)) return;
            ev.preventDefault();
            ev.stopPropagation();
            clearBuffer();
            window.bbpOpenPrePanel();
        }, true);
        document.addEventListener('contextmenu', function () {
            var e = ed();
            if (!e || !inPre(e)) return;
            clearBuffer();
        }, true);
        document.addEventListener('mousedown', function (ev) {
            var e = ed();
            if (!e || !inPre(e) || !ev.target.closest) return;
            var box = ev.target.closest('.mce-listbox');
            var item = ev.target.closest('.mce-menu-item');
            var label = item ? (item.textContent || '').replace(/\s+/g, ' ').trim() : '';
            var heading = item && /heading\s*[1-6]|^h[1-6]$/i.test(label);
            if (!box && !heading) return;
            ev.preventDefault();
            ev.stopPropagation();
            if (box) dimFormat(e, true);
        }, true);
        function boot() { guard(ed()); }
        if (window.tinymce && tinymce.on) tinymce.on('AddEditor', function (evt) { guard(evt.editor); });
        boot();
        setInterval(boot, 500);

        document.getElementById('bbp-pre-cancel').onclick = close;
        document.getElementById('bbp-pre-insert').onclick = function () {
            var text = plain(input.value);
            if (!text) return;
            var e = ed();
            if (e) {
                var target = (loaded && loaded.parentNode) ? loaded : preNode(e);
                if (target) {
                    e.undoManager.transact(function () {
                        e.dom.setHTML(target, esc(text));
                    });
                } else {
                    e.execCommand('mceInsertContent', false, '<pre>' + esc(text) + '</pre>');
                }
            } else {
                var ta = document.getElementById('bbp_topic_content') || document.getElementById('bbp_reply_content');
                if (ta) ta.value += (ta.value ? '\n\n' : '') + '<pre>' + esc(text) + '</pre>';
            }
            clearBuffer();
            close();
        };
    })();
    </script>
    <?php
}

add_filter( 'bbp_kses_allowed_tags', function ( $tags ) {
    $tags['pre'] = array();
    return $tags;
} );
