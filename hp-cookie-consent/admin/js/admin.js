/**
 * HP Cookie Consent - Admin JavaScript
 *
 * @package HPCookieConsent
 */
(function ($) {
    'use strict';

    $(function () {
        // Color Picker
        $('.hpcc-color-picker').wpColorPicker();

        // Tabs
        var $tabs = $('.hpcc-tabs .nav-tab');
        var $contents = $('.hpcc-tab-content');

        $tabs.on('click', function (e) {
            e.preventDefault();
            var target = $(this).attr('href');

            $tabs.removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $contents.removeClass('active');
            $(target).addClass('active');
        });

        // Activate tab from URL hash
        var hash = window.location.hash;
        if (hash && $(hash).length) {
            $tabs.removeClass('nav-tab-active');
            $tabs.filter('[href="' + hash + '"]').addClass('nav-tab-active');
            $contents.removeClass('active');
            $(hash).addClass('active');
        }
    });
})(jQuery);
