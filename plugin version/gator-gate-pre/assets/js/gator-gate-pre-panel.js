( function () {
    function init() {
        var panel = document.getElementById( 'gator-gate-pre-panel' );
        var input = document.getElementById( 'gator-gate-pre-input' );

        if ( ! panel || ! input ) {
            return false;
        }

        var loaded = null;

        function ed() {
            return window.tinyMCE && ( tinyMCE.get( 'bbp_topic_content' ) || tinyMCE.get( 'bbp_reply_content' ) );
        }

        function inPanel( node ) {
            return !!( node && panel.contains( node ) );
        }

        function inPre( e, node ) {
            try {
                return !!( e && e.selection && e.dom && e.dom.getParent( node || e.selection.getNode(), 'pre' ) );
            } catch ( err ) {
                return false;
            }
        }

        function preNode( e ) {
            return inPre( e ) ? e.dom.getParent( e.selection.getNode(), 'pre' ) : null;
        }

        function esc( s ) {
            return String( s || '' )
                .replace( /&/g, '&amp;' )
                .replace( /</g, '&lt;' )
                .replace( />/g, '&gt;' );
        }

        function plain( s ) {
            return String( s || '' )
                .replace( /^\s*<pre\b[^>]*>/i, '' )
                .replace( /<\/pre>\s*$/i, '' )
                .replace( /<\/?pre\b[^>]*>/gi, '' );
        }

        function close() {
            panel.hidden = true;
            input.value = '';
            loaded = null;
        }

        window.gatorGatePreOpenPanel = function () {
            var e = ed();
            if ( ! e ) {
                return;
            }
            loaded = preNode( e );
            input.value = loaded ? ( loaded.innerText || loaded.textContent || '' ) : '';
            panel.hidden = false;
            input.focus();
        };

        function dimFormat( e, locked ) {
            var root = e.getContainer && e.getContainer();
            if ( ! root ) {
                return;
            }
            var boxes = root.querySelectorAll( '.mce-listbox' );
            for ( var i = 0; i < boxes.length; i++ ) {
                boxes[ i ].style.pointerEvents = locked ? 'none' : '';
                boxes[ i ].style.opacity = locked ? '0.4' : '';
            }
        }

        function guard( e ) {
            if ( ! e || e._gatorGatePreFmt ) {
                return;
            }
            e._gatorGatePreFmt = true;

            e.on( 'BeforeExecCommand', function ( ev ) {
                var cmd = String( ev.command || '' );
                var val = String( ev.value || '' );
                if ( ! inPre( e ) ) {
                    return;
                }
                if (
                    cmd === 'FormatBlock' ||
                    cmd === 'mceToggleFormat' ||
                    cmd === 'mceBlockQuote' ||
                    cmd === 'FontName' ||
                    cmd === 'FontSize' ||
                    /^h[1-6]$/i.test( val )
                ) {
                    ev.preventDefault();
                    if ( ev.stopImmediatePropagation ) {
                        ev.stopImmediatePropagation();
                    }
                    return false;
                }
            }, true );

            e.on( 'NodeChange click', function () {
                dimFormat( e, inPre( e ) );
            } );
        }

        document.addEventListener( 'mousedown', function ( ev ) {
            if ( inPanel( ev.target ) ) {
                return;
            }
            var e = ed();
            if ( ! e || ! inPre( e ) || ! ev.target.closest ) {
                return;
            }
            var box = ev.target.closest( '.mce-listbox' );
            var item = ev.target.closest( '.mce-menu-item' );
            var label = item ? ( item.textContent || '' ).replace( /\s+/g, ' ' ).trim() : '';
            var heading = item && /heading\s*[1-6]|^h[1-6]$/i.test( label );
            if ( ! box && ! heading ) {
                return;
            }
            ev.preventDefault();
            ev.stopPropagation();
            if ( box ) {
                dimFormat( e, true );
            }
        }, true );

        function boot() {
            var e = ed();
            if ( e ) {
                guard( e );
            }
        }

        window.gatorGatePreGuard = guard;

        if ( window.tinymce && tinymce.on ) {
            tinymce.on( 'AddEditor', function ( evt ) {
                guard( evt.editor );
            } );
        }

        boot();

        var cancelBtn = document.getElementById( 'gator-gate-pre-cancel' );
        var insertBtn = document.getElementById( 'gator-gate-pre-insert' );

        if ( cancelBtn ) {
            cancelBtn.onclick = close;
        }

        if ( insertBtn ) {
            insertBtn.onclick = function () {
                var text = plain( input.value );
                if ( ! text ) {
                    return;
                }
                var e = ed();
                if ( e ) {
                    var target = ( loaded && loaded.parentNode ) ? loaded : preNode( e );
                    if ( target ) {
                        e.undoManager.transact( function () {
                            e.dom.setHTML( target, esc( text ) );
                        } );
                    } else {
                        e.execCommand( 'mceInsertContent', false, '<pre>' + esc( text ) + '</pre>' );
                    }
                } else {
                    var ta = document.getElementById( 'bbp_topic_content' ) || document.getElementById( 'bbp_reply_content' );
                    if ( ta ) {
                        ta.value += ( ta.value ? '\n\n' : '' ) + '<pre>' + esc( text ) + '</pre>';
                    }
                }
                close();
            };
        }

        return true;
    }

    // Try immediately, then keep trying until the panel exists
    if ( ! init() ) {
        var attempts = 0;
        var timer = setInterval( function () {
            attempts++;
            if ( init() || attempts > 50 ) {
                clearInterval( timer );
            }
        }, 100 );
    }
} )();
