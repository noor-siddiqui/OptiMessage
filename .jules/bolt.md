## 2024-05-18 - WooCommerce Order Loops N+1 Query Anti-pattern
**Learning:** Looping through `wc_get_orders()` and checking user metadata (e.g., for SMS consent via `get_user_meta`) causes a severe N+1 query bottleneck because WooCommerce doesn't automatically pre-load user meta for the associated `customer_id`s.
**Action:** Always collect `customer_id`s from the orders first and prime the WordPress user meta cache using `update_meta_cache( 'user', array_unique( $customer_ids ) )` before starting the main loop.

## 2024-05-18 - Synchronous API calls in checkout
**Learning:** The OptiMessage plugin executed a synchronous API call to Twilio (`OM_Twilio_API::lookup_phone`) during the critical path of the WooCommerce checkout process. This caused checkout requests to block until Twilio responded, adding significant network latency directly to the user's wait time.
**Action:** Offload all non-critical external API requests to background jobs. Use WooCommerce Action Scheduler (`as_enqueue_async_action`) where available for robust job queueing, with a fallback to `wp_schedule_single_event` (WP Cron) when it is not.
