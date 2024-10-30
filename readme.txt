=== Hatch ===
Contributors: A Big Egg
Tags: timber, templating, developers
Requires at least: 4.7
Tested up to: 5.9.2
Stable tag: 1.1.0
Requires PHP: 7.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Hatch is a helper library for developers which makes it more pleasant to build websites using Timber, Advanced Custom Fields Pro and other common plugins. 

== What is this thing? ==

Hatch is a helper library for developers which makes it more pleasant to build websites using Timber, Advanced Custom Fields Pro and other common plugins. 

== Requirements ==

Timber (latest version.)

== A better way to use Timber ==

Using [Timber](https://en-gb.wordpress.org/plugins/timber-library/) we can use Twig templates to build our sites rather than PHP. 

Typically we set up a Timber context like this: 

`
$context = Timber::get_context();

$context['post'] = Timber::get_post();

function website_get_related_posts() {
	// do something fancy here

	return [];
}

$context['related_posts'] = website_get_related_posts();

Timber::render( 'post.twig', $context );
`

This is _fine_ but it can get a bit mucky - especially if we have complicated page templates with lots of variables.

Hatch provides a tidier way of setting up contexts for Timber and other popular libraries:

`
// 'post' is already added by default

Hatch::add_context( 'related_posts', function() {
	// do something fancy
	return [];
} );

Hatch::render( 'post.twig' ); // no need to pass $context in 
`

More & better documentation to come! Look in abe-hatch.php for more info

== License ==

Copyright 2020 A Big Egg (David Hewitson Ltd)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.%