/**
 * Revision Reaper admin runner.
 *
 * Drives the dry-run / live-run progress UI on the Tools > Revision Reaper
 * page. All server-injected values arrive via wp_localize_script() under
 * window.timuRevisionReaper.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var data = window.timuRevisionReaper || {};
		var nonce = data.nonce || '';
		var ajaxUrl = data.ajaxUrl || ( window.ajaxurl || '' );
		var items = ( data.items && data.items.length ) ? data.items.slice() : [];
		var total = items.length;
		var completed = 0;
		var isDryRun = !! data.isDryRun;
		var i18n = data.i18n || {};

		// Backup-confirm gate on the live-run form.
		$( '#rr-backup-confirm' ).on( 'change', function () {
			$( '#rr-live-run-btn' ).prop( 'disabled', ! this.checked );
		} );

		$( '#rr-live-run-form' ).on( 'submit', function ( e ) {
			if ( ! $( '#rr-backup-confirm' ).is( ':checked' ) ) {
				e.preventDefault();
				window.alert( i18n.confirmBackup || 'Please confirm the backup acknowledgement before running a live cleanup.' );
				return false;
			}
			if ( ! window.confirm( i18n.confirmStart || 'Start live database cleanup? Items will be exported to JSON, then trashed.' ) ) {
				e.preventDefault();
				return false;
			}
			return true;
		} );

		if ( ! total ) {
			return;
		}

		function processNext() {
			if ( ! items.length ) {
				if ( ! isDryRun ) {
					$( '#rr-log' ).prepend( '<div><strong>' + ( i18n.cleanupFinished || 'Cleanup finished. Optimizing tables...' ) + '</strong></div>' );
					$.post( ajaxUrl, { action: 'timu_rr_optimize_db', nonce: nonce } ).done( function ( res ) {
						$( '#rr-log' ).prepend( '<div style="color:green;">' + ( res && res.data ? res.data : '' ) + '</div>' );
					} );
				} else {
					$( '#rr-log' ).prepend( '<div><strong>' + ( i18n.simulationComplete || 'Simulation complete. No changes made.' ) + '</strong></div>' );
				}
				return;
			}

			var item = items.shift();
			$.post( ajaxUrl, {
				action: 'timu_rr_purge_item',
				item_id: item.id,
				item_type: item.type,
				limit: data.limit || 3,
				dry_run: isDryRun ? 'true' : 'false',
				nonce: nonce
			} ).done( function ( res ) {
				completed++;
				var percent = Math.round( ( completed / total ) * 100 );
				// Keep the visual width and the assistive-tech value in lockstep.
				$( '#rr-progress-bar' ).css( 'width', percent + '%' ).attr( 'aria-valuenow', percent );
				$( '#rr-log' ).prepend( '<div>' + ( res && res.data ? res.data : '' ) + '</div>' );
				processNext();
			} );
		}

		if ( ! isDryRun ) {
			// Live run: snapshot before we touch anything.
			$( '#rr-log' ).prepend( '<div><strong>' + ( i18n.writingExport || 'Writing pre-run export...' ) + '</strong></div>' );
			$.post( ajaxUrl, { action: 'timu_rr_pre_run_export', nonce: nonce } )
				.done( function ( res ) {
					if ( res && res.success ) {
						$( '#rr-log' ).prepend( '<div style="color:green;">' + res.data + '</div>' );
						processNext();
					} else {
						var msg = ( res && res.data ) ? res.data : ( i18n.exportFailed || 'Pre-run export failed.' );
						$( '#rr-log' ).prepend( '<div style="color:#b32d2e;">Aborting: ' + msg + '</div>' );
					}
				} )
				.fail( function () {
					$( '#rr-log' ).prepend( '<div style="color:#b32d2e;">' + ( i18n.exportRequestFailed || 'Aborting: pre-run export request failed.' ) + '</div>' );
				} );
		} else {
			processNext();
		}
	} );
}( jQuery ) );
