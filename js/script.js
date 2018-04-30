// see http://solislab.com/blog/5-tips-for-using-ajax-in-wordpress/
// and https://pippinsplugins.com/using-ajax-your-plugin-wordpress-admin/

jQuery(document).ready(function() {
    console.log('blog-feed-finder', bff_data);

    var $ = jQuery;

    $('#bff-feedback').removeClass('success', 'failure');
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
        // disable the submit button until it returns.
        $('#bff-submit').attr('disabled', true);
        $('#bff-feedback').removeClass('success', 'failure');
        $('#bff-feedback').html('Processing...');
        console.log('url: ', bff_data.ajaxurl);
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: bff_data.ajaxurl,
            data: {
                'action': 'bff_submit',
                'nonce_submit' : bff_data.nonce_submit,
                'url' : $.trim($('#bff-url').val()),
            },
            success: function(data) {
                var msg = '';
                console.log('Success: data: ', data);
                if (data.hasOwnProperty('success')) {
                    // strip links out
                    //msg = data.message.replace(/<a[^>]*>[^<]*<\/a>/g, '');
                    msg = data.message;
                    console.log('Success msg', msg);
                    $('#bff-submit').attr('disabled', false);
                    $('#bff-feedback').addClass('success');
                    $('#bff-feedback').removeClass('failure');
                    $('#bff-feedback').text(msg);
                    if (data.hasOwnProperty('redirect')) {
                        $('#bff-url').val(data.redirect);
                    }
                } else if (data.hasOwnProperty('error')) {
                    msg = data.message;
                    console.log('Error msg', msg);
                    console.log('message:', msg);
                    $('#bff-submit').attr('disabled', false);
                    $('#bff-feedback').text(msg);
                    $('#bff-feedback').addClass('failure');
                    $('#bff-feedback').removeClass('success');
                    if (data.hasOwnProperty('redirect')) {
                        $('#bff-url').val(data.redirect);
                    }
                }
                return true;
            },
            failure: function(data) {
                console.log('Failure: data: ', data);
                $('#bff-feedback').removeClass('failure', 'success');
                $('#bff-feedback').text(msg);
            }
        });
        // if nothing else returns this first, there was a problem...
        console.log('trickled through to false...');
        return false;
    });
});
