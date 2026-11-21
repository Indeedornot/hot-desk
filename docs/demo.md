# Demo outline

Aim for 3–5 minutes. Start the Compose stack before recording. Use the generated local accounts; keep passwords off-screen.

1. Sign in as a member. Select an office and tomorrow. Show desk equipment and
   reserve a desk. Open My reservations and the activity timeline.
2. Edit the reservation, then cancel it. Show that the desk is available again.
3. For an office whose hours include the current time, reserve today. Check in,
   then check out. Show the actor and timestamps in the activity history.
4. Sign in as an administrator. Create or edit an office, desk and account.
   Assign a permanent seat to a member. Show that the member cannot reserve
   another desk and that other members cannot reserve the assigned desk.
5. Remove the assignment. Show the searchable administrative event history and
   the confirmation email in Mailpit.
6. In Swagger UI, run GET /offices and show the HTTP 200 response.
7. Briefly show the controller → DTO → service → repository flow, the ERD,
   transactional audit/queue writes, and a passing test run.

Before recording, cancel leftover demo reservations and remove temporary
assignments through the UI. History intentionally remains visible.
