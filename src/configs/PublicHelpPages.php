<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2015  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * Default Public Wiki Pages
 *
 * This file should be generated using ExportPublicHelpDb.php
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\configs;

/**
 * Public wiki pages
 * @var array
 */
$public_pages = [];
$public_pages["en-US"]["404"] = <<< 'EOD'
title=Page Not Found
description=The page you requested cannot be found on our server
END_HEAD_VARS
==The page you requested cannot be found.==
EOD;
$public_pages["en-US"]["409"] = <<< 'EOD'
title=Conflict

description=Your request would result in an edit conflict.
END_HEAD_VARS
==Your request would result in an edit conflict, so will not be processed.==
EOD;
$public_pages["en-US"]["Main"] = <<< 'EOD'
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS


EOD;
$public_pages["en-US"]["Syntax"] = <<< 'EOD'
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=Yioop Wiki Syntax

author=Chris Pollett

robots=

description=Describes the markup used by Yioop&#039;

page_header=

page_footer=

END_HEAD_VARS=Yioop Wiki Syntax=

Wiki syntax is a lightweight way to markup a text document so that
it can be formatted and drawn nicely by Yioop.
This page briefly describes the wiki syntax supported by Yioop.

==Headings==
In wiki syntax headings of documents and sections are written as follows:

&lt;nowiki&gt;
=Level1=
==Level2==
===Level3===
====Level4====
=====Level5=====
======Level6======
&lt;/nowiki&gt;

and would look like:

=Level1=
==Level2==
===Level3===
====Level4====
=====Level5=====
======Level6======

==Paragraphs==
In Yioop two new lines indicates a new paragraph. You can control
the indent of a paragraph by putting colons followed by a space in front of it:

&lt;nowiki&gt;
: some indent

:: a little more

::: even more

:::: that&#039;s sorta crazy
&lt;/nowiki&gt;

which looks like:

: some indent

:: a little more

::: even more

:::: that&#039;s sorta crazy

==Horizontal Rule==
Sometimes it is convenient to separate paragraphs or sections with a horizontal
rule. This can be done by placing four hyphens on a line by themselves:
&lt;nowiki&gt;
----
&lt;/nowiki&gt;
This results in a line that looks like:
----

==Text Formatting Within Paragraphs==
Within a paragraph it is often convenient to make some text bold, italics,
underlined, etc. Below is a quick summary of how to do this:
===Wiki Markup===
{|
|&lt;nowiki&gt;&#039;&#039;italic&#039;&#039;&lt;/nowiki&gt;|&#039;&#039;italic&#039;&#039;
|-
|&lt;nowiki&gt;&#039;&#039;&#039;bold&#039;&#039;&#039;&lt;/nowiki&gt;|&#039;&#039;&#039;bold&#039;&#039;&#039;
|-
|&lt;nowiki&gt;&#039;&#039;&#039;&#039;&#039;bold and italic&#039;&#039;&#039;&#039;&#039;&lt;/nowiki&gt;|&#039;&#039;&#039;&#039;&#039;bold and italic&#039;&#039;&#039;&#039;&#039;
|}

===HTML Tags===
Yioop also supports several html tags such as:
{|
|&lt;nowiki&gt;&lt;del&gt;delete&lt;/del&gt;&lt;/nowiki&gt;|&lt;del&gt;delete&lt;/del&gt;
|-
|&lt;nowiki&gt;&lt;ins&gt;insert&lt;/ins&gt;&lt;/nowiki&gt;|&lt;ins&gt;insert&lt;/ins&gt;
|-
|&lt;nowiki&gt;&lt;s&gt;strike through&lt;/s&gt; or
&lt;strike&gt;strike through&lt;/strike&gt; &lt;/nowiki&gt;|&lt;s&gt;strike through&lt;/s&gt;
|-
|&lt;nowiki&gt;&lt;sup&gt;superscript&lt;/sup&gt; and
&lt;sub&gt;subscript&lt;/sub&gt;&lt;/nowiki&gt;|&lt;sup&gt;superscript&lt;/sup&gt; and
&lt;sub&gt;subscript&lt;/sub&gt;
|-
|&lt;nowiki&gt;&lt;tt&gt;typewriter&lt;/tt&gt;&lt;/nowiki&gt;|&lt;tt&gt;typewriter&lt;/tt&gt;
|-
|&lt;nowiki&gt;&lt;u&gt;underline&lt;/u&gt;&lt;/nowiki&gt;|&lt;u&gt;underline&lt;/u&gt;
|}

===Spacing within Paragraphs===
The HTML entity
&lt;nowiki&gt;&amp;nbsp;&lt;/nowiki&gt;
can be used to create a non-breaking space. The tag
&lt;nowiki&gt;&lt;br&gt;&lt;/nowiki&gt;
can be used to produce a line break.

==Preformatted Text and Unformatted Text==
You can force text to be formatted as you typed it rather
than using the layout mechanism of the browser using the
&lt;nowiki&gt;&lt;pre&gt;preformatted text tag.&lt;/pre&gt;&lt;/nowiki&gt;
Alternatively, a sequence of lines all beginning with a
space character will also be treated as preformatted.

Wiki markup within pre tags is still parsed by Yioop.
If you would like to add text that is not parsed, enclosed
it in `&lt;`nowiki&gt; `&lt;`/nowiki&gt; tags.

==Styling Text Paragraphs==
Yioop wiki syntax offers a number of templates for
control the styles, and alignment of text for
a paragraph or group of paragraphs:&lt;br /&gt;
`{{`left| some text`}}`,&lt;br /&gt; `{{`right| some text`}}`,&lt;br /&gt;
and&lt;br /&gt;
`{{`center| some text`}}`&lt;br /&gt; can be used to left-justify,
right-justify, and center a block of text. For example,
the last command, would produce:
{{center|
some text
}}
If you know cascading style sheets (CSS), you can set
a class or id selector for a block of text using:&lt;br /&gt;
`{{`class=&quot;my-class-selector&quot; some text`}}`&lt;br /&gt;and&lt;br /&gt;
`{{`id=&quot;my-id-selector&quot; some text`}}`.&lt;br /&gt;
You can also apply inline styles to a block of text
using the syntax:&lt;br /&gt;
`{{`style=&quot;inline styles&quot; some text`}}`.&lt;br /&gt;
For example, `{{`style=&quot;color:red&quot; some text`}}` looks
like {{style=&quot;color:red&quot; some text}}.

==Lists==
The Yioop Wiki Syntax supported of ways of listing items:
bulleted/unordered list, numbered/ordered lists, and
definition lists. Below are some examples:

===Unordered Lists===
&lt;nowiki&gt;
* Item1
** SubItem1
** SubItem2
*** SubSubItem1
* Item 2
* Item 3
&lt;/nowiki&gt;
would be drawn as:
* Item1
** SubItem1
** SubItem2
*** SubSubItem1
* Item 2
* Item 3

===Ordered Lists===
&lt;nowiki&gt;
# Item1
## SubItem1
## SubItem2
### SubSubItem1
# Item 2
# Item 3
&lt;/nowiki&gt;
# Item1
## SubItem1
## SubItem2
### SubSubItem1
# Item 2
# Item 3

