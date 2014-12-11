(function( $ ) {

	$( document ).ready(function() {

		/**
		 * Extending drafts
		 */

		$('.extend-toggle').on('click', function (e) {
			e.preventDefault();

			// If screensize is small, only show user control fields on table
			if ( 768 > $(window).width() ) {
				$( '.title' ).toggle();
				$( '.url' ).toggle();
				$( '.expires-after' ).toggle();
				$( '.delete-link-table-cell' ).toggle();

				if ($( '#actions-table-header' ).attr('colspan') ) {
					$( '#actions-table-header' ).removeAttr('colspan');	
				} else {
					$( '#actions-table-header' ).attr('colspan', 2);
				}
			}

			var key = $(e.target).data('key');

			$( '#inline-extend-actions-' + key ).toggleClass('user-controls');
			$( '#extend-form-' + key ).toggle('fast');
			$( '#expand-' + key ).toggle('fast');
			$( '#extend-form-' + key + ' input[name="expires"]' ).focus();
		})


		/**
		 * Display real-time human readable "Expires After" value 
		 */
		var expiresAfterDynamicFields = $( '[data-draft-expiry-time]' );

		$.each(expiresAfterDynamicFields, function ( index, element ) {
			var $expiresAfter = $(element);

			setInterval(function() {
				var expiryDateEpochTime = $expiresAfter.data( 'draft-expiry-time' );
				var currentEpochTime = Math.round( new Date().getTime() / 1000.0 );

				var secondsUntilExpiry = expiryDateEpochTime - currentEpochTime;					

				$expiresAfter.html(getHumanReadableExpiryTime( secondsUntilExpiry ));

			}, 1000 );
		});

		/**
		 * Given seconds, this method returns an easy to read representation
		 *
		 * Some examples are: "2 hours 31 minutes", "3 days, 16 hours"
		 */
		function getHumanReadableExpiryTime( secondsUntilExpiry ) {
			var SECONDS_IN_A_MINUTE = 60, SECONDS_IN_AN_HOUR = 3600,
				SECONDS_IN_A_DAY = 86400, MINUTES_IN_AN_HOUR = 60,
				HOURS_IN_A_DAY = 24;

			var minutesUntilExpiry = Math.floor(
				secondsUntilExpiry / SECONDS_IN_A_MINUTE );
			var hoursUntilExpiry = Math.floor(
				secondsUntilExpiry / SECONDS_IN_AN_HOUR );
			var daysUntilExpiry = Math.floor(
				secondsUntilExpiry / SECONDS_IN_A_DAY );

			var secondsUntilExpiryDayOffset = Math.floor(
				secondsUntilExpiry % SECONDS_IN_A_MINUTE );
			var minutesUntilExpiryDayOffset = Math.floor(
				minutesUntilExpiry % MINUTES_IN_AN_HOUR );
			var hoursUntilExpiryDayOffset = Math.floor(
				hoursUntilExpiry % HOURS_IN_A_DAY );
			
			var humanReadableExpiryTime;

			switch ( true ) {
		  		case 0 >= secondsUntilExpiry:
			    	humanReadableExpiryTime = dffL10n.expired;
			    	break;
			   	case 0 < secondsUntilExpiry && 45 >= secondsUntilExpiry:
			    	humanReadableExpiryTime = secondsUntilExpiry + ' ' + dffL10n.seconds;
			    	break;
			    case 45 < secondsUntilExpiry && 75 > secondsUntilExpiry:
					humanReadableExpiryTime = dffL10n.aboutAMinute;
					break;
			    case SECONDS_IN_AN_HOUR > secondsUntilExpiry &&  1 == minutesUntilExpiry:
					humanReadableExpiryTime = minutesUntilExpiry  + ' ' + dffL10n.minute + 
						' ' + secondsUntilExpiryDayOffset + ' ' + dffL10n.seconds;
					break;
			    case SECONDS_IN_AN_HOUR > secondsUntilExpiry &&  0 < minutesUntilExpiry:
					humanReadableExpiryTime = minutesUntilExpiry  + ' ' + dffL10n.minutes;
					break;
			    case SECONDS_IN_AN_HOUR < secondsUntilExpiry && 
			    		SECONDS_IN_A_DAY > secondsUntilExpiry :
					humanReadableExpiryTime = hoursUntilExpiry + ' ' + dffL10n.hours + ' ' +
						minutesUntilExpiryDayOffset + ' ' + dffL10n.minutes;
					break;
			    case SECONDS_IN_A_DAY < secondsUntilExpiry:
					humanReadableExpiryTime = daysUntilExpiry + ' ' + dffL10n.days + ' ' +
 						hoursUntilExpiryDayOffset + ' ' + dffL10n.hours;
					break;
		  		default:
		    		humanReadableExpiryTime = secondsUntilExpiry + ' ' + dffL10n.seconds;
			}

			return humanReadableExpiryTime;
		}

	});

})( jQuery );
