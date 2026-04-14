## 2024-05-18 - WooCommerce Order Loops N+1 Query Anti-pattern
**Learning:** Looping through `wc_get_orders()` and checking user metadata (e.g., for SMS consent via `get_user_meta`) causes a severe N+1 query bottleneck because WooCommerce doesn't automatically pre-load user meta for the associated `customer_id`s.
**Action:** Always collect `customer_id`s from the orders first and prime the WordPress user meta cache using `update_meta_cache( 'user', array_unique( $customer_ids ) )` before starting the main loop.

## 2024-05-18 - WordPress Custom Query N+1 Query Anti-pattern
**Learning:** Looping through custom `$wpdb` results that include a `user_id` and then using core functions like `get_edit_user_link()` causes a severe N+1 query bottleneck because WordPress doesn't automatically pre-load the user object cache for custom queries.
**Action:** Always collect `user_id`s from the results first and prime the WordPress user cache using `cache_users( array_unique( $user_ids ) )` before starting the main loop.

## 2024-05-18 - Synchronous API calls in checkout
**Learning:** The OptiMessage plugin executed a synchronous API call to Twilio (`OM_Twilio_API::lookup_phone`) during the critical path of the WooCommerce checkout process. This caused checkout requests to block until Twilio responded, adding significant network latency directly to the user's wait time.
**Action:** Offload all non-critical external API requests to background jobs. Use WooCommerce Action Scheduler (`as_enqueue_async_action`) where available for robust job queueing, with a fallback to `wp_schedule_single_event` (WP Cron) when it is not.

## 2024-05-18 - Synchronous API calls in WP_List_Table loops
**Learning:** The OptiMessage plugin executed a synchronous API call to Twilio (`OM_Twilio_API::lookup_phone`) within the `column_default` rendering method of `WP_List_Table` implementations (`OM_Subscribers_List_Table` and `OM_Guest_Customers_List_Table`). This resulted in a severe N+1 API call bottleneck during page rendering, causing significant delays and potential timeouts when loading lists of subscribers or customers with unvalidated phone numbers.
**Action:** Decouple external API lookups from repetitive rendering structures. Use asynchronous background jobs (e.g., WooCommerce Action Scheduler or WP Cron) queued within the loop. Ensure you check for already scheduled actions (e.g., `as_has_scheduled_action` or `wp_next_scheduled`) to prevent duplicate job creation and database bloat.
