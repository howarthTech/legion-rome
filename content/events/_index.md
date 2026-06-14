---
title: "Events"
description: "Upcoming meetings and past gatherings at Post 5."
# Section gets HTML + RSS + a JSON feed (consumed by legion-rome-crm) +
# a combined .ics feed members can subscribe to.
outputs:
  - HTML
  - RSS
  - EventsJSON
  - EventsICS
cascade:
  # Every event page beneath this section also gets a .ics download.
  outputs:
    - HTML
    - EventCal
---

The post meets the **2nd Monday of each month at 6:00 PM** at The Farm.
Special events, holiday observances, and Auxiliary/SAL meetings are listed
below. Click any event for full details, location, and an option to add it
to your calendar.
