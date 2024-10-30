Hatch
=====

## What is this thing?

Hatch is a helper library for developers which makes it more pleasant to build websites using Timber, Advanced Custom Fields Pro and other common plugins. 


## A better way to use Timber

Using [Timber](https://en-gb.wordpress.org/plugins/timber-library/) we can use Twig templates to build our sites rather than PHP. 

Typically we set up a Timber context like this:

```php
$context = Timber::get_context();

$context['post'] = Timber::get_post();

function website_get_related_posts() {
	// do something fancy here

	return [];
}

$context['related_posts'] = website_get_related_posts();

Timber::render( 'post.twig', $context );
```

This is _fine_ but it can get a bit mucky - especially if you have complicated page templates with lots of variables.

Hatch provides a tidier way of setting up contexts for Timber and other popular libraries:

```php

// 'post' is already added by default

Hatch::add_context( 'related_posts', function() {
	// do something fancy
	return [];
} );

Hatch::render( 'post.twig' ); // no need to pass $context in 

```


