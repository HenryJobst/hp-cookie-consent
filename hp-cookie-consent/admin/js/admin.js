/**
 * HP Cookie Consent - Admin JavaScript
 *
 * @package HPCookieConsent
 */
(function ($) {
    'use strict';

    $(function () {
        // Color Picker with live preview
        function updatePreview() {
            var bg = $('#hpcc_bg_color').val() || '#ffffff';
            var text = $('#hpcc_text_color').val() || '#1f2937';
            var primary = $('#hpcc_primary_color').val() || '#2563eb';

            var $preview = $('#hpcc-admin-preview');
            if (!$preview.length) return;
            $preview.css({ background: bg, color: text });
            $('#hpcc-preview-accept').css({ background: primary });
            $('#hpcc-preview-reject').css({ color: text, 'border-color': text });
        }

        $('.hpcc-color-picker').wpColorPicker({
            change: function () {
                setTimeout(updatePreview, 50);
            }
        });

        updatePreview();

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
