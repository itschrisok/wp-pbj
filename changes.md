
## Scope & File Map

This section outlines the primary responsibilities and roles of each core file in the voting system:

- **`inc/class-pb-voting-service.php`**: Backend voting service. Handles registration of meta fields, REST API endpoints, admin page logic, participant selection, vote tallying, round state, and result snapshots. Interacts with the database and PB_Voting_Sort for ranking.
- **`inc/class-pb-voting-sort.php`**: Sorting/ranking logic. Contains algorithms for sorting participants (recent, highest, lowest, tiebreaker) and (future) rank assignment.
- **`single-voting_round.php`**: Frontend template fowp_nonce_field('pb_voting_round_meta', 'pb_voting_round_nonce');r displaying a single voting round. Handles loading participant data, integrating REST API endpoints, rendering voting UI, and live updating via polling/SSE.
- **Other files**: Theme templates, JavaScript for frontend interactivity, and additional admin UI files as necessary.




# Proposed Improvements: Admin Panel, Frontend Logic, and Backend (Voting Service)

This document outlines a structured plan for enhancing the voting system, with recommendations organized by layer and phased steps. All improvements are designed to build upon the existing class structure (`PB_Voting_Service`, `PB_Voting_Sort`, `single-voting_round.php`) rather than replacing them.

---

## 1. Admin Panel Improvements

### Phase 1: Usability Enhancements
1. **Improve Voting Round Overview**
    - Add live status badges (Active, Upcoming, Expired) with color coding.
    - Show real-time vote counts and rankings for each round (using AJAX or REST).
    - Enable quick access to round results and "End Now" actions.
2. **Bulk Actions**
    - Add bulk actions to end multiple rounds or archive results.
3. **Participant Management**
    - Allow inline editing of participant lists for custom rounds.
    - Display participant vote counts in the admin meta box.

---

### Admin Panel Redesign & Logic Changes

The Admin Panel for voting rounds should be redesigned with the following logic and UI improvements:

- **Always Show "Selected Participants" and "Round Reference":**
    - The meta box should always display the current list of selected participants for the round, as well as the round's unique reference ID.
- **Start/End Fields as Source of Truth:**
    - The "Start" and "End" datetime fields are the authoritative source for round timing. "Duration" is calculated from these and shown as a display-only value (not directly editable).
- **Multi-Selects Use Checkbox-Style UI:**
    - All multi-select fields (such as categories or manual participants) use a checkbox or checkbox-style UI for clearer selection of multiple items.
- **Display "Total Nominees" or "Total Places":**
    - For nomination rounds, show a "Total Nominees" count.
    - For finals and custom rounds, display a "Total Places" (number of participant slots).
- **Field Visibility Adjusts by Round Type:**
    - Only fields relevant to the selected round type are visible, for a clean and intuitive UX:
        - *Nomination*: Show category selection and "Total Nominees."
        - *Final*: Show source round selector and "Total Places."
        - *Custom*: Show manual participant selector and allow category-based or manual selection.
- **Dynamic Field Behavior:**
    - Fields appear/disappear or enable/disable based on round type or related checkboxes (e.g., enabling manual override).
- **Participant Logic by Type:**
    - *Nomination*: All participants in the chosen categories are included.
    - *Final*: Top nominees from the selected source round are included.
    - *Custom*: Admin can select participants manually or by category.
- **Result Caching and Display:**
    - The panel displays cached participants and updates automatically after changes.

---

---

## 2. Frontend Logic Improvements

### Phase 1: Live Updates and User Experience
1. **Implement Server-Sent Events (SSE) for Live Vote Updates**
    - Replace or supplement periodic polling (`setInterval`) in `single-voting_round.php` with an SSE connection to push vote/rank changes instantly.
    - Fallback to polling if SSE is not supported by the browser.
    - Technical Note: Use a lightweight PHP endpoint to stream JSON updates to connected clients. Integrate with REST vote update logic.
2. **Optimistic UI Updates**
    - Immediately update the UI on vote button press, then reconcile with backend confirmation.
