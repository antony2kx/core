(function ($) {
    $(document).ready(function () {
        $(document.body).on('click', '.mailoptin-optin-theme', function (e) {
            var optin_campaign_title_obj = $('#mailoptin-add-optin-campaign-title');
            // remove input field error on change.
            optin_campaign_title_obj.change(function () {
                optin_campaign_title_obj.removeClass('mailoptin-input-error');
            });

            if (!optin_campaign_title_obj.val()) {
                optin_campaign_title_obj.addClass('mailoptin-input-error');
            }
            else {
                optin_campaign_title_obj.removeClass('mailoptin-input-error');
                $(".mailoptin-error").remove();
                $('.mailoptin-new-toolbar i.fa-spinner').css('opacity', 1);

                var ajaxData = {
                    action: 'mailoptin_create_optin_campaign',
                    nonce: mailoptin_globals.nonce,
                    title: optin_campaign_title_obj.val().trim(),
                    theme: $(this).attr('data-optin-theme').trim(),
                    type: $(this).attr('data-optin-type').trim()
                };

                $.post(ajaxurl, ajaxData, function (response) {
                        if (response.success && response.data.redirect) {
                            window.location.assign(response.data.redirect);
                        }
                        else {
                            var error_msg = response.data ? response.data : '';
                            optin_campaign_title_obj.after('<span class="mailoptin-error">' + error_msg + '</span>');
                            $('.mailoptin-new-toolbar i.fa-spinner').css('opacity', 0);
                        }
                    }, 'json'
                );
            }
        });
    });
})(jQuery);