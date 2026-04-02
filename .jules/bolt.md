## 2024-05-18 - WooCommerce Order Loops N+1 Query Anti-pattern
**Learning:** Looping through `wc_get_orders()` and checking user metadata (e.g., for SMS consent via `get_user_meta`) causes a severe N+1 query bottleneck because WooCommerce doesn't automatically pre-load user meta for the associated `customer_id`s.
**Action:** Always collect `customer_id`s from the orders first and prime the WordPress user meta cache using `update_meta_cache( 'user', array_unique( $customer_ids ) )` before starting the main loop.
## 2024-05-19 - WP Core functions and N+1 queries in custom tables
**Learning:** Functions like `get_edit_user_link( $user_id )` internally fetch the WP_User object. When looping through results from a custom table via `$wpdb->get_results()`, calling these functions for each row causes an N+1 query bottleneck because the user objects aren't pre-loaded.
**Action:** Always extract unique user IDs from custom `$wpdb` query results and prime the object cache using `cache_users( array_unique( $user_ids ) )` before entering the display loop.
