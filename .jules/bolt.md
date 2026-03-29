## 2024-03-29 - WooCommerce Order User Meta N+1
**Learning:** Unlike WP_User_Query, WooCommerce's `wc_get_orders` does not automatically prime the user meta cache. Iterating over a list of orders and querying user consent (via `get_user_meta`) creates an N+1 query problem, slowing down bulk SMS filtering significantly.
**Action:** When fetching user metadata for a group of customers from WooCommerce orders, pre-populate the cache using `update_meta_cache( 'user', $customer_ids )` prior to the loop.
