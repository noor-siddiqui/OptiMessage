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
        var formData = $(this).serialize();
        formData += '&action=om_setup_sms_queue';

        $.post(omData.ajax_url, formData, function (response) {
            if (response.success) {
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
    });
});