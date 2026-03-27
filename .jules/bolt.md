## 2024-03-27 - [Fix N+1 Meta Query]
**Learning:** Calling `get_user_meta` inside a loop iterating over WooCommerce orders for thousands of customers causes an N+1 query problem that severely degraded performance.
**Action:** Always map the IDs into an array and use `update_meta_cache('user', $user_ids)` before looping to pre-populate the metadata cache in bulk.
