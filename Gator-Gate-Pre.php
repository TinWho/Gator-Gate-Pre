/*
 * Code Snippet:       Gator-Gate-Pre for bbPress
 * Description:        A simple and robust preformatted code block
 * Version:            0.0.1-Alpha
 * AUTHOR:             Tin Who (https://tinfoilwho.com)
 * License:            GPL-2.0-or-later
 *
 * AI-generated/AI-assisted code provided AS-IS.
 * User assumes all risk and responsibility for use.
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'bbp_theme_before_topic_form_content', 'gator_gate_pre_box' );
add_action( 'bbp_theme_before_reply_form_content', 'gator_gate_pre_box' );

function gator_gate_pre_box() {
    ?>
    <p class="gator-pre-controls">
        <button type="button" class="button" id="gator-pre-toggle">Paste preformatted text</button>
    </p>
    <div id="gator-pre-panel" style="display:none;margin:0 0 1em;">
        <textarea id="gator-pre-input" rows="8" style="width:100%;font-family:monospace;white-space:pre;"></textarea>
        <p>
            <button type="button" class="button" id="gator-pre-insert">Insert into post</button>
            <button type="button" class="button" id="gator-pre-cancel">Cancel</button>
        </p>
    </div>
    <script>
    (function () {
        var toggle = document.getElementById('gator-pre-toggle');
        var panel  = document.getElementById('gator-pre-panel');
        var input  = document.getElementById('gator-pre-input');
        var insert = document.getElementById('gator-pre-insert');
        var cancel = document.getElementById('gator-pre-cancel');
        if (!toggle || !panel || !input) return;

        function field() {
            return document.getElementById('bbp_topic_content')
                || document.getElementById('bbp_reply_content');
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        toggle.addEventListener('click', function () {
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            if (panel.style.display === 'block') input.focus();
        });

        cancel.addEventListener('click', function () {
            panel.style.display = 'none';
            input.value = '';
        });

        insert.addEventListener('click', function () {
            var raw = input.value;
            if (!raw) return;

            var html = '<pre class="gator-pre">' + escapeHtml(raw) + '</pre>';
            var ta = field();

            if (window.tinyMCE) {
                var ed = tinyMCE.get('bbp_topic_content') || tinyMCE.get('bbp_reply_content');
                if (ed) {
                    ed.execCommand('mceInsertContent', false, html);
                    panel.style.display = 'none';
                    input.value = '';
                    return;
                }
            }

            if (ta) {
                ta.value += (ta.value ? '\n\n' : '') + html;
            }

            panel.style.display = 'none';
            input.value = '';
        });
    })();
    </script>
    <?php
}

add_action( 'wp_head', function () {
    if ( ! function_exists( 'is_bbpress' ) || ! is_bbpress() ) {
        return;
    }
    echo '<style>pre.gator-pre{white-space:pre;font-family:monospace;overflow-x:auto;}</style>';
} );
