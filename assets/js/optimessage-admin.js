jQuery(document).ready(function ($) {
    // Update reachable customer count when a product is selected or cleared.
    $('select[name="product_id"]').on('change', function () {
        var productId = $(this).val();
        var $count = $('#om-reachable-count');
        var $label = $('#om-reachable-label');

        if (typeof omProductCounts === 'undefined' || typeof omAllCount === 'undefined') {
            return;
        }

        if (productId && productId !== '') {
            var count = omProductCounts[productId] || 0;
            $count.text(count);
            $label.text(omData.strings.reachable_filtered);
        } else {
            $count.text(omAllCount);
            $label.text(omData.strings.reachable_all);
        }
    });

<<<<<<< perf/hoist-get-option-sms-history-11636205782672209129
    $('#om-bulk-filter-form').on('submit', function (e) {
        e.preventDefault();

        var $btn = $('#om-send-btn');
        var $wrapper = $('#om-progress-wrapper');
        var $bar = $('#om-progress-bar');
        var $text = $('#om-progress-text');

        // Disable button and show progress UI using our translated PHP strings.
        $btn.prop('disabled', true).text(omData.strings.processing);
        $wrapper.show();

        // Setup the Queue.
=======
    /**
     * Shared batch processor used by both CSV and Product Filter forms.
     */
    function processBatch(total, processedSoFar, ui) {
        $.post(omData.ajax_url, {
            action: 'om_process_sms_batch',
            om_nonce: omData.batch_nonce
        }, function (response) {
            if (response.success) {
                var processedNow = response.data.processed;
                var totalProcessed = processedSoFar + processedNow;
                var percentage = Math.round((totalProcessed / total) * 100);

                // Update UI.
                ui.$bar.css('width', percentage + '%');
                ui.$text.text(omData.strings.sent + ' ' + totalProcessed + ' ' + omData.strings.of + ' ' + total + ' (' + percentage + '%)');

                if (response.data.is_done) {
                    ui.$text.text(omData.strings.success + ' ' + total + ' ' + omData.strings.messages_sent);
                    ui.$btn.text(omData.strings.finished);

                    // Show a "Send Another" button so the user can reset when ready.
                    var $reset = $('<button type="button" class="button" style="margin-left: 10px;">' + omData.strings.send_another + '</button>');
                    ui.$btn.after($reset);
                    $reset.on('click', function () {
                        ui.$wrapper.fadeOut(400, function () {
                            ui.$bar.css('width', '0%');
                        });
                        ui.$btn.prop('disabled', false).text(ui.originalBtnText);
                        ui.$form.find('textarea[name="message"]').val('');
                        $reset.remove();
                    });
                } else {
                    processBatch(total, totalProcessed, ui);
                }
            } else {
                ui.$text.text(omData.strings.error_processing + ': ' + response.data);
            }
        }).fail(function () {
            ui.$text.text(omData.strings.server_lost);
        });
    }

    // ─── Product Filter Form ───
    $('#om-bulk-filter-form').on('submit', function (e) {
        e.preventDefault();

        var ui = {
            $form: $(this),
            $btn: $('#om-send-btn'),
            $wrapper: $('#om-progress-wrapper'),
            $bar: $('#om-progress-bar'),
            $text: $('#om-progress-text'),
            originalBtnText: omData.strings.send_sms
        };

        ui.$btn.prop('disabled', true).text(omData.strings.processing);
        ui.$wrapper.show();

>>>>>>> main
        var formData = $(this).serialize();
        formData += '&action=om_setup_sms_queue';

        $.post(omData.ajax_url, formData, function (response) {
            if (response.success) {
<<<<<<< perf/hoist-get-option-sms-history-11636205782672209129
                var totalMessages = response.data.total;
                if (totalMessages === 0) {
                    $text.text(omData.strings.no_customers);
                    $btn.prop('disabled', false).text(omData.strings.send_sms);
                    return;
                }

                // Start the Batch Loop.
                processBatch(totalMessages, 0);
            } else {
                $text.text(omData.strings.error + ': ' + response.data);
                $btn.prop('disabled', false).text(omData.strings.send_sms);
            }
        });

        // The Recursive Batch Function.
        function processBatch(total, processedSoFar) {
            $.post(omData.ajax_url, {
                action: 'om_process_sms_batch',
                om_nonce: $('#om_nonce').val()
            }, function (response) {
                if (response.success) {
                    var processedNow = response.data.processed;
                    var totalProcessed = processedSoFar + processedNow;
                    var percentage = Math.round((totalProcessed / total) * 100);

                    // Update UI.
                    $bar.css('width', percentage + '%');
                    $text.text(omData.strings.sent + ' ' + totalProcessed + ' ' + omData.strings.of + ' ' + total + ' (' + percentage + '%)');

                    if (response.data.is_done) {
                        $text.text(omData.strings.success + ' ' + total + ' ' + omData.strings.messages_sent);
                        $btn.text(omData.strings.finished);

                        // Show a "Send Another" button so the user can reset when ready.
                        var $reset = $('<button type="button" class="button" style="margin-left: 10px;">' + omData.strings.send_another + '</button>');
                        $btn.after($reset);
                        $reset.on('click', function () {
                            $wrapper.fadeOut(400, function () {
                                $bar.css('width', '0%');
                            });
                            $btn.prop('disabled', false).text(omData.strings.send_sms);
                            $('#om-bulk-filter-form').find('textarea[name="message"]').val('');
                            $reset.remove();
                        });
                    } else {
                        // Not done yet? Call this function again!
                        processBatch(total, totalProcessed);
                    }
                } else {
                    $text.text(omData.strings.error_processing + ': ' + response.data);
                }
            }).fail(function () {
                $text.text(omData.strings.server_lost);
            });
        }
=======
                if (response.data.total === 0) {
                    ui.$text.text(omData.strings.no_customers);
                    ui.$btn.prop('disabled', false).text(ui.originalBtnText);
                    return;
                }
                processBatch(response.data.total, 0, ui);
            } else {
                ui.$text.text(omData.strings.error + ': ' + response.data);
                ui.$btn.prop('disabled', false).text(ui.originalBtnText);
            }
        });
    });

    // ─── CSV Upload Form ───
    $('#om-bulk-csv-form').on('submit', function (e) {
        e.preventDefault();

        var ui = {
            $form: $(this),
            $btn: $('#om-csv-send-btn'),
            $wrapper: $('#om-csv-progress-wrapper'),
            $bar: $('#om-csv-progress-bar'),
            $text: $('#om-csv-progress-text'),
            originalBtnText: omData.strings.send_csv
        };

        ui.$btn.prop('disabled', true).text(omData.strings.processing);
        ui.$wrapper.show();

        // Use FormData to handle file uploads via AJAX.
        var formData = new FormData(this);
        formData.append('action', 'om_setup_csv_queue');

        $.ajax({
            url: omData.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    // Show invalid numbers if any were found.
                    if (response.data.invalid_count > 0) {
                        var invalidList = response.data.invalid_phones.join(', ');
                        var $notice = $('<div class="notice notice-warning inline" style="margin: 10px 0; padding: 10px;">' +
                            '<p><strong>' + omData.strings.invalid_numbers + ' (' + response.data.invalid_count + '):</strong></p>' +
                            '<p style="word-break: break-all;">' + $('<span>').text(invalidList).html() + '</p>' +
                            '</div>');
                        ui.$wrapper.before($notice);
                    }

                    if (response.data.total === 0) {
                        ui.$text.text(omData.strings.no_customers);
                        ui.$btn.prop('disabled', false).text(ui.originalBtnText);
                        return;
                    }

                    ui.$text.text(omData.strings.validated.replace('%d', response.data.total));
                    processBatch(response.data.total, 0, ui);
                } else {
                    ui.$text.text(omData.strings.error + ': ' + response.data);
                    ui.$btn.prop('disabled', false).text(ui.originalBtnText);
                }
            },
            error: function () {
                ui.$text.text(omData.strings.server_lost);
            }
        });
>>>>>>> main
    });
});