===Mixed Lists===
&lt;nowiki&gt;
# Item1
#* SubItem1
#* SubItem2
#*# SubSubItem1
# Item 2
# Item 3
&lt;/nowiki&gt;
# Item1
#* SubItem1
#* SubItem2
#*# SubSubItem1
# Item 2
# Item 3

===Definition Lists===
&lt;nowiki&gt;
;Term 1: Definition of Term 1
;Term 2: Definition of Term 2
&lt;/nowiki&gt;
;Term 1: Definition of Term 1
;Term 2: Definition of Term 2

==Tables==
A table begins with {`|`  and ends with `|`}. Cells are separated with | and
rows are separated with |- as can be seen in the following
example:
&lt;nowiki&gt;
{|
|a||b
|-
|c||d
|}
&lt;/nowiki&gt;
{|
|a||b
|-
|c||d
|}
Headings for columns and rows can be made by using an exclamation point, !,
rather than a vertical bar |. For example,
&lt;nowiki&gt;
{|
!a!!b
|-
|c|d
|}
&lt;/nowiki&gt;
{|
!a!!b
|-
|c|d
|}
Captions can be added using the + symbol:
&lt;nowiki&gt;
{|
|+ My Caption
!a!!b
|-
|c|d
|}
&lt;/nowiki&gt;
{|
|+ My Caption
!a!!b
|-
|c|d
|}
Finally, you can put a CSS class or style attributes (or both) on the first line
of the table to further control how it looks:
&lt;nowiki&gt;
{| class=&quot;wikitable&quot;
|+ My Caption
!a!!b
|-
|c|d
|}
&lt;/nowiki&gt;
{| class=&quot;wikitable&quot;
|+ My Caption
!a!!b
|-
|c|d
|}
Within a cell attributes like align, valign, styles, and class can be used. For
example,
&lt;nowiki&gt;
{|
| style=&quot;text-align:right;&quot;| a| b
|-
| lalala | lalala
|}
&lt;/nowiki&gt;
{|
| style=&quot;text-align:right;&quot;| a| b
|-
| lalala | lalala
|}

==Math==

Math can be included into a wiki document by either using the math tag:
&lt;nowiki&gt;
&lt;math&gt;
\sum_{i=1}^{n} i = frac{(n+1)(n)}{2}
&lt;/math&gt;
&lt;/nowiki&gt;

&lt;math&gt;
\sum_{i=1}^{n} i = frac{(n+1)(n)}{2}
&lt;/math&gt;

==Adding Resources to a Page==

Yioop wiki syntax supports adding search bars, audio, images, and video to a
page. The magnifying class edit tool icon can be used to add a search bar via
the GUI. This can also be added by hand with the syntax:
&lt;nowiki&gt;
{{search:default|size:small|placeholder:Search Placeholder Text}}
&lt;/nowiki&gt;
This syntax is split into three parts each separated by a vertical bar |. The
first part search:default means results from searches should come from the
default search index. You can replace default with the timestamp of a specific
index or mix if you do not want to use the default. The second group size:small
indicates the size of the search bar to be drawn. Choices of size are small,
medium, and large. Finally, placeholder:Search Placeholder Text indicates the
grayed out background text in the search input before typing is done should
read: Search Placeholder Text. Here is what the above code outputs:

{{search:default|size:small|placeholder:Search Placeholder Text}}

Image, video and other media resources can be associated with a page by dragging
and dropping them in the edit textarea or by clicking on the link click to select
link in the gray box below the textarea. This would add wiki code such as

&lt;pre&gt;
( (resource:myphoto.jpg|Resource Description))
&lt;/pre&gt;

to the page. Only saving the page will save this code and upload the resource to
the server. In the above myphoto.jpg is the resource that will be inserted and 
Resource Description is the alternative text to use in case the viewing browser
cannot display jpg files. A list of media that have already been associated with
a page appears under the Page Resource heading below the textarea. This
table allows the user to rename and delete resources as well as insert the
same resource at multiple locations within the same document. To add a resource
from a different wiki page belonging to the same group to the current wiki
page one can use a syntax like:

&lt;pre&gt;
( (resource:Documentation:ConfigureScreenForm1.png|The work directory form)) 
&lt;/pre&gt;

Here Documentation would be the page and ConfigureScreenForm1.png the resource.

==Page Settings, Page Type==

In edit mode for a wiki page, next to the page name, is a link [Settings].
Clicking this link expands a form which can be used to control global settings
for a wiki page.  This form contains a drop down for the page type, another
drop down for the type of border for the page in non-logged in mode,
a checkbox for whether a table of contents should be auto-generated from level 2
and level three headings and then text
fields or areas for the page title, author, meta robots, and page description.
Beneath this one can specify another wiki page to be used as a header for this
page and also specify another wiki page to be used as a footer for this page.

