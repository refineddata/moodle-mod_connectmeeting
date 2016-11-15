$(document).ready(function () {
    get_mod_connectmeeting_filter();

    mod_connectmeeting_add_tooltip();

    $('body').on("click", "#connectmeeting-update-from-adobe", function (event) {
        event.preventDefault();
        var block = $(this);
        var connectmeeting_id = block.data('connectmeetingid');
        $('#connectmeetingcontent' + connectmeeting_id).html('');
        $('#connectmeetingcontent' + connectmeeting_id).addClass('rt-loading-image');
        $.ajax({
            url: window.wwwroot + "/mod/connectmeeting/ajax/connectmeeting_callback.php",
            dataType: "html",
            data: {
                connectmeeting_id: connectmeeting_id,
                update_from_adobe: 1,
            }
        }).done(function (data) {
            $('#connectmeetingcontent' + connectmeeting_id).removeClass('rt-loading-image');
            $('#connectmeetingcontent' + connectmeeting_id).html(data);
            mod_connectmeeting_add_tooltip();
        });
    });
});

function mod_connectmeeting_add_tooltip() {
    if (typeof($.uitooltip) != 'undefined') {
        $('.mod_connectmeeting_tooltip').uitooltip({
            show: null, // show immediately
            items: '.mod_connectmeeting_tooltip',
            content: function () {
                return $(this).next('.mod_connectmeeting_popup').html();
            },
            position: {my: "left top", at: "right top", collision: "flipfit"},
            hide: {
                effect: "" // fadeOut
            },
            open: function (event, ui) {
                ui.tooltip.animate({left: ui.tooltip.position().left + 10}, "fast");
            },
            close: function (event, ui) {
                ui.tooltip.hover(
                    function () {
                        $(this).stop(true).fadeTo(400, 1);
                    },
                    function () {
                        $(this).fadeOut("400", function () {
                            $(this).remove();
                        })
                    }
                );
            }
        });
    }
}

function add_mod_connectmeeting_filter_alert(block, type, msg) {
    block.html(
        '<div class="fitem" id="fgroup_id_urlgrp_alert">' +
        '<div class="felement fstatic alert alert-' + type + ' alert-dismissible">' +
        '<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>' +
        msg +
        '</div>' +
        '</div>'
    );
}

function get_mod_connectmeeting_filter() {
    $('.connectmeeting_display_block').each(function (index) {
        var block = $(this);
        var acurl = block.data('acurl');
        var sco = block.data('sco');
        var courseid = block.data('courseid');
        block.removeClass('connectmeeting_display_block').addClass('connect_display_block_done');
        $.ajax({
            url: window.wwwroot + "/mod/connectmeeting/ajax/connectmeeting_callback.php",
            dataType: "html",
            data: {
                acurl: acurl,
                sco: sco,
                courseid: courseid,
                options: encodeURIComponent(block.data('options')),
                frommymeetings: block.data('frommymeetings'),
                frommyrecordings: block.data('frommyrecordings')
            }
        }).done(function (data) {
            block.html(data);
        }).fail(function (jqXHR, textStatus, errorThrown) {
            add_mod_connectmeeting_filter_alert(block, 'danger', jqXHR.status + " " + jqXHR.statusText);
        });
    });
}
