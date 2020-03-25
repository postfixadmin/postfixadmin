/*
Created on 2020-03-12
Copyright 2020 Jacques Deguest
Distributed under the same licence as Postfix Admin
*/
$(document).ready(function()
{
	const DEBUG = true;
	
	// Credits to: https://tdanemar.wordpress.com/2010/08/24/jquery-serialize-method-and-checkboxes/
	// Modified by Jacques Deguest to include other form elements:
	// http://www.w3schools.com/tags/tag_input.asp
	(function($) 
	{
		$.fn.serializeAll = function(options) 
		{
			return $.param(this.serializeArrayAll(options));
		};

		$.fn.serializeArrayAll = function (options) 
		{
			var o = $.extend({
			checkboxesAsBools: false
		}, options || {});

		var rselectTextarea = /select|textarea/i;
		var rinput = /text|hidden|password|search|date|time|number|color|datetime|email|file|image|month|range|tel|url|week/i;

		return this.map(function () 
		{
			return this.elements ? $.makeArray(this.elements) : this;
		})
		.filter(function () 
		{
			return this.name && !this.disabled &&
				(this.checked
				|| (o.checkboxesAsBools && this.type === 'checkbox')
				|| rselectTextarea.test(this.nodeName)
				|| rinput.test(this.type));
		})
		.map(function (i, elem) 
		{
			var val = $(this).val();
			return val == null ?
			null :
			$.isArray(val) ?
				$.map(val, function (val, i) 
				{
					return { name: elem.name, value: val };
				}) :
				{
					name: elem.name,
					value: (o.checkboxesAsBools && this.type === 'checkbox') ?
							(this.checked ? 1 : 0) :
							val
				};
			}).get();
		};
	})(jQuery);
	
	window.makeMessage = function( type, mesg )
	{
		return( sprintf( '<div class="%s">%s</div>', type, mesg ) );
	};
	
	window.showMessage = function()
	{
		if( DEBUG ) console.log( "Called from " + ( arguments.callee.caller === null ? 'void' : arguments.callee.caller.name ) );
		var opts = {
		div: $('#message'),
		append: false,
		dom: null,
		timeout: null,
		scroll: false,
		timeoutCallback: null,
		};
		var msgDiv = $('#message');
		if( arguments.length == 3 && typeof( arguments[2] ) === 'object' )
		{
			var param = arguments[2];
			opts.type = arguments[0];
			opts.message = arguments[1];
			if( typeof( param.append ) !== 'undefined' ) opts.append = param.append;
			if( typeof( param.dom ) !== 'undefined' ) opts.dom = param.dom;
			if( typeof( param.timeout ) !== 'undefined' ) opts.timeout = param.timeout;
			if( typeof( param.timeoutCallback ) !== 'undefined' ) opts.timeoutCallback = param.timeoutCallback;
			if( typeof( param.scroll ) !== 'undefined' ) opts.scroll = param.scroll;
			if( typeof( param.speak ) !== 'undefined' ) opts.speak = param.speak;
		}
		// Backward compatibility
		else if( arguments.length >= 2 )
		{
			opts.type = arguments[0];
			opts.message = arguments[1];
			if( arguments.length > 2 ) opts.append = arguments[2];
			if( arguments.length > 3 ) opts.dom = arguments[3];
			if( arguments.length > 4 ) opts.timeout = arguments[4];
		}
		else
		{
			msgDiv.append( makeMessage( 'warning', "showMessage called with only " + arguments.length + " parameters while 2 at minimum are required. Usage: showMessage( type, message, append, domObject, timeout ) or showMessage( type, message, options )" ) );
			return( false );
		}
		if( typeof( opts.dom ) === 'object' && opts.dom != null )
		{
			msgDiv = opts.dom;
		}
		// Check if message is an array of messages
		// https://stackoverflow.com/a/4775741/4814971
		if( Array.isArray )
		{
			if( Array.isArray( opts.message ) )
			{
				opts.messages = opts.message;
			}
		}
		else if( opts.message instanceof( Array ) )
		{
			opts.messages = opts.message;
		}
		else if( $.isArray( opts.message ) )
		{
			opts.messages = opts.message;
		}
		
		// messages has been set with previous check
		// Make this array a list of errors
		if( opts.hasOwnProperty( 'messages' ) )
		{
			opts.message = sprintf( "<ol>\n%s\n</ol>", opts.messages.map(function(e){ return('<li>' + e + '</li>'); }).join( "\n" ) );
		}
		
		if( opts.append )
		{
			msgDiv.append(makeMessage(opts.type, opts.message));
		}
		else
		{
			msgDiv.html(makeMessage(opts.type, opts.message));
		}
		
		if( opts.type == 'error' )
		{
			msgDiv.addClass( 'error-shake' );
			setTimeout(function()
			{
				msgDiv.removeClass( 'error-shake' );
			}, 70000);
		}
		
		if( parseInt( opts.timeout ) > 0 )
		{
			var thisTimeout = parseInt( opts.timeout );
			setTimeout(function()
			{
				msgDiv.html( '' );
				if( typeof( opts.timeoutCallback ) === 'function' ) 
				{
					opts.timeoutCallback();
				}
			},thisTimeout);
		}
		else
		{
			setTimeout(function()
			{
				msgDiv.html( '' );
			},15000);
		}
		if( opts.scroll )
		{
			if( DEBUG ) console.log( "Scrolling to the top of the page..." );
			$('html, body').animate( { scrollTop: 0 }, 500 );
		}
		else
		{
			if( DEBUG ) console.log( "No scrolling..." );
		}
	};
	
	window.postfixAdminProgressBar = function() 
	{
		var xhr = new window.XMLHttpRequest();
		if( DEBUG ) console.log( "Initiating the progress bar." );
		if( DEBUG ) console.log( "Called from:\n" + (new Error).stack );
		$('#postfixadmin-progress').show().removeClass('done');
		xhr.upload.addEventListener('progress', function(evt) 
		{
			if( evt.lengthComputable ) 
			{
				var percentComplete = evt.loaded / evt.total;
				if( DEBUG ) console.log(percentComplete);
				$('#postfixadmin-progress').css({
					width: percentComplete * 100 + '%' });
				if( DEBUG ) console.log( "upload.addEventListener: " + percentComplete );
				if( percentComplete === 1 ) 
				{
					$('#postfixadmin-progress').addClass('done').hide();
				}
			}
		}, false);
		xhr.addEventListener('progress', function(evt) 
		{
			if( evt.lengthComputable ) 
			{
				var percentComplete = evt.loaded / evt.total;
				if( DEBUG ) console.log("addEventListener: " + percentComplete);
				$('#postfixadmin-progress').css({
					width: percentComplete * 100 + '%' });
				if( percentComplete === 1 ) 
				{
					$('#postfixadmin-progress').addClass('done').hide();
				}
			}
		}, false);
		return( xhr );
	};
	
	window.postfixAdminProgressBarStart = function()
	{
// 		$('#postfixadmin-progress').show().addClass('done');
		$('#postfixadmin-progress').show();
		$({property: 0}).animate({property: 85}, 
		{
			// Arbitrary time, which should well cover the time it takes to get response from server
			// Otherwise, well our progress bar will hang at 85% until we get a call to kill it
			duration: 4000,
			step: function() 
			{
				var _percent = Math.round( this.property );
				$('#postfixadmin-progress').css( 'width',  _percent + '%' );
			}
		});
	};
	
	window.postfixAdminProgressBarStop = function()
	{
		$({property: 85}).animate({property: 105}, 
		{
			duration: 1000,
			step: function() 
			{
				var _percent = Math.round( this.property );
				$('#postfixadmin-progress').css( 'width',  _percent + '%' );
				if( _percent == 105 ) 
				{
					$('#postfixadmin-progress').addClass('done');
				}
			},
			complete: function() 
			{
				$('#postfixadmin-progress').hide();
				$('#postfixadmin-progress').removeClass('done');
				$('#postfixadmin-progress').css( 'width', '0%' );
			}
		});
	};

	window.autoconfigShowHideArrow = function()
	{
		// Reset. Show them all
		$('table.server .autoconfig-command').show();
		// Hide first and last ones
		// This requires jQUery up to v3.3. Version 3.4 onward do not support :first and :last anymore
		$('.autoconfig-incoming:first .autoconfig-move-up').hide();
		$('.autoconfig-incoming:last .autoconfig-move-down').hide();
		$('.autoconfig-outgoing:first .autoconfig-move-up').hide();
		$('.autoconfig-outgoing:last .autoconfig-move-down').hide();
	};
	
	window.autoconfig_ajax_call = function( postData )
	{
		var prom = $.Deferred();
		$this = $(this);
		$.ajax({
			xhr: postfixAdminProgressBar(),
			type: "POST",
			url: "autoconfig.php",
			dataType: "json",
			data: postData,
			beforeSend: function(xhr)
			{
				xhr.overrideMimeType( "application/json; charset=utf-8" );
				postfixAdminProgressBarStart();
			},
			error: function(xhr, errType, ExceptionObject)
			{
				postfixAdminProgressBarStop();
				if( DEBUG ) console.log( "Returned error " + xhr.status + " with error type " + errType + " and exception object " + JSON.stringify( ExceptionObject ) );
				if( DEBUG ) console.log( "Current url is: " + xhr.responseURL );
				if( DEBUG ) console.log( "Http headers are: " + xhr.getAllResponseHeaders() );
				if( DEBUG ) console.log( "Response raw data is: \n" + xhr.responseText );
				// There was a redirect most likely due to some timeout
// 				if( xhr.getResponseHeader( 'Content-Type' ).toLowerCase().indexOf( 'text/html' ) >= 0 )
// 				{
// 					window.location.reload();
// 					return( true );
// 				}
				var msg = 'An unexpected error has occurred';
				showMessage( 'error', msg, { scroll: true });
				$this.addClass( 'error-shake' );
				prom.reject();
			},
			success: function(data, status, xhr)
			{
				postfixAdminProgressBarStop();
				if( data.error )
				{
					showMessage( 'error', data.error, { scroll: true });
					$this.addClass( 'error-shake' );
					setTimeout(function()
					{
						$this.removeClass( 'error-shake' );
					},5000);
					prom.reject();
				}
				else
				{
					if( data.success )
					{
						prom.resolve(data);
						showMessage( 'success', data.success, { scroll: true });
						if( DEBUG ) console.log( "save(): " + data.success );
					}
					else if( data.info )
					{
						showMessage( 'info', data.info, { scroll: true } );
						prom.resolve();
					}
					else
					{
						showMessage( 'info', data.msg, { scroll: true } );
						prom.resolve();
					}
				}
			}
		});
		return( prom.promise() );
	};
	
	$(document).on('click','#autoconfig_save', function(e)
	{
		e.preventDefault();
		$this = $(this);
		var data = {handler: 'autoconfig_save'};
		$('#autoconfig_form').serializeArrayAll().map(function(item)
		{
			if( data[ item.name ] !== undefined )
			{
				if( !data[ item.name ].push )
				{
					data[ item.name ] = [ data[item.name] ];
				}
				data[ item.name ].push( item.value );
			}
			else
			{
				data[ item.name ] = item.value;
			}
		});
		if( DEBUG ) console.log( "serialized data is: " + JSON.stringify( data ) );
		autoconfig_ajax_call( data ).done(function(data)
		{
			// Since this shared function is used for both saving (adding and updating) as well as deleting
			// we check if those data properties are returned by the server.
			// Those here are only returned if this is the result of an addition
			if( data.hasOwnProperty('config_id') )
			{
				if( $('#autoconfig_form input[name="config_id"]').length == 0 )
				{
					showMessage( 'error', 'An unexpected error has occurred (check web console for details)', { scroll: true });
					throw( "Unable to find the field \"config_id\" !" );
				}
				if( typeof( data.config_id ) !== 'undefined' && data.config_id.length > 0 )
				{
					// We force trigger change, because this is a hidden field and it hidden field do not trigger change
					$('#autoconfig_form input[name="config_id"]').val( data.config_id ).trigger('change');
				}
			}
			// Do the hosts
			var hostTypes = [ 'incoming', 'outgoing' ];
			for( var j = 0; j < hostTypes.length; j++ )
			{
				var hostType = hostTypes[j];
				if( DEBUG ) console.log( "Checking host of type " + hostType );
				if( data.hasOwnProperty(hostType + '_server') && Array.isArray( data[hostType + '_server'] ) )
				{
					var thoseHosts = $('.autoconfig-' + hostType);
					var dataHosts = data[hostType + '_server'];
					if( thoseHosts.length == 0 )
					{
						showMessage( 'error', 'An unexpected error has occurred (check web console for details)', { scroll: true });
						throw( "Unable to find any hosts block in our form!" );
					}
					else if( dataHosts.length != thoseHosts.length )
					{
						
						showMessage( 'error', 'An unexpected error has occurred (check web console for details)', { scroll: true });
						throw( "Total of " + hostType + " servers returned from the server (" + dataHosts.length + ") do not match the total hosts we have in our form (" + thoseHosts.length + ")." );
					}
					dataHosts.forEach(function(def, index)
					{
						if( DEBUG ) console.log( "def contains: " + JSON.stringify( def ) );
						if( !def.hasOwnProperty('config_id') ||
							!def.hasOwnProperty('hostname') ||
							!def.hasOwnProperty('port') )
						{
							if( DEBUG ) console.error( "Something is wrong. Data received is missing fields config_id, or hostname or port" );
							return( false );
						}
						thoseHosts.each(function(offset, obj)
						{
							var dom = $(obj);
							var hostId = dom.find('input[name="host_id[]"]');
							var hostName = dom.find('input[name="hostname[]"]');
							var hostPort = dom.find('input[name="port[]"]');
							if( !hostId.length )
							{
								showMessage( 'error', 'An unexpected error has occurred (check web console for details)', { scroll: true });
								throw( "Unable to find host id field for host type \"" + hostType + "\" at offset " + i );
							}
							if( !hostName.length )
							{
								showMessage( 'error', 'An unexpected error has occurred (check web console for details)', { scroll: true });
								throw( "Unable to find host name field for host type \"" + hostType + "\" at offset " + i );
							}
							if( !hostPort.length )
							{
								showMessage( 'error', 'An unexpected error has occurred (check web console for details)', { scroll: true });
								throw( "Unable to find host port field for host type \"" + hostType + "\" at offset " + i );
							}
							// We found our match: no id, hostname and port match
							if( hostId.val().length == 0 && hostName.val() == def.hostname && hostPort.val() == def.port )
							{
								if( DEBUG ) console.log( "Setting host id " + def.host_id + " to host name " + hostName.val() + " with port " + hostPort.val() );
								hostId.val( def.host_id );
								// exit the loop
								return( false );
							}
						});
					});
				}
				else
				{
					if( DEBUG ) console.error( "Something is wrong. Data received for hosts of type " + hostType + " does not exist or is not an array." );
				}
			}
		
			// Now, do the texts
			var textTypes = ['instruction', 'documentation'];
			for( var j = 0; j < textTypes.length; j++ )
			{
				var textType = textTypes[j];
				if( DEBUG ) console.log( "Checking text of type " + textType );
				if( data.hasOwnProperty(textType) && Array.isArray( data[textType] ) )
				{
					var dataTexts = data[textType];
					var thoseTextIds = $('input[name="' + textType + '_id[]"]');
					var thoseTextLang = $('select[name="' + textType + '_lang[]"]');
					var thoseTextData = $('textarea[name="' + textType + '_text[]"]');
					// The array could very well be empty
					dataTexts.forEach(function(def, index)
					{
						if( !def.hasOwnProperty('id') ||
							!def.hasOwnProperty('type') ||
							!def.hasOwnProperty('lang') )
						{
							if( DEBUG ) console.error( "Something is wrong. Data received is missing fields id, or type or lang" );
							return( false );
						}
						for( var k = 0; k < thoseTextIds.length; k++ )
						{
							var textId = thoseTextIds.eq(k);
							var textLang = thoseTextLang.eq(k);
							// Found a match
							if( textId.val().length == 0 && textLang.val() == def.lang )
							{
								if( DEBUG ) console.log( "Setting text id " + def.id + " to text with type " + textType + " and language " + def.lang );
								textId.val( def.id );
								return( false );
							}
						}
					});
				}
				else
				{
					if( DEBUG ) console.error( "Something is wrong. Data received for " + textType + " text does not exist or is not an array." );
				}
			}
		}).fail(function()
		{
			// Nothing for now
		});
	});
	
	$(document).on('click','#autoconfig_remove', function(e)
	{
		e.preventDefault();
		var data = 
		{
		handler: 'autoconfig_remove',
		config_id: $('input[name="config_id"]').val(),
		token: $('input[name="token"]').val(),
		};
		if( DEBUG ) console.log( "Data to be sent is: " + JSON.stringify( data ) );
		autoconfig_ajax_call( data ).done(function(data)
		{
			// Reload the page, but without the query string at the end
			window.location.href = window.location.pathname;
		}).fail(function()
		{
			// Nothing for now
		});
	});
	
	$(document).on('click', '#autoconfig_cancel', function(e)
	{
		e.preventDefault();
		window.location.href = 'list.php?table=domain';
		return( true );
	});
	
	// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/random
	window.getRandomInt = function(min, max) 
	{
		return( Math.floor( Math.random() * Math.floor((max - min) + min) ) );
	}	
	/*
	I could have just created one function and called it 
	*/
	// Add and remove hosts
	$(document).on('click','.autoconfig-server-add',function(e)
	{
		e.preventDefault();
		var row = $(this).closest('table.server').closest('tr');
		if( !row.length )
		{
			throw( "Unable to find the current enclosing row." );
		}
		var clone = row.clone();
		clone.find('select,input[type!="hidden"],textarea').each(function(i,item)
		{
			$(item).val( '' );
		});
		// We need to remove the host_id so it can be treated as a new host and not an update of an existing one
		clone.find('input[name="host_id[]"]').val( '' );
		// Set default value and trigger change, which will call an event handler that will hide/show the section on pop3
		clone.find('.host_type').val('imap').trigger('change');
		var optionLabels = ['leave_messages_on_server', 'download_on_biff', 'days_to_leave_messages_on_server', 'check_interval'];
		optionLabels.forEach(function(fieldName, index)
		{
			if( DEBUG ) console.log( "Checking field name " + fieldName );
			var thisField = clone.find('input[name="' + fieldName + '[]"]');
			if( thisField.length > 0 )
			{
				var forThisField = $('label[for="' + thisField.attr('id') + '"]', clone);
				if( forThisField.length > 0 )
				{
					if( DEBUG ) console.log( "Field is " + thisField.html() + "\nLabel field is: " + forThisField.html() );
					thisField.attr('id', 'autoconfig_' + fieldName + '_' + getRandomInt(100,1000));
					maxAttempt = 10;
					if( DEBUG ) console.log( "Checking if generated id exists: " + thisField.attr('id') );
					while( $('#' + thisField.attr('id'), clone).length > 0 && ++maxAttempt < 10 )
					{
						thisField.attr('id', 'autoconfig_' + fieldName + '_' + getRandomInt(100,1000));
					}
					forThisField.attr('for', thisField.attr('id'));
					if( DEBUG ) console.log( "Final generated id is: " + thisField.attr('id') );
				}
				else
				{
					if( DEBUG ) console.error( "Unable to find label element for field name." );
				}
			}
		});
		
		clone.insertAfter( row );
		autoconfigShowHideArrow();
		$('html, body').animate( { scrollTop: clone.offset().top }, 500 );
		return( true );
	});
	
	$(document).on('click','.autoconfig-server-remove',function(e)
	{
		e.preventDefault();
		var row = $(this).closest('table.server').closest('tr');
		if( !row.length )
		{
			throw( "Unable to find the current enclosing row." );
		}
		// Check if there are at least 2 elements, so that after at least one remain
		var re = new RegExp('(autoconfig-(?:incoming|outgoing))-server');
		var res = $(this).attr('class').match( re );
		console.log( res );
		if( res == null )
		{
			throw( "Cannot find class \"autoconfig-incoming-server\" or class \"autoconfig-outgoing-server\" in our clicked element." );
		}
		// Now find how many elements we have with this class
		var total = $('tr.' + res[1]).length;
		if( total < 2 )
		{
			row.addClass('autoconfig-error-shake');
			setTimeout(function()
			{
				row.removeClass('autoconfig-error-shake');
			},1000);
			return( false );
		}
		row.remove();
		autoconfigShowHideArrow();
	});
	
	// Add and remove account enable instructions or support documentation
	$(document).on('click','.autoconfig-locale-text-add',function(e)
	{
		e.preventDefault();
		var row = $(this).closest('tr');
		if( !row.length )
		{
			throw( "Unable to find the current enclosing row." );
		}
		var clone = row.clone();
		clone.find('select,input[type!="hidden"],textarea').each(function(i,item)
		{
			$(item).val( '' );
		});
		// We need to remove the host_id so it can be treated as a new text and not an update of an existing one
		clone.find('input[name$="_id[]"]').val( '' );
		clone.insertAfter( row );
		$('html, body').animate( { scrollTop: clone.offset().top }, 500 );
	});

	$(document).on('click','.autoconfig-locale-text-remove',function(e)
	{
		e.preventDefault();
		var row = $(this).closest('tr');
		if( !row.length )
		{
			throw( "Unable to find the current enclosing row." );
		}
		var re = new RegExp('(autoconfig-(instruction|documentation))');
		var res = $(this).attr('class').match( re );
		if( res == null )
		{
			throw( "Cannot find class \"autoconfig-instruction\" or class \"autoconfig-documentation\" in our clicked element." );
		}
		var textType = res[2];
		var total = $('tr.' + res[1]).length;
		if( DEBUG ) console.log( total + " rows found for text type " + textType );
		if( total < 2 )
		{
			if( DEBUG ) console.log( "text remove: one left" );
			if( DEBUG ) console.log( "Getting lang object with " + '[name="' + textType + '_lang[]"]' );
			var textLang = row.find('[name="' + textType + '_lang[]"]');
			if( DEBUG ) console.log( "Getting text object with " + '[name="' + textType + '_text[]"]' );
			var textData = row.find('[name="' + textType + '_text[]"]');
			if( DEBUG ) console.log( "text remove: found lang and text field? " + ( ( textLang.length > 0 && textData.length > 0 ) ? "Yes" : "No" ) );
			// This is remaining default tet row and there is no more data
			if( ( textLang.val() === null || ( textLang.val() !== null && textLang.val() == '' ) ) && $.trim(textData.val()) == '' )
			{
				if( DEBUG ) console.log( "text remove: lang and text fields are empty already, error shake it" );
				row.addClass('autoconfig-error-shake');
				setTimeout(function()
				{
					row.removeClass('autoconfig-error-shake');
				},1000);
			}
			else
			{
				if( DEBUG ) console.log( "text remove: empty fields" );
				textLang.val( '' );
				textData.val( '' );
			}
			return( false );
		}
		row.remove();
	});
	
	$(document).on('click', '#copy_provider_value', function(e)
	{
		e.preventDefault();
		var orgField = $('input[name="organisation"]');
		var providerNameField = $('input[name="provider_name"]');
		if( !orgField.length || !providerNameField.length )
		{
			throw( "Unable to find either the provider name field or the organisation field!" );
		}
		if( providerNameField.val().length == 0 )
		{
			return( false );
		}
		orgField.val( providerNameField.val() );
		return( true );
	});
	
	$(document).on('click', '#autoconfig_toggle_select_all_domains', function(e)
	{
		e.preventDefault();
		// if( DEBUG ) console.log( "provider_domain options length: " + $('#autoconfig_provider_domain option').length + " and disabled options are: " + $('#autoconfig_provider_domain option:disabled').length + " and selected options are: " + $('#autoconfig_provider_domain option:selected').length );
		if( $('#autoconfig_provider_domain option:selected').length > 0 )
		{
			$('#autoconfig_provider_domain option').prop('selected', false);
		}
		else
		{
			if( $('#autoconfig_provider_domain option:disabled').length == $('#autoconfig_provider_domain option').length )
			{
				var row = $(this).closest('tr');
				row.addClass('error-shake');
				setTimeout(function()
				{
					row.removeClass('error-shake');
				},500);
				return( false );
			}
			else
			{
				$('#autoconfig_provider_domain option:not(:disabled)').prop('selected', true);
			}
		}
	});
	
	$(document).on('change', '#autoconfig_form .host_type', function(e)
	{
		var typeValue = $(this).val();
		// We get the enclosing table object to set some limiting context to the host_pop3 selector below
		var tbl = $(this).closest('table');
		if( typeValue == 'imap' )
		{
			$('.host_pop3', tbl).hide();
		}
		else
		{
			$('.host_pop3', tbl).show();
		}
	});
	
	$(document).on('change', '#autoconfig_form .username_template', function(e)
	{
		var usernameField = $(this).closest('.server').find('input[name="username[]"]');
		if( usernameField.length == 0 )
		{
			throw( "Unable to find the username field!" );
		}
		usernameField.val( $(this).val() );
	});
	
	$(document).on('change', '#autoconfig_form select[name="jump_to"]', function(e)
	{
		var id = $(this).val();
		var re = new RegExp( '([a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12})' );
		// This will display an empty form
		if( id.length == 0 )
		{
			window.location.href = 'autoconfig.php';
		}
		else if( id.match( re ) )
		{
			window.location.href = 'autoconfig.php?config_id=' + encodeURIComponent( id );
		}
	});
	
	// Upon change in the content of the hidden config_id, enable or disable the "Remove" buttton
	// The Remove button can only be used if this is for an existing configuration obviously
	$(document).on('change', '#autoconfig_form input[name="config_id"]', function(e)
	{
		if( DEBUG ) console.log( "Config id field contains \"" + $(this).val() + "\"." );
		if( $(this).val().length == 0 || $(this).val().match( /^[[:blank:]\t]*$/ ) )
		{
			$('#autoconfig_remove').attr( 'disabled', true );
		}
		else
		{
			$('#autoconfig_remove').attr( 'disabled', false );
		}
	});
	
	$(document).on('click', '.autoconfig-move-up', function(e)
	{
		e.preventDefault();
		var row = $(this).closest('.server').closest('tr');
		if( row.prev().length == 0 || ( !row.prev().hasClass('autoconfig-incoming') && !row.prev().hasClass('autoconfig-outgoing') ) )
		{
			return( false );
		}
		row.insertBefore( row.prev() );
		$('html, body').animate( { scrollTop: row.offset().top }, 500 );
		autoconfigShowHideArrow();
	});
	
	$(document).on('click', '.autoconfig-move-down', function(e)
	{
		e.preventDefault();
		var row = $(this).closest('.server').closest('tr');
		if( row.next().length == 0 || ( !row.next().hasClass('autoconfig-incoming') && !row.next().hasClass('autoconfig-outgoing') ) )
		{
			return( false );
		}
		row.insertAfter( row.next() );
		$('html, body').animate( { scrollTop: row.offset().top }, 500 );
		autoconfigShowHideArrow();
	});
	
	window.checkExistingTextLanguage = function(opts = {})
	{
		if( typeof( opts ) !== 'object' )
		{
			throw( "Parameters provided is not an object. Call checkExistingTextLanguage like this: checkExistingTextLanguage({ type: 'instruction', lang: 'fr', caller: $(this) })" );
		}
		var textType = opts.type;
		var callerMenu = opts.caller;
		var langToCheck = opts.lang;
		if( DEBUG ) console.log( "checkExistingTextLanguage() checking text type " + textType + " for language " + langToCheck );
		if( typeof( textType ) === 'undefined' )
		{
			throw( "No text type was provided." );
		}
		var langMenu = $('select[name="' + textType + '_lang[]"]');
		if( DEBUG ) console.log( "checkExistingTextLanguage() Found " + langMenu.length + " language menu(s)." );
		if( langMenu.length == 0 )
		{
			throw( "Could not find any language menu for this text type " + textType );
		}
		if( langMenu.length == 1 )
		{
			return( true );
		}
		var alreadySelected = false;
		if( DEBUG ) console.log( "checkExistingTextLanguage() checking each existing language menu." );
		langMenu.each(function(offset, menuObject)
		{
			if( $(menuObject).is( callerMenu ) )
			{
				if( DEBUG ) console.log( "checkExistingTextLanguage() skipping because this is our caller menu." );
				return( true );
			}
			// Found a match, stop there
			else if( $(menuObject).val() == langToCheck )
			{
				if( DEBUG ) console.log( "checkExistingTextLanguage() Found match with this menu value (" + $(menuObject).val() + ") matching the language to check \"" + langToCheck + "\"." );
				alreadySelected = true;
				return( false );
			}
		});
		if( DEBUG ) console.log( "checkExistingTextLanguage() returning: " + !alreadySelected );
		return( !alreadySelected );
	};
	
	// Upon selection or change of a language, we check it has not been selected already
	$(document).on('change', 'select[name="instruction_lang[]"]', function(e)
	{
		if( !checkExistingTextLanguage({ type: 'instruction', lang: $(this).val(), caller: $(this) }) )
		{
			$(this).addClass('error-shake');
			// <i class="fas fa-exclamation-triangle"></i>
			var warning = $('<i/>',
			{
			class: 'fas fa-exclamation-triangle fa-2x',
			style: 'color: red; font-size: 20px;',
			});
			warning.insertAfter( $(this) );
			var that = $(this);
			setTimeout(function()
			{
				that.removeClass('error-shake');
				warning.remove();
			},5000);
			$(this).val('');
			return( false );
		}
		return( true );
	});
	
	window.toggleCertFiles = function(option)
	{
		if( typeof( option ) === 'undefined' )
		{
			return( false );
		}
		if( option == 'local' )
		{
			$('.cert_files').show();
		}
		else
		{
			$('.cert_files').hide();
		}
	};
	
	$(document).on('change', 'select[name="sign_option"]', function(e)
	{
		toggleCertFiles( $(this).val() );
	});
	
	// Need to trigger the change for the host type menu
	if( $('#autoconfig_form .host_type').length > 0 )
	{
		$('#autoconfig_form .host_type').trigger('change');
	}
	if( $('#autoconfig_form input[name="config_id"]').length > 0 )
	{
		$('#autoconfig_form input[name="config_id"]').trigger('change');
	}
	// Hide useless up/down arrows
	autoconfigShowHideArrow();
	toggleCertFiles( $('select[name="sign_option"]').val() );
});
