# partials/

Reusable UI pieces rendered inside pages (wireframe p7). Not web-accessible.
Each partial expects one row-shaped array (e.g. `$plant`, `$listing`) so it works the same with mock data and with database rows.

| File | Shows | Used by | Task |
|---|---|---|---|
| `plant-card.php` | Photo (4:3), health badge, nickname (Lora), species (italic), next-care hint, collection. With `'community' => true` (Explore): likes on the photo, owner and town instead of care and collection. Add `'show_owner' => false` (Public Profile): the collection instead of the owner | P04 My Garden, P09 Explore, P14 Public Profile | CORE-05 |
| `listing-card.php` | Photo, Swap/Free badge, title, town · distance · age, Looking for, owner + rating, interest count | P10 Exchange, P12 preview, P14 Public Profile | CORE-05 |
| `member-card.php` | Avatar, @username, name, town, plant count and rating; "2 matching plants" when a search matched their plants. With `'compact' => true`: avatar and one line, "42 plants · ★ 4.8 (14)". The whole card opens the Public Profile; never email or phone | P09 Explore (People tab and the "Plant lovers matching" row) | COM-01, COM-02 |
| `status-badge.php` | Health status chip — colour **and** word (Thriving, Stable, Needs attention, Sick) | Everywhere | CORE-05 |
| `care-row.php` | Plant thumbnail, task and interval, due/overdue tag, Mark done | P03 Dashboard, P05 Reminders | CORE-05 |
| `confidence-meter.php` | Labelled bar: High ≥ 80 % · Medium 60–79 % · Not sure < 60 % | P06, P07, P08 | CORE-05 |
| `rating.php` | Stars, average and count, e.g. ★ 4.9 (23) | P10, P11, P13, P14 | CORE-05 |
| `stars.php` | Five stars filled up to a score (a review's 1–5, or an average to the nearest whole star), one image for screen readers: "Rated 4.9 out of 5". `'size' => 'sm'` for small stars | P14 Public Profile (rating card, reviews), style guide; later the S4 Rate dialog | COM-05, COM-13 |
| `avatar.php` | Member photo, or initials on a tinted circle; sizes `sm` and `lg` | Header, listing cards, P13, P14 | CORE-05 |
| `auth-tabs.php` | The Sign up · Log in switch: two links drawn like the segmented control, the current page pressed | P02 (`register.php`, `login.php`) | CORE-07, CORE-09 |
| `auth-panel.php` | Community panel beside the form: a member's quote, their town and plant count, a plant picture | P02 (`register.php`, `login.php`) | CORE-07 |
| `empty-state.php` | Illustration, one-line message and one clear next step (state S1). An action takes `'href'` (a page) or `'url'` (a full address from `query_url()`) | P03, P04, P09, P10 | CORE-25 |
| `modal.php` | Dialog shell for S4 Rate, S5 Log update, S6 Confirm delete | P05, P13, all deletes | Week 5 · COM-12, CORE-19, CORE-20 (built with its JavaScript, as in the roadmap) |

Render a partial with the `partial()` helper from `includes/helpers.php`, naming the variable it expects:

```php
<?php foreach ($mock_plants as $plant): ?>
  <?php partial('plant-card', ['plant' => $plant]); ?>
<?php endforeach; ?>
```

Every partial starts with a comment saying which variable it expects. The shared ones can all be seen in `public/styleguide.php`; `auth-tabs` and `auth-panel` belong to one page pair and are seen on `register.php`.

Add a new partial only when the same markup appears on two or more pages; otherwise keep it in the page.
