# Cache Hive

Cache Hive is a high-performance caching and optimization plugin for WordPress, designed for both single-site and complex multisite environments. It offers a comprehensive suite of tools including full-page caching, advanced object cache integration with Redis and Memcached, and a full suite of front-end optimizations.

## 🚀 Quick Start & Development

### Prerequisites
- PHP 7.4+
- WordPress 6.0+
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) (LTS version recommended)

### Installation & Build Process
1.  **Clone the Repository:**
    ```sh
    git clone https://github.com/realmranshuman/cache-hive.git
    cd cache-hive
    ```

2.  **Install PHP & JS Dependencies:**
    This command installs both Composer and NPM dependencies.
    ```sh
    composer install && npm install
    ```

3.  **Development:**
    Start the Vite development server. This provides hot-reloading for the React admin interface and proxies requests to your local WordPress installation.
    ```sh
    npm run dev
    ```
    > **Note:** You may need to edit `vite.config.js` to set the `proxy` to your local WordPress development URL.

4.  **Production Build:**
    Compile the React/TypeScript application for production. This creates optimized and minified assets in the `/build` directory.
    ```sh
    npm run build
    ```

5.  **Activate:**
    Activate the "Cache Hive" plugin in your WordPress admin dashboard.

---

## ✨ Features

### 📦 Caching Engine

-   **Full-Page Caching:** Enable or disable full-page caching for lightning-fast load times.
-   **Contextual Caching:** Granular control to cache content for logged-in users, commenters, the REST API, and mobile devices.
-   **Customizable TTLs:** Set specific Time-To-Live (TTL) durations for public pages, private (logged-in) user cache, the front page, RSS feeds, and REST API endpoints.
-   **Intelligent Auto-Purging:** Automatically purge relevant caches when content is published or updated. Configure rules for the entire site, front page, post type archives, term archives, and more.
-   **Serve Stale Cache:** Option to serve an expired (stale) version of a page to users while a fresh version is being generated in the background, ensuring high availability.
-   **Powerful Exclusions:** Prevent specific pages from being cached by excluding URIs (supports wildcards), query strings, cookies, or entire user roles.

### ⚙️ Object Cache Integration

Supercharge your WordPress backend with a persistent object cache. Cache Hive provides a robust drop-in that integrates seamlessly with Redis and Memcached.

#### Supported Backends
-   **Redis:** Connect via PhpRedis (recommended), Predis, or Credis.
-   **Memcached:** Connect via the PECL Memcached extension.

#### Connection & Authentication
-   **Standard Connections:** Configure Host, Port, Username, and Password directly from the UI.
-   **Advanced Connections:** Supports Unix Sockets, TCP, and secure **TLS/SSL** connections for Redis.
-   **Flexible Authentication:**
    -   **Redis:** Supports both password-only and ACL-based (Username + Password) authentication.
    -   **Memcached:** Supports SASL authentication (Username + Password).

#### Multisite-Aware Architecture
-   **Per-Site Isolation:** By default, each site in a multisite network has its own isolated object cache to prevent data conflicts.
-   **Global Groups:** Define specific cache groups (e.g., `users`, `usermeta`, `sites`) that should be shared across the entire network for maximum performance and reduced database load.
-   **Non-Persistent Groups:** Specify groups that should only be cached for a single page load (in-memory) and never written to the persistent backend.

### 🚀 `wp-config.php` Overrides (Advanced)

For production environments and maximum control, you can define constants in your `wp-config.php` file to lock down and override any setting from the UI. This is the recommended approach for secure and consistent configurations.

When a constant is defined, it takes ultimate priority and the corresponding field in the admin UI will be disabled.

