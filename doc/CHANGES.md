## 0.7.0 - December 2025

* New design! New logo!
* Moved HTML code to templates
* Prepare for internationalization of UI
* Support for [external apps](doc/APPS.md) via iframe or links
* Add small API to transmit authentication data to apps (works with oPodSync)
* Add checkbox on login page to stay logged-in
* Web file manager:
  * New, better design!
  * Now integrated inside KaraDAV UI
  * New: create WOPI documents (text, spreadsheet, presentation, drawing)
  * Changed "download all" to "download selected"
  * Various small improvements and fixes

## 0.6.4 - December 11, 2025

* Update README for app support, report on more NextCloud clients working (or not): Iotas, Yaga, Memories, LesPas, Quillpad
* Add support for LesPas Android app
* Allow to unblock iOS clients in config file if anyone wants to test them
* Add support for OpenCloud Android client

## 0.6.3 - November 29, 2025

* Assign an ID to the root webdav directory, as it's not in the database in KaraDAV (fixes crash in Nextcloud Android app)

## 0.6.2 - November 28, 2025

* Update dependencies, fix issues for PHP 8.5 (please report any issue on PHP 8.5)

## 0.6.1 - January 10, 2025

* Fix issue with NextCloud desktop and directories

## 0.6.0 - January 5, 2025

* Add support for NextCloud Notes API (note: read-only/favorites are not supported)
* Add support for image thumbnails (documents are not supported), this can be disabled
* Web UI enhancements:
	* Multiple file upload using "Upload" button
	* Select multiple files and delete them
	* Image thumbnails
	* Editing text/MarkDown files: save without closing, auto-save, protect against accidental closing of the tab, press Ctrl+S to save
* KaraDAV now maintains a cache of file sizes and modification time inside the SQLite database
	* This will make things faster for finding out if something has changed when you have a lot of files
	* The `DISABLE_SLOW_OPERATIONS` constant has been removed and no longer has any effect
* Add new configuration constant `DB_JOURNAL_MODE` to be able to set WAL mode for SQLite (recommended)
* Add new configuration constant `THUMBNAIL_CACHE_PATH`
* Add sanity validation of file names, to avoid invalid characters in Windows
* Fix: emptying the trashbin returned an error in NextCloud Android

## 0.5.1 - November 21, 2024

* Fix upload of files larger than 10MB in NC/OC clients (thanks to @nproth)
* Update dependencies

## 0.5.0 - April 8, 2024

* Rewrite WOPI server
* Handle read-only files in WOPI server
* Implement randomly generated avatars for users

## 0.4.9 - 2024

* Bug fixes

## 0.4.8 - 2024

* Bug fixes

## 0.4.7 - 2024

* Bug fixes

## 0.4.6 - 2024

* Bug fixes

## 0.4.5 - January 10, 2024

* Fix: Don't forbid from creating directories containing three dots in the name

## 0.4.4 - January 1st, 2024

* Update dependencies

## 0.4.3 - December 31, 2023

* Add support for `PROPPATCH`-ing last modification time
* Fix creation of ghost directory in user directory (fix #46)
* Fix bug with NextCloud client where files were not showing up anymore
* Allow to empty trash from user home
* Update dependencies

## 0.4.2

## 0.4.1 - October 15, 2023

* Update dependencies to fix "Invalid URL" bug

## 0.4.0 - September 11, 2023

* Implement trashbin support, compatible with NextCloud API

## 0.3.13

* Use files inodes as file ID, so that we keep the same id when the file is moved or renamed.
* Fix a bug in the NextCloud Android app, where the "Plus" button was disabled, because the NextCloud app doesn't respect the NextCloud spec.

## 0.3.12

* Fix issue on NextCloud Android after they changed OC-FileId from string to integer

## 0.3.11

* Fix warning in PHP 8.2 (@lubiana)

## 0.3.10

* Allow to install as a subdirectory

## 0.3.9

* Allow to disable recursive directory size / mtime for slow filesystems

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
