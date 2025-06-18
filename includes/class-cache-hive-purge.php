<?php
/**
 * Handles all cache purging operations for Cache Hive.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Hive_Purge {
    
    /**
     * Initializes the purge hooks.
     * @since 1.0.0
     */
    public static function init() {
        // Purge on post/page/cpt updates
        add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 10, 2 );
        add_action( 'wp_trash_post', array( __CLASS__, 'on_trash_post' ) );
        
        // Purge on comment changes
        add_action( 'comment_post', array( __CLASS__, 'on_comment_change' ) );
        add_action( 'edit_comment', array( __CLASS__, 'on_comment_change' ) );
        add_action( 'transition_comment_status', array( __CLASS__, 'on_comment_change' ) );

        // Purge on theme/plugin/core updates
        add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );
    }

    /**
     * Purges the entire cache for all sites.
     * This also purges Cloudflare if the integration is enabled.
     *
     * @since 1.0.0
     */
    public static function purge_all() {
        Cache_Hive_Disk::purge_all();
        
        // Also purge integrated services
        if ( Cache_Hive_Settings::get('cloudflare_enabled') ) {
            Cache_Hive_Cloudflare::purge_all();
        }
    }

    /**
     * Fired when a post is saved or updated.
     *
     * @since 1.0.0
     * @param int     $post_id The post ID.
     * @param WP_Post $post    The post object.
     */
    public static function on_save_post( $post_id, $post ) {
        // Ignore revisions and autosaves
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        // Only purge for published posts
        if ( 'publish' !== $post->post_status ) {
            return;
        }

        self::run_purge_rules( $post );
    }

    /**
     * Fired when a post is trashed.
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     */
    public static function on_trash_post( $post_id ) {
        $post = get_post( $post_id );
        if ( $post ) {
            self::run_purge_rules( $post );
        }
    }
    
    /**
     * Fired on any comment status change.
     *
     * @since 1.0.0
     */
    public static function on_comment_change() {
        // For simplicity, we can treat any comment change as a reason to purge.
        // A more complex implementation could check settings.
        self::run_purge_rules();
    }
    
    /**
     * Fired when WordPress, a plugin, or a theme is upgraded.
     *
     * @since 1.0.0
     */
    public static function on_upgrade( $upgrader, $options ) {
        if ( Cache_Hive_Settings::get('purge_on_upgrade') && $options['action'] === 'update' ) {
            self::purge_all();
        }
    }

    /**
     * Central logic to decide what to purge based on settings.
     *
     * @since 1.0.0
     * @param WP_Post|null $post The post object, if available.
     */
    private static function run_purge_rules( $post = null ) {
        if ( Cache_Hive_Settings::get('purge_on_update_all') ) {
            self::purge_all();
            return;
        }

        // If not purging all, purge specific related content
        if ( $post ) {
            // Always purge the specific post's URL
            $url_to_purge = get_permalink( $post );
            if ( $url_to_purge ) {
                Cache_Hive_Disk::purge_url( $url_to_purge );
            }
        }
        
        // Purge Front Page / Home
        if ( Cache_Hive_Settings::get('purge_on_update_front_page') || Cache_Hive_Settings::get('purge_on_update_home') ) {
            Cache_Hive_Disk::purge_url( home_url( '/' ) );
        }

        // Purge All Pages (if a page was updated)
        if ( $post && $post->post_type === 'page' && Cache_Hive_Settings::get('purge_on_update_pages') ) {
            // This is a simple interpretation. A true "purge all pages" would require
            // iterating through all page URLs, which can be slow. A common approach is
            // to just purge the homepage/frontpage as a proxy.
            Cache_Hive_Disk::purge_url( home_url( '/' ) );
        }

        // The logic for purging archives (author, date, term, etc.) can get complex.
        // For now, a simple approach is to purge the homepage, which often reflects recent content.
        // A more advanced implementation would find the specific archive URLs related to the post.
        if ( Cache_Hive_Settings::get('purge_on_update_author_archive') || 
             Cache_Hive_Settings::get('purge_on_update_post_type_archive') ||
             Cache_Hive_Settings::get('purge_on_update_date_archive') ||
             Cache_Hive_Settings::get('purge_on_update_term_archive')
        ) {
            // As a robust starting point, purging the home page is a safe bet.
            Cache_Hive_Disk::purge_url( home_url( '/' ) );
        }
    }
}