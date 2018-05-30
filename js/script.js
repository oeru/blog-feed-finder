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

function compile_feeds(feeds, types) {
    cnt = feeds.length;
    msg = '';
    msg += '<p>We have identified the following feeds. Please select the one you think the best choice:</p>';
    msg += '<ul>';
    feeds.forEach(function(feed, num) {
        content_type = types[feed.type];
        msg += '<li>';
        msg += '<a href="' + feed.url + '">' + feed.url + '</a>';
        if (feed.title != '') {
            msg += ', entitled "' + feed.title +'" ';
        }
        msg += ' (' + content_type + ' format)';
        msg += ' <span id="bff-select-' + num + '" class="bff-select button">Select Feed</span>';
        msg += '</li>';
    });
    msg += '</ul>';
    return msg;
}

// if we have only one
function selected_feed(feeds, types) {
    cnt = feeds.length;
    msg = '';
    if (feeds.selected) {
        feed = feeds[feeds.selected];
        content_type = types[feed.type];
        msg += '<p>You have selected a valid ' + content_type + ' feed:</p>';
    } else if (cnt == 1) {
        feed = feeds[0];
        content_type = types[feed.type];
        msg += '<p>We have identified a valid ' + content_type + ' feed:</p>';
    }
    msg += '<p class="feed selected">';
    msg += '<a href="' + feed.url +'">' + feed.url + '</a>';
    if (feed.title != '') {
        msg += ', entitled "' + feed.title +'""';
    }
    msg += '</p>';
    return msg;
}

// create a set of courses for which to assign a new feed url...
function compile_courses(courses, newfeed) {
    msg = '<ul>';
    settable = false;
    courses.forEach(function(site) {
        msg += '<li class="'+site.tag+'"><a href="' + site.path +'">' + site.name + '</a> (' + site.tag +')';
        if (site.feed) {
            if (site.feed == newfeed) {
                msg += ' - blog feed already set to <a href="' + site.feed + '">'+newfeed+'</a>';
            } else {
                msg += ' - <span id="bff-original-'+ site.tag + '">existing blog feed: <a href="' + site.feed + '">' + site.feed + '</a></span>';
                msg += ' <span id="bff-set-' + site.tag + '-' + site.id +
                    '" class="bff-set button" hidden>Update</span>';
                settable = true;
            }
        } else {
            msg += ' - <span id="bff-original-'+ site.tag + '">no url specified</span>';
            msg += ' <span id="bff-set-' + site.tag + '-' + site.id +
                '" class="bff-set button" hidden>Assign</span>';
            settable = true;
        }
        msg += '</li>';
    });
    msg += '</ul>';
    if (settable) {
        msg =  '<p>You can assign this blog feed to any of your course blog feeds, or use it to update (replace) any existing assignments:</p>' + msg;
    } else {
        msg =  '<p>All of your blog feeds are already set to this feed! There\'s nothing you need to do. Well done!</p>' + msg;
    }
    return msg;
}


// jQuery seletors and related functions in that context
jQuery(document).ready(function() {
    var $ = jQuery;
    var feeds, feed_types, courses;
    console.log('blog-feed-finder', bff_data);

    // because this uses jquery selectors, it's in here
    function replace_url(data) {
        url = get_url(data);
        $('#bff-url').val(url);
    }

    // turn on the course list and let user assign a feed to Courses
    function enable_courses() {
        console.log('enabling courses...');
        $('#bff-feed-list').html(selected_feed(feeds,feed_types));
        $('#bff-course-list').attr('hidden', false);
        $('#bff-course-list').html(compile_courses(courses, feeds[feeds.selected].url));
    }

    // show that a course has been updated
    function update_course_feed(tag, id, url) {
        console.log('course '+tag+' updated to '+url);
        $('#bff-original-'+tag).html('updated blog feed to <a href="'+url+'">'+url+'</a>!');
        $('#bff-original-'+tag).addClass('updated');
        selector = '#bff-set-'+tag+'-'+id;
        console.log('turning off button: "'+selector+'"');
        $(selector).hide();
    }

    $('#bff-feedback').removeClass('success failure');
    $('#bff-feedback').text('Ready...');

    // handle (re)load of the page
    $(window).on('load', function() {
        console.log('in load');
        $('#bff-submit').attr('disabled', false);
        $('#bff-feedback').html('Ready...');
        $('#bff-course-list').attr('hidden', true);
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
        // just in case it was previously shown, this needs to be
        // re-hidden or subsequent requests
        $('#bff-course-list').attr('hidden', true);
        $('#bff-submit').attr('disabled', true);
        $('#bff-feeds').attr('hidden', true);
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
                        // assign these to global variables to make them
                        // visible between jQuery events...
                        feeds = data.feeds;
                        feed_types = data.feed_types;
                        courses = data.courses;
                        //
                        $('#bff-feed-list').attr('hidden', false);
                        $('#bff-feeds').attr('hidden', false);
                        // if we've only got one feed, or if one's been selected...
                        if (feeds.length == 1) {
                            console.log('only one feed!');
                            feeds.selected = 0;
                            console.log('feeds array: '+feeds);
                        }
                        if (feeds.hasOwnProperty('selected')) {
                            enable_courses();
                        } else if (feeds.length == 0) {
                            $('#bff-feed-list').html('<p>No feeds found.</p>');
                        } else {
                            $('#bff-feed-list').html(compile_feeds(feeds,feed_types));
                            console.log('need to select a feed: ', feeds);
                        }
                    }
                }
                console.log('returning true');
                return true;
            },
            failure: function(data) {
                console.log('Failure: data: ', data);
                $('#bff-feedback').removeClass('failure success');
                $('#bff-feedback').text(msg);
            }
        });
        // if nothing else returns this first, there was a problem...
        console.log('completed submit... returning false');
        return false;
    });

    // handle selection of a feed
    $('#bff-feed-list').on('click','.bff-select', function() {
        button_id = $(this).attr('id');
        console.log('the value of this selected button id is '+button_id);
        // pick out the number
        feeds.selected = button_id.split("-")[2];
        console.log('the selected feed is '+feeds.selected);
        // hide the whole list
        $('#bff-feed-list ul').attr('hidden', true);
        // set the selected feed
        console.log('completed select');
        enable_courses();
        return true;
    });

    $('#bff-course-list').on('click','.bff-set', function() {
        // make sure user doesn't submit another URL now...
        $('#bff-submit').attr('disabled', false);
        // get the button id, which contains the
        button_id = $(this).attr('id');
        console.log('the value of this button id is '+button_id);
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: bff_data.ajaxurl,
            data: {
                'action': 'bff_set',
                'nonce_set' : bff_data.nonce_set,
                'course_tag' : button_id.split("-")[2],
                'course_id' : button_id.split("-")[3],
                'feed' : feeds[feeds.selected]
            },
            success: function(data) {
                if (data.hasOwnProperty('success')) {
                    tag = button_id.split("-")[2];
                    id = button_id.split("-")[3];
                    console.log('Success - '+tag);
                    update_course_feed(tag, id, feed.url);
                }
                return true;
            },
            failure: function(data) {
                console.log('failure: data: ', data);
            }
        });
        console.log('completed set');
        return false;
    });

    // the end of the jQuery loop...
});
