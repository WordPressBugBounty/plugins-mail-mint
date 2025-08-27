jQuery(document).ready(function ($) {
    
    $(document).on("click", ".mailmint-promotional-banner .close-promotional-banner", function(event) {
		event.preventDefault();
        $('.mailmint-promotional-banner').css('display','none');
        jQuery.ajax({
            type : "post",
            dataType : "json",
            url : mrm_admin_ajax.ajaxurl,
            data : {action: "mint_delete_promotional_banner", nonce: window.mrm_admin_ajax.nonce}
        }) 
	});

    $(document).on("click", ".mint-notification-counter__btn-area.close-no-thanks", function(event) {
		event.preventDefault();
        $('.mailmint-promotional-banner').css('display','none');
        jQuery.ajax({
            type : "post",
            dataType : "json",
            url : mrm_admin_ajax.ajaxurl,
            data : {action: "mint_delete_promotional_banner", nonce: window.mrm_admin_ajax.nonce}
        }) 
	});

    $(document).on("click", ".mailmint-database-update-notice", function(event) {
		event.preventDefault();
        $('.mailmint-promotional-banner').css('display','none');
        jQuery.ajax({
            type : "post",
            dataType : "json",
            url : mrm_admin_ajax.ajaxurl,
            data : {action: "mint_delete_promotional_banner", nonce: window.mrm_admin_ajax.nonce}
        }) 
	});
});
