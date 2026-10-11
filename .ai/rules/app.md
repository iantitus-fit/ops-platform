---
paths:
  - 'app/**'
---

# App

## Money is integer cents
All money values are stored and passed as integer cents (`*_cents` integer columns, integer casts), never floats. Convert to dollars for display only.

## Business configuration is read only through ActiveConfig
Application code reads business configuration only through `App\Icm\ActiveConfig`, never from the ICM folder. Only `App\Icm\Loader`, run by the sync, reads the folder.
