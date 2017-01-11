=== Google Analytics Popular Posts ===
Contributors: inn_nerds
Donate link: https://inn.org/donate
Tags: most popular, google analytics, analytics, stats
Requires at least: 4.1
Tested up to: 4.7
Stable tag: 0.1.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Use Google Analytics data to determine the most popular posts on your site and display a list of popular posts via the included widget.

== Description ==

This Google Analytics Popular Posts plugin queries Google Analytics for pageview data for your site and uses an algorithm based on publish date and total number of pageviews to determine a weighted pageview score for a post.

Using this scoring mechanism, the plugin generates a list of the most popular posts for a given site. The list of popular posts can be displayed using the included "Google Analytics Popular Posts" widget.

This plugin is under active development. If you experience problems with this plugin, please [file an issue on GitHub](https://github.com/INN/Google-Analytics-Popular-Posts/issues).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ga-popular-posts` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Authenticate using your Google account via the Settings > GA Popular Posts page in the WordPress dashboard
4. Review the settings, changing them as desired and click "Save changes"

== Frequently Asked Questions ==

= How does this differ from other WordPress popular post plugins? =

The problem we found with many WordPress popular post plugins was the lack of data used in the algorithm. Our approach was first to pull as much data as possible into WordPress so we could develop a better algorithm.

= How do I configure this plugin? =

Installation instructions are available [in the plugin documentation](https://github.com/INN/Google-Analytics-Popular-Posts/tree/master/docs)

= What data are you capturing? =

We're currently calling and caching the `ga:pageviews` to the database for each `ga:pagepath` dimension. Values for the current day and previous are stored during each cron job. A post id is generated for each `ga:pagepath` that corresponds to an actual post.

= How do I query the data myself? =

The data is stored across two database tables. The first (`analyticbridge_pages`) stores each `ga:pagepath` with a unique id and corresponding post id (if it exists).

The second table (`analyticbridge_metrics`) relates a `page_id` to a metric & value over a start & end date.

To query this data yourself, find the corresponding page_id from the pages table and select using it from the metrics table. This can be accomplished using joins.

== Changelog ==


= 0.1.2=

- Improvements to the login flow leading to fewer bugs
- Updated documentation after visual refreshes on Google.com
- Added documentation for some common problems
- Small code formatting cleanups

= 0.1.1 =

- Remove calls to function `of_get_option`, `largo_top_term`, `largo_hero_class`, `largo_has_categories_or_tags`
- Remove option to display thumbnails for posts
- Adds filter `abp-widget-posts-term` to allow changing the word used for multiple posts.
- Small code formatting cleanups

= 0.1.0 =

- Initial release
