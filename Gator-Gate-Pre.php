/*
 * LAST SINGLE FILE CODE SNIPPET VERSION  
 * Code Snippet:      Gator-Gate-Pre for bbPress
 * Description:       A simple and robust preformatted code block
 * Plugin Name:       Gator Gate Pre for bbPress
 * Plugin URI:        https://tinfoilwho.com
 * Version:           0.8.1-Alpha
 * Author:            Tin Who
 * Author URI:        https://tinfoilwho.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gg-pre
 * Domain Path:       /languages
 *
 *
 * AI-generated/AI-assisted code provided AS-IS.
 * User assumes all risk and responsibility for use.
 *
 * How it works
 * ------------
 * Controls the visual editor and Pre button on front-end bbPress topic and
 * reply forms via two checkboxes stored as forum post meta:
 *
 * - Enable Visual Editor
 * - Hide the Code tab (UI only; can only be checked when Visual Editor is on)
 *
 * Unchecking Visual Editor automatically unchecks Hide the Code tab.
 * The Pre button is on by default when the visual editor is enabled.
 * Other plugins can override it with the 'gg_pre_show_pre_button' filter.
 *
 * For example add either to you funtion.php 
*   // Hide the Pre button
*    add_filter( 'gg_pre_show_pre_button', '__return_false' );
*
*   // Show the Pre button (force it on)
*   add_filter( 'gg_pre_show_pre_button', '__return_true' );
 *
 *
 * The admin area is left completely untouched except for the two checkboxes
 * added to the Attributes metabox.
 *
 * Future multi-file plugin conversion notes
 * -----------------------------------------
 * - Move the TinyMCE plugin JS to assets/gg-pre-tinymce.js and enqueue with wp_enqueue_script.
 * - Move the paste-panel markup + script into a template or enqueued file.
 * - Use wp_localize_script for all remaining JS strings.
 * - Add readme.txt, uninstall.php, and proper activation/deactivation hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Block direct access.
}

/* -------------------------------------------------------------------------
 * Constants
 * ------------------------------------------------------------------------- */
define( 'GG_PRE_VERSION', '0.8.1-Alpha' );
define( 'GG_PRE_TEXT_DOMAIN', 'gg-pre' );

/* -------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */

/**
 * Get the current forum ID from the bbPress context.
 *
 * @return int
 */
function gg_pre_get_current_forum_id() {
    if ( function_exists( 'bbp_get_reply_forum_id' ) && bbp_is_reply_edit() ) {
        return (int) bbp_get_reply_forum_id( bbp_get_reply_id() );
    }
    if ( function_exists( 'bbp_get_topic_forum_id' ) && bbp_is_topic_edit() ) {
        return (int) bbp_get_topic_forum_id( bbp_get_topic_id() );
    }
    if ( isset( $_POST['bbp_forum_id'] ) ) {
        return absint( $_POST['bbp_forum_id'] );
    }
    if ( isset( $_POST['bbp_topic_id'] ) && function_exists( 'bbp_get_topic_forum_id' ) ) {
        return (int) bbp_get_topic_forum_id( absint( $_POST['bbp_topic_id'] ) );
    }
    if ( function_exists( 'bbp_get_forum_id' ) ) {
        return (int) bbp_get_forum_id();
    }
    return 0;
}

/**
 * True only on front-end topic or reply forms.
 *
 * @return bool
 */
function gg_pre_is_topic_or_reply_form() {
    if ( ! function_exists( 'is_bbpress' ) || ! is_bbpress() ) {
        return false;
    }
    if ( function_exists( 'bbp_is_topic_edit' ) && bbp_is_topic_edit() ) {
        return true;
    }
    if ( function_exists( 'bbp_is_reply_edit' ) && bbp_is_reply_edit() ) {
        return true;
    }
    if ( function_exists( 'bbp_is_single_forum' ) && bbp_is_single_forum() ) {
        return true;
    }
    if ( function_exists( 'bbp_is_single_topic' ) && bbp_is_single_topic() ) {
        return true;
    }
    return false;
}

