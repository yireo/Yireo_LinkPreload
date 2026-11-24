define([
    'jquery',
    'jquery/jquery.cookie'
], function ($) {
    'use strict';

    $.cookie('linkpreload', 1, {
        path: '/',
        secure: window.location.protocol === 'https:',
        samesite: 'Lax'
    });
});
