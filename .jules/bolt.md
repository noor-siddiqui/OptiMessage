## 2024-05-18 - WooCommerce Order Loops N+1 Query Anti-pattern
**Learning:** Looping through `wc_get_orders()` and checking user metadata (e.g., for SMS consent via `get_user_meta`) causes a severe N+1 query bottleneck because WooCommerce doesn't automatically pre-load user meta for the associated `customer_id`s.
**Action:** Always collect `customer_id`s from the orders first and prime the WordPress user meta cache using `update_meta_cache( 'user', array_unique( $customer_ids ) )` before starting the main loop.
## 2024-05-20 - Custom $wpdb Queries & WordPress Core N+1 Query Anti-pattern
**Learning:** Iterating over custom `$wpdb` results in WordPress and calling core functions like `get_edit_user_link()` that internally fetch `WP_User` objects causes an N+1 query bottleneck because `WP_User_Query` wasn't used.
**Action:** Always collect user IDs from the `$wpdb` results first and prime the WordPress user cache using `cache_users( array_unique( $user_ids ) )` before iterating and using core user functions.
