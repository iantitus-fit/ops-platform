# Contractor operations research (2026-10-19)

Research input for the floor plan, not a decision. The decision it fed is "The project is the spine" in [PROJECT.md](../../.planning/PROJECT.md#key-decisions). Gathered 2026-10-18 and 2026-10-19 from trade publications, job postings, business-for-sale listings and vendor material. Vendor claims are marked (vendor). These numbers are context for design; examples and fixtures in the repo stay fictional.

Question asked (Ian): what do home-service contractors at roughly $5M to $50M and 20 to 50 staff need to run their business, how do their timelines work, and how should the platform scale up or down without being overbuilt?

## Who these businesses are

Home-service contractors repair and replace things on homes people already live in: roofs, HVAC systems, plumbing and electrical, landscapes, kitchens and baths. Mostly residential, some light commercial (an $11.6M HVAC firm for sale runs 70/30 residential to commercial [18]). They are not developers, new-home builders or commercial general contractors, which run on other software.

At about $5M and up the owner mostly runs managers (sales, production, office, finance) instead of working in the field. A roofing coach puts the common stall at $3M to $8M, while the owner is still the bottleneck [10, sponsored]. The field drives to the work every day.

## Four segments

| | Roofing and exteriors | HVAC, plumbing, electrical | Remodeling | Landscaping |
|---|---|---|---|---|
| Who is in the field | Commission-only sales reps (often 1099) who canvass and inspect [6]; subcontracted crews paid per square ($150 to $300, vendor) under a W-2 foreman [2, 3]; production manager schedules crews, subs and materials [4]; insurance supplement specialists [5] | W-2 techs in take-home trucks, about 3 residential calls a day, commission and on-call [13]; comfort advisors sell replacements on base plus commission [14]; about 6 CSR/dispatchers for 30 techs at one operator [19] | Sales, design and production run as three businesses with handoffs between them [28]; a PM builds two-week schedules for staff and subs and prices change orders [27]; firms over $3M subcontracted 61% of labor (2008) [23] | Account managers own a book of maintenance accounts and sell enhancements [35]; W-2 crews plus seasonal H-2B labor (37% of certified H-2B positions in FY2024) [33] |
| How long a job takes | Reroof install 1 to 2 days [7] (3 to 5 at one contractor [8]); insurance money 90 to 180 days (vendor, unconfirmed) [9] | Repair on the first visit; replacement install 2 to 8 hours [15] | Kitchen: 8.3 months planning, 4.5 months building; primary bath 5.4 and 3.2 (Houzz 2019) [25] | Ongoing; weekly routes; 12-month contracts [38] |
| How money comes in | Deposit; insurance pays the actual cash value first and releases held-back depreciation after proof of completion; supplements; checks endorsed to the mortgage lender [9] | Per ticket (about $350 repair, $11K to $20K replacement, vendor) [16]; maintenance memberships, 72% offer them, most at $200 or $300 a year [12]; financing [17] | Deposit, then payments by phase; change orders signed and paid before the work (2006 examples) [24] | Monthly contract billing; enhancements on top (enhancement revenue is 30% of maintenance revenue) [32] |
| What is unique | Insurance claim (carrier, adjuster, deductible, depreciation, supplements); aerial measurements; sub pay per square | Dispatch volume; memberships with recurring billing; equipment history per address; financing; on-call | Selections and allowances; payment schedules by phase; sub bids and invoices; work in progress | Routes; crew hours budgeted per property; seasonal and snow work; account-manager upsell |

## What every segment shares

- Lead intake and a pipeline
- An estimate or proposal with a signature
- One record per piece of work: photos, documents, notes, status
- A crew schedule with a simple phone view
- Materials and purchase orders
- Changes to scope after the sale (change orders, supplements)
- Several payments per piece of work, with what is still owed
- Estimated against actual cost per piece of work
- QuickBooks sync
- An owner scorecard

What differs by trade is a pack on top of these (the "What is unique" row above).

## The size dial

The rooms stay the same at every size. What changes is how many hands a piece of work passes through.

- **Owner-operator (1 to 10 people):** the owner fills every role. Front desk, a board of open work, quote, payment link.
- **Growing (10 to 20):** an office person and crews. Roles, a schedule, cost per piece of work starts to matter.
- **Established ($5M+, 20 to 50):** departments, KPIs per role, subcontractors, commissions, a trade pack.

Speed to answer is worth most at the small end: shops under 5 techs book 24% of calls against 59% at 25 or more, and 9% after 6 p.m. (ServiceTitan 2022, vendor; traced in `~/Projects/persona-research/06_offer/output/T1-offer.md`). Bigger shops have office staff on the phones.

## What breaks at $5M to $50M

- The owner as the bottleneck; overhead rising faster than margin at $3M to $10M in HVAC [21].
- Handoffs from sales to production [28].
- Knowing whether each piece of work made money; finance systems owners outgrow [31]; unfinished work tying up cash [28].
- Accountability: KPIs and scorecards, because job descriptions alone fail [36].
- Collections, especially insurance money.
- Tools that do not talk to each other: 62% of commercial landscapers run 7 or more tools, 29% run 10 or more (vendor) [34].

## Overbuild warnings

- The leading tools already go unused unless someone inside owns them [19].
- Only 21% of 181 commercial techs said any tool feels built for the field [22].
- Build a shared core plus trade packs, not depth everywhere.

## Compared with BLUEPRINT.md

Things a contractor at this size runs that BLUEPRINT has no area for yet: property (vehicles, equipment, insurance policies, inventory), subcontractors, recruiting and training, and profit per piece of work. Open for the floor plan; not decided.

## Reference products at this size

ServiceTitan for HVAC and plumbing (8,000 active customers at about $78K a year [20]); AccuLynx and JobNimbus for roofing; Buildertrend and JobTread for remodeling; Aspire and LMN for landscaping; ContractorHUB, roofing first, organized as People, Property, Process, Pipeline and Performance [41].

## Thin evidence

- Plumbing and electrical: no segment-specific data found.
- Insurance collection timing comes from one bookkeeping vendor's blog, unconfirmed by a trade source.
- Remodeling payment examples are from 2006; subcontracting shares from 2008.
- Seasonality outside landscaping is thin.
- Census counts of firms in the $5M to $50M band were not pulled (SUSB 2022 receipts-size table [40]; census.gov blocks automated fetching).

## Sources

1. https://www.roofingcontractor.com/articles/100416-2025-state-of-the-roofing-industry-report
2. https://www.roofingcontractor.com/articles/93087-when-it-comes-to-subcontracting-which-way-will-the-pendulum-swing
3. https://jobnimbus.com/blog/how-to-find-roofing-subcontractors (vendor)
4. https://storm-guard-careers.careerplug.com/jobs/3568358
5. https://talents.vaia.com/companies/ar-roofing/wichita/insurance-supplementing-specialist-151358624/
6. https://www.careerbuilder.com/job-details/roofing-sales-representative-springfield-mo--c076d133-d636-4cc0-b5be-9de47922075d
7. https://gaf.com/es-us/blog/your-home/how-long-does-it-take-to-replace-a-roof-281474980300551
8. https://bobharveyroofing.com/knowledge-center/how-long-roof-replacement-take
9. https://beancount.io/blog/2026/05/23/roofing-contractor-storm-restoration-insurance-claims-bookkeeping-acv-rcv-recoverable-depreciation-mortgage-endorsed-checks-supplements-aob-section-179-job-costing-workmanship-warranty-reserves-guide (vendor)
10. https://www.roofingcontractor.com/articles/102577-why-good-roofers-struggle-to-grow (sponsored)
11. https://becn.com/beaconbits/build-your-business/can-roofers-work-in-winter-5-steps-to-get-your-roofing-business-ready-for-the-slow-season
12. https://www.achrnews.com/articles/153922-homeowners-contractors-mutually-support-maintenance-agreements
13. https://constructionjobs.com/job/hvac-selling-technician-1784613645-6a5f0b0db335d
14. https://jobs.goldmedalservice.com/job/tinton-falls/comfort-advisor/46224/61903984176
15. https://www.trane.com/residential/en/products/air-conditioners/hvac-installation/
16. https://www.achrnews.com/articles/165733-residential-hvac-sales-slowdown-signals-shift-away-from-replacements
17. https://www.achrnews.com/articles/166259-10-takeaways-from-housecall-pros-latest-survey
18. https://searchfunder.com/post/185m-ebitda-hvac-services-company-in-wisconsin
19. https://searchfunder.com/post/field-management-software-servicetitan-v-housecall-pro-v-others
20. https://meritechcapital.com/blog/servicetitan-s-1-breakdown
21. https://www.achrnews.com/articles/166144-is-hvacs-middle-class-disappearing
22. https://www.achrnews.com/articles/166760-technicians-do-not-resist-technology-they-resist-technology-that-does-not-help-them
23. https://www.proremodeler.com/home/article/55183608/business-results-survey-workforce
24. https://www.proremodeler.com/news/article/55182639/the-payment-schedule
25. https://www.houzz.com/magazine/how-long-it-took-to-plan-and-complete-a-remodel-in-2019-stsetivw-vs~137254320
26. https://kbbonline.com/?p=159254
27. https://jobs.recorder.com/job/e8ac4768-394a-43ee-8104-788d075f24ab/project-manager-residential-remodeling
28. https://proremodeler.com/preventing-bottlenecks-sales-design-and-production
29. https://eyeonhousing.org/2025/11/remodelers-on-the-rise-how-renovation-is-reshaping-residential-construction/
30. https://www.businesswire.com/news/home/20240816083837/en/
31. https://www.jlconline.com/business/a-remodelers-guide-to-business-growth_o/
32. https://www.lawnandlandscape.com/article/wilson-360-2024-landscape-industry-benchmark
33. https://dol.gov/sites/dolgov/files/ETA/oflc/pdfs/H-2B_Selected_Statistics_FY2024_Q4.pdf
34. https://turfmagazine.com/aspires-2026-commercial-landscape-industry-report-is-now-live (vendor)
35. https://app.energyhire.com/job-board/account-manager-annapolis-md-616381c2-f769-4742-88c2-a47458e4b41b
36. https://www.lawnandlandscape.com/article/how-to-grow-a-landscaping-business-delegation-accountability-business-builders-summit
37. https://www.lawnandlandscape.com/article/state-of-the-industry-coast-to-coast/
38. https://guide.youraspire.com/docs/creating-fixed-payment-contracts-to-include-snow-services (vendor)
39. https://www.lawnandlandscape.com/news/work-force-research
40. https://census.gov/data/tables/2022/econ/susb/2022-susb-annual.html
41. https://contractorhub.app/ (vendor)
