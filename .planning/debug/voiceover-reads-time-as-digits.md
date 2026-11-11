---
status: investigating
trigger: "VoiceOver reads 9:00 AM on the booking page as 'nine zero zero' instead of as a time (UAT test 2, gap G-08-2)"
created: 2026-11-11T11:30:00Z
updated: 2026-11-11T12:10:00Z
goal: find_root_cause_only
---

## Current Focus

hypothesis: The page hands VoiceOver a plain "H:MM" string with U+00A0 before AM/PM on every surface. Nothing in the page's markup or Chromium's accessibility tree explains "nine zero zero", and the macOS speech synthesizer reads that exact string as a time. So the fault is in VoiceOver's own text handling (NBSP-sensitive tokenizing, or the user's "Read numbers as: Digits" setting). Not provable without VoiceOver.
test: Manual probe page /private/tmp/claude-501/-Users-iantitus-Projects-ops-platform/c35af645-3d9b-4e77-9170-89eb4ffb7070/scratchpad/vo-time-probe.html (rows A to H), read with VoiceOver in Chrome, plus VoiceOver Utility > Verbosity > Text > "Read numbers as".
expecting: Row B/E digits but Row A/D time = NBSP is the cause. All rows incl. Row G's "10" digit-style = "Read numbers as: Digits" setting. Rows A/D also digits but Row G fine = VoiceOver does not treat "9:00" as a time in Chrome regardless of the space.
next_action: Return INVESTIGATION INCONCLUSIVE to the orchestrator with the probe and the safe fix direction.

## Symptoms

expected: Each time is announced with its day and time; focus moves to the confirmation; errors are read beside the field; the footer disclosures are announced as expandable. (Truth: VoiceOver reads every time as a time, 9:00 AM as "nine A M", not as separate digits.)
actual: VoiceOver walk otherwise passed ("8:00 AM, Tuesday, November 10, button", list of 8). The one problem: VoiceOver reads 9:00 AM as "nine zero zero" instead of as a time.
errors: none. Chrome on macOS with VoiceOver.
reproduction: UAT test 2, .planning/phases/08-schedule-and-booking/08-UAT.md, gap G-08-2.
started: found in UAT 2026-11-09/10.

## Eliminated

- hypothesis: A non-standard colon, a narrow no-break space (U+202F) or any other odd character is in the time text.
  evidence: Rendered the real Blade views (picker, check step, booked) with the real BookingPage::timeLabel/visitFrom via reflection; every non-ASCII code point in every time string is a single U+00A0 before AM/PM. Colon is U+003A. Carbon 'g:i A' gives a plain U+0020 before the swap.
  timestamp: 2026-11-11

- hypothesis: Chromium alters or splits the time text before it reaches the platform.
  evidence: CDP Accessibility.getFullAXTree on the rendered markup: button names are "9:00<U+00A0>AM, Tuesday, November 10" (one string, aria-hidden visible span excluded), the Check and Booked dd's StaticText is "9:00<U+00A0>AM". NBSP preserved, nothing else added.
  timestamp: 2026-11-11

- hypothesis: The U+00A0 makes the macOS speech synthesizer read "9:00 AM" as digits.
  evidence: `say` renders byte-identical audio for plain space, U+00A0 and U+202F in Samantha, Daniel, Karen, Moira, Tessa, Rishi, Fred, Kathy, Ralph and the other classic en voices, for 8:00 AM to 12:00 PM, in "9:00 AM, Tuesday, November 10" and bare. The system default voice always renders time-length audio (0.62 to 0.76 s, vs 1.2 to 1.6 s when digits are read). Caveat: this is the synthesizer fed directly, not VoiceOver, and the voice VoiceOver uses on Ian's Mac is not known.
  timestamp: 2026-11-11

