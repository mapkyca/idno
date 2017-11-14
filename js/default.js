/**
 Helper Javascript functions for Known
 
 If you need to add your own JavaScript, the best thing to do is to create your own js files
 and reference them from a custom plugin or template.
 
 IMPORTANT: This file isn't loaded directly, for changes to show you must generate a minified
 version. E.g.
 
 yui-compressor default.js > default.min.js
 
 @package idno
 @subpackage core
 */

/**
 * Globals
 */

var isCreateFormVisible = false;

/** Known security object */
function Security() {}

/** Perform a HEAD request on the current page and pass the token to a given callback */
Security.getCSRFToken = function (callback, pageurl) {

    if (pageurl == undefined)
	pageurl = known.currentPageUrl;

    $.ajax({
	type: "GET",
	data: {url: pageurl},
	url: known.config.displayUrl + 'service/security/csrftoken/'
    }).done(function (message, text, jqXHR) {


	callback(message.token, message.time);
    });
}

/** Refresh all security tokens */
Security.refreshTokens = function () {

    $('.known-security-token').each(function () {
	var form = $(this).closest('form');

	Security.getCSRFToken(function (token, ts) {

	    form.find('input[name=__bTk]').val(token);
	    form.find('input[name=__bTs]').val(ts);

	}, form.find('input[name=__bTa]').val());
    });
}

setInterval(function () {
    Security.refreshTokens();
}, 300000);

/** 
 * Initialise ACL controls
 */
Security.activateACLControls = function () {
    $('.acl-ctrl-option').each(function () {
	if ($(this).data('acl') == $(this).closest('.access-control-block').find('input').val()) {
	    $(this).closest('.btn-group').find('.dropdown-toggle').html($(this).html() + ' <span class="caret"></span>');
	}
    });
    $('.acl-ctrl-option').on('click', function () {
	$(this).closest('.access-control-block').find('input').val($(this).data('acl'));
	$(this).closest('.btn-group').find('.dropdown-toggle').html($(this).html() + ' <span class="caret"></span>');
	$(this).closest('.btn-group').find('.dropdown-toggle').click();
    });
}

$(document).ready(function () {
    Security.activateACLControls();
});


/** Known Javascript logging */
function Logger() {}

Logger.log = function (message, level) {

    if (typeof level === 'undefined')
	level = 'INFO';

    switch (level.toUpperCase()) {
	case "ALERT":
	case "ERROR":
	case "EXCEPTION":
	    level = "ERROR";
	    console.error(level + ": " + message);
	    break;
	
	case "WARN":
	case "WARNING": 
	    level = "WARNING";
	    console.warn(level + ": " + message);
	    break;
	    
	default: 
	    level = "INFO";
	    console.log(level + ": " + message);
    }

    Security.getCSRFToken(function (token, ts) {
	$.ajax({
	    type: "POST",
	    data: {
		    level: level,
		    message: message,
		    __bTk: token,
		    __bTs: ts
		},
	    url: known.config.displayUrl + 'service/system/log/',
	});
    }, known.config.displayUrl + 'service/system/log/');

}

Logger.info = function(message) {
    Logger.log(message, 'INFO');
}

Logger.warn = function(message) {
    Logger.log(message, 'WARN');
}

Logger.error = function(message) {
    Logger.log(message, 'ERROR');
}

Logger.errorHandler = function (error) {

    var stack = error.error.stack;
    var message = error.error.toString();

    if (stack) {
	message += '\n' + stack;
    }

    console.error(error);
    Logger.log(message, 'ERROR');
}

/** Default error/exception handler */
window.addEventListener('error', function (e) { Logger.errorHandler(e); });


/** Known notifications */
function Notifications() {}

/**
 * Poll for new notifications
 */
Notifications.poll = function () {
    $.get(known.config.displayUrl + 'service/notifications/new-notifications')
	    .done(function (data) {
		//console.log("Polling for new notifications succeeded");
		//console.log(data);
		if (data.notifications)
		    if (data.notifications.length > 0) {
			for (i = 0; i < data.notifications.length; i++) {
			    var title = data.notifications[i].title;
			    var body = data.notifications[i].body;
			    var icon = data.notifications[i].icon;
			    var link = data.notifications[i].link;
			    try {
				var notification = new Notification(title, {
				    icon: icon,
				    body: body,
				    data: link
				});
				notification.onclick = function(e) {
				    window.location.href = link;
				}
			    } catch (e) {
				// We have to use service worker, as "new Notification" doesn't work anymore
				navigator.serviceWorker.ready.then(function(registration) {
				    registration.showNotification(title, {
					icon: icon,
					body: body,
					data: link
				    });
				});
			    }
			}
		    }
	    })
	    .fail(function (data) {
		//console.log("Polling for new notifications failed");
	    });
}

