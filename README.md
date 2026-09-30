# PlantFolio 🌿
*Where your plants tell their story.*

A mobile-responsive web platform where plant lovers build a profile for every plant they own, track its health, trade plants with others, and diagnose problems from a photo. Built for the **Plant Care & Exchange Community** coursework brief — B.Sc. (Hons) Computer Science, Wayamba University of Sri Lanka — and extended with two machine-learning features: plant species recognition and plant disease detection.

## Tech stack
| Layer | Technology |
|---|---|
| Front end | HTML, CSS (design tokens), JavaScript, mobile-first at 375 px |
| Back end | PHP 8+ (PDO), MySQL |
| ML service | Python 3.10+, Flask, TensorFlow/Keras (trained on Google Colab) |
| Local server | XAMPP (Apache + PHP + MySQL) |
| Version control | Git + GitHub (`main` ← `develop` ← `feature/*`) |

## Folder structure
```
plantfolio/
├── public/          # Web root — one PHP file per page
│   ├── assets/      #   css/, js/, img/
│   └── uploads/     #   user photos (git-ignored)
├── includes/        # db.php, auth.php, header.php, footer.php, flash.php
├── partials/        # plant-card.php, listing-card.php
├── config/          # config.example.php (config.php is git-ignored)
├── database/        # schema.sql, seed.sql, ERD
├── ml-service/      # Flask API, notebooks/, models/ (git-ignored)
├── docs/            # Proposal, wireframes, conventions, report drafts
└── .github/         # Pull-request template
```

## Run it locally
1. Install XAMPP (PHP 8+), Git and VS Code.
2. Clone into the XAMPP web folder:
   ```bash
   cd C:/xampp/htdocs        # macOS: /Applications/XAMPP/htdocs
   git clone https://github.com/<owner>/plantfolio.git
   cd plantfolio
   git checkout develop
   ```
3. Create your local config: `cp config/config.example.php config/config.php`, then edit it.
4. Start **Apache** and **MySQL** in the XAMPP Control Panel.
5. Create a database called `plantfolio` in phpMyAdmin and import `database/schema.sql` then `database/seed.sql` *(available after CORE-01/02)*.
6. Open <http://localhost/plantfolio/public/>.

ML service (Phase 3): see [`ml-service/README.md`](ml-service/README.md).

## How we work
Branching, commits and pull requests are described in [CONTRIBUTING.md](CONTRIBUTING.md).

## Team working agreement *(agreed in PLN-06 — fill in)*
- **Weekly review:** _day / time_
- **Communication channel:** _e.g. WhatsApp group / Discord_
- **Review turnaround:** reviewers respond to a PR within **3 days**
- **Task tracking:** PlantFolio Task Sheet — update your task status when a PR opens or merges

### Definition of Done
A task is done when:
- [ ] It meets the task's definition of done in the Task Sheet
- [ ] Code is on a `feature/*` branch and merged into `develop` through an approved PR
- [ ] The assigned Test Plan cases pass and results are logged
- [ ] Security checklist holds (prepared statements, escaped output, CSRF on POST, access checks)
- [ ] The page works at 375 px width
- [ ] Code is readable and commented; no secrets committed

## Team
| Member | Role |
|---|---|
| Ranshitha Attanayake | Team leader · lead developer · merges PRs |
| Member 2 | Front-end & responsive QA |
| Member 3 | Database, data content & accessibility |
| Member 4 | Security review & validation testing |
| Member 5 | QA lead · usability testing |
| Member 6 | ML reviewer · plant recognition |
| Member 7 | ML reviewer · disease detection content |
