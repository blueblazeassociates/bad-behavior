Bad Behavior
============

This is a fork of the Bad Behavior plugin.

Read the original README.txt for more information about it.

Changes:

* Changed to use function_exists(). is_callable() was failing on SiteGround php-cli.
* Fixed unclosed quotes in db query.
* Fixed undefined array index bugs.
* Tweaked plugin header to use afragen/github-updater.

Branches:

* blueblaze:      tracks the latest changes and tweaks from Blue Blaze.
* master:         tracks the latest version from the plugin author on the WordPress.org repo.
* 2.2.19-branch:  tracks Blue Blaze's updates to version 2.2.19.