**Example `wp-config.php` for a Secure Redis TLS Connection:**
```php
/** Cache Hive Object Cache Configuration **/
define( 'CACHE_HIVE_OBJECT_CACHE_METHOD', 'redis' );
define( 'CACHE_HIVE_OBJECT_CACHE_CLIENT', 'phpredis' );

// Connection Details
define( 'CACHE_HIVE_OBJECT_CACHE_HOST', 'tls://your-redis-host.com' );
define( 'CACHE_HIVE_OBJECT_CACHE_PORT', 6380 );
define( 'CACHE_HIVE_OBJECT_CACHE_TIMEOUT', 2.5 ); // Optional, in seconds
define( 'CACHE_HIVE_REDIS_DATABASE', 0 );

// Authentication (ACL)
define( 'CACHE_HIVE_REDIS_USERNAME', 'your-acl-user' );
define( 'CACHE_HIVE_REDIS_PASSWORD', 'your-secure-password' );

// Advanced TLS Options
define( 'CACHE_HIVE_REDIS_TLS_VERIFY_PEER', true ); // Recommended for production
define( 'CACHE_HIVE_REDIS_TLS_CA_CERT', '/path/to/your/ca.crt' );
// define( 'CACHE_HIVE_REDIS_TLS_LOCAL_CERT', '/path/to/your/client.crt' ); // If using client certs
// define( 'CACHE_HIVE_REDIS_TLS_LOCAL_PK', '/path/to/your/client.key' );   // If using client certs
```

**Full List of Override Constants:**
```php
// --- General Settings ---
define( 'CACHE_HIVE_OBJECT_CACHE_METHOD', 'redis' );      // 'redis' or 'memcached'
define( 'CACHE_HIVE_OBJECT_CACHE_CLIENT', 'phpredis' );   // 'phpredis', 'predis', 'credis'
define( 'CACHE_HIVE_OBJECT_CACHE_HOST', '127.0.0.1' );
define( 'CACHE_HIVE_OBJECT_CACHE_PORT', 6379 );
define( 'CACHE_HIVE_OBJECT_CACHE_LIFETIME', 3600 );
define( 'CACHE_HIVE_OBJECT_CACHE_TIMEOUT', 2.0 );
define( 'CACHE_HIVE_OBJECT_CACHE_PERSISTENT', true );

// --- Redis Specific ---
define( 'CACHE_HIVE_REDIS_DATABASE', 0 );
define( 'CACHE_HIVE_REDIS_USERNAME', '' );
define( 'CACHE_HIVE_REDIS_PASSWORD', '' );

// --- Memcached Specific ---
define( 'CACHE_HIVE_MEMCACHED_USERNAME', '' );
define( 'CACHE_HIVE_MEMCACHED_PASSWORD', '' );

// --- Redis TLS Specific ---
define( 'CACHE_HIVE_REDIS_TLS_CA_CERT', '' );      // Path to CA certificate file
define( 'CACHE_HIVE_REDIS_TLS_LOCAL_CERT', '' ); // Path to client certificate file
define( 'CACHE_HIVE_REDIS_TLS_LOCAL_PK', '' );    // Path to client private key file
define( 'CACHE_HIVE_REDIS_TLS_PASSPHRASE', '' );  // Passphrase for the client private key
define( 'CACHE_HIVE_REDIS_TLS_VERIFY_PEER', false ); // Set to true in production
```

### ⚡️ Front-End Optimization

-   **CSS:** Minify, combine, load asynchronously, and optimize font delivery.
-   **JavaScript:** Minify, combine, and choose between `defer` or `delay` execution strategies to eliminate render-blocking resources.
-   **HTML:** Minify the final HTML output, remove unnecessary tags and comments, and pre-resolve DNS lookups for external resources.
-   **Media:** Lazy-load images and iframes, automatically add missing image dimensions, and optimize images on upload for a smaller footprint.

### ☁️ Cloudflare Integration

-   Connect directly to your Cloudflare account via API Token or Key.
-   Toggle Development Mode on or off with a single click.
-   Purge the entire Cloudflare cache directly from your WordPress dashboard.
-   Automatically syncs plugin cache purging actions with Cloudflare's cache.
