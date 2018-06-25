# blog-feed-finder
A WordPress plug-in that helps a user find a valid URL for their personal blog feed.

## What it does

The goal of the Blog Feed Finder (BFF) is to provide non-technical users an intuitive interface which guides them through the process of finding the appropriate web address (URI) for their blog's feed, providing them with short non-technical insights to help their learning, complemented with more technically accurate elaboration in user-selectable info popups. The system is intended for use by both desktop/laptop and mobile computer users.

The BFF plug-in is designed to work on a WP in Multi-Site configuration. In our case, we use each sub-site (in subdir mode) as a separate "Course" in which learners (either anonymous or logged in users) participate. Once the user has found a valid blog feed, if they are authenticated, this plugin allows them to associate that feed URL (and its feed type, e.g. RSS, Atom, JSON) with each Course (subsite) for which they are registered.

We ([the OERu](https://oeru.org)) also operates the [WEnotes service](https://tech.oeru.org/wikieducator-notes-oerus-course-feed-aggregation-and-messaging-system Overview of what WikiEducator notes are, and why), [WEnotes WordPress Plug-in](https://github.com/oeru/wenotes) and a [library of scanner scripts](https://bitbucket.org/wikieducator/wenotes-tools) ([we also have a full-Docker-based deployment stack](https://github.com/oeru/wenotes-docker)), which scans various social media (mostly Free and Open Source federated media in preference to the proprietary social media monocultures, e.g. Mastodon, Discourse, Hypothes.is, etc.) to aggregate messages which are tagged with our Course Codes. In the case of registered blog feeds, we scan them for tagged posts and then reference them in a form that can be incorporated into our per-Course WEnotes feeds.

## How it works

The plugin creates a default page (path /blog-feed-finder/) on the site when enabled, which contains a brief explanation of the BFF's use, and a simple text entry field and "submit" button. The user is instructed to enter a web address for their blog (by visiting it in another browser tab and copy-and-pasting the web address into the text field). Upon submit, the BFF visits the site, ensuring it is

* a valid domain construct (from a text perspective), adding basics like a valid Scheme if none is included,
* a valid domain that DNS can find,
* that the server at the DNS IP address responds to that domain (noting any redirects that occur along the way),
* checks to make sure it's not one of a number of commonly entered incorrect domains (e.g. google.com, facebook.com, wikieducator.org, and the Course site itself, course.oeru.org), which it flags to the user, or
* if a valid non-erroneous website responds, the BFF looks for header references to "alternate" links for the site (usually feeds) and tests them, or, if none are found,
* it looks for evidence of a feed in a number of "usual places", e.g. for an /rss or /feed or /rss.xml, etc. by actually checking if those variants of the original URL respond and contain content consistent with those feed types, and
* if it finds a valid feed (it determines the type automatically), it alerts the user that a valid feed was found, and its type.

If the user is authenticated, further capabilities are available:

* If multiple feeds are found, the BFF tries to identify the most likely candidate, and eliminates any that clearly aren't what's being sought (e.g. the common "Comment" feed on many WordPress blogs), allowing the user to choose among any remaining candidates,
* once a single feed is selected, the user is then presented with a list of the Courses (subsites) for which they are registered, including a listing of any existing feed specifications. The user can then opt to set the selected feed to that for any of the courses.

## Technical details

The BFF makes use of default WordPress jQuery library dependencies and has a fully AJAX-based interface.

## Testing and Feedback

We're keen for as many people as possible to use this code, make suggestion to improve it (or improve it unilaterally :) - it's fully FOSS), or perhaps learn a few tricks from it. You can provide feedback through the Git foundry support mechanisms (e.g. issues) or via our [Tech Blog](https://tech.oeru.org). 
