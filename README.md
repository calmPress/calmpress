# calmPress
A modern CMS based on WordPress (a.k.a WordPress fork)

You can read more about the "why" on the project's site at https://calmpress.org and follow news on the blog https://blog.calmpress.org

## Getting a "ready to use" code

It might come as a surprise to some, but GitHub have bandwidth limits and in order to avoid running into such a corner, all of the
distributions will be done from our site. Right now you can get them from the downloads page at https://calmpress.org/downloads/

## Installation instructions
Same as the WordPress "5 minutes" installations. 

## Converting from WordPress
calmPress strives to be as DB and API compatible to WordPress as possible, therefor a conversion from WordPress to calmPress should not
involve more than pointing to the same DB, probably by copying the `wp-config.php`, and copying the wp-content directory.

Themes and plugins written for WordPress should work "as is", where "work" in this context is that they will not suffer any kind of PHP
level error, although if they are targetting some niche functionality that is being deprecated like trackbacks, they might not be able
to do anything meaningful.

## Contributing

In general this kind of project always have a need for non technical contribution. For example, the project needs a nicer logo, and some
proof reading.

Reporting issues, whether they are "bugs" or feature requests, is a great way to contribute. Few things to keep in mind
- We use the term "bug" to describe a situation in which a feature being developed is not working correctly. Once the feature was shipped
in a release, any further modifications are "feature requests"
- Suggestions on improving current implementation in terms of text UX, or code structure are very welcome (as long as they will not cause regresions ;) )
- For "new" user facing features you should have a convincing argument why more than 30% of the users will be interested to use it
- If you are interested in making a case for deprecating a feature, it should prove it has an inherent secutiry, privacy or performance
problems that can not be fixed, or that the feature is not used.
- Code contribution are welcome in the form of PR. Still better to open an issue first to be sure that the suggested development fits with the 
"values" and technical direction the project will like to have.
- If you are contributing a code, it should use the WordPress coding standards as much as possible.

## Developing

You will need to install NPM's grunt to run a build.

All sorce code is located under the /src directory, and the grunt/build process builds a "distribution" into the /build directory.
