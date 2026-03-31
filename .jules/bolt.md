## 2024-05-24 - N+1 query vulnerability with wc_get_orders
**Learning:** `wc_get_orders()` does not automatically prime the user meta cache like `WP_User_Query` does. When fetching orders and then fetching user meta within a loop, an N+1 query issue occurs.
**Action:** Always extract the unique customer IDs before the main loop and pre-populate the cache using `update_meta_cache( 'user', $customer_ids );` to prevent thousands of redundant database queries.
