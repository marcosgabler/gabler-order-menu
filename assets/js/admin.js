/**
 * Order Menu – Admin JavaScript
 *
 * Handles:
 *  - jQuery UI Sortable (nested drag-and-drop)
 *  - AJAX save / reset
 *  - Enable/disable front-end toggle
 *  - Visual feedback (notice, spinner, order numbers)
 *
 * @package OrderMenu
 */
/* global omData, jQuery */

( function ( $ ) {
    'use strict';

    // ── State ──────────────────────────────────────────────────────────────
    let isDirty  = false;   // unsaved changes?
    let isSaving = false;

    // ── Cached selectors ───────────────────────────────────────────────────
    const $app       = $( '#om-app' );
    const $btnSave   = $( '#om-btn-save' );
    const $btnReset  = $( '#om-btn-reset' );
    const $notice    = $( '#om-notice' );
    const postType   = $app.data( 'post-type' );

    // ── Init sortable (recursive for nested lists) ──────────────────────
    function initSortable( $list ) {
        $list.sortable( {
            handle:          '.om-item__handle',
            placeholder:     'om-item om-item--placeholder',
            tolerance:       'pointer',
            cursor:          'grabbing',
            cursorAt:        { top: 24, left: 80 },
            connectWith:     '.om-sortable',   // allow cross-list dragging
            items:           '> li.om-item',
            opacity:         1,
            scroll:          true,
            scrollSensitivity: 60,
            scrollSpeed:     12,

            start: function ( _e, ui ) {
                ui.item.addClass( 'om-item--dragging' );
                ui.placeholder.css( { visibility: 'visible', height: ui.item.outerHeight() } );
            },

            stop: function ( _e, ui ) {
                ui.item.removeClass( 'om-item--dragging' );
                markDirty();
                refreshOrderNumbers();
            },

            receive: function () {
                markDirty();
            },
        } );

        // Init nested lists too
        $list.find( '.om-sortable--nested' ).each( function () {
            initSortable( $( this ) );
        } );
    }

    initSortable( $( '#om-sortable-root' ) );

    // ── Mark dirty ─────────────────────────────────────────────────────────
    function markDirty() {
        if ( ! isDirty ) {
            isDirty = true;
            $btnSave.prop( 'disabled', false );
        }
    }

    // ── Refresh order number badges ─────────────────────────────────────────
    function refreshOrderNumbers() {
        let counter = 0;
        $( '#om-sortable-root .om-item' ).each( function () {
            $( this ).find( '> .om-item__inner .om-order-val' ).first().text( counter );
            counter++;
        } );
    }

    // ── Serialize tree to flat array ────────────────────────────────────────
    /**
     * Walk the DOM tree and collect { id, parent, order } for every item.
     *
     * @param {jQuery} $list
     * @param {number} parentId
     * @param {Array}  result   – mutated in place
     * @param {Object} counter  – { v: 0 } shared mutable counter
     */
    function serializeList( $list, parentId, result, counter ) {
        $list.children( '.om-item' ).each( function () {
            const id = parseInt( $( this ).data( 'id' ), 10 );
            result.push( { id: id, parent: parentId, order: counter.v++ } );

            const $nested = $( this ).children( '.om-sortable--nested' );
            if ( $nested.length ) {
                serializeList( $nested, id, result, counter );
            }
        } );
    }

    // ── Show notice ─────────────────────────────────────────────────────────
    function showNotice( message, type ) {
        $notice
            .attr( 'class', 'om-notice om-notice--' + type )
            .html( message )
            .removeAttr( 'hidden' );

        if ( type !== 'saving' ) {
            setTimeout( () => $notice.attr( 'hidden', '' ), 4000 );
        }
    }

    function hideNotice() {
        $notice.attr( 'hidden', '' );
    }

    // ── Save ────────────────────────────────────────────────────────────────
    $btnSave.on( 'click', function () {
        if ( isSaving ) return;
        isSaving = true;

        const items   = [];
        const counter = { v: 0 };
        serializeList( $( '#om-sortable-root' ), 0, items, counter );

        $btnSave.prop( 'disabled', true );
        showNotice( '<span class="om-spinner"></span> ' + omData.i18n.saving, 'saving' );

        $.post( omData.ajaxUrl, {
            action:    'om_save_order',
            nonce:     omData.nonce,
            post_type: postType,
            order:     JSON.stringify( items ),
        } )
        .done( function ( res ) {
            if ( res.success ) {
                isDirty  = false;
                isSaving = false;
                showNotice( '✓ ' + omData.i18n.saved, 'success' );
            } else {
                isSaving = false;
                $btnSave.prop( 'disabled', false );
                showNotice( '✕ ' + ( res.data?.message || omData.i18n.error ), 'error' );
            }
        } )
        .fail( function () {
            isSaving = false;
            $btnSave.prop( 'disabled', false );
            showNotice( '✕ ' + omData.i18n.error, 'error' );
        } );
    } );

    // ── Reset ───────────────────────────────────────────────────────────────
    $btnReset.on( 'click', function () {
        if ( ! window.confirm( omData.i18n.confirm ) ) return;

        showNotice( '<span class="om-spinner"></span> ' + omData.i18n.saving, 'saving' );

        $.post( omData.ajaxUrl, {
            action:    'om_reset_order',
            nonce:     omData.nonce,
            post_type: postType,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                hideNotice();
                window.location.reload();
            } else {
                showNotice( '✕ ' + ( res.data?.message || omData.i18n.error ), 'error' );
            }
        } )
        .fail( function () {
            showNotice( '✕ ' + omData.i18n.error, 'error' );
        } );
    } );

    // ── Enable/disable front-end toggle ────────────────────────────────────
    $( '#om-enable-front' ).on( 'change', function () {
        const $cb    = $( this );
        const type   = $cb.data( 'type' );
        const nonce  = $cb.data( 'nonce' );
        const enable = $cb.is( ':checked' ) ? 1 : 0;

        $.post( omData.ajaxUrl, {
            action:    'om_toggle_frontend',
            nonce:     nonce,
            post_type: type,
            enable:    enable,
        } ).fail( function () {
            // Revert on error
            $cb.prop( 'checked', ! enable );
        } );
    } );

    // ── Unload guard ────────────────────────────────────────────────────────
    $( window ).on( 'beforeunload', function () {
        if ( isDirty ) {
            return 'Você tem alterações não salvas.';
        }
    } );

    // Prevent unload warning after navigating via tabs
    $( '.om-tab' ).on( 'click', function () {
        isDirty = false;
    } );

} )( jQuery );
