## 2024-05-18 - WooCommerce Order Loops N+1 Query Anti-pattern
**Learning:** Looping through `wc_get_orders()` and checking user metadata (e.g., for SMS consent via `get_user_meta`) causes a severe N+1 query bottleneck because WooCommerce doesn't automatically pre-load user meta for the associated `customer_id`s.
**Action:** Always collect `customer_id`s from the orders first and prime the WordPress user meta cache using `update_meta_cache( 'user', array_unique( $customer_ids ) )` before starting the main loop.

## 2024-05-19 - Custom $wpdb Results N+1 Query Anti-pattern with Core Functions
**Learning:** Iterating over raw `$wpdb->get_results()` and passing `user_id`s to WordPress core functions like `get_edit_user_link()` causes an N+1 query bottleneck. Because the custom query bypasses WordPress caching mechanisms, the core function triggers individual `get_userdata()` database calls for every iteration.
**Action:** When displaying lists derived from custom `$wpdb` queries, pre-load the user and meta cache by extracting unique `user_id`s and calling `cache_users( array_unique( $user_ids ) )` before beginning the iteration loop.
