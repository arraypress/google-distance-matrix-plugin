jQuery(document).ready(function ($) {
    // Cache form elements
    const $form = $('.calculation-form');
    const $origins = $('#origins');
    const $destinations = $('#destinations');
    const $results = $('.distance-matrix-results');
    const $submitButton = $form.find('input[type="submit"]');

    // Initialize tooltip for multiple addresses info
    $('.calculation-form .description').tooltip({
        items: 'p',
        content: 'Example: "New York, NY | Los Angeles, CA | Chicago, IL"'
    });

    // Add multiple origins/destinations buttons
    $origins.after(
        $('<button>', {
            type: 'button',
            class: 'button button-secondary add-address',
            text: '+ Add Another Origin',
            'data-target': 'origins'
        })
    );

    $destinations.after(
        $('<button>', {
            type: 'button',
            class: 'button button-secondary add-address',
            text: '+ Add Another Destination',
            'data-target': 'destinations'
        })
    );

    // Handle adding new address fields
    $('.add-address').on('click', function (e) {
        e.preventDefault();
        const target = $(this).data('target');
        const $input = $(`#${target}`);
        const currentValue = $input.val();

        if (currentValue) {
            $input.val(currentValue + ' | ');
            $input.focus();
            // Move cursor to end
            const length = $input.val().length;
            $input[0].setSelectionRange(length, length);
        }
    });

    // Basic form validation
    $form.on('submit', function (e) {
        const originsValue = $origins.val().trim();
        const destinationsValue = $destinations.val().trim();

        if (!originsValue || !destinationsValue) {
            e.preventDefault();
            alert('Please enter both origin and destination addresses.');
            return false;
        }

        // Show loading state
        $submitButton.prop('disabled', true)
            .val('Calculating...');

        return true;
    });

    // Handle unit selection changes
    $('#units').on('change', function () {
        const selectedUnit = $(this).val();
        // Could be used to update any unit-specific UI elements
        $(document).trigger('distanceMatrixUnitChange', [selectedUnit]);
    });

    // Handle travel mode changes
    $('#travel_mode').on('change', function () {
        const mode = $(this).val();
        const $avoid = $('#avoid');

        // Update available avoid options based on travel mode
        if (mode === 'transit') {
            $avoid.prop('disabled', true)
                .val('')
                .parent().parent().fadeOut();
        } else {
            $avoid.prop('disabled', false)
                .parent().parent().fadeIn();
        }
    });

    // Add address validation helper
    function validateAddressField($field) {
        const addresses = $field.val().split('|');
        const validAddresses = addresses
            .map(addr => addr.trim())
            .filter(addr => addr.length > 0);

        // Update field with cleaned addresses
        if (validAddresses.length) {
            $field.val(validAddresses.join(' | '));
            return true;
        }
        return false;
    }

    // Add keyboard shortcuts for adding addresses
    $origins.add($destinations).on('keydown', function (e) {
        // Alt + | to add separator
        if (e.altKey && e.key === '|') {
            e.preventDefault();
            const $input = $(this);
            const curPos = $input[0].selectionStart;
            const value = $input.val();
            $input.val(
                value.slice(0, curPos) +
                ' | ' +
                value.slice(curPos)
            );
        }
    });

    // Handle clear cache button confirmation
    $('.clear-cache-button').on('click', function (e) {
        if (!confirm('Are you sure you want to clear the cache? This will force new API requests for subsequent calculations.')) {
            e.preventDefault();
            return false;
        }
    });

    // Add copy results button if results exist
    if ($results.length) {
        const $copyButton = $('<button>', {
            type: 'button',
            class: 'button button-secondary copy-results',
            text: 'Copy Results'
        }).insertAfter($results);

        $copyButton.on('click', function () {
            const resultsText = $results.find('table')
                .find('tr')
                .map(function () {
                    return $(this).find('td')
                        .map(function () {
                            return $(this).text().trim();
                        })
                        .get()
                        .join('\t');
                })
                .get()
                .join('\n');

            // Copy to clipboard
            navigator.clipboard.writeText(resultsText).then(
                function () {
                    $copyButton.text('Copied!');
                    setTimeout(() => {
                        $copyButton.text('Copy Results');
                    }, 2000);
                },
                function (err) {
                    console.error('Failed to copy results:', err);
                    alert('Failed to copy results to clipboard');
                }
            );
        });
    }

    // Add error handling for API key field
    $('#google_distance_matrix_api_key').on('paste blur', function () {
        const $field = $(this);
        const value = $field.val().trim();

        if (value && !value.match(/^[A-Za-z0-9_-]+$/)) {
            $field.addClass('error');
            if (!$field.next('.error-message').length) {
                $field.after(
                    $('<p>', {
                        class: 'error-message',
                        text: 'API key appears to be invalid. Please check your key.'
                    })
                );
            }
        } else {
            $field.removeClass('error');
            $field.next('.error-message').remove();
        }
    });

    // Initialize any existing error states
    $('#google_distance_matrix_api_key').trigger('blur');
});