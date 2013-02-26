/**
 * Toggle JS
 *
 * @package Custom Field Toggle
 *
 */

jQuery(document).ready(function($){
	
	/**
	 * Handles the toggle of the custom field value.
	 *
	 * @handler   click
	 * @return    void
	 *
	 */
	$('.ui-toggle').click(function(){
		$this = $(this)
		state = $this.hasClass('ui-state-on');
		$this.toggleClass('ui-state-on');
		$this.toggleClass('ui-state-off');

		id = $('#post_ID').val();
		val = "&val="+(state ? 0 : 1);
		field = "&field="+$this.prev().val();
		
		$.ajax({
			url: ajaxurl,
			data: "action=toggle_option&post_id="+id+field+val,
			success: function(response){
				if (typeof response != 'object') response = $.parseJSON(response);

				if (response.status != true) {
					$this.toggleClass('ui-state-on');
					$this.toggleClass('ui-state-off');
				}
			}
		})
	});
});