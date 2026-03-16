jQuery(document).ready(function ($) {
    /**
     * Inline Validation System
     */
    function initInlineValidation() {
        // Add validation error container after each required field
        $('.mrm-form-wrapper-inner form').each(function() {
            $(this).find('[required]').each(function() {
                var $field = $(this);
                var $formGroup = $field.closest('.mrm-form-group, .mrm-input-group');
                
                // Add error message container if not exists
                if ($formGroup.length && !$formGroup.find('.mrm-validation-error').length) {
                    $formGroup.append('<span class="mrm-validation-error">This field is required</span>');
                }
                
                // Override browser default validation tooltip
                $field.on('invalid', function(e) {
                    e.preventDefault();
                    showFieldError($field);
                });
                
                // Clear error on input
                $field.on('input change', function() {
                    clearFieldError($field);
                });
            });
        });
    }

    function showFieldError($field) {
        var $formGroup = $field.closest('.mrm-form-group, .mrm-input-group');
        var errorMessage = getValidationMessage($field);
        
        $formGroup.addClass('has-error');
        $formGroup.find('.mrm-validation-error').text(errorMessage);
    }

    function clearFieldError($field) {
        var $formGroup = $field.closest('.mrm-form-group, .mrm-input-group');
        $formGroup.removeClass('has-error');
    }

    function getValidationMessage($field) {
        var field = $field[0];
        
        if (field.validity.valueMissing) {
            return 'This field is required';
        }
        if (field.validity.typeMismatch) {
            if (field.type === 'email') {
                return 'Please enter a valid email address';
            }
            return 'Please enter a valid value';
        }
        if (field.validity.patternMismatch) {
            return 'Please match the requested format';
        }
        
        return 'Please fill out this field correctly';
    }

    function validateForm($form) {
        var isValid = true;
        
        $form.find('[required]').each(function() {
            var $field = $(this);
            var field = $field[0];
            
            if (!field.checkValidity()) {
                showFieldError($field);
                isValid = false;
            } else {
                clearFieldError($field);
            }
        });
        
        // Focus first invalid field
        if (!isValid) {
            $form.find('.has-error').first().find('input, select, textarea').focus();
        }
        
        return isValid;
    }

    // Initialize inline validation
    initInlineValidation();

    /**
     * Shortcode form submission ajax
     */

    $(".mrm-form-wrapper-inner form").on("submit",function (e){
        e.preventDefault();
        
        // Run inline validation first
        if (!validateForm($(this))) {
            return false;
        }
        
        $(this).find(".response").html("");
        $(this).find(".mrm-submit-button").addClass("show-loader");
        $(this).find(".mrm-submit-button").attr('disabled','disabled');
        let that = this;
        var data = new FormData();
        data.append( "post_data", $(that).serialize() );
        data.append( "wp_nonce", window.MRM_Frontend_Vars.nonce ); // This nonce is created in the following file: /app/Internal/Frontend/FrontendAssets.php
        data.append( "action", "mrm_submit_form");
        fetch(window.MRM_Frontend_Vars.rest_api_url+"mint-mail/v1/mint-form-submit",
            {
                method: "POST",
                body: data
            })
            .then(function(res){ return res.json(); })
            .then(function(response){
                $(that).find(".mrm-submit-button").removeClass("show-loader");
                $(that).find(".mrm-submit-button").removeAttr('disabled');
                if (response.status === "success") {
                    $(".response").addClass("mintmrm-success");
                    $(".response").removeClass("mintmrm-error");
                    $(that).find(".mrm-submit-button").removeClass("show-loader");
                    if (response.confirmation_type === "same_page") {
                        if (response.after_form_submission === "hide_form") {
                            $(".mrm-form-wrapper form").hide();
                            $(".mrm-form-wrapper").hide();
                        } else if (
                            response.after_form_submission === "reset_form"
                        ) {
                            $("#mrm-form")[0].reset();
                        }
                    }
                    if (response.confirmation_type === "to_a_page") {
                        if (response.redirect_page) {
                            setTimeout(function () {
                                window.location.href = response.redirect_page;
                            }, 2000);
                        } else {
                            setTimeout(function () {
                                $(that).find(".response").html(
                                    "Redirect URL not found"
                                );
                            }, 2000);
                        }
                    }
                    if (response.confirmation_type === "to_a_custom_url") {
                        if (response.custom_url !== "") {
                            setTimeout(function () {
                                window.location.href = response.custom_url;
                            }, 1000);
                        }
                    }
                    if (response.confirmation_type === "download") {
                        fetch(response.file_url)
                            .then(response => response.blob())
                            .then(blob => {
                                const url = window.URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.href = url;
                                link.download = response.file_name || 'download';
                                link.style.display = 'none';
                                
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                
                                // Clean up the object URL
                                window.URL.revokeObjectURL(url);
                            })
                            .catch(error => {
                                console.error('Download failed:', error);
                                // Fallback to direct link
                                window.open(response.file_url, '_blank');
                            });
                            
                        if (response.after_form_submission === "hide_form") {
                            $(".mrm-form-wrapper form").hide();
                            $(".mrm-form-wrapper").hide();
                        } else if (response.after_form_submission === "reset_form") {
                            $("#mrm-form")[0].reset();
                        }
                    }
                    $(that).parent().find(".response").html(response.message);
                    $(that).find(".response").html(response.message);
                } else if (response.status == "failed") {
                    $(that).find(".response").addClass("mintmrm-error");
                    $(that).find(".response").removeClass("mintmrm-success");
                    $(that).find(".response").html(response.message);
                    $(that).find(".mrm-submit-button").removeAttr('disabled');
                }

            })
    })

    function modifyQueryString(input) {
        // Split the input string into key-value pairs
        const pairs = input.split('&');
        const result = [];
        const mrmList = [];
    
        pairs.forEach(pair => {
            const [key, value] = pair.split('=');
    
            // Keep the key-value pairs that are not list items
            if (!/^\d+$/.test(key)) {
                result.push(`${key}=${value}`);
            } else {
                // Collect list item keys
                mrmList.push(key);
            }
        });
    
        // Append the mrm_list items in the desired format
        mrmList.forEach(item => {
            result.push(`mrm_list%5B%5D=${item}`);
        });
    
        // Join the result array into a string
        return result.join('&');
    }

    $("#mrm-preference-form").on("submit", function (e) {
        e.preventDefault();
        jQuery(".response").html("");
        $(".mrm-pref-submit-button").addClass("show-loader");
        var data = new FormData();
        var postData = jQuery("#mrm-preference-form").serialize();
        var modifiedQueryString = modifyQueryString(postData);
        postData = modifiedQueryString;
        data.append( "post_data", modifiedQueryString );
        data.append( "wp_nonce", window.MRM_Frontend_Vars.nonce ); // This nonce is created in the following file: /app/Internal/Frontend/FrontendAssets.php
        data.append( "action", "mrm_preference_update_by_user");
        fetch(window.MRM_Frontend_Vars.rest_api_url+"mint-mail/v1/mint-preference-submit",
            {
                method: "POST",
                body: data
            })
            .then(function(res){ return res.json(); })
            .then(function(response){
                if (response.status == "success") {
                    $(".mrm-pref-submit-button").removeClass("show-loader");
                    $("#mrm-preference-form").hide();
                    $(".response").addClass("mintmrm-success");
                    $(".response").removeClass("mintmrm-error");
                    $(".response").html(response.message);
                } else if (response.status == "failed") {
                    jQuery(".response").addClass("mintmrm-error");
                    jQuery(".response").removeClass("mintmrm-success");
                    jQuery(".response").html(response.message);
                    $(".mrm-pref-submit-button").removeClass("show-loader");
                }
            })
    });

    /**
     * Set cokokie
     * @param name
     * @param value
     * @param daysToLive
     */
    function setCookie(cName, cValue, expDays) {
        let date = new Date();
        date.setTime(date.getTime() + expDays * 24 * 60 * 60 * 1000);
        const expires = "expires=" + date.toUTCString();

        var value = { show: cValue, expire: date.getTime() };

        document.cookie =
            cName + "=" + JSON.stringify(value) + "; " + expires + "; path=/";
    }
    /**
     * Form Close button Function
     */


    $(".mrm-form-close").on("click", function () {
        $(this).parent().parent().hide();
        var form_id = $(this).attr('form-id')
        let that = this;
        var data = new FormData();
        data.append( "form_id", form_id );
        data.append( "action", "set_mint_mail_cookie_for_form");
        data.append( "wp_nonce", window.MRM_Frontend_Vars.nonce ); // This nonce is created in the following file: /app/Internal/Frontend/FrontendAssets.php

        fetch(window.MRM_Frontend_Vars.rest_api_url+"mint-mail/v1/mint-form-cookie-submit",
            {
                method: "POST",
                body: data
            })
            .then(function(res){ return res.json(); })
            .then(function(response){
                if( "success" === response.status){
                    $(that).parent().parent().hide();
                }else{
                    $(that).parent().parent().hide();
                }
            })
    });

    if ($("#mint-google-recaptcha").length > 0) {
        var recaptch_mint = $('[id="mint-google-recaptcha"]');
        if(window.MRM_Frontend_Vars.recaptcha_settings.enable &&  window.MRM_Frontend_Vars.recaptcha_settings.api_version === "v2_visible"){
            recaptch_mint.append(`<script src="https://www.google.com/recaptcha/api.js" async defer></script>`)
        }
        if(window.MRM_Frontend_Vars.recaptcha_settings.enable &&  window.MRM_Frontend_Vars.recaptcha_settings.api_version === "v2_visible"){
            recaptch_mint.append(`<div class="g-recaptcha" data-sitekey="`+window.MRM_Frontend_Vars.recaptcha_settings.v2_visible.site_key+`"></div>`)

            document.addEventListener("DOMContentLoaded", function() {
                var site_key = window.MRM_Frontend_Vars.recaptcha_settings.v3_invisible.site_key;
                grecaptcha.ready(function() {
                    grecaptcha.execute(site_key, { action: "homepage" }).then(function(token) {
                        document.getElementById("g-recaptcha-response").value = token;
                    });
                });
            });
        }
    }

    $(".mint-form-button").on('click',function (){
         $(this).parent().parent().find('#mrm-popup').css("display","flex");
    })

    /**
     * Exit Intent Detection
     * Triggers popup when user moves mouse towards the top of the page (exit intent)
     */
    function initExitIntentDetection() {
        const sessionKey = 'exitIntentShownForms';

        // Initialize sessionStorage
        if (!sessionStorage.getItem(sessionKey)) {
            sessionStorage.setItem(sessionKey, JSON.stringify({}));
        }

        function hasShownInSession(formId) {
            const shownForms = JSON.parse(sessionStorage.getItem(sessionKey) || '{}');
            return shownForms[formId] === true;
        }

        function markAsShownInSession(formId) {
            const shownForms = JSON.parse(sessionStorage.getItem(sessionKey) || '{}');
            shownForms[formId] = true;
            sessionStorage.setItem(sessionKey, JSON.stringify(shownForms));
        }

        // Detect when mouse leaves the window from the top (exit intent)
        document.addEventListener('mouseleave', function(e) {
            // Only trigger on exit from top
            if (e.clientY > 0) return;

            const exitIntentForms = document.querySelectorAll('[data-exit-intent="true"]');
            
            exitIntentForms.forEach(formWrapper => {
                const formId = formWrapper.getAttribute('data-form-id');
                
                if (!formId) return;

                // Check if already shown in this session
                if (hasShownInSession(formId)) return;

                // Check for cookie
                const cookieExists = document.cookie.split('; ').some(row => 
                    row.startsWith(`mrm_form_cookie_${formId}=`)
                );

                if (!cookieExists) {
                    // Remove the hide class to show the form
                    formWrapper.classList.remove('mrm-form-disable');
                    formWrapper.style.display = 'flex';
                    markAsShownInSession(formId);
                }
            });
        });

        // Alternative: Detect cursor position very close to top
        let lastClientY = null;
        const exitThreshold = 0; // pixels from top (near browser bookmarks)
        
        document.addEventListener('mousemove', function(e) {
            const currentY = e.clientY;
            
            // Only check if mouse is near the top
            if (currentY > exitThreshold) {
                lastClientY = currentY;
                return;
            }

            // Check if moving upward toward exit
            if (lastClientY !== null && currentY < lastClientY) {
                const exitIntentForms = document.querySelectorAll('[data-exit-intent="true"]');
                
                exitIntentForms.forEach(formWrapper => {
                    const formId = formWrapper.getAttribute('data-form-id');
                    
                    if (!formId) return;

                    // Check if already shown in this session
                    if (hasShownInSession(formId)) return;

                    // Check for cookie
                    const cookieExists = document.cookie.split('; ').some(row => 
                        row.startsWith(`mrm_form_cookie_${formId}=`)
                    );

                    if (!cookieExists) {
                        // Remove the hide class to show the form
                        formWrapper.classList.remove('mrm-form-disable');
                        formWrapper.style.display = 'flex';
                        markAsShownInSession(formId);
                    }
                });
            }

            lastClientY = currentY;
        });
    }

    // Initialize exit-intent detection when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExitIntentDetection);
    } else {
        initExitIntentDetection();
    }

    
    $('#mrm-unsubscribe-cancel').on('click', function(e) {
        e.preventDefault();
        window.close();
    });

    // Toggle dropdown visibility
    $('.mintmrm-dropdown-button').on('click', function(e) {
        e.preventDefault();
        $(this).toggleClass('show');
        $('.add-contact.mintmrm-dropdown').toggleClass('show');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.mintmrm-dropdown-button, .add-contact.mintmrm-dropdown').length) {
            $('.mintmrm-dropdown-button').removeClass('show');
            $('.add-contact.mintmrm-dropdown').removeClass('show');
        }
    });

    const updateDropdownButton = () => {
        const $dropdownButton = $('.mintmrm-dropdown-button');
        const $checkboxes = $('.mintmrm-dropdown-list input[type="checkbox"]').not('#all-items-create');
        const $checkedCheckboxes = $checkboxes.filter(':checked');

        $dropdownButton.empty();
        if ($checkedCheckboxes.length > 0) {
            $checkedCheckboxes.each(function() {
                const title = $(this).next('label').text();
                $dropdownButton.append(
                    `<span class="single-list mintmrm-tag-list">${title}
                        <span class="close-list" title="Delete">&#10005;</span>
                    </span>`
                );
            });
        } else {
            $dropdownButton.text('Select Tag');
        }
    };

    // Handle individual checkbox selection
    $('.mintmrm-dropdown-list input[type="checkbox"]').not('#all-items-create').on('change', function() {
        const $allItemsCheckbox = $('#all-items-create');
        const $checkboxes = $('.mintmrm-dropdown-list input[type="checkbox"]').not($allItemsCheckbox);
        const $checkedCheckboxes = $checkboxes.filter(':checked');

        // Update "Select All Items" checkbox
        if ($checkedCheckboxes.length === $checkboxes.length) {
            $allItemsCheckbox.prop('checked', true);
        } else {
            $allItemsCheckbox.prop('checked', false);
        }

        updateDropdownButton();
    });

    // Handle "Select All Items" checkbox
    $('#all-items-create').on('change', function() {
        const $checkboxes = $('.mintmrm-dropdown-list input[type="checkbox"]').not(this);
        $checkboxes.prop('checked', $(this).is(':checked'));
        updateDropdownButton();
    });

    // Remove item from button when close icon is clicked
    $(document).on('click', '.close-list', function() {
        const $checkbox = $(`input[type="checkbox"][value="${$(this).parent().text().trim()}"]`);
        $checkbox.prop('checked', false).trigger('change');
    });

    // Search functionality on input
    $('.searchbar.mintmrm-dropdown-list input[type="search"]').on('input', function() {
        const searchValue = $(this).val().toLowerCase();
        const $items = $('.option-section .single-column');
        let hasMatch = false;

        $items.each(function() {
            const itemText = $(this).find('label').text().toLowerCase();
            const isMatch = itemText.includes(searchValue);
            $(this).toggle(isMatch);
            if (isMatch) {
                hasMatch = true;
            }
        });

        // Show "No items found" if no matches
        if (!hasMatch) {
            if (!$('.no-items-found').length) {
                $('.option-section').append('<div class="no-items-found">No items found</div>');
            }
        } else {
            $('.no-items-found').remove();
        }
    });
});