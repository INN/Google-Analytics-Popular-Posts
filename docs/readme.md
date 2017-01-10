# Google Analytics Popular Posts

## An Analytics Plugin for WordPress

---

Google Analytics Popular Posts is a WordPress plugin from the [Institute for Nonprofit News](http://inn.org). Its goal is to make Google Analytics easily querable from the WordPress environment aiding in post aggregation, article metrics and better editorial understanding.

**This plugin is under active development.** Updating to new versions might require reactivating to rebuild database tables, as there is currently no upgrading framework. If you experience a problem with this plugin, please [file an issue on GitHub](https://github.com/INN/Google-Analytics-Popular-Posts/issues).

---

### Contents
 * __<big>[Setup Instructions](setup.md)</big>__ 
 * __<big>[Using the Widget](widget.md)</big>__ 
 * __<big>[Troubleshooting](troubleshooting.md)</big>__ 

---

### Description

At its core, Google Analytics Popular Posts is a wrapper around the Google Analytics API. On plugin activation, a WordPress cron job is registered to pull fresh analytic data every 20 minutes.

Functions for querying this data will be available in the future.

To show a potential (and probable) use case, the plugin registers a popular post widget on the WordPress dashboard. The algorithm used is currently crude, but takes into account the age of each post as well as the number of views recieved in the current day and previous day.
