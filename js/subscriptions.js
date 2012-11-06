jQuery( document ).ready( function($) {

	$('#postlist').on('click', '.toggle-sub-status', function(ev) {
		ev.preventDefault();
		var btn = $(this),
			thispost = btn.parents('.post'),
			id = thispost.attr('id').replace('prologue-', '');
		$(this).text('...');
		toggle_sub_status( thispost, id );
	});

	function toggle_sub_status( post, id ) {
		$.post( subscriptions.ajaxurl, {
			'action' : 'toggle_sub_status',
			'post_id' : id
		}, function( response ) {
			post.find('.toggle-sub-status').text( response );

		}, 'text' );
	}

});