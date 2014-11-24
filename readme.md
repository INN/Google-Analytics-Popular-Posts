# Analytic Bridge

## Completed & TODO

- [x] Authentication with Google Analytics.
- [x] Creation of database schema.
- [x] Cron job registered.
- [x] Dashboard widget
- [x] Cron job to update yesterday's values one a day.
- [ ] Handle uninstall and disconnect properly.
- [ ] Add front end options for the 'halflife' of a post
- [ ] API for querying individual pageviews quickly.
- [x] Class or WP_Query support for sorting by 'popularity'
- [ ] Add support for easier google redirect urls (rewrite rule)

## Connecting google services.

1. Log into https://console.developers.google.com/
2. Create a new project, name it anything you want (e.g. 'Analytic Bridge')
3. Under Credentials click 'Create new Client ID'
4. Under 'Authorized redirect URIs' insert the following url:
`http://{$path-to-wordpress}/wp-admin/options-general.php?page=analytic-bridge`
5. Add the Client Secret and Client ID to the Analytic Bridge option page.
6. [Find the profile id](https://support.google.com/analytics/answer/1032385?hl=en-GB) that corresponds to the Google Analytics table tracking your site, and save it in Property View ID. This should be in the format `ga:xxxxxxx`