---
paths:
  - 'database/factories/**'
---

# Factories

## Money is integer cents
All money values are stored and passed as integer cents (`*_cents` integer columns, integer casts), never floats. Convert to dollars for display only.
