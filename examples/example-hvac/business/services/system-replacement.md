---
type: service
name: Replacement system
slug: system-replacement
duration_minutes: 240
book_online: false
price_policy: starting_at
price_cents: 200000
intake:
  - key: unit_size
    ask: About what size is your current system?
    options: ["Small (under 3 tons)", "Large (3 tons and up)", "Not sure"]
  - key: condition
    ask: Is your current system still running?
    options: ["Still running", "Not running", "Not sure"]
  - key: ductwork
    ask: Is there ductwork in the house already?
    options: ["Yes", "No", "Not sure"]
price_sentences:
  - answers:
      unit_size: "Small (under 3 tons)"
    sentence: "For a smaller system, under 3 tons, a replacement system typically runs $2,000 to $5,000 installed."
    amounts_cents: [200000, 500000]
  - answers:
      unit_size: "Large (3 tons and up)"
    sentence: "For a larger system, 3 tons and up, a replacement system typically runs $5,000 to $10,000 installed."
    amounts_cents: [500000, 1000000]
price_default:
  sentence: "That's fine if you're not sure what you have. A replacement system typically runs $2,000 to $5,000 installed for a smaller unit and $5,000 to $10,000 for a larger one. We'll lock in an accurate quote once we see your setup."
  amounts_cents: [200000, 500000, 1000000]
---
We replace central air systems, heat pumps and furnaces. A replacement is quoted after we see the house, so a visit is set up with the owner rather than booked online.
The size of the current system, in tons, is the main thing that moves the price.
