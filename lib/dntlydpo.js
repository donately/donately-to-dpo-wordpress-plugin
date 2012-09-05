(function($){

	window.dntlydpo = {};
	var dntlydpo = window.dntlydpo;
	
	dntlydpo.initialize = function() {
		dntlydpo.setElements();
		dntlydpo.resetDPO();
		dntlydpo.syncDonations();
	};
	
	dntlydpo.setElements = function() {
		dntlydpo.elems = {};
		dntlydpo.elems.dpo_reset_token_btn = jQuery('#dpo-reset-user');
		dntlydpo.elems.sync_donations_btn = jQuery('#dntly-sync-donations');
		dntlydpo.properties = {};
	};

	dntlydpo.syncDonations = function() {
		dntlydpo.elems.sync_donations_btn.bind('click', function(e) {
			e.preventDefault();
			jQuery.ajax({
				'type'  : 'post',
				'url'		: ajaxurl,
				'data'	: {
								'action'	: 'dntly_sync_donations'
							  },
				'success'	: function(response) { console.log(response); dntly.refreshLog(0); },
				'error'	: function(response) { alert('syncDonations Error'); console.log(response); dntly.refreshLog(0);}
			});
		});
	}

	dntlydpo.resetDPO = function() {
		dntlydpo.elems.dpo_reset_token_btn.bind('click', function(e) {
			e.preventDefault();
			console.log('resetDPO');
			jQuery('#dpo-form #dpo-user-name').val('');
			jQuery('#dpo-form #dpo-user-password').val('');
			jQuery('#dpo-form').submit();
		});
	}

	jQuery(document).ready(function() {
		dntlydpo.initialize();
		dntly.refreshLog(0);
	});

})(jQuery);