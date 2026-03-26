/* WP-OData Suite Admin Scripts */
(function ($) {
    'use strict';

    // Auto-dismiss success notices after 4 seconds.
    $(document).ready(function () {
        setTimeout(function () {
            $('.notice-success.is-dismissible').fadeOut(400);
        }, 4000);
    });

}(jQuery));
