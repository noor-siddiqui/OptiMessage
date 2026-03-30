## 2024-05-24 - N+1 Queries with wc_get_orders
**Learning:** Unlike WP_User_Query, WooCommerce's `wc_get_orders` does not automatically prime the user meta cache. Fetching user metadata for a group of customers (e.g., within an orders loop checking for consent) results in an N+1 query issue.
**Action:** Pre-populate the cache using `update_meta_cache( 'user', $customer_ids )` prior to the loop.
