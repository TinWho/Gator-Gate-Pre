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

add_filter( 'bbp_use_wp_editor', '__return_true' );

add_filter( 'user_can_richedit', function ( $can ) {
    if ( function_exists( 'is_bbpress' ) && is_bbpress() ) {
        return true;
    }
    return $can;
} );

function gator_gate_enable_bbpress_visual_editor( $args = array() ) {
    $args['tinymce']        = true;
    $args['teeny']          = false;
    $args['quicktags']      = true;
    $args['default_editor'] = 'tinymce';
    return $args;
}

add_filter(
    'bbp_after_get_the_content_parse_args',
    'gator_gate_enable_bbpress_visual_editor'
);

add_filter( 'wp_editor_settings', function ( $settings, $editor_id ) {
    if ( ! in_array( $editor_id, array( 'bbp_topic_content', 'bbp_reply_content' ), true ) ) {
        return $settings;
    }
    $settings['tinymce']        = true;
    $settings['quicktags']      = true;
    $settings['teeny']          = false;
    $settings['default_editor'] = 'tinymce';
    return $settings;
}, 20, 2 );

add_filter( 'tiny_mce_before_init', function ( $init, $editor_id ) {
    if ( ! in_array( $editor_id, array( 'bbp_topic_content', 'bbp_reply_content' ), true ) ) {
        return $init;
    }
    $init['block_formats'] = 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6';
    return $init;
}, 20, 2 );

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
    echo <<<'JS'
tinymce.PluginManager.add("bbp_pre", function (ed) {
    ed.addButton("bbp_pre", {
        text: "Pre",
        tooltip: "Paste preformatted text",
        onclick: function () {
            if (window.bbpOpenPrePanel) window.bbpOpenPrePanel();
        }
    });

    function inPre() {
        try {
            return !!(ed.selection && ed.dom.getParent(ed.selection.getNode(), "pre"));
        } catch (err) {
            return false;
        }
    }
    function open() {
        if (window.bbpOpenPrePanel) window.bbpOpenPrePanel();
    }
    function isPasteKey(ev) {
        if (!ev || !(ev.ctrlKey || ev.metaKey) || ev.altKey || ev.shiftKey) return false;
        return String(ev.key || "").toLowerCase() === "v" || ev.keyCode === 86 || ev.which === 86;
    }
    function block(ev) {
        ev.preventDefault();
        ev.stopPropagation();
        if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
    }
    function bindFrame() {
        var win = ed.getWin && ed.getWin();
        var doc = ed.getDoc && ed.getDoc();
        if (!win || !doc || win._bbpPre) return;
        win._bbpPre = true;
        win.addEventListener("keydown", function (ev) {
            if (!isPasteKey(ev) || !inPre()) return;
            block(ev);
            open();
        }, true);
        win.addEventListener("paste", function (ev) {
            if (!inPre()) return;
            block(ev);
            open();
        }, true);
        doc.addEventListener("paste", function (ev) {
            if (!inPre()) return;
            block(ev);
            open();
        }, true);
    }

    ed.on("PastePreProcess", function (e) {
        if (!inPre()) return;
        e.content = "";
        if (e.preventDefault) e.preventDefault();
        open();
    });
    ed.on("PastePostProcess", function () {
        if (!inPre()) return;
        if (ed.undoManager && ed.undoManager.hasUndo && ed.undoManager.hasUndo()) {
            ed.undoManager.undo();
        }
        open();
    });
    ed.on("BeforeExecCommand", function (ev) {
        var cmd = String(ev.command || "");
        if (!inPre()) return;
        if (/^mcePaste/i.test(cmd) || cmd === "Paste") {
            block(ev);
            open();
            return false;
        }
    }, true);
    ed.on("init", bindFrame);
    bindFrame();
    if (window.bbpGuardPre) window.bbpGuardPre(ed);
});
JS;
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
        function inPanel(node) {
            return !!(node && panel.contains(node));
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
            if (!e || e._bbpPreFmt) return;
            e._bbpPreFmt = true;
            e.on('BeforeExecCommand', function (ev) {
                var cmd = String(ev.command || '');
                var val = String(ev.value || '');
                if (!inPre(e)) return;
                if (cmd === 'FormatBlock' || cmd === 'mceToggleFormat' || cmd === 'mceBlockQuote' || cmd === 'FontName' || cmd === 'FontSize' || /^h[1-6]$/i.test(val)) {
                    ev.preventDefault();
                    ev.stopImmediatePropagation();
                    return false;
                }
            }, true);
            e.on('NodeChange click', function () {
                dimFormat(e, inPre(e));
            });
        }
        document.addEventListener('mousedown', function (ev) {
            if (inPanel(ev.target)) return;
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
        window.bbpGuardPre = guard;
        if (window.tinymce && tinymce.on) tinymce.on('AddEditor', function (evt) { guard(evt.editor); });
        boot();

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