Notifications.enable = function (opt_dontAsk) {
    if (!known.session.loggedIn) {
	return;
    }

    if (!("Notification" in window)) {
	console.log("The Notification API is not supported by this browser");
	return;
    }
    
    // New method click handling
    self.addEventListener('notificationclick', function(event) {
	window.location.href = event.notification.data;
    });
    
    if (Notification.permission !== 'denied' && Notification.permission !== 'granted' && !opt_dontAsk) {
	Notification.requestPermission(function (permission) {
	    // If the user accepts, let's create a notification
	    if (permission === "granted") {
		setInterval(Notifications.poll, 30000);
	    }
	});
    } else if (Notification.permission === 'granted') {
	setInterval(Notifications.poll, 30000);
    }
}

/**
 * Have notifications been granted?
 * @returns {Boolean}
 */
Notifications.isEnabled = function () {
    if (!known.session.loggedIn) {
	return false;
    }

    if (!("Notification" in window)) {
	console.log("The Notification API is not supported by this browser");
	return false;
    }
    if (Notification.permission === 'granted') {
	return true;
    }

    return false;
}

// Backwards compatibility for those who already have notifications installed
function doPoll() {
    Notifications.poll();
}

// If we've granted permission (via the notifications page), then lets configure a poll for new notifications
$(document).ready(function () {
    if (Notifications.isEnabled()) {
	Notifications.enable(true); // Don't pester asking for permission, only do that on notifications page
    }
});


/**
 *** Content creation
 */

function bindControls() {
    $('.acl-ctrl-option').click(function () {
	$('#access-control-id').val($(this).attr('data-acl'));
	$('#acl-text').html($(this).html());
    });
    $('.syndication-toggle input[type=checkbox]').bootstrapToggle();
    $('.ignore-this').hide();

    Security.activateACLControls();
    Template.enableFormCandy();
    Template.enableRichTextRequired();

    $('#contentCreate .form-control').first().focus();
}

function contentCreateForm(plugin, editUrl) {
    if (isCreateFormVisible) {
	// Ignore additional clicks on create button
	return;
    }

    isCreateFormVisible = true;
    $.ajax(editUrl, {
	dataType: 'html',
	success: function (data) {
	    $('#contentCreate').html(data).slideDown(400);
	    $('#contentTypeButtonBar').slideUp(400);
	    window.contentCreateType = plugin;
	    window.contentPage = true;

	    bindControls();
	},
	error: function (error) {
	    $('#contentTypeButtonBar').slideDown(400);
	    isCreateFormVisible = false;
	}

    });
}

function hideContentCreateForm() {
    isCreateFormVisible = false;
    if (window.contentPage == true) {
	$('#contentTypeButtonBar').slideDown(200);
	$('#contentCreate').slideUp(200);
    } else {
	//window.close(); // Will only fire for child windows
	if (window.history.length > 1) {
	    window.history.back();
	}
    }
}


/**
 * Strip HTML from string
 * @param html
 * @returns {string}
 */
function knownStripHTML(html) {
    var tmp = document.createElement("DIV");
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || "";
}

/**
 * Are we in an iFrame?
 * @returns {boolean}
 */
function inIframe() {
    try {
	return window.self !== window.top;
    } catch (e) {
	return true;
    }
}

/**
 * Decode HTML elements
 * @param encodedString
 * @returns {string}
 */
function htmlEntityDecode(encodedString) {
    var textArea = document.createElement('textarea');
    textArea.innerHTML = encodedString;
    return textArea.value;
}

/*
 * Shim so that JS functions can get the current site URL
 * @deprecated Use known.config.displayUrl
 */
function wwwroot() {
    return known.config.displayUrl;
}

/**
 * Shim so JS functions can tell if this is a logged in session or not.
 * @deprecated Use known.session.loggedin
 * @returns {Boolean}
 */
function isLoggedIn() {
    if (typeof known !== 'undefined')
	if (known.session.loggedIn) {
	    return true;
	}
    return false;
}

/**
 * Actions to perform on page load
 */
$(document).ready(function () {
    var url = $('#soft-forward').attr('href');

    if (!!url) {
	window.location = url;
    }

    if (known.session.loggedIn) {
	//TODO(ben) re-enable in a smarter way
	//Notifications.enable(true);
    }
});