3. **Enhanced Sorting/Filtering**
    - Allow filtering participants by category or type.
    - Remember user sort/filter preferences in localStorage.

### Phase 2: Accessibility and Feedback
4. **Accessibility Improvements**
    - Ensure all interactive elements are keyboard accessible and include ARIA labels.
5. **User Feedback**
    - Show success/error toasts for voting actions. 
    - Display countdown timers and round status more prominently.

---

### Frontend Logic — Round Awareness and End-of-Round Display

1. **Round Type Awareness**
   - The frontend (`single-voting_round.php`) must detect and display whether the round is:
     - **Nomination Round** — show “Nomination Round”.
     - **Final Round** — show “Final Voting Round”.
     - **Custom Round** — show “Voting Round”.
   - Use the `_pb_round_state` meta to determine the current type and include a `data-round-type` attribute in the wrapper element for JS reference.

2. **Nominee and Place Awareness**
   - When a round ends:
     - **Nomination Round:** Display “This nomination round has ended. The top [X] nominees have been selected.” and list selected nominees in ranked order.
     - **Final or Custom Round:** Display “Voting has ended. The top [X] places are shown below.” and display ranked winners (1st, 2nd, 3rd, etc.).
   - Use the new meta fields `Total Nominees` and `Total Places` to display relevant counts.

3. **End-of-Round Logic**
   - The frontend should detect the end time compared to the current time.
   - Automatically disable vote buttons and show the final results once the round ends.
   - Replace the voting grid with the snapshot results message and rankings.
   - **Polling Behavior:** Once the round has ended (`hasEnded=true`), the frontend should stop polling for updates (or disconnect SSE), as results are frozen.

4. **Visual Sorting**
   - Continue supporting sorting options (Recent, Highest, Lowest, Tiebreaker).
   - After a round ends, default sorting mode should switch to “Highest” (final ranking).
   - Sorting can remain client-side after receiving the last cached data via SSE or REST.

5. **Integration Notes**
   - These improvements will integrate with the SSE system for real-time updates.
   - When the round closes, the frontend transitions to display the frozen snapshot generated by `PB_Voting_Service::snapshot_round_results()`.

---

## 3. Backend (PB_Voting_Service & PB_Voting_Sort) Improvements

### Phase 1: Performance and Robustness
1. **Caching Vote Totals and Rankings**
    - Cache results of expensive queries (e.g., `get_ranked_participants`) using WordPress transients or object cache.
    - Invalidate cache on vote submission or round changes.
    - Technical Note: Use `set_transient` keyed by `round_ref` and sort mode; clear cache in `handle_vote_submission` and after admin changes.
2. **Optimize Vote Table Access**
    - Add DB indexes for frequent queries (e.g., `last_vote_at`).
    - Batch-fetch vote records for multiple rounds if needed.

**Note:**  
The backend logic for participant calculation and result caching must be updated to:
- Ensure `calculate_participants()` always reflects the new admin panel logic for each round type (nomination, final, custom).
- Properly cache and invalidate participant and result lists when admin changes are made or votes are cast.

### Phase 2: Real-Time Data Delivery
3. **SSE Endpoint for Live Updates**
    - Add a REST or custom endpoint that streams vote/rank changes using SSE.
    - Push only changed data to clients for efficiency.
    - Technical Note: Use PHP's `header('Content-Type: text/event-stream')` and flush after each update; integrate with WordPress REST authentication.
4. **Move Rank Assignment to PB_Voting_Sort**
    - Refactor rank assignment logic from `PB_Voting_Service` to `PB_Voting_Sort` for single responsibility.

---

## Technical Notes
- **SSE Implementation**
    - Add a new endpoint (e.g., `/pb/v1/round/{round_ref}/stream`) that streams JSON events when vote totals or ranks change.
    - On the frontend, use `EventSource` to subscribe; update UI in real-time.
    - Ensure the endpoint checks for new votes/rankings and emits events only on change.
- **Caching Strategy**
    - Use `set_transient( 'pb_voting_results_{round_ref}_{sort}', $data, $timeout )`.
    - On vote or round update, call `delete_transient` to invalidate.
