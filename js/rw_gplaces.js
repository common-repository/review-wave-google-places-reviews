function review_wave_init_gplaces(wrap_sel) {
	var $wrap = jQuery(wrap_sel);

	if (!$wrap.length || !$wrap.find('.gp-place-lookup').length) return;

	$wrap.find('.gp-place-lookup').each(function(){
		var $places = jQuery(this).closest('.rw-gplaces');
		if ($places.data('gp-auto')) return;

		var autocomplete = new google.maps.places.Autocomplete(this);
		autocomplete.addListener('place_changed', function(){
			var place = autocomplete.getPlace();

			$wrap.find('.gp-place-name').text(place.name).val(place.name);
			$wrap.find('.gp-place-id').text(place.place_id).val(place.place_id);
		});

		$places.data('gp-auto', autocomplete);
	});

	$wrap.find('.gp-place-type').change(function(e){
		var value = jQuery(this).val();
		jQuery(this).closest('.rw-gplaces').data('gp-auto').setTypes(value.length ? [value] : []);
	});
};
