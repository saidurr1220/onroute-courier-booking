/**
 * VIP Courier Form Handler
 * Handles AJAX submission with smooth animations and professional success message
 */
(function($) {
        'use strict';

        $(document).ready(function() {
                const $form=$('#vip-enquiry-form-elem');
                const $submitBtn=$form.find('.vip-submit-btn');
                const $successOverlay=$('#vip-success-overlay');
                const $card=$('#vip-card');

                // Form submission handler
                $form.on('submit', function(e) {
                        e.preventDefault();

                        // Validate form
                        if ( !this.checkValidity()) {
                            return;
                        }

                        // Disable button and show loading state
                        $submitBtn.addClass('loading').prop('disabled', true);

                        // Check if vipFormData is defined
                        if (typeof vipFormData==='undefined') {
                            handleError('Form data not initialized. Please refresh the page.');
                            $submitBtn.removeClass('loading').prop('disabled', false);
                            return;
                        }

                        // Prepare form data as plain object instead of FormData
                        const formData= {
                            action: 'vip_submit_enquiry',
                            nonce: vipFormData.nonce,
                            full_name: $form.find('input[name="full_name"]').val(),
                            contact_email: $form.find('input[name="contact_email"]').val(),
                            contact_phone: $form.find('input[name="contact_phone"]').val(),
                            delivery_details: $form.find('textarea[name="delivery_details"]').val(),
                            privacy_check: $form.find('input[name="privacy_check"]').is(':checked') ? 1 : 0
                        }

                        ;

                        

                        // Send AJAX request
                        $.ajax( {

                                url: vipFormData.ajaxurl,
                                type: 'POST',
                                data: formData,
                                dataType: 'json',
                                success: function(response) {
                                    

                                    if (response.success) {
                                        handleSuccess(response.data);
                                    }

                                    else {
                                        handleError(response.data.message || 'An error occurred. Please try again.');
                                        $submitBtn.removeClass('loading').prop('disabled', false);
                                    }
                                }

                                ,
                                error: function(xhr, status, error) {
                                    
                                    handleError('Network error: '+ status + '. Please try again.');
                                    $submitBtn.removeClass('loading').prop('disabled', false);
                                }
                            }

                        );
                    }

                );

                /**
         * Handle successful submission
         */
                function handleSuccess(data) {
                    // Get email value for success message
                    const email=$form.find('input[name="contact_email"]').val();

                    // Update success message with dynamic content
                    $('#vip-success-email').text(email);
                    $('#vip-success-ref').text(data.reference || 'PENDING');

                    // Disable form card
                    $card.addClass('form-disabled');

                    // Show success overlay with animation
                    setTimeout(function() {
                            $successOverlay.addClass('show');
                        }

                        , 300);

                    // Scroll to overlay
                    $('html, body').animate( {
                            scrollTop: $successOverlay.offset().top - 100
                        }

                        , 500);
                }

                /**
         * Handle error
         */
                function handleError(message) {
                    // Show error message in a toast/alert
                    const errorMsg=$('<div class="vip-form-msg vip-form-error" style="margin-bottom: 20px;"></div>') .html('<i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>'+ message);

                    $form.prepend(errorMsg);

                    // Auto-remove error message after 5 seconds
                    setTimeout(function() {
                            errorMsg.fadeOut(300, function() {
                                    $(this).remove();
                                }

                            );
                        }

                        , 5000);
                }

                // Close success overlay when clicking dismiss button
                $(document).on('click', '.vip-success-close-btn', function() {
                        $successOverlay.removeClass('show');

                        // Reset form after dismissing
                        setTimeout(function() {
                                $form[0].reset();
                                $card.removeClass('form-disabled');
                                $submitBtn.removeClass('loading').prop('disabled', false);
                            }

                            , 500);
                    }

                );

                // Close overlay when clicking outside (optional)
                $successOverlay.on('click', function(e) {
                        if ($(e.target).is($successOverlay)) {
                            $(this).removeClass('show');

                            setTimeout(function() {
                                    $form[0].reset();
                                    $card.removeClass('form-disabled');
                                    $submitBtn.removeClass('loading').prop('disabled', false);
                                }

                                , 500);
                        }
                    }

                );
            }

        );

    }

)(jQuery);
