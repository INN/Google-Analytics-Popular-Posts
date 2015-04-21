# Setup

The Analytic Bridge takes two seperate authenticates to pull data.

 * A Google Developer account must be used to generate a `Client Secret` and `Client ID` for API authentication.
 * A user with read access to the appropriate analytics profile must authenticate their account for usage.

## Authenticating API calls

More detailed instructions coming soon.

 1. Log into [console.developers.google.com/](https://console.developers.google.com/)
 2. Create a new project, name it anything you want (e.g. 'Analytic Bridge')
 3. Under Credentials click 'Create new Client ID'
 4. Under 'Authorized redirect URIs' insert the following url: `http://{$path-to-wordpress}/wp-admin/options-general.php?page=analytic-bridge`
 5. Add the Client Secret and Client ID to the Analytic Bridge option page.
 6. Save changes on your page.

## Connecting a Google Profile

[Find the profile id](https://support.google.com/analytics/answer/1032385?hl=en-GB) that corresponds to the Google Analytics table tracking your site, and save it in Property View ID. This should be in the format `ga:xxxxxxx`

Assuming the above values are valid a "connect" link should become available at the bottom of the page. Use this button to authorize a Google account with permissions to the Property View you are attempting to pull data from.