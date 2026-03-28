## 2024-05-24 - N+1 query problems fetching user meta in bulk filters
**Learning:** Iterating over an array of `WC_Order` returned by `wc_get_orders( array( 'limit' => -1 ) )` and manually calling `get_user_meta` on each iteration introduces a classic N+1 query problem, since WooCommerce does not automatically pre-load the user meta cache for the underlying customers.
**Action:** Use `update_meta_cache( 'user', array_unique( $customer_ids ) );` prior to any iteration loop to batch prime the metadata cache in a single SQL query, dramatically speeding up execution time for thousands of orders.
