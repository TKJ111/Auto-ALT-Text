jQuery(document).ready(function($) {
    let imageQueue = [];
    let processedCount = 0;
    let isProcessing = false;

    const debugConsole = {
        show() {
            $('.debug-console').show();
            $('.toggle-debug').text('Hide Debug Console');
        },
        
        hide() {
            $('.debug-console').hide();
            $('.toggle-debug').text('Show Debug Console');
        },
        
        log(message, type = 'info') {
            const $content = $('.debug-content');
            const timestamp = new Date().toLocaleTimeString();
            const $entry = $(`
                <div class="log-entry log-${type}">
                    [${timestamp}] ${message}
                </div>
            `);
            $content.append($entry);
            $content.scrollTop($content[0].scrollHeight);
        },
        
        clear() {
            $('.debug-content').empty();
        }
    };

    // Add debug console event handlers
    $('.toggle-debug').on('click', function() {
        const $console = $('.debug-console');
        if ($console.is(':visible')) {
            debugConsole.hide();
        } else {
            debugConsole.show();
        }
    });

    $('.clear-logs').on('click', function() {
        debugConsole.clear();
    });

    $('.close-debug').on('click', function() {
        debugConsole.hide();
    });

    // Scan images button
    $('#scan-images').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        $button.prop('disabled', true).text('Scanning...');
        
        $('#scan-status').show();
        $('#status-text').text('Scanning images...');
        updateProgress(0);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'scan_images',
                nonce: autoAltText.nonce
            },
            success: function(response) {
                console.log('Response:', response);
                if (response.success) {
                    const data = response.data;
                    
                    // Update statistics
                    $('#total-images').text(data.total);
                    $('#with-alt').text(data.with_alt);
                    $('#without-alt').text(data.without_alt);
                    
                    // Show results and buttons
                    $('#scan-results').show();
                    if (data.without_alt > 0) {
                        $('#process-missing').show();
                        $('#process-all').show();
                        
                        // Populate image list
                        populateImageList(data.images);
                    }
                    
                    $('#status-text').text('Scan complete!');
                    updateProgress(100);
                } else {
                    $('#status-text').text('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                $('#status-text').text('Error scanning images: ' + textStatus);
            },
            complete: function() {
                $button.prop('disabled', false).text('Scan Images');
            }
        });
    });

    function populateImageList(images) {
        const $tbody = $('#images-tbody');
        $tbody.empty();
        
        images.forEach(image => {
            $tbody.append(`
                <tr data-image-id="${image.id}">
                    <td><img src="${image.thumbnail}" class="image-preview"></td>
                    <td>${image.title}</td>
                    <td class="status-cell">
                        <span class="status status-pending">Pending</span>
                    </td>
                    <td>
                        <button class="button process-single">Generate ALT</button>
                    </td>
                </tr>
            `);
        });
        
        $('#image-list').show();
    }

    // Process single image
    $(document).on('click', '.process-single', function() {
        const $button = $(this);
        const $row = $button.closest('tr');
        const imageId = $row.data('image-id');
        
        processSingleImage(imageId, $row);
    });

    // Process missing ALT text
    $('#process-missing').on('click', function() {
        const $rows = $('#images-tbody tr');
        imageQueue = [];
        
        $rows.each(function() {
            imageQueue.push($(this).data('image-id'));
        });
        
        processedCount = 0;
        debugConsole.log(`Starting batch processing of ${imageQueue.length} images...`, 'info');
        startBatchProcessing();
    });

    function startBatchProcessing() {
        if (imageQueue.length === 0) {
            isProcessing = false;
            $('#status-text').text('Processing complete!');
            return;
        }

        if (!isProcessing) {
            isProcessing = true;
            $('#scan-status').show();
            $('#status-text').text('Processing images...');
        }

        const imageId = imageQueue.shift();
        const $row = $(`tr[data-image-id="${imageId}"]`);
        
        // Process with 3-second delay for free tier (20 requests per minute)
        setTimeout(() => {
            processSingleImage(imageId, $row)
                .then(() => {
                    processedCount++;
                    const progress = (processedCount / (processedCount + imageQueue.length)) * 100;
                    updateProgress(progress);
                    
                    // Wait 3 seconds between requests (20 per minute)
                    setTimeout(startBatchProcessing, 3000);
                });
        }, 100);
    }

    function processSingleImage(imageId, $row) {
        return new Promise((resolve, reject) => {
            debugConsole.log('Current language setting: ' + $('#language').val(), 'info');
            debugConsole.log(`Processing image ID: ${imageId}`);
            $row.find('.status')
                .removeClass('status-pending status-error')
                .addClass('status-processing')
                .text('Processing...');
            
            $row.find('button').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'process_single_image',
                    image_id: imageId,
                    language: $('#language').val()
                },
                success: function(response) {
                    debugConsole.log('API Response:', 'info');
                    debugConsole.log(JSON.stringify(response, null, 2), 'info');

                    if (response && response.success && response.data && response.data.alt_text) {
                        const altText = response.data.alt_text;
                        debugConsole.log(`Success - ALT text generated: ${altText}`, 'success');
                        $row.find('.status')
                            .removeClass('status-processing status-error')
                            .addClass('status-complete')
                            .html('Complete: ' + altText);
                    } else {
                        const errorMsg = (response && response.data) ? response.data : 'Failed to generate ALT text';
                        debugConsole.log(`Error: ${errorMsg}`, 'error');
                        $row.find('.status')
                            .removeClass('status-processing')
                            .addClass('status-error')
                            .text('Error: ' + errorMsg);
                    }
                    resolve();
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    const errorMsg = `${textStatus}: ${errorThrown}`;
                    debugConsole.log(`AJAX Error: ${errorMsg}`, 'error');
                    $row.find('.status')
                        .removeClass('status-processing')
                        .addClass('status-error')
                        .text('Error: ' + errorMsg);
                    resolve(); // Still resolve to continue processing
                },
                complete: function() {
                    $row.find('button').prop('disabled', false);
                },
                dataType: 'json'
            });
        });
    }

    function updateProgress(percentage) {
        $('#progress-inner').css('width', percentage + '%');
    }

    // Add this near the top of your jQuery ready function
    $('#test-azure-connection').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $result = $('#test-connection-result');
        
        $button.prop('disabled', true).text('Testing...');
        $result.hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_azure_connection'
            },
            success: function(response) {
                $result
                    .removeClass('notice-error notice-success')
                    .addClass(response.success ? 'notice-success' : 'notice-error')
                    .html(response.data)
                    .show();
            },
            error: function() {
                $result
                    .removeClass('notice-success')
                    .addClass('notice-error')
                    .html('Connection test failed. Please check your network connection.')
                    .show();
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Azure Connection');
            }
        });
    });

    // Add this click handler after your existing click handlers
    $('#process-all').on('click', function() {
        const $rows = $('#images-tbody tr');
        imageQueue = [];
        
        // Show scanning message
        $('#scan-status').show();
        $('#status-text').text('Scanning all images...');
        updateProgress(0);
        
        // Get all images, not just ones without alt text
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_all_images'
            },
            success: function(response) {
                if (response.success && response.data.images) {
                    // Clear existing table
                    const $tbody = $('#images-tbody');
                    $tbody.empty();
                    
                    // Add all images to table and queue
                    response.data.images.forEach(image => {
                        imageQueue.push(image.id);
                        $tbody.append(`
                            <tr data-image-id="${image.id}">
                                <td><img src="${image.thumbnail}" class="image-preview"></td>
                                <td>${image.title}</td>
                                <td class="status-cell">
                                    <span class="status status-pending">Pending</span>
                                </td>
                                <td>
                                    <button class="button process-single">Generate ALT</button>
                                </td>
                            </tr>
                        `);
                    });
                    
                    $('#image-list').show();
                    processedCount = 0;
                    startBatchProcessing();
                }
            },
            error: function() {
                $('#status-text').text('Error scanning images');
            }
        });
    });

    function processTranslation(response) {
        if (response.success) {
            debugConsole.log('Translation successful', 'success');
            debugConsole.log(`Original text: ${response.data.original}`, 'info');
            debugConsole.log(`Translated text: ${response.data.translated}`, 'info');
        } else {
            debugConsole.log(`Translation failed: ${response.error}`, 'error');
        }
    }
}); 