( function ( $ ) {
	'use strict';

	// Safe fallback for localized variables
	var wccAdmin = window.wccAdmin || {
		labelPublished: 'Publicada',
		labelDraft: 'Borrador',
		confirmDelete: '¿Eliminar esta entrega? El cambio se aplicará al guardar el post.',
		labelEntries: 'entregas',
		labelPublishedOf: 'publicadas'
	};

	// B1: Module-level counter seeded with timestamp – never collides even after deletions.
	var entryCounter = Date.now();

	function getNextIndex() {
		return ++entryCounter;
	}

	/**
	 * Sync the status badge and date shown in the collapsed header.
	 *
	 * @param {jQuery} $entry The .wcc-entry element to update.
	 */
	function updateHeaderMeta( $entry ) {
		var status  = $entry.find( 'select[name*="[status]"]' ).val();
		var date    = $entry.find( 'input[type="date"]' ).val() || '';
		var $badge  = $entry.find( '.wcc-entry__status-badge' );
		var $dateEl = $entry.find( '.wcc-entry__header-date' );

		$entry.attr( 'data-status', status );
		$badge.removeClass( 'is-draft is-publish' );

		if ( status === 'publish' ) {
			$badge.addClass( 'is-publish' ).text( wccAdmin.labelPublished );
		} else {
			$badge.addClass( 'is-draft' ).text( wccAdmin.labelDraft );
		}

		$dateEl.text( date );
	}

	/**
	 * Refresh the "(N entregas, M publicadas)" counter.
	 */
	function updateEntryCount() {
		var total     = $( '#wcc-entries .wcc-entry' ).length;
		var published = $( '#wcc-entries .wcc-entry[data-status="publish"]' ).length;
		$( '#wcc-entry-count' ).text(
			'(' + total + ' ' + wccAdmin.labelEntries + ', ' + published + ' ' + wccAdmin.labelPublishedOf + ')'
		);
	}

	// A1: On page load collapse all entries except the first, and sync badges.
	$( function () {
		var $entries = $( '#wcc-entries .wcc-entry' );
		$entries.each( function () {
			updateHeaderMeta( $( this ) );
		} );
		$entries.not( ':first' ).addClass( 'is-collapsed' );
		updateEntryCount();
	} );

	// Add a new entry.
	$( '#wcc-add-entry' ).on( 'click', function () {
		// B3: Collapse all existing entries before inserting the new one.
		$( '#wcc-entries .wcc-entry' ).addClass( 'is-collapsed' );

		var html = $( '#tmpl-wcc-entry' ).html().replace( /__INDEX__/g, getNextIndex() );
		$( '#wcc-entries' ).prepend( html );

		var $new = $( '#wcc-entries .wcc-entry' ).first();
		updateHeaderMeta( $new );
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
		$( this ).closest( '.wcc-entry' ).toggleClass( 'is-collapsed' );
	} );

	// Keep heading text in sync while the user types.
	$( '#wcc-entries' ).on( 'input', '.wcc-title', function () {
		var title = $( this ).val() || 'Nueva entrega';
		$( this ).closest( '.wcc-entry' ).find( '.wcc-entry__heading' ).text( title );
	} );

	// A4: Sync status badge when the status select changes.
	$( '#wcc-entries' ).on( 'change', 'select[name*="[status]"]', function () {
		updateHeaderMeta( $( this ).closest( '.wcc-entry' ) );
		updateEntryCount();
	} );

	// A4: Sync date label when the date input changes.
	$( '#wcc-entries' ).on( 'change', 'input[type="date"]', function () {
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
		var $statusSelect = $entry.find( 'select[name*="[status]"]' );
		var $contentInput = $entry.find( '.wcc-content' );

		var title   = $titleInput.val() || '';
		var date    = $dateInput.val() || '';
		var status  = $statusSelect.val() || 'draft';
		var content = $contentInput.val() || '';
		var id      = $idInput.val() || '';

		if ( ! title && ! content.trim() ) {
			alert( 'El título y el contenido no pueden estar vacíos.' );
			return;
		}

		var postId = $( '#post_ID' ).val();
		var nonce  = $( 'input[name="wcc_nonce"]' ).val();

		if ( ! postId ) {
			alert( 'No se pudo obtener el ID del post.' );
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
				entry: {
					id: id,
					title: title,
					date: date,
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

					// Actualizar la meta cabecera
					updateHeaderMeta( $entry );

					// Feedback visual exitoso
					$button.text( wccAdmin.labelSaved ).addClass( 'wcc-saved' );
					setTimeout( function () {
						$button.prop( 'disabled', false ).text( originalText ).removeClass( 'wcc-saved' );
					}, 2000 );
				} else {
					alert( 'Error al guardar: ' + ( response.data.message || 'Error desconocido' ) );
					$button.prop( 'disabled', false ).text( originalText );
				}
			},
			error: function () {
				alert( 'Ocurrió un error en la comunicación con el servidor.' );
				$button.prop( 'disabled', false ).text( originalText );
			}
		} );
	} );

} )( jQuery );
