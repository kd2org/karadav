# Setting up SnappyMail with KaraDAV as SSO

[SnappyMail](https://github.com/the-djmaze/snappymail) is a lightweight webmail in PHP.

It can be integrated as an app inside KaraDAV quite easily.

# Setup

1. Copy the `_karadav.php` file in a new empty directory
2. Edit it to change `KARADAV_URL` to your own server.
3. Configure your web server to redirect `/index.php` to `/_karadav.php`, in Apache you can use the provided `.htaccess` file
4. Visit the URL linked to that folder, and append `?admin` at the end, eg. `https://snappymail.example.org/?admin`
5. PHP will download and extract the source code of SnappyMail, and should display the admin panel login screen
6. Get the password from the `data/_data_/_default_/admin_password.txt` file. The login is `admin`.
7. Create your domains, IMAP and SMTP config and so on.
8. Copy and edit the `_karadav_users.php` file to suit your needs, it MUST return an array with two elements: the login and the password
9. Add SnappyMail to KaraDAV menu, by editing the `config.local.php`:

```
const EXTERNAL_APPS = [
	'snappymail' => [
		'label'  => 'E-mail',
		'url'    => 'https://snappymail.example.org/?ext_session_id=%sessionid%',
		'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10h5v-2h-5c-4.34 0-8-3.66-8-8s3.66-8 8-8s8 3.66 8 8v1.43c0 .79-.71 1.57-1.5 1.57s-1.5-.78-1.5-1.57V12c0-2.76-2.24-5-5-5s-5 2.24-5 5s2.24 5 5 5c1.38 0 2.64-.56 3.54-1.47c.65.89 1.77 1.47 2.96 1.47c1.97 0 3.5-1.6 3.5-3.57V12c0-5.52-4.48-10-10-10m0 13c-1.66 0-3-1.34-3-3s1.34-3 3-3s3 1.34 3 3s-1.34 3-3 3"/></svg>',
	],
];
```

It should now work :)

Alternatively, if you already have a SnappyMail setup, just copy the `_karadav.php` file at the root of SnappyMail.

