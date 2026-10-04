# partials/

Reusable UI pieces rendered inside pages (wireframe p7). Not web-accessible.
Each partial expects one row-shaped array (e.g. `$plant`, `$listing`) so it works the same with mock data and with database rows.

| File | Shows | Used by | Task |
|---|---|---|---|
| `plant-card.php` | Photo (4:3), health badge, nickname (Lora), species (italic), next-care hint, collection | P04 My Garden, P09 Explore, P14 Public Profile | CORE-05 |
| `listing-card.php` | Photo, Swap/Free badge, title, town · distance · age, Looking for, owner + rating, interest count | P10 Exchange, P12 preview, P14 Public Profile | CORE-05 |
| `status-badge.php` | Health status chip — colour **and** word (Thriving, Stable, Needs attention, Sick) | Everywhere | CORE-05 |
| `care-row.php` | Plant thumbnail, task and interval, due/overdue tag, Mark done | P03 Dashboard, P05 Reminders | CORE-05 |
| `confidence-meter.php` | Labelled bar: High ≥ 80 % · Medium 60–79 % · Not sure < 60 % | P06, P07, P08 | CORE-05 |
| `rating.php` | Stars, average and count, e.g. ★ 4.9 (23) | P10, P11, P13, P14 | CORE-05 |
| `empty-state.php` | Illustration, one-line message and one clear next step (state S1) | P03, P04, P09, P10 | CORE-25 |
| `modal.php` | Dialog shell for S4 Rate, S5 Log update, S6 Confirm delete | P05, P13, all deletes | CORE-05 |

Add a new partial only when the same markup appears on two or more pages; otherwise keep it in the page.
