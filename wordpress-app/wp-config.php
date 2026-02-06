<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', getenv('WORDPRESS_DB_NAME') ?: 'wordpress' );

/** Database username */
define( 'DB_USER', getenv('WORDPRESS_DB_USER') ?: 'wordpress' );

/** Database password */
define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: 'secret' );

/** Database hostname */
define( 'DB_HOST', getenv('WORDPRESS_DB_HOST') ?: 'database' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY', getenv('WORDPRESS_AUTH_KEY') ?: '?DWQM!Q>[_).)ikpPKIlCODxTRn+=LT}tARwthgSB{)p!gtL=y2xo8%@HJ#oODt&' );
define( 'SECURE_AUTH_KEY', getenv('WORDPRESS_SECURE_AUTH_KEY') ?: '(t>L92{NGbr>=-CPg%bS+}{p_l0emzT#<ccu)!=E0iHPsO.gpbRWph[(_K>R|:]:' );
define( 'LOGGED_IN_KEY', getenv('WORDPRESS_LOGGED_IN_KEY') ?: '(?@DX*9^&kgMB-0L1]7v;0H@gs0D8_NjB0,LG)+Rd:;wOtluwB=ybhq#m%OLN0D2' );
define( 'NONCE_KEY', getenv('WORDPRESS_NONCE_KEY') ?: 'iq-?ap@{q<p[pYh1hco([75wyH;ENufB&cZ7vfFv=c<+<q*7(o@@B}c+T[%t#P6o' );
define( 'AUTH_SALT', getenv('WORDPRESS_AUTH_SALT') ?: 'n!oQu_bHRt[Ymm;|1D>!RaIH<Q+qQaA0c,o@-6A7r:>kTq[4;yFG?Ei2k74DtX%b' );
define( 'SECURE_AUTH_SALT', getenv('WORDPRESS_SECURE_AUTH_SALT') ?: '[9%|n!}J.37s5MFjfq6gBmVJfeLEw=2HYZmGNC]Wm{MF24[R^Ur&XK>7.m#APOC8' );
define( 'LOGGED_IN_SALT', getenv('WORDPRESS_LOGGED_IN_SALT') ?: '{mzFQ2<rqc{r4DiK:3ZldQ{P6q4&lDgCG)mTiS(Vm1Z6.}>cVodRc5M#=tQXBiQK' );
define( 'NONCE_SALT', getenv('WORDPRESS_NONCE_SALT') ?: '-Lz.;Vnc#MLLp?;0ueyDyn?9uz67zDA!QjLn<P5K]tFZXgNGG.ZJ2[@]?r@FMES8' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', filter_var(getenv('WORDPRESS_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN) );

/* Add any custom values between this line and the "stop editing" line. */




/**
 * Docker Environment Configurations
 */

// Reverse Proxy / Load Balancer SSL Support (Traefik)
// This fixes redirect loops when behind a reverse proxy handling SSL
if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
    $_SERVER['HTTPS'] = 'on';
}

// WordPress URLs - from environment for Docker flexibility
if ( getenv('WORDPRESS_HOME') ) {
    define( 'WP_HOME', getenv('WORDPRESS_HOME') );
}
if ( getenv('WORDPRESS_SITEURL') ) {
    define( 'WP_SITEURL', getenv('WORDPRESS_SITEURL') );
}

// Debug logging (only when WP_DEBUG is true)
define( 'WP_DEBUG_LOG', filter_var(getenv('WORDPRESS_DEBUG_LOG') ?: false, FILTER_VALIDATE_BOOLEAN) );
define( 'WP_DEBUG_DISPLAY', filter_var(getenv('WORDPRESS_DEBUG_DISPLAY') ?: false, FILTER_VALIDATE_BOOLEAN) );

// Filesystem method - use direct for Docker containers
define( 'FS_METHOD', 'direct' );

// Disable file editing in admin (security best practice for containers)
define( 'DISALLOW_FILE_EDIT', true );

// Force SSL for admin when behind proxy
define( 'FORCE_SSL_ADMIN', true );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
