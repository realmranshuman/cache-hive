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

        // Register custom purge hooks from settings
        $custom_hooks = Cache_Hive_Settings::get('customPurgeHooks');
        if ( !empty($custom_hooks) ) {
            $hooks = array_filter(array_map('trim', explode("\n", $custom_hooks)));
            foreach ( $hooks as $hook ) {
                add_action( $hook, array( __CLASS__, 'purge_all' ) );
            }
        }
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
        // Only purge entire site if the setting is strictly true
        if ( Cache_Hive_Settings::get('autoPurgeEntireSite', false ) === true ) {
            self::purge_all();
            Cache_Hive_Disk::purge_all_private(); // Purge private cache as well
            return;
        }

        // If not purging all, purge specific related content
        if ( $post ) {
            // Only purge the specific post's URL for single posts (not pages)
            if ( $post->post_type === 'post' ) {
                $url_to_purge = get_permalink( $post );
                if ( $url_to_purge ) {
                    Cache_Hive_Disk::purge_url( $url_to_purge );
                    Cache_Hive_Disk::purge_private_url( $url_to_purge ); // Purge private cache for this post
                }
            }
            // For pages, purge homepage/frontpage as a proxy for all pages
            if ( $post->post_type === 'page' && Cache_Hive_Settings::get('autoPurgePages') ) {
                Cache_Hive_Disk::purge_url( home_url( '/' ) );
                Cache_Hive_Disk::purge_private_url( home_url( '/' ) );
            }
        }
        // Purge Front Page / Home
        if ( Cache_Hive_Settings::get('autoPurgeFrontPage') || Cache_Hive_Settings::get('autoPurgeHomePage') ) {
            Cache_Hive_Disk::purge_url( home_url( '/' ) );
            Cache_Hive_Disk::purge_private_url( home_url( '/' ) );
        }
        // Purge Author Archive
        if ( $post && Cache_Hive_Settings::get('autoPurgeAuthorArchive') ) {
            $author_url = get_author_posts_url( $post->post_author );
            if ( $author_url ) {
                Cache_Hive_Disk::purge_url( $author_url );
                Cache_Hive_Disk::purge_private_url( $author_url );
            }
        }
        // Purge Post Type Archive
        if ( $post && Cache_Hive_Settings::get('autoPurgePostTypeArchive') ) {
            $post_type_obj = get_post_type_object( $post->post_type );
            if ( $post_type_obj && !empty($post_type_obj->has_archive) ) {
                $archive_url = get_post_type_archive_link( $post->post_type );
                if ( $archive_url ) {
                    Cache_Hive_Disk::purge_url( $archive_url );
                    Cache_Hive_Disk::purge_private_url( $archive_url );
                }
            }
        }
        // Purge Yearly Archive
        if ( $post && Cache_Hive_Settings::get('autoPurgeYearlyArchive') ) {
            $year = get_the_date( 'Y', $post );
            if ( $year ) {
                $year_url = get_year_link( $year );
                if ( $year_url ) {
                    Cache_Hive_Disk::purge_url( $year_url );
                    Cache_Hive_Disk::purge_private_url( $year_url );
                }
            }
        }
        // Purge Monthly Archive
        if ( $post && Cache_Hive_Settings::get('autoPurgeMonthlyArchive') ) {
            $year = get_the_date( 'Y', $post );
            $month = get_the_date( 'm', $post );
            if ( $year && $month ) {
                $month_url = get_month_link( $year, $month );
                if ( $month_url ) {
                    Cache_Hive_Disk::purge_url( $month_url );
                    Cache_Hive_Disk::purge_private_url( $month_url );
                }
            }
        }
        // Purge Daily Archive
        if ( $post && Cache_Hive_Settings::get('autoPurgeDailyArchive') ) {
            $year = get_the_date( 'Y', $post );
            $month = get_the_date( 'm', $post );
            $day = get_the_date( 'd', $post );
            if ( $year && $month && $day ) {
                $day_url = get_day_link( $year, $month, $day );
                if ( $day_url ) {
                    Cache_Hive_Disk::purge_url( $day_url );
                    Cache_Hive_Disk::purge_private_url( $day_url );
                }
            }
        }
        // Purge Term Archive
        if ( $post && Cache_Hive_Settings::get('autoPurgeTermArchive') ) {
            $taxonomies = get_object_taxonomies( $post->post_type );
            foreach ( $taxonomies as $taxonomy ) {
                $terms = get_the_terms( $post, $taxonomy );
                if ( $terms && !is_wp_error($terms) ) {
                    foreach ( $terms as $term ) {
                        $term_url = get_term_link( $term );
                        if ( !is_wp_error($term_url) ) {
                            Cache_Hive_Disk::purge_url( $term_url );
                            Cache_Hive_Disk::purge_private_url( $term_url );
                        }
                    }
                }
            }
        }
    }
}