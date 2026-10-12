/* global jQuery, ajaxurl, window, document */
( function ( $ ) {
	'use strict';

	// Safe fallback for localized variables
	var wccAdmin = $.extend(
		{
			labelPublished: 'Publicada',
			labelDraft: 'Borrador',
			labelScheduled: 'Programada',
			confirmDelete: '¿Eliminar esta entrega? El cambio se aplicará al guardar el post.',
			labelNewEntry: 'Nueva entrega',
			labelEntriesOne: '%d entrega',
			labelEntriesMany: '%d entregas',
			labelPublishedOne: '%d publicada',
			labelPublishedMany: '%d publicadas',
			labelSaving: 'Guardando…',
			labelSaved: 'Guardada',
			noticeSaved: 'Entrega guardada correctamente.',
			noticeBumped: 'Entrega publicada: la fecha del post fue actualizada y el post volvió al inicio.',
			errorEmpty: 'El título y el contenido no pueden estar vacíos.',
			errorPostId: 'No se pudo obtener el ID del post.',
			errorServer: 'Ocurrió un error en la comunicación con el servidor.',
			errorUnknown: 'Error desconocido'
		},
		window.wccAdmin || {}
	);

	// Module-level counter seeded with timestamp – never collides even after deletions.
	var entryCounter = Date.now();

	function getNextIndex() {
		return ++entryCounter;
	}

	/**
	 * Fill a "%d" template with a number.
	 *
	 * @param {string} tpl Template with %d.
	 * @param {number} n   Number.
	 * @return {string} Text.
	 */
	function plural( tpl, n ) {
		return tpl.replace( '%d', n );
	}

	/**
	 * Sync the status badge (and optionally the date) shown in the collapsed header.
	 *
	 * @param {jQuery}  $entry        The .wcc-entry element to update.
	 * @param {string}  formattedDate Optional server-formatted date string.
	 * @param {boolean} keepDate      Keep the date text already rendered by PHP (page load).
	 */
	function updateHeaderMeta( $entry, formattedDate, keepDate ) {
		var status  = $entry.find( 'select[name*="[status]"]' ).val();
		var date    = $entry.find( 'input[type="date"]' ).val() || '';
		var time    = $entry.find( 'input[type="time"]' ).val() || '';
		var $badge  = $entry.find( '.wcc-entry__status-badge' );
		var $dateEl = $entry.find( '.wcc-entry__header-date' );

		$entry.attr( 'data-status', status );
		$badge.removeClass( 'is-draft is-publish is-scheduled' );

		if ( status === 'publish' ) {
			$badge.addClass( 'is-publish' ).text( wccAdmin.labelPublished );
		} else if ( status === 'scheduled' ) {
			$badge.addClass( 'is-scheduled' ).text( wccAdmin.labelScheduled );
		} else {
			$badge.addClass( 'is-draft' ).text( wccAdmin.labelDraft );
		}

		if ( formattedDate ) {
			$dateEl.text( formattedDate );
		} else if ( ! keepDate ) {
			$dateEl.text( time ? date + ' ' + time : date );
		}
	}

	/**
	 * Refresh the "(N entregas, M publicadas)" counter.
	 */
	function updateEntryCount() {
		var total     = $( '#wcc-entries .wcc-entry' ).length;
		var published = $( '#wcc-entries .wcc-entry[data-status="publish"]' ).length;
		$( '#wcc-entry-count' ).text(
			'(' + plural( 1 === total ? wccAdmin.labelEntriesOne : wccAdmin.labelEntriesMany, total ) +
			', ' + plural( 1 === published ? wccAdmin.labelPublishedOne : wccAdmin.labelPublishedMany, published ) + ')'
		);
	}

	function showNotice( message, type ) {
		var noticeType = type || 'success';
		var $notice = $( '<div />', {
			class: 'notice notice-' + noticeType + ' is-dismissible wcc-ajax-notice',
			role: 'error' === noticeType ? 'alert' : 'status',
			'aria-live': 'polite'
		} ).append( $( '<p />' ).text( message ) );

		$( '.wcc-ajax-notice' ).remove();
		$( '#wcc_weekly_content .inside' ).prepend( $notice );
	}

	// On page load collapse all entries except the first, and sync badges (the date text comes
	// already formatted by PHP: keep it).
	$( function () {
		var $entries = $( '#wcc-entries .wcc-entry' );
		$entries.each( function () {
			updateHeaderMeta( $( this ), '', true );
		} );
		$entries.not( ':first' ).addClass( 'is-collapsed' );
		$entries.each( function () {
			var isCollapsed = $( this ).hasClass( 'is-collapsed' );
			$( this ).find( '.wcc-toggle-entry' ).attr( 'aria-expanded', ! isCollapsed );
		} );
		updateEntryCount();

		// Inicializar jQuery UI Sortable para permitir arrastrar y soltar
		$( '#wcc-entries' ).sortable( {
			handle: '.wcc-entry__header',
			axis: 'y',
			placeholder: 'wcc-sortable-placeholder',
			update: function () {
				updateEntryCount();
			}
		} );
	} );

	// Add a new entry.
	$( '#wcc-add-entry' ).on( 'click', function () {
		// Collapse all existing entries before inserting the new one.
		$( '#wcc-entries .wcc-entry' ).addClass( 'is-collapsed' ).find( '.wcc-toggle-entry' ).attr( 'aria-expanded', 'false' );

		var html = $( '#tmpl-wcc-entry' ).html().replace( /__INDEX__/g, getNextIndex() );
		$( '#wcc-entries' ).prepend( html );

		var $new = $( '#wcc-entries .wcc-entry' ).first();
		updateHeaderMeta( $new );
		$new.find( '.wcc-toggle-entry' ).attr( 'aria-expanded', 'true' );
		$new.find( '.wcc-title' ).trigger( 'focus' );
		updateEntryCount();
	} );

	// Remove an entry.
	$( '#wcc-entries' ).on( 'click', '.wcc-remove-entry', function () {
		if ( window.confirm( wccAdmin.confirmDelete ) ) {
			$( this ).closest( '.wcc-entry' ).remove();
			updateEntryCount();
		}
	} );

	// Toggle collapse/expand.
	$( '#wcc-entries' ).on( 'click', '.wcc-toggle-entry', function () {
		var $entry = $( this ).closest( '.wcc-entry' );
		$entry.toggleClass( 'is-collapsed' );
		var isCollapsed = $entry.hasClass( 'is-collapsed' );
		$( this ).attr( 'aria-expanded', ! isCollapsed );
	} );

	// Keep heading text in sync while the user types.
	$( '#wcc-entries' ).on( 'input', '.wcc-title', function () {
		var title = $( this ).val() || wccAdmin.labelNewEntry;
		$( this ).closest( '.wcc-entry' ).find( '.wcc-entry__heading' ).text( title );
	} );

	// Sync status badge when the status select changes.
	$( '#wcc-entries' ).on( 'change', 'select[name*="[status]"]', function () {
		updateHeaderMeta( $( this ).closest( '.wcc-entry' ) );
		updateEntryCount();
	} );

	// Sync date label when the date or time input changes.
	$( '#wcc-entries' ).on( 'change', 'input[type="date"], input[type="time"]', function () {
		updateHeaderMeta( $( this ).closest( '.wcc-entry' ) );
	} );

	// AJAX Save single entry
	$( '#wcc-entries' ).on( 'click', '.wcc-save-single-entry', function ( e ) {
		e.preventDefault();
		e.stopPropagation();

		var $button = $( this );
		var $entry  = $button.closest( '.wcc-entry' );

		// Inputs
		var $idInput      = $entry.find( 'input[name*="[id]"]' );
		var $titleInput   = $entry.find( '.wcc-title' );
		var $dateInput    = $entry.find( 'input[type="date"]' );
		var $timeInput    = $entry.find( 'input[type="time"]' );
		var $statusSelect = $entry.find( 'select[name*="[status]"]' );
		var $contentInput = $entry.find( '.wcc-content' );

		var title   = $titleInput.val() || '';
		var date    = $dateInput.val() || '';
		var time    = $timeInput.val() || '';
		var status  = $statusSelect.val() || 'draft';
		var content = $contentInput.val() || '';
		var id      = $idInput.val() || '';

		if ( ! title && ! content.trim() ) {
			showNotice( wccAdmin.errorEmpty, 'error' );
			$titleInput.trigger( 'focus' );
			return;
		}

		var postId = $( '#post_ID' ).val();
		var nonce  = $( 'input[name="wcc_nonce"]' ).val();

		if ( ! postId ) {
			showNotice( wccAdmin.errorPostId, 'error' );
			return;
		}

		// Cambiar visual del botón a cargando
		var originalText = $button.text();
		$button.prop( 'disabled', true ).text( wccAdmin.labelSaving );

		$.ajax( {
			url: ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wcc_save_single_entry',
				post_id: postId,
				nonce: nonce,
				active_ids: $( '#wcc-entries .wcc-entry' ).map( function () {
					return $( this ).attr( 'data-entry-id' ) || '';
				} ).get(),
				settings: {
					enabled: $( 'input[name="wcc_enabled"]' ).is( ':checked' ) ? '1' : '',
					hide_index: $( 'input[name="wcc_hide_index"]' ).is( ':checked' ) ? '1' : '',
					order: $( 'select[name="wcc_order"]' ).val() || 'desc',
					title: $( 'input[name="wcc_title"]' ).val() || ''
				},
				entry: {
					id: id,
					title: title,
					date: date,
					time: time,
					status: status,
					content: content
				}
			},
			success: function ( response ) {
				if ( response.success ) {
					// Actualizar ID si era nuevo
					if ( ! id && response.data.entry_id ) {
						$idInput.val( response.data.entry_id );
						$entry.attr( 'data-entry-id', response.data.entry_id );
					}

					// Sincronizar el select de estado con el estado real del servidor
					if ( response.data.status ) {
						$statusSelect.val( response.data.status );
					}

					// Actualizar la meta cabecera
					updateHeaderMeta( $entry, response.data.formatted_date );
					updateEntryCount();

					// Feedback visual exitoso
					$button.text( wccAdmin.labelSaved ).addClass( 'wcc-saved' );
					showNotice(
						response.data.bumped ? wccAdmin.noticeBumped : wccAdmin.noticeSaved,
						'success'
					);
					setTimeout( function () {
						$button.prop( 'disabled', false ).text( originalText ).removeClass( 'wcc-saved' );
					}, 2000 );
				} else {
					showNotice( ( response.data && response.data.message ) || wccAdmin.errorUnknown, 'error' );
					$button.prop( 'disabled', false ).text( originalText );
				}
			},
			error: function () {
				showNotice( wccAdmin.errorServer, 'error' );
				$button.prop( 'disabled', false ).text( originalText );
			}
		} );
	} );

} )( jQuery );
