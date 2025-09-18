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

2.  **Development Environment Setup:**
    Use the Composer script to prepare all PHP dependencies, scope vendor libraries, and build JavaScript assets. This will also create all required directories and optimize the autoloader.
    ```sh
    composer dev
    ```
    This script will:
    - Prepare all required directories
    - Install Composer dependencies
    - Scope PHP dependencies with php-scoper
    - Optimize the Composer autoloader
    - Install NPM dependencies and build JS assets

    > **Note:** On Windows, use:
    > ```sh
    > composer windows-dev
    > ```

3.  **Development (Hot Reload):**
    Start the Vite development server for the React admin interface:
    ```sh
    npm run dev
    ```
    > **Note:** You will need to edit `vite.config.js` to set the `proxy` to your local WordPress development URL.

4.  **Production Build:**
    Use the Composer script to assemble a production-ready plugin package in `dist/cache-hive`:
    ```sh
    composer build
    ```
    This script will:
    - Run the full dev script
    - Assemble the production package in `dist/cache-hive`
    - Copy only the necessary files (excluding dev files)
    - Generate a production autoloader

    > **Note:** On Windows, use:
    > ```sh
    > composer windows-build
    > ```

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

For production environments and maximum control, you can define constants in your `wp-config.php` file to lock down and override any setting from the UI. This is the recommended approach for secure and consistent configurations. When a constant is defined, it takes ultimate priority and the corresponding field in the admin UI will be disabled.

#### Example 1: Secure TLS to a Managed Redis Service (e.g., AWS ElastiCache, DigitalOcean)

This is the most common production scenario. The Redis server has a certificate from a trusted public CA like Let's Encrypt.

```php
/** Cache Hive: Secure Redis TLS Connection **/
define( 'CACHE_HIVE_OBJECT_CACHE_METHOD', 'redis' );
define( 'CACHE_HIVE_OBJECT_CACHE_CLIENT', 'phpredis' );

// The 'tls://' prefix is crucial. The hostname MUST match the certificate's name.
define( 'CACHE_HIVE_OBJECT_CACHE_HOST', 'tls://redis.your-domain.com' );
define( 'CACHE_HIVE_OBJECT_CACHE_PORT', 6380 );

// Authentication (ACL Example)
define( 'CACHE_HIVE_REDIS_USERNAME', 'your-acl-user' );
define( 'CACHE_HIVE_REDIS_PASSWORD', 'your-secure-password' );

// Enable peer verification for security. No CA_CERT is needed because
// the certificate is signed by a publicly trusted authority.
define( 'CACHE_HIVE_REDIS_TLS_VERIFY_PEER', true );
```

#### Example 2: Secure TLS to a Server with a Private or Self-Signed Certificate

Use this method if your Redis server uses a certificate issued by an internal, private Certificate Authority (CA), or if it's a self-signed certificate.

```php
/** Cache Hive: Redis with Private/Self-Signed TLS Certificate **/
define( 'CACHE_HIVE_OBJECT_CACHE_HOST', 'tls://internal-redis.my-company.lan' );
define( 'CACHE_HIVE_OBJECT_CACHE_PORT', 6380 );
define( 'CACHE_HIVE_REDIS_PASSWORD', 'your-secure-password' );

// We must still verify the peer for security.
define( 'CACHE_HIVE_REDIS_TLS_VERIFY_PEER', true );

// CRITICAL: Provide the path to the public certificate of the CA that signed
// the Redis server's certificate. This tells PHP to trust this specific CA.
define( 'CACHE_HIVE_REDIS_TLS_CA_CERT', '/path/to/your/private-ca.pem' );
```
> **When do I need `CACHE_HIVE_REDIS_TLS_CA_CERT`?**
> You only need this constant if your PHP environment does not inherently trust the authority that signed your Redis server's SSL certificate. For standard Let's Encrypt or other major public CAs, this is almost never needed. You **must** use it for private or self-signed CAs to establish a chain of trust.

---

#### Full List of Override Constants

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
define( 'CACHE_HIVE_REDIS_TLS_VERIFY_PEER', false ); // Set to true in production for security
define( 'CACHE_HIVE_REDIS_TLS_CA_CERT', '' );      // Path to your custom/private CA certificate file (.pem or .crt)
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