- hypothesis: Only the 9 o'clock hour is mis-read by the page or the synthesizer.
  evidence: Markup is identical per hour. Synthesizer reads 8, 9, 10, 11, 12 AM/PM, 1 to 7 PM as times in every normalizing voice. The UAT text never says 8:00 AM was spoken correctly: "8:00 AM, Tuesday, November 10, button" is quoted as the shape of the phrase, and the user asks "whether every time is affected". The orchestrator's premise that 8:00 AM read fine is not in the evidence.
  timestamp: 2026-11-11

## Evidence

- timestamp: 2026-11-11T11:30:00Z
  checked: BookingPage::timeLabel (app/Livewire/BookingPage.php:273-276) and BookingSettings::timeText (app/Rooms/ScheduleAndBooking/BookingSettings.php:100)
  found: timeText is Carbon format 'g:i A'. timeLabel str_replace's U+0020 with U+00A0. Used at BookingPage.php:248 (picker times) and :266 (visit time, used for both chosenTime at :188 and visit at :189).
  implication: Every time on the page carries U+00A0 before AM/PM.

- timestamp: 2026-11-11T11:45:00Z
  checked: Every surface that shows a time (rendered the views in the app context with a stub lookup, scratchpad render.php)
  found: three surfaces. (1) Picker button: resources/views/components/booking/time.blade.php:2 has <span aria-hidden="true">9:00&nbsp;AM</span><span class="sr-only">9:00&nbsp;AM, Tuesday, November 10</span>; the name is the sr-only span. (2) Check your time: booking-page.blade.php:50 <dd>9:00&nbsp;AM</dd>. (3) You are booked: booking-page.blade.php:88 <dd>9:00&nbsp;AM</dd>. No <time> element, no aria-label, no split spans. The zone line "All times are in Central Time." carries no digits.
  implication: Same string on all three; the only structural difference is a button name built from hidden text (picker) versus a standalone definition-list value (check, booked).

- timestamp: 2026-11-11T11:50:00Z
  checked: Synthesizer reading (say, Samantha/Daniel/system default), durations and md5 of the audio
  found: "9:00 AM" == "nine A M" == "9 AM" byte-identical; "9:00" alone == "nine o'clock"; "9 00" == "nine zero zero"; "[[nmbr LTRL]]9:00 AM" (literal number mode) == "9 00 AM" (colon dropped, digits one at a time, 1.21 s vs 0.79 s). Plain/U+00A0/U+202F identical in default number mode.
  implication: "nine zero zero" is exactly what the synthesizer says when the colon is not treated as a time separator or when digits are read literally. It is not what it says for the page's string. So something between Chrome and the synthesizer (VoiceOver) changes the string or the number mode.

- timestamp: 2026-11-11T11:55:00Z
  checked: Web (Apple VoiceOver Utility docs, AppleVis guide)
  found: VoiceOver Utility > Verbosity > Text has "Read numbers as: Words | Digits"; Digits reads every digit separately ("one zero zero" for a hundred). No source found on VoiceOver and U+00A0 in times. A 2014 test (davidmacd.com, found via search, not fetched) shows VoiceOver's reading of AM/PM depends on spacing and case.
  implication: A user-level VoiceOver setting is a live candidate that no page change would fix. NBSP sensitivity inside VoiceOver is unconfirmed either way.

- timestamp: 2026-11-11T12:00:00Z
  checked: ~/Library/Preferences for VoiceOver prefs on this Mac
  found: only com.apple.VoiceOverTraining.plist; no com.apple.VoiceOver4.default.plist, so the VoiceOver settings cannot be read from here.
  implication: Cannot check "Read numbers as" or the VoiceOver voice from the shell.

## Resolution

root_cause: not confirmed. Verified: the page sends "H:MM<U+00A0>AM/PM" on all three surfaces and nothing else unusual; Chromium passes it through; the macOS synthesizer reads that string as a time in every normalizing voice tested. Inferred, unproven: VoiceOver's own handling (NBSP before AM/PM, or "Read numbers as: Digits") produces "nine zero zero".
fix: none (find_root_cause_only).
verification: n/a
files_changed: []
