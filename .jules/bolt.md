## 2024-05-18 - WooCommerce Order Loops N+1 Query Anti-pattern
**Learning:** Looping through `wc_get_orders()` and checking user metadata (e.g., for SMS consent via `get_user_meta`) causes a severe N+1 query bottleneck because WooCommerce doesn't automatically pre-load user meta for the associated `customer_id`s.
**Action:** Always collect `customer_id`s from the orders first and prime the WordPress user meta cache using `update_meta_cache( 'user', array_unique( $customer_ids ) )` before starting the main loop.

## 2024-05-18 - WordPress Custom Query N+1 Query Anti-pattern
**Learning:** Looping through custom `$wpdb` results that include a `user_id` and then using core functions like `get_edit_user_link()` causes a severe N+1 query bottleneck because WordPress doesn't automatically pre-load the user object cache for custom queries.
**Action:** Always collect `user_id`s from the results first and prime the WordPress user cache using `cache_users( array_unique( $user_ids ) )` before starting the main loop.

## 2024-05-18 - Synchronous API calls in checkout
**Learning:** The OptiMessage plugin executed a synchronous API call to Twilio (`OM_Twilio_API::lookup_phone`) during the critical path of the WooCommerce checkout process. This caused checkout requests to block until Twilio responded, adding significant network latency directly to the user's wait time.
**Action:** Offload all non-critical external API requests to background jobs. Use WooCommerce Action Scheduler (`as_enqueue_async_action`) where available for robust job queueing, with a fallback to `wp_schedule_single_event` (WP Cron) when it is not.
## 2024-05-18 - Missing hook handler for guest customers
**Learning:** Adding a hook for action scheduler like  will not work unless a handler function is implemented that process the job, or the jobs will fire into the void and data will not be processed.
**Action:** When creating new async jobs, ensure the corresponding handler logic is implemented.
## 2024-05-18 - Missing hook handler for guest customers
**Learning:** Adding a hook for action scheduler like `om_async_twilio_lookup_job` will not work unless a handler function is implemented that process the job, or the jobs will fire into the void and data will not be processed.
**Action:** When creating new async jobs, ensure the corresponding handler logic is implemented.
## 2024-05-18 - Synchronous API calls in CSV setup
**Learning:** Parsing CSV numbers and hitting the Twilio Lookup API sequentially (using `OM_Twilio_API::lookup_phone`) blocks the PHP process unnecessarily, leading to timeouts on large CSV uploads.
**Action:** Instead of calling `lookup_phone` inside a foreach loop, always use `OM_Twilio_API::lookup_phone_batch()` by collecting requests into an array and chunking them. This executes requests concurrently using `curl_multi_init`.
