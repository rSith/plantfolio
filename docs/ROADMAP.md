# PlantFolio — Complete Implementation Roadmap

**Project:** A web application where plant lovers can create digital plant portfolios, track care, and exchange plants locally.

**Tech Stack:** Python (Flask), MySQL, HTML5, CSS3, vanilla JavaScript. No React, no Java.

**Marking:** Functionality 70%, UX 15%, Code quality 10%, Documentation 5%.

---

## Phase 0: Foundations (1-2 weeks before Week 1)

Goal: learn just enough of each technology to build one tiny working page end to end.

### Tools to install
- Python 3 (recent version) and pip ✓
- MySQL Server and MySQL Workbench ✓
- Git ✓
- VS Code with Python and live-preview extensions
- Postman or Thunder Client (for testing routes)
- A modern browser with DevTools

### Practice exercise (Phase 0 checkpoint)
Build and delete a throwaway "Hello Plants" app: one Flask page with a form where you type a plant name, it saves to a MySQL table, and the page lists all saved plants. When this works, you have touched every layer PlantFolio uses.

---

## Phase 1: Planning and design (1 week, before Week 1)

### Step 1.1 - Lock the scope
MVP: sign up, log in, profiles, gardens, plant CRUD, photos, care history, health status, search/filter, care reminders, exchange listings, user ratings, mobile-responsive design.

Nice to have: messaging, email reminders, smart recommendations, AI exchange matching.

### Step 1.2 - Map the user journey
Sign up → Profile → Garden → Add plant → Log care → Browse community → Exchange listing → Rate

### Step 1.3 - List the pages
Landing, Sign up / Log in, Dashboard, Garden view, Plant profile, Add / edit plant, User profile, Explore / search, Exchange marketplace, Listing detail, My exchanges.

### Step 1.4 - Wireframes and design system
Sketch every page: mobile first, then desktop. Pick colour palette (green primary, neutral, health colours), two fonts, spacing scale.

### Step 1.5 - Design the database (8 tables)
- users (id, email, password_hash, name, bio, location, profile_photo)
- user_gardens (id, user_id, name)
- plant_species (id, common_name, scientific_name, water_frequency, light_needs)
- plants (id, user_garden_id, nickname, species_id, photo_path, health_status, last_watered, acquired_date)
- plant_history (id, plant_id, event_type, date, notes)
- plant_listings (id, plant_id, user_id, listing_type, status, description)
- user_interests (id, user_id, listing_id)
- user_ratings (id, from_user_id, to_user_id, listing_id, rating, comment)

Draw an ER diagram in MySQL Workbench.

### Step 1.6 - Plan the routes
Group by feature: auth, gardens, plants, care, search, listings, ratings.

### Step 1.7 - Plan the project structure
Use Flask blueprints: `auth/`, `gardens/`, `plants/`, `community/`, `exchange/`, `ai/`. Keep templates, static (CSS, JS), and uploads in separate folders. Store secrets in `.env` file, never in code.

**Checkpoint:** scope table, wireframes, ER diagram, route list in docs folder.

---

## Phase 2 (Weeks 1-2): Setup, database and authentication

### Week 1 - Foundation

1. Create GitHub repository with README, .gitignore, and docs folder.
2. Create Python virtual environment, install Flask, SQLAlchemy, MySQL driver, password-hashing library, Pillow, form library.
3. Create MySQL database and dedicated user (not root).
4. Build folder structure and application factory (creates and configures the app).
5. Write SQLAlchemy models for all 8 tables using Flask-Migrate for migrations.
6. Seed plant_species with 30-50 common house plants.
7. Build base template: navbar, footer, flash-message area, CSS design system. Every page extends it.

### Week 2 - Authentication

1. Sign-up page: validate input (email format, password length, unique email) on browser and server.
2. Store passwords as hashes only, using a hashing library.
3. Log in and log out using Flask sessions (Flask-Login recommended).
4. Protect private pages with a "login required" guard.
5. CSRF protection on every form.
6. User profile page: view and edit bio, location, profile photo.
7. Dashboard page: empty state saying "Create your first garden".
8. Landing page for logged-out visitors.
9. Check every page at phone width in DevTools.

**Checkpoint:** new user can register, log in, edit profile, log out; wrong passwords show clear message; private pages redirect to login; code on GitHub with meaningful commits.

---

## Phase 3 (Weeks 3-4): Gardens, plant profiles and care history

### Week 3 - Gardens and plant CRUD

1. Create, rename, delete gardens (decide: delete plants or move them?).
2. Add-plant form: nickname, species (dropdown), garden, acquired date, health status, photo.
3. Photo upload: accept only images, limit file size, resize with Pillow to standard width + thumbnail.
4. Plant profile page: large photo, species info, health badge, days since watered.
5. Edit and delete plant with confirmation.
6. Ownership check: users can only edit their own plants.

### Week 4 - Care history and dashboard

1. Log care: water, fertilize, repot, prune, or note. Water updates "last watered".
2. Care timeline on plant profile, newest first.
3. Photo gallery per plant (show growth over time).
4. Dashboard: garden cards with plant counts, "needs water" list.
5. Public view of user's collection (read-only for visitors).
6. Use JavaScript fetch for quick actions (log water without page reload).

