---
paths:
    - 'resources/js/**'
---

# Js

## Status and date thresholds are computed backend-only

Never derive compliance status or apply day-count thresholds (14-day asset window, 30-day certificate window) in TypeScript. Status arrives precomputed from PHP (Asset::serviceStatus(), Certificate::expiringCerts); status-badge.tsx is pure presentation and must stay that way. Read dates from the shared `today` Inertia prop, never the client clock — a local clock can disagree with the server's business date at timezone boundaries. Do not add a TS copy of the PHP thresholds.
