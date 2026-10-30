<?php
/**
 * Main plugin class.
 *
 * @package Gator_Gate_Pre
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Gator_Gate_Pre
 */
final class Gator_Gate_Pre {

    /**
     * Singleton instance.
     *
     * @var Gator_Gate_Pre|null
     */
    private static $instance = null;

    /**
     * Cached forum flags.
     *
     * @var array|null
     */
    private static $flags = null;

    /**
     * Get instance.
     *
     * @return Gator_Gate_Pre
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->hooks();
        add_filter( 'tiny_mce_before_init', array( $this, 'add_content_style' ), 30, 2 );
        // Print CSS late in the footer so it wins like a Fluent Snippet
        add_action( 'wp_footer', array( $this, 'print_line_spacing_css' ), 999 );
    }

    /**
     * Register all hooks.
     */
    private function hooks() {
        // Admin metabox.
        add_action( 'bbp_forum_metabox', array( $this, 'forum_metabox_fields' ) );
        add_action( 'bbp_forum_attributes_metabox_save', array( $this, 'save_forum_meta' ) );

        // Visual editor forcing.
        add_filter( 'bbp_use_wp_editor', array( $this, 'filter_use_wp_editor' ) );
        add_filter( 'user_can_richedit', array( $this, 'filter_user_can_richedit' ) );
        add_filter( 'bbp_after_get_the_content_parse_args', array( $this, 'filter_editor_args' ) );
        add_filter( 'wp_editor_settings', array( $this, 'filter_wp_editor_settings' ), 20, 2 );
        add_filter( 'tiny_mce_before_init', array( $this, 'filter_block_formats' ), 20, 2 );

        // Pre button.
        add_filter( 'mce_buttons', array( $this, 'filter_mce_buttons' ) );
        add_filter( 'mce_external_plugins', array( $this, 'filter_mce_external_plugins' ) );

        // Paste panel.
        add_action( 'bbp_theme_before_topic_form_content', array( $this, 'render_paste_panel' ) );
        add_action( 'bbp_theme_before_reply_form_content', array( $this, 'render_paste_panel' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_panel_script' ) );

        // KSES.
        add_filter( 'bbp_kses_allowed_tags', array( $this, 'filter_kses_tags' ) );
    }

    /**
     * Editor line spacing only — tightened, no font-size overrides.
     * Applied only when the visual editor is enabled for this exact forum AND the line-spacing checkbox is on.
     *
     * @param array  $init
     * @param string $editor_id
     * @return array
     */
    public function add_content_style( $init, $editor_id = '' ) {
        if ( is_admin() || ! function_exists( 'is_bbpress' ) || ! is_bbpress() ) {
            return $init;
        }

        $allowed = array( 'bbp_topic_content', 'bbp_reply_content' );
        if ( $editor_id && ! in_array( $editor_id, $allowed, true ) ) {
            return $init;
        }

        // Only on topic/reply forms where the visual editor is enabled for this exact forum
        if ( ! $this->should_force_visual_editor() ) {
            return $init;
        }

        $flags = $this->get_forum_flags();
        if ( empty( $flags['change_line_space'] ) ) {
            return $init;
        }

        $css = '
            /* Overall body spacing inside the visual editor */
            body {
                line-height: 1.15; /* lower = tighter lines */
                margin: 0.5rem 0.4rem 0.4rem 0.4rem; /* top right bottom left – increase first value for more top gap */
                padding: 0; /* no extra padding so rem margins control all spacing */
            }

            /* Paragraph spacing */
            p {
                margin: 0.25rem 0 !important; /* vertical space between paragraphs */
                line-height: 1.15 !important; /* match body line-height for consistency */
            }
            /* First paragraph (pulls content up to reduce top gap) */
            p:first-child {
                margin-top: 0.5rem !important; /* slightly larger top margin so content does not stick to the editor edge */
            }
            p:last-child {
                margin-bottom: 0 !important; /* remove bottom gap on the final paragraph */
            }

            /* Heading spacing */
            h1, h2, h3, h4, h5, h6 {
                margin-top: 0.5rem !important;    /* space above headings */
                margin-bottom: 0.3rem !important; /* space below headings */
                line-height: 1.15 !important;     /* keep headings tight like body text */
            }
            /* First heading (pulls content up to reduce top gap) */
            h1:first-child,
            h2:first-child,
            h3:first-child,
            h4:first-child,
            h5:first-child,
            h6:first-child {
                margin-top: 0.5rem !important; /* same reduced top gap treatment as first paragraph */
            }

            /* Preformatted blocks */
            pre {
                margin: 0.75rem 0 !important; /* space above and below pre blocks */
                line-height: 1.15 !important; /* tighter line-height inside code blocks */
            }
            /* First preformatted block (pulls content up to reduce top gap) */
            pre:first-child {
                margin-top: 0.75rem !important; /* keep the larger top margin for pre even when first */
            }
            pre:last-child {
                margin-bottom: 0.5rem !important; /* modest bottom margin so last pre does not touch the edge */
            }

            /* Line break spacing */
            br {
                margin: 0.25rem 0; /* small space around line breaks */
            }
        ';

        // Optional: collapse extra whitespace so the string stays compact
        $css = preg_replace( '/\s+/', ' ', trim( $css ) );

        if ( ! empty( $init['content_style'] ) ) {
            $init['content_style'] .= $css;
        } else {
            $init['content_style'] = $css;
        }

        return $init;
    }

    /**
     * Print front-end line-spacing CSS directly in the footer.
     * Runs on bbPress pages when the line-spacing option is enabled for the forum.
     * The CSS file is local and trusted. Output is left raw so rules (including negative
     * margins) are identical to Fluent Snippets.
     */
    public function print_line_spacing_css() {
        if ( ! function_exists( 'is_bbpress' ) || ! is_bbpress() ) {
            return;
        }

        $flags = $this->get_forum_flags();
        if ( empty( $flags['change_line_space'] ) ) {
            return;
        }

        $css_file = GATOR_GATE_PRE_PATH . 'assets/css/gator-gate-pre.css';
        if ( ! file_exists( $css_file ) ) {
            return;
        }

        $css = file_get_contents( $css_file );
        if ( ! $css ) {
            return;
        }

        // Local trusted CSS — output raw so selectors and negative margins are preserved exactly.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<style id="gator-gate-pre-line-spacing">' . $css . '</style>';
    }

    /**
     * Get current forum ID from bbPress context.
     *
     * @return int
     */
    private function get_current_forum_id() {
        if ( function_exists( 'bbp_get_reply_forum_id' ) && bbp_is_reply_edit() ) {
            return (int) bbp_get_reply_forum_id( bbp_get_reply_id() );
        }
        if ( function_exists( 'bbp_get_topic_forum_id' ) && bbp_is_topic_edit() ) {
            return (int) bbp_get_topic_forum_id( bbp_get_topic_id() );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Reading bbPress form context only. bbPress verifies the form nonces on submission.
        if ( isset( $_POST['bbp_forum_id'] ) ) {
            return absint( $_POST['bbp_forum_id'] );
        }
        if ( isset( $_POST['bbp_topic_id'] ) && function_exists( 'bbp_get_topic_forum_id' ) ) {
            return (int) bbp_get_topic_forum_id( absint( $_POST['bbp_topic_id'] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

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
    private function is_topic_or_reply_form() {
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
     * @return array
     */
    private function get_forum_flags() {
        if ( null !== self::$flags ) {
            return self::$flags;
        }

        self::$flags = array(
            'visual_editor'     => false,
            'hide_code_tab'     => false,
            'pre_button'        => false,
            'change_line_space' => false,
        );

        $forum_id = $this->get_current_forum_id();
        if ( ! $forum_id ) {
            return self::$flags;
        }

        self::$flags['visual_editor'] = (bool) get_post_meta( $forum_id, '_gator_gate_pre_visual_editor', true );

        if ( self::$flags['visual_editor'] ) {
            self::$flags['hide_code_tab'] = (bool) get_post_meta( $forum_id, '_gator_gate_pre_hide_code_tab', true );

            // Line spacing is off by default; only true when explicitly saved as '1'
            self::$flags['change_line_space'] = (bool) get_post_meta( $forum_id, '_gator_gate_pre_change_line_space', true );
        }

        self::$flags['pre_button'] = (bool) apply_filters(
            'gator_gate_pre_show_pre_button',
            self::$flags['visual_editor'],
            $forum_id
        );

        return self::$flags;
    }

    /**
     * Whether the visual editor should be forced.
     *
     * @return bool
     */
    private function should_force_visual_editor() {
        if ( ! $this->is_topic_or_reply_form() ) {
            return false;
        }
        $flags = $this->get_forum_flags();
        return $flags['visual_editor'];
    }

    /**
     * Whether the Pre button should be shown.
     *
     * @return bool
     */
    private function should_show_pre_button() {
        if ( ! $this->should_force_visual_editor() ) {
            return false;
        }
        $flags = $this->get_forum_flags();
        return $flags['pre_button'];
    }

    /* -------------------------------------------------------------------------
     * Admin checkboxes
     * ------------------------------------------------------------------------- */

    /**
     * Render the forum metabox checkboxes.
     *
     * @param WP_Post $post
     */
    public function forum_metabox_fields( $post ) {
        if ( ! current_user_can( 'edit_forum', $post->ID ) ) {
            return;
        }

        $visual             = (bool) get_post_meta( $post->ID, '_gator_gate_pre_visual_editor', true );
        $hide               = (bool) get_post_meta( $post->ID, '_gator_gate_pre_hide_code_tab', true );
        $line_space         = get_post_meta( $post->ID, '_gator_gate_pre_change_line_space', true );
        $line_space_checked = $visual && ( '1' === $line_space );

        wp_nonce_field( 'gator_gate_pre_save_forum_meta', 'gator_gate_pre_forum_nonce' );
        ?>
        <hr />
        <p>
            <label>
                <input type="checkbox" name="gator_gate_pre_visual_editor" id="gator_gate_pre_visual_editor" value="1" <?php checked( $visual ); ?> />
                <?php esc_html_e( 'Enable Visual Editor', 'gator-gate-pre' ); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="gator_gate_pre_hide_code_tab" id="gator_gate_pre_hide_code_tab" value="1" <?php checked( $hide ); ?> <?php disabled( ! $visual ); ?> />
                <?php esc_html_e( 'Hide the Code tab', 'gator-gate-pre' ); ?>
            </label>
            <br />
            <span class="description"><?php esc_html_e( 'Only available when the Visual Editor is enabled. This is a UI choice, not a security control.', 'gator-gate-pre' ); ?></span>
        </p>
        <p>
            <label>
                <input type="checkbox" name="gator_gate_pre_change_line_space" id="gator_gate_pre_change_line_space" value="1" <?php checked( $line_space_checked ); ?> <?php disabled( ! $visual ); ?> />
                <?php esc_html_e( 'Change line spacing', 'gator-gate-pre' ); ?>
            </label>
            <br />
            <span class="description"><?php esc_html_e( 'Only available when the Visual Editor is enabled. Applies tighter line-height and paragraph/heading margins in the visual editor and on the front end.', 'gator-gate-pre' ); ?></span>
        </p>
        <script>
        (function () {
            var visual    = document.getElementById('gator_gate_pre_visual_editor');
            var hide      = document.getElementById('gator_gate_pre_hide_code_tab');
            var lineSpace = document.getElementById('gator_gate_pre_change_line_space');
            if (!visual || !hide || !lineSpace) return;

            function sync() {
                if (!visual.checked) {
                    hide.checked = false;
                    hide.disabled = true;
                    lineSpace.checked = false;
                    lineSpace.disabled = true;
                } else {
                    hide.disabled = false;
                    lineSpace.disabled = false;
                }
            }

            visual.addEventListener('change', sync);
            sync();
        })();
        </script>
        <?php
    }

    /**
     * Save forum meta from the metabox.
     *
     * @param int $forum_id
     */
    public function save_forum_meta( $forum_id ) {
        if ( ! isset( $_POST['gator_gate_pre_forum_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gator_gate_pre_forum_nonce'] ) ), 'gator_gate_pre_save_forum_meta' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_forum', $forum_id ) ) {
            return;
        }

        $visual     = ! empty( $_POST['gator_gate_pre_visual_editor'] ) ? '1' : '';
        $hide       = ! empty( $_POST['gator_gate_pre_hide_code_tab'] ) ? '1' : '';
        $line_space = ! empty( $_POST['gator_gate_pre_change_line_space'] ) ? '1' : '';

        if ( '' === $visual ) {
            $hide       = '';
            $line_space = '';
        }

        update_post_meta( $forum_id, '_gator_gate_pre_visual_editor', $visual );
        update_post_meta( $forum_id, '_gator_gate_pre_hide_code_tab', $hide );
        update_post_meta( $forum_id, '_gator_gate_pre_change_line_space', $line_space );
    }

    /* -------------------------------------------------------------------------
     * Filters
     * ------------------------------------------------------------------------- */

    public function filter_use_wp_editor( $use ) {
        return $this->should_force_visual_editor() ? true : $use;
    }

    public function filter_user_can_richedit( $can ) {
        return $this->should_force_visual_editor() ? true : $can;
    }

    public function filter_editor_args( $args ) {
        if ( ! $this->should_force_visual_editor() ) {
            return $args;
        }
        $flags = $this->get_forum_flags();
        $args['tinymce']        = true;
        $args['teeny']          = false;
        $args['quicktags']      = ! $flags['hide_code_tab'];
        $args['default_editor'] = 'tinymce';
        return $args;
    }

    public function filter_wp_editor_settings( $settings, $editor_id ) {
        if ( ! $this->should_force_visual_editor() ) {
            return $settings;
        }
        $allowed = array( 'bbp_topic_content', 'bbp_reply_content' );
        if ( ! in_array( $editor_id, $allowed, true ) ) {
            return $settings;
        }
        $flags = $this->get_forum_flags();
        $settings['tinymce']        = true;
        $settings['quicktags']      = ! $flags['hide_code_tab'];
        $settings['teeny']          = false;
        $settings['default_editor'] = 'tinymce';
        return $settings;
    }

    public function filter_block_formats( $init, $editor_id ) {
        if ( ! $this->is_topic_or_reply_form() ) {
            return $init;
        }
        $allowed = array( 'bbp_topic_content', 'bbp_reply_content' );
        if ( ! in_array( $editor_id, $allowed, true ) ) {
            return $init;
        }
        $init['block_formats'] = 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6';
        return $init;
    }

    public function filter_mce_buttons( $buttons ) {
        if ( $this->should_show_pre_button() && ! in_array( 'gator_gate_pre', $buttons, true ) ) {
            $buttons[] = 'gator_gate_pre';
        }
        return $buttons;
    }

    public function filter_mce_external_plugins( $plugins ) {
        if ( $this->should_show_pre_button() ) {
            $plugins['gator_gate_pre'] = GATOR_GATE_PRE_URL . 'assets/js/gator-gate-pre-tinymce.js';
        }
        return $plugins;
    }

    public function filter_kses_tags( $tags ) {
        if ( ! is_array( $tags ) ) {
            $tags = array();
        }
        $tags['pre'] = array();
        return $tags;
    }

    /* -------------------------------------------------------------------------
     * Paste panel
     * ------------------------------------------------------------------------- */

    public function enqueue_panel_script() {
        if ( ! $this->should_show_pre_button() ) {
            return;
        }

        wp_enqueue_script(
            'gator-gate-pre-panel',
            GATOR_GATE_PRE_URL . 'assets/js/gator-gate-pre-panel.js',
            array(),
            GATOR_GATE_PRE_VERSION,
            true
        );

        wp_localize_script(
            'gator-gate-pre-panel',
            'gatorGatePreData',
            array(
                'insertLabel' => __( 'Insert Preformatted Text', 'gator-gate-pre' ),
                'cancelLabel' => __( 'Cancel', 'gator-gate-pre' ),
            )
        );
    }

    public function render_paste_panel() {
        static $done = false;
        if ( $done || ! $this->should_show_pre_button() ) {
            return;
        }
        $done = true;

        $insert_label = esc_html__( 'Insert Preformatted Text', 'gator-gate-pre' );
        $cancel_label = esc_html__( 'Cancel', 'gator-gate-pre' );
        ?>
        <div id="gator-gate-pre-panel" hidden>
            <textarea id="gator-gate-pre-input" rows="8"></textarea>
            <p>
                <button type="button" class="button" id="gator-gate-pre-insert"><?php echo esc_html( $insert_label ); ?></button>
                <button type="button" class="button" id="gator-gate-pre-cancel"><?php echo esc_html( $cancel_label ); ?></button>
            </p>
        </div>
        <?php
    }
}