- **Extending Existing Classes**
    - Add new methods (e.g., `get_cached_ranked_participants`, `stream_live_updates`) to `PB_Voting_Service`.
    - Move sorting and ranking logic into `PB_Voting_Sort`, ensuring backward compatibility.

---

## Summary
These recommendations aim to incrementally enhance the voting platform's usability, performance, and real-time capabilities. Each step is designed to extend the current codebase, leveraging and improving the established class structure and templates.


## Data Model & Meta Fields

This section summarizes the key data model elements and meta fields used throughout the system:

- **Voting Rounds (`voting_round` post type):**
    - `_pb_round_state`: Type of round (`nomination`, `final`, `custom`)
    - `_pb_round_start`: Start datetime (site timezone)
    - `_pb_round_end`: End datetime (site timezone, derived/calculated)
    - `_pb_round_duration`: Duration (days)
    - `_pb_round_category_ids`: Array of category IDs (for nomination rounds)
    - `_pb_round_source`: Source round ID (for finals)
    - `_pb_round_manual_participants`: Array of participant IDs (for custom rounds)
    - `_pb_round_participants`: Cached participant IDs for the round
    - `_pb_round_ref`: Unique reference string for the round
    - `_pb_round_results`: Array of ranked post IDs (snapshot at round end)
    - `_pb_round_rankings`: Array of full ranking data (snapshot)
- **Participants (votable post types):**
    - `_pb_ref_id`: Unique reference string for each participant
    - `_pb_in_voting`: Boolean, is eligible for inclusion in voting rounds
- **Votes (DB Table `pb_votes`):**
    - `round_ref`, `post_ref`, `votes`, `last_vote_at`

**Meta Field Notes:**
- All meta fields are registered for REST API access where appropriate.
- Reference fields (`_pb_ref_id`, `_pb_round_ref`) are used for decoupling public-facing IDs from internal post IDs.
- Caching fields (`_pb_round_participants`, `_pb_round_results`, `_pb_round_rankings`) are used to store calculated or snapshot data for performance and consistency.


## API Contracts

This section documents the main REST API endpoints and their expected request/response formats:

- **POST `/pb/v1/vote`**
    - **Request Body:** `{ "round_ref": string, "post_ref": string }`
    - **Response:** `{ "round_ref": string, "post_ref": string, "total": int, "votes": int, "rank": int, "last_vote_at": string }`
    - **Behavior:** Increments the vote count for the given participant in the specified round, returns updated vote and rank info.

- **GET `/pb/v1/round/{round_ref}/totals?sort={sort_mode}`**
    - **Response:** `{ "round_ref": string, "results": [ { "post_id": int, "post_ref": string, "votes": int, "rank": int, "last_vote_at": string } ] }`
    - **Behavior:** Returns the ranked and/or sorted list of participants for the round, including all relevant voting data.

- **(Planned) GET `/pb/v1/round/{round_ref}/stream` (SSE)**
    - **Response:** Text/event-stream of JSON events: `{ "post_ref": string, "votes": int, "rank": int, ... }`
    - **Behavior:** Streams vote/rank updates in real-time as votes are cast.

**Meta Field REST API:**
- All relevant meta fields are exposed via the WordPress REST API for admin/editor UIs.


## Step-by-Step Implementation Plan

This phased plan details the rollout of improvements:

### **Phase A: Data Model & Meta Fields**
1. Define and register all necessary meta fields for voting rounds and participants.
2. Ensure REST API visibility for all meta fields required by frontend and admin UIs.
3. Implement caching meta fields for participants and results.

### **Phase B: Backend Logic & API**
1. Refactor participant calculation logic to match new admin panel and round type rules.
2. Implement caching for expensive queries (vote totals, rankings) using WordPress transients.
3. Add REST API endpoints for voting, round results, and (optionally) SSE streaming.
4. Add DB indices for vote table performance.
5. Move rank assignment logic to `PB_Voting_Sort`.

