jQuery( document ).ready(
	function($) {
		function initialize() {
			$('#traffic-select-level').change(function () {
				level = $(this).val();
			});
			$('#traffic-select-format').change(function () {
				mode = $(this).val();
			});
			$('#traffic-control-play').click(function () {
				consoleRun();
			});
			$('#traffic-control-pause').click(function () {
				consolePause();
			});
		}
		function consoleRun() {
			document.querySelector( '#traffic-control-pause' ).classList.remove( 'traffic-control-inactive' );
			document.querySelector( '#traffic-control-pause' ).classList.add( 'traffic-control-active' );
			document.querySelector( '#traffic-control-play' ).classList.remove( 'traffic-control-active' );
			document.querySelector( '#traffic-control-play' ).classList.add( 'traffic-control-inactive' );
			document.querySelector( '.traffic-control-hint' ).innerHTML = 'running&nbsp;&nbsp;&nbsp;🟢';
			running = true;
		}
		function consolePause() {
			document.querySelector( '#traffic-control-play' ).classList.remove( 'traffic-control-inactive' );
			document.querySelector( '#traffic-control-play' ).classList.add( 'traffic-control-active' );
			document.querySelector( '#traffic-control-pause' ).classList.remove( 'traffic-control-active' );
			document.querySelector( '#traffic-control-pause' ).classList.add( 'traffic-control-inactive' );
			document.querySelector( '.traffic-control-hint' ).innerHTML = 'paused&nbsp;&nbsp;&nbsp;🟠';
			running = false;
		}
		function loadLines() {
			if ( running ) {
				if ( '0' === index ) {
					elem = document.createElement( 'pre' );
					elem.classList.add( 'traffic-logger-line' );
					elem.classList.add( 'traffic-logger-line-init' );
					elem.innerHTML = 'Waiting first API call...';
					root.appendChild( elem );
					init = true;
				}
				$.ajax(
					{
						type : 'GET',
						url : livelog.restUrl,
						data : { level: level, mode: mode, index: index },
						beforeSend: function ( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', livelog.restNonce ); },
						success: function( response ) {
							if ( response ) {
								if ( undefined !== response.index ) {
									index = response.index;
								}
								if ( undefined !== response.items ) {
									items = Object.entries( response.items );
									if ( items.length > 0 ) {
										if ( init ) {
											root.removeChild( root.firstElementChild );
											init = false;
											consoleRun();
										}
										items.forEach(
											function( item ){
												elem = document.createElement( 'pre' );
												elem.classList.add( 'traffic-logger-line' );
												elem.classList.add( 'traffic-logger-line-' + item[1].level );
												elem.innerHTML = item[1].line.replace( ' ', '&nbsp;' );
												if ( root.childElementCount > livelog.buffer ) {
													root.removeChild( root.firstElementChild );
												}
												root.appendChild( elem );
												$('#traffic-logger-lines').animate( { scrollTop: elem.offsetTop }, 20 );
											}
										);
									}
								}
							}
						},
						/*error: function( response ) {
                            console.log( response );
                        },*/
						complete:function( response ) {
							setTimeout( loadLines, livelog.frequency );
						}
					}
				);
			} else {
				setTimeout( loadLines, 250 );
			}
		}

		let level   = 'info';
		let mode    = 'wp';
		let index   = '0';
		let running = true;
		let init    = false;
		const root  = document.querySelector( '#traffic-logger-lines' );

		initialize();
		loadLines();

	}
);