**Checkpoint:** create garden, add 5 plants with photos, log care, edit/delete plant; another user cannot edit your plants; pages load quickly with 50+ plants.

---

## Phase 4 (Weeks 5-6): Search, filters, health status and reminders

### Week 5 - Search and community discovery

1. Explore page: public plants and users as cards.
2. Keyword search: plant nickname, species, owner name.
3. Filters: plant type, location, health status, garden. Combine filters.
4. Pagination (e.g. 12 cards per page).
5. Database indexes on filtered columns.
6. Empty states with suggestions.

### Week 6 - Health and care reminders

1. Health status badges everywhere (colour + text, accessible).
2. Reminder logic: compare last watered with species' water frequency; flag "due today" or "overdue".
3. Reminders panel on dashboard, count badge in navbar.
4. Optional: APScheduler for daily recalculation. In-app reminders are enough for MVP.
5. Health history: record changes in plant_history.

**Checkpoint:** search and filters work in combination; overdue plants show on dashboard; app works on phone.

---

## Phase 5 (Weeks 7-8): Plant exchange, ratings, polish and testing

### Week 7 - Exchange listings and reputation

1. Create listing from one of your plants: trade or gift, description, want, location.
2. Marketplace page with filters (type, location, species) and listing detail.
3. "I'm interested" button (one per user per listing; cannot express interest in own listing).
4. Owner view: list of interested users. Contact details revealed only after owner accepts.
5. Mark listing as exchanged or closed.
6. Ratings: after exchange, each side rates the other 1-5 stars + comment. Show average on profiles and listings.

### Week 8 - Polish, testing and documentation

1. Test every user story by hand (desktop and phone); write test results table.
2. Automated tests with pytest for auth, ownership checks, exchange rules.
3. Friendly error pages (404, 500) and clear form error messages.
4. UX pass: spacing, loading states, confirmations, accessible labels, alt text.
5. Code quality: remove unused code, consistent naming, comments, one feature per blueprint.
6. Documentation: README, ER diagram, route list, screenshots, user guide.
7. Load demo data: several users, gardens, plants, listings.

**Checkpoint (MVP complete):** full journey from sign-up to rated exchange works without errors.

---

## Phase 6 (bonus): AI plant recognition and disease detection

Only start after Phase 5 is fully working. Functionality is 70% of marks; AI is extra.

### Step 6.1 - Learn the basics (1 week)
- Image classification and transfer learning
- CNNs at a high level
- Keras in Google Colab with GPU
- Metrics: accuracy, precision, recall

### Step 6.2 - Plant recognition
1. Use pre-trained MobileNetV2.
2. Find or collect house-plant dataset matching plant_species.
3. Fine-tune top layers in Colab; save model.
4. Evaluate on test set; record accuracy and confusion matrix.

### Step 6.3 - Disease detection
1. Use PlantVillage dataset (54,000+ labelled leaf images).
2. Train/validation/test split; apply augmentation.
3. Train with transfer learning on MobileNetV2; compare setups.
4. Note limitation: PlantVillage photos are single leaves on plain backgrounds.

### Step 6.4 - Integrate into Flask
1. Load each model once when app starts.
2. Add "Identify this plant" to add-plant form (pre-fills species).
3. Add "Check health" on plant profile.
4. Resize photo with Pillow, run prediction, show top 3 results with confidence.
5. Below confidence threshold, say "not sure".
6. Save diagnosis to plant_history.

**Checkpoint:** both features work on new photos; accuracy and limitations documented with training charts.

---

## Phase 7: Deployment, submission and portfolio

1. Deploy Flask app and MySQL on Python-friendly platform (PythonAnywhere, Render, Railway).
2. Use production server, turn debug mode off, secrets as environment variables.
3. Test live site on phone and laptop: sign up, add plant, list it, express interest.
4. Submission: source code link, live link, README, ER diagram, test results, screenshots, ML report (if Phase 6 done).
5. Presentation: 5-minute demo following user journey, slide on architecture, slide on AI results.
6. Portfolio: pin repo on GitHub, add demo GIF to README, post on LinkedIn and IEEE Student Branch.

---

## Habits for every phase

- **Git:** commit small and often; one branch per feature; push daily.
- **Build vertically:** finish one feature end to end before starting the next.
- **Weekly review:** compare progress with roadmap; cut from "nice to have" if behind, never from MVP.
- **Ask for code by step:** "Give me code for Phase 3, Week 3, step 3 (photo upload)" — small pieces you understand.

## Security checklist

- Passwords hashed; secrets never on GitHub
- CSRF protection on every form; input validated on server
- Ownership checked on every edit and delete
- Uploads restricted by type/size, renamed safely
- Contact details shown only after owner accepts exchange

## Master checklist

- [ ] Phase 0: tools installed; practice app works
- [ ] Phase 1: scope, wireframes, ER diagram, route list
- [ ] Phase 2: sign up, log in, log out, profile
- [ ] Phase 3: gardens, plants, photos, care history
- [ ] Phase 4: search, filters, health, reminders
- [ ] Phase 5: exchange, ratings, tested, documented
- [ ] Phase 6: AI features (bonus)
- [ ] Phase 7: deployed, submitted, presented
