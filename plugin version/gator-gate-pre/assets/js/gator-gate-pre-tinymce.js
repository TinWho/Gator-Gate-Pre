tinymce.PluginManager.add( 'gator_gate_pre', function ( ed ) {
    ed.addButton( 'gator_gate_pre', {
        text: 'Pre',
        tooltip: 'Paste preformatted text',
        onclick: function () {
            if ( window.gatorGatePreOpenPanel ) {
                window.gatorGatePreOpenPanel();
            }
        }
    } );

    function inPre() {
        try {
            return !!( ed.selection && ed.dom.getParent( ed.selection.getNode(), 'pre' ) );
        } catch ( err ) {
            return false;
        }
    }

    function open() {
        if ( window.gatorGatePreOpenPanel ) {
            window.gatorGatePreOpenPanel();
        }
    }

    function isPasteKey( ev ) {
        if ( ! ev || ! ( ev.ctrlKey || ev.metaKey ) || ev.altKey || ev.shiftKey ) {
            return false;
        }
        return String( ev.key || '' ).toLowerCase() === 'v' || ev.keyCode === 86 || ev.which === 86;
    }

    function block( ev ) {
        if ( ! ev ) {
            return;
        }
        ev.preventDefault();
        ev.stopPropagation();
        if ( ev.stopImmediatePropagation ) {
            ev.stopImmediatePropagation();
        }
    }

    function bindFrame() {
        var win = ed.getWin && ed.getWin();
        var doc = ed.getDoc && ed.getDoc();
        if ( ! win || ! doc || win._gatorGatePre ) {
            return;
        }
        win._gatorGatePre = true;

        win.addEventListener( 'keydown', function ( ev ) {
            if ( ! isPasteKey( ev ) || ! inPre() ) {
                return;
            }
            block( ev );
            open();
        }, true );

        win.addEventListener( 'paste', function ( ev ) {
            if ( ! inPre() ) {
                return;
            }
            block( ev );
            open();
        }, true );

        doc.addEventListener( 'paste', function ( ev ) {
            if ( ! inPre() ) {
                return;
            }
            block( ev );
            open();
        }, true );
    }

    ed.on( 'PastePreProcess', function ( e ) {
        if ( ! inPre() ) {
            return;
        }
        e.content = '';
        if ( e.preventDefault ) {
            e.preventDefault();
        }
        open();
    } );

    ed.on( 'PastePostProcess', function () {
        if ( ! inPre() ) {
            return;
        }
        if ( ed.undoManager && ed.undoManager.hasUndo && ed.undoManager.hasUndo() ) {
            ed.undoManager.undo();
        }
        open();
    } );

    ed.on( 'BeforeExecCommand', function ( ev ) {
        var cmd = String( ev.command || '' );
        if ( ! inPre() ) {
            return;
        }
        if ( /^mcePaste/i.test( cmd ) || cmd === 'Paste' ) {
            block( ev );
            open();
            return false;
        }
    }, true );

    ed.on( 'init', bindFrame );
    bindFrame();

    if ( window.gatorGatePreGuard ) {
        window.gatorGatePreGuard( ed );
    }
} );
