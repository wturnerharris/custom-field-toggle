/**
 * Admin JS
 *
 * @package Custom Field Toggle
 *
 */

jQuery(document).ready(function($){
	
	/**
	 * Monitor text changes in the form and disable page template option based on post type field.
	 *
	 * @handler   keyup
	 * @return    void
	 *
	 */
	$('#ToggleForm').keyup( function (){
		post_type = $('input[name="post_type"]');
		tpl = $('select[name="template"]');
		
		if ( post_type.val() != "page" ) {
			tpl.attr('disabled','true');
			tpl.find('option:not([value="default"])').removeAttr('selected');
			tpl.find('option[value="default"]').attr('selected', 'true');
		} else {
			tpl.removeAttr('disabled');
		}
	}).trigger('keyup');
	
	m = $('#ToggleMessage');
	if (m.length) {
		if (m.text() != '') m.show().delay(2500).fadeOut(1500);
		else m.hide();
	}
});
