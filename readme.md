# Google Analytics Popular Posts

**_Warning:_** this plugin is under active development. Updating to new versions might require reactivating to rebuild database tables, as there is currently no upgrading framework.

## Documentation

- [Index](docs/index.md)
- [Setup](docs/setup.md)

## Questions

###### _How does this differ from other WordPress popular post plugins?_

The problem we found with many WordPress popular post plugins was the lack of data used in the algorithm. Our approach was first to pull as much data as possible into WordPress so we could develop a better algorithm.

###### _What data are you capturing?_

We're currently calling and caching the `ga:pageviews` to the database for each `ga:pagepath` dimension. Values for the current day and previous are stored during each cron job. A post id is generated for each `ga:pagepath` that corresponds to an actual post.

###### _How do I query the data myself?_

The data is stored across two database tables. The first (`analyticbridge_pages`) stores each `ga:pagepath` with a unique id and corresponding post id (if it exists).

The second table (`analyticbridge_metrics`) relates a `page_id` to a metric & value over a start & end date.

To query this data yourself, find the corresponding page_id from the pages table and select using it from the metrics table. This can be accomplished using joins.

## Completed & TODO

- [x] Authentication with Google Analytics.
- [x] Creation of database schema.
- [x] Cron job registered.
- [x] Dashboard widget
- [x] Cron job to update yesterday's values one a day.
- [x] Handle uninstall and disconnect properly.
- [x] Add front end options for the 'half-life' of a post
- [x] Class or WP_Query support for pulling popular posts.
- [ ] API for querying individual page views quickly.
- [ ] Add support for easier google redirect urls (rewrite rule)