The contents of the page title is displayed in the browser title when the 
wiki page is accessed with the  Activity Panel collapsed or when not logged in. 
Similarly, in the collapsed or not logged in mode, if one looks as the HTML 
page source for the page,  in the head of document, &lt;meta&gt; tags for author, 
robots, and description are set according to these fields. These fields can
be useful for search engine optimization. The robots meta tag can be
used to control how search engine robots index the page. Wikipedia has more information on
[[https://en.wikipedia.org/wiki/Meta_element|Meta Elements]].

The &#039;&#039;&#039;Standard&#039;&#039;&#039; page type treats the page as a usual wiki page.

&#039;&#039;&#039;Page Alias&#039;&#039;&#039; type redirects the current page to another page name. This can
be used to handle things like different names for the same topic or to do localization
of pages. For example, if you switch the locale from English to French and
you were on the wiki page dental_floss when you switch to French the article
dental_floss might redirect to the page dentrifice.

&#039;&#039;&#039;Media List&#039;&#039;&#039; type means that the page, when read, should display just the
resources in the page as a list of thumbnails and links. These links for the 
resources go to a separate pages used to display these resources. 
This kind of page is useful for a gallery of
images or a collection of audio or video files. 

&#039;&#039;&#039;Presentation&#039;&#039;&#039; type is for a wiki page whose purpose is a slide presentation. In this mode,
....
on a line by itself is used to separate one slide. If presentation type is a selected a new
slide icon appears in the wiki edit bar allowining one to easily add new slides. 
When the Activity panel is not collapsed and you are reading a presentation, it just 
displays as a single page with all slides visible. Collapsing the Activity panel presents 
the slides as a typical slide presentation using the
[[www.w3.org/Talks/Tools/Slidy2/Overview.html|Slidy]] javascript.
EOD;
$public_pages["en-US"]["ad_program_terms"] = <<< 'EOD'
page_type=standard

page_alias=

page_border=none

toc=true

title=Advertisement Program Terms

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS==Terms and Conditions==
EOD;
$public_pages["en-US"]["advertise"] = <<< 'EOD'
page_type=standard

page_alias=

page_border=none

toc=true

title=Advertise using Yioop

author=Chris Pollett

robots=

description=A Description of Advertising Available at Yioop

page_header=

page_footer=

END_HEAD_VARS==What Ad Services We Offer==
EOD;
$public_pages["en-US"]["bot"] = <<< 'EOD'
title=Bot

description=Describes the web crawler used with this
web site
END_HEAD_VARS
==My Web Crawler==

Please Describe Your Robot
EOD;
$public_pages["en-US"]["captcha_time_out"] = <<< 'EOD'
title=Captcha/Recover Time Out
END_HEAD_VARS
==Account Timeout==

A large number of captcha refreshes or recover password requests
have been made from this IP address. Please wait until
%s to try again.
EOD;
$public_pages["en-US"]["presentation"] = <<< 'EOD'
page_type=presentation

page_alias=

page_border=solid-border

toc=true

title=Test Presentation

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS=Title=
* Slide Item
* Slide Item
* Slide Item
....
=Title=
* Slide Item
* Slide Item
* Slide Item
....

EOD;
$public_pages["en-US"]["privacy"] = <<< 'EOD'
title=Privacy Policy

description=Describes what information this site collects and retains about
users and how it uses that information
END_HEAD_VARS
==We are concerned with your privacy==
EOD;
$public_pages["en-US"]["register_time_out"] = <<< 'EOD'
title=Create/Recover Account

END_HEAD_VARS

==Account Timeout==

A number of incorrect captcha responses or recover password requests
have been made from this IP address. Please wait until
%s to access this site.
EOD;
$public_pages["en-US"]["suggest_day_exceeded"] = <<< 'EOD'

EOD;
$public_pages["en-US"]["terms"] = <<< 'EOD'
=Terms of Service=

Please write the terms for the services provided by this website.
EOD;
//
// Default Help Wiki Pages
//
/**
 * Help wiki pages
 * @var array
 */$help_pages = [];
$help_pages["en-US"]["Account_Registration"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=Account Registration

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThe Account Registration field-set is used to control how user&#039;s can obtain accounts on a Yioop installation.

The dropdown at the start of this fieldset allows you to select one of four
possibilities:
* &#039;&#039;&#039;Disable Registration&#039;&#039;&#039;, users cannot register themselves, only the root
account can add users.
When Disable Registration is selected, the Suggest A Url form and link on
the tool.php page is disabled as well, for all other registration type this
link is enabled.
* &#039;&#039;&#039;No Activation&#039;&#039;&#039;, user accounts are immediately activated once a user
signs up.
* &#039;&#039;&#039;Email Activation&#039;&#039;&#039;, after registering, users must click on a link which
comes in a separate email to activate their accounts.
If Email Activation is chosen, then the reset of this field-set can be used
to specify the email address that the email comes to the user. The checkbox Use
PHP mail() function controls whether to use the mail function in PHP to send
the mail, this only works if mail can be sent from the local machine.
Alternatively, if this is not checked like in the image above, one can
configure an outgoing SMTP server to send the email through.
* &#039;&#039;&#039;Admin Activation&#039;&#039;&#039;, after registering, an admin account must activate
the user before the user is allowed to use their account.
EOD;
$help_pages["en-US"]["Ad_Server"] = <<< EOD
page_type=standard

page_border=solid-border

title=Ad Server

END_HEAD_VARS* The Ad Server field-set is used to control whether, where,
and what external advertisements should be displayed by this Yioop instance.
EOD;
$help_pages["en-US"]["Add_Locale"] = <<< EOD
page_type=standard

page_border=solid-border

toc=true

title=Add Locale

description=Help article describing how to add a Locale.

END_HEAD_VARS==Adding a Locale==

The Manage Locales activity can be used to configure Yioop for use with 
different languages and for different regions.

* The first form on this activity allows you to create a new &amp;quot;Locale&amp;quot;
-- an object representing a language and a region.
* The first field on this form should be filled in with a name for the locale in
the language of the locale.
* So for French you would put :Fran&amp;ccedil;ais. The locale tag should be the
IETF language tag.
EOD;
$help_pages["en-US"]["Adding_Examples_to_a_Classifier"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSTo train a classifier one needs to add positive and negative examples of the concept that is to be learned. One way to add positive (negative) examples is to select an existing crawl and then marking that all (respectively, none) are in the class using the drop down below.

&lt;br /&gt;

Another way to give examples is to pick an existing crawl, leave the dropdown set to label by hand. Then type some keywords to search for in the crawl you picked using the &#039;&#039;&#039;Keyword&#039;&#039;&#039; textfield and click &#039;&#039;&#039;Load&#039;&#039;&#039;. This will bring up a list of search results together with links &#039;&#039;&#039;In Class&#039;&#039;&#039;, &#039;&#039;&#039;Not in Class&#039;&#039;&#039;, and &#039;&#039;&#039;Skip&#039;&#039;&#039;. These can then be used to add positive or negative examples.

&lt;br /&gt;

When you are done adding example, click &#039;&#039;&#039;Finalize&#039;&#039;&#039; to have Yioop actually build the classifier based on your training.

EOD;
$help_pages["en-US"]["Allowed_to_Crawl_Sites"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Allowed to Crawl Sites&#039;&#039;&#039; is a list of urls (one-per-line) and domains that the crawler is allowed to crawl. Only pages that are on sub-sites of the urls listed here will be crawled.

&lt;br /&gt;

This textarea is only used in determining by can be crawled if &#039;&#039;&#039;Restrict Sites By Url&#039;&#039;&#039; is checked.

&lt;br /&gt;

A line like:
&lt;pre&gt;
  http://www.somewhere.com/foo/
&lt;/pre&gt;
would allow the url
&lt;pre&gt;
  http://www.somewhere.com/foo/goo.jpg
&lt;/pre&gt;
to be crawled.

&lt;br /&gt;

A line like:
&lt;pre&gt;
 domain:foo.com
&lt;/pre&gt;
would allow the url
&lt;pre&gt;
  http://a.b.c.foo.com/blah/
&lt;/pre&gt;
to be crawled.
EOD;
$help_pages["en-US"]["Arc_and_Re-crawls"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Crawl or Arc Folder to Re-index&#039;&#039;&#039; dropdown allows one to select a previous Yioop crawl or an archive to do another crawl of. Possible archives that can be index include Arc files,  Warc Files, Email, Database dump, Open Directory RDF dumps, Media Wiki dumps etc. Re-crawling an old crawl might be useful if you would like to do further processing of the records in the index. Besides containing previous crawls, the dropdown list is populated by looking at the WORK_DIRECTORY/archives folder for sub-folders containing an arc_description.ini file.

&lt;br /&gt;

{{right|[[https://www.seekquarry.com/?c=static&amp;p=Documentation#Archive%20Crawl%20Options| Learn More.]]}}

EOD;
$help_pages["en-US"]["Authentication_Type"] = <<< EOD
page_type=standard

page_border=solid-border

title=Authentication Type

END_HEAD_VARSThe Authentication Type field-set is used to control the protocol
used to log people into Yioop.

* Below is a list of Authentication types supported.
** &#039;&#039;&#039;Normal Authentication&#039;&#039;&#039;, passwords are checked against stored as
salted hashes of the password; or
** &#039;&#039;&#039;ZKP (zero knowledge protocol) authentication&#039;&#039;&#039;, the server picks
challenges at random and send these to the browser the person is logging in
from, the browser computes based on the password an appropriate response
according to the Fiat Shamir protocol.cThe password is never sent over the
internet and is not stored on the server. These are the main advantages of
ZKP, its drawback is that it is slower than Normal Authentication as to prove
who you are with a low probability of error requires several browser-server
exchanges.

* You should choose which authentication scheme you want before you create many
users as if you switch everyone will need to get a new password.
EOD;
$help_pages["en-US"]["Browse_Groups"] = <<< EOD
page_type=standard
page_border=solid-border
toc=true
title=Browse Groups
END_HEAD_VARS==Creating or Joining a group==
You can create or Join a Group all in one place using this Text field.
Simply enter the Group Name You want to create or Join. If the Group Name
already exists, you will simply join the group. If the group name doesn&#039;t
exist, you will be presented with more options to customize and create your
new Group.
==Browse Existing Groups==
You can use the [Browse] hyper link to browse the existing Groups.
You will then be presented with a web form to narrow your search followed by
a list of all visible groups to you beneath.
{{right|[[https://www.seekquarry.com/?c=static&amp;p=Documentation#Managing%20Users,%20Roles,%20and%20Groups| Learn More..]]}}
EOD;
$help_pages["en-US"]["Captcha_Type"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=Captcha Type

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThe Captcha Type field set controls what kind of
[[https://en.wikipedia.org/wiki/CAPTCHA|captcha]] will be used during account
registration, password recovery, and if a user wants to suggest a url.

* The choices for captcha are:
** &#039;&#039;&#039;Text Captcha&#039;&#039;&#039;, the user has to select from a series of dropdown answers
to questions of the form: &#039;&#039;Which in the following list is the most/largest/etc?
or Which is the following list is the least/smallest/etc?; &#039;&#039;
** &#039;&#039;&#039;Graphic Captcha&#039;&#039;&#039;, the user needs to enter a sequence of characters from
a distorted image;
** &#039;&#039;&#039;Hash captcha&#039;&#039;&#039;, the user&#039;s browser (the user doesn&#039;t need to do anything)
needs to extend a random string with additional characters to get a string
whose hash begins with a certain lead set of characters.

Of these, Hash Captcha is probably the least intrusive but requires
Javascript and might run slowly on older browsers. A text captcha might be used
to test domain expertise of the people who are registering for an account.
Finally, the graphic captcha is probably the one people are most familiar with.
EOD;
$help_pages["en-US"]["Changing_the_Classifier_Label"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThe label of a classifier determines what meta-words will be added to pages that have that concept.

&lt;br /&gt;

If the label is foo, and the foo classifier is used in a crawl, then pages which have the foo property
will have the meta-word class:foo added to the list of words that are indexed.
EOD;
$help_pages["en-US"]["Crawl_Mixes"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSA &#039;&#039;&#039;Crawl Mix&#039;&#039;&#039; allows one to combine several crawl indexes into one to greater customize search results. This page allows one to either create a new crawl mix or find and edit an existing one. The list of crawl mixes is user dependent -- each user can create their own mixes of crawls that exist on the Yioop system.

&lt;br /&gt;

Clicking &#039;&#039;&#039;Share&#039;&#039;&#039;  on a crawl mix allows a user to post their crawl mix to a group&#039;s feed. User&#039;s of that group can then import this crawl mix into their own list of mixes by clicking on it.

&lt;br /&gt;

Clicking &#039;&#039;&#039;Set as Index&#039;&#039;&#039;  on a crawl mix means that by default the given crawl mix will be used to serve search results for this site.
EOD;
$help_pages["en-US"]["Crawl_Order"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Crawl Order&#039;&#039;&#039; controls how the crawl determines what to crawl next.

&lt;br /&gt;

&#039;&#039;&#039;Breadth-first Search&#039;&#039;&#039; means that Yioop first crawls the seeds sites, followed by those
sites directly linked to the seed site, followed by those directly linked to sites directly linked
to seed sites, etc.

&lt;br /&gt;

&#039;&#039;&#039;Page Importance&#039;&#039;&#039; gives each seed site an initial amount of cash. Yioop then crawls the seed sites. A given crawled page has its cash splits  amongst the sites that it link to based on the link quality and whether it has been crawled yet. The sites with the most cash are crawled next and this process is continued.
EOD;
$help_pages["en-US"]["Create_Group"] = <<< EOD
page_type=standard

page_border=solid-border

title=Create Group

END_HEAD_VARS&#039;&#039;You will get to this form when the Group Name is available to
create a new Group. &#039;&#039;
----

&#039;&#039;&#039;Name&#039;&#039;&#039; Field is used to specify the name of the new Group.
&lt;br /&gt;
&#039;&#039;&#039;Register&#039;&#039;&#039; dropdown says how other users are allowed to join the group:
* &lt;u&gt;No One&lt;/u&gt; means no other user can join the group (you can still invite
other users).
* &lt;u&gt;By Request&lt;/u&gt; means that other users can request the group owner to join
the group.
* &lt;u&gt;Anyone&lt;/u&gt; means all users are allowed to join the group.
&lt;br /&gt;
The &#039;&#039;&#039;Access&#039;&#039;&#039; dropdown controls how users who belong/subscribe to a group
other than the owner can access that group.
* &lt;u&gt;No Read&lt;/u&gt; means that a non-owner member of the group cannot read or
write the group news feed and cannot read the group wiki.
* &lt;u&gt;Read&lt;/u&gt; means that a non-owner member of the group can read the group
news feed and the groups wiki page.
* &lt;u&gt;Read&lt;/u&gt; Comment means that a non-owner member of the group can read the
group feed and wikis and can comment on any existing threads, but cannot start
new ones.
* &lt;u&gt;Read Write&lt;/u&gt;, means that a non-owner member of the group can start new
threads and comment on existing ones in the group feed and can edit and create
wiki pages for the group&#039;s wiki.
&#039;&#039;&#039;Voting&#039;&#039;&#039;
* Specify the kind of voting allowed in the new group. + Voting allows users to
vote up, -- Voting allows users to vote down. +/- allows Voting up and down.
&#039;&#039;&#039;Post Life time&#039;&#039;&#039; - Specifies How long the posts should be kept.
EOD;
$help_pages["en-US"]["Database_Setup"] = <<< EOD
page_type=standard

page_border=solid-border

title=Database Setup

END_HEAD_VARSThe database is used to store information about what users are
allowed to use the admin panel and what activities and roles these users have.
* The Database Set-up field-set is used to specify what database management
system should be used, how it should be connected to, and what user name and
password should be used for the connection.

* Supported Databases
** PDO (PHP&#039;s generic DBMS interface).
** Sqlite3 Database.
** Mysql Database.

* Unlike many database systems, if an sqlite3 database is being used then the
connection is always a file on the current filesystem and there is no notion of
login and password, so in this case only the name of the database is asked for.
For sqlite, the database is stored in WORK_DIRECTORY/data.

* For single user settings with a limited number of news feeds, sqlite is
probably the most convenient database system to use with Yioop. If you think you
are going to make use of Yioop&#039;s social functionality and have many users,
feeds, and crawl mixes, using a system like Mysql or Postgres might be more
appropriate.
EOD;
$help_pages["en-US"]["Disallowed_and_Sites_With_Quotas"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Disallowed to Crawl Sites&#039;&#039;&#039; are urls or domains (listed one-per-line) that Yioop should not crawl.

&lt;br /&gt;

A line like:
&lt;pre&gt;
  http://www.somewhere.com/foo/
&lt;/pre&gt;
would disallow the url
&lt;pre&gt;
  http://www.somewhere.com/foo/goo.jpg
&lt;/pre&gt;
to be crawled.

&lt;br /&gt;

A line like:
&lt;pre&gt;
 domain:foo.com
&lt;/pre&gt;
would disallow the url
&lt;pre&gt;
  http://a.b.c.foo.com/blah/
&lt;/pre&gt;
to be crawled.
&lt;br /&gt;

&#039;&#039;&#039;Sites with Quotes&#039;&#039;&#039; are urls or domains that Yioop should at most crawl some fixed number of urls from in an hour. These are listed in the same text area as Disallowed to Crawl Sites. To indicate the quota one lists after the url a fragment #some_number. For example,
&lt;pre&gt;
  http://www.yelp.com/#100
&lt;/pre&gt;
would restrict crawling of urls from Yelp to 100/hour.
EOD;
$help_pages["en-US"]["Discover_Groups"] = <<< EOD
page_type=standard

page_border=solid-border

toc=true

title=Discover Groups

END_HEAD_VARS&#039;&#039;&#039;Name&#039;&#039;&#039; Field is used to specify the name of the Group to
search for.
&#039;&#039;&#039;Owner&#039;&#039;&#039; Field lets you search a Group using it&#039;s Owner name.
&lt;br /&gt;
&#039;&#039;&#039;Register&#039;&#039;&#039; dropdown says how other users are allowed to join the group:
* &lt;u&gt;No One&lt;/u&gt; means no other user can join the group (you can still invite
other users).
* &lt;u&gt;By Request&lt;/u&gt; means that other users can request the group owner to join
the group.
* &lt;u&gt;Anyone&lt;/u&gt; means all users are allowed to join the group.
&lt;br /&gt;
&#039;&#039;It should be noted that the root account can always join any group.
The root account can also always take over ownership of any group.&#039;&#039;
&lt;br /&gt;
The &#039;&#039;&#039;Access&#039;&#039;&#039; dropdown controls how users who belong/subscribe to a group
other than the owner can access that group.
* &lt;u&gt;No Read&lt;/u&gt; means that a non-owner member of the group cannot read or
write the group news feed and cannot read the group wiki.
* &lt;u&gt;Read&lt;/u&gt; means that a non-owner member of the group can read the group
news feed and the groups wiki page.
* &lt;u&gt;Read&lt;/u&gt; Comment means that a non-owner member of the group can read the
group feed and wikis and can comment on any existing threads, but cannot start
new ones.
* &lt;u&gt;Read Write&lt;/u&gt;, means that a non-owner member of the group can start new
threads and comment on existing ones in the group feed and can edit and create
wiki pages for the group&#039;s wiki.
&lt;br /&gt;
The access to a group can be changed by the owner after a group is created.
* &lt;u&gt;No Read&lt;/u&gt; and &lt;u&gt;Read&lt;/u&gt; are often suitable if a group&#039;s owner wants to
perform some kind of moderation.
* &lt;u&gt;Read&lt;/u&gt; and &lt;u&gt;Read Comment&lt;/u&gt; groups are often suitable if someone wants
to use a Yioop Group as a blog.
* &lt;u&gt;Read&lt;/u&gt; Write makes sense for a more traditional bulletin board.
EOD;
$help_pages["en-US"]["Editing_Locales"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThe &#039;&#039;&#039;Edit Locale&#039;&#039;&#039; form can be used to specify how various message strings in Yioop are translated in different languages.

The table below has two columns: a column of string identifiers and a column of translations. A string identifier refers to a location in the code marked as needing to be translated, the corresponding translation in that row is how it should be translated for the current locale. Identifiers typically specify the code file in which the identifier occurs. For example, the identifier
 serversettings_element_name_server
would appear in the file views/elements/server_settings.php . To see where this identifier occurs one could open that file and search for this string.

If no translation exists yet for an identifier the translation value for that row will appear in red. Hovering the mouse over this red field will show the translation of this field in the default locale (usually English).

The &#039;&#039;&#039;Show dropdown&#039;&#039;&#039; allows one to show either all identifiers or just those missing translations. The filter field let&#039;s one to see only identifiers that contain the filter as a substring.
EOD;
$help_pages["en-US"]["Editing_a_Crawl_Mix"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSA crawl mix is built out of a list of &#039;&#039;&#039;search result fragments&#039;&#039;&#039;.

&lt;br /&gt;

A fragment has a &#039;&#039;&#039;Results Shown&#039;&#039;&#039; dropdown which specifies up to how many results that given fragment is responsible for. If one that had three fragments, the first with this value set to 1 the next with it set to 5 and the last set to whatever. Then on a query the Yioop will try to get the first result from the first fragment, up to the next five results from the next fragment, and all remaining results from the last fragment. If a given fragment doesn&#039;t produce results the search engine skips to the  next fragment.

&lt;br /&gt;

The &#039;&#039;&#039;Add Crawls&#039;&#039;&#039; dropdown can be used to add a crawl to the given fragment. Several crawl indexes can be added to a given fragment. When search results are computed for the fragment, the search is performed on all of these indexes and a score for each result is determined. The &#039;&#039;&#039;Weight&#039;&#039;&#039; dropdown can then be set to specify how important a given indexes score of a result should be in the total score of a search result. The top totals scores are then returned by the fragment. If when performing the search on a given index you would like additional terms to be added to the query these can be specified in the &#039;&#039;&#039;Keywords&#039;&#039;&#039; field.


EOD;
$help_pages["en-US"]["Filtering_Search_Results"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS==Filter Websites From Results Form==
The textarea in this form is used to list hosts one per line which are to be removed from any search result page in which they might appear. Lines in the textarea must be hostnames not general urls. Listing a host name like:
&lt;pre&gt;
 http://www.cs.sjsu.edu/
&lt;/pre&gt;
would prevent any urls from this site from appearing in search results. I.e., so for example, the URL
&lt;pre&gt;
 http://www.cs.sjsu.edu/faculty/pollett/
&lt;/pre&gt;
would be prevented from appearing in search results.
EOD;
$help_pages["en-US"]["Indexing_Plugins"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Indexing Plugins&#039;&#039;&#039; are additional indexing processors that a document can be made to go through during the indexing process. Users who know how to code can create their own plugins using the plugin API. Plugins can be used to extract new &quot;micro-documents&quot; from a given document, do clustering, or can be used to control the indexing or non-indexing of web pages based on their content.

&lt;br /&gt;

The table below allows a user to select and configure which plugins should be used in the current crawl.

&lt;br /&gt;


{{right|[[http://www.seekquarry.com/?c=static&amp;p=Documentation#Page%20Indexing%20and%20Search%20Options|Learn More..]]}}
EOD;
$help_pages["en-US"]["Kinds_of_Summarizers"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSYioop uses a &#039;&#039;&#039;summarizer&#039;&#039;&#039; to extract from a downloaded, or otherwise acquired document, text that it will add to its index. This text is also used for search result snippet generation. Only terms which appear in this summary can be used to look up a document.

&lt;br /&gt;

The &lt;b&gt;Basic&lt;/b&gt; summarizer tries to pick text from an ad hoc list of presumed important places in a web document until it has gotten the desired amount of text for a summary. For example, it might try to get text from title tags, h1 tags, etc before try to get it from paragraph tags.

&lt;br /&gt;

The &lt;b&gt;Centroid&lt;/b&gt; summarizer splits a document into &quot;sentence&quot; units. It then computes an &quot;average&quot; sentence for the document. It then adds to the summary sentences in order of how close they are to this average until the desired amount of text has been acquired.
EOD;
$help_pages["en-US"]["Locale_List"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=Locale List

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSBeneath the Add Locale form is a table listing some of the current
locales.


* The Show Dropdown let&#039;s you control how many of these locales are displayed in
one go.
* The Search link lets you bring up an advance search form to search for
particular locales and also allows you to control the direction of the listing.

The Locale List table
* The first column in the table  has a link with the name of the locale.
Clicking on this link brings up a page where one can edit the strings for that
locale.
* The next three columns of the Locale List table give the locale tag,
whether user&#039;s can use that locale in Settings, and the writing
direction of the locale, this is followed by the percent of strings translated.
* The Edit link in the column let&amp;#039;s you edit the locale tag, enabled status, and
text direction of a locale.
* Finally, clicking the Delete link let&amp;#039;s one delete a locale and all
its strings.
EOD;
$help_pages["en-US"]["Locale_Writing_Mode"] = <<< EOD
page_type=standard

page_border=solid-border

title=Locale Writing Mode

END_HEAD_VARSThe last field on the form is to specify how the language is
written. There are four options:
# lr-tb -- from left-to-write from the top of the page to the bottom as in
English.
#  rl-tb from right-to-left from the top the page to the bottom as in Hebrew
and Arabic.
#  tb-rl from the top of the page to the bottom from right-to-left as in
Classical Chinese.
#  tb-lr from the top of the page to the bottom from left-to-right as in
non-cyrillic Mongolian or American Sign Language.

&#039;&#039;lr-tb and rl-tb support work better than the vertical language support. As of
this writing, Internet Explorer and WebKit based browsers (Chrome/Safari) have
some vertical language support and the Yioop stylesheets for vertical languages
still need some tweaking. For information on the status in Firefox check out
this writing mode bug.&#039;&#039;
EOD;
$help_pages["en-US"]["Machine_Information"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Machine Information&#039;&#039;&#039; shows the currently known about machines. 

&lt;br /&gt;

This list always begins with the &#039;&#039;&#039;Name Server&#039;&#039;&#039; itself and a toggle to control whether or not the Media Updater process is running on the Name Server. This allows you to control whether or not Yioop attempts to update its RSS (or Atom) search sources on an hourly basis. Yioop also uses the Media updater to convert videos that have been uploaded into mp4 and webm if ffmpeg is installed.

&lt;br /&gt;

There is also a link to the log file of the Media Updater process. Under the Name Server information is a dropdown that can be used to control the number of current machine statuses that are displayed for all other machines that have been added. It also might have next and previous arrow links to go through the currently available machines.

&lt;br /&gt;

{{right|[[https://www.seekquarry.com/?c=static&amp;p=Documentation#GUI%20for%20Managing%20Machines%20and%20Servers| Learn More.]]}}
EOD;
$help_pages["en-US"]["Manage_Advertisements"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThe &#039;&#039;&#039;Advertisement Name&#039;&#039;&#039;, &#039;&#039;&#039;Text Description&#039;&#039;&#039;, &#039;&#039;&#039;Destination URL&#039;&#039;&#039; fields can be used to create a text-based advertisement. What this ad will look like appears in the &#039;&#039;&#039;Preview&#039;&#039;&#039; area.
&lt;br /&gt;

The &#039;&#039;&#039;Duration&#039;&#039;&#039; dropdown controls how many days the ad campaign will run for. The campaign starts on the date of purchase and this first day till midnight Pacific Time counts as one day of duration.
&lt;br /&gt;

&#039;&#039;&#039;Keywords&#039;&#039;&#039; should consist of a comma separated list of words or phrases. Each word or phrase has a minimum bid for each day based on demand for that keyword. If no one so far has purchased an ad for any of the keywords, then this minimum is $1/day/word or phrase. Otherwise, it is calculated using the total of the bids so far.
&lt;br /&gt; 

The &#039;&#039;&#039;Calculate Bid&#039;&#039;&#039; button computes the minimum cost for the campaign you have chosen, add presents a form to receive your credit card information.  

On this form the static field &#039;&#039;&#039;Minimum Bid Required&#039;&#039;&#039; field gives the minimum amount required to pay for the advertisement campaign in question. The &#039;&#039;&#039;Expensive word&#039;&#039;&#039; static field says for your campaign which term contributes the most to this minimum bid cost. The Budget fields allows you to enter an amount greater than or equal to the minimum bid that you are willing to pay your ad campaign. If there have been no other bids on your keywords then the minimum bid will show you ad 100% of the time any of your keywords are search for. If, however, there have been other bids, your bid amount as a fraction of the total bid amount for that day for the search keyword is used to select a frequency with which your ad is displayed, so it can make sense to bid more than the minimum required amount.
&lt;br /&gt;

If you need to edit the keywords or other details of your ad before purchasing it, you can click the &#039;&#039;&#039;Edit Ad&#039;&#039;&#039; button; otherwise, clicking the &#039;&#039;&#039;Purchase&#039;&#039;&#039; button completes the purchase of your Ad campaign.
&lt;br /&gt;

The &#039;&#039;&#039;Advertisement List&#039;&#039;&#039; beneath the form lists details for all of the ads you have created from most recent to least recent as well as impression and click information. You can edit the text of your ad (but not the keywords) by clicking an ad&#039;s edit column. You can also Deactivate a campaign to stop it from displaying. This does not refund your money.
EOD;
$help_pages["en-US"]["Manage_Credits"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Purchase Ad Credits&#039;&#039;&#039; form can be used to purchase ad credits which can then be spent under &#039;&#039;&#039;Manage Advertisements&#039;&#039;&#039;.
&lt;br /&gt;&lt;br /&gt;

The &#039;&#039;&#039;Quantity&#039;&#039;&#039; dropdown specifies the number of credits one wants to purchase at what price.
&lt;br /&gt;

The &#039;&#039;&#039;Card Number&#039;&#039;&#039; field should be filled in with a valid credit card.
&lt;br /&gt;

The &#039;&#039;&#039;CVC&#039;&#039;&#039; field you should put the three or four digit card verification number for your card.
&lt;br /&gt;

The &#039;&#039;&#039;Expiration&#039;&#039;&#039; dropdown is used to set your cards expiration date.
&lt;br /&gt; 

The &#039;&#039;&#039;Purchase&#039;&#039;&#039; button is used to complete the purchase of Ad credit.
&lt;br /&gt;

Beneath the Purchase form is the list of &#039;&#039;&#039;Ad Credit Transactions&#039;&#039;&#039; that have been made with your account.
EOD;
$help_pages["en-US"]["Manage_Machines"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Add Machine&#039;&#039;&#039; allows you to add a new machine to be controlled by this Yioop instance. 

&lt;br /&gt;

The &#039;&#039;&#039;Machine Name&#039;&#039;&#039; field lets you give this machine an easy to remember name. The Machine URL field should be filled in with the URL to the installed Yioop instance.

&lt;br /&gt;

The &#039;&#039;&#039;Mirror&#039;&#039;&#039; check-box says whether you want the given Yioop installation to act as a mirror for another Yioop installation. Checking it will reveal a drop-down menu that allows you to choose which installation amongst the previously entered machines you want to mirror. 

&lt;br /&gt;

The &#039;&#039;&#039;Has Queue Server&#039;&#039;&#039; check-box is used to say whether the given Yioop installation will be running a queue server or not.

&lt;br /&gt;

Finally, the &#039;&#039;&#039;Number of Fetchers&#039;&#039;&#039; drop down allows you to say how many fetcher instances you want to be able to manage for that machine.

&lt;br /&gt;

{{right|[[https://www.seekquarry.com/?c=static&amp;p=Documentation#GUI%20for%20Managing%20Machines%20and%20Servers|Learn More..]]}}
EOD;
$help_pages["en-US"]["Media_Sources"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Media Sources&#039;&#039;&#039; are used to specify how Yioop should handle video and news sites. 

&lt;br /&gt;

A &#039;&#039;&#039;Video source&#039;&#039;&#039; is used to specify where to find the thumb nail of a video given the url of the video on a website. This is used by Yioop when displaying search results containing the video link to show the thumb nail. For example, if the Url value is
 http://www.youtube.com/watch?v={} 
and the Thumb value is
 http://i1.ytimg.com/vi/{}/default.jpg, 
this tells Yioop that if a search result contains something like
&lt;pre&gt;
 https://www.youtube.com/watch?v=dQw4w9WgXcQ
&lt;/pre&gt;
this says find the thumb at
&lt;pre&gt;
 http://i1.ytimg.com/vi/dQw4w9WgXcQ/default.jpg 
&lt;/pre&gt;

An &#039;&#039;&#039;RSS media source&#039;&#039;&#039; can be used to add an RSS or Atom feed (it auto-detects which kind) to the list of feeds which are downloaded hourly when Yioop&#039;s Media Updater is turned on. Besides the name you need to specify the URL of the feed in question. 

&lt;br /&gt;

An &#039;&#039;&#039;HTML media source&#039;&#039;&#039; is a web page that has news articles like an RSS page that you want the Media Updater to scrape on an hourly basis. To specify where in the HTML page the news items appear you specify different XPath information. For example,
&lt;pre&gt;
 Name: Cape Breton Post	
 URL: http://www.capebretonpost.com/News/Local-1968
 Channel: //div[contains(@class, &quot;channel&quot;)]	
 Item: //article
 Title:	//a
 Description: //div[contains(@class, &quot;dek&quot;)]
 Link: //a
&lt;/pre&gt;
The Channel field is used to specify the tag that encloses all the news items. Relative to this as the root tag, //article says the path to an individual news item. Then relative to an individual news item, //a gets the title, etc. Link extracts the href attribute of that same //a .

&lt;br /&gt;

Not all RSS feeds use the same tag to specify the image associated with a news item. The Image XPath allows you to specify relative to a news item (either RSS or HTML) where an image thumbnail exists. If a site does not use such thumbnail one can prefix the path with ^ to give the path relative to the root of the whole file to where a thumb nail for the news source exists. Yioop automatically removes escaping from RSS containing escaped HTML when computing this. For example, the following works for the feed:
&lt;pre&gt;
  http://feeds.wired.com/wired/index
 //description/div[contains(@class,
    &quot;rss_thumbnail&quot;)]/img/@src
&lt;/pre&gt;
EOD;
$help_pages["en-US"]["Name_Server_Setup"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSYioop can be run in a single machine or multi-machine setting. In a multi-machine setting, copies of Yioop software would be on different machines. One machine called the &#039;&#039;&#039;Name Server&#039;&#039;&#039; would be responsible for coordinating who crawls what between these machines. This fieldset allows the user to specify the url of the Name Server as well as a string (which should be the same amongst all machines using that name server) that will be used to verify that this machine is allowed to talk to the Name Server. In a single machine setting these settings can be left at their default values.

&lt;br /&gt;

When someone enters a query into a Yioop set-up, they typically enter the query on the name server. The &#039;&#039;&#039;Use Filecache&#039;&#039;&#039; checkbox controls whether the query results are cached in a file so that they don&#039;t have to be recalculated when someone enters the same query again. The file cache is purged periodically so that it doesn&#039;t get too large.  
EOD;
$help_pages["en-US"]["Page_Byte_Ranges"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Byte Range to Download&#039;&#039;&#039; determines the maximum number of bytes that Yioop will download for a given page when crawling. Setting a maximum is important so that Yioop does not get stuck downloading very large files.

&lt;br /&gt;

When Yioop shows the cached version of a URL it shows only what it downloaded.
EOD;
$help_pages["en-US"]["Page_Classifiers"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSClassifiers are used to say whether a page has or does not have a property. The &#039;&#039;&#039;Manage Classifiers&#039;&#039;&#039; activity let&#039;s you create and manage the classifiers for this Yioop system. Creating a classifier will take you to a page that let&#039;s you train the classifier against existing data such as a crawl indexed. Once you have a classifier you can use it to add meta words for that concept to pages in future crawls by selecting in on the Page Options activity. You can also use classifiers to score documents for ranking purposes in search results, again this can be done under the Page Options Activity.
EOD;
$help_pages["en-US"]["Page_Grouping_Options"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThe &#039;&#039;&#039;Search Results Grouping&#039;&#039;&#039; controls allow you to control on a search query how many qualifying documents from an index to compute before trying to sort and rank them to find the top k results (here k is usually 10).  In a multi-queue-server setting the query is simultaneously asked by the name server machine of each of the queue server machines and the results are aggregated. 

&lt;br /&gt;

&#039;&#039;&#039;Minimum Results to Group&#039;&#039;&#039; controls the number of results the name server want to have before sorting of results is done. When the name server request documents from each queue server, it requests for
&lt;br /&gt;
&amp;alpha; &amp;times; (Minimum Results to Group)/(Number of Queue Servers) documents. 

&lt;br /&gt;
&#039;&#039;&#039;Server Alpha&#039;&#039;&#039; controls the number alpha. 
EOD;
$help_pages["en-US"]["Page_Ranking_Factors"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSIn computing the relevance of a word/term to a page the fields on this form allow one to set the relative weight given to the word depending on whether it appears in the title, a link, or if it appears anywhere
else (description).
EOD;
$help_pages["en-US"]["Page_Rules"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Page Field Extraction Rules &#039;&#039;&#039; are statements from a Yioop-specific indexing language which can be applied to the words in a summary page before it is stored in an index. Details on this language can be found in the [[http://www.seekquarry.com/?c=static&amp;p=Documentation#Page%20Indexing%20and%20Search%20Options|Page Indexing and Search Options]] section of the Yioop Documentation.

&lt;br /&gt;

The textarea below this heading can be used to list out which extraction rules should be used for the current crawl.
EOD;
$help_pages["en-US"]["Proxy_Server"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=Proxy server

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS* Yioop can make use of a proxy server to do web
crawling.
EOD;
$help_pages["en-US"]["Search_Results_Editor"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThe &#039;&#039;&#039;Edit Result Page&#039;&#039;&#039; form can be used to change the title and snippet text associated with a given url if it appears in search results. The Edited Urls dropdown let&#039;s one see which URLs have been previously edited and allows one to load and re-edit these if desired. Edited words in the title and description of an edited URL are not indexed. Only the words from the page as originally appearing in the index are used for this. This form only controls the title and snippet text of the URL when it appears in a search engine result page.
EOD;
$help_pages["en-US"]["Search_Results_Page_Elements"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThese checkboxes control whether various links and drop downs on the search result and landing
pages appear or not.

; &#039;&#039;&#039;Word Suggest&#039;&#039;&#039;: Controls whether the suggested query drop down appear as a query is entered in the search bar and whether thesaurus results appear on search result pages.
; &#039;&#039;&#039;Subsearch&#039;&#039;&#039; : Controls whether the links to subsearches such as Image, Video, and News search appear at the top of all search pages
; &#039;&#039;&#039;Signin&#039;&#039;&#039; : Controls whether the &#039;&#039;&#039;Sign In&#039;&#039;&#039; link appears at the top of the Yioop landing and search result pages.
; &#039;&#039;&#039;Cache&#039;&#039;&#039;, &#039;&#039;&#039;Similar&#039;&#039;&#039;, &#039;&#039;&#039;Inlinks&#039;&#039;&#039;, &#039;&#039;&#039;IP Address&#039;&#039;&#039;: Control whether the corresponding links appear after each search result item.

	

EOD;
$help_pages["en-US"]["Seed_Sites_and_URL_Suggestions"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Seed Sites&#039;&#039;&#039; are a list of urls that Yioop should start a crawl from.

&lt;br /&gt;

If under Server Settings : Account Registration user&#039;s are allowed to register for Yioop accounts at some
level other than completely disabled, then the Tools: Suggest a Url form will be enabled. URLs suggested through this form can be added to the seed sites by clicking the &#039;&#039;&#039;Add User Suggest data&#039;&#039;&#039; link. These URLS will appear at the end of the seeds sites and will appear with a timestamp of when they added before them. Adding this data to the seed sites clears the list of suggested sites from where it is temporarily stored before being added.

&lt;br /&gt;

Some site&#039;s robot.txt forbid crawl of the site. If you would like to create a placeholder page for such a site so that a link to that site might still appear in the index, but so that the site itself is not crawled by the crawler, you can use a syntax like:

&lt;nowiki&gt;
http://www.facebool.com/###!
Facebook###!
A%20famous%20social%20media%20site
&lt;/nowiki&gt;

This should all be on one line. Here ###! is used a separator and the format is url##!title###!description.
EOD;
$help_pages["en-US"]["Start_Crawl"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSEnter a name for your crawl and click start to begin a new crawl. Previously completed crawls appear in the table below.

&lt;br /&gt;

Before you start your crawl be sure to start the queue servers and fetchers to be used for the crawl under &#039;&#039;&#039;Manage Machines&#039;&#039;&#039;.

&lt;br /&gt;

The &#039;&#039;&#039;Options&#039;&#039;&#039; link let&#039;s you specify what web sites you want to crawl or if you want to do an archive previous crawls or different kinds of data sets.
EOD;
$help_pages["en-US"]["Subsearches"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARS&#039;&#039;&#039;Subsearches&#039;&#039;&#039; are specialized search hosted on a Yioop site other than the default index. For example, a site might have a usual web search and also offer News and Images subsearches. This form let&#039;s you set up such a subsearch.

&lt;br /&gt;

A list of links to all the current subsearches on a Yioop site appears at the 
 site_url?a=more
page. Links to some of the subsearches may appear at the top left hand side of of the default landing page provided the Pages Options : Search Time : Subsearch checkbox is checked.

&lt;br /&gt;

The &#039;&#039;&#039;Folder Name&#039;&#039;&#039; of a subsearch is the name that appears as part of the query string when doing a search restricted to that subsearch. After creating a subsearch, the table below will have a &#039;&#039;&#039;Localize&#039;&#039;&#039; link next to its name. This lets you give names for your subsearch on the More page mentioned above with respect to different languages. 

EOD;
$help_pages["en-US"]["Summary_Length"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThis determines the maximum number of bytes that can appear in a summary generated for a document that Yioop has crawled. To have any effect this value should be smaller that the byte range downloaded. yo
EOD;
$help_pages["en-US"]["Test_Indexing_a_Page"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThe &#039;&#039;&#039;Test Page&#039;&#039;&#039; form is used to test how Yioop would process a given web page. To test a web page one copies and pastes the source of the web page (obtainable by doing View Source in a browser) into the textarea. Then one selects the mimetype of the page (usually, text/html) and submits the form to see the processing results.
EOD;
$help_pages["en-US"]["Using_a_Classifier_or_Ranker"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSA &lt;b&gt;binary classifier&lt;/b&gt; is used to say whether or not a page has a property (for example, being a spam page or not). Classifiers can be created using the Manage Classifiers activity.

&lt;br/&gt;

The classifiers that have been created in this Yioop instance are listed in the table below and can be used for future crawls. Given a classifier named foo, selecting the &#039;&#039;&#039;Use to Classify&#039;&#039;&#039; check box for it tells Yioop to insert some subset of the following labels as meta-words when it indexes a page:
&lt;pre&gt;
 class:foo
 class:foo:10plus
 class:foo:20plus
 class:foo:30plus
 class:foo:40plus
 ...
 class:foo:50
 ...
&lt;/pre&gt;
When a document is scored against a classifier foo, it gets a score between 0 and 1 and if the score is greater than 0.5 the meta-word class:foo is added. A meta-word class:foo:XXplus indicates the document achieved at least a score of XX with respect to the classifier, and a meta-word class:foo:XX indicates it had a score between 0.XX and 0.XX + 0.9.

&lt;br /&gt;

The &#039;&#039;&#039;Use to Rank&#039;&#039;&#039; checkbox indicates that Yioop should take the score between 0 and 1 and use this as one of the scores when ranking search results.
EOD;
$help_pages["en-US"]["Work_Directory"] = <<< EOD
page_type=standard

page_alias=

page_border=solid-border

toc=true

title=

author=

robots=

description=

page_header=

page_footer=

END_HEAD_VARSThe &#039;&#039;&#039;Work Directory&#039;&#039;&#039; is a folder used to store all the customizations of this instance of Yioop.
This field should be a complete file system path to a folder that exists. 
It should use forward slashes. For example:

 /some_folder/some_subfolder/yioop_data 
(more appropriate for Mac or Linux) or
 c:/some_folder/some_subfolder/yioop_data
(more appropriate on a Windows system).

If you decide to upgrade Yioop at some later date you only have to replace the code folder
of Yioop and set the Work Directory path to the value of your pre-upgrade version. For this
reason the Work Directory should not be a subfolder of the Yioop code folder.
EOD;

