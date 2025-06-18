# Cache Hive WordPress Plugin

## Setup & Development

1. **Install dependencies:**
   ```sh
   npm install
   ```

2. **Start Vite dev server (with proxy for WP):**
   ```sh
   npm run dev
   ```
   - Edit `vite.config.js` if you need to adjust proxy settings for your local WP site.

3. **Build for production:**
   ```sh
   npm run build
   ```
   - Outputs to `build/` at the plugin root.

4. **Activate the plugin in WordPress admin.**

## File Structure

- `cache-hive.php` — Main plugin file, registers admin menu and enqueues assets
- `src/` — React/TypeScript source code
- `build/` — Compiled assets (output by Vite)

## Styling

- Tailwind CSS v3 is preconfigured. Edit `tailwind.config.js` and use Tailwind classes in your components.

## Features

### Caching
- Enable/disable full-page caching
- Cache for logged-in users, commenters, REST API, and mobile devices
- Custom mobile user agent list
- Default TTL (Time To Live) for public, private, front page, feed, and REST cache
- Auto purge rules for publish/update (all pages, front page, home, pages, author archive, post type archive, yearly/monthly/daily/term archives)
- Serve stale cache option
- Purge all on upgrade
- Exclusions for URIs, query strings, cookies, and user roles
- Object cache support (Memcached/Redis)
  - Host, port, lifetime, username, password
  - Global groups and no-cache groups
  - Persistent connection option
- Browser cache toggle and TTL

### Page Optimization
- **CSS Optimization**
  - Minify CSS
  - Combine CSS files
  - Combine external and inline CSS
  - Font optimization (default/swap)
  - Exclude specific files/paths from minify/combine
- **JS Optimization**
  - Minify JS
  - Combine JS files
  - Combine external and inline JS
  - Deferred/delayed JS loading (off/deferred/delayed)
  - Exclude specific files/paths from minify/combine/defer
- **HTML Optimization**
  - Minify HTML
  - DNS prefetch and preconnect (manual and automatic)
  - Load Google Fonts asynchronously
  - Keep or remove HTML comments
  - Remove WordPress emoji scripts
  - Remove `<noscript>` tags
- **Media Optimization**
  - Lazyload images and iframes
  - Add missing image sizes
  - Responsive image placeholders
  - Optimize image uploads (quality setting)
  - Automatically resize uploads (width/height)

### Cloudflare Integration
- Enable/disable Cloudflare API integration
- Store and manage API key/token, email, and domain
- Toggle Cloudflare development mode (bypass cache)
- Purge all Cloudflare cache with one click
- Cloudflare cache settings automatically synced with plugin cache settings

---

**Happy coding!**
