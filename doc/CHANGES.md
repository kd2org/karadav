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