/**
 * Get forum flags from post meta.
 *
 * @return array {
 *     @type bool $visual_editor
 *     @type bool $hide_code_tab
 *     @type bool $pre_button
 * }
 */
function gg_pre_get_forum_flags() {
    static $flags = null;
    if ( null !== $flags ) {
        return $flags;
    }

    $flags = array(
        'visual_editor' => false,
        'hide_code_tab' => false,
        'pre_button'    => false,
    );

    $forum_id = gg_pre_get_current_forum_id();
    if ( ! $forum_id ) {
        return $flags;
    }

    $flags['visual_editor'] = (bool) get_post_meta( $forum_id, '_gg_pre_visual_editor', true );

    // Hide Code tab only makes sense when the visual editor is on.
    if ( $flags['visual_editor'] ) {
        $flags['hide_code_tab'] = (bool) get_post_meta( $forum_id, '_gg_pre_hide_code_tab', true );
    }

    // Pre button is on by default when the visual editor is enabled.
    // Other plugins can override via the filter.
    $flags['pre_button'] = (bool) apply_filters(
        'gg_pre_show_pre_button',
        $flags['visual_editor'],
        $forum_id
    );

    return $flags;
}

/**
 * Whether the visual editor should be forced right now.
 *
 * @return bool
 */
function gg_pre_should_force_visual_editor() {
    if ( ! gg_pre_is_topic_or_reply_form() ) {
        return false;
    }
    $flags = gg_pre_get_forum_flags();
    return $flags['visual_editor'];
}

/**
 * Whether the Pre button should be shown right now.
 *
 * @return bool
 */
function gg_pre_should_show_pre_button() {
    if ( ! gg_pre_should_force_visual_editor() ) {
        return false;
    }
    $flags = gg_pre_get_forum_flags();
    return $flags['pre_button'];
}

/* -------------------------------------------------------------------------
 * Admin: checkboxes in the Attributes metabox
 * ------------------------------------------------------------------------- */

add_action( 'bbp_forum_metabox', 'gg_pre_forum_metabox_fields' );

function gg_pre_forum_metabox_fields( $post ) {
    if ( ! current_user_can( 'edit_forum', $post->ID ) ) {
        return;
    }

    $visual = (bool) get_post_meta( $post->ID, '_gg_pre_visual_editor', true );
    $hide   = (bool) get_post_meta( $post->ID, '_gg_pre_hide_code_tab', true );

    wp_nonce_field( 'gg_pre_save_forum_meta', 'gg_pre_forum_nonce' );
    ?>
    <hr />
    <p>
        <label>
            <input type="checkbox" name="gg_pre_visual_editor" id="gg_pre_visual_editor" value="1" <?php checked( $visual ); ?> />
            <?php esc_html_e( 'Enable Visual Editor', GG_PRE_TEXT_DOMAIN ); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="gg_pre_hide_code_tab" id="gg_pre_hide_code_tab" value="1" <?php checked( $hide ); ?> <?php disabled( ! $visual ); ?> />
            <?php esc_html_e( 'Hide the Code tab', GG_PRE_TEXT_DOMAIN ); ?>
        </label>
        <br />
        <span class="description"><?php esc_html_e( 'Only available when the Visual Editor is enabled. This is a UI choice, not a security control.', GG_PRE_TEXT_DOMAIN ); ?></span>
    </p>
    <script>
    (function () {
        var visual = document.getElementById('gg_pre_visual_editor');
        var hide   = document.getElementById('gg_pre_hide_code_tab');
        if (!visual || !hide) return;

        function sync() {
            if (!visual.checked) {
                hide.checked = false;
                hide.disabled = true;
            } else {
                hide.disabled = false;
            }
        }

        visual.addEventListener('change', sync);
        // Run once on load in case of stale state.
        sync();
    })();
    </script>
    <?php
}

add_action( 'bbp_forum_attributes_metabox_save', 'gg_pre_save_forum_meta' );

