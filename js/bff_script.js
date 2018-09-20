/* created by Dave Lane, dave@oerfoundation.org, https://oeru.org */
var BFF_DEBUG = true; // set to false to disable debugging
function LOG() { if (BFF_DEBUG) { console.log.apply(this, arguments); }}
LOG('BFF DEBUG =', BFF_DEBUG); // only prints if DEBUG = true

/* jquery-independent functions */
function addslashes(string) {
    return string.replace(/\\/g, '\\\\').
        replace(/\u0008/g, '\\b').
        replace(/\t/g, '\\t').
        replace(/\n/g, '\\n').
        replace(/\f/g, '\\f').
        replace(/\r/g, '\\r').
        replace(/'/g, '\\\'').
        replace(/"/g, '\\"');
}

function get_url(data) {
    if (data.hasOwnProperty('redirect') && data.redirect != "") {
        LOG('Redirect... ', data.redirect);
        url = data.redirect;
    } else {
        LOG('Original... ', data.orig_url)
        url = data.orig_url;
    }
    return url;
}

function compile_feeds(feeds, types, classes, authenticated) {
    cnt = feeds.length;
    msg = '';
    msg += '<p class="instruction">We have identified the following supported feeds. ';
    if (authenticated) {
        msg += 'Please select the one you think the best choice:';
    }
    msg += '</p>';
    msg += '<ul>';
    feeds.forEach(function(feed, num) {
        content_type = types[feed.type];
        content_class = classes[feed.type];
        msg += '<li>';
        msg += '<a href="' + feed.url + '">' + feed.url + '</a>';
        if (feed.title != '') {
            msg += ', entitled "' + feed.title +'" ';
        }
        //msg += ' (' + content_type + ' format)';
        msg += '<span class="bff-feed '+content_class+'" title="'+content_type+' Format"></span>';
        if (authenticated) {
            LOG('user is authenticated');
            msg += ' <span id="bff-select-' + num + '" class="bff-select button">Select Feed</span>';
        } else {
            LOG('user is NOT authenticated');
        }
        msg += '</li>';
    });
    msg += '</ul>';
    return msg;
}

// if we have only one
function selected_feed(feeds, types, classes) {
    cnt = feeds.length;
    msg = '';
    if (feeds.selected) {
        feed = feeds[feeds.selected];
        content_type = types[feed.type];
        content_classes = classes[feed.type];
        msg += '<p class="instruction"><span class="bff-feed '+content_classes+'" title="' + content_type + ' feed"></span>';
        end = ' selected</p>';
    } else if (cnt == 1) {
        feed = feeds[0];
        content_type = types[feed.type];
        content_classes = classes[feed.type];
        msg += '<p class="instruction"><span class="bff-feed '+content_classes+'" title="' + content_type + ' feed"></span> We found a feed - ';
        end = '</p>';
    }
    msg += '<a href="' + feed.url +'">' + feed.url + '</a>';
    if (feed.title != '') {
        msg += ', entitled "' + feed.title +'"';
    }
    msg += end;
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
                    '" class="bff-set button" hidden>Replace</span>';
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
        msg =  '<p class="instruction">You can assign this blog feed to any of your OERu courses, or use it to replace any existing assignments:</p>' + msg;
    } else {
        msg =  '<p>All of your blog feeds are already set to this feed! There\'s nothing you need to do. Well done!</p>' + msg;
    }
    return msg;
}

// get the course tag, given that the course tag could contain a '-'
// the course tag is the set of terms between the 2nd and last '-'
function get_course_tag(str) {
    terms = str.split('-');
    tag = terms.slice(2,terms.length-1).join('-');
    LOG('returning tag '+tag);
    return tag;
}

// get the course id, which is the final element in a string separated by '-'
// the course is after the last '-'
function get_course_id(str) {
    terms = str.split('-');
    //LOG('terms ('+terms.length+') = '+JSON.stringify(terms));
    id = terms[terms.length-1];
    LOG('returning id '+id);
    return id;
}

