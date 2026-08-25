/**
 * @namespace MintAbandonedCart
 */

/**
 * @typedef {Object} FormData
 * @property {string} email - The email address.
 * @property {Object} checkout_fields_data - The data of checkout fields.
 * @property {string} wp_nonce - The WordPress nonce.
 * @property {string} action - The action to perform.
 */

/**
 * @typedef {Object} Response
 * @property {Function} json - A function to parse the response as JSON.
 */

/**
 * @callback FetchCallback
 * @param {Response} res - The fetch response object.
 */

/**
 * @typedef {Object} MintFrontendVars
 * @property {Object} abandoned_setting - The abandoned cart settings.
 * @property {boolean} enable - Indicates if abandoned cart feature is enabled.
 * @property {string} nonce - The WordPress nonce.
 */

/**
 * Represents the MintAbandonedCart module.
 * @type {Object}
 */
var MintAbandonedCart;

(function ($) {
    "use strict";    
    /**
     * Serialize and encode the form object.
     * @returns {string} The serialized and encoded form data.
     */
    $.fn.mint_serializeAndEncode = function () {
        return $.map(this.serializeArray(), function (val) {
            let field = $("input[name='" + val.name + "']");
            if (field.attr('type') == 'checkbox') {
                if (field.prop("checked")) {
                    return [val.name, encodeURIComponent('1')].join('=');
                } else {
                    return [val.name, encodeURIComponent('0')].join('=');
                }
            } else {
                return [val.name, encodeURIComponent(val.value)].join('=');
            }
        }).join('&');
    };

    /**
     * Initializes the MintAbandonedCart module.
     */
    MintAbandonedCart = {
        checkout_form: $('form.checkout'),
        checkout_fields_data: {},
        checkout_fields: [],
        updateCheckout: 0,

        /**
         * Initializes the MintAbandonedCart module.
         */
        init: function () {
            this.checkout_fields = [
                'billing_first_name',
                'billing_last_name',
                'billing_company',
                'billing_phone',
                'billing_country',
                'billing_address_1',
                'billing_address_2',
                'billing_city',
                'billing_state',
                'billing_postcode',
                'shipping_first_name',
                'shipping_last_name',
                'shipping_company',
                'shipping_country',
                'shipping_address_1',
                'shipping_address_2',
                'shipping_city',
                'shipping_state',
                'shipping_postcode',
                'shipping_phone',
            ];

            $.each(MintAbandonedCart.checkout_fields, function (i, field_name) {
                MintAbandonedCart.checkout_fields_data[field_name] = '';
            });

            this.add_gdpr_message();
        },

        /**
         * Captures the email when focus is lost on the billing email field.
         */
        mint_capture_email: function () {
            $(document).on('focusout', '#billing_email, #email', function () {
                MintAbandonedCart.mint_get_checkout_data();
            });

            var billing_email = jQuery('#billing_email').val();
            
			if( !billing_email  ){
				billing_email = jQuery('#email').val();
			}

            if (billing_email !== '' && MintAbandonedCart.mint_isValidEmailAddress(billing_email)) {
                MintAbandonedCart.mint_capture_data_on_page_load();
                MintAbandonedCart.mint_process_email(billing_email);
            }
        },

        /**
         * Gets the checkout data when the billing email field is modified.
         */
        mint_get_checkout_data: function () {
            var email = $('#billing_email').val();

			if (!email) {
				email = $('#email').val();
			}

            if (email !== '' && MintAbandonedCart.mint_isValidEmailAddress(email)) {
                MintAbandonedCart.mint_process_email(email);
            }
        },

        /**
         * Captures the checkout data on page load.
         */
        mint_capture_data_on_page_load: function () {
            $.each(MintAbandonedCart.checkout_fields, function (i, field_name) {
                var $this = $('#' + field_name);
                MintAbandonedCart.checkout_fields_data[field_name] = $this.val();
            });
        },

        /**
         * Captures the checkout field data when a change event occurs.
         */
        mint_captureCheckoutField: function () {
            if (MintAbandonedCart.updateCheckout === 0 && !window.MintFrontendVars?.is_checkout_block) {
                return;
            }
            
            var field_name = $(this).attr('name');

            if(!field_name){
                field_name = $(this).attr('id');
                var checkout_formdata = MintAbandonedCart.get_form_data();
                MintAbandonedCart.checkout_fields_data = checkout_formdata;
                return;
            }
            /** for checking checkbox fields **/
            if ($(this).attr('type') == 'checkbox') {
                if ($(this).prop('checked')) {
                    $(this).val(1);
                } else {
                    $(this).val(0);
                }
            }
            if (!field_name || MintAbandonedCart.checkout_fields.indexOf(field_name) === -1) {
                return;
            }

            if (!$(this).val() || MintAbandonedCart.checkout_fields_data[field_name] === $(this).val()) {
                return;
            }
            var checkout_formdata = MintAbandonedCart.checkout_form.mint_serializeAndEncode();
            checkout_formdata = mint_deserialize_obj(checkout_formdata);
            MintAbandonedCart.checkout_fields_data = checkout_formdata;
        },

        /**
         * Processes the email and updates the checkout data.
         * @param {string} email - The email address.
         */
        mint_process_email: function (email) {
            if ('undefined' === typeof email || !window.MintFrontendVars?.abandoned_setting.enable || (0 === MintAbandonedCart.updateCheckout && !window.MintFrontendVars?.is_checkout_block)  || '' === email ) {
                return;
            }

            var data = new FormData();
            data.append("email", email);
            data.append("checkout_fields_data", JSON.stringify(MintAbandonedCart.checkout_fields_data));
            data.append("wp_nonce", window.MintFrontendVars?.nonce);
            fetch((window.MintFrontendVars?.rest_url || "/wp-json/") + "mint-mail/v1/abandoned-cart/update-checkout", {
                method: "POST",
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'X-WP-Nonce': window.MintFrontendVars?.nonce
                },
                body: data
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (response) {
                    // Handle the response if needed
                });
        },

        /**
         * Checks if the given email address is valid.
         * @param {string} emailAddress - The email address to validate.
         * @returns {boolean} True if the email address is valid, false otherwise.
         * @since 1.5.0
         */
        mint_isValidEmailAddress: function (emailAddress) {
            var pattern = new RegExp(/^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/i);

            return pattern.test(emailAddress);
        },

        /**
         * Initializes the abandoned cart functionality.
         */
        abandoned_cart: function () {
            if (window.MintFrontendVars?.abandoned_setting.enable) {
                MintAbandonedCart.mint_capture_email();
            }
        },

        get_form_data: function(){
            const data = {};
            MintAbandonedCart.checkout_form.find('input, select, textarea').each(function() {
                const id = $(this).attr('id'); // Use 'id' as the key
                if (id) { // Make sure the field has an 'id'
                    if ($(this).is(':checkbox')) {
                        data[id] = $(this).is(':checked'); // For checkboxes, store whether they are checked
                    } else if ($(this).is(':radio')) {
                        if ($(this).is(':checked')) {
                            data[id] = $(this).val(); // For radio buttons, store the value of the selected one
                        }
                    } else {
                        data[id] = $(this).val(); // For other inputs, store the value
                    }
                }
            });
            return data;
        },

        add_gdpr_message: function () {
            if(window.MintFrontendVars?.abandoned_setting.enable && 'require' === window.MintFrontendVars?.abandoned_setting.gdpr_consent){
                if (!$('.wc-block-components-address-form__email').length) {
                    setTimeout(() => {
                        this.add_gdpr_message();
                    }, 2000);
                    return;
                }
                if ($('.mint-form-control-wrapper').length) {
                    return;
                }
                var mint_no_thanks = window.MintFrontendVars?.abandoned_setting.consent_text
                var mint_no_thanks_text = 'Your email and cart are saved so we can send you email reminders about this order'
                var mint_no_thanks_link_text = 'No Thanks'
                var mint_no_thanks_array = mint_no_thanks.split('{{')
                if( 2 === mint_no_thanks_array. length ){
                    mint_no_thanks_text  = mint_no_thanks_array[0]
                    mint_no_thanks_link_text = mint_no_thanks_array[1];
                    mint_no_thanks_link_text= mint_no_thanks_link_text.match(/label="([^"]+)"/)[1];
                }
                const text_link = "<a class='mint_email_consent_no_thanks'>" + mint_no_thanks_link_text + "</a>";
                var mint_email_gdpr_consent_message = '<span class="mint-gdpr-consent-text">' + mint_no_thanks_text + text_link + '</span>';
                const emailConsentHtml = '<input type="hidden" id="mint_email_gdpr_consent" value="1" />';
                mint_email_gdpr_consent_message += emailConsentHtml;
                $('.wc-block-components-address-form__email').after('<span class="form-row form-row-wide mint-form-control-wrapper mint-col-full">' + mint_email_gdpr_consent_message + '</span>');
                $(".mint_email_consent_no_thanks").on('click',function (){

                    var email = $('#billing_email').val();
                    if (!email) {
                        email = $('#email').val();
                    }
                    
                    var data = new FormData();
                    var that = $(this)
                    data.append("email", email);
                    data.append("wp_nonce", window.MintFrontendVars?.nonce);
                    fetch((window.MintFrontendVars?.rest_url || "/wp-json/") + "mint-mail/v1/abandoned-cart/skip-track", {
                        method: "POST",
                        headers: {
                            'Access-Control-Allow-Origin': '*',
                            'X-WP-Nonce': window.MintFrontendVars?.nonce
                        },
                        body: data
                    })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            $('#mint_email_gdpr_consent').val('0');
                            that.parent().fadeOut("slow");
                        });

                })
            }
        },
    };

    /**
     * Abandoned cart JS Function Initiate here
     */
    MintAbandonedCart.abandoned_cart = function () {
        if (window.MintFrontendVars?.abandoned_setting.enable) {
            MintAbandonedCart.mint_capture_email();
        }
    };

    /* Initialize */
    MintAbandonedCart.init();

    /**
     * Event handlers
     */
    $(window).on('load', function () {
        MintAbandonedCart.abandoned_cart();
        if(!MintAbandonedCart.checkout_form.length){
            setTimeout(()=>{
                MintAbandonedCart.checkout_form = $('form.wc-block-components-form');
                if(MintAbandonedCart.checkout_form.length > 0){
                    MintAbandonedCart.checkout_form.on('change', 'input', MintAbandonedCart.mint_captureCheckoutField);
                    MintAbandonedCart.checkout_form.on('change', 'select', MintAbandonedCart.mint_captureCheckoutField);
                    MintAbandonedCart.checkout_form.on('click change', '.input-checkbox', MintAbandonedCart.mint_captureCheckoutField);
                    MintAbandonedCart.checkout_form.on('blur change', '.input-text', MintAbandonedCart.mint_captureCheckoutField);
                    MintAbandonedCart.checkout_form.on('focusout', '.input-text', MintAbandonedCart.mint_captureCheckoutField);
                    $(document).on('blur change', 'input', MintAbandonedCart.mint_get_checkout_data);
                }
            }, 100)
        }
        // Detect change and save data in database
        MintAbandonedCart.checkout_form.on('change', 'select', MintAbandonedCart.mint_captureCheckoutField);
        MintAbandonedCart.checkout_form.on('click change', '.input-checkbox', MintAbandonedCart.mint_captureCheckoutField);
        MintAbandonedCart.checkout_form.on('blur change', '.input-text', MintAbandonedCart.mint_captureCheckoutField);
        MintAbandonedCart.checkout_form.on('focusout', '.input-text', MintAbandonedCart.mint_captureCheckoutField);
        $(document).on('blur change', '#billing_email, #email, .input-text,.input-checkbox', MintAbandonedCart.mint_get_checkout_data);
    });

    $(document).on('updated_checkout', function () {
        // Update Checkout is triggered
        MintAbandonedCart.updateCheckout = 1;
        MintAbandonedCart.mint_captureCheckoutField();
        var email = $('#billing_email').val();
		if( !email ){
			email = $('#email').val();
		}
        if ('' !== email ) {
            MintAbandonedCart.mint_process_email(email);
        }
    });
    
    if( window.MintFrontendVars?.abandoned_setting.enable && 'require' === window.MintFrontendVars?.abandoned_setting.gdpr_consent){
        var mint_no_thanks = window.MintFrontendVars?.abandoned_setting.consent_text
        var mint_no_thanks_text = 'Your email and cart are saved so we can send you email reminders about this order'
        var mint_no_thanks_link_text = 'No Thanks'
        var mint_no_thanks_array = mint_no_thanks.split('{{')
        if( 2 === mint_no_thanks_array. length ){
            mint_no_thanks_text  = mint_no_thanks_array[0]
            mint_no_thanks_link_text = mint_no_thanks_array[1];
            mint_no_thanks_link_text= mint_no_thanks_link_text.match(/label="([^"]+)"/)[1];
        }
        const text_link = "<a class='mint_email_consent_no_thanks' style='text-decoration:underline;cursor: pointer;'>" +mint_no_thanks_link_text + "</a>";
        var mint_email_gdpr_consent_message = '<span>' + mint_no_thanks_text + text_link +'</span>';
        const emailConsentHtml = '<input type="hidden" id="mint_email_gdpr_consent" value="1" />';
        mint_email_gdpr_consent_message += emailConsentHtml;

        $('#billing_email_field .woocommerce-input-wrapper #billing_email').after('<span class="form-row form-row-wide mint-form-control-wrapper mint-col-full">' + mint_email_gdpr_consent_message + '</span>');
        $(".mint_email_consent_no_thanks").on('click',function (){
            var email = $('#billing_email').val();
			if (!email) {
				email = $('#email').val();
			}
            
            var data = new FormData();
            var that = $(this)
            data.append("email", email);
            data.append("wp_nonce", window.MintFrontendVars?.nonce);
            fetch((window.MintFrontendVars?.rest_url || "/wp-json/") + "mint-mail/v1/abandoned-cart/skip-track", {
                method: "POST",
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'X-WP-Nonce': window.MintFrontendVars?.nonce
                },
                body: data
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (response) {
                    $('#mint_email_gdpr_consent').val('0');
                    that.parent().fadeOut("slow");
                });

        })

    }

})(jQuery);

/**
 * Deserializes the query string into an object.
 * @param {string} query - The query string to deserialize.
 * @returns {object} The deserialized object.
 */
function mint_deserialize_obj(query) {
    var setValue = function (root, path, value) {
        if (path.length > 1) {
            var dir = path.shift();
            if (typeof root[dir] === 'undefined') {
                root[dir] = path[0] === '' ? [] : {};
            }

            arguments.callee(root[dir], path, value);
        } else {
            if (root instanceof Array) {
                root.push(value);
            } else {
                root[path] = value;
            }
        }
    };

    var nvp = query.replace('?', '').split('&');
    var data = {};

    for (var i = 0; i < nvp.length; i++) {
        var pair = nvp[i].split('=');
        var name = decodeURIComponent(pair[0]);
        var value = decodeURIComponent(pair[1]);

        var path = name.match(/(^[^\[]+)(\[.*\]$)?/);
        var first = path[1];
        if (path[2]) {
            // case of 'array[level1]' || 'array[level1][level2]'
            path = path[2].match(/(?=\[(.*)\]$)/)[1].split('][');
        } else {
            // case of 'name'
            path = [];
        }
        path.unshift(first);

        setValue(data, path, value);
    }

    return data;
}
