(function(){

	$(document).ready(function() {

		var $searchElasticSettings = $('#searchElasticSettings');

		function loadServers($element) {
			$.get(
				OC.generateUrl('apps/search_elastic/settings/servers')
			).done(function( result ) {
				$element.val(result.servers);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not load servers'));
			});
		}
		function saveServers(servers) {
			$.post(
				OC.generateUrl('apps/search_elastic/settings/servers'),
				{ servers: servers }
			).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not save servers'));
			});
		}
		function getScanExternalStorages($element) {
			$.get(
				OC.generateUrl('apps/search_elastic/settings/scanExternalStorages')
			).done(function( result ) {
				$element.prop('checked', result.scanExternalStorages);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not get scanExternalStorages'));
			});
		}
		function toggleScanExternalStorages($element) {
			$.post(
				OC.generateUrl('apps/search_elastic/settings/scanExternalStorages'),
				{ scanExternalStorages: $element.prop('checked') }
			).done(function( result ) {
				$element.prop('checked', result.scanExternalStorages);
			}).fail(function( result ) {
				$element.prop('checked', !$element.prop('checked'));
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not set scanExternalStorages'));
			});
		}
		function renderStatus(stats) {
			console.debug(stats);
			var count = stats.oc_index.total.docs.count;
			var size_in_bytes = stats.oc_index.total.store.size_in_bytes;
			var countIndexed = stats.countIndexed;
			$searchElasticSettings.find('.message').text(
				n('search_elastic', '{countIndexed} nodes marked as indexed, {count} document in index uses {size} bytes', '{countIndexed} nodes marked as indexed, {count} documents in index using {size} bytes', count, {count: count, countIndexed:countIndexed, size: size_in_bytes})
			);
		}
		function checkStatus() {
			$searchElasticSettings.find('.icon').addClass('icon-loading-small').removeClass('error success');
			$.get(
				OC.generateUrl('apps/search_elastic/settings/status')
			).done(function( result ) {
				$searchElasticSettings.find('.icon').addClass('success').removeClass('error icon-loading-small');
				$searchElasticSettings.find('button').text(t('search_elastic', 'Reset index'));
				renderStatus(result.stats);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				$searchElasticSettings.find('.message').text(result.responseJSON.message);
				$searchElasticSettings.find('button').text(t('search_elastic', 'Setup index'));
			});
		}
		function setup() {
			$searchElasticSettings.find('.icon').addClass('icon-loading-small').removeClass('error success');
			return $.post(
				OC.generateUrl('apps/search_elastic/setup')
			).done(function( result ) {
				$searchElasticSettings.find('.icon').addClass('success').removeClass('error icon-loading-small');
				$searchElasticSettings.find('button').text(t('search_elastic', 'Reset index'));
				renderStatus(result.stats);
			}).fail(function( result ) {
				$searchElasticSettings.find('.icon').addClass('error').removeClass('success icon-loading-small');
				console.error(result);
				OC.dialogs.alert(result.responseJSON.message, t('search_elastic', 'Could not setup indexes'));
			});
		}

		var timer;

		$searchElasticSettings.on('keyup', 'input', function(e) {
			clearTimeout(timer);
			var that = this;
			//highlightInput($(this));
			timer = setTimeout(function() {
				saveServers($(that).val());
				checkStatus();
			}, 1500);
		});

		$searchElasticSettings.on('click', 'button', function(e) {
			setup();
		});

		$searchElasticSettings.on('click', 'input[type="checkbox"]', function(e) {
			toggleScanExternalStorages($searchElasticSettings.find('input[type="checkbox"]'));
		});

		loadServers($searchElasticSettings.find('input[type="text"]'));
		getScanExternalStorages($searchElasticSettings.find('input[type="checkbox"]'));
		checkStatus();

	});

})();