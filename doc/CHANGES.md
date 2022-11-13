# 0.3.2

* Fix issue with Cyberduck (Windows)
* Move auto-detection code for WWW_URL outside of config.local.php, if WWW_URL is not defined, then KaraDAV will trigger auto-detection

# 0.3.1

* Fix issue with NextCloud direct URLs crashing

# 0.3.0

* Fix typos
* Fix security issues
* Add list of tested clients in README
* Add systemd service file
* Don't store WOPI tokens in database, instead use a time-limited HMAC hash
