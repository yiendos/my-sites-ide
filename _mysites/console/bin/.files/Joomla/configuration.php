<?php
/**
 * @package    Joomla.Installation
 *
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * -------------------------------------------------------------------------
 * THIS SHOULD ONLY BE USED AS A LAST RESORT WHEN THE WEB INSTALLER FAILS
 *
 * If you are installing Joomla! manually ie not using the web browser installer
 * then rename this file to configuration.php eg
 *
 * UNIX -> mv configuration.php-dist configuration.php
 * Windows -> rename configuration.php-dist configuration.php
 *
 * Now edit this file and configure the parameters for your site and
 * database.
 *
 * Finally move this file to the root folder of your Joomla installation eg
 *
 * UNIX -> mv configuration.php ../
 * Windows -> copy configuration.php ../
 *
 */
class JConfig
{
	/* Site Settings */
	public $offline = '0';
	public $offline_message = 'This site is down for maintenance.<br /> Please check back again soon.';
	public $display_offline_message = '1';
	public $offline_image = '';
	public $sitename = '%replace%';            // Name of Joomla site
	public $editor = 'tinymce';
	public $captcha = '0';
	public $list_limit = '20';
	public $access = '1';
	public $frontediting = '1';

	/* Database Settings */
	public $dbtype = 'mysqli';               // Normally mysqli
	public $host = 'db';              // This is normally set to localhost
	public $user = 'root';                       // DB username
	public $password = 'root';                   // DB password
	public $db = 'sites_%replace%';                         // DB database name
	public $dbprefix = 'j_';               // Do not change unless you need to!

	/* Server Settings */
	public $secret = 'YZ1sPNzswvZhvOB0';     // Change this to something more secure
	public $gzip = '0';
	public $error_reporting = 'default';
	public $helpurl = 'https://help.joomla.org/proxy?keyref=Help{major}{minor}:{keyref}&lang={langcode}';
	public $ftp_host = '';
	public $ftp_port = '';
	public $ftp_user = '';
	public $ftp_pass = '';
	public $ftp_root = '';
	public $ftp_enable = '0';
	public $tmp_path = '/Sites/%replace%/tmp/';                // This path needs to be writable by Joomla!
	public $log_path = '/Sites/%replace%/logs/'; // This path needs to be writable by Joomla!
	public $live_site = '';                   // Optional, full URL to Joomla install.
	public $force_ssl = 0;                    // Force areas of the site to be SSL ONLY.  0 = None, 1 = Administrator, 2 = Both Site and Administrator

	/* Locale Settings */
	public $offset = 'UTC';

	/* Session settings */
	public $lifetime = 600;                  // Session time
	public $session_handler = 'database';
	public $shared_session = '0';
	public $session_memcache_server_host = 'localhost';
	public $session_memcache_server_port = '11211';
	public $session_memcached_server_host = 'localhost';
	public $session_memcached_server_port = '11211';
	public $session_redis_server_host = 'localhost';
	public $session_redis_server_port = '6379';
	public $session_redis_server_db = '0';


	/* Mail Settings */
	public $mailonline = '1';
	public $mailer = 'smtp';
	public $mailfrom = 'admin@example.com';
	public $fromname = '%replace%';
	public $massmailoff = '0';
	public $replyto     = '';
	public $replytoname = '';
	public $sendmail    = '/usr/sbin/sendmail';
	public $smtpauth = 0;
	public $smtpuser = '';
	public $smtppass = '';
	public $smtphost = 'mailhog';
	public $smtpsecure = 'none';
	public $smtpport = 1025;

	/* Cache Settings */
	public $caching = '0';
	public $cachetime = '15';
	public $cache_handler = 'file';
	public $cache_platformprefix = '0';
	public $memcache_persist = '1';
	public $memcache_compress = '0';
	public $memcache_server_host = 'localhost';
	public $memcache_server_port = '11211';
	public $memcached_persist = '1';
	public $memcached_compress = '0';
	public $memcached_server_host = 'localhost';
	public $memcached_server_port = '11211';
	public $redis_persist = '1';
	public $redis_server_host = 'localhost';
	public $redis_server_port = '6379';
	public $redis_server_auth = '';
	public $redis_server_db = '0';

	/* Proxy Settings */
	public $proxy_enable = '0';
	public $proxy_host = '';
	public $proxy_port = '';
	public $proxy_user = '';
	public $proxy_pass = '';

	/* Debug Settings */
	public $debug = 1;
	public $debug_lang = '0';
	public $debug_lang_const = '1';

	/* Meta Settings */
	public $MetaDesc = 'Joomla! - the dynamic portal engine and content management system';
	public $MetaKeys = 'joomla, Joomla';
	public $MetaTitle = '1';
	public $MetaAuthor = '1';
	public $MetaVersion = '0';
	public $MetaRights = '';
	public $robots = '';
	public $sitename_pagetitles = '0';

	/* SEO Settings */
	public $sef = 1;
	public $sef_rewrite = 1;
	public $sef_suffix = '0';
	public $unicodeslugs = 1;

	/* Feed Settings */
	public $feed_limit = 10;
	public $feed_email = 'none';

	/* Cookie Settings */
	public $cookie_domain = '';
	public $cookie_path = '';

	/* Miscellaneous Settings */
	public $asset_id = '1';
}
