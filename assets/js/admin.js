(function ($) {
	'use strict';

	var running = false;

	function setProgress(processed, total, message) {
		var percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
		$('#vmbls-progress-panel').prop('hidden', false);
		$('#vmbls-progress-bar').css('width', percent + '%');
		$('#vmbls-progress-percent').text(percent + '%');
		$('#vmbls-progress-text').text(message + ' ' + processed + '/' + total);
	}

	function fail(message) {
		running = false;
		$('#vmbls-start-scan, #vmbls-start-scan-secondary').prop('disabled', false);
		$('#vmbls-progress-text').text(message || vmblsAdmin.i18n.failed);
	}

	function runBatch(scanId, batch, total) {
		$.post(vmblsAdmin.ajaxUrl, {
			action: 'vmbls_scan_batch',
			nonce: vmblsAdmin.nonce,
			scan_id: scanId,
			batch: batch
		}).done(function (response) {
			if (!response.success) {
				fail(response.data && response.data.message);
				return;
			}

			setProgress(response.data.processed, total, response.data.done ? vmblsAdmin.i18n.completed : vmblsAdmin.i18n.scanning);
			if (response.data.done) {
				running = false;
				window.location.href = window.location.pathname + '?page=viralmaisuriyaa-broken-link-scanner&scan_id=' + scanId;
				return;
			}
			runBatch(scanId, batch + 1, total);
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : vmblsAdmin.i18n.failed;
			fail(message);
		});
	}

	function startScan() {
		if (running) {
			return;
		}
		running = true;
		$('#vmbls-start-scan, #vmbls-start-scan-secondary').prop('disabled', true);
		setProgress(0, 0, vmblsAdmin.i18n.starting);

		$.post(vmblsAdmin.ajaxUrl, {
			action: 'vmbls_start_scan',
			nonce: vmblsAdmin.nonce
		}).done(function (response) {
			if (!response.success) {
				fail(response.data && response.data.message);
				return;
			}
			if (response.data.done) {
				window.location.href = window.location.pathname + '?page=viralmaisuriyaa-broken-link-scanner&scan_id=' + response.data.scanId;
				return;
			}
			setProgress(0, response.data.total, vmblsAdmin.i18n.scanning);
			runBatch(response.data.scanId, 1, response.data.total);
		}).fail(function () {
			fail(vmblsAdmin.i18n.failed);
		});
	}

	$('#vmbls-start-scan, #vmbls-start-scan-secondary').on('click', startScan);

	$('#vmbls-scan-history').on('change', function () {
		window.location.href = window.location.pathname + '?page=viralmaisuriyaa-broken-link-scanner&scan_id=' + encodeURIComponent($(this).val());
	});

	function initDataTable() {
		var $table = $('#vmbls-results-table');
		if (!$table.length) {
			return;
		}

		var $tbody = $table.find('tbody');
		var rows = $tbody.find('tr').get();
		var page = 1;
		var pageLength = parseInt($('#vmbls-page-length').val(), 10) || 25;
		var sortIndex = -1;
		var sortDirection = 1;

		function matchesHttp(code, filter) {
			if (!filter) { return true; }
			code = parseInt(code, 10) || 0;
			if (filter === 'error') { return code === 0; }
			if (filter === '2xx') { return code >= 200 && code < 300; }
			if (filter === '3xx') { return code >= 300 && code < 400; }
			if (filter === '4xx') { return code >= 400 && code < 500; }
			if (filter === '5xx') { return code >= 500 && code < 600; }
			return true;
		}

		function filteredRows() {
			var search = ($('#vmbls-table-search').val() || '').toLowerCase().trim();
			var status = $('#vmbls-filter-status').val();
			var type = $('#vmbls-filter-type').val();
			var scope = $('#vmbls-filter-scope').val();
			var http = $('#vmbls-filter-http').val();

			return rows.filter(function (row) {
				var $row = $(row);
				var textMatch = !search || $row.text().toLowerCase().indexOf(search) !== -1;
				return textMatch &&
					(!status || $row.data('status') === status) &&
					(!type || $row.data('type') === type) &&
					(!scope || $row.data('scope') === scope) &&
					matchesHttp($row.data('http'), http);
			});
		}

		function sortRows(list) {
			if (sortIndex < 0) { return list; }
			return list.sort(function (a, b) {
				var av = String($(a).children().eq(sortIndex).data('value') || '').toLowerCase();
				var bv = String($(b).children().eq(sortIndex).data('value') || '').toLowerCase();
				var an = parseFloat(av), bn = parseFloat(bv);
				if (!isNaN(an) && !isNaN(bn)) { return (an - bn) * sortDirection; }
				return av.localeCompare(bv) * sortDirection;
			});
		}

		function render() {
			pageLength = parseInt($('#vmbls-page-length').val(), 10) || 25;
			var list = sortRows(filteredRows());
			var pages = Math.max(1, Math.ceil(list.length / pageLength));
			page = Math.min(page, pages);
			var start = (page - 1) * pageLength;
			var end = Math.min(start + pageLength, list.length);

			$(rows).hide();
			list.slice(start, end).forEach(function (row) { $(row).show(); });
			$('#vmbls-empty-state').prop('hidden', list.length !== 0);
			$table.toggle(list.length !== 0);
			$('#vmbls-result-count').text(list.length + ' / ' + rows.length);
			$('#vmbls-table-info').text(list.length ? (start + 1) + '–' + end + ' of ' + list.length : '0 of 0');
			$('#vmbls-page-info').text(vmblsAdmin.i18n.page + ' ' + page + ' ' + vmblsAdmin.i18n.of + ' ' + pages);
			$('#vmbls-prev-page').prop('disabled', page <= 1);
			$('#vmbls-next-page').prop('disabled', page >= pages);
		}

		$('#vmbls-table-search').on('input', function () { page = 1; render(); });
		$('#vmbls-filter-status, #vmbls-filter-type, #vmbls-filter-scope, #vmbls-filter-http, #vmbls-page-length').on('change', function () { page = 1; render(); });
		$('#vmbls-prev-page').on('click', function () { if (page > 1) { page--; render(); } });
		$('#vmbls-next-page').on('click', function () { page++; render(); });
		$table.find('thead th[data-sort]').on('click keydown', function (event) {
			if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') { return; }
			event.preventDefault();
			var index = $(this).index();
			sortDirection = sortIndex === index ? sortDirection * -1 : 1;
			sortIndex = index;
			$table.find('thead th').removeClass('is-sort-asc is-sort-desc');
			$(this).addClass(sortDirection === 1 ? 'is-sort-asc' : 'is-sort-desc');
			render();
		});

		render();
	}

	initDataTable();
}(jQuery));