### **Phase C: Admin Panel & Frontend Template**
1. Redesign voting round meta box:
    - Always show selected participants and round reference.
    - Use start/end as source of truth for timing.
    - Multi-selects with checkbox UI.
    - Dynamic field visibility by round type.
    - Show total nominees/places.
2. Update admin overview page with live status, results, and actions.
3. Update `single-voting_round.php` to:
    - Show round type and awareness.
    - Display nominees/winners after round ends.
    - Automatically disable voting and stop polling/SSE after round end.
    - Show countdown and improved feedback.

### **Phase D: Real-Time & Performance**
1. Implement SSE endpoint for live frontend updates.
2. Integrate frontend with SSE (fallback to polling if unavailable).
3. Finalize caching and cache invalidation logic for all vote/result queries.
4. Add accessibility and UX improvements as detailed.


## Risks, Challenges & Mitigations

### Hosting Environment (SSE on Shared Hosting)

**Observation:**  
Current deployment on SiteGround shared hosting limits long-lived connections, such as those required for Server-Sent Events (SSE). SiteGround uses NGINX with PHP-FPM/FastCGI, which buffers and terminates requests after ~60 seconds. As a result, persistent streaming via SSE is unreliable.

**Implications:**  
- SSE connections may disconnect or time out after a short period (10–60 seconds).  
- PHP output buffering and proxy behavior prevent real-time flushing.  
- This environment does not allow disabling NGINX caching or buffering per endpoint.

**Mitigation Plan:**  
1. **Default to Polling with Optional SSE Fallback:**  
   Implement SSE structure in backend and frontend, but use polling (every 5–10s) as the reliable method. SSE should only be attempted if the environment supports persistent connections.  

2. **External or Future Upgrade Path:**  
   In future phases, consider:  
   - Hosting the SSE endpoint on a microservice (Node.js or Python) with proper headers.  
   - Migrating to a VPS or managed cloud instance that allows keep-alive and buffer control.  

3. **Implementation Strategy:**  
   - Keep frontend auto-fallback logic: try SSE, fallback to polling if disconnected.  
   - Document host limitations in project readme and developer notes.

This section identifies potential issues and outlines strategies to address them:

- **Output Buffering & SSE:**  
    - *Risk*: PHP output buffering and server/proxy configuration may delay or prevent real-time SSE delivery.
    - *Mitigation*: Use `flush()` and `ob_flush()` after each SSE event; recommend disabling output buffering for the SSE endpoint. Document server config requirements (e.g., Apache/nginx settings).

- **Hosting Timeouts:**  
    - *Risk*: Long-lived PHP connections for SSE may be terminated by shared hosting environments.
    - *Mitigation*: Set appropriate timeout headers, keep SSE events lightweight, and provide polling fallback for unsupported hosts.

- **Concurrency & Race Conditions:**  
    - *Risk*: Simultaneous votes may cause race conditions in vote tallying.
    - *Mitigation*: Use SQL-level atomic updates (`ON DUPLICATE KEY UPDATE`) and ensure DB indices are present. Test for edge cases.

- **Database Indices & Scaling:**  
    - *Risk*: Large number of votes/participants may slow down queries.
    - *Mitigation*: Add appropriate indices to the vote table (e.g., `round_ref`, `post_ref`, `last_vote_at`). Regularly prune or archive old vote data.

- **Security & Permissions:**  
    - *Risk*: Unauthorized access to admin or REST endpoints.
    - *Mitigation*: Use proper REST API authentication/authorization, nonce checks, and capability checks for all admin actions.

- **Timezone Consistency:**  
    - *Risk*: Discrepancies between server, site, and client timezones may cause incorrect round timing.
    - *Mitigation*: Store all times in site timezone or UTC; convert consistently on frontend and backend.

- **Snapshot Consistency:**  
    - *Risk*: Final snapshot of results may differ from live data if not captured atomically at round end.
    - *Mitigation*: Ensure `snapshot_round_results()` is called immediately and atomically when the round ends, and that frontend uses snapshot data after `hasEnded=true`.
