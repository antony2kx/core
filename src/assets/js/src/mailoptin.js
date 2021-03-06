/**
 * @var {object} mailoptin_globals
 */
define(['jquery', 'js.cookie', 'mailoptin_globals', 'moModal', 'moExitIntent', 'moScrollTrigger'],
    function ($, Cookies, mailoptin_globals) {
        "use strict";

        $.MailOptin = {

            /**
             * Is the current screen customizer preview?
             * @return {boolean}
             */
            is_customize_preview: (mailoptin_globals.is_customize_preview === 'true'),

            /**
             * Track optin conversion and impression.
             *
             * @param {string} optin_uuid
             */
            track_impression: function (optin_uuid) {
                // bail if this is customizer preview
                if ($.MailOptin.is_customize_preview === true) return;

                var stat_data = {
                    optin_uuid: optin_uuid,
                    conversion_page: window.location.href,
                    referrer: document.referrer || ""
                };

                $.post(
                    mailoptin_globals.ajaxurl,
                    {
                        action: 'mailoptin_track_impression',
                        stat_data: stat_data
                    }
                );
            }
        };

        var mailoptin_optin = {

            mailoptin_jq_plugin: function () {
                var self = this;
                $.fn.mailoptin = function () {
                    var modal_options, $optin_uuid, $optin_type, $optin_css_id, optin_js_config, test_mode;
                    $optin_uuid = this.attr('id');
                    $optin_type = this.attr('data-optin-type');
                    $optin_css_id = $optin_uuid + '_' + $optin_type;
                    optin_js_config = self.optin_js_config($optin_css_id);
                    test_mode = ($.MailOptin.is_customize_preview === true) ? true : optin_js_config.test_mode;

                    // add the close-optin event handler. modal/lightbox has its own so skip.
                    if (this.hasClass('mo-optin-form-lightbox') === false) {
                        $(document).on('click.moOptin', 'a[rel~="moOptin:close"]', {
                                'optin_uuid': $optin_uuid,
                                'optin_type': $optin_type,
                                'optin_js_config': optin_js_config,
                                'self': self
                            }, self.close_optin
                        );

                        $(document).on('click.moOptin', '.mo-close-optin', {
                                'optin_uuid': $optin_uuid,
                                'optin_type': $optin_type,
                                'optin_js_config': optin_js_config,
                                'self': self
                            }, self.close_optin
                        );
                    }

                    // remove the close optin event if we're in customizer.
                    if ($.MailOptin.is_customize_preview === true) {
                        $(document).off('submit.moOptinSubmit', 'form.mo-optin-form');
                        $(document).off('click.moOptin', 'a[rel~="moOptin:close"]');
                        $(document).off('click.moOptin', '.mo-close-optin');
                    }

                    /** lightbox / modal */
                    if (this.hasClass('mo-optin-form-lightbox')) {
                        modal_options = {
                            optin_uuid: $optin_uuid,
                            bodyClose: optin_js_config.body_close,
                            keyClose: optin_js_config.body_close,
                            test_mode: test_mode,
                            iconClose: optin_js_config.icon_close,
                            onOpen: function () {
                                self.animate_optin_display.call(this, optin_js_config.effects);
                            },
                            onClose: function () {
                                self.set_cookie('exit', $optin_uuid, optin_js_config);
                            }
                        };

                        if ($.MailOptin.is_customize_preview === true) {
                            modal_options.keyClose = false;
                            modal_options.bodyClose = false;
                            modal_options.test_mode = true;
                        }

                        // merge modal specific object with that of optin js config
                        optin_js_config = $.extend({}, modal_options, optin_js_config);

                        self.rule_base_show_optin_form.call(this, optin_js_config, 'lightbox');
                    }

                    /** Notification bar */
                    if (this.hasClass('mo-optin-form-bar')) {

                        // only one instance of top bar can show at a time.
                        if ($.MailOptin['isActiveMOBar_' + optin_js_config.bar_position] === true) return;

                        self.rule_base_show_optin_form.call(this, optin_js_config, 'bar');
                    }

                    /** Slide INs */
                    if (this.hasClass('mo-optin-form-slidein')) {

                        // only one instance of slidein type can shown at a time.
                        if ($.MailOptin['isActiveMOSlidein_' + optin_js_config.slidein_position] === true) return;

                        self.rule_base_show_optin_form.call(this, optin_js_config, 'slidein');
                    }
                };

                $.fn.extend({
                    animateOptin: function (animationName) {
                        var animationEnd = 'webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend';
                        this.addClass('MOanimated ' + animationName).one(animationEnd, function () {
                            $(this).removeClass('MOanimated ' + animationName);
                        });
                    }
                });
            },

            /**
             * Is after x seconds rule active?
             *
             * @param {object} optin_config
             * @returns {boolean}
             */
            is_after_x_seconds_active: function (optin_config) {
                return optin_config.x_seconds_status === true && optin_config.x_seconds_value !== undefined;
            },

            /**
             * Is after x seconds rule active?
             *
             * @param {object} optin_config
             * @returns {boolean}
             */
            is_after_x_scroll_active: function (optin_config) {
                return optin_config.x_scroll_status === true && optin_config.x_scroll_value !== undefined;
            },

            /**
             * Is after x page views rule active?
             *
             * @param {object} optin_config
             * @returns {boolean}
             */
            is_after_x_page_views_active: function (optin_config) {
                return optin_config.x_page_views_status === true &&
                    optin_config.x_page_views_condition !== undefined &&
                    optin_config.x_page_views_value !== undefined;
            },

            /**
             * Is exit intent rule active?
             *
             * @param {object} optin_config
             * @returns {boolean}
             */
            is_exit_intent_active: function (optin_config) {
                return optin_config.exit_intent_status === true;
            },

            /**
             * Determine if optin should display or not.
             *
             * @param {string} $optin_uuid
             *
             * @returns {boolean}
             */
            is_optin_visible: function ($optin_uuid) {
                // if global success cookie found, do not display any optin.
                if (Cookies.get('mo_global_success_cookie')) return false;
                // if global interaction/exit cookie found, do not display any optin.
                if (Cookies.get('mo_global_cookie')) return false;
                // if success cookie found for this optin, do not display it.
                if (Cookies.get('mo_success_' + $optin_uuid)) return false;
                // if exit cookie found for this optin, do not dispay it.
                if (Cookies.get('mo_' + $optin_uuid)) return false;

                return true;
            },

            /**
             * Handle display/showing of optin form.
             *
             * @param {object} optin_config for lightbox, this is modal_options.  others is optin_js_config
             * @param {string} optin_type type of optin
             */
            rule_base_show_optin_form: function (optin_config, optin_type) {

                var self = mailoptin_optin;
                // we did this becos 'this' inside setTimeout() will be wrong.
                var _this = this;

                // if customizer, display immediately.
                if ($.MailOptin.is_customize_preview === true || optin_config.test_mode === true) {
                    return self.display_optin_form.call(_this, optin_config, optin_type);
                }

                if (self.is_optin_visible(optin_config.optin_uuid) === false) return;

                if (self.is_after_x_page_views_active(optin_config)) {
                    var x_page_views_condition = optin_config.x_page_views_condition;
                    var x_page_views_value = optin_config.x_page_views_value;

                    switch (x_page_views_condition) {
                        // for each condition, do the inverse return false if comparison is true.
                        case 'equals':
                            if (self.get_page_views() !== x_page_views_value)  return;
                            break;
                        case 'more_than':
                            if (self.get_page_views() <= x_page_views_value) return;
                            break;
                        case 'less_than':
                            if (self.get_page_views() >= x_page_views_value)  return;
                            break;
                        case 'at_least':
                            if (self.get_page_views() < x_page_views_value)  return;
                            break;
                        case 'not_more_than':
                            if (self.get_page_views() > x_page_views_value)  return;
                            break;
                    }
                }


                var wait_seconds = optin_config.x_seconds_value * 1000;
                var optin_scroll_percent = optin_config.x_scroll_value;

                // If all three rules are active, run the below shebang
                if (self.is_after_x_seconds_active(optin_config) === true &&
                    self.is_after_x_scroll_active(optin_config) === true &&
                    self.is_exit_intent_active(optin_config) === true) {
                    setTimeout(function () {
                        $.moScrollTrigger('enable');
                        $(document).on('moScrollTrigger', function (e, pctScrolled) {
                            if (pctScrolled >= optin_scroll_percent) {
                                $.moExitIntent('enable');
                                $(document).on("moExitIntent", function () {
                                    return self.display_optin_form.call(_this, optin_config, optin_type);
                                });
                            }
                        });

                    }, wait_seconds);

                    return;
                }

                // If only "is_after_x_scroll_active" and "is_exit_intent_active" rules are active, run the below shebang
                if (self.is_after_x_scroll_active(optin_config) === true &&
                    self.is_exit_intent_active(optin_config) === true) {

                    $.moScrollTrigger('enable');
                    $(document).on('moScrollTrigger', function (e, pctScrolled) {
                        if (pctScrolled >= optin_scroll_percent) {
                            $.moExitIntent('enable');
                            $(document).on("moExitIntent", function () {
                                return self.display_optin_form.call(_this, optin_config, optin_type);
                            });
                        }
                    });

                    return;
                }

                // If only "after_x_seconds" and "after_x_scroll" rules are active, run the below shebang
                if (self.is_after_x_seconds_active(optin_config) === true &&
                    self.is_after_x_scroll_active(optin_config) === true) {

                    setTimeout(function () {
                        $.moScrollTrigger('enable');
                        $(document).on('moScrollTrigger', function (e, pctScrolled) {
                            if (_this.hasClass('si-open') === false) {
                                if (pctScrolled >= optin_scroll_percent) {
                                    _this.addClass('si-open');
                                    return self.display_optin_form.call(_this, optin_config, optin_type);
                                }
                            }
                        });

                    }, wait_seconds);

                    return;
                }

                // If only "after_x_seconds" and "exit intent" rules are active, run the below shebang
                if (self.is_after_x_seconds_active(optin_config) === true &&
                    self.is_exit_intent_active(optin_config) === true) {
                    setTimeout(function () {
                        $.moExitIntent('enable');
                        $(document).on("moExitIntent", function () {
                            return self.display_optin_form.call(_this, optin_config, optin_type);
                        });

                    }, wait_seconds);

                    return;
                }

                // If only "after_x_seconds" rules is active, run the below shebang
                if (self.is_after_x_seconds_active(optin_config) === true) {
                    setTimeout(function () {
                        return self.display_optin_form.call(_this, optin_config, optin_type);
                    }, wait_seconds);

                    return;
                }

                // If only "after x scroll" rules is active, run the below shebang
                if (self.is_after_x_scroll_active(optin_config)) {
                    $.moScrollTrigger('enable');
                    $(document).on('moScrollTrigger', function (e, pctScrolled) {
                        if (_this.hasClass('si-open') === false) {
                            if (pctScrolled >= optin_scroll_percent) {
                                _this.addClass('si-open');
                                return self.display_optin_form.call(_this, optin_config, optin_type);
                            }
                        }
                    });

                    return;
                }

                // If only "exit intent" rules is active, run the below shebang
                if (self.is_exit_intent_active(optin_config)) {
                    $.moExitIntent('enable');
                    $(document).on("moExitIntent", function () {
                        return self.display_optin_form.call(_this, optin_config, optin_type);
                    });

                    return;
                }

                return self.display_optin_form.call(_this, optin_config, optin_type);
            },

            /**
             * Optin-type agnostic helper function to display optin form.
             * @param {object} optin_config
             * @param {string} optin_type
             */
            display_optin_form: function (optin_config, optin_type) {
                var self = mailoptin_optin;

                // do cookie checking if we are not in customizer mode and not test mode is active.
                if ($.MailOptin.is_customize_preview === false && optin_config.test_mode === false) {
                    if (self.is_optin_visible(optin_config.optin_uuid) === false) return;
                }

                if (optin_type !== undefined && optin_type === 'lightbox') {
                    this.MoModalBox(optin_config);
                    // trigger optin show event.
                    $(this).trigger('moOptin:show', [optin_config.optin_uuid]);
                    // stop further execution
                    return;
                }

                if (optin_config.effects !== undefined) {
                    self.animate_optin_display.call(this, optin_config.effects);
                    self.flag_optin_type_displayed(optin_config, optin_type);
                    this.show();
                    $(this).trigger('moOptin:show', [optin_config.optin_uuid]);
                    return;
                }

                self.flag_optin_type_displayed(optin_config, optin_type);
                this.fadeIn();
                // trigger optin show event.
                $(this).trigger('moOptin:show', [optin_config.optin_uuid]);
            },

            /**
             * Set flag when an optin-type is displayed to prevent multiple optin-type instance showing.
             *
             * @param {object} optin_config
             * @param {string} optin_type
             */
            flag_optin_type_displayed: function (optin_config, optin_type) {

                if (optin_type === 'bar') {
                    var bar_position = optin_config.bar_position;
                    $.MailOptin['isActiveMOBar_' + bar_position] = true;
                }

                if (optin_type === 'slidein') {
                    var slidein_position = optin_config.slidein_position;
                    $.MailOptin['isActiveMOSlidein_' + slidein_position] = true;
                }
            },

            /**
             * Set flag when an optin-type is closed.
             *
             * @param {object} optin_config
             * @param {string} optin_type
             */
            flag_optin_type_close: function (optin_config, optin_type) {
                if (optin_type === 'bar') {
                    var bar_position = optin_config.bar_position;
                    $.MailOptin['isActiveMOBar_' + bar_position] = false;
                }

                if (optin_type === 'slidein') {
                    var slidein_position = optin_config.slidein_position;
                    $.MailOptin['isActiveMOSlidein_' + slidein_position] = false;
                }
            },

            /**
             * Closes any displayed optin. well doesn't for modals as they have theirs.
             */
            close_optin: function (e) {
                e.preventDefault();

                var optin_container = $(this).parents('.moOptinForm');
                var optin_uuid = optin_container.attr('id');
                var optin_type = optin_container.attr('data-optin-type');
                var optin_css_id = optin_uuid + '_' + optin_type;
                var optin_config = mailoptin_optin.optin_js_config(optin_css_id);

                optin_container.fadeOut(400, function () {
                    $(this).trigger('moOptin:close', [this]);
                });

                // cleanup for on-scroll optin to prevent from triggering all the time
                optin_container.removeClass('si-open');

                mailoptin_optin.set_cookie('exit', optin_uuid, optin_config);
                mailoptin_optin.flag_optin_type_close(optin_config, optin_type);
            },

            /**
             * Initialize optin event handlers.
             */
            initOptinForms: function () {
                /**
                 * simply this for all optin types using one single selector. initOptin
                 */
                $(".mo-optin-form-lightbox, .mo-optin-form-bar, .mo-optin-form-slidein").each(function (index, element) {
                    var optin_container = $(element);

                    /** @todo click launch */
                    $('a[data-modal]').click(function (event) {
                        optin_container.mailoptin();
                    });

                    optin_container.mailoptin();
                });
            },

            /**
             * Track number of page views.
             */
            track_page_views: function () {
                var prev_count = Cookies.get('mo_page_views_counter');
                var count = (prev_count === undefined) ? 0 : prev_count;

                // cookie expiration is missing thus making it a session cookie.
                Cookies.set('mo_page_views_counter', ++count);
            },

            /**
             * Get number of page views.
             */
            get_page_views: function () {
                return Number(Cookies.get('mo_page_views_counter'));
            },

            /**
             * Animate optin form display
             */
            animate_optin_display: function (effects) {
                if ((effects != '') || (effects !== undefined)) {
                    this.find('.mo-optin-form-wrapper').animateOptin(effects);
                }
            },

            /**
             * Return the configuration in Javascript of an optin.
             * @param {string} optin_css_id
             * @returns {object}
             */
            optin_js_config: function (optin_css_id) {
                return window[optin_css_id];
            },

            /**
             * Handle actual conversion of optin.
             */
            optin_conversion: function () {

                var optin_data, optin_container, $optin_uuid, $optin_type, $optin_css_id, optin_js_config, self;

                self = this;

                // if we are in customizer preview, bail.
                if ($.MailOptin.is_customize_preview === true) return;

                $(document).on('submit.moOptinSubmit', 'form.mo-optin-form', function (e) {
                    e.preventDefault();

                    optin_container = $(this).parents('.moOptinForm');
                    $optin_uuid = optin_container.attr('id');
                    $optin_type = optin_container.attr('data-optin-type');
                    $optin_css_id = $optin_uuid + '_' + $optin_type;
                    optin_js_config = self.optin_js_config($optin_css_id);

                    self.hide_optin_error($optin_uuid);

                    // data variable is only populated if validation passes.
                    if (self.validate_optin_form_fields($optin_css_id, optin_js_config)) {
                        optin_data = {
                            optin_uuid: $optin_uuid,
                            email: $('input#' + $optin_css_id + '_email_field').val(),
                            name: $('input#' + $optin_css_id + '_name_field').val(),
                            _mo_timestamp: $('input#' + $optin_css_id + '_honeypot_timestamp').val(),
                            user_agent: navigator.userAgent,
                            conversion_page: window.location.href,
                            referrer: document.referrer || ""
                        };

                        self.addProcessingOverlay.call(optin_container);

                        self.subscribe_to_email_list(optin_data, optin_container, optin_js_config, $optin_type);
                    }
                });
            },

            /**
             * Add overlay over optin lighbox/modal that shows spinner and success message.
             *
             */
            addProcessingOverlay: function () {
                this.find('.mo-optin-spinner').show();
            },

            /**
             * Remove overlay over optin lighbox/modal that shows spinner and success message.
             *
             */
            removeProcessingOverlay: function () {
                this.find('.mo-optin-spinner').hide();
            },

            /**
             * Add close icon to processing overlay modal after successful optin.
             */
            addSuccessCloseIcon: function () {
                this.find('.mo-optin-spinner').after('<a href="#" class="mo-optin-success-close" rel="moOptin:close">×</a>');
            },

            /**
             * Remove close icon to processing overlay modal after successful optin.
             */
            removeSuccessCloseIcon: function () {
                this.find('.mo-optin-success-close').remove();
            },

            /**
             * overlay over optin lighbox/modal that shows spinner and success message.
             *
             * @param {object} success_message
             */
            displaySuccessContent: function (success_message) {
                // display the success container div.
                this.find('.mo-optin-success-msg').show();
            },

            /**
             * Remove spinner.
             */
            removeSpinner: function () {
                // remove spinner gif icon
                this.find('.mo-optin-spinner').css('background-image', 'none');
            },

            /**
             * Display error message from optin.
             *
             * @param {string} error_message
             */
            displayErrorMessage: function (error_message) {
                this.find('.mo-optin-error').text(error_message).show();
            },

            /**
             * Set conversion / close cookie for campaign.
             *
             * @param {string} type type of cookie to set. Can be exit or success cookie.
             * @param {string} optin_uuid
             * @param {object} optin_js_config
             */
            set_cookie: function (type, optin_uuid, optin_js_config) {
                // default test mode to false.
                var test_mode = optin_js_config.test_mode || false;
                // if type is exit cookie, return empty. if it's success, it get appended to 'mo_' when setting cookie key.
                type = type === 'exit' ? '' : type + '_';

                if (!test_mode) {
                    Cookies.set('mo_' + type + optin_uuid, true, {expires: optin_js_config.cookie});
                    // set either global exit or success cookie depending on the context in which set_cookie() is called.
                    // cookie expiration could be optin_js_config.global_cookie or optin_js_config.global_success_cookie
                    // no need to check if cookie value is not zero(0) before setting cookie because a cookie set to expires in 0 days
                    // returns undefined when tried to be gotten.
                    Cookies.set('mo_global_' + type + 'cookie', true, {expires: optin_js_config['global_' + type + 'cookie']});
                }
            },

            /**
             * POST collected optin data to appropriate connected email list.
             *
             * @param {mixed} optin_data
             * @param {object} optin_container jQuery object of the parent div container
             * @param {object} optin_js_config optin JS configuarations
             * @param {object} $optin_type optin optin type of the optin form being looped.
             */
            subscribe_to_email_list: function (optin_data, optin_container, optin_js_config, $optin_type) {
                var self = this;

                $.post(
                    mailoptin_globals.ajaxurl,
                    {
                        action: 'mailoptin_add_to_email_list',
                        optin_data: optin_data
                    },
                    function (response) {
                        if (!$.isEmptyObject(response) && 'success' in response) {
                            if (response.success === true) {
                                // set cookie for this option conversion
                                self.set_cookie('success', optin_data.optin_uuid, optin_js_config);

                                // do not include success icon if icon_close (close icon automatically attached to lightbox) is set to true.
                                // icon_close config is always false for none lightbox optin forms. see ./Core/src/OptinForms/AbstractOptinForm.php LN497

                                if (optin_js_config.icon_close !== true) {
                                    self.addSuccessCloseIcon.call(optin_container);
                                }

                                self.displaySuccessContent.call(optin_container, optin_js_config.success_message);
                                self.removeSpinner.call(optin_container);
                            }
                            else {
                                self.removeProcessingOverlay.call(optin_container);
                                self.removeSuccessCloseIcon.call(optin_container);
                                self.displayErrorMessage.call(optin_container, response.message);
                            }
                        }
                        else {
                            self.displayErrorMessage.call(optin_container, optin_js_config.unexpected_error);
                            self.removeProcessingOverlay.call(optin_container);
                            self.removeSuccessCloseIcon.call(optin_container);
                        }
                    },
                    'json'
                );
            },

            /**
             * Validate name and email fields.
             *
             * @param {string} $optin_css_id optin CSS ID
             * @param {object} optin_js_config optin js config
             * @returns {boolean}
             */
            validate_optin_form_fields: function ($optin_css_id, optin_js_config) {

                var namefield_error = optin_js_config.name_missing_error;
                var emailfield_error = optin_js_config.email_missing_error;
                var honeypot_error = optin_js_config.honeypot_error;

                var self = this;
                var name_field = $('#' + $optin_css_id + '_name_field:visible');
                var email_field = $('#' + $optin_css_id + '_email_field:visible');

                var honeypot_email_field = $('#' + $optin_css_id + '_honeypot_email_field').val();
                var honeypot_website_field = $('#' + $optin_css_id + '_honeypot_website_field').val();
                var response = true;

                // Throw error if either of the honeypot fields are filled.
                if (honeypot_email_field.length > 0 || honeypot_website_field.length > 0) {
                    self.display_optin_error.call(undefined, $optin_css_id, honeypot_error);
                    response = false;
                }

                // if this is an email field, validate that the email address.
                if (email_field.length > 0) {
                    if (self.isValidEmail(email_field.val()) === false) {
                        self.display_optin_error.call(email_field, $optin_css_id, emailfield_error);
                        response = false;
                    }
                }

                // if this is a name field, check if the field isn't empty.
                if (name_field.length > 0) {
                    if (name_field.val() === "") {
                        self.display_optin_error.call(name_field, $optin_css_id, namefield_error);
                        response = false;
                    }
                }

                return response;
            },

            /**
             * Output an optin error with the field highlighted red.
             *
             * @param {string} $optin_css_id optin CSS ID
             * @param {string} error
             */
            display_optin_error: function ($optin_css_id, error) {
                if (this !== undefined) {
                    this.css("-webkit-box-shadow", "inset 0px 0px 0px 2px #f45a4a");
                    this.css("-moz-box-shadow", "inset 0px 0px 0px 2px #f45a4a");
                    this.css("box-shadow", "inset 0px 0px 0px 2px #f45a4a");
                }
                $('div#' + $optin_css_id + ' .mo-optin-error').text(error).show();
            },

            /**
             * Hide optin error including removing the red border.
             *
             * @param {string} $optin_css_id optin CSS ID
             */
            hide_optin_error: function ($optin_css_id) {
                var input_fields = $('div#' + $optin_css_id + ' .mo-optin-field');
                var obj = $('div#' + $optin_css_id + ' .mo-optin-error').hide();
                input_fields.css('-webkit-box-shadow', '');
                input_fields.css('-moz-box-shadow', '');
                input_fields.css('box-shadow', '');
            },

            /**
             * Check if email address is valid.
             *
             * @param {string} email
             * @returns {boolean}
             */
            isValidEmail: function (email) {
                return (new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i)).test(email);
            },

            /**
             * All event subscription / listener should go here.
             */
            eventSubscription: function () {
                var self = this;
                // track impression for optin form other than modals
                $(document).on('moOptin:show', function (e, optin_uuid) {
                    $.MailOptin.track_impression(optin_uuid)
                });
            },

            /**
             * Initialize class
             */
            init: function () {
                var _this = this;
                // don't wait for dom to be loaded first. start tracking asap.
                _this.track_page_views();
                $(function () {
                    _this.eventSubscription();
                    _this.mailoptin_jq_plugin();
                    _this.initOptinForms();
                    _this.optin_conversion();
                });
            }
        };

        mailoptin_optin.init();
    });