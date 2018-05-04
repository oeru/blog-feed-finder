/* created by Dave Lane, dave@oerfoundation.org, https://oeru.org */

function get_url(data) {
    if (data.hasOwnProperty('redirect') && data.redirect != "") {
        console.log('Redirect... ', data.redirect);
        url = data.redirect;
    } else {
        console.log('Original... ', data.orig_url)
        url = data.orig_url;
    }
    return url;
}

function compile_message(msgs, types) {
    msg='<div class="bff-responses">';
    msgs.forEach(function(entry) {
        msg += '<p class="bff-response ' + entry.type + '">' +
            types[entry.type] + ': ' + entry.message + '</p>';
    });
    msg += "</div>";
    return msg;
}

jQuery(document).ready(function() {
    console.log('blog-feed-finder', bff_data);

    // because this uses jquery selectors, it's in here
    function replace_url(data) {
        url = get_url(data);
        $('#bff-url').val(url);
    }

    var $ = jQuery;

    $('#bff-feedback').removeClass('success failure');
    $('#bff-feedback').text('Ready...');

    // handle (re)load of the page
    $(window).on('load', function() {
        console.log('in load');
        $('#bff-submit').attr('disabled', false);
        $('#bff-feedback').html('Ready...');
    });

    // set this up to submit on 'enter'
    $('input').keypress( function (e) {
        c = e.which ? e.which : e.keyCode;
        console.log('input: ' + c);
        if (c == 13) {
            $('#bff-submit').click();
            return false;
        }
    });

    // handle the submit button being pushed
    $('#bff-submit').click(function() {
        $('#bff-submit').attr('disabled', true);
        $('#bff-feedback').removeClass('success failure');
        $('#bff-feedback').html('Processing...');
        //console.log('url: ', bff_data.ajaxurl);
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: bff_data.ajaxurl,
            data: {
                'action': 'bff_submit',
                'nonce_submit' : bff_data.nonce_submit,
                'url' : $('#bff-url').val(),
            },
            success: function(data) {
                var msgs = '';
                var types = data.types
                console.log('Success: data: ', data);
                if (data.hasOwnProperty('success')) {
                    // strip links out
                    //msg = data.message.replace(/<a[^>]*>[^<]*<\/a>/g, '');
                    msgs = data.messages;
                    console.log('Success msgs', msgs);
                    $('#bff-submit').attr('disabled', false);
                    $('#bff-feedback').addClass('success');
                    $('#bff-feedback').removeClass('failure');
                    $('#bff-feedback').html(compile_message(msgs, types));
                    replace_url(data);
                    if (data.hasOwnProperty('feeds')) {
                        $('#bff-feeds').attr('hidden', false);
                    }
                }
                return true;
            },
            failure: function(data) {
                console.log('Failure: data: ', data);
                $('#bff-feedback').removeClass('failure success');
                $('#bff-feedback').text(msg);
            }
        });
        // if nothing else returns this first, there was a problem...
        console.log('trickled through to false...');
        return false;
    });
});
