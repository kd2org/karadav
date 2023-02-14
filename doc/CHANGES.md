## 0.3.8

* Allow to have "unlimited" quota for users (actually, up to the maximum space available on disk) by setting `-1` as user quota
* Change method for recursive disk space and recursive directory delete

## 0.3.7

* Fix issue #25 dynamic properties with PHP 8.2

## 0.3.6

* Fix division by zero error when quota is zero

## 0.3.5

* Add ERRORS_SHOW configuration constant to allow hiding PHP errors details
* Expose all ErrorManager options: add ERRORS_LOG, ERRORS_EMAIL, ERRORS_REPORT_URL constants to configure the path to the error log, an email address that should receive the errors, and a errbit/airbrake compatible API endpoint to sent the error reports to.

## 0.3.4

* Security fix
* Fix issues with directories containing brackets in their names

## 0.3.3

* Get around more NextCloud desktop client bugs

## 0.3.2

* Fix issue with Cyberduck (Windows)
* Move auto-detection code for WWW_URL outside of config.local.php, if WWW_URL is not defined, then KaraDAV will trigger auto-detection

## 0.3.1

* Fix issue with NextCloud direct URLs crashing

## 0.3.0

* Fix typos
* Fix security issues
* Add list of tested clients in README
* Add systemd service file
* Don't store WOPI tokens in database, instead use a time-limited HMAC hash