// jQuery selectors and related functions in that context
jQuery(document).ready(function() {
    var $ = jQuery;
    var feeds, feed_types, courses, authenticated;
    LOG('blog-feed-finder', bff_data);

    $('#bff-feedback').removeClass('success failure');
    $('#bff-feedback').text('Ready...');

    // handle (re)load of the page
    $(window).on('load', function() {
        LOG('in load');
        $('#bff-submit').attr('disabled', false);
        $('#bff-feedback').html('Ready...');
        $('#bff-course-list').attr('hidden', true);
    });

    /*
     * initialise jquery tooltips with custom functionality
     * credit for this: https://gist.github.com/csasbach/867744
     */
     var tooltip = '.bff-tooltip';  // trigger for popup content
     var popup = 'bff-popup';  // the actual popup class

     function enable_tooltips() {
         // popup display offsets
         var voffset = 6;
         var hoffset = 6;
         // popup pause and fade times in milliseconds
         var ptime = 3000;
         var ftime = 1000;

         LOG('enable tooltips');
         $(tooltip).each(function() {
            // grab the content from the title attribute and remove the
            // title attrib to avoid normal popup-on-hover behaviour
            $(this).data('title', $(this).attr('title'));
            LOG('sorted tooltip with text '+$(this).data('title'));
            $(this).removeAttr('title');
            // show popup on mouseover/hover
            $(tooltip).mouseover(function() {
                LOG('mouseover');
                // remove any currently displaying popups
                $(this).next('.'+popup).remove();
                // create the popup
                text = $(this).data('title');
                LOG('new text: '+text);
                $(this).after('<div class="'+popup+'"><p>'+text+'</p></div>');
                // manage positioning of the popups (voffset pixels above and hoffset left of tooltip trigger)
                var left = $(this).position().left + $(this).width()+hoffset;
    		    var top = $(this).position().top-voffset;
        		$(this).next().css('left',left);
                $(this).next().css('top',top);
                enable_popup();
            });
            // manage clicks, e.g. from touch devices
            $(tooltip).click(function() {
                LOG('click');
                $(this).mouseover();
                // after a ptime pause, then fade out over ftime second
                $(this).next().animate({opacity: 0.9}, {duration: ptime, complete: function() {
                    $(this).fadeOut(ftime);
                }});
            });
            // remove popup on mouseout
            $(tooltip).mouseout(function() {
                LOG('mouseout');
    			$(this).next('.'+popup).remove();
            });

        });
    }
    function enable_popup() {
        LOG('enabling popup');
        $('.'+popup).each(function() {
            // if the user explicitly clicks on a popup
            $('.'+popup).click(function() {
                LOG('click on popup');
                $(this).remove();
            });
        });
    }
    /*
     * end tooltip stuff
     */

    // jquery functions
    function compile_message(msgs, types) {
        msg='<div id="bff-responses" class="bff-responses">';
        num = 0;
        msgs.forEach(function(entry) {
            LOG('entry = '+JSON.stringify(entry));
            msg += '<p class="bff-response ' + entry.type + '">' +
                types[entry.type] + ' ' + entry.message;
            if (entry.detail != '') {
                LOG('entry detail = '+entry.detail);
                // escape single quotes
                escaped = entry.detail.replace(/'/g, '&#39;');
                LOG('escaped = '+escaped);
                id = 'popupInfo-'+num;
                msg += '<a href="#'+id+'" class="bff-tooltip"  title=\''+escaped+'\'></a>';
            } else {
                msg += '</p>';
            }
            num += 1;
        });
        msg += "</div>";
        return msg;
    }

    // because this uses jquery selectors, it's in here
    function replace_url(data) {
        url = get_url(data);
        $('#bff-url').val(url);
    }

    // turn on the course list and let user assign a feed to Courses
    function enable_courses(authenticated) {
        LOG('enabling courses...');
        $('#bff-feed-list').html(selected_feed(feeds,feed_types,feed_classes));
        $('#bff-course-list').attr('hidden', false);
        if (authenticated) {
            LOG('user logged in.');
            $('#bff-course-list').html(compile_courses(courses, feeds[feeds.selected].url));
        } else {
            $('#bff-course-list').hide();
            LOG('user NOT logged in.');
        }
    }

    // show that a course has been updated
    function update_course_feed(tag, id, url) {
        LOG('course '+tag+' updated to '+url);
        $('#bff-original-'+tag).html('updated blog feed to <a href="'+url+'">'+url+'</a>&nbsp;&nbsp;<span class="bff-success">Success</span>');
        $('#bff-original-'+tag).addClass('updated');
        selector = '#bff-set-'+tag+'-'+id;
        LOG('turning off button: "'+selector+'"');
        $(selector).hide();
    }

    // set this up to submit on 'enter'
    $('input').keypress( function (e) {
        c = e.which ? e.which : e.keyCode;
        LOG('input: ' + c);
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
        //LOG('url: ', bff_data.ajaxurl);
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
                LOG('Success: data: ', data);
                if (data.hasOwnProperty('success')) {
                    // strip links out
                    //msg = data.message.replace(/<a[^>]*>[^<]*<\/a>/g, '');
                    msgs = data.messages;
                    LOG('Success msgs', msgs);
                    $('#bff-submit').attr('disabled', false);
                    $('#bff-feedback').addClass('success');
                    $('#bff-feedback').removeClass('failure');
                    // create the new messages (and any relevant popups)
                    $('#bff-feedback').html(compile_message(msgs, types));
                    // initialise tooltips
                    enable_tooltips();
                    // replace the text in the input field if it's been modified.
                    replace_url(data);
                    if (data.hasOwnProperty('feeds')) {
                        // assign these to global variables to make them
                        // visible between jQuery events...
                        feeds = data.feeds;
                        feed_types = data.feed_types;
                        feed_classes = data.feed_classes;
                        courses = data.courses;
                        authenticated = data.authenticated;
                        //
                        $('#bff-feed-list').attr('hidden', false);
                        $('#bff-feeds').attr('hidden', false);
                        // if we've only got one feed, or if one's been selected...
                        if (feeds.length == 1) {
                            LOG('only one feed!');
                            feeds.selected = 0;
                            LOG('feeds array: '+feeds);
                        }
                        if (feeds.hasOwnProperty('selected')) {
                            enable_courses(authenticated);
                        } else if (feeds.length == 0) {
                            $('#bff-feed-list').html('<p>No feeds found.</p>');
                        } else {
                            $('#bff-feed-list').html(compile_feeds(feeds, feed_types, feed_classes, authenticated));
                            // add this in case it was removed by a previous run.
                            $('#bff-feed-list').addClass('bff-alert-box');
                            LOG('need to select a feed: ', feeds);
                        }
                    }
                }
                LOG('returning true');
                return true;
            },
            failure: function(data) {
                LOG('Failure: data: ', data);
                $('#bff-feedback').removeClass('failure success');
                $('#bff-feedback').text(msg);
            }
        });
        // if nothing else returns this first, there was a problem...
        LOG('completed submit... returning false');
        return false;
    });

    // handle selection of a feed
    $('#bff-feed-list').on('click','.bff-select', function() {
        button_id = $(this).attr('id');
        LOG('the value of this selected button id is '+button_id);
        // pick out the number
        feeds.selected = button_id.split("-")[2];
        LOG('the selected feed is '+feeds.selected);
        // hide the whole list
        $('#bff-feed-list ul').attr('hidden', true);
        // set the selected feed
        LOG('completed select');
        // remove the "bff-alert-box" class
        $('#bff-feed-list').removeClass('bff-alert-box');
        enable_courses(authenticated);
        return true;
    });

    $('#bff-course-list').on('click','.bff-set', function() {
        // make sure user doesn't submit another URL now...
        $('#bff-submit').attr('disabled', false);
        // get the button id, which contains the
        button_id = $(this).attr('id');
        LOG('the value of this button id is '+button_id);
        course_tag = get_course_tag(button_id);
        course_id = get_course_id(button_id);
        LOG('the course: '+course_tag+'('+course_id+')');
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: bff_data.ajaxurl,
            data: {
                'action': 'bff_set',
                'course_id' : course_id,
                'course_tag' : course_tag,
                'nonce_set' : bff_data.nonce_set,
                'feed' : feeds[feeds.selected]
            },
            success: function(data) {
                if (data.hasOwnProperty('success')) {
                    LOG('button_id = '+ button_id);
                    tag = get_course_tag(button_id);
                    id = get_course_id(button_id);
                    LOG('Success - '+tag+'('+id+')');
                    update_course_feed(tag, id, feed.url);
                    $('.bff-success').fadeOut(4000);
                }
                return true;
            },
            failure: function(data) {
                LOG('failure: data: ', data);
            }
        });
        LOG('completed set');
        return false;
    });

    // the end of the jQuery loop...
});