function gg_pre_save_forum_meta( $forum_id ) {
    if ( ! isset( $_POST['gg_pre_forum_nonce'] ) || ! wp_verify_nonce( $_POST['gg_pre_forum_nonce'], 'gg_pre_save_forum_meta' ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_forum', $forum_id ) ) {
        return;
    }

    $visual = ! empty( $_POST['gg_pre_visual_editor'] ) ? '1' : '';
    $hide   = ! empty( $_POST['gg_pre_hide_code_tab'] ) ? '1' : '';

    // If visual editor is off, force hide-code off as well.
    if ( '' === $visual ) {
        $hide = '';
    }

    update_post_meta( $forum_id, '_gg_pre_visual_editor', $visual );
    update_post_meta( $forum_id, '_gg_pre_hide_code_tab', $hide );
}

/* -------------------------------------------------------------------------
 * Enable Visual Editor
 * ------------------------------------------------------------------------- */

add_filter( 'bbp_use_wp_editor', function ( $use ) {
    return gg_pre_should_force_visual_editor() ? true : $use;
} );

add_filter( 'user_can_richedit', function ( $can ) {
    return gg_pre_should_force_visual_editor() ? true : $can;
} );

function gg_pre_enable_bbpress_visual_editor( $args = array() ) {
    if ( ! gg_pre_should_force_visual_editor() ) {
        return $args;
    }

    $flags = gg_pre_get_forum_flags();

    $args['tinymce']        = true;
    $args['teeny']          = false;
    $args['quicktags']      = ! $flags['hide_code_tab'];
    $args['default_editor'] = 'tinymce';

    return $args;
}
add_filter( 'bbp_after_get_the_content_parse_args', 'gg_pre_enable_bbpress_visual_editor' );

add_filter( 'wp_editor_settings', function ( $settings, $editor_id ) {
    if ( ! gg_pre_should_force_visual_editor() ) {
        return $settings;
    }
    $allowed_ids = array( 'bbp_topic_content', 'bbp_reply_content' );
    if ( ! in_array( $editor_id, $allowed_ids, true ) ) {
        return $settings;
    }

    $flags = gg_pre_get_forum_flags();

    $settings['tinymce']        = true;
    $settings['quicktags']      = ! $flags['hide_code_tab'];
    $settings['teeny']          = false;
    $settings['default_editor'] = 'tinymce';

    return $settings;
}, 20, 2 );

// Remove the standard Preformatted option from the dropdown.
add_filter( 'tiny_mce_before_init', function ( $init, $editor_id ) {
    if ( ! gg_pre_is_topic_or_reply_form() ) {
        return $init;
    }
    $allowed_ids = array( 'bbp_topic_content', 'bbp_reply_content' );
    if ( ! in_array( $editor_id, $allowed_ids, true ) ) {
        return $init;
    }
    $init['block_formats'] = 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6';
    return $init;
}, 20, 2 );

/* -------------------------------------------------------------------------
 * TinyMCE “Pre” button (only when visual editor + pre button are enabled)
 * ------------------------------------------------------------------------- */

add_filter( 'mce_buttons', function ( $buttons ) {
    if ( gg_pre_should_show_pre_button() && ! in_array( 'gg_pre', $buttons, true ) ) {
        $buttons[] = 'gg_pre';
    }
    return $buttons;
} );

add_filter( 'mce_external_plugins', function ( $plugins ) {
    if ( gg_pre_should_show_pre_button() ) {
        $plugins['gg_pre'] = admin_url( 'admin-ajax.php?action=gg_pre_plugin' );
    }
    return $plugins;
} );

add_action( 'wp_ajax_gg_pre_plugin', 'gg_pre_plugin_js' );
add_action( 'wp_ajax_nopriv_gg_pre_plugin', 'gg_pre_plugin_js' );

function gg_pre_plugin_js() {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
        wp_die( esc_html__( 'Invalid request.', GG_PRE_TEXT_DOMAIN ), 400 );
    }

    nocache_headers();
    header( 'Content-Type: application/javascript; charset=UTF-8' );
    header( 'X-Content-Type-Options: nosniff' );

    $button_text = esc_js( __( 'Pre', GG_PRE_TEXT_DOMAIN ) );
    $tooltip     = esc_js( __( 'Paste preformatted text', GG_PRE_TEXT_DOMAIN ) );

    echo <<<JS
tinymce.PluginManager.add("gg_pre", function (ed) {
    ed.addButton("gg_pre", {
        text: "{$button_text}",
        tooltip: "{$tooltip}",
        onclick: function () {
            if (window.ggPreOpenPanel) window.ggPreOpenPanel();
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
        if (window.ggPreOpenPanel) window.ggPreOpenPanel();
    }

    function isPasteKey(ev) {
        if (!ev || !(ev.ctrlKey || ev.metaKey) || ev.altKey || ev.shiftKey) return false;
        return String(ev.key || "").toLowerCase() === "v" || ev.keyCode === 86 || ev.which === 86;
    }

    function block(ev) {
        if (!ev) return;
        ev.preventDefault();
        ev.stopPropagation();
        if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
    }

    function bindFrame() {
        var win = ed.getWin && ed.getWin();
        var doc = ed.getDoc && ed.getDoc();
        if (!win || !doc || win._ggPre) return;
        win._ggPre = true;

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

    if (window.ggPreGuard) window.ggPreGuard(ed);
});
JS;

    exit;
}

/* -------------------------------------------------------------------------
 * Paste panel (only when Pre button is enabled)
 * ------------------------------------------------------------------------- */

add_action( 'bbp_theme_before_topic_form_content', 'gg_pre_paste_box' );
add_action( 'bbp_theme_before_reply_form_content', 'gg_pre_paste_box' );

function gg_pre_paste_box() {
    static $done = false;
    if ( $done || ! gg_pre_should_show_pre_button() ) {
        return;
    }
    $done = true;

    $insert_label = esc_html__( 'Insert Preformatted Text', GG_PRE_TEXT_DOMAIN );
    $cancel_label = esc_html__( 'Cancel', GG_PRE_TEXT_DOMAIN );
    ?>
    <div id="gg-pre-panel" hidden>
        <textarea id="gg-pre-input" rows="8"></textarea>
        <p>
            <button type="button" class="button" id="gg-pre-insert"><?php echo $insert_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
            <button type="button" class="button" id="gg-pre-cancel"><?php echo $cancel_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
        </p>
    </div>
    <script>
    (function () {
        var panel = document.getElementById('gg-pre-panel'),
            input = document.getElementById('gg-pre-input'),
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
                return !!(e && e.selection && e.dom && e.dom.getParent(node || e.selection.getNode(), 'pre'));
            } catch (err) {
                return false;
            }
        }

        function preNode(e) {
            return inPre(e) ? e.dom.getParent(e.selection.getNode(), 'pre') : null;
        }

        function esc(s) {
            return String(s || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
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

        window.ggPreOpenPanel = function () {
            var e = ed();
            if (!e) return;
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
            if (!e || e._ggPreFmt) return;
            e._ggPreFmt = true;

            e.on('BeforeExecCommand', function (ev) {
                var cmd = String(ev.command || '');
                var val = String(ev.value || '');
                if (!inPre(e)) return;
                if (cmd === 'FormatBlock' || cmd === 'mceToggleFormat' || cmd === 'mceBlockQuote' ||
                    cmd === 'FontName' || cmd === 'FontSize' || /^h[1-6]$/i.test(val)) {
                    ev.preventDefault();
                    if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
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

        function boot() { 
            var e = ed();
            if (e) guard(e); 
        }
        window.ggPreGuard = guard;
        if (window.tinymce && tinymce.on) {
            tinymce.on('AddEditor', function (evt) { guard(evt.editor); });
        }
        boot();

        var cancelBtn = document.getElementById('gg-pre-cancel');
        var insertBtn = document.getElementById('gg-pre-insert');
        if (cancelBtn) cancelBtn.onclick = close;
        if (insertBtn) {
            insertBtn.onclick = function () {
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
        }
    })();
    </script>
    <?php
}

/* -------------------------------------------------------------------------
 * Allow <pre> through KSES
 * ------------------------------------------------------------------------- */

add_filter( 'bbp_kses_allowed_tags', function ( $tags ) {
    if ( ! is_array( $tags ) ) {
        $tags = array();
    }
    $tags['pre'] = array();
    return $tags;
} );

// End of Gator Gate Pre (single-file / snippet version)
