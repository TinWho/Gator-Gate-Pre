=== Gator Gate Pre for bbPress ===
Contributors: tinfoilwho
Tags: bbpress, tinymce, pre, code, visual-editor
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple, robust preformatted code block support for the bbPress visual editor.

== Description ==

Gator Gate Pre for bbPress adds reliable Visual Editor `<pre>` support to front-end bbPress topic and reply forms on a per-forum basis.

On each forum’s edit screen (Attributes metabox) you will find three checkboxes:

* **Enable Visual Editor** — forces the visual editor on topic and reply forms for that forum.
* **Hide the Code tab** — available only when the visual editor is enabled (UI convenience only).
* **Change line spacing** — available only when the visual editor is enabled. Applies tighter line-height and paragraph/heading margins in the visual editor and on the front end of saved posts.

The Pre button and paste panel appear by default whenever the visual editor is enabled.

**Other plugins (or a theme’s functions.php file) can control the Pre button** with the `gg_pre_show_pre_button` filter:

```php

  // Hide the Pre button everywhere
  add_filter( 'gator_gate_pre_show_pre_button', '__return_false' );

  // Force the Pre button on everywhere
  add_filter( 'gator_gate_pre_show_pre_button', '__return_true' );

  // Target a specific forum ID
  add_filter( 'gator_gate_pre_show_pre_button', function( $show, $forum_id ) {
      if ( 123 === (int) $forum_id ) {
          return false; // hide on forum 123
      }
      return $show;
  }, 10, 2 